# Catálogos

Documentación de endpoints para gestión de catálogos: clientes, proveedores, especies, transportes, incoterms, vendedores, artes de pesca, países, términos de pago, zonas de captura y etiquetas.

## Índice

- [Clientes](#clientes)
- [Proveedores](#proveedores)
- [Especies](#especies)
- [Transportes](#transportes)
- [Incoterms](#incoterms)
- [Vendedores](#vendedores)
- [Artes de Pesca](#artes-de-pesca)
- [Países](#países)
- [Términos de Pago](#términos-de-pago)
- [Zonas de Captura](#zonas-de-captura)
- [Etiquetas](#etiquetas)

**Nota:** Todos los endpoints de catálogos siguen el mismo patrón CRUD estándar. Se documentan los endpoints principales y se indica que los demás siguen la misma estructura.

---

## Clientes

### Listar Clientes

```http
GET /api/v2/customers
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Cliente A",
      "code": "CLI-001",
      "tax_id": "12345678A",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Cliente

```http
POST /api/v2/customers
```

#### Request Body

```json
{
  "name": "Cliente A",
  "code": "CLI-001",
  "tax_id": "12345678A",
  "address": "Calle Principal 123",
  "city": "Ciudad",
  "country_id": 1,
  "email": "cliente@example.com",
  "phone": "123456789"
}
```

---

### Mostrar Cliente

```http
GET /api/v2/customers/{id}
```

---

### Actualizar Cliente

```http
PUT /api/v2/customers/{id}
```

---

### Eliminar Cliente

```http
DELETE /api/v2/customers/{id}
```

---

### Eliminar Múltiples Clientes

```http
DELETE /api/v2/customers
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

### Opciones de Clientes (Pública)

```http
GET /api/v2/customers/op
```

**Nota:** Ruta pública, no requiere autenticación.

---

### Opciones de Clientes (Autenticada)

```http
GET /api/v2/customers/options
```

---

## Proveedores

Todos los endpoints de proveedores siguen el mismo patrón que clientes:

- `GET /api/v2/suppliers` - Listar
- `POST /api/v2/suppliers` - Crear
- `GET /api/v2/suppliers/{id}` - Mostrar
- `PUT /api/v2/suppliers/{id}` - Actualizar
- `DELETE /api/v2/suppliers/{id}` - Eliminar
- `DELETE /api/v2/suppliers` - Eliminar múltiples
- `GET /api/v2/suppliers/options` - Opciones

---

## Especies

Todos los endpoints de especies siguen el patrón CRUD estándar:

- `GET /api/v2/species` - Listar
- `POST /api/v2/species` - Crear
- `GET /api/v2/species/{id}` - Mostrar
- `PUT /api/v2/species/{id}` - Actualizar
- `DELETE /api/v2/species/{id}` - Eliminar
- `DELETE /api/v2/species` - Eliminar múltiples
- `GET /api/v2/species/options` - Opciones

---

## Transportes

Todos los endpoints de transportes siguen el patrón CRUD estándar:

- `GET /api/v2/transports` - Listar
- `POST /api/v2/transports` - Crear
- `GET /api/v2/transports/{id}` - Mostrar
- `PUT /api/v2/transports/{id}` - Actualizar
- `DELETE /api/v2/transports/{id}` - Eliminar
- `DELETE /api/v2/transports` - Eliminar múltiples
- `GET /api/v2/transports/options` - Opciones

---

## Incoterms

Todos los endpoints de incoterms siguen el patrón CRUD estándar:

- `GET /api/v2/incoterms` - Listar
- `POST /api/v2/incoterms` - Crear
- `GET /api/v2/incoterms/{id}` - Mostrar
- `PUT /api/v2/incoterms/{id}` - Actualizar
- `DELETE /api/v2/incoterms/{id}` - Eliminar
- `DELETE /api/v2/incoterms` - Eliminar múltiples
- `GET /api/v2/incoterms/options` - Opciones

---

## Vendedores

Todos los endpoints de vendedores siguen el patrón CRUD estándar:

- `GET /api/v2/salespeople` - Listar
- `POST /api/v2/salespeople` - Crear
- `GET /api/v2/salespeople/{id}` - Mostrar
- `PUT /api/v2/salespeople/{id}` - Actualizar
- `DELETE /api/v2/salespeople/{id}` - Eliminar
- `DELETE /api/v2/salespeople` - Eliminar múltiples
- `GET /api/v2/salespeople/options` - Opciones

---

## Artes de Pesca

Todos los endpoints de artes de pesca siguen el patrón CRUD estándar:

- `GET /api/v2/fishing-gears` - Listar
- `POST /api/v2/fishing-gears` - Crear
- `GET /api/v2/fishing-gears/{id}` - Mostrar
- `PUT /api/v2/fishing-gears/{id}` - Actualizar
- `DELETE /api/v2/fishing-gears/{id}` - Eliminar
- `DELETE /api/v2/fishing-gears` - Eliminar múltiples
- `GET /api/v2/fishing-gears/options` - Opciones

---

## Países

Todos los endpoints de países siguen el patrón CRUD estándar:

- `GET /api/v2/countries` - Listar
- `POST /api/v2/countries` - Crear
- `GET /api/v2/countries/{id}` - Mostrar
- `PUT /api/v2/countries/{id}` - Actualizar
- `DELETE /api/v2/countries/{id}` - Eliminar
- `DELETE /api/v2/countries` - Eliminar múltiples
- `GET /api/v2/countries/options` - Opciones

---

## Términos de Pago

Todos los endpoints de términos de pago siguen el patrón CRUD estándar:

- `GET /api/v2/payment-terms` - Listar
- `POST /api/v2/payment-terms` - Crear
- `GET /api/v2/payment-terms/{id}` - Mostrar
- `PUT /api/v2/payment-terms/{id}` - Actualizar
- `DELETE /api/v2/payment-terms/{id}` - Eliminar
- `DELETE /api/v2/payment-terms` - Eliminar múltiples
- `GET /api/v2/payment-terms/options` - Opciones

---

## Zonas de Captura

Todos los endpoints de zonas de captura siguen el patrón CRUD estándar:

- `GET /api/v2/capture-zones` - Listar
- `POST /api/v2/capture-zones` - Crear
- `GET /api/v2/capture-zones/{id}` - Mostrar
- `PUT /api/v2/capture-zones/{id}` - Actualizar
- `DELETE /api/v2/capture-zones/{id}` - Eliminar
- `DELETE /api/v2/capture-zones` - Eliminar múltiples
- `GET /api/v2/capture-zones/options` - Opciones

---

## Etiquetas

Todos los endpoints de etiquetas siguen el patrón CRUD estándar:

- `GET /api/v2/labels` - Listar
- `POST /api/v2/labels` - Crear
- `GET /api/v2/labels/{id}` - Mostrar
- `PUT /api/v2/labels/{id}` - Actualizar
- `DELETE /api/v2/labels/{id}` - Eliminar
- `GET /api/v2/labels/options` - Opciones

---

## Formato Estándar de Respuestas

### Response Exitosa - Listar (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Nombre",
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

### Response Exitosa - Crear (201)

```json
{
  "data": {
    "id": 1,
    "name": "Nombre",
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

### Response Exitosa - Actualizar (200)

```json
{
  "data": {
    "id": 1,
    "name": "Nombre Actualizado",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

### Response Exitosa - Eliminar (200)

```json
{
  "message": "Recurso eliminado correctamente."
}
```

### Response Errónea - Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo name es obligatorio.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### Response Errónea - No Encontrado (404)

```json
{
  "message": "Recurso no encontrado."
}
```

---

## Headers Requeridos

Todos los endpoints requieren:

```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json  (para POST/PUT)
```

**Excepción:** `GET /api/v2/customers/op` es pública y no requiere `Authorization`.

