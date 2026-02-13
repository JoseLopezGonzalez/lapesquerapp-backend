# Resumen: Problema, Solución y Contexto Backend — Productos, Variantes y Escaneo GS1

**Documento de referencia para cambios futuros en el backend (PesquerApp).**
Analiza el problema actual del ERP en gestión de productos/variantes y escaneo, la solución propuesta en `solucion.md`, y su contraste con el código actual del backend. Sirve como base para implementación y coordinación frontend/backend.

---

## 1. Resumen ejecutivo

|                                 | Descripción                                                                                                                                                                                                                                                                                                                                     |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Problema**              | El sistema identifica productos por**nombre** (con atributos embebidos), no tiene modelo producto base / variante, la caja no almacena atributos de variante, y el escaneo GS1-128 (GTIN-14 + peso + lote) no permite identificar de forma unívoca la caja ni obtener sus atributos (caladero, zona FAO, calibre, estado, presentación). |
| **Solución**             | Introducir**Product** (producto base) y **Product Variant** (combinación de atributos con **GTIN único**). La caja referencia a la variante (`variant_id`). Escaneo: GTIN → lookup en `product_variants` → atributos. Solo se crean variantes que realmente se producen, evitando explosión combinatoria.             |
| **Estado actual backend** | Producto por nombre; Box con `article_id`, `lot`, `gs1_128`, pesos; sin variantes, sin atributos en caja, sin endpoint de escaneo; conciliación por `lot` (string en Production).                                                                                                                                                       |

---

## 2. Análisis detallado del problema

Fuente: `resumen-problemas-implementacion-actual.md` (y verificación frente al código).

### 2.1 Gestión de productos y variantes

- **Distinción por nombre:** Los productos se diferencian por atributos (caladero, zona FAO, subzona FAO, calibre, estado, presentación) pero **solo mediante el nombre** (ej. "Merluza Cantábrico Calibre 3" vs "Merluza Gran Sol Calibre 3"). Consecuencias:

  - Duplicidades conceptuales (mismo producto base, distintos nombres).
  - Imposibilidad de normalizar: cambiar estructura de atributos implica tocar nombres.
- **Atributos que definen la variante (mínimo):** Origen (caladero), zona de captura (FAO), calibre, estado (fresco/congelado/cocido). Posible ampliación: presentación, certificación, etc.
- **Explosión combinatoria:** Modelar todas las variantes como combinaciones predefinidas llevaría a decenas o cientos de miles de filas. No es viable crear ni mantener tantas variantes.
- **UX:** Cuatro o más desplegables anidados (caladero → zona FAO → subzona → calibre → estado) son inaceptables; aumentan abandono y tiempo de tarea.

**Código actual:**
`Product` tiene `name`, `family_id`, `species_id`, `capture_zone_id`, `article_gtin`, `box_gtin`, `pallet_gtin`, `a3erp_code`, `facil_com_code`. No hay campos ni relaciones para calibre, estado ni presentación. No existe entidad “caladero” separada de la zona de captura. La zona de captura existe (`CaptureZone`), el resto de catálogos (caladero, subzona FAO, calibre, estado, presentación) **no están en el código**.

### 2.2 Unidad mínima de stock y atributos

- La unidad mínima de stock es la **caja**. Las cajas deben poder describirse con todos los atributos relevantes sin depender de un modelo de variantes con miles de combinaciones predefinidas.
- Algunos atributos **no dependen solo del lote/producción**: p. ej. **estado** (fresco, congelado, cocido). De una misma producción puede salir producto en distintos estados. Por tanto los atributos no pueden inferirse únicamente del lote.

**Código actual:**
Tabla `boxes`: `id`, `article_id`, `lot`, `gs1_128`, `gross_weight`, `net_weight`, `timestamps`. **No hay campos** para caladero, zona/subzona FAO, calibre, estado ni presentación. El requisito de “atributos en la caja” **no está implementado**.

### 2.3 Concepto de lote en el sistema actual

- **Lote = string de trazabilidad:** En el código, el lote es un **campo string** de la entidad **Production** (`lot`). Se usa en trazabilidad (nodos vinculados a recepción, pedidos, almacenes). No es una entidad que agrupe por “misma captura” ni por “mismos atributos”.
- **Mezcla en un mismo lote:** No se exige homogeneidad de atributos dentro de un lote. En un mismo lote pueden coexistir cajas con distintos caladeros, calibres, estados, etc. Por tanto **no se pueden inferir los atributos de la caja solo a partir del lote**.

**Código actual:**
`Production` tiene `lot` (string), `date`, `species_id`, `capture_zone_id`, `notes`, `diagram_data`, `opened_at`, `closed_at`. La conciliación agrupa cajas por `Box::where('lot', $this->lot)`; un mismo valor de lote puede corresponder a cajas de distintos productos y atributos.

### 2.4 Escaneo GS1-128 y origen de los atributos

- **Qué lee el sistema:** Escáner GS1-128 lee **GTIN-14** (código de la caja/producto), **peso neto** (AI 3103) y **lote** (AI 10). Esa es la información que llega desde el código de barras.
- **Atributos fuera del código:** El código no incluye caladero, zona FAO, calibre, estado ni presentación. **Solo con lo que lee la máquina no se obtienen esos atributos**; hay que definirlos en otro punto del flujo.
- **Ambigüedad:** Si se busca solo por GTIN-14 + lote, puede haber **varias cajas** con el mismo GTIN y el mismo lote pero con atributos distintos (p. ej. distinto calibre). El sistema no sabría qué caja concreta se ha escaneado.

**Código actual:**

- Box guarda el código completo en `gs1_128`.
- **No existe un endpoint** que reciba explícitamente el payload del escáner (GTIN-14 + peso + lote). Las cajas se crean/actualizan desde recepciones de materia prima, alta de palets, etc. (`PalletController`, `RawMaterialReceptionController`).
- La API de cajas (`BoxesController::index`) permite filtrar por `gs1128` (lista de códigos). No hay flujo documentado de “escaneo → identificación unívoca de caja + atributos”.

### 2.5 Origen de las etiquetas

- Las etiquetas pueden generarse desde la **webapp** o desde una **etiquetadora externa**; el cliente puede combinar ambos orígenes. El sistema debe seguir siendo válido en todos los casos.
- **Requisito:** El diseño debe ser sostenible con etiquetas desde webapp, desde etiquetadora externa o mezcla de ambos.

**Código actual:**
No hay lógica específica que distinga origen de etiqueta; las cajas se crean con `gs1_128` y `article_id` desde los flujos existentes.

### 2.6 Resumen de problemas en una frase (del documento original)

- Duplicidades y mala modelización por usar el nombre del producto para variantes.
- Imposibilidad práctica de modelar todas las variantes por explosión combinatoria.
- UX inviable con 4+ selectores anidados.
- Lote como string de trazabilidad que no agrupa por atributos y puede mezclar productos con atributos distintos.
- Atributos como el estado que no dependen solo del lote/producción.
- Escáner que solo aporta GTIN-14, peso y lote; los atributos no están en el código.
- Ambigüedad al identificar la caja escaneada cuando GTIN + lote no son únicos.
- Necesidad de que el sistema funcione con etiquetas generadas por webapp, por etiquetadora externa o por ambos.

---

## 3. Análisis detallado de la solución

Fuente: `solucion.md`.

### 3.1 Idea central

- **Separar producto base de variante:**

  - **Products:** nombre (ej. Merluza, Bacalao), descripción, tenant.
  - **ProductVariants:** combinación de atributos por producto, con **GTIN único** como identificador de escaneo.
- **Caja y variante:** La tabla de cajas referencia a **ProductVariant** (`variant_id`), no solo a Product. Así la caja “lleva” los atributos a través de la variante.

### 3.2 Estructura de tablas propuesta

```
Products
├─ id (PK)
├─ name (Merluza, Bacalao, etc.)
├─ description
└─ timestamps

ProductVariants
├─ id (PK)
├─ product_id (FK)
├─ gtin (UNIQUE)        ← identificador de escaneo
├─ state (Congelado, Descongelado, Fresco)
├─ origin (Mauritania, España, Marruecos, etc.)
├─ caliber (P4, T4, T3, 0,500-0,800kg, null)
├─ capture_zone_id (FK a zonas FAO)
└─ timestamps

UNIQUE KEY (product_id, state, origin, caliber, capture_zone_id)

Boxes
├─ id (PK)
├─ variant_id (FK a ProductVariants)
├─ serial_number (identificador único de esta caja física)
├─ lot_number (trazabilidad de producción)
├─ weight
├─ pallet_id (FK, si aplica)
└─ timestamps
```

### 3.3 Por qué la solución funciona (según el documento)

1. **Velocidad de identificación:** Escaneo GTIN → lookup directo en `product_variants` → O(1). Retorna toda la información de la variante en una consulta; sin ambigüedad por nombre.
2. **Genericidad por tenant:** El GTIN es el mismo para todos los tenants; cada tenant define sus propias variantes (combinaciones de atributos). No hay lógica hardcodeada en el código de barras.
3. **Normalización sin explosión:** Solo se crean variantes que **realmente se producen**. No se pregeneran todas las combinaciones (ej. 5×10×20×8×3). La unicidad `(product_id, state, origin, caliber, capture_zone_id)` evita duplicados.
4. **Trazabilidad clara:** `lot_number` sigue en la caja (trazabilidad administrativa). La variante define QUÉ es el producto; el lote define DE DÓNDE viene.
5. **Flexibilidad futura:** Cambiar estructura de atributos requiere migración de BD (una vez); no obliga a cambiar lógica de escaneo ni códigos de barras de clientes.

### 3.4 Lookup en escaneo

1. Escanear código GS1-128.
2. Extraer GTIN.
3. `SELECT * FROM product_variants WHERE gtin = ?`.
4. Retorna: id, product_id, state, origin, caliber, capture_zone_id.
5. Esa información identifica la variante y sus atributos; la caja se asocia por `variant_id` (y opcionalmente por `serial_number` si está en el código).

### 3.5 Relación con Boxes

- Al escanear: se lee GTIN → identifica variante; o se lee `serial_number` → identifica caja concreta.
- La solución evita EAV/atributos dinámicos: los atributos son fijos (state, origin, caliber, capture_zone), sin múltiples joins ni complejidad por tenant.

### 3.6 Cambios requeridos (según solucion.md)

1. **Migración BD:** Crear tabla `ProductVariants`, mover/mapear atributos desde el modelo actual.
2. **Rediseño UI:** Sustituir selector de “nombre de producto” por selector de atributos (p. ej. 4 dropdowns con filtros, sin exigir 4+ anidados si se diseña bien).
3. **Generación de GTIN:** Asegurar que cada variante tenga un GTIN único.
4. **Lógica de escaneo:** Cambiar lookup de nombre → lookup por GTIN (y, si se usa, por serial).

---

## 4. Comparativa: problema vs solución vs código actual

### 4.1 Modelo de datos

| Aspecto                     | Problema (requisitos)                       | Solución propuesta                                        | Código actual                                         |
| --------------------------- | ------------------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------ |
| Identificación de producto | No por nombre; evitar duplicidades          | Product (base) + ProductVariant (atributos + GTIN)         | Solo Product por nombre; sin variantes                 |
| Atributos de variante       | Deben poder describir la caja; no solo lote | En ProductVariant: state, origin, caliber, capture_zone_id | Product: species_id, capture_zone_id; Box: ninguno     |
| Unidad mínima de stock     | Caja con atributos                          | Box con variant_id (+ lot_number, serial_number, weight)   | Box con article_id, lot, gs1_128, pesos; sin atributos |
| Explosión combinatoria     | Evitar miles de variantes predefinidas      | Solo variantes que “realmente producen”                  | N/A (no hay variantes)                                 |
| Lote                        | String de trazabilidad; no define atributos | lot_number en Box; variante define QUÉ es                 | lot en Box; Production.lot; conciliación por lot      |

### 4.2 Escaneo e identificación

| Aspecto                           | Problema                                      | Solución                                          | Código actual                                                |
| --------------------------------- | --------------------------------------------- | -------------------------------------------------- | ------------------------------------------------------------- |
| Datos del escáner                | GTIN-14, peso, lote; sin atributos en código | GTIN → ProductVariant → atributos                | gs1_128 guarda código completo; filtro por gs1128 en listado |
| Identificación unívoca          | Ambigüedad cuando GTIN + lote no son únicos | GTIN único por variante; opcional serial por caja | No hay endpoint de escaneo; cajas por otros flujos            |
| Origen atributos en flujo escaneo | No definido                                   | Del catálogo ProductVariants vía GTIN            | No implementado                                               |

### 4.3 Beneficios antes/después (según solucion.md)

| Aspecto           | Antes                                   | Después                       |
| ----------------- | --------------------------------------- | ------------------------------ |
| Identificación   | Nombre (texto)                          | GTIN (numérico, único)       |
| Velocidad escaneo | Búsqueda por nombre                    | Lookup por GTIN (O(1))         |
| Genericidad       | Hardcodeada en nombres                  | Misma estructura BD por tenant |
| Duplicidades      | Sí (mismo producto, nombres distintos) | No (GTIN unique)               |
| Trazabilidad      | Confusa (atributos en nombre)           | Clara (variante ≠ lote)       |
| Escalabilidad     | Crece con nombres                       | Crece con variantes reales     |

---

## 5. Puntos de alineación y tensiones

### 5.1 Alineación

- **Atributos “en la caja”:** El problema pide que la caja sea portadora de atributos. La solución lo satisface **por referencia**: Box → `variant_id` → ProductVariant (atributos). La caja no repite campos, pero queda unívocamente caracterizada por la variante.
- **Sin explosión combinatoria:** La solución coincide con el requisito: solo variantes que realmente existen en BD.
- **Lote no define variante:** Se mantiene lote como trazabilidad; la variante define el “qué”.
- **Escaneo rápido:** GTIN → variante resuelve obtención de atributos sin búsquedas por nombre.

### 5.2 Tensiones y consideraciones

- **Etiquetadora externa:** Si la etiquetadora externa no usa el catálogo de GTINs del sistema (ProductVariants), el flujo “escaneo → GTIN → variante” puede fallar o requerir que el cliente configure la externa con los mismos GTINs. El documento de solución no detalla este caso; conviene definirlo (p. ej. política de GTINs, o flujo de alta de variantes/GTINs compartidos).
- **Serial (AI 21):** La solución usa `serial_number` en Box para identificar la caja concreta. Si las etiquetas vienen de una etiquetadora externa que no recibe el serial de la webapp, no se puede garantizar identificación unívoca por serial; en ese escenario se depende de GTIN (variante) + lote y, si hay varias cajas misma variante + mismo lote, podría persistir ambigüedad a menos que se exija GTIN distinto por “combinación que se etiqueta” (alineado con la solución).
- **Catálogos faltantes:** La solución habla de state, origin, caliber, capture_zone. En el backend actual solo existen Species y CaptureZone. Faltan (o hay que mapear): caladero/origin, calibre, estado (fresco/congelado/cocido), y posiblemente presentación. Las migraciones deben crear o reutilizar tablas/catálogos según convención del negocio.
- **Product actual:** Product tiene ya `capture_zone_id`, `box_gtin`, etc. El paso a “producto base sin atributos de variante” y “variante con GTIN” implica migración de datos y posiblemente mantener compatibilidad temporal (ej. box_gtin a nivel producto vs gtin a nivel variante).

---

## 6. Verificación frente al código (resumen)

- **Box:** `article_id`, `lot`, `gs1_128`, `gross_weight`, `net_weight`. Sin caladero, zona/subzona FAO, calibre, estado, presentación. Sin `variant_id` ni `serial_number`.
- **Product:** `name`, `family_id`, `species_id`, `capture_zone_id`, `article_gtin`, `box_gtin`, `pallet_gtin`, `a3erp_code`, `facil_com_code`. Sin modelo de variantes.
- **Production:** `lot` (string), resto de campos; conciliación por `Box::where('lot', $this->lot)`.
- **Catálogos:** Species, CaptureZone (zonas de captura). No hay tablas para caladero (como entidad distinta), subzona FAO, calibre, estado, presentación.
- **Escaneo:** No hay endpoint que reciba explícitamente payload del escáner (GTIN-14 + peso + lote). Las cajas se crean/actualizan en PalletController (alta/actualización de palets), RawMaterialReceptionController (recepciones), etc. BoxesController::index permite filtro `gs1128` (lista de códigos).

---

## 7. Recomendaciones para futuros cambios en el backend

1. **Migraciones:**

   - Crear tabla `product_variants` con product_id, gtin (UNIQUE), state, origin, caliber, capture_zone_id y UNIQUE(product_id, state, origin, caliber, capture_zone_id).
   - Añadir a `boxes`: `variant_id` (FK a product_variants), `serial_number` (nullable), y considerar renombrar `lot` → `lot_number` si se quiere alinear con la solución.
   - Definir catálogos faltantes (caladero/origin, calibre, estado, presentación si aplica) o campos controlados por dominio.
2. **Endpoint de escaneo:**

   - Implementar un endpoint que reciba el payload del escáner (GTIN-14, peso neto, lote y, si existe, serial).
   - Lookup: `ProductVariant::where('gtin', $gtin)->first()`; con la variante se tienen todos los atributos; con variant_id + lot (+ serial si está) se puede identificar o crear la caja según reglas de negocio.
3. **Creación/actualización de cajas:**

   - En flujos donde hoy se usa `article_id` + `lot` + `gs1_128`, pasar a `variant_id` (o derivarlo desde producto + atributos hasta que todo esté migrado), manteniendo `lot`/`lot_number` y `gs1_128` para trazabilidad y compatibilidad.
4. **Compatibilidad y migración de datos:**

   - Estrategia para productos actuales (nombre con atributos embebidos): decidir si se generan variantes a partir de productos existentes y se asigna un GTIN por variante, y cómo se rellenan `variant_id` en cajas existentes (por artículo + lote + lógica de negocio o manual).
5. **Etiquetadora externa y serial:**

   - Documentar si el sistema asume que los GTINs impresos por la etiquetadora externa pertenecen al catálogo de variantes.
   - Definir si la identificación unívoca de caja en escaneo depende siempre de GTIN+lot (+ serial cuando esté disponible) y qué hacer cuando hay varias cajas misma variante y mismo lote (p. ej. orden de selección o obligar serial cuando la etiqueta es de webapp).
6. **API y recursos:**

   - Exponer variantes en API (listado por producto, por GTIN, atributos).
   - Incluir en respuestas de cajas la variante (y sus atributos) además del producto, para que el frontend no dependa del nombre para mostrar atributos.

Este documento debe actualizarse cuando se implementen migraciones o nuevos endpoints para mantener una base clara sobre la que apoyar los cambios del backend en este tema.
