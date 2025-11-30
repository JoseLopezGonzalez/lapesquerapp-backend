# Catálogos - Vendedores (Salespeople)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Salesperson` representa un **comercial o vendedor** de la empresa. Los vendedores están asignados a clientes y pedidos, y tienen emails configurados para recibir notificaciones.

**Archivo del modelo**: `app/Models/Salesperson.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `salespeople`

**Migración**: `database/migrations/companies/2023_12_19_152319_create_salespeople_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del vendedor |
| `name` | string | NO | Nombre del vendedor |
| `emails` | text | YES | Emails concatenados (formato especial con CC:) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

**⚠️ Nota importante**: El campo `emails` **NO está en la migración base** pero está en el fillable y se usa en el controlador. Puede haber una migración adicional que lo agregue, o es una inconsistencia.

**Nota**: El campo `emails` almacena emails en formato especial:
- Emails regulares: `email1@example.com;email2@example.com;`
- Emails CC: `CC:email3@example.com;`
- Separados por `;` y cada línea separada por `\n`

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name', 'emails'];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `customers()` - Clientes
```php
public function customers()
{
    return $this->hasMany(Customer::class);
}
```
- Relación uno-a-muchos con `Customer`
- Clientes asignados a este vendedor

### 2. `orders()` - Pedidos
```php
public function orders()
{
    return $this->hasMany(Order::class);
}
```
- Relación uno-a-muchos con `Order`
- Pedidos gestionados por este vendedor

---

## 🔢 Accessors (Atributos Calculados)

### `getEmailsArrayAttribute()`

Extrae emails regulares del campo `emails`.

### `getCcEmailsArrayAttribute()`

Extrae emails en copia (CC) del campo `emails`.

### `extractEmails($type)`

Método helper privado que procesa el campo `emails`. Similar al de otros modelos.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/SalespersonController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Vendedores
```php
GET /v2/salespeople
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 10)

**Respuesta**: Collection paginada de `SalespersonResource`

#### `store(Request $request)` - Crear Vendedor
```php
POST /v2/salespeople
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
    'emails' => 'nullable|array',
    'emails.*' => 'string|email:rfc,dns|distinct',
    'ccEmails' => 'nullable|array',
    'ccEmails.*' => 'string|email:rfc,dns|distinct',
]
```

**Request body**:
```json
{
    "name": "Juan Pérez",
    "emails": ["juan@example.com"],
    "ccEmails": ["admin@example.com"]
}
```

**Comportamiento**: Convierte arrays de emails a formato texto con separadores

**Respuesta** (201): `SalespersonResource`

#### `show(string $id)` - Mostrar Vendedor
```php
GET /v2/salespeople/{id}
```

#### `update(Request $request, string $id)` - Actualizar Vendedor
```php
PUT /v2/salespeople/{id}
```

**Validación**: Igual que `store()`

#### `destroy(Salesperson $salesperson)` - Eliminar Vendedor
```php
DELETE /v2/salespeople/{id}
```

**Advertencia**: ⚠️ No valida si el vendedor está en uso (clientes, pedidos)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Vendedores
```php
DELETE /v2/salespeople
```

#### `options()` - Opciones para Select
```php
GET /v2/salespeople/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/SalespersonResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Juan Pérez",
    "emails": ["juan@example.com"],
    "ccEmails": ["admin@example.com"]
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/salespeople/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo emails No Existe en Migración Base

1. **emails No Está en Migración** (`database/migrations/companies/2023_12_19_152319_create_salespeople_table.php`)
   - El campo `emails` no está en la migración base pero está en fillable y se usa en controlador
   - **Problema**: Inconsistencia entre BD y código
   - **Recomendación**: 
     - Verificar si hay migración adicional que lo agregue
     - O agregar migración para crear el campo
     - O eliminar del fillable si no se usa

### ⚠️ Eliminación Sin Validaciones

2. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/SalespersonController.php:152-156`)
   - No valida si el vendedor está en uso (clientes, pedidos)
   - **Líneas**: 152-156
   - **Problema**: Puede eliminar vendedores en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Formato de Emails Complejo

2. **Almacenamiento de Emails en Texto** (`app/Models/Salesperson.php:54-73`)
   - Similar a otros modelos
   - **Problema**: Formato propenso a errores
   - **Recomendación**: Usar tabla relacionada o campo JSON

### ⚠️ Sin Validación de Nombre Único

3. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/SalespersonController.php`)
   - No valida que el nombre sea único
   - **Recomendación**: Agregar unique constraint si se requiere

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

