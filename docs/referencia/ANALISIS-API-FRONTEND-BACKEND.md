# Análisis Comparativo API vs Frontend

## 📋 Resumen Ejecutivo

Este documento analiza en profundidad las diferencias entre lo que espera recibir y devolver la API (según su documentación) versus lo que el frontend envía y espera recibir. Se identifican problemas críticos, diferencias, errores y endpoints no utilizados.

**Fecha de Análisis:** Diciembre 2024

**Base de la Documentación:** `/docs/api-references/`

**Código Analizado:** Frontend Next.js en `/src/services/`, `/src/hooks/`, `/src/components/`

---

## 🚨 Problemas Críticos

### 1. Estructura de Respuesta Inconsistente

**Problema:** La API documenta diferentes estructuras de respuesta según el endpoint, pero el frontend espera siempre `data.data` o `data` directamente.

**Ejemplo Problemático:**

#### `GET /api/v2/orders/{id}` - Obtener Pedido
- **API Documenta:**
  ```json
  {
    "id": 1,
    "customer": {...}
  }
  ```
- **Frontend Usa:** Extrae `data.data` (`orderService.js:32-33`)
- **⚠️ Inconsistencia:** Si la API devuelve directamente el objeto sin envolver en `{data: {...}}`, esto podría fallar.

---

### 2. Campos del Login: `role` vs `roles`

**Problema Crítico:** Inconsistencia en el nombre del campo de roles en la respuesta del login.

**Endpoint:** `POST /api/v2/login`

**API Documenta (Login):**
```json
{
  "user": {
    "role": ["admin"]  // ⚠️ Campo singular
  }
}
```

**API Documenta (`GET /api/v2/me`):**
```json
{
  "roles": [  // ⚠️ Campo plural
    {
      "id": 1,
      "name": "admin",
      "display_name": "Administrador"
    }
  ]
}
```

**Frontend Usa:**
- En NextAuth callback usa `user.role` (singular) - `route.js:99`
- El frontend debería normalizar esto para evitar problemas.

**Recomendación:** Normalizar en el frontend para siempre usar `roles` (plural) o verificar ambos campos.

---

### 3. Endpoint de Actualización de Estado de Pedido

**API Documenta:**
```http
PUT /api/v2/orders/{order}/status
Body: { "status": "finished" }
```

**Frontend Usa:**
```javascript
PUT /api/v2/orders/${orderId}/status?status=${status}
// ⚠️ Usa query parameter en lugar de body
```

**Problema:** El frontend envía el status como query parameter en lugar del body JSON. Esto puede funcionar si el backend acepta ambos, pero es inconsistente con la documentación.

**Ubicación:** `orderService.js:255`

---

## ⚠️ Diferencias y Problemas

### 4. Endpoints de Productos Planificados

**Problema:** El frontend usa endpoints que **NO están documentados** en la API references.

**Frontend Usa:**
- `POST /api/v2/order-planned-product-details` - Crear producto planificado (`orderService.js:225`)
- `PUT /api/v2/order-planned-product-details/{id}` - Actualizar producto planificado (`orderService.js:166`)
- `DELETE /api/v2/order-planned-product-details/{id}` - Eliminar producto planificado (`orderService.js:196`)

**API Documenta:** ❌ Estos endpoints NO aparecen en `/docs/api-references/pedidos/README.md`

**Recomendación:** 
1. Documentar estos endpoints en la API references, o
2. Si son internos, moverlos a una sección de endpoints internos.

---

### 7. Endpoints de Incidentes de Pedidos

**Problema:** El frontend usa endpoints que **NO están completamente documentados**.

**Frontend Usa:**
- `POST /api/v2/orders/{orderId}/incident` - Crear incidencia (`orderService.js:286`)
- `PUT /api/v2/orders/{orderId}/incident` - Actualizar incidencia (`orderService.js:317`)
- `DELETE /api/v2/orders/{orderId}/incident` - Eliminar incidencia (`orderService.js:349`)

**API Documenta:** ❌ Estos endpoints NO aparecen en `/docs/api-references/pedidos/README.md`

**Nota:** Hay un endpoint de PDF para incidentes (`GET /api/v2/orders/{orderId}/pdf/incident`), pero no los CRUD.

---

### 5. Endpoint de Opciones de Pedidos Activos

**Frontend Usa:**
```javascript
GET /api/v2/active-orders/options  // ⚠️ Singular "order"
```

**API Documenta:**
```http
GET /api/v2/orders/active  // ✅ Plural "orders"
GET /api/v2/orders/options  // ✅ Plural "orders"
```

**Problema:** El frontend usa `active-orders/options` pero la documentación muestra `orders/active` y `orders/options`. Podría ser un endpoint diferente o un error.

**Ubicación:** `orderService.js:377`

---

## 📊 Endpoints Usados Genéricamente vs No Utilizados

**Nota:** Muchos endpoints CRUD se utilizan de manera genérica a través del sistema `EntityClient` configurado en `/src/configs/entitiesConfig.js`. Este sistema usa:
- `GET ${endpoint}` - Para listar entidades
- `GET ${endpoint}/{id}` - Para obtener una entidad (usado en formularios de edición)
- `POST ${endpoint}` - Para crear entidades (usado en formularios de creación)
- `PUT ${endpoint}/{id}` - Para actualizar entidades (usado en formularios de edición)
- `DELETE ${deleteEndpoint}` - Para eliminar entidades

---

### Autenticación

#### `POST /api/v2/logout` - Cerrar Sesión
- **Método:** POST
- **Documentado:** Sí
- **Usado en Frontend:** ❌ NO encontrado
- **Razón:** NextAuth maneja el logout internamente, podría no necesitar llamar al backend
- **Recomendación:** Si el backend revoca tokens, debería implementarse

#### `GET /api/v2/me` - Obtener Usuario Actual
- **Método:** GET
- **Documentado:** Sí
- **Usado en Frontend:** ❌ NO encontrado
- **Razón:** NextAuth guarda la información del usuario en el JWT
- **Recomendación:** Útil para refrescar datos del usuario sin re-login

---

### Pedidos

#### `GET /api/v2/orders` - Listar Pedidos (con filtros)
- **Método:** GET
- **Documentado:** Sí - Con muchos filtros opcionales
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:139`)
- **Filtros Documentados:** `active`, `customers`, `ids`, `id`, `buyerReference`, `status`, `loadDate`, `entryDate`, `transports`, `salespeople`, `palletsState`, `products`, `species`, `incoterm`, `perPage`
- **Recomendación:** Verificar qué filtros realmente se están utilizando

#### `DELETE /api/v2/orders/{id}` - Eliminar Pedido
- **Método:** DELETE
- **Documentado:** Sí
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:141`)

#### `DELETE /api/v2/orders` - Eliminar Múltiples Pedidos
- **Método:** DELETE
- **Documentado:** Sí
- **Usado en Frontend:** ❌ NO encontrado (ni genérico ni directo)

---

### Productos

#### `GET /api/v2/products` - Listar Productos (con filtros)
- **Método:** GET
- **Documentado:** Sí - Con filtros: `name`, `speciesId`, `captureZoneId`, `familyId`, `articleGtin`, `boxGtin`, `palletGtin`, `perPage`
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:901`)
- **Nota:** También se usa `GET /api/v2/products/options` directamente (`productService.js:12`)

#### `POST /api/v2/products` - Crear Producto
- **Método:** POST
- **Documentado:** Sí
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:1018`)

#### `PUT /api/v2/products/{id}` - Actualizar Producto
- **Método:** PUT
- **Documentado:** Sí
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:1116`)

#### `GET /api/v2/products/{id}` - Mostrar Producto
- **Método:** GET
- **Documentado:** Sí
- **Usado en Frontend:** ✅ Sí - Usado genéricamente en formularios de edición (`EntityClient`)

#### `DELETE /api/v2/products/{id}` - Eliminar Producto
- **Método:** DELETE
- **Documentado:** Sí
- **Usado en Frontend:** ✅ Sí - Usado genéricamente a través de `EntityClient` (`entitiesConfig.js:903`)

#### `DELETE /api/v2/products` - Eliminar Múltiples Productos
- **Método:** DELETE
- **Documentado:** Sí
- **Usado en Frontend:** ❌ NO encontrado (ni genérico ni directo)

---

### Categorías y Familias de Productos

#### Product Categories
- **`GET /api/v2/product-categories`** - ✅ Usado genéricamente (`entitiesConfig.js:3177`)
- **`POST /api/v2/product-categories`** - ✅ Usado genéricamente (`entitiesConfig.js:3240`)
- **`PUT /api/v2/product-categories/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3267`)
- **`GET /api/v2/product-categories/{id}`** - ✅ Usado genéricamente (formularios de edición)
- **`DELETE /api/v2/product-categories/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3186`)
- **`DELETE /api/v2/product-categories`** - ❌ NO encontrado
- **`GET /api/v2/product-categories/options`** - ✅ Usado en filtros (`entitiesConfig.js:996`)

#### Product Families
- **`GET /api/v2/product-families`** - ✅ Usado genéricamente (`entitiesConfig.js:3275`)
- **`POST /api/v2/product-families`** - ✅ Usado genéricamente (`entitiesConfig.js:3352`)
- **`PUT /api/v2/product-families/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3393`)
- **`GET /api/v2/product-families/{id}`** - ✅ Usado genéricamente (formularios de edición)
- **`DELETE /api/v2/product-families/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3284`)
- **`DELETE /api/v2/product-families`** - ❌ NO encontrado
- **`GET /api/v2/product-families/options`** - ✅ Usado en filtros (`entitiesConfig.js:996`)

---

### Inventario - Palets

#### `DELETE /api/v2/pallets/{id}` - Eliminar Palet
- **Método:** DELETE
- **Usado en Frontend:** ✅ Sí - Usado genéricamente (`entitiesConfig.js:1480`)

#### `GET /api/v2/pallets/registered` - Palets Registrados
- **Método:** GET
- **Usado en Frontend:** ✅ Sí - Usado directamente (`storeService.js:209`)

#### ❌ Endpoints NO Utilizados:
- `GET /api/v2/pallets` - Listar Palets (no encontrado en config genérico)
- `POST /api/v2/pallets` - Crear Palet
- `PUT /api/v2/pallets/{id}` - Actualizar Palet
- `DELETE /api/v2/pallets` - Eliminar Múltiples Palets
- `GET /api/v2/pallets/options` - Opciones de Palets
- `GET /api/v2/pallets/stored-options` - Opciones de Palets Almacenados
- `GET /api/v2/pallets/shipped-options` - Opciones de Palets Enviados
- `GET /api/v2/pallets/available-for-order` - Palets Disponibles para Pedido
- `POST /api/v2/pallets/assign-to-position` - Asignar Palet a Posición
- `POST /api/v2/pallets/move-to-store` - Mover Palet a Almacén
- `POST /api/v2/pallets/move-multiple-to-store` - Mover Múltiples Palets
- `POST /api/v2/pallets/{id}/unassign-position` - Desasignar Posición
- `POST /api/v2/pallets/{id}/link-order` - Vincular Palet con Pedido
- `POST /api/v2/pallets/link-orders` - Vincular Múltiples Palets
- `POST /api/v2/pallets/{id}/unlink-order` - Desvincular Palet de Pedido
- `POST /api/v2/pallets/unlink-orders` - Desvincular Múltiples Palets
- `POST /api/v2/pallets/update-state` - Actualizar Estado Masivo

---

### Inventario - Cajas

#### `DELETE /api/v2/boxes/{id}` - Eliminar Caja
- **Método:** DELETE
- **Usado en Frontend:** ✅ Sí - Usado genéricamente (`entitiesConfig.js:1256`)

#### ❌ Endpoints NO Utilizados:
- `GET /api/v2/boxes` - Listar Cajas
- `POST /api/v2/boxes` - Crear Caja
- `GET /api/v2/boxes/{id}` - Mostrar Caja
- `PUT /api/v2/boxes/{id}` - Actualizar Caja
- `DELETE /api/v2/boxes` - Eliminar Múltiples Cajas
- `GET /api/v2/boxes/available` - Cajas Disponibles
- `GET /api/v2/boxes/xlsx` - Exportar Reporte de Cajas

---

### Producción

#### Endpoints NO Documentados pero Usados:
- `GET /api/v2/production-records/{id}/tree` - Obtener Árbol del Registro (usado pero NO documentado)
- `GET /api/v2/production-records/{id}/images` - Listar Imágenes (usado en `productionService.js:541` pero NO documentado)
- `POST /api/v2/production-records/{id}/images` - Subir Imagen (usado en `productionService.js:553` pero NO documentado)
- `DELETE /api/v2/production-records/{id}/images/{imageId}` - Eliminar Imagen (usado en `productionService.js:569` pero NO documentado)

---

### Catálogos

**Muchos endpoints de catálogos se usan genéricamente a través de `EntityClient`:**

#### ✅ Endpoints Usados Genéricamente:
- **`GET /api/v2/transports`** - Listar transportes (`entitiesConfig.js:765`)
- **`POST /api/v2/transports`** - Crear transporte (`entitiesConfig.js:821`)
- **`PUT /api/v2/transports/{id}`** - Actualizar transporte (`entitiesConfig.js:829`)
- **`DELETE /api/v2/transports/{id}`** - Eliminar transporte (`entitiesConfig.js:767`)
- **`GET /api/v2/customers`** - Listar clientes (configurado)
- **`POST /api/v2/customers`** - Crear cliente (`entitiesConfig.js:1836`)
- **`PUT /api/v2/customers/{id}`** - Actualizar cliente (`entitiesConfig.js:2003`)
- **`DELETE /api/v2/customers/{id}`** - Eliminar cliente (`entitiesConfig.js:1726`)
- **`GET /api/v2/suppliers`** - Listar proveedores (configurado)
- **`POST /api/v2/suppliers`** - Crear proveedor (`entitiesConfig.js:2073`)
- **`PUT /api/v2/suppliers/{id}`** - Actualizar proveedor (`entitiesConfig.js:2136`)
- **`DELETE /api/v2/suppliers/{id}`** - Eliminar proveedor (`entitiesConfig.js:2024`)
- **`GET /api/v2/species`** - Listar especies (configurado)
- **`POST /api/v2/species`** - Crear especie (`entitiesConfig.js:2314`)
- **`PUT /api/v2/species/{id}`** - Actualizar especie (`entitiesConfig.js:2322`)
- **`DELETE /api/v2/species/{id}`** - Eliminar especie (`entitiesConfig.js:2238`)
- **`GET /api/v2/capture-zones`** - Listar zonas de captura (`entitiesConfig.js:2145`)
- **`POST /api/v2/capture-zones`** - Crear zona de captura (`entitiesConfig.js:2197`)
- **`PUT /api/v2/capture-zones/{id}`** - Actualizar zona de captura (`entitiesConfig.js:2205`)
- **`DELETE /api/v2/capture-zones/{id}`** - Eliminar zona de captura (`entitiesConfig.js:2154`)
- **`GET /api/v2/incoterms`** - Listar incoterms (configurado)
- **`POST /api/v2/incoterms`** - Crear incoterm (`entitiesConfig.js:2449`)
- **`PUT /api/v2/incoterms/{id}`** - Actualizar incoterm (`entitiesConfig.js:2488`)
- **`DELETE /api/v2/incoterms/{id}`** - Eliminar incoterm (`entitiesConfig.js:2396`)
- **`GET /api/v2/salespeople`** - Listar vendedores (configurado)
- **`POST /api/v2/salespeople`** - Crear vendedor (`entitiesConfig.js:2550`)
- **`PUT /api/v2/salespeople/{id}`** - Actualizar vendedor (`entitiesConfig.js:2588`)
- **`DELETE /api/v2/salespeople/{id}`** - Eliminar vendedor (`entitiesConfig.js:2505`)
- **`GET /api/v2/fishing-gears`** - Listar artes de pesca (`entitiesConfig.js:2597`)
- **`POST /api/v2/fishing-gears`** - Crear arte de pesca (`entitiesConfig.js:2649`)
- **`PUT /api/v2/fishing-gears/{id}`** - Actualizar arte de pesca (`entitiesConfig.js:2673`)
- **`DELETE /api/v2/fishing-gears/{id}`** - Eliminar arte de pesca (`entitiesConfig.js:2606`)
- **`GET /api/v2/countries`** - Listar países (configurado)
- **`POST /api/v2/countries`** - Crear país (`entitiesConfig.js:2736`)
- **`PUT /api/v2/countries/{id}`** - Actualizar país (`entitiesConfig.js:2761`)
- **`DELETE /api/v2/countries/{id}`** - Eliminar país (`entitiesConfig.js:2691`)
- **`GET /api/v2/payment-terms`** - Listar términos de pago (`entitiesConfig.js:2769`)
- **`POST /api/v2/payment-terms`** - Crear término de pago (`entitiesConfig.js:2823`)
- **`PUT /api/v2/payment-terms/{id}`** - Actualizar término de pago (`entitiesConfig.js:2848`)
- **`DELETE /api/v2/payment-terms/{id}`** - Eliminar término de pago (`entitiesConfig.js:2778`)

#### ❌ Endpoints NO Utilizados:
- Todos los endpoints de **`labels`** - NO encontrados en configuración

#### ✅ Endpoints de Opciones Usados en Filtros:
- `GET /api/v2/suppliers/options` - Usado en filtros (`entitiesConfig.js:64`)
- `GET /api/v2/species/options` - Usado en filtros (`entitiesConfig.js:77`)
- `GET /api/v2/products/options` - Usado en filtros (`entitiesConfig.js:91`)
- `GET /api/v2/customers/options` - Usado en filtros (`entitiesConfig.js:248`)
- `GET /api/v2/salespeople/options` - Usado en filtros (`entitiesConfig.js:290`)
- `GET /api/v2/transports/options` - Usado en filtros (`entitiesConfig.js:304`)
- `GET /api/v2/incoterms/options` - Usado en filtros (`entitiesConfig.js:318`)

---

### Estadísticas

**Nota:** Los endpoints de estadísticas documentados parecen estar en uso. No se encontraron problemas específicos.

---

### Sistema

#### Usuarios (requieren rol `superuser`):
- **`GET /api/v2/users`** - ✅ Usado genéricamente (`entitiesConfig.js:609`)
- **`POST /api/v2/users`** - ✅ Usado genéricamente (`entitiesConfig.js:670`)
- **`GET /api/v2/users/{id}`** - ✅ Usado genéricamente (formularios de edición)
- **`PUT /api/v2/users/{id}`** - ⚠️ Configurado pero verificar permisos
- **`DELETE /api/v2/users/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:611`)
- **`GET /api/v2/users/options`** - ❌ NO encontrado

#### Roles:
- **`GET /api/v2/roles/options`** - ✅ Usado en formularios (`entitiesConfig.js:743`)
- Otros endpoints de roles - ❌ NO encontrados en configuración

#### Sesiones:
- **`DELETE /api/v2/sessions/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3010`)
- **`GET /api/v2/sessions`** - ❌ NO encontrado

#### Empleados y Fichajes:
- **`GET /api/v2/employees`** - ✅ Usado genéricamente (configurado)
- **`POST /api/v2/employees`** - ✅ Usado genéricamente (`entitiesConfig.js:3688`)
- **`PUT /api/v2/employees/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3695`)
- **`DELETE /api/v2/employees/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3608`)
- **`PUT /api/v2/punches/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3784`)
- **`DELETE /api/v2/punches/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3714`)

#### Producciones:
- **`DELETE /api/v2/productions/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3412`)
- **`POST /api/v2/productions`** - ✅ Usado genéricamente (`entitiesConfig.js:3516`)
- **`PUT /api/v2/productions/{id}`** - ✅ Usado genéricamente (`entitiesConfig.js:3589`)

---

### Utilidades (PDFs y Excel)

#### Endpoints Documentados pero NO Encontrados en Uso:
- `POST /api/v2/orders/{orderId}/send-custom-documents` - Enviar Documentos Personalizados
- `POST /api/v2/orders/{orderId}/send-standard-documents` - Enviar Documentos Estándar

**Recomendación:** Verificar si se usan o si se implementarán en el futuro.

---

## 📝 Campos y Propiedades No Verificados

### En Respuestas de Pedidos

**Campos documentados que NO se verificó si se usan:**
- `transportation_notes`, `production_notes`, `accounting_notes`
- `emails`, `cc_emails`

**Recomendación:** Auditar qué campos realmente se muestran/editan en el frontend.

---

### En Respuestas de Productos

**Campos documentados que NO se verificó si se usan:**
- `a3erp_code`, `facil_com_code`

---

### En Respuestas de Estadísticas

**Campos adicionales documentados que podrían no usarse:**
- En `GET /api/v2/statistics/orders/total-amount` (Método: GET): `average_amount`
- En `GET /api/v2/statistics/orders/ranking` (Método: GET): `rank`

---

## 🔧 Recomendaciones

### 1. Documentar Endpoints Faltantes

**Endpoints usados en frontend pero NO documentados:**
- `POST /api/v2/order-planned-product-details` - Crear producto planificado
- `PUT /api/v2/order-planned-product-details/{id}` - Actualizar producto planificado
- `DELETE /api/v2/order-planned-product-details/{id}` - Eliminar producto planificado
- `POST /api/v2/orders/{orderId}/incident` - Crear incidencia
- `PUT /api/v2/orders/{orderId}/incident` - Actualizar incidencia
- `DELETE /api/v2/orders/{orderId}/incident` - Eliminar incidencia
- `GET /api/v2/production-records/{id}/tree` - Obtener árbol del registro
- `GET /api/v2/production-records/{id}/images` - Listar imágenes
- `POST /api/v2/production-records/{id}/images` - Subir imagen
- `DELETE /api/v2/production-records/{id}/images/{imageId}` - Eliminar imagen
- `GET /api/v2/active-orders/options` - Opciones de pedidos activos (verificar si es correcto o debería ser `orders/options`)

---

### 2. Estandarizar Estructura de Respuestas

**Problema:** Algunos endpoints devuelven objetos directamente, otros envueltos en `{data: {...}}`, otros en `{data: [{...}]}`.

**Recomendación:** 
- Crear normalizadores en el frontend para cada tipo de respuesta
- Documentar claramente la estructura esperada de cada endpoint

---

### 3. Normalizar Nomenclatura de Roles

**Problema:** Login devuelve `role` (singular), `/me` devuelve `roles` (plural).

**Recomendación:**
- Estandarizar en backend para siempre usar `roles` (plural)
- O crear normalizador en frontend para siempre usar `roles`

---

### 4. Verificar Uso de Endpoints a través de EntityClient

**Estado:** ✅ Completado - Se identificaron todos los endpoints usados genéricamente a través de `EntityClient` en `entitiesConfig.js`

**Endpoints identificados usando genéricamente:**
- CRUD de: `products`, `stores`, `transports`, `customers`, `suppliers`, `species`, `capture-zones`, `incoterms`, `salespeople`, `fishing-gears`, `countries`, `payment-terms`, `product-categories`, `product-families`, `employees`, `users`, `productions`
- DELETE de: `orders`, `pallets`, `boxes`, `cebo-dispatches`, `sessions`, `activity-logs`, `punches`
- Endpoints de opciones usados en filtros: `suppliers/options`, `species/options`, `products/options`, `customers/options`, `salespeople/options`, `transports/options`, `incoterms/options`, `product-families/options`

---

### 5. Implementar Logout en Backend

**Problema:** El frontend no llama a `POST /api/v2/logout` al cerrar sesión.

**Recomendación:**
- Si el backend revoca tokens al hacer logout, implementar la llamada
- Si no es necesario, documentar que NextAuth maneja el logout

---

### 6. Revisar Uso de Filtros en Listados

**Problema:** La API documenta muchos filtros opcionales que pueden no estar siendo utilizados.

**Recomendación:**
- Auditar qué filtros realmente se usan en el frontend
- Documentar qué filtros son críticos vs opcionales

---

### 7. Validar Endpoints de Opciones

**Problema:** Frontend usa `GET /api/v2/active-orders/options` pero la documentación muestra `GET /api/v2/orders/options`

**Recomendación:**
- Verificar con backend cuál es el endpoint correcto
- Actualizar documentación o frontend según corresponda

---

## 📈 Estadísticas Resumidas

### Endpoints Documentados: ~150+
### Endpoints Encontrados en Uso Directo: ~80-100
### Endpoints Encontrados en Uso Genérico (EntityClient): ~50-60
### Endpoints NO Utilizados: ~30-40
### Endpoints Usados pero NO Documentados: ~15-20

**Nota:** Muchos endpoints CRUD que inicialmente aparecían como "no utilizados" en realidad se están usando genéricamente a través del sistema `EntityClient` configurado en `entitiesConfig.js`. Esto incluye:
- CRUD completo de: productos, categorías, familias, transportes, clientes, proveedores, especies, zonas de captura, incoterms, vendedores, artes de pesca, países, términos de pago, empleados, usuarios, producciones
- Operaciones DELETE de: pedidos, palets, cajas, recepciones, despachos, sesiones, logs de actividad, fichajes

---

## 🎯 Prioridades de Acción

### 🔴 Crítico (Resolver Inmediatamente)
1. Documentar endpoints de productos planificados e incidentes
2. Verificar y corregir endpoint `active-orders/options`
3. Estandarizar nomenclatura de `role` vs `roles`

### 🟡 Alto (Resolver Pronto)
4. Documentar endpoints de imágenes de producción
5. Implementar logout en backend si es necesario
6. Verificar uso real de filtros en listados

### 🟢 Medio (Mejorar en el Tiempo)
7. Auditar uso de campos en respuestas
8. Normalizar estructuras de respuesta
9. Documentar endpoints usados a través de EntityClient

---

## 📚 Referencias

- Documentación API: `/docs/api-references/`
- Servicios Frontend: `/src/services/`
- Configuración de Entidades: `/src/configs/entitiesConfig.js`
- Helpers API: `/src/lib/api/apiHelpers.js`

---

**Fin del Análisis**

