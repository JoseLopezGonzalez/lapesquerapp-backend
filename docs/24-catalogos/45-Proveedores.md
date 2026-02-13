# Catálogos - Proveedores (Suppliers)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Supplier` representa un **proveedor** de la empresa. Los proveedores pueden ser de diferentes tipos (materia prima, cebo, etc.) y contienen información de contacto, códigos de integración con sistemas externos, y configuraciones específicas para exportación de cebo.

**Archivo del modelo**: `app/Models/Supplier.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `suppliers`

**Migración base**: `database/migrations/companies/2024_05_29_153926_create_suppliers_table.php`

**Migración adicional**:
- `2025_06_18_125840_update_suppliers_emails_field.php` - Actualiza campo `emails` a `text`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del proveedor |
| `name` | string | NO | Nombre del proveedor |
| `type` | string | YES | Tipo de proveedor |
| `contact_person` | string | YES | Persona de contacto |
| `phone` | string | YES | Teléfono de contacto |
| `emails` | text | YES | Emails concatenados (formato especial con CC:) |
| `address` | text | YES | Dirección del proveedor |
| `cebo_export_type` | string | YES | Tipo de exportación de cebo |
| `facil_com_code` | string | YES | Código para sistema Facilcom |
| `a3erp_cebo_code` | string | YES | Código A3ERP para cebo |
| `facilcom_cebo_code` | string | YES | Código Facilcom para cebo |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

**Nota**: El campo `emails` almacena emails en formato especial similar a `Customer`:
- Emails regulares: `email1@example.com;email2@example.com;`
- Emails CC: `CC:email3@example.com;`
- Separados por `;` y cada línea separada por `\n`

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'type',
    'contact_person',
    'phone',
    'emails',
    'address',
    'cebo_export_type',
    'facil_com_code',
    'a3erp_cebo_code',
    'facilcom_cebo_code',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

**Nota**: El modelo no tiene relaciones explícitas definidas, aunque probablemente esté relacionado con:
- `RawMaterialReception` (recepciones de materia prima)
- `CeboDispatch` (despachos de cebo)

---

## 🔢 Accessors (Atributos Calculados)

### `getEmailsArrayAttribute()`

Extrae emails regulares del campo `emails`.

```php
return $this->extractEmails('regular');
```

### `getCcEmailsArrayAttribute()`

Extrae emails en copia (CC) del campo `emails`.

```php
return $this->extractEmails('cc');
```

### `extractEmails($type)`

Método helper privado que procesa el campo `emails`. Similar al de `Customer`.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/SupplierController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Proveedores
```php
GET /v2/suppliers
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `SupplierResource`

#### `store(Request $request)` - Crear Proveedor
```php
POST /v2/suppliers
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
    'type' => 'nullable|string|max:255',
    'contact_person' => 'nullable|string|max:255',
    'phone' => 'nullable|string|max:50',
    'emails' => 'nullable|array',
    'emails.*' => 'string|email:rfc,dns|distinct',
    'ccEmails' => 'nullable|array',
    'ccEmails.*' => 'string|email:rfc,dns|distinct',
    'address' => 'nullable|string|max:1000',
    'cebo_export_type' => 'nullable|string|max:255',
    'a3erp_cebo_code' => 'nullable|string|max:255',
    'facilcom_cebo_code' => 'nullable|string|max:255',
    'facil_com_code' => 'nullable|string|max:255',
]
```

**Request body**:
```json
{
    "name": "Proveedor Ejemplo S.L.",
    "type": "Materia Prima",
    "contact_person": "Juan Pérez",
    "phone": "+34 123 456 789",
    "emails": ["proveedor@example.com"],
    "ccEmails": ["admin@example.com"],
    "address": "Calle Proveedor 123",
    "cebo_export_type": "A3ERP",
    "a3erp_cebo_code": "PROV001"
}
```

**Comportamiento especial**:
- Convierte arrays de emails a formato texto con separadores
- Agrega prefijo `CC:` a emails en copia

**Respuesta** (201): `SupplierResource`

#### `show($id)` - Mostrar Proveedor
```php
GET /v2/suppliers/{id}
```

**Respuesta**: `SupplierResource`

#### `update(Request $request, $id)` - Actualizar Proveedor
```php
PUT /v2/suppliers/{id}
```

**Validación**: Igual que `store()`

#### `destroy(string $id)` - Eliminar Proveedor
```php
DELETE /v2/suppliers/{id}
```

**Advertencia**: ⚠️ No valida si el proveedor está en uso (recepciones, despachos)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Proveedores
```php
DELETE /v2/suppliers
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/suppliers/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/SupplierResource.php`

**Campos expuestos** (aproximados):
```json
{
    "id": 1,
    "name": "Proveedor Ejemplo S.L.",
    "type": "Materia Prima",
    "contact_person": "Juan Pérez",
    "phone": "+34 123 456 789",
    "emails": ["proveedor@example.com"],
    "ccEmails": ["admin@example.com"],
    "address": "Calle Proveedor 123",
    "cebo_export_type": "A3ERP",
    "facil_com_code": "PROV001",
    "a3erp_cebo_code": "PROV001",
    "facilcom_cebo_code": "FAC001"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/suppliers/*`

---

## 📝 Ejemplos de Uso

### Crear un Proveedor
```http
POST /v2/suppliers
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Proveedor Nuevo S.L.",
    "type": "Cebo",
    "contact_person": "María García",
    "phone": "+34 987 654 321",
    "emails": ["contacto@proveedor.com"],
    "address": "Calle Proveedor 456"
}
```

### Buscar Proveedores
```http
GET /v2/suppliers?name=ejemplo
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/SupplierController.php:133-139`)
   - No valida si el proveedor está en uso (recepciones, despachos)
   - **Líneas**: 133-139
   - **Problema**: Puede eliminar proveedores en uso, rompiendo relaciones
   - **Recomendación**: 
     - Validar relaciones antes de eliminar
     - O usar soft deletes

### ⚠️ Formato de Emails Complejo

2. **Almacenamiento de Emails en Texto** (`app/Models/Supplier.php:56-75`)
   - Emails se almacenan como texto con formato especial (similar a Customer)
   - **Líneas**: 56-75
   - **Problema**: Formato propenso a errores, difícil de mantener
   - **Recomendación**: Usar tabla relacionada o campo JSON estructurado

### ⚠️ Sin Validación de Nombre Único

3. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/SupplierController.php`)
   - No valida que el nombre sea único
   - **Problema**: Pueden crearse proveedores duplicados
   - **Recomendación**: 
     - Agregar unique constraint en BD
     - O validar en controlador

### ⚠️ Sin Relaciones Definidas

4. **No Hay Relaciones Explícitas** (`app/Models/Supplier.php`)
   - No hay relaciones definidas aunque probablemente existan
   - **Problema**: No se puede acceder fácilmente a recepciones/despachos
   - **Recomendación**: Agregar relaciones si existen en BD

### ⚠️ Campos Específicos de Cebo

5. **Campos Específicos de Cebo** (`app/Models/Supplier.php`)
   - Tiene campos específicos para cebo (`cebo_export_type`, `a3erp_cebo_code`, `facilcom_cebo_code`)
   - **Estado**: Correcto si solo algunos proveedores son de cebo
   - **Recomendación**: Considerar normalización si crece la complejidad

### ⚠️ Validación de Emails

6. **Validación de Emails con RFC y DNS** (`app/Http/Controllers/v2/SupplierController.php:48, 101`)
   - Valida emails con `email:rfc,dns`
   - **Estado**: ✅ Correcto pero puede fallar si DNS no está disponible

### ⚠️ Sin Filtros Avanzados

7. **Filtros Limitados** (`app/Http/Controllers/v2/SupplierController.php:13-35`)
   - Solo filtra por `id`, `ids`, y `name`
   - **Problema**: No permite filtrar por tipo, códigos, etc.
   - **Recomendación**: Agregar filtros adicionales si se necesitan

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

