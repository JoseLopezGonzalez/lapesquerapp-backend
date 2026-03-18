# Prospectos

## Endpoints

- `GET /api/v2/prospects`
- `POST /api/v2/prospects`
- `GET /api/v2/prospects/{id}`
- `PUT /api/v2/prospects/{id}`
- `DELETE /api/v2/prospects/{id}`
- `GET /api/v2/prospects/{id}/contacts`
- `POST /api/v2/prospects/{id}/contacts`
- `PUT /api/v2/prospects/{id}/contacts/{contactId}`
- `DELETE /api/v2/prospects/{id}/contacts/{contactId}`
- `POST /api/v2/prospects/{id}/convert-to-customer`
- `POST /api/v2/prospects/{id}/schedule-action`
- `DELETE /api/v2/prospects/{id}/next-action`

## Estados válidos

- `new`
- `following`
- `offer_sent`
- `customer`
- `discarded`

## Orígenes válidos

- `conxemar`
- `direct`
- `referral`
- `web`
- `other`

## Crear o actualizar prospecto

Payload:

```json
{
  "companyName": "Acme Seafood",
  "countryId": 1,
  "speciesInterest": ["langostino", "pulpo"],
  "origin": "direct",
  "status": "new",
  "notes": "Interés inicial",
  "commercialInterestNotes": "Formato 2kg, mercado horeca",
  "nextActionAt": "2026-03-20",
  "nextActionNote": "Llamar para enviar oferta",
  "lostReason": null,
  "salespersonId": 3,
  "primaryContact": {
    "name": "Ana Compras",
    "role": "Compras",
    "phone": "600111222",
    "email": "ana@acme.test"
  }
}
```

Respuesta de escritura:

```json
{
  "message": "Prospecto creado correctamente.",
  "data": {
    "id": 10,
    "companyName": "Acme Seafood",
    "status": "new",
    "nextActionAt": "2026-03-20",
    "nextActionNote": "Llamar para enviar oferta",
    "primaryContact": {
      "id": 14,
      "name": "Ana Compras",
      "isPrimary": true
    }
  },
  "warnings": [
    {
      "type": "company_name",
      "message": "Ya existe una empresa con el mismo nombre.",
      "matches": {
        "prospectIds": [4],
        "customerIds": [8]
      }
    }
  ]
}
```

`warnings` puede venir vacío. Los duplicados no bloquean.

## Listado

Filtros soportados:

- `search`
- `status[]`
- `origin[]`
- `countries[]`
- `salespeople[]`
- `perPage`

Orden por defecto:

1. prospectos con `next_action_at`
2. fecha de próxima acción ascendente
3. `company_name`

## Contactos

Un prospecto puede tener varios contactos, pero funcionalmente solo uno puede quedar como `isPrimary=true`. Al crear o editar un contacto primario, el backend desmarca el anterior.

Payload de contacto:

```json
{
  "name": "Ana Compras",
  "role": "Compras",
  "phone": "600111222",
  "email": "ana@acme.test",
  "isPrimary": true
}
```

## Conversión a cliente

`POST /api/v2/prospects/{id}/convert-to-customer`

Precondiciones:

- El prospecto debe estar en `offer_sent`
- Debe existir contacto primario
- El contacto primario debe tener teléfono o email

Efectos:

- Crea `Customer`
- Actualiza el prospecto a `status=customer`
- Guarda `customer_id` en el prospecto
- Reasigna `customer_id` en ofertas del prospecto que aún no lo tengan

Errores típicos:

- `403` si el comercial intenta convertir un prospecto ajeno
- `422` si el estado no es `offer_sent`
- `422` si falta el contacto primario o no tiene teléfono/email

## Agenda del prospecto

- `POST /api/v2/prospects/{id}/schedule-action`
- Payload:

```json
{
  "nextActionAt": "2026-03-25",
  "nextActionNote": "Enviar condiciones actualizadas"
}
```

`nextActionNote` es opcional (máx. 255 caracteres). Si no se envía, se limpia la descripción de la próxima acción.

- `DELETE /api/v2/prospects/{id}/next-action`
- Limpia `next_action_at` y `next_action_note` sin registrar interacción
