# Catálogos - Incoterms

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Incoterm` representa un **término de comercio internacional (Incoterm)** que define las condiciones de entrega en transacciones comerciales internacionales (ej: "FOB", "CIF", "EXW"). Los incoterms se asignan a pedidos para definir las condiciones de entrega.

**Archivo del modelo**: `app/Models/Incoterm.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `incoterms`

**Migración**: `database/migrations/companies/2024_04_26_105751_create_incoterms_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del incoterm |
| `code` | string(10) | NO | Código del incoterm (ej: "FOB", "CIF") |
| `description` | text | NO | Descripción del incoterm |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'code',
    'description',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `orders()` - Pedidos
```php
public function orders()
{
    return $this->hasMany(Order::class);
}
```
- Relación uno-a-muchos con `Order`
- Pedidos que usan este incoterm

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/IncotermController.php`

### Métodos del Controlador

#### `index()` - Listar Incoterms
```php
GET /v2/incoterms
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `code`: Buscar por código (LIKE)
- `description`: Buscar por descripción (LIKE)

**Orden**: Por código ascendente

**Query parameters**: `perPage` (default: 10)

**Respuesta**: Collection paginada de `IncotermResource`

#### `store(Request $request)` - Crear Incoterm
```php
POST /v2/incoterms
```

**Validación**:
```php
[
    'code' => 'required|string|max:255',
    'description' => 'required|string|max:255',
]
```

#### `show(string $id)` - Mostrar Incoterm
```php
GET /v2/incoterms/{id}
```

#### `update(Request $request, string $id)` - Actualizar Incoterm
```php
PUT /v2/incoterms/{id}
```

#### `destroy(string $id)` - Eliminar Incoterm
```php
DELETE /v2/incoterms/{id}
```

**Advertencia**: ⚠️ No valida si el incoterm está en uso (pedidos)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Incoterms
```php
DELETE /v2/incoterms
```

#### `options()` - Opciones para Select
```php
GET /v2/incoterms/options
```

**Respuesta**: Array con formato `"{code} - {description}"`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/IncotermResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "code": "FOB",
    "description": "Free On Board",
    "created_at": "2025-01-15T10:00:00",
    "updated_at": "2025-01-15T10:00:00"
}
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/IncotermController.php:117-123`)
   - No valida si el incoterm está en uso (pedidos)
   - **Problema**: Puede eliminar incoterms en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Sin Validación de Código Único

2. **No Valida Unicidad de Código** (`app/Http/Controllers/v2/IncotermController.php`)
   - No valida que el código sea único
   - **Recomendación**: Agregar unique constraint en BD

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

