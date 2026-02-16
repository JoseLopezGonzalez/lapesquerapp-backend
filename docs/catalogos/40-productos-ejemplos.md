# Ejemplos de API - Productos (Products)

Este documento muestra ejemplos concretos de las respuestas y datos esperados para los endpoints de productos.

---

## 📥 GET /v2/products (index)

### Ejemplo de respuesta

```json
{
  "data": [
    {
      "id": 1,
      "name": "Filetes de atún rojo",
      "species": {
        "id": 5,
        "name": "Atún rojo",
        "scientificName": "Thunnus thynnus",
        "fao": "BFT",
        "image": "https://example.com/atun-rojo.jpg"
      },
      "captureZone": {
        "id": 2,
        "name": "Atlántico Norte"
      },
      "category": {
        "id": 1,
        "name": "Pescado",
        "description": "Productos de pescado",
        "active": true
      },
      "family": {
        "id": 3,
        "name": "Filetes",
        "description": "Productos en filetes",
        "category": {
          "id": 1,
          "name": "Pescado",
          "description": "Productos de pescado",
          "active": true
        },
        "active": true
      },
      "articleGtin": "1234567890123",
      "boxGtin": "1234567890124",
      "palletGtin": "1234567890125",
      "a3erpCode": "ATU001",
      "facilcomCode": "FAC001"
    },
    {
      "id": 2,
      "name": "Rodajas de merluza",
      "species": {
        "id": 8,
        "name": "Merluza",
        "scientificName": "Merluccius merluccius",
        "fao": "HKE",
        "image": null
      },
      "captureZone": {
        "id": 1,
        "name": "Mediterráneo"
      },
      "category": null,
      "family": null,
      "articleGtin": null,
      "boxGtin": null,
      "palletGtin": null,
      "a3erpCode": null,
      "facilcomCode": null
    }
  ],
  "links": {
    "first": "http://localhost/api/v2/products?page=1",
    "last": "http://localhost/api/v2/products?page=5",
    "prev": null,
    "next": "http://localhost/api/v2/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "http://localhost/api/v2/products",
    "per_page": 14,
    "to": 14,
    "total": 68
  }
}
```

### Parámetros de consulta opcionales

- `id`: Filtrar por ID específico
- `ids`: Array de IDs (ej: `?ids[]=1&ids[]=2`)
- `name`: Búsqueda parcial por nombre (ej: `?name=atún`)
- `species`: Array de IDs de especies (ej: `?species[]=5&species[]=8`)
- `captureZones`: Array de IDs de zonas de captura
- `categories`: Array de IDs de categorías
- `families`: Array de IDs de familias
- `articleGtin`: Filtrar por GTIN del artículo
- `boxGtin`: Filtrar por GTIN de la caja
- `palletGtin`: Filtrar por GTIN del palet
- `perPage`: Elementos por página (default: 14)

---

## 📥 GET /v2/products/{id} (show)

### Ejemplo de respuesta

```json
{
  "message": "Producto obtenido con éxito",
  "data": {
    "id": 1,
    "name": "Filetes de atún rojo",
    "species": {
      "id": 5,
      "name": "Atún rojo",
      "scientificName": "Thunnus thynnus",
      "fao": "BFT",
      "image": "https://example.com/atun-rojo.jpg"
    },
    "captureZone": {
      "id": 2,
      "name": "Atlántico Norte"
    },
    "category": {
      "id": 1,
      "name": "Pescado",
      "description": "Productos de pescado",
      "active": true
    },
    "family": {
      "id": 3,
      "name": "Filetes",
      "description": "Productos en filetes",
      "category": {
        "id": 1,
        "name": "Pescado",
        "description": "Productos de pescado",
        "active": true
      },
      "active": true
    },
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125",
    "a3erpCode": "ATU001",
    "facilcomCode": "FAC001"
  }
}
```

### Notas

- El endpoint carga automáticamente las relaciones: `species`, `captureZone`, `family.category`, `family`
- Si alguna relación no existe, devuelve un objeto vacío `[]` o `null` según el caso
- El campo `category` se obtiene a través de `family.category`, por lo que si no hay familia, será `null`

---

## 📤 POST /v2/products (create/store)

### Datos esperados en el request body

```json
{
  "name": "Filetes de atún rojo",
  "speciesId": 5,
  "captureZoneId": 2,
  "familyId": 3,
  "articleGtin": "1234567890123",
  "boxGtin": "1234567890124",
  "palletGtin": "1234567890125",
  "a3erp_code": "ATU001",
  "facil_com_code": "FAC001"
}
```

### Ejemplo mínimo (solo campos requeridos)

```json
{
  "name": "Filetes de atún rojo",
  "speciesId": 5,
  "captureZoneId": 2
}
```

### Ejemplo sin GTINs (campos opcionales omitidos)

```json
{
  "name": "Rodajas de merluza",
  "speciesId": 8,
  "captureZoneId": 1,
  "familyId": 4
}
```

### Ejemplo con GTINs como null

```json
{
  "name": "Filetes de atún rojo",
  "speciesId": 5,
  "captureZoneId": 2,
  "familyId": 3,
  "articleGtin": null,
  "boxGtin": null,
  "palletGtin": null
}
```

### Ejemplo con GTINs como string vacío (se normaliza a null)

```json
{
  "name": "Filetes de atún rojo",
  "speciesId": 5,
  "captureZoneId": 2,
  "articleGtin": "",
  "boxGtin": "",
  "palletGtin": ""
}
```

### Compatibilidad con snake_case

El endpoint también acepta los campos en formato `snake_case` para compatibilidad:

```json
{
  "name": "Filetes de atún rojo",
  "species_id": 5,
  "capture_zone_id": 2,
  "family_id": 3,
  "article_gtin": "1234567890123",
  "box_gtin": "1234567890124",
  "pallet_gtin": "1234567890125"
}
```

### Validaciones

| Campo | Requerido | Tipo | Validación |
|-------|-----------|------|------------|
| `name` | ✅ Sí | string | min:3, max:255, único en la tabla |
| `speciesId` | ✅ Sí | integer | debe existir en `tenant.species` |
| `captureZoneId` | ✅ Sí | integer | debe existir en `tenant.capture_zones` |
| `familyId` | ❌ No | integer | debe existir en `tenant.product_families` (si se envía) |
| `articleGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (si se envía) |
| `boxGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (si se envía) |
| `palletGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (si se envía) |
| `a3erp_code` | ❌ No | string | max:255 |
| `facil_com_code` | ❌ No | string | max:255 |

### Respuesta exitosa (201 Created)

```json
{
  "message": "Producto creado con éxito",
  "data": {
    "id": 1,
    "name": "Filetes de atún rojo",
    "species": {
      "id": 5,
      "name": "Atún rojo",
      "scientificName": "Thunnus thynnus",
      "fao": "BFT",
      "image": "https://example.com/atun-rojo.jpg"
    },
    "captureZone": {
      "id": 2,
      "name": "Atlántico Norte"
    },
    "category": {
      "id": 1,
      "name": "Pescado",
      "description": "Productos de pescado",
      "active": true
    },
    "family": {
      "id": 3,
      "name": "Filetes",
      "description": "Productos en filetes",
      "category": {
        "id": 1,
        "name": "Pescado",
        "description": "Productos de pescado",
        "active": true
      },
      "active": true
    },
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125",
    "a3erpCode": "ATU001",
    "facilcomCode": "FAC001"
  }
}
```

### Errores de validación (422 Unprocessable Entity)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "Ya existe un producto con este nombre."
    ],
    "speciesId": [
      "The selected species id is invalid."
    ],
    "articleGtin": [
      "El GTIN del artículo debe tener entre 8 y 14 dígitos numéricos."
    ]
  }
}
```

---

## 📝 PUT/PATCH /v2/products/{id} (update)

### Datos esperados en el request body

**Todos los campos son opcionales** (usar `sometimes` en validación). Solo se actualizan los campos que se envían.

### Ejemplo actualizando solo el nombre

```json
{
  "name": "Filetes de atún rojo premium"
}
```

### Ejemplo actualizando múltiples campos

```json
{
  "name": "Filetes de atún rojo premium",
  "speciesId": 5,
  "captureZoneId": 2,
  "familyId": 3,
  "articleGtin": "9876543210987",
  "boxGtin": "9876543210988",
  "palletGtin": "9876543210989",
  "a3erp_code": "ATU002",
  "facil_com_code": "FAC002"
}
```

### Ejemplo eliminando GTINs (enviar null o string vacío)

```json
{
  "articleGtin": null,
  "boxGtin": "",
  "palletGtin": null
}
```

### Compatibilidad con snake_case

También acepta formato `snake_case`:

```json
{
  "name": "Filetes de atún rojo premium",
  "species_id": 5,
  "capture_zone_id": 2,
  "family_id": 3
}
```

### Validaciones

| Campo | Requerido | Tipo | Validación |
|-------|-----------|------|------------|
| `name` | ❌ No* | string | min:3, max:255, único (excepto el producto actual) |
| `speciesId` | ❌ No* | integer | debe existir en `tenant.species` |
| `captureZoneId` | ❌ No* | integer | debe existir en `tenant.capture_zones` |
| `familyId` | ❌ No | integer | debe existir en `tenant.product_families` (si se envía) |
| `articleGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (excepto el producto actual) |
| `boxGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (excepto el producto actual) |
| `palletGtin` | ❌ No | string | regex: `^[0-9]{8,14}$`, único (excepto el producto actual) |
| `a3erp_code` | ❌ No | string | max:255 |
| `facil_com_code` | ❌ No | string | max:255 |

*Nota: Los campos marcados con `sometimes|required` solo son requeridos si se envían en el request.

### Respuesta exitosa (200 OK)

```json
{
  "message": "Producto actualizado con éxito",
  "data": {
    "id": 1,
    "name": "Filetes de atún rojo premium",
    "species": {
      "id": 5,
      "name": "Atún rojo",
      "scientificName": "Thunnus thynnus",
      "fao": "BFT",
      "image": "https://example.com/atun-rojo.jpg"
    },
    "captureZone": {
      "id": 2,
      "name": "Atlántico Norte"
    },
    "category": {
      "id": 1,
      "name": "Pescado",
      "description": "Productos de pescado",
      "active": true
    },
    "family": {
      "id": 3,
      "name": "Filetes",
      "description": "Productos en filetes",
      "category": {
        "id": 1,
        "name": "Pescado",
        "description": "Productos de pescado",
        "active": true
      },
      "active": true
    },
    "articleGtin": "9876543210987",
    "boxGtin": "9876543210988",
    "palletGtin": "9876543210989",
    "a3erpCode": "ATU002",
    "facilcomCode": "FAC002"
  }
}
```

### Errores de validación (422 Unprocessable Entity)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "Ya existe un producto con este nombre."
    ],
    "articleGtin": [
      "Ya existe un producto con este GTIN de artículo."
    ]
  }
}
```

---

## 📋 Estructura de datos comunes

### Objeto `species`

```json
{
  "id": 5,
  "name": "Atún rojo",
  "scientificName": "Thunnus thynnus",
  "fao": "BFT",
  "image": "https://example.com/atun-rojo.jpg"
}
```

### Objeto `captureZone`

```json
{
  "id": 2,
  "name": "Atlántico Norte"
}
```

### Objeto `category`

```json
{
  "id": 1,
  "name": "Pescado",
  "description": "Productos de pescado",
  "active": true
}
```

### Objeto `family`

```json
{
  "id": 3,
  "name": "Filetes",
  "description": "Productos en filetes",
  "category": {
    "id": 1,
    "name": "Pescado",
    "description": "Productos de pescado",
    "active": true
  },
  "active": true
}
```

---

## 🔍 Notas importantes

1. **GTINs opcionales**: Los campos `articleGtin`, `boxGtin` y `palletGtin` son completamente opcionales. Pueden:
   - Omitirse del request
   - Enviarse como `null`
   - Enviarse como string vacío `""` (se normaliza automáticamente a `null`)

2. **Compatibilidad snake_case**: Tanto `create` como `update` aceptan campos en formato `snake_case` (`species_id`, `capture_zone_id`, etc.) para compatibilidad con versiones anteriores.

3. **Eager loading**: Los endpoints `show` y `index` cargan automáticamente las relaciones necesarias para evitar el problema N+1.

4. **Paginación**: El endpoint `index` devuelve resultados paginados. Por defecto muestra 14 elementos por página, pero se puede cambiar con el parámetro `perPage`.

5. **Ordenamiento**: Los resultados del `index` siempre se ordenan por nombre ascendente.

6. **Transacciones**: Tanto `create` como `update` usan transacciones de base de datos para garantizar la consistencia.

