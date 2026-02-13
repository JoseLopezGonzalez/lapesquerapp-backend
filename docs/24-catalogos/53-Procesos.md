# Catálogos - Procesos (Process - Maestro de Procesos)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Process` representa un **proceso maestro de producción**. Define los tipos de procesos que pueden ejecutarse en un lote de producción (ej: "Fileteado", "Envasado", "Congelado"). Estos procesos se usan como plantillas al crear registros de producción (`ProductionRecord`).

**Concepto clave**: `Process` es un catálogo/maestro. `ProductionRecord` es la instancia real de un proceso ejecutado en un lote específico.

**Archivo del modelo**: `app/Models/Process.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `processes`

**Migración base**: `database/migrations/companies/2024_11_01_141253_create_processes_table.php`

**Migración adicional**:
- `2024_05_27_143913_add_species_id_to_processes_table.php` - Agrega `species_id`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del proceso |
| `name` | string | NO | Nombre del proceso (ej: "Fileteado") |
| `type` | enum | NO | Tipo: `'starting'`, `'process'`, `'final'` |
| `species_id` | bigint | NO | FK a `species` - Especie asociada (agregado después) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `species`

**Constraints**:
- `species_id` → `species.id` (onDelete: cascade)

**Tipos de proceso**:
- `starting`: Proceso inicial (ej: "Descarga")
- `process`: Proceso intermedio (ej: "Fileteado", "Envasado")
- `final`: Proceso final (ej: "Congelado", "Empaquetado")

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name', 'type'];
```

**Nota**: `species_id` no está en fillable pero existe en BD.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

**Nota**: El modelo no tiene relaciones explícitas definidas, aunque está relacionado con:
- `ProductionRecord` (a través de `process_id`)
- `Species` (a través de `species_id`)

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProcessController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Procesos
```php
GET /v2/processes
```

**Filtros disponibles** (query parameters):
- `type`: Filtrar por tipo (`starting`, `process`, `final`)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 15)

**Respuesta**: Collection paginada de `ProcessResource`

#### `store(Request $request)` - Crear Proceso
```php
POST /v2/processes
```

**Validación**:
```php
[
    'name' => 'required|string|min:2',
    'type' => 'required|in:starting,process,final',
]
```

**Request body**:
```json
{
    "name": "Fileteado",
    "type": "process"
}
```

**Respuesta** (201): `ProcessResource`

#### `show(string $id)` - Mostrar Proceso
```php
GET /v2/processes/{id}
```

#### `update(Request $request, string $id)` - Actualizar Proceso
```php
PUT /v2/processes/{id}
```

**Validación**: Igual que `store()` pero con `sometimes`

#### `destroy(string $id)` - Eliminar Proceso
```php
DELETE /v2/processes/{id}
```

**Advertencia**: ⚠️ No valida si el proceso está en uso (production_records)

#### `options(Request $request)` - Opciones para Select
```php
GET /v2/processes/options
```

**Query parameters**: `type` (opcional) - Filtrar por tipo

**Respuesta**:
```json
{
    "message": "Opciones de procesos obtenidas correctamente.",
    "data": [
        {
            "value": 1,
            "label": "Fileteado",
            "type": "process"
        },
        ...
    ]
}
```

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProcessResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Fileteado",
    "type": "process",
    "createdAt": "2025-01-15T10:00:00Z",
    "updatedAt": "2025-01-15T10:00:00Z"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/processes/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo species_id No Está en Fillable

1. **species_id No en Fillable** (`app/Models/Process.php:16`)
   - Campo `species_id` existe en BD pero no está en fillable
   - **Líneas**: 16
   - **Problema**: No se puede asignar al crear/actualizar
   - **Recomendación**: 
     - Agregar al fillable si se necesita
     - O eliminar de BD si no se usa

### ⚠️ Eliminación Sin Validaciones

2. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/ProcessController.php:90-98`)
   - No valida si el proceso está en uso (production_records)
   - **Líneas**: 90-98
   - **Problema**: Puede eliminar procesos en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Sin Relaciones Definidas

3. **No Hay Relaciones** (`app/Models/Process.php`)
   - No hay relaciones definidas aunque existen en BD
   - **Problema**: No se puede acceder fácilmente a production_records o species
   - **Recomendación**: Agregar relaciones si se necesitan

### ⚠️ Sin Validación de Nombre Único

4. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/ProcessController.php`)
   - No valida que el nombre sea único
   - **Recomendación**: Agregar unique constraint si se requiere

### ⚠️ Cascade en species_id

5. **Cascade en species_id** (`database/migrations/companies/2024_05_27_143913_add_species_id_to_processes_table.php:17`)
   - `onDelete('cascade')` en `species_id`
   - **Problema**: Si se elimina una especie, se eliminan todos sus procesos
   - **Recomendación**: Considerar `onDelete('set null')` o validar antes de eliminar

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

