# Catálogos - Clientes (Customers)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Customer` representa un **cliente** de la empresa. Los clientes contienen información completa de contacto, direcciones, términos de pago, vendedor asignado, y configuraciones para envío de emails. Los clientes están vinculados a pedidos y tienen códigos de integración con sistemas externos (A3ERP, Facilcom).

**Archivo del modelo**: `app/Models/Customer.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `customers`

**Migración base**: `database/migrations/companies/2023_12_19_152335_create_customers_table.php`

**Migraciones adicionales**:
- `2025_05_07_094955_add_a3erp_code_to_customers_table.php` - Agrega `a3erp_code`
- `2025_05_07_130527_add_facilcom_code_to_customers_table.php` - Agrega `facilcom_code`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del cliente |
| `name` | string | NO | Nombre del cliente |
| `alias` | string | YES | Alias del cliente (auto-generado: "Cliente Nº {id}") |
| `vat_number` | string | NO | Número de identificación fiscal (NIF/CIF) |
| `payment_term_id` | bigint | NO | FK a `payment_terms` - Términos de pago |
| `billing_address` | text | NO | Dirección de facturación |
| `shipping_address` | text | NO | Dirección de envío |
| `transportation_notes` | text | YES | Notas de transporte |
| `production_notes` | text | YES | Notas de producción |
| `accounting_notes` | text | YES | Notas contables |
| `salesperson_id` | bigint | NO | FK a `salespeople` - Vendedor asignado |
| `emails` | text | NO | Emails concatenados (formato especial con CC:) |
| `contact_info` | text | NO | Información de contacto |
| `country_id` | bigint | NO | FK a `countries` - País del cliente |
| `transport_id` | bigint | NO | FK a `transports` - Transporte por defecto |
| `a3erp_code` | string | YES | Código para sistema A3ERP |
| `facilcom_code` | string | YES | Código para sistema Facilcom |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign keys a `payment_terms`, `salespeople`, `countries`, `transports`

**Constraints**:
- `payment_term_id` → `payment_terms.id`
- `salesperson_id` → `salespeople.id`
- `country_id` → `countries.id`
- `transport_id` → `transports.id`

**Nota**: El campo `emails` almacena emails en formato especial:
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
    'payment_term_id',
    'billing_address',
    'shipping_address',
    'transportation_notes',
    'production_notes',
    'accounting_notes',
    'salesperson_id',
    'emails',
    'contact_info',
    'country_id',
    'transport_id',
    'a3erp_code',
    'facilcom_code',
    'alias',
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
- Todos los pedidos del cliente

### 2. `salesperson()` - Vendedor
```php
public function salesperson()
{
    return $this->belongsTo(Salesperson::class);
}
```
- Relación muchos-a-uno con `Salesperson`

### 3. `country()` - País
```php
public function country()
{
    return $this->belongsTo(Country::class);
}
```
- Relación muchos-a-uno con `Country`

### 4. `transport()` - Transporte
```php
public function transport()
{
    return $this->belongsTo(Transport::class);
}
```
- Relación muchos-a-uno con `Transport`

### 5. `payment_term()` - Términos de Pago
```php
public function payment_term()
{
    return $this->belongsTo(PaymentTerm::class);
}
```
- Relación muchos-a-uno con `PaymentTerm`

---

## 🔢 Accessors (Atributos Calculados)

### `getEmailsArrayAttribute()`

Extrae emails regulares del campo `emails`.

```php
return $this->extractEmails('regular');
```

**Retorna**: Array de strings con emails (sin prefijo CC:)

### `getCcEmailsArrayAttribute()`

Extrae emails en copia (CC) del campo `emails`.

```php
return $this->extractEmails('cc');
```

**Retorna**: Array de strings con emails (sin prefijo CC:)

### `extractEmails($type)`

Método helper privado que procesa el campo `emails`.

**Lógica**:
1. Divide por `;`
2. Si tiene prefijo `CC:` o `cc:`, lo agrega a CC
3. Si no tiene prefijo, lo agrega a regulares

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/CustomerController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Clientes
```php
GET /v2/customers
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)
- `vatNumber`: Filtrar por NIF/CIF exacto
- `paymentTerms`: Array de IDs de términos de pago
- `salespeople`: Array de IDs de vendedores
- `countries`: Array de IDs de países

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 10)

**Respuesta**: Collection paginada de `CustomerResource`

#### `store(Request $request)` - Crear Cliente
```php
POST /v2/customers
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
    'vatNumber' => 'nullable|string|max:20',
    'billing_address' => 'nullable|string|max:1000',
    'shipping_address' => 'nullable|string|max:1000',
    'transportation_notes' => 'nullable|string|max:1000',
    'production_notes' => 'nullable|string|max:1000',
    'accounting_notes' => 'nullable|string|max:1000',
    'emails' => 'nullable|array',
    'emails.*' => 'string|email:rfc,dns|distinct',
    'ccEmails' => 'nullable|array',
    'ccEmails.*' => 'string|email:rfc,dns|distinct',
    'contact_info' => 'nullable|string|max:1000',
    'salesperson_id' => 'nullable|exists:tenant.salespeople,id',
    'country_id' => 'nullable|exists:tenant.countries,id',
    'payment_term_id' => 'nullable|exists:tenant.payment_terms,id',
    'transport_id' => 'nullable|exists:tenant.transports,id',
    'a3erp_code' => 'nullable|string|max:255',
    'facilcom_code' => 'nullable|string|max:255',
]
```

**Request body**:
```json
{
    "name": "Cliente Ejemplo S.L.",
    "vatNumber": "B12345678",
    "billing_address": "Calle Principal 123",
    "shipping_address": "Calle Envío 456",
    "emails": ["cliente@example.com"],
    "ccEmails": ["copia@example.com"],
    "salesperson_id": 1,
    "country_id": 1,
    "payment_term_id": 1,
    "transport_id": 1
}
```

**Comportamiento especial**:
1. Convierte arrays de emails a formato texto con separadores
2. Agrega prefijo `CC:` a emails en copia
3. Crea el cliente
4. **Auto-genera `alias`**: "Cliente Nº {id}" después de crear

**Respuesta** (201): `CustomerResource`

#### `show(string $id)` - Mostrar Cliente
```php
GET /v2/customers/{id}
```

**Respuesta**: `CustomerResource`

#### `update(Request $request, string $id)` - Actualizar Cliente
```php
PUT /v2/customers/{id}
```

**Validación**: Igual que `store()`

**Comportamiento especial**:
- Recalcula `alias` a "Cliente Nº {id}" (sobrescribe si ya existe)

#### `destroy(string $id)` - Eliminar Cliente
```php
DELETE /v2/customers/{id}
```

**Advertencia**: ⚠️ No valida si el cliente tiene pedidos asociados

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Clientes
```php
DELETE /v2/customers
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/customers/options
```

**Respuesta**: Array simple con `id` y `name`
```json
[
    {"id": 1, "name": "Cliente Ejemplo S.L."},
    ...
]
```

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/CustomerResource.php`

Usa el método `toArrayAssoc()` del modelo.

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Cliente Ejemplo S.L.",
    "alias": "Cliente Nº 1",
    "vatNumber": "B12345678",
    "paymentTerm": {...},
    "billingAddress": "Calle Principal 123",
    "shippingAddress": "Calle Envío 456",
    "transportationNotes": "...",
    "productionNotes": "...",
    "accountingNotes": "...",
    "salesperson": {...},
    "emails": ["cliente@example.com"],
    "ccEmails": ["copia@example.com"],
    "contactInfo": "...",
    "country": {...},
    "transport": {...},
    "a3erpCode": "CLI001",
    "facilcomCode": "FAC001",
    "createdAt": "2025-01-15T10:00:00",
    "updatedAt": "2025-01-15T10:00:00"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/customers/*`

---

## 📝 Ejemplos de Uso

### Crear un Cliente
```http
POST /v2/customers
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Cliente Nuevo S.L.",
    "vatNumber": "B87654321",
    "billing_address": "Dirección facturación",
    "shipping_address": "Dirección envío",
    "emails": ["cliente@example.com", "contacto@example.com"],
    "ccEmails": ["admin@example.com"],
    "salesperson_id": 1,
    "country_id": 1,
    "payment_term_id": 1,
    "transport_id": 1
}
```

### Buscar Clientes
```http
GET /v2/customers?name=ejemplo&salespeople[]=1&countries[]=1
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Alias Auto-generado Siempre

1. **Alias Se Sobrescribe en Update** (`app/Http/Controllers/v2/CustomerController.php:226-227`)
   - En `update()`, siempre recalcula alias a "Cliente Nº {id}"
   - **Líneas**: 226-227
   - **Problema**: Si el usuario cambió el alias personalizado, se pierde
   - **Recomendación**: Solo generar alias si no existe, no sobrescribir

### ⚠️ Eliminación Sin Validaciones

2. **No Valida Pedidos Antes de Eliminar** (`app/Http/Controllers/v2/CustomerController.php:236-242`)
   - No valida si el cliente tiene pedidos asociados
   - **Líneas**: 236-242
   - **Problema**: Puede eliminar clientes con pedidos, rompiendo integridad referencial
   - **Recomendación**: 
     - Validar `$customer->orders()->exists()` antes de eliminar
     - O usar soft deletes

### ⚠️ Formato de Emails Complejo

3. **Almacenamiento de Emails en Texto** (`app/Models/Customer.php:123-142`)
   - Emails se almacenan como texto con formato especial (separadores `;` y prefijos `CC:`)
   - **Líneas**: 123-142
   - **Problema**: 
     - Formato propenso a errores
     - Difícil de mantener y validar
   - **Recomendación**: 
     - Usar tabla relacionada `customer_emails` con tipo (regular/cc)
     - O usar campo JSON estructurado

### ⚠️ Campos Requeridos en BD Pero Opcionales en Validación

4. **Campos NOT NULL en BD Pero Opcionales en Request** (`app/Http/Controllers/v2/CustomerController.php:76-95`)
   - Varios campos son `NOT NULL` en BD pero opcionales en validación
   - **Problema**: Puede causar errores SQL si no se proporcionan
   - **Recomendación**: 
     - Hacer campos nullable en BD
     - O hacer requeridos en validación

5. **vat_number Requerido en BD Pero Nullable en Validación** (`database/migrations/companies/2023_12_19_152335_create_customers_table.php:18`)
   - Campo `vat_number` es `string` (NOT NULL) pero validación permite null
   - **Problema**: Error SQL si se envía null
   - **Recomendación**: Alinear BD y validación

### ⚠️ Validación de Emails Compleja

6. **Validación de Emails con RFC y DNS** (`app/Http/Controllers/v2/CustomerController.php:85-87`)
   - Valida emails con `email:rfc,dns`
   - **Estado**: ✅ Correcto pero puede fallar si DNS no está disponible
   - **Recomendación**: Considerar validación menos estricta si hay problemas de conectividad

### ⚠️ toArrayAssoc() Sin Validación de Relaciones

7. **Acceso Directo a Relaciones Sin Validar** (`app/Models/Customer.php:66-77`)
   - Accede directamente a `->payment_term->toArrayAssoc()` sin validar null
   - **Líneas**: 66-77
   - **Problema**: Error si relación es null
   - **Recomendación**: Usar `optional()` o validar antes de acceder

### ⚠️ Sin Eager Loading por Defecto

8. **index() No Usa Eager Loading** (`app/Http/Controllers/v2/CustomerController.php:16-61`)
   - No carga relaciones necesarias (salesperson, country, transport, payment_term)
   - **Problema**: Queries N+1 si se acceden estas relaciones en Resource
   - **Recomendación**: Agregar `->with(['salesperson', 'country', 'transport', 'payment_term'])`

### ⚠️ Métodos Vacíos

9. **create() y edit() Vacíos** (`app/Http/Controllers/v2/CustomerController.php:66-69, 159-162`)
   - Métodos están vacíos (no implementados)
   - **Líneas**: 66-69, 159-162
   - **Estado**: No crítico, pero código muerto

### ⚠️ Sin Validación de Unicidad

10. **No Valida vatNumber Único** (`app/Http/Controllers/v2/CustomerController.php`)
    - No valida que `vat_number` sea único
    - **Problema**: Pueden existir clientes duplicados con mismo NIF
    - **Recomendación**: 
      - Agregar unique constraint en BD
      - O validar en controlador

### ⚠️ Migraciones Duplicadas

11. **Migración a3erp_code Duplicada** (`database/migrations/companies/2025_03_14_094533_add_a3erp_code_to_customers_table.php`, `2025_05_07_094955_add_a3erp_code_to_customers_table.php`)
    - Hay dos migraciones para agregar `a3erp_code`
    - **Problema**: Posible conflicto o migración fallida
    - **Recomendación**: Verificar y limpiar migraciones duplicadas

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

