# Catálogos - Especies (Species)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Species` representa una **especie pesquera**. Las especies identifican el tipo de pescado o marisco y contienen información taxonómica (nombre científico, código FAO) y el arte de pesca utilizado.

**Concepto clave**: Las especies son fundamentales para la clasificación de productos. Cada producto está asociado a una especie, y esto permite filtrar y agrupar productos por tipo de pescado/marisco.

**Archivo del modelo**: `app/Models/Species.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `species`

**Migración**: `database/migrations/companies/2023_08_09_145303_create_species_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la especie |
| `name` | string | NO | Nombre común de la especie |
| `scientific_name` | string | NO | Nombre científico |
| `fao` | string(3) | NO | Código FAO (3 caracteres) |
| `image` | string | NO | Ruta de la imagen de la especie |
| `fishing_gear_id` | bigint | NO | FK a `fishing_gears` - Arte de pesca |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `fishing_gears`

**Constraints**:
- `fishing_gear_id` → `fishing_gears.id`

**Nota**: El campo `fishing_gear_id` fue agregado posteriormente según comentarios en código.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'scientific_name',
    'fao',
    'image',
    'fishing_gear_id',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `fishingGear()` - Arte de Pesca
```php
public function fishingGear()
{
    return $this->belongsTo(FishingGear::class, 'fishing_gear_id');
}
```
- Relación muchos-a-uno con `FishingGear`
- Arte de pesca utilizado para esta especie

### 2. `productions()` - Producciones
```php
public function productions()
{
    return $this->hasMany(Production::class, 'species_id');
}
```
- Relación uno-a-muchos con `Production`
- Lotes de producción de esta especie

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/SpeciesController.php`

### Métodos del Controlador

#### `index()` - Listar Especies
```php
GET /v2/species
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)
- `fishingGears`: Array de IDs de artes de pesca
- `fao`: Buscar por código FAO (LIKE)
- `scientificName`: Buscar por nombre científico (LIKE)

**Query parameters**:
- `perPage`: Elementos por página (default: 10)

**Orden**: Por nombre ascendente

**Respuesta**: Collection paginada de `SpeciesResource`

#### `store(Request $request)` - Crear Especie
```php
POST /v2/species
```

**Validación**:
```php
[
    'name' => 'required|string|min:2',
    'scientificName' => 'required|string|min:2',
    'fao' => 'required|regex:/^[A-Z]{3,5}$/',
    'fishingGearId' => 'required|exists:tenant.fishing_gears,id',
]
```

**Request body**:
```json
{
    "name": "Atún rojo",
    "scientificName": "Thunnus thynnus",
    "fao": "BFT",
    "fishingGearId": 1
}
```

**Respuesta** (201): `SpeciesResource`

#### `show(Species $species)` - Mostrar Especie
```php
GET /v2/species/{id}
```

**Respuesta**: `SpeciesResource`

#### `update(Request $request, Species $species)` - Actualizar Especie
```php
PUT /v2/species/{id}
```

**Validación**: Igual que `store()`

**Respuesta**: `SpeciesResource`

#### `destroy(Species $species)` - Eliminar Especie
```php
DELETE /v2/species/{id}
```

**Advertencia**: ⚠️ No valida si la especie está en uso (productos, producciones)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Especies
```php
DELETE /v2/species
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/species/options
```

**Respuesta**: Array con formato `"Nombre (Nombre científico - FAO)"`
```json
[
    {
        "id": 1,
        "name": "Atún rojo (Thunnus thynnus - BFT)"
    },
    ...
]
```

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/SpeciesResource.php`

**Campos expuestos** (desde `toArrayAssoc()`):
```json
{
    "id": 1,
    "name": "Atún rojo",
    "scientificName": "Thunnus thynnus",
    "fao": "BFT",
    "image": "path/to/image.jpg"
}
```

**Nota**: `toArrayAssoc()` no incluye `fishingGear`, aunque existe la relación.

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/species/*`

---

## 📝 Ejemplos de Uso

### Crear una Especie
```http
POST /v2/species
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Merluza",
    "scientificName": "Merluccius merluccius",
    "fao": "HKE",
    "fishingGearId": 2
}
```

### Buscar Especies
```http
GET /v2/species?name=atún&fao=BFT
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo image Requerido Pero No Validado

1. **image No Validado en Store/Update** (`app/Http/Controllers/v2/SpeciesController.php:64-69`)
   - Campo `image` existe en BD y fillable, pero no se valida en controlador
   - **Líneas**: 64-69
   - **Problema**: Puede crear especies sin imagen o con valor inválido
   - **Recomendación**: 
     - Agregar validación de `image` (nullable o required según caso)
     - O manejar upload de imagen si es necesario

### ⚠️ Eliminación Sin Validaciones

2. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/SpeciesController.php:114-118`)
   - No valida si la especie está en uso (productos, producciones)
   - **Líneas**: 114-118
   - **Problema**: Puede eliminar especies en uso, rompiendo relaciones
   - **Recomendación**: 
     - Validar relaciones antes de eliminar
     - O usar soft deletes

### ⚠️ Validación de FAO

3. **Regex FAO Permite 3-5 Caracteres** (`app/Http/Controllers/v2/SpeciesController.php:67`)
   - Valida `^[A-Z]{3,5}$` pero en migración es `string(3)`
   - **Líneas**: 67
   - **Problema**: Inconsistencia entre validación y BD
   - **Recomendación**: 
     - Ajustar validación a exactamente 3 caracteres
     - O cambiar tipo en BD si se necesita más

### ⚠️ toArrayAssoc() No Incluye fishingGear

4. **Falta fishingGear en toArrayAssoc()** (`app/Models/Species.php:17-26`)
   - Método no incluye `fishingGear` aunque existe relación
   - **Líneas**: 17-26
   - **Problema**: Información faltante en respuestas
   - **Recomendación**: Agregar `fishingGear` si se necesita

### ⚠️ Código Comentado

5. **Comentarios en Código** (`app/Models/Species.php:28-29`)
   - Comentarios sobre fishing_gear_id
   - **Líneas**: 28-29
   - **Problema**: Código comentado que confunde
   - **Recomendación**: Eliminar comentarios obsoletos

### ⚠️ Validación de fishingGearId Requerido

6. **fishingGearId Requerido** (`app/Http/Controllers/v2/SpeciesController.php:68`)
   - Campo requerido pero no hay validación de existencia en create si la especie ya existe
   - **Estado**: ✅ Validación correcta con `exists:tenant.fishing_gears,id`

### ⚠️ Sin Validación de Nombre Único

7. **No Valida Unicidad de Nombres** (`app/Http/Controllers/v2/SpeciesController.php`)
   - No valida que nombre científico o nombre común sean únicos
   - **Problema**: Pueden crearse especies duplicadas
   - **Recomendación**: 
     - Agregar unique constraints en BD
     - O validar en controlador

### ⚠️ Options Formatea Nombre Complejo

8. **Formato de Nombre en options()** (`app/Http/Controllers/v2/SpeciesController.php:144`)
   - Formato: `"{name} ({scientific_name} - {fao})"`
   - **Líneas**: 144
   - **Estado**: Correcto, pero podría ser configurable

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

