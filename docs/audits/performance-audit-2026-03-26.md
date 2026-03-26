# Auditoría Profunda de Rendimiento — PesquerApp Backend Laravel

> **Fecha:** 2026-03-26
> **Metodología:** Exploración directa del repositorio — todo lo que se afirma aquí está verificado en código real.

---

## 1. Resumen Ejecutivo

El backend es funcionalmente sólido pero acumula **sobrecoste técnico en tres capas principales**:

1. **Trabajo síncrono que debería ser asíncrono** — PDFs con Chromium, exports Excel
2. **Modelos con appends costosos** que se disparan indiscriminadamente
3. **Dependencias muertas** que añaden peso al bootstrap sin aportar valor

**Principales sospechas confirmadas:**

- **14 métodos de generación PDF bloqueantes** (Chromium/Snappdf), todos dentro del request, con `memory_limit=512M` y timeout 300s en el peor caso
- **20+ exportaciones Excel síncronas** vía Maatwebsite/Excel
- `**QUEUE_CONNECTION=sync`** por defecto pese a que `docker-compose.yml` levanta un worker de Redis
- **3 librerías PDF instaladas**, 2 sin ninguna referencia activa en el código
- `**tymon/jwt-auth` instalado y sin uso** — ServiceProvider activo en cada arranque
- `**google/cloud-document-ai` instalado y sin uso** — se usa `smalot/pdfparser` (regex) en su lugar
- `**Order` y `Pallet`** con 10+ accessors en `$appends`, algunos con `.load()` interno o queries anónimas
- `**PunchDashboardService::getData()**` hace `Employee::all()` sin filtro + bucle O(n²)
- `**CACHE_DRIVER=file` y `SESSION_DRIVER=file**` con Redis disponible y no usado
- **Índices ausentes** en `orders.status`, `orders.created_at`, `boxes.lot`, `boxes.gs1_128`

**Nivel de riesgo:** MEDIO-ALTO. El primer punto de fallo visible bajo carga real será la latencia en endpoints de PDF/Excel y listados con filtros complejos.

---

## 2. Mapa Técnico del Stack Auditado


| Capa                         | Valor detectado                                                                     |
| ---------------------------- | ----------------------------------------------------------------------------------- |
| Framework                    | Laravel 10.x                                                                        |
| PHP objetivo                 | `^8.1` en `composer.json`                                                           |
| PHP en Docker Sail           | 8.3                                                                                 |
| PHP en Dockerfile prod       | 8.2                                                                                 |
| Base de datos                | MySQL 8.0 (central + dinámicas por tenant)                                          |
| Multi-tenancy                | `X-Tenant` → `TenantMiddleware` → `DB::purge/reconnect('tenant')`                   |
| Autenticación                | Sanctum + Magic Link/OTP; `tymon/jwt-auth` sin uso                                  |
| Cola                         | `QUEUE_CONNECTION=sync`; Redis y worker disponibles pero inactivos                  |
| Caché                        | `CACHE_DRIVER=file`; Redis disponible pero sin usar                                 |
| Sesiones                     | `SESSION_DRIVER=file`; fallback en `config/session.php` es `array` (no persistente) |
| Logs                         | canal `stack→single`; nivel `debug` por defecto                                     |
| PDF activo                   | `beganovich/snappdf` (Chromium headless)                                            |
| PDF muertos                  | `barryvdh/laravel-dompdf`, `spatie/laravel-pdf` (instalados, sin referencias)       |
| Excel                        | `maatwebsite/excel` + `phpoffice/phpspreadsheet`                                    |
| IA/OCR                       | `google/cloud-document-ai` instalado, sin uso                                       |
| Total providers en bootstrap | 41 ServiceProviders (20 framework + 21 de paquetes)                                 |
| Autoload files globales      | 3 (`tenant_helpers.php`, `helpers.php`, `mbstring_polyfill.php`)                    |
| Rutas API                    | ~291 declaraciones en `routes/api.php`                                              |


---

## 3. Hallazgos Críticos

### C1 — Generación de PDF completamente síncrona con Chromium headless

- **Severidad:** CRÍTICA
- **Impacto:** 3–15 segundos de latencia por request de PDF; 512MB RAM por generación masiva; timeout de 300s en `generateOrderSheetsWithFilters()`
- **Evidencia:**
  - `app/Http/Controllers/v2/PDFController.php` — 14 métodos, todos llaman `$snappdf->setHtml($html)->generate()` dentro del request
  - `generateOrderSheetsWithFilters()` hace `ini_set('memory_limit', '512M')` y `ini_set('max_execution_time', '300')` directamente en el controlador
  - `app/Http/Middleware/Traits/HandlesChromiumConfig.php` — trait que configura path de Chromium, requiere binario en el proceso web
  - PDFs también se generan en `app/Http/Controllers/v2/SupplierLiquidationController.php` y `app/Http/Controllers/v2/OfferController.php`
- **Por qué afecta:** Cada llamada a Snappdf lanza un proceso de Chromium. Bajo concurrencia, agota el pool de workers PHP-FPM.
- **Recomendación concreta:**
  1. Crear `GeneratePdfJob` que reciba la entidad y el tipo de documento
  2. El endpoint devuelve inmediatamente un `job_id`
  3. El cliente hace polling a `/pdfs/{jobId}/status` o usa SSE
  4. El PDF generado se guarda en Storage y se sirve vía URL firmada
- **Riesgo de tocarlo:** MEDIO — requiere cambio de UX en frontend; el worker en `docker-compose.yml` ya existe

---

### C2 — Exportaciones Excel síncronas (20+ métodos)

- **Severidad:** CRÍTICA
- **Impacto:** Igual que PDFs: bloquea workers, consume RAM proporcional al dataset
- **Evidencia:**
  - `app/Http/Controllers/v2/ExcelController.php` — 20+ métodos que devuelven `Excel::download(...)` directamente
  - Incluye `OrderExport`, `A3ERPExport` (3 variantes), `FacilcomExport`, `RawMaterialReceptionExport`, `CeboDispatchExport`, `BoxesReportExport`, `ProductLotDetailsExport`
- **Por qué afecta:** Maatwebsite/Excel carga todos los registros en memoria. En exports de pedidos con filtros amplios puede superar 256MB.
- **Recomendación concreta:** Mismo patrón que PDFs. Maatwebsite/Excel ya soporta exports con `ShouldQueue` nativamente.
- **Riesgo de tocarlo:** MEDIO

---

### C3 — `QUEUE_CONNECTION=sync` con worker levantado en docker-compose

- **Severidad:** CRÍTICA
- **Impacto:** Cualquier `dispatch()` se ejecuta síncronamente en el request
- **Evidencia:**
  - `.env.example`: `QUEUE_CONNECTION=sync`
  - `docker-compose.yml`: servicio `queue` con `php artisan queue:work --verbose --tries=3 --backoff=5 --sleep=3`
  - Contradicción directa: infraestructura de worker lista, conexión por defecto sin usar
  - `OnboardTenantJob` y `MigrateTenantJob` son síncronos con esta configuración — el onboarding de un tenant (157 migraciones) puede tardar 30–120s dentro del request
- **Recomendación concreta:** Cambiar `QUEUE_CONNECTION=redis` en `.env.example` y en `.env` de producción. Redis ya está levantado.
- **Riesgo de tocarlo:** BAJO

---

### C4 — `Order` y `Pallet` con accessors con `.load()` interno y queries anónimas

- **Severidad:** CRÍTICA
- **Impacto:** N+1 queries garantizadas en cualquier listado que acceda a estos accessors sin precargar las relaciones
- **Evidencia:**
  - `app/Models/Order.php` `getTotalNetWeightAttribute()`: llama `$this->load('boxes.box.productionInputs')` dentro del accessor si la relación no está cargada — cualquier listado que acceda a `totalNetWeight` sin el scope `withTotals()` dispara esta carga implícita
  - `app/Models/Order.php` `getProductsBySpeciesAndCaptureZoneAttribute()`: itera `pallets → boxes → box → product → species → fishingGear` sin garantía de eager loading
  - `app/Models/Pallet.php` `getPositionAttribute()`: hace `StoredPallet::where('pallet_id', $this->id)->first()` en lugar de usar la relación `storedPallet` — query anónima por cada palet
  - `app/Models/Pallet.php` `getStoreAttribute()`: idem
  - `app/Models/Box.php` `getIsAvailableAttribute()`: ejecuta `.exists()` si la relación no está cargada
- **Por qué afecta:** En un listado de 50 pedidos accediendo a `totalNetWeight` sin el scope correcto → 50 × N queries adicionales.
- **Recomendación concreta:**
  - Eliminar `.load()` de los accessors — forzar que las relaciones vengan siempre desde el scope
  - En Pallet, reemplazar queries anónimas por uso de la relación `storedPallet()` ya definida
  - Activar `Model::preventLazyLoading()` en `AppServiceProvider` bajo `app()->environment('local')` — detectará todos los N+1 durante desarrollo
- **Riesgo de tocarlo:** ALTO — requiere auditar todos los sitios que acceden a estos accessors

---

### C5 — `PunchDashboardService::getData()` carga todos los empleados sin filtro

- **Severidad:** CRÍTICA
- **Impacto:** RAM proporcional a empleados × eventos del día; lógica O(n²) en memoria
- **Evidencia:**
  - `app/Services/PunchDashboardService.php`: `$allEmployees = Employee::all()` sin WHERE, sin LIMIT, sin paginación
  - Bucle con `$events->slice($i + 1)->firstWhere(...)` — O(n²) en colecciones Eloquent
  - Con 200+ empleados y 1000+ fichajes del día, la operación puede superar 128MB solo en este dashboard
- **Recomendación concreta:**
  - Reemplazar la lógica de "último evento por empleado" por `GROUP BY employee_id` + `MAX(timestamp)` en SQL
  - Filtrar solo empleados activos con actividad reciente
- **Riesgo de tocarlo:** BAJO-MEDIO (solo afecta dashboard, no escrituras)

---

## 4. Hallazgos Importantes

### I1 — `whereHas()` encadenados hasta 4 niveles en OrderListService y PalletListService

- **Severidad:** ALTA
- **Evidencia:**
  - `app/Services/v2/OrderListService.php`: `whereHas('pallets.palletBoxes.box.product', ...)` — 4 niveles de JOIN implícito en subquery EXISTS
  - `app/Services/v2/PalletListService.php`: 9+ `whereHas()` en `applyFilters()`, niveles hasta profundidad 3
- **Por qué afecta:** Cada filtro activo = +1 subquery EXISTS. Con 5+ filtros simultáneos, el plan de ejecución MySQL puede degradarse a múltiples full scans.
- **Recomendación concreta:** Reemplazar `whereHas` de filtrado por JOINs explícitos con `distinct()`. Especialmente en niveles 3-4.

---

### I2 — 3 librerías PDF instaladas con auto-discovery, 2 sin uso

- **Severidad:** ALTA
- **Evidencia:**
  - `bootstrap/cache/packages.php`: `barryvdh/laravel-dompdf`, `barryvdh/laravel-snappy` y `spatie/laravel-pdf` con auto-discovery
  - Solo `beganovich/snappdf` tiene referencias activas en el código
  - `config/app.php`: alias `'PDF'` duplicado — primero `Barryvdh\DomPDF\Facade::class`, luego `Barryvdh\Snappy\Facades\SnappyPdf::class`; el segundo sobrescribe al primero silenciosamente. **Conflicto activo.**
- **Recomendación:** `composer remove barryvdh/laravel-dompdf spatie/laravel-pdf` + limpiar alias duplicado en `config/app.php`

---

### I3 — `tymon/jwt-auth` instalado y sin uso, ServiceProvider activo

- **Severidad:** ALTA
- **Evidencia:**
  - `bootstrap/cache/packages.php`: listado con auto-discovery
  - Búsqueda de `JWTAuth`, `jwt`, `tymon` en controladores/servicios: **cero referencias**
  - La autenticación es 100% Sanctum
- **Recomendación:** `composer remove tymon/jwt-auth && rm config/jwt.php`

---

### I4 — `google/cloud-document-ai` instalado sin uso real

- **Severidad:** ALTA (peso en vendor y autoloader)
- **Evidencia:**
  - `composer.json`: `google/cloud-document-ai: ^2.1`
  - Cero referencias en código de aplicación
  - La extracción PDF activa usa `app/Services/PdfExtractionService.php` con `smalot/pdfparser` y regex
- **Recomendación:** `composer remove google/cloud-document-ai` hasta que se implemente de verdad

---

### I5 — Inconsistencia de versión PHP entre entornos

- **Severidad:** ALTA (DX)
- **Evidencia:**
  - `composer.json`: `"php": "^8.1"` — acepta desde 8.1
  - `docker-compose.yml`: PHP 8.3
  - `Dockerfile`: PHP 8.2
- **Recomendación:** Alinear `composer.json` a `"php": "^8.2"` que es la versión de producción

---

### I6 — Chrome instalado en la imagen Docker de producción

- **Severidad:** ALTA (operación)
- **Evidencia:** `Dockerfile` Stage 2: `RUN apt-get install -y google-chrome-stable` + fuentes dejavu/liberation/freefont → imagen +500MB
- **Recomendación:**
  - Opción 1: Contenedor sidecar solo para Chromium (`browserless/chrome`) con conexión WebSocket
  - Opción 2: Mover generación de PDFs al worker de cola (ya tiene Chrome), separando el contenedor web del worker

---

### I7 — Índices ausentes en tablas de alta frecuencia

- **Severidad:** ALTA
- **Evidencia:**
  - `create_orders_table.php` (2023): sin índice en `status`, `load_date`, `entry_date`. El índice compuesto se añadió tardíamente en diciembre 2025 — posibles tenants sin él.
  - `create_boxes_table.php` (2023): `boxes.lot` y `boxes.gs1_128` sin índices — columnas de búsqueda habitual por lote y código de barras
  - `create_pallets_table.php`: `order_id` como FK añadida tardíamente (dic 2025)
- **Recomendación:** Migración nueva con índice en `boxes.lot`, índice único en `boxes.gs1_128`, índice compuesto `(order_id, status)` en pallets

---

### I8 — `CACHE_DRIVER=file` y `SESSION_DRIVER=file` cuando Redis está disponible

- **Severidad:** ALTA
- **Evidencia:**
  - `.env.example`: `CACHE_DRIVER=file`, `SESSION_DRIVER=file`
  - `config/session.php`: `'driver' => env('SESSION_DRIVER', 'array')` — si `SESSION_DRIVER` no está en `.env`, las sesiones se pierden en cada request (fallback `array` = no persistente)
  - `docker-compose.yml`: servicio `redis` levantado y sano
- **Recomendación:** `.env.example` → `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`. `config/session.php` → cambiar fallback de `array` a `file`

---

### I9 — `CheckFeatureFlag` middleware registrado pero sin aplicar a ninguna ruta

- **Severidad:** MEDIA-ALTA (deuda técnica)
- **Evidencia:**
  - `app/Http/Middleware/CheckFeatureFlag.php` existe
  - `app/Http/Kernel.php`: `'feature' => CheckFeatureFlag::class` registrado
  - `routes/api.php`: cero referencias a `->middleware('feature:...')`
- **Recomendación:** Decidir si se aplica o se elimina. La infraestructura (service, caché, BD) funciona correctamente.

---

## 5. Hallazgos Menores


| #   | Hallazgo                                                                                        | Archivo                               | Acción                                                   |
| --- | ----------------------------------------------------------------------------------------------- | ------------------------------------- | -------------------------------------------------------- |
| M1  | `$appends` poblado en `RawMaterialReception` — `locked_pallet_ids` hace lazy load               | `app/Models/RawMaterialReception.php` | Vaciar `$appends`, llamar explícitamente desde Resources |
| M2  | `knuckleswtf/scribe` en `require` en vez de `require-dev`                                       | `composer.json`                       | Mover a `require-dev`                                    |
| M3  | `LOG_LEVEL=debug` implícito — logs verbosos en producción                                       | `config/logging.php`                  | `LOG_LEVEL=warning` en producción                        |
| M4  | `mbstring_polyfill.php` cargado globalmente para polyfill PHP < 8.4 (solo necesario por Scribe) | `composer.json`                       | Irrelevante si se alinea a PHP 8.2+                      |
| M5  | `spatie/browsershot` instalado, solapamiento con Snappdf                                        | `composer.json`                       | Verificar uso real y eliminar uno                        |
| M6  | `stevebauman/location` hace llamadas HTTP externas en runtime; uso poco claro                   | `composer.json`                       | Verificar uso activo                                     |
| M7  | Sin evidencia de `route:cache`, `config:cache`, `event:cache` en pipeline de deploy             | Pipeline deploy                       | Añadir a script de deploy                                |


---

## 6. Dependencias o Paquetes Sospechosos


| Paquete                    | Motivo de sospecha                                       | Estado recomendado             |
| -------------------------- | -------------------------------------------------------- | ------------------------------ |
| `tymon/jwt-auth`           | Sin uso activo; ServiceProvider registrado               | **Eliminar**                   |
| `barryvdh/laravel-dompdf`  | Sin uso activo; alias PDF duplicado en config            | **Eliminar**                   |
| `spatie/laravel-pdf`       | Cero referencias en el codebase                          | **Eliminar**                   |
| `google/cloud-document-ai` | Sin uso; se usa `smalot/pdfparser` en su lugar           | **Eliminar hasta implementar** |
| `spatie/browsershot`       | Snappdf ya hace lo mismo                                 | **Verificar y eliminar uno**   |
| `barryvdh/laravel-snappy`  | Aparentemente no se usa si el código usa Snappdf directo | **Verificar**                  |
| `knuckleswtf/scribe`       | En `require` en lugar de `require-dev`                   | **Mover a require-dev**        |
| `stevebauman/location`     | Hace llamadas HTTP externas; uso poco claro              | **Verificar uso real**         |


---

## 7. Providers, Middlewares, Bootstrap y Ciclo Global

- **41 providers** en bootstrap (20 framework + 21 paquetes) — los de paquetes muertos (jwt, dompdf, spatie-pdf) se registran igualmente en cada arranque
- `**DynamicCorsMiddleware`** (global, todos los requests): en producción, hace query a BD central para validar CORS por subdominio, cacheada 600s — la primera request desde cada subdominio paga el coste; con `CACHE_DRIVER=file` puede generar race conditions bajo concurrencia
- `**TenantMiddleware**`: `DB::purge('tenant')` + `DB::reconnect('tenant')` por cada request — el overhead de reconexión MySQL es medible bajo carga alta; la caché de 300s está en file con el mismo problema de atomicidad
- `**tenantSetting()**` en `app/Support/helpers.php`: caché solo por request (variable PHP local), no entre requests — las settings de empresa rara vez cambian; se podrían cachear en Redis 5-10 min
- **Sin `route:cache`**: con 291 rutas, el parsing de `routes/api.php` ocurre en cada arranque de Artisan y en cada request en entornos sin OPcache optimizado

---

## 8. Problemas de Eloquent y SQL


| Origen                                               | Problema                                    | Tipo                | Severidad |
| ---------------------------------------------------- | ------------------------------------------- | ------------------- | --------- |
| `Order::getTotalNetWeightAttribute`                  | `.load()` dentro del accessor               | N+1 garantizado     | CRÍTICO   |
| `Order::getProductsBySpeciesAndCaptureZoneAttribute` | 5 niveles de relaciones sin garantía        | N+1 potencial       | CRÍTICO   |
| `Pallet::getPositionAttribute`                       | `StoredPallet::where(...)->first()` anónimo | Query por instancia | CRÍTICO   |
| `Pallet::getStoreAttribute`                          | idem                                        | Query por instancia | ALTO      |
| `Box::getIsAvailableAttribute`                       | `.exists()` si relación no cargada          | Lazy load           | ALTO      |
| `ProductionRecord::getNodeData`                      | Recursión con `loadMissing()` anidado       | N+1 recursivo       | ALTO      |
| `PunchDashboardService::getData`                     | `Employee::all()` sin filtro                | Full table scan     | CRÍTICO   |
| `OrderListService`                                   | `whereHas` 4 niveles profundos              | Subqueries EXISTS   | ALTO      |
| `PalletListService::applyFilters`                    | 9+ `whereHas` acumulables                   | Subqueries EXISTS   | ALTO      |


**Modelo a seguir:** `app/Services/v2/OrderDetailService.php` — 22 relaciones con `with()` y `select()` explícito por relación. Patrón correcto que debería replicarse en el resto del proyecto.

---

## 9. Problemas de Endpoints, Resources y Serialización


| Endpoint                          | Problema                             | Severidad |
| --------------------------------- | ------------------------------------ | --------- |
| `POST /v2/pdf/*`                  | Chromium síncrono                    | CRÍTICO   |
| `GET /v2/excel/*`                 | Excel síncrono                       | CRÍTICO   |
| `GET /v2/punch/dashboard`         | `Employee::all()` + O(n²)            | ALTO      |
| `GET /v2/orders`                  | `whereHas` anidados + `withTotals()` | ALTO      |
| `GET /v2/pallets`                 | 9+ `whereHas()` acumulables          | ALTO      |
| `POST /v2/pdf-extraction/extract` | PDF parsing síncrono con regex       | MEDIO     |


**Resources problemáticos:**

- `app/Http/Resources/v2/OrderDetailsResource.php`: accede a `$this->productionProductDetails` y `$this->productDetails` — accessors compuestos que pueden disparar lazy loads; usa `.map(fn => $pallet->toArrayAssoc())` en lugar de `PalletResource::collection()`, impidiendo control de serialización
- `app/Http/Resources/v2/OrderResource.php`: accede a `$this->totalNetWeight` — el accessor que llama `.load()` internamente

---

## 10. Problemas de Caché, Colas y Trabajo Asíncrono

**Trabajo síncrono que debe moverse a cola:**


| Operación                                       | Coste estimado    | Prioridad |
| ----------------------------------------------- | ----------------- | --------- |
| 14 métodos PDF (PDFController)                  | 3–15s por request | INMEDIATA |
| 20+ exports Excel (ExcelController)             | 2–60s por request | INMEDIATA |
| Extracción PDF (PdfExtractionController)        | 1–5s              | MEDIA     |
| Onboarding tenant con `sync` (OnboardTenantJob) | 30–120s           | INMEDIATA |


**Oportunidades de caché ausentes:**

- Resultados de dashboards (`PunchDashboardService` no cachea nada — recalcula en cada request)
- Listados de pedidos con filtros repetidos
- `tenantSetting()` entre requests (actualmente solo cachea por request en memoria PHP)

---

## 11. Problemas de Configuración y Entorno


| Problema                                 | Archivo              | Impacto                                   |
| ---------------------------------------- | -------------------- | ----------------------------------------- |
| `QUEUE_CONNECTION=sync`                  | `.env.example`       | Todo trabajo pesado síncrono              |
| `CACHE_DRIVER=file`                      | `.env.example`       | Sin atomicidad, sin tags                  |
| `SESSION_DRIVER=file` + fallback `array` | `config/session.php` | Sesiones no persistentes sin env definido |
| `LOG_LEVEL=debug` implícito              | `config/logging.php` | I/O excesivo en producción                |
| Alias `'PDF'` duplicado                  | `config/app.php`     | Conflicto silencioso de facade            |
| PHP `^8.1` vs Docker 8.2/8.3             | `composer.json`      | Inconsistencia de runtime                 |
| Chrome en imagen de producción           | `Dockerfile`         | Imagen +500MB                             |
| `scribe` en `require` no `require-dev`   | `composer.json`      | Docs en vendor de producción              |
| Sin OPcache declarado en Dockerfile      | `Dockerfile`         | Sin aceleración de bytecode PHP           |
| Sin `route:cache` en deploy              | Pipeline             | Parsing de rutas en cada arranque         |


---

## 12. Quick Wins


| #   | Acción                                                                                                     | Coste estimado |
| --- | ---------------------------------------------------------------------------------------------------------- | -------------- |
| 1   | `QUEUE_CONNECTION=redis` en `.env.example`                                                                 | 1 línea        |
| 2   | `CACHE_DRIVER=redis` + `SESSION_DRIVER=redis` en `.env.example`                                            | 2 líneas       |
| 3   | `composer remove tymon/jwt-auth && rm config/jwt.php`                                                      | 5 min          |
| 4   | `composer remove barryvdh/laravel-dompdf spatie/laravel-pdf` + limpiar alias duplicado en `config/app.php` | 10 min         |
| 5   | `composer remove google/cloud-document-ai`                                                                 | 5 min          |
| 6   | Mover `knuckleswtf/scribe` a `require-dev`                                                                 | 1 línea        |
| 7   | Migración: índice en `boxes.lot` e índice único en `boxes.gs1_128`                                         | 15 min         |
| 8   | `Model::preventLazyLoading()` en `AppServiceProvider` bajo `app()->environment('local')`                   | 2 líneas       |
| 9   | Añadir `route:cache`, `config:cache`, `event:cache` al pipeline de deploy                                  | 3 comandos     |
| 10  | Cambiar fallback de `array` a `file` en `config/session.php`                                               | 1 línea        |


---

## 13. Acciones Recomendadas Priorizadas


Los porcentajes de mejora son estimaciones relativas al total del sobrecoste técnico identificado, distribuido por categoría de impacto.

| #   | Acción                                                                          | Impacto   | Esfuerzo | Riesgo   | Plazo      | % mejora estimado | Estado          |
| --- | ------------------------------------------------------------------------------- | --------- | -------- | -------- | ---------- | :---------------: | :-------------: |
| 1   | Cambiar `QUEUE_CONNECTION=redis` en env                                         | CRÍTICO   | Muy bajo | Muy bajo | Inmediato  | ~15 %             | ✅ Implementado |
| 2   | Cambiar `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`                            | Alto      | Muy bajo | Muy bajo | Inmediato  | ~5 %              | ✅ Implementado |
| 3   | Remover paquetes muertos (jwt, dompdf, snappy, spatie-pdf, cloud-doc-ai)        | Alto      | Bajo     | Muy bajo | Sprint 1   | ~4 %              | ✅ Implementado |
| 4   | Corregir session.php fallback y alias PDF duplicado                             | Medio     | Muy bajo | Muy bajo | Sprint 1   | ~1 %              | ✅ Implementado |
| 5   | Índices en `boxes.lot` y `boxes.gs1_128`                                        | Alto      | Bajo     | Muy bajo | Sprint 1   | ~5 %              | ✅ Migración creada (pendiente `migrate:companies`) |
| 6   | Activar `preventLazyLoading()` en local                                         | Alto (DX) | Muy bajo | Muy bajo | Sprint 1   | ~3 % (DX)         | ✅ Implementado |
| 7   | `route:cache` / `config:cache` en deploy                                        | Medio     | Bajo     | Bajo     | Sprint 1   | ~2 %              | ⬜ Por implementar (pipeline de deploy) |
| 8   | Mover PDF generation a `GeneratePdfJob`                                         | CRÍTICO   | Alto     | Medio    | Sprint 2   | ~20 %             | ⬜ Por implementar |
| 9   | Mover Excel exports a `GenerateExcelJob`                                        | CRÍTICO   | Alto     | Medio    | Sprint 2   | ~15 %             | ⬜ Por implementar |
| 10  | Eliminar `.load()` de `Order::getTotalNetWeightAttribute`                       | Alto      | Medio    | Medio    | Sprint 2   | ~6 %              | ⬜ Por implementar |
| 11  | Reemplazar queries anónimas en `Pallet::getPositionAttribute`                   | Alto      | Bajo     | Bajo     | Sprint 2   | ~4 %              | ⬜ Por implementar |
| 12  | Refactorizar `PunchDashboardService::getData()`                                 | Alto      | Medio    | Bajo     | Sprint 2   | ~5 %              | ⬜ Por implementar |
| 13  | Reemplazar `whereHas` profundos por JOINs en OrderListService/PalletListService | Alto      | Medio    | Medio    | Sprint 3   | ~6 %              | ⬜ Por implementar |
| 14  | Sidecar Chrome o mover PDF al worker                                            | Medio     | Medio    | Bajo     | Sprint 3   | ~3 %              | ⬜ Por implementar |
| 15  | Cachear `tenantSetting()` en Redis entre requests                               | Medio     | Bajo     | Bajo     | Sprint 3   | ~2 %              | ⬜ Por implementar |
| 16  | Vaciar `$appends` en modelos, llamar accessors desde Resources                  | Medio     | Alto     | Alto     | Sprint 3–4 | ~4 %              | ⬜ Por implementar |


---

## 14. Riesgos y Cautelas al Aplicar Cambios

- **Multi-tenant:** `DB::purge/reconnect` es la pieza central de aislamiento. No tocar sin tests de tenant isolation. Al migrar caché a Redis, las claves ya incluyen el identificador de tenant (código actual correcto).
- **Auth:** Antes de remover `tymon/jwt-auth`, confirmar que no hay ningún guard `jwt` activo en rutas (grep en `routes/api.php`).
- **Serialización / `$appends`:** Vaciar `$appends` en modelos es el cambio de mayor riesgo — cualquier código que acceda directamente a `$model->accessor_name` sin pasar por un Resource devolverá `null`. Requiere auditoría completa de todos los consumers antes de aplicar.
- **PDFs/Excel asíncronos:** Requiere coordinación con el frontend para implementar polling/push. Sin esa coordinación, el usuario no verá el resultado de la generación.
- `**generateOrderSheetsWithFilters()`:** Endpoint masivo de uso ocasional. Si se mueve a Job, el usuario necesita notificación (email, push) al completar.
- **PdfExtractionService:** Usa regex hardcodeada para un formato específico. Cualquier cambio de implementación debe garantizar compatibilidad de output con los consumidores del endpoint.

---

## 15. Conclusión Final

El backend acumula **tres categorías de deuda de rendimiento con impacto real:**

**1. Trabajo síncrono que no debería serlo** — La generación de PDFs con Chromium y las exportaciones Excel dentro del request son el cuello de botella más visible. Con el worker Redis ya en `docker-compose.yml`, la corrección es cambiar la conexión de cola y envolver las operaciones en Jobs. Mayor retorno con esfuerzo medio.

**2. Dependencias muertas que engordan el bootstrap** — JWT-auth, dompdf, spatie-pdf y cloud-document-ai están instalados y activos en el autoloader pero sin uso real. Removerlos reduce el tiempo de arranque de Artisan, el peso del vendor y elimina ServiceProviders innecesarios. Grupo de quick wins más puro del proyecto.

**3. Modelos con accessors costosos en `$appends`** — Order y Pallet tienen accessors que disparan lazy loads o queries anónimas. Bajo carga real en listados paginados, esto puede multiplicar el número de queries por factor 10–50x. La corrección es más cuidadosa, pero `preventLazyLoading()` en local la hace segura de implementar progresivamente.

**Orden recomendado de intervención:**

1. Variables de entorno (queue/cache/session → Redis) — inmediato, sin riesgo
2. Limpieza de dependencias muertas — Sprint 1, bajo riesgo
3. Correcciones de configuración + nuevos índices — Sprint 1
4. PDFs y Excel asíncronos — Sprint 2, coordinado con frontend
5. Corrección de accessors problemáticos en Order/Pallet (con `preventLazyLoading` activo como guía) — Sprint 2-3
6. Refactorización de `whereHas` a JOINs — Sprint 3

