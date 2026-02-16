# Catálogos - Términos de Pago (Payment Terms)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `PaymentTerm` representa un **término o condición de pago** para clientes (ej: "30 días", "Contado", "60 días neto"). Los términos de pago se asignan a clientes y definen las condiciones de facturación.

**Archivo del modelo**: `app/Models/PaymentTerm.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `payment_terms`

**Migración**: `database/migrations/companies/2023_12_19_145004_create_payment_terms_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del término de pago |
| `name` | string | NO | Nombre del término (ej: "30 días") |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name'];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

**Nota**: El modelo no tiene relaciones explícitas definidas, aunque está relacionado con:
- `Customer` (a través de `payment_term_id`)

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/PaymentTermController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Términos de Pago
```php
GET /v2/payment-terms
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `PaymentTermResource`

#### `store(Request $request)` - Crear Término de Pago
```php
POST /v2/payment-terms
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
]
```

**Request body**:
```json
{
    "name": "30 días"
}
```

**Respuesta** (201): `PaymentTermResource`

#### `show(string $id)` - Mostrar Término de Pago
```php
GET /v2/payment-terms/{id}
```

#### `update(Request $request, string $id)` - Actualizar Término de Pago
```php
PUT /v2/payment-terms/{id}
```

**Validación**: Igual que `store()`

#### `destroy(string $id)` - Eliminar Término de Pago
```php
DELETE /v2/payment-terms/{id}
```

**Advertencia**: ⚠️ No valida si el término está en uso (clientes)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Términos
```php
DELETE /v2/payment-terms
```

#### `options()` - Opciones para Select
```php
GET /v2/payment-terms/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/PaymentTermResource.php`

Usa el método `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "30 días",
    "createdAt": "2025-01-15T10:00:00",
    "updatedAt": "2025-01-15T10:00:00"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/payment-terms/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/PaymentTermController.php:110-116`)
   - No valida si el término está en uso (clientes)
   - **Líneas**: 110-116
   - **Problema**: Puede eliminar términos en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Sin Validación de Nombre Único

2. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/PaymentTermController.php`)
   - No valida que el nombre sea único
   - **Recomendación**: Agregar unique constraint en BD

### ⚠️ Sin Relaciones Definidas

3. **No Hay Relación customers()** (`app/Models/PaymentTerm.php`)
   - No hay relación definida aunque existe FK en clientes
   - **Recomendación**: Agregar relación si se necesita

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

