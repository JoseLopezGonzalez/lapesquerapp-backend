# Catálogos - Impuestos (Taxes)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Tax` representa un **impuesto o tasa** que se aplica a productos en pedidos (ej: "IVA 21%", "IVA 10%"). Los impuestos están vinculados a detalles planificados de pedidos (`OrderPlannedProductDetail`) para calcular el total de cada línea.

**Archivo del modelo**: `app/Models/Tax.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `taxes`

**Migración**: `database/migrations/companies/2025_03_09_181653_create_taxes_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del impuesto |
| `name` | string | NO | Nombre del impuesto (ej: "IVA 21%") - **UNIQUE** |
| `rate` | decimal(5,2) | NO | Tasa del impuesto (ej: 21.00) - Default: 0 |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `name` (unique)

**Constraints**:
- Unique constraint en `name`

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'rate',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `orderPlannedProductDetails()` - Detalles Planificados
```php
public function orderPlannedProductDetails()
{
    return $this->hasMany(OrderPlannedProductDetail::class);
}
```
- Relación uno-a-muchos con `OrderPlannedProductDetail`
- Detalles de pedidos que usan este impuesto

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/TaxController.php`

### Métodos del Controlador

**⚠️ Estado**: El controlador está **prácticamente vacío**. Solo tiene implementado `options()`.

#### `options()` - Opciones para Select
```php
GET /v2/taxes/options
```

**Respuesta**: Array con `id`, `name`, `rate`
```json
[
    {
        "id": 1,
        "name": "IVA 21%",
        "rate": 21.00
    },
    ...
]
```

**Ordenado**: Por tasa ascendente

**Métodos no implementados**:
- `index()`: Vacío
- `create()`: Vacío
- `store()`: Vacío
- `show()`: Vacío
- `edit()`: Vacío
- `update()`: Vacío
- `destroy()`: Vacío

---

## 📄 API Resource

**Nota**: No existe `TaxResource` en v2. El modelo usa `toArrayAssoc()`.

**Campos expuestos** (desde `toArrayAssoc()`):
```json
{
    "id": 1,
    "name": "IVA 21%",
    "rate": 21.00
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/taxes/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ CRUD Completo No Implementado

1. **Controlador Prácticamente Vacío** (`app/Http/Controllers/v2/TaxController.php`)
   - Solo tiene implementado `options()`
   - **Líneas**: 19-85
   - **Problema**: No se pueden crear, actualizar ni eliminar impuestos desde la API v2
   - **Recomendación**: Implementar métodos CRUD completos si se necesita gestión

### ⚠️ Sin Validación de Rate

2. **Rate Sin Validación en Modelo** (`app/Models/Tax.php`)
   - No valida que `rate` esté en rango válido (0-100)
   - **Problema**: Pueden crearse impuestos con tasas inválidas
   - **Recomendación**: Agregar validación en controlador o modelo

### ⚠️ Unique Constraint en BD

3. **name Tiene Unique Constraint** (`database/migrations/companies/2025_03_09_181653_create_taxes_table.php:16`)
   - Campo `name` es único en BD
   - **Estado**: ✅ Correcto, pero no se valida en controlador (no implementado)

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

