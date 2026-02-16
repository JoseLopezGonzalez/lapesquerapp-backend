# Catálogos - Transportes (Transports)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Transport` representa una **empresa de transporte** o transportista. Los transportes están vinculados a pedidos y clientes, y contienen información de contacto y emails para notificaciones.

**Archivo del modelo**: `app/Models/Transport.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `transports`

**Migración**: `database/migrations/companies/2023_12_19_145548_create_transports_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del transporte |
| `name` | string | NO | Nombre del transportista |
| `vat_number` | string | NO | Número de identificación fiscal (NIF/CIF) |
| `address` | text | NO | Dirección del transportista |
| `emails` | text | YES | Emails concatenados (formato especial con CC:) |
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
    'vat_number',
    'address',
    'emails',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `orders()` - Pedidos
```php
public function orders()
{
    return $this->hasMany(Order::class);
}
```
- Relación uno-a-muchos con `Order`
- Pedidos que usan este transporte

### 2. `customers()` - Clientes
```php
public function customers()
{
    return $this->hasMany(Customer::class);
}
```
- Relación uno-a-muchos con `Customer`
- Clientes que usan este transporte por defecto

---

## 🔢 Accessors (Atributos Calculados)

### `getEmailsArrayAttribute()`

Extrae emails regulares del campo `emails`.

### `getCcEmailsArrayAttribute()`

Extrae emails en copia (CC) del campo `emails`.

### `extractEmails($type)`

Método helper privado que procesa el campo `emails`. Similar al de `Customer` y `Supplier`.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/TransportController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Transportes
```php
GET /v2/transports
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)
- `address`: Buscar por dirección (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `TransportResource`

#### `store(Request $request)` - Crear Transporte
```php
POST /v2/transports
```

**Validación**:
```php
[
    'name' => 'required|string|min:3',
    'vatNumber' => 'required|string|regex:/^[A-Z0-9]{8,12}$/',
    'address' => 'required|string|min:10',
    'emails' => 'nullable|array',
    'emails.*' => 'email',
    'ccEmails' => 'nullable|array',
    'ccEmails.*' => 'email',
]
```

**Request body**:
```json
{
    "name": "Transportes Ejemplo S.L.",
    "vatNumber": "B12345678",
    "address": "Calle Transporte 123",
    "emails": ["transporte@example.com"],
    "ccEmails": ["admin@example.com"]
}
```

**Comportamiento**: Convierte arrays de emails a formato texto con separadores

**Respuesta** (201): `TransportResource`

#### `show(string $id)` - Mostrar Transporte
```php
GET /v2/transports/{id}
```

#### `update(Request $request, string $id)` - Actualizar Transporte
```php
PUT /v2/transports/{id}
```

**Validación**: Igual que `store()`

#### `destroy(string $id)` - Eliminar Transporte
```php
DELETE /v2/transports/{id}
```

**Advertencia**: ⚠️ No valida si el transporte está en uso (pedidos, clientes)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Transportes
```php
DELETE /v2/transports
```

#### `options()` - Opciones para Select
```php
GET /v2/transports/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/TransportResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Transportes Ejemplo S.L.",
    "vatNumber": "B12345678",
    "address": "Calle Transporte 123",
    "emails": ["transporte@example.com"],
    "ccEmails": ["admin@example.com"]
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/transports/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/TransportController.php:166-172`)
   - No valida si el transporte está en uso (pedidos, clientes)
   - **Líneas**: 166-172
   - **Problema**: Puede eliminar transportes en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Formato de Emails Complejo

2. **Almacenamiento de Emails en Texto** (`app/Models/Transport.php:78-97`)
   - Similar a Customer y Supplier
   - **Problema**: Formato propenso a errores
   - **Recomendación**: Usar tabla relacionada o campo JSON

### ⚠️ Validación de VAT Number

3. **Regex VAT Number Limitado** (`app/Http/Controllers/v2/TransportController.php:60`)
   - Valida `^[A-Z0-9]{8,12}$`
   - **Problema**: No cubre todos los formatos de NIF/CIF
   - **Recomendación**: Validación más específica según país

### ⚠️ Sin Validación de Unicidad

4. **No Valida vatNumber Único** (`app/Http/Controllers/v2/TransportController.php`)
   - No valida que `vat_number` sea único
   - **Recomendación**: Agregar unique constraint en BD

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

