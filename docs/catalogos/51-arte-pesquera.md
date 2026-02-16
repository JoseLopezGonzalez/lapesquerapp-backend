# Catálogos - Arte Pesquera (Fishing Gear)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `FishingGear` representa un **arte de pesca** (ej: "Red de arrastre", "Pesca con caña"). Los artes de pesca están vinculados a especies, definiendo qué método de pesca se utiliza para cada tipo de pescado/marisco.

**Archivo del modelo**: `app/Models/FishingGear.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `fishing_gears`

**Migración**: `database/migrations/companies/2024_04_22_110654_create_fishing_gears_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del arte de pesca |
| `name` | string | NO | Nombre del arte de pesca |
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

### `species()` - Especies
```php
public function species()
{
    return $this->hasMany(Species::class);
}
```
- Relación uno-a-muchos con `Species`
- Especies pesqueras que usan este arte

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/FishingGearController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Artes de Pesca
```php
GET /v2/fishing-gears
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `FishingGearResource`

#### `store(Request $request)` - Crear Arte de Pesca
```php
POST /v2/fishing-gears
```

**Validación**:
```php
[
    'name' => 'required|string|min:2',
]
```

#### `show(string $id)` - Mostrar Arte de Pesca
```php
GET /v2/fishing-gears/{id}
```

#### `update(Request $request, string $id)` - Actualizar Arte de Pesca
```php
PUT /v2/fishing-gears/{id}
```

#### `destroy(string $id)` - Eliminar Arte de Pesca
```php
DELETE /v2/fishing-gears/{id}
```

**Advertencia**: ⚠️ No valida si el arte está en uso (especies)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Artes
```php
DELETE /v2/fishing-gears
```

#### `options()` - Opciones para Select
```php
GET /v2/fishing-gears/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/FishingGearResource.php`

Usa `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "Red de arrastre"
}
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/FishingGearController.php:113-119`)
   - No valida si el arte está en uso (especies)
   - **Problema**: Puede eliminar artes en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Código Comentado

2. **Comentarios en Modelo** (`app/Models/FishingGear.php:15-16`)
   - Hay comentarios sobre `fishing_gear_id` en especies
   - **Problema**: Código comentado que confunde
   - **Recomendación**: Eliminar comentarios obsoletos

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

