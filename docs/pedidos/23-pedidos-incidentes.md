# Pedidos - Gestión de Incidentes (Incidents)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Incident` representa un **incidente** ocurrido en relación con un pedido. Un pedido puede tener un solo incidente, que se utiliza para registrar problemas, su resolución y el tipo de resolución aplicada.

**Concepto clave**: Un incidente cambia el estado del pedido a `'incident'` y permite rastrear problemas y sus resoluciones.

**Archivo del modelo**: `app/Models/Incident.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `incidents`

**Migración**: `database/migrations/companies/2025_03_22_163650_create_incidents_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del incidente |
| `order_id` | bigint | NO | FK a `orders` - Pedido relacionado |
| `description` | text | NO | Descripción del incidente |
| `status` | string | NO | Estado: `'open'` o `'resolved'` |
| `resolution_type` | string | YES | Tipo de resolución: `'returned'`, `'partially_returned'`, `'compensated'` |
| `resolution_notes` | text | YES | Notas sobre la resolución |
| `resolved_at` | timestamp | YES | Fecha y hora de resolución |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `orders.id` (unique, un solo incidente por pedido)

**Constraints**:
- `order_id` → `orders.id` (onDelete: cascade, unique)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'order_id',
    'description',
    'status',
    'resolution_type',
    'resolution_notes',
    'resolved_at',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `order()` - Pedido
```php
public function order()
{
    return $this->belongsTo(Order::class);
}
```
- Relación muchos-a-uno con `Order`
- Un incidente pertenece a un pedido

**Nota**: La relación inversa en `Order` es `hasOne`:
```php
public function incident()
{
    return $this->hasOne(Incident::class);
}
```

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/IncidentController.php`

### Métodos del Controlador

#### `show($orderId)` - Obtener Incidente
```php
GET /v2/orders/{orderId}/incident
```

**Comportamiento**:
- Busca el pedido con su incidente
- Si no existe incidente, retorna 404
- Si existe, retorna el incidente con `toArrayAssoc()`

**Respuesta exitosa** (200):
```json
{
    "id": 1,
    "description": "Producto dañado en transporte",
    "status": "open",
    "resolutionType": null,
    "resolutionNotes": null,
    "resolvedAt": null,
    "createdAt": "2025-01-15T10:30:00.000000Z",
    "updatedAt": "2025-01-15T10:30:00.000000Z"
}
```

**Respuesta sin incidente** (404):
```json
{
    "message": "Incident not found"
}
```

#### `store(Request $request, $orderId)` - Crear Incidente
```php
POST /v2/orders/{orderId}/incident
```

**Validación**:
```php
[
    'description' => 'required|string',
]
```

**Request body**:
```json
{
    "description": "Producto dañado durante el transporte"
}
```

**Comportamiento**:
1. Busca el pedido
2. **Valida que no exista ya un incidente** (retorna 400 si existe)
3. Crea el incidente con `status = 'open'`
4. **Actualiza el estado del pedido a `'incident'`**
5. Retorna el incidente creado

**Respuesta exitosa** (201):
```json
{
    "id": 1,
    "description": "Producto dañado durante el transporte",
    "status": "open",
    // ...
}
```

**Respuesta si ya existe** (400):
```json
{
    "message": "Incident already exists"
}
```

#### `update(Request $request, $orderId)` - Actualizar/Resolver Incidente
```php
PUT /v2/orders/{orderId}/incident
```

**Validación**:
```php
[
    'resolution_type' => 'required|in:returned,partially_returned,compensated',
    'resolution_notes' => 'nullable|string',
]
```

**Request body**:
```json
{
    "resolution_type": "returned",
    "resolution_notes": "Producto devuelto completo, nuevo envío programado"
}
```

**Comportamiento**:
1. Busca el pedido y su incidente
2. Si no existe incidente, retorna 404
3. Actualiza el incidente:
   - `status = 'resolved'`
   - `resolution_type` = valor recibido
   - `resolution_notes` = valor recibido (opcional)
   - `resolved_at` = `now()`
4. Retorna el incidente actualizado

**Tipos de resolución**:
- `returned`: Producto devuelto completo
- `partially_returned`: Producto parcialmente devuelto
- `compensated`: Compensación aplicada

**Nota importante**: Este método resuelve el incidente, pero **NO cambia el estado del pedido**. El pedido permanece en estado `'incident'` hasta que se elimine el incidente.

#### `destroy($orderId)` - Eliminar Incidente
```php
DELETE /v2/orders/{orderId}/incident
```

**Comportamiento**:
1. Busca el pedido y su incidente
2. Si no existe incidente, retorna 404
3. Elimina el incidente
4. **Actualiza el estado del pedido a `'finished'`**
5. Retorna mensaje de éxito

**Respuesta exitosa** (200):
```json
{
    "message": "Incident deleted"
}
```

---

## 🔄 Flujo de Estados

### Estados del Incidente

1. **`open`**: Incidente abierto, pendiente de resolución
2. **`resolved`**: Incidente resuelto (con tipo y notas de resolución)

### Estados del Pedido Relacionados

1. **Creación de incidente**: `Order.status` → `'incident'`
2. **Resolución de incidente**: `Order.status` permanece `'incident'` (no cambia automáticamente)
3. **Eliminación de incidente**: `Order.status` → `'finished'`

**Nota**: Un pedido en estado `'incident'` puede tener el incidente resuelto pero el pedido sigue marcado como con incidente hasta que se elimine el registro del incidente.

---

## 📄 Método `toArrayAssoc()`

El modelo incluye un método `toArrayAssoc()` para transformar a array:

```php
public function toArrayAssoc(): array
{
    return [
        'id' => $this->id,
        'description' => $this->description,
        'status' => $this->status,
        'resolutionType' => $this->resolution_type,
        'resolutionNotes' => $this->resolution_notes,
        'resolvedAt' => $this->resolved_at,
        'createdAt' => $this->created_at,
        'updatedAt' => $this->updated_at,
    ];
}
```

**Conversión de nombres**:
- `resolution_type` → `resolutionType` (camelCase)
- `resolved_at` → `resolvedAt` (camelCase)
- `created_at` → `createdAt` (camelCase)
- `updated_at` → `updatedAt` (camelCase)

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/orders/{orderId}/incident`

---

## 📝 Ejemplos de Uso

### Crear un Incidente
```http
POST /v2/orders/5/incident
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "description": "Algunas cajas llegaron congeladas cuando deberían estar frescas"
}
```

**Respuesta** (201):
```json
{
    "id": 1,
    "description": "Algunas cajas llegaron congeladas cuando deberían estar frescas",
    "status": "open",
    "resolutionType": null,
    "resolutionNotes": null,
    "resolvedAt": null,
    "createdAt": "2025-01-15T10:30:00.000000Z",
    "updatedAt": "2025-01-15T10:30:00.000000Z"
}
```

### Resolver un Incidente
```http
PUT /v2/orders/5/incident
Content-Type: application/json
Authorization: Bearer {token}

{
    "resolution_type": "compensated",
    "resolution_notes": "Se aplicó descuento del 10% en el siguiente pedido"
}
```

**Respuesta** (200):
```json
{
    "id": 1,
    "description": "Algunas cajas llegaron congeladas cuando deberían estar frescas",
    "status": "resolved",
    "resolutionType": "compensated",
    "resolutionNotes": "Se aplicó descuento del 10% en el siguiente pedido",
    "resolvedAt": "2025-01-15T15:45:00.000000Z",
    "createdAt": "2025-01-15T10:30:00.000000Z",
    "updatedAt": "2025-01-15T15:45:00.000000Z"
}
```

### Eliminar un Incidente
```http
DELETE /v2/orders/5/incident
Authorization: Bearer {token}
```

**Respuesta** (200):
```json
{
    "message": "Incident deleted"
}
```

**Nota**: Después de eliminar, el pedido cambia automáticamente a estado `'finished'`.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Cambio de Estado del Pedido

1. **Estado no Cambia al Resolver** (`app/Http/Controllers/v2/IncidentController.php:61-66`)
   - Al resolver el incidente, el pedido permanece en estado `'incident'`
   - Solo cambia a `'finished'` cuando se elimina el incidente
   - **Líneas**: 61-66
   - **Problema**: Puede confundir: un incidente resuelto no cambia el estado del pedido
   - **Recomendación**: 
     - Considerar cambiar estado a `'finished'` cuando se resuelve
     - O mantener el estado actual pero documentarlo claramente

### ⚠️ No Permite Actualizar Descripción

2. **No Se Puede Editar Descripción** (`app/Http/Controllers/v2/IncidentController.php:56-59`)
   - El método `update()` solo permite resolver, no editar descripción
   - **Líneas**: 56-59
   - **Problema**: Si hay un error en la descripción, no se puede corregir
   - **Recomendación**: Permitir actualizar descripción si el estado es `'open'`

### ⚠️ No Valida Estado Antes de Resolver

3. **Puede Resolver Ya Resuelto** (`app/Http/Controllers/v2/IncidentController.php:46-54`)
   - No valida si el incidente ya está resuelto antes de resolver
   - **Líneas**: 46-54
   - **Problema**: Puede sobrescribir datos de resolución previa
   - **Recomendación**: Validar que `status == 'open'` antes de resolver

### ⚠️ Constraint Unique en Base de Datos

4. **Un Solo Incidente por Pedido** (`database/migrations/companies/2025_03_22_163650_create_incidents_table.php`)
   - Hay constraint unique en `order_id`
   - **Estado**: Correcto según diseño, pero limita casos de múltiples incidentes
   - **Recomendación**: Documentar claramente esta limitación

### ⚠️ No Hay Historial

5. **Eliminación Directa Sin Historial** (`app/Http/Controllers/v2/IncidentController.php:71-89`)
   - Al eliminar, se pierde el historial del incidente
   - **Líneas**: 71-89
   - **Problema**: No hay registro de incidentes pasados
   - **Recomendación**: 
     - Usar soft deletes
     - O crear tabla de historial de incidentes

### ⚠️ No Valida Pedido en Estado Correcto

6. **Puede Crear Incidente en Pedido Finalizado** (`app/Http/Controllers/v2/IncidentController.php:23-44`)
   - No valida el estado del pedido antes de crear incidente
   - **Líneas**: 23-44
   - **Problema**: Puede crear incidentes en pedidos finalizados o con otro incidente resuelto
   - **Recomendación**: Validar que el pedido esté en estado válido

### ⚠️ Tipo de Resolución Limitado

7. **Tipos de Resolución Fijos** (`app/Http/Controllers/v2/IncidentController.php:57`)
   - Solo permite 3 tipos: `returned`, `partially_returned`, `compensated`
   - **Líneas**: 57
   - **Estado**: Correcto si cubre todos los casos, pero puede ser limitante
   - **Recomendación**: Considerar si faltan tipos o hacerlo configurable

### ⚠️ No Hay Notificaciones

8. **Sin Notificaciones Automáticas** (`app/Http/Controllers/v2/IncidentController.php`)
   - No hay sistema de notificaciones cuando se crea o resuelve un incidente
   - **Problema**: Puede haber retrasos en la atención
   - **Recomendación**: Considerar eventos/notificaciones automáticas

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

