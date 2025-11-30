# Etiquetas - Gestión de Etiquetas (Labels)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Label` representa una **plantilla de etiqueta** para impresión. Permite almacenar configuraciones de formato para diferentes tipos de etiquetas que pueden ser utilizadas en el sistema.

**Archivo del modelo**: `app/Models/Label.php`

**Propósito**: Gestionar plantillas configurables de etiquetas que pueden ser reutilizadas para imprimir información de productos, cajas, palets, etc.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `labels`

**Migración**: `database/migrations/companies/2025_06_23_085023_create_labels_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la etiqueta |
| `name` | string | NO | Nombre de la etiqueta (identificador) |
| `format` | json | YES | Configuración del formato en JSON |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

**Nota**: El campo `format` es JSON y puede almacenar cualquier estructura de configuración para definir el formato de la etiqueta (dimensiones, campos a mostrar, posición, etc.).

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name', 'format'];
```

### Casts

```php
protected $casts = [
    'format' => 'array',
];
```

El campo `format` se convierte automáticamente de JSON a array PHP y viceversa.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- **No usa `HasFactory`**: No tiene factory definida

---

## 🔗 Relaciones

**Nota**: El modelo `Label` no tiene relaciones definidas. Es un modelo independiente que almacena plantillas de configuración.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/LabelController.php`

### Métodos del Controlador

#### `index()` - Listar Etiquetas
```php
GET /v2/labels
```

**Orden**: Por nombre ascendente

**Respuesta**: Collection de `LabelResource` (NO paginada)

**Nota**: ⚠️ No hay paginación, retorna todas las etiquetas.

#### `store(Request $request)` - Crear Etiqueta
```php
POST /v2/labels
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
    'format' => 'nullable|array',
]
```

**Request body**:
```json
{
    "name": "Etiqueta Caja Standard",
    "format": {
        "width": 100,
        "height": 50,
        "fields": ["product", "lot", "weight"]
    }
}
```

**Respuesta** (201): `LabelResource`

#### `show(Label $label)` - Mostrar Etiqueta
```php
GET /v2/labels/{id}
```

**Respuesta**: `LabelResource`

#### `update(Request $request, Label $label)` - Actualizar Etiqueta
```php
PUT /v2/labels/{id}
```

**Validación**:
```php
[
    'name' => 'sometimes|string|max:255',
    'format' => 'nullable|array',
]
```

**Respuesta**: `LabelResource`

#### `destroy(Label $label)` - Eliminar Etiqueta
```php
DELETE /v2/labels/{id}
```

**Respuesta**: Mensaje de éxito

#### `options()` - Opciones para Select
```php
GET /v2/labels/options
```

**Respuesta**: Array simple con `id` y `name`
```json
[
    {
        "id": 1,
        "name": "Etiqueta Caja Standard"
    },
    ...
]
```

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/LabelResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Etiqueta Caja Standard",
    "format": {
        "width": 100,
        "height": 50,
        "fields": ["product", "lot", "weight"]
    }
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/labels/*`

**Rutas definidas**:
- `GET /v2/labels` - Listar
- `POST /v2/labels` - Crear
- `GET /v2/labels/{id}` - Mostrar
- `PUT /v2/labels/{id}` - Actualizar
- `DELETE /v2/labels/{id}` - Eliminar
- `GET /v2/labels/options` - Opciones

---

## 📝 Estructura del Campo `format`

El campo `format` es JSON/array y puede almacenar cualquier estructura. No hay un esquema definido en el código. Ejemplos posibles:

```json
{
    "width": 100,
    "height": 50,
    "unit": "mm",
    "fields": [
        {
            "type": "text",
            "name": "product",
            "position": {"x": 10, "y": 10}
        },
        {
            "type": "barcode",
            "name": "gs1_128",
            "position": {"x": 10, "y": 30}
        }
    ]
}
```

**⚠️ Nota**: La estructura exacta del campo `format` no está documentada en el código y puede variar según la implementación del frontend o el sistema de impresión.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Sin Paginación en Index

1. **Retorna Todas las Etiquetas** (`app/Http/Controllers/v2/LabelController.php:11-14`)
   - No hay paginación en `index()`
   - **Líneas**: 11-14
   - **Problema**: Puede retornar muchas etiquetas sin límite
   - **Recomendación**: Agregar paginación si se esperan muchas etiquetas

### ⚠️ Sin Validación de Estructura de format

2. **format Sin Validación de Esquema** (`app/Http/Controllers/v2/LabelController.php:20, 36`)
   - Solo valida que sea array, no valida estructura
   - **Líneas**: 20, 36
   - **Problema**: Pueden guardarse estructuras inconsistentes
   - **Recomendación**: 
     - Documentar esquema esperado
     - Agregar validación de estructura si se requiere consistencia

### ⚠️ Sin Validación de Unicidad de name

3. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/LabelController.php`)
   - No valida que el nombre sea único
   - **Problema**: Pueden crearse etiquetas con nombres duplicados
   - **Recomendación**: Agregar unique constraint en BD o validación

### ⚠️ Código Comentado

4. **Método destroy Alternativo Comentado** (`app/Http/Controllers/v2/LabelController.php:68-74`)
   - Hay código comentado de método `destroy` alternativo
   - **Líneas**: 68-74
   - **Recomendación**: Eliminar código comentado

### ⚠️ Sin Relaciones

5. **No Hay Relaciones Definidas** (`app/Models/Label.php`)
   - No hay relaciones con otros modelos
   - **Estado**: Puede ser intencional si las etiquetas son independientes
   - **Recomendación**: Documentar si se esperan relaciones futuras

### ⚠️ Sin HasFactory

6. **No Usa HasFactory** (`app/Models/Label.php`)
   - No tiene trait `HasFactory`
   - **Estado**: No crítico, solo afecta testing
   - **Recomendación**: Agregar si se necesita para testing

### ⚠️ Estructura de format No Documentada

7. **format Sin Esquema Definido** (`app/Models/Label.php:15`)
   - El campo `format` puede almacenar cualquier estructura JSON
   - **Problema**: No hay documentación sobre qué estructura se espera
   - **Recomendación**: 
     - Documentar estructura esperada
     - O crear modelo/schema para validación
     - O agregar comentarios explicativos en el modelo

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

