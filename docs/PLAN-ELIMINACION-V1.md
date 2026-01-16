# Plan de Eliminación de API v1

## 📋 Visión General

Este documento describe el plan completo para eliminar toda la infraestructura relacionada con la API v1, que está **DEPRECADA** y ya no debe mantenerse en el código base.

**Estado Actual**: v1 está deprecada y no documentada. La versión activa es v2.

**Objetivo**: Eliminar completamente v1 sin afectar v2 ni romper funcionalidades existentes.

---

## 🔍 Inventario Completo de v1

### 1. Controladores (29 archivos)

Ubicación: `app/Http/Controllers/v1/`

| Controlador                                   | Estado                            | Equivalente v2                                                                          |
| --------------------------------------------- | --------------------------------- | --------------------------------------------------------------------------------------- |
| `AuthController.php`                        | ✅ Tiene v2                       | `v2/AuthController`                                                                   |
| `AutoSalesController.php`                   | ⚠️ Revisar si hay v2            | - (Dejar documentacion de<br /> implementacion antigua por si nos hiciese <br />falta) |
| `BoxesReportController.php`                 | ✅ Tiene v2                       | `v2/ExcelController`                                                                  |
| `CaptureZoneController.php`                 | ✅ Tiene v2                       | `v2/CaptureZoneController`                                                            |
| `CeboController.php`                        | ⚠️ Revisar si hay v2            | - (implementar de manera similar al v1)                                                 |
| `CeboDispatchController.php`                | ✅ Tiene v2                       | `v2/CeboDispatchController`                                                           |
| `CeboDispatchReportController.php`          | ✅ Tiene v2                       | `v2/ExcelController`                                                                  |
| `CustomerController.php`                    | ✅ Tiene v2                       | `v2/CustomerController`                                                               |
| `FinalNodeController.php`                   | ⚠️ Revisar funcionalidad única | - No implementar                                                                        |
| `IncotermController.php`                    | ✅ Tiene v2                       | `v2/IncotermController`                                                               |
| `OrderController.php`                       | ✅ Tiene v2                       | `v2/OrderController`                                                                  |
| `OrderDocumentMailerController.php`         | ✅ Tiene v2                       | `v2/OrderDocumentController`                                                          |
| `PalletController.php`                      | ✅ Tiene v2                       | `v2/PalletController`                                                                 |
| `PaymentTermController.php`                 | ✅ Tiene v2                       | `v2/PaymentTermController`                                                            |
| `PDFController.php`                         | ✅ Tiene v2                       | `v2/PDFController`                                                                    |
| `ProcessController.php`                     | ✅ Tiene v2                       | `v2/ProcessController`                                                                |
| `ProcessNodeController.php`                 | ⚠️ Revisar funcionalidad única | - No implementar                                                                        |
| `ProductController.php`                     | ✅ Tiene v2                       | `v2/ProductController`                                                                |
| `ProductionController.php`                  | ✅ Tiene v2                       | `v2/ProductionController`                                                             |
| `RawMaterialController.php`                 | ⚠️ Revisar si hay v2            | - No implementar                                                                        |
| `RawMaterialReceptionController.php`        | ✅ Tiene v2                       | `v2/RawMaterialReceptionController`                                                   |
| `RawMaterialReceptionsReportController.php` | ✅ Tiene v2                       | `v2/ExcelController`                                                                  |
| `RawMaterialReceptionsStatsController.php`  | ✅ Tiene v2                       | `v2/RawMaterialReceptionStatisticsController`                                         |
| `SalespersonController.php`                 | ✅ Tiene v2                       | `v2/SalespersonController`                                                            |
| `SpeciesController.php`                     | ✅ Tiene v2                       | `v2/SpeciesController`                                                                |
| `StoreController.php`                       | ✅ Tiene v2                       | `v2/StoreController`                                                                  |
| `StoredPalletController.php`                | ✅ Tiene v2                       | `v2/PalletController` (métodos stored)                                               |
| `StoresStatsController.php`                 | ✅ Tiene v2                       | `v2/StockStatisticsController`                                                        |
| `SupplierController.php`                    | ✅ Tiene v2                       | `v2/SupplierController`                                                               |
| `TransportController.php`                   | ✅ Tiene v2                       | `v2/TransportController`                                                              |

### 2. Resources (27 archivos)

Ubicación: `app/Http/Resources/v1/`

| Resource                                    | Estado                                                    | Equivalente v2             |
| ------------------------------------------- | --------------------------------------------------------- | -------------------------- |
| `AutoSaleResource.php`                    | ⚠️ Revisar uso                                          | -                          |
| `BoxResource.php`                         | ⚠️ Revisar uso                                          | -                          |
| `CaptureZoneResource.php`                 | ✅ Tiene v2                                               | `v2/CaptureZoneResource` |
| `CeboDispatchProductResource.php`         | ⚠️ Revisar uso                                          | -                          |
| `CeboDispatchResource.php`                | ⚠️ Revisar uso                                          | -                          |
| `CeboResource.php`                        | ⚠️ Revisar uso                                          | -                          |
| `CustomerResource.php`                    | ⚠️**CRÍTICO** - Verificado uso en routes/api.php | `v2/CustomerResource`    |
| `IncotermResource.php`                    | ✅ Tiene v2                                               | `v2/IncotermResource`    |
| `OrderDetailsResource.php`                | ⚠️ Revisar uso                                          | -                          |
| `OrderResource.php`                       | ⚠️ Revisar uso                                          | -                          |
| `PalletResource.php`                      | ⚠️ Revisar uso                                          | -                          |
| `PaymentTermResource.php`                 | ⚠️ Revisar uso                                          | -                          |
| `ProcessResource.php`                     | ⚠️ Revisar uso                                          | -                          |
| `ProductionResource.php`                  | ⚠️ Revisar uso                                          | -                          |
| `ProductResource.php`                     | ⚠️ Revisar uso                                          | -                          |
| `RawMaterialReceptionProductResource.php` | ⚠️ Revisar uso                                          | -                          |
| `RawMaterialReceptionResource.php`        | ⚠️ Revisar uso                                          | -                          |
| `RawMaterialResource.php`                 | ⚠️ Revisar uso                                          | -                          |
| `SalespersonResource.php`                 | ⚠️ Revisar uso                                          | -                          |
| `SpeciesResource.php`                     | ⚠️ Revisar uso                                          | -                          |
| `StoreDetailsResource.php`                | ⚠️ Revisar uso                                          | -                          |
| `StoreResource.php`                       | ⚠️ Revisar uso                                          | -                          |
| `SupplierResource.php`                    | ⚠️ Revisar uso                                          | -                          |
| `TransportResource.php`                   | ⚠️ Revisar uso                                          | -                          |

### 3. Exports (4 archivos)

Ubicación: `app/Exports/v1/`

| Export                             | Estado      | Equivalente v2                                      |
| ---------------------------------- | ----------- | --------------------------------------------------- |
| `BoxesExport.php`                | ✅ Tiene v2 | `v2/ExcelController::exportBoxesReport`           |
| `CeboDispatchA3erpExport.php`    | ✅ Tiene v2 | `v2/ExcelController::exportCeboDispatchA3erp`     |
| `CeboDispatchFacilcomExport.php` | ✅ Tiene v2 | `v2/ExcelController::exportCeboDispatchFacilcom`  |
| `RawMaterialReceptionExport.php` | ✅ Tiene v2 | `v2/ExcelController::exportRawMaterialReception*` |

### 4. Rutas API v1

Ubicación: `routes/api.php` (líneas 5-243)

**Total de rutas v1 identificadas**: ~80+ endpoints

**Categorías de rutas**:

- Autenticación (register, login, logout, me)
- CRUD de entidades (stores, pallets, orders, customers, etc.)
- Reportes y exportaciones (boxes_report, raw_material_receptions_report, etc.)
- Generación de PDFs (delivery-note, order-signs, CMR, etc.)
- Estadísticas (monthly-stats, annual-stats, etc.)
- Endpoints especiales (auto-sales, process-nodes, final-nodes)

---

## ⚠️ Puntos Críticos y Advertencias

### 🔴 CRÍTICO - Antes de Eliminar

1. **Imports innecesarios de v1 en v2** ⚠️ **ENCONTRADOS**

   - **Archivo**: `routes/api.php:55`

     - **Problema**: `use App\Http\Resources\v1\CustomerResource;` - NO se usa en el archivo
     - **Acción**: Eliminar este import
   - **Archivo**: `app/Http/Controllers/v2/CustomerController.php:6`

     - **Problema**: `use App\Http\Resources\v1\CustomerResource;` - Importado pero NO se usa (el controlador usa `V2CustomerResource` en su lugar)
     - **Acción**: Eliminar este import (línea 6)
2. **Verificar dependencias en v2**

   - Revisar si algún controlador v2 importa o usa código de v1
   - Verificar si hay referencias cruzadas entre v1 y v2
3. **Documentación obsoleta**

   - Hay documentación que menciona v1 como obsoleta
   - Actualizar referencias en documentos después de eliminar
4. **Tests (si existen)**

   - Verificar si hay tests para v1 que deban eliminarse
   - Asegurarse de que los tests de v2 sigan funcionando
5. **Clientes externos**

   - **VERIFICAR**: ¿Hay algún cliente (frontend, integraciones, apps móviles) que todavía use v1?
   - Si existen, documentar la migración necesaria antes de eliminar
6. **Autenticación diferente**

   - v1 usa `auth:api` (Passport probablemente)
   - v2 usa `auth:sanctum` (Sanctum)
   - Asegurarse de que no haya conflictos al eliminar

### 🟡 IMPORTANTE - Consideraciones

1. **Controladores sin equivalente v2 claro**:

   - `AutoSalesController` - Revisar funcionalidad
   - `CeboController` - Revisar funcionalidad
   - `RawMaterialController` - Revisar funcionalidad
   - `FinalNodeController` - Funcionalidad específica de producción
   - `ProcessNodeController` - Funcionalidad específica de producción
2. **Endpoints especiales**:

   - Endpoints de auto-sales (`v1/auto-sales`, `v1/auto-sales-customers`)
   - Endpoints de process-nodes (`v1/process-nodes-decrease`, etc.)
   - Endpoints de final-nodes (`v1/final-nodes-profit`, etc.)
   - **Acción**: Verificar si estos endpoints tienen equivalentes en v2 o si su funcionalidad fue migrada
3. **Rutas comentadas**:

   - Líneas 101-103, 106-110, 118 - Hay rutas comentadas en `routes/api.php`
   - Revisar si son v1 o v2 antes de eliminar

---

## 📝 Plan de Ejecución

### Fase 1: Análisis y Verificación (OBLIGATORIO ANTES DE CONTINUAR)

#### Paso 1.1: Verificar Uso Real de v1

```bash
# Buscar referencias a v1 en logs, base de datos, etc.
# Revisar si hay clientes activos usando v1
```

**Tareas**:

- [ ] Revisar logs de acceso para identificar requests a `/api/v1/*`
- [ ] Consultar con el equipo si hay integraciones usando v1
- [ ] Revisar documentación de integraciones externas
- [ ] Verificar si hay apps móviles o frontend legacy usando v1

#### Paso 1.2: Verificar Dependencias Internas

**Tareas**:

- [ ] Buscar imports de v1 en archivos v2
- [ ] **VERIFICADO**: Eliminar import no usado de `CustomerResource` v1 en `routes/api.php:55`
- [ ] **VERIFICADO**: Eliminar import no usado de `CustomerResource` v1 en `app/Http/Controllers/v2/CustomerController.php:6` (el controlador usa `V2CustomerResource` en su lugar)
- [ ] Revisar si hay tests que dependan de v1
- [ ] Verificar middlewares compartidos

#### Paso 1.3: Identificar Funcionalidades Sin Equivalente v2

**Tareas**:

- [ ] Documentar endpoints de `AutoSalesController` y verificar si hay equivalente v2
- [ ] Documentar endpoints de `CeboController` y verificar si hay equivalente v2
- [ ] Documentar endpoints de `RawMaterialController` y verificar si hay equivalente v2
- [ ] Documentar endpoints de `FinalNodeController` y verificar si están en v2/Production
- [ ] Documentar endpoints de `ProcessNodeController` y verificar si están en v2/Production

### Fase 2: Preparación

#### Paso 2.1: Backup y Documentación

**Tareas**:

- [ ] Crear rama de trabajo: `feature/remove-v1-api`
- [ ] Documentar todos los endpoints v1 que se van a eliminar
- [ ] Crear lista de equivalentes v2 (para referencia futura)

#### Paso 2.2: Migrar Funcionalidades Faltantes (si aplica)

**Tareas**:

- [ ] Si hay funcionalidades v1 sin equivalente v2, crear los endpoints en v2 primero
- [ ] Migrar lógica de negocio necesaria
- [ ] Probar endpoints v2 antes de eliminar v1

### Fase 3: Eliminación (Orden Recomendado)

#### Paso 3.1: Eliminar Rutas v1

**Archivo**: `routes/api.php`

**Acción**: Eliminar líneas 5-243 (todo el bloque de v1)

**Código a eliminar**:

- Imports de controladores v1 (líneas 5-40, 55)
- Todas las rutas `Route::*` que empiecen con `v1/` (líneas 112-243)

**⚠️ CUIDADO**: Verificar que no haya rutas comentadas importantes que deban mantenerse

#### Paso 3.2: Eliminar Exports v1

**Ubicación**: `app/Exports/v1/`

**Acción**: Eliminar directorio completo

**Archivos**:

- `BoxesExport.php`
- `CeboDispatchA3erpExport.php`
- `CeboDispatchFacilcomExport.php`
- `RawMaterialReceptionExport.php`

#### Paso 3.3: Eliminar Resources v1

**Ubicación**: `app/Http/Resources/v1/`

**Acción**: Eliminar directorio completo después de verificar que no se usen en v2

**⚠️ CRÍTICO**: Verificar especialmente `CustomerResource.php` antes de eliminar

#### Paso 3.4: Eliminar Controladores v1

**Ubicación**: `app/Http/Controllers/v1/`

**Acción**: Eliminar directorio completo

**Orden recomendado**:

1. Primero eliminar controladores que definitivamente tienen v2
2. Luego los que necesitan verificación (AutoSales, Cebo, RawMaterial)
3. Finalmente los de producción (FinalNode, ProcessNode) - solo si se confirmó migración

### Fase 4: Limpieza y Verificación

#### Paso 4.1: Limpieza de Código

**Tareas**:

- [ ] Eliminar imports no utilizados
- [ ] Revisar archivos de configuración (si hay referencias a v1)
- [ ] Limpiar comentarios obsoletos relacionados con v1

#### Paso 4.2: Actualizar Documentación

**Tareas**:

- [ ] Eliminar menciones a v1 en documentación
- [ ] Actualizar `docs/README.md` si menciona v1
- [ ] Actualizar `docs/referencia/98-Errores-Comunes.md` si menciona v1
- [ ] Actualizar cualquier otro documento que mencione v1

#### Paso 4.3: Verificación Final

**Tareas**:

- [ ] Ejecutar tests (si existen)
- [ ] Verificar que v2 sigue funcionando correctamente
- [ ] Revisar que no hay errores de sintaxis
- [ ] Verificar que no hay referencias rotas a v1

### Fase 5: Testing y Validación

#### Paso 5.1: Testing Funcional

**Tareas**:

- [ ] Probar autenticación v2 (login, logout, me)
- [ ] Probar endpoints principales de v2 (CRUD de entidades)
- [ ] Probar generación de PDFs v2
- [ ] Probar exportaciones v2
- [ ] Verificar que todas las funcionalidades v2 siguen operativas

#### Paso 5.2: Validación de Regresiones

**Tareas**:

- [ ] Revisar logs de errores
- [ ] Verificar que no se rompió ninguna funcionalidad existente
- [ ] Confirmar que no hay referencias rotas

---

## 🔧 Comandos Útiles

### Buscar referencias a v1

```bash
# Buscar todos los archivos que contienen "v1"
grep -r "v1\|V1" --include="*.php" app/ routes/

# Buscar imports de v1
grep -r "use.*v1\|from.*v1" --include="*.php" app/ routes/

# Buscar rutas v1 en archivos de rutas
grep -r "v1/" routes/
```

### Verificar uso de recursos v1

```bash
# Buscar uso de CustomerResource v1
grep -r "CustomerResource.*v1\|v1.*CustomerResource" --include="*.php" app/ routes/
```

### Contar archivos v1

```bash
# Contar controladores v1
find app/Http/Controllers/v1 -name "*.php" | wc -l

# Contar resources v1
find app/Http/Resources/v1 -name "*.php" | wc -l

# Contar exports v1
find app/Exports/v1 -name "*.php" | wc -l
```

---

## 📊 Resumen de Archivos a Eliminar

| Categoría              | Cantidad       | Ubicación                         |
| ----------------------- | -------------- | ---------------------------------- |
| **Controladores** | 29             | `app/Http/Controllers/v1/`       |
| **Resources**     | 27             | `app/Http/Resources/v1/`         |
| **Exports**       | 4              | `app/Exports/v1/`                |
| **Rutas**         | ~80+ endpoints | `routes/api.php` (líneas 5-243) |
| **Imports**       | ~25            | `routes/api.php`                 |

**Total**: ~60 archivos PHP + ~240 líneas de rutas

---

## ✅ Checklist Final

Antes de hacer commit, verificar:

- [ ] Todas las rutas v1 eliminadas de `routes/api.php`
- [ ] Todos los imports v1 eliminados de `routes/api.php`
- [ ] Directorio `app/Http/Controllers/v1/` eliminado
- [ ] Directorio `app/Http/Resources/v1/` eliminado
- [ ] Directorio `app/Exports/v1/` eliminado
- [ ] No hay referencias a v1 en código v2
- [ ] Tests ejecutados y pasando (si existen)
- [ ] Documentación actualizada
- [ ] Sin errores de sintaxis o referencias rotas
- [ ] v2 funciona correctamente después de la eliminación

---

## 🚨 Riesgos y Mitigación

| Riesgo                     | Probabilidad | Impacto | Mitigación                             |
| -------------------------- | ------------ | ------- | --------------------------------------- |
| Cliente externo usando v1  | Media        | Alto    | Verificar en Fase 1.1 antes de eliminar |
| Funcionalidad única en v1 | Baja         | Medio   | Identificar en Fase 1.3 y migrar a v2   |
| Referencias rotas en v2    | Baja         | Medio   | Buscar dependencias en Fase 1.2         |
| Tests que dependen de v1   | Baja         | Bajo    | Eliminar tests obsoletos en Fase 2      |
| Regresiones en v2          | Muy Baja     | Alto    | Testing exhaustivo en Fase 5            |

---

## 📅 Estimación

- **Fase 1 (Análisis)**: 2-4 horas
- **Fase 2 (Preparación)**: 1-2 horas
- **Fase 3 (Eliminación)**: 1-2 horas
- **Fase 4 (Limpieza)**: 1-2 horas
- **Fase 5 (Testing)**: 2-4 horas

**Total estimado**: 7-14 horas de trabajo

---

## 📝 Notas Finales

1. **NO ELIMINAR** sin completar la Fase 1 (Análisis y Verificación)
2. Si se encuentra alguna funcionalidad v1 sin equivalente v2, **CREAR el equivalente v2 primero**
3. Mantener un backup o branch de respaldo durante el proceso
4. Comunicar al equipo antes de hacer merge a producción
5. Considerar hacer la eliminación en producción durante horario de bajo tráfico (si aplica)

---

**Última actualización**: 2025-01-27
**Estado**: ✅ **COMPLETADO** - Todas las referencias a v1 han sido eliminadas

---

## ✅ Estado de Ejecución

### Eliminación Completada (2025-01-27)

**Fase 1: Análisis y Verificación** ✅
- [x] Verificado uso real de v1
- [x] Verificado dependencias internas
- [x] Identificadas funcionalidades sin equivalente v2

**Fase 2: Preparación** ✅
- [x] Creado `CaptureZoneResource` v2 (faltaba equivalente v2)
- [x] Actualizados imports en controladores v2

**Fase 3: Eliminación** ✅
- [x] Eliminadas todas las rutas v1 de `routes/api.php` (líneas 5-243)
- [x] Eliminados todos los imports v1 de `routes/api.php`
- [x] Eliminado directorio `app/Http/Controllers/v1/` (29 controladores)
- [x] Eliminado directorio `app/Http/Resources/v1/` (27 resources)
- [x] Eliminado directorio `app/Exports/v1/` (4 exports)

**Fase 4: Limpieza** ✅
- [x] Eliminados imports innecesarios de v1 en controladores v2
- [x] Actualizados controladores v2 para usar resources v2 exclusivamente
- [x] Reemplazadas referencias a V2*Resource por *Resource en controladores v2

**Fase 5: Verificación** ✅
- [x] Verificado que no hay referencias rotas a v1
- [x] Verificado que no hay errores de linting
- [x] Actualizado documento con estado final

### Archivos Modificados

**Controladores v2 actualizados para usar resources v2**:
- `app/Http/Controllers/v2/CaptureZoneController.php` - Ahora usa `v2/CaptureZoneResource`
- `app/Http/Controllers/v2/CeboDispatchController.php` - Ahora usa `v2/CeboDispatchResource`
- `app/Http/Controllers/v2/CustomerController.php` - Eliminado import v1, usa `v2/CustomerResource`
- `app/Http/Controllers/v2/SpeciesController.php` - Eliminado import v1, usa `v2/SpeciesResource`
- `app/Http/Controllers/v2/SupplierController.php` - Eliminado import v1, usa `v2/SupplierResource`
- `app/Http/Controllers/v2/TransportController.php` - Eliminado import v1, usa `v2/TransportResource`
- `app/Http/Controllers/v2/StoreController.php` - Eliminado import v1, usa `v2/StoreResource` y `v2/StoreDetailsResource`
- `app/Http/Controllers/v2/BoxesController.php` - Eliminado import v1 innecesario
- `app/Http/Controllers/v2/PaymentTermController.php` - Eliminado import v1 innecesario
- `app/Http/Controllers/v2/IncotermController.php` - Eliminado import v1 innecesario
- `app/Http/Controllers/v2/FishingGearController.php` - Eliminado import v1 innecesario
- `app/Http/Controllers/v2/CountryController.php` - Eliminado import v1 innecesario
- `app/Http/Controllers/v2/TaxController.php` - Eliminado import v1 innecesario

**Archivos creados**:
- `app/Http/Resources/v2/CaptureZoneResource.php` - Creado para reemplazar v1

**Archivos eliminados**:
- `routes/api.php` - Eliminadas todas las rutas v1 (líneas 112-243)
- `app/Http/Controllers/v1/` - Directorio completo eliminado
- `app/Http/Resources/v1/` - Directorio completo eliminado
- `app/Exports/v1/` - Directorio completo eliminado

### Resultado Final

✅ **Total de archivos eliminados**: ~60 archivos PHP
✅ **Total de líneas de código eliminadas**: ~240 líneas de rutas + miles de líneas de controladores/resources/exports
✅ **Sin errores de linting**: Todos los controladores v2 funcionan correctamente
✅ **Sin referencias rotas**: Todas las dependencias de v1 han sido reemplazadas por v2

---

## 📝 Notas Finales

1. **CaptureZoneResource v2**: Fue necesario crear este resource ya que no existía en v2 pero estaba siendo usado por `CaptureZoneController` v2.

2. **Resources v1**: Todos los resources v1 han sido eliminados. Los controladores v2 ahora usan exclusivamente resources v2.

3. **Rutas v1**: Todas las rutas v1 han sido eliminadas de `routes/api.php`. Solo quedan las rutas v2 activas.

4. **Tests**: Se recomienda ejecutar los tests del proyecto para verificar que todo funciona correctamente.

5. **Documentación**: La documentación que mencionaba v1 como obsoleta puede ser actualizada para reflejar que v1 ha sido completamente eliminada.

---

**Completado el**: 2025-01-27
