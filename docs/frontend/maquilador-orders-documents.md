# Maquilador — Documentación y Emails en Pedidos

**Fecha:** 2026-06-25  
**Estado:** Implementado en backend  
**Complementa:** `external-processors-relations.md`

---

## 1. Resumen de lo nuevo

Se han añadido dos capacidades al flujo de pedidos cuando hay un maquilador asignado:

1. **Dos campos nuevos en el pedido** para controlar qué aparece en los documentos del maquilador.
2. **Dos documentos PDF nuevos** (CMR para maquilador + letreros para maquilador) con datos anonimizados.
3. **Un endpoint de email** para enviar esos documentos directamente al maquilador.

El maquilador recibe estos documentos para saber cómo cargar la mercancía en el camión sin ver los datos reales del cliente de destino.

---

## 2. Campos nuevos en el pedido

### 2.1 `maquiladorDestination`

| Propiedad | Valor |
|---|---|
| Tipo | `string \| null` |
| Máx. | 500 caracteres |
| Obligatorio | No |
| Cuándo usarlo | Solo cuando el pedido tiene maquilador asignado |

**Para qué sirve:** Es el texto que aparece como destinatario/consignatario en el CMR del maquilador y en sus letreros. Reemplaza el nombre y dirección real del cliente.

**Ejemplos de valor:**
```
"Cliente Nº1, Olano Italia"
"Ref. 007, Atlantica FR"
"Distribución Norte"
```

**Si no se rellena:** Los documentos del maquilador muestran `"Cliente #ID"` (ej: `"Cliente #42"`) como fallback.

---

### 2.2 `loadingAddress`

| Propiedad | Valor |
|---|---|
| Tipo | `string \| null` |
| Máx. | 500 caracteres |
| Obligatorio | No |
| Cuándo usarlo | Cuando el punto de carga de este pedido es distinto al de la empresa |

**Para qué sirve:** Controla el campo "Lugar de carga" que aparece en el CMR (tanto el normal como el del maquilador). Antes estaba fijo como "ISLA CRISTINA - HUELVA" hardcodeado. Ahora es dinámico.

**Prioridad en el CMR:**
1. `orders.loadingAddress` si tiene valor → se usa ese.
2. Si no hay valor y hay maquilador → dirección del maquilador (su `city` + `province`).
3. Si no hay nada → `company.address.city` + `company.address.province` desde settings (comportamiento anterior).

**Sugerencia de UX:** Cuando el usuario asigna un maquilador al pedido, pre-rellenar este campo con la dirección del maquilador (`externalProcessor.address`). El usuario puede editarlo o borrarlo.

**Ejemplos de valor:**
```
"Polígono Industrial El Arenal, nave 4. Vigo (Pontevedra)"
"C/ Pesqueros 12. Burela (Lugo)"
```

---

## 3. Cambios en el contrato de pedidos

### 3.1 Crear pedido — `POST /api/v2/orders`

Nuevos campos opcionales:

```json
{
  "customer": 15,
  "entryDate": "2026-06-25",
  "loadDate": "2026-06-26",
  "externalProcessor": 7,
  "maquiladorDestination": "Cliente Nº1, Olano Italia",
  "loadingAddress": "Polígono Industrial, nave 4. Vigo (Pontevedra)"
}
```

Si no hay maquilador o no se quieren usar estos campos, omitirlos o enviar `null`.

---

### 3.2 Editar pedido — `PUT /api/v2/orders/{id}` / `PATCH /api/v2/orders/{id}`

Mismos campos, todos opcionales con `sometimes`:

```json
{
  "externalProcessor": 7,
  "maquiladorDestination": "Cliente Nº1, Olano Italia",
  "loadingAddress": "Polígono Industrial, nave 4. Vigo (Pontevedra)"
}
```

Para limpiar un campo:

```json
{
  "maquiladorDestination": null,
  "loadingAddress": null
}
```

Si el campo no se envía, el valor existente no cambia.

---

### 3.3 Detalle de pedido — `GET /api/v2/orders/{id}` (show)

La respuesta del detalle (`OrderDetailsResource`) ahora incluye los dos campos nuevos:

```json
{
  "id": 42,
  "status": "pending",
  "loadDate": "2026-06-26",
  "externalProcessorId": 7,
  "externalProcessor": {
    "id": 7,
    "name": "Congelados Atlántico S.L.",
    "vatNumber": "B12345678",
    "sanitaryRegistrationNumber": "12.34567/PO",
    "contactPerson": "María García",
    "phone": "+34 986 000 000",
    "emails": ["produccion@maquilador.com"],
    "ccEmails": ["admin@maquilador.com"],
    "address": "Polígono Industrial, nave 4",
    "city": "Vigo",
    "province": "Pontevedra",
    "isActive": true
  },
  "maquiladorDestination": "Cliente Nº1, Olano Italia",
  "loadingAddress": "Polígono Industrial, nave 4. Vigo (Pontevedra)"
}
```

Si no hay valores:

```json
{
  "maquiladorDestination": null,
  "loadingAddress": null
}
```

> **Nota:** `maquiladorDestination` y `loadingAddress` solo están en el recurso de detalle (show, store, update). **No aparecen en el listado paginado** (`GET /api/v2/orders`), que es el recurso compacto.

---

## 4. Endpoints de documentos PDF del maquilador

Ambos endpoints requieren que el pedido tenga un `external_processor_id` asignado. Si no, devuelven `422`.

---

### 4.1 CMR del maquilador

```http
GET /api/v2/orders/{orderId}/pdf/maquilador-cmr
```

**Headers requeridos:** `X-Tenant`, `Authorization: Bearer {token}`

**Qué devuelve:** PDF del CMR donde:
- Expedidor: empresa (desde settings)
- Consignatario y destinatario: `maquiladorDestination` (o `"Cliente #ID"` si está vacío)
- Transporte: igual que el CMR normal
- Lugar de carga: `loadingAddress` → dirección del maquilador → settings empresa (por prioridad)
- Matrículas, temperatura, incoterm, fecha: igual que el CMR normal

**Sin datos del cliente real** en ningún campo.

**Respuesta si no hay maquilador (422):**
```json
{
  "message": "El pedido no tiene un transformador externo asignado."
}
```

---

### 4.2 Letreros del maquilador

```http
GET /api/v2/orders/{orderId}/pdf/maquilador-signs
```

**Qué devuelve:** PDF apaisado con un letrero por palet donde:
- Expedidor: empresa (desde settings)
- Consignatario: `maquiladorDestination` (o `"Cliente #ID"`)
- Datos del palet: ID, cajas, peso
- Número de pedido + QR codes del palet y del pedido
- Nombre del transporte

**Sin dirección real del cliente.**

**Respuesta si no hay maquilador (422):**
```json
{
  "message": "El pedido no tiene un transformador externo asignado."
}
```

---

## 5. Endpoint de envío de email al maquilador

```http
POST /api/v2/orders/{orderId}/send-maquilador-documents
```

**Headers requeridos:** `X-Tenant`, `Authorization: Bearer {token}`  
**Body:** ninguno (vacío o `{}`)

**Qué hace:**
1. Genera el CMR del maquilador en PDF.
2. Genera los letreros del maquilador en PDF.
3. Envía ambos PDFs adjuntos al email del maquilador asignado al pedido.
   - **Para:** `externalProcessor.emails`
   - **CC:** `externalProcessor.ccEmails`
   - **BCC:** `company.bcc_email` (configurado en settings del tenant)

**Respuesta de éxito (200):**
```json
{
  "message": "Documentación enviada al maquilador correctamente."
}
```

**Respuesta si no hay maquilador asignado (422):**
```json
{
  "message": "El pedido no tiene un transformador externo asignado.",
  "userMessage": "Asigna un maquilador al pedido antes de enviar su documentación."
}
```

**Respuesta si el maquilador no tiene emails (422):**
```json
{
  "message": "El maquilador no tiene emails configurados.",
  "userMessage": "El transformador externo asignado no tiene ninguna dirección de email. Añade emails en su ficha antes de enviar."
}
```

**Permisos:** Requiere autenticación. Rol `Comercial` recibe 403 (igual que el resto de envíos de documentación).

---

## 6. Envío personalizado — `send-custom-documents` actualizado

El endpoint existente también acepta ahora los tipos y destinatario del maquilador:

```http
POST /api/v2/orders/{orderId}/send-custom-documents
```

**Nuevos tipos de documento válidos:**
- `maquilador-cmr`
- `maquilador-signs`

**Nuevo destinatario válido:**
- `external_processor`

**Ejemplo — enviar solo el CMR al maquilador:**

```json
{
  "documents": [
    {
      "type": "maquilador-cmr",
      "recipients": ["external_processor"]
    }
  ]
}
```

**Ejemplo — enviar ambos docs al maquilador y el CMR normal al transporte:**

```json
{
  "documents": [
    {
      "type": "maquilador-cmr",
      "recipients": ["external_processor"]
    },
    {
      "type": "maquilador-signs",
      "recipients": ["external_processor"]
    },
    {
      "type": "CMR",
      "recipients": ["transport"]
    }
  ]
}
```

Lista completa de tipos de documento válidos (incluidos los anteriores):

| Clave | Nombre |
|---|---|
| `loading-note` | Nota de Carga |
| `packing-list` | Packing List |
| `CMR` | CMR (normal) |
| `valued-loading-note` | Nota de Carga Valorada |
| `order-confirmation` | Confirmación de Pedido |
| `transport-pickup-request` | Solicitud de Recogida |
| `maquilador-cmr` | CMR Maquilador |
| `maquilador-signs` | Letreros Maquilador |

Lista completa de destinatarios válidos:

| Clave | Fuente de emails |
|---|---|
| `customer` | Emails configurados en el pedido (campo `emails` del order) |
| `transport` | Emails del transporte asignado |
| `salesperson` | Emails del comercial asignado |
| `external_processor` | Emails del maquilador asignado |

---

## 7. Flujo recomendado en la UI

### 7.1 Formulario de crear/editar pedido

Cuando el pedido tiene un maquilador asignado, mostrar (preferiblemente en el mismo bloque o sección):

```
Maquilador / Transformador externo
[ Selector de maquilador (existente) ]

Destino para documentos del maquilador
[ Campo texto — ej: "Cliente Nº1, Olano Italia"          ]
  Texto que aparecerá como destinatario en el CMR y letreros del maquilador.

Lugar de carga
[ Campo texto — ej: "Polígono Industrial, nave 4. Vigo"  ]
  Punto desde donde carga el maquilador. Si se deja vacío se usa su dirección o la de la empresa.
```

**Comportamiento sugerido al seleccionar un maquilador:**
- Pre-rellenar `loadingAddress` con la dirección del maquilador si el campo está vacío: `externalProcessor.address + ', ' + externalProcessor.city + ' (' + externalProcessor.province + ')'`
- No pre-rellenar `maquiladorDestination` — lo introduce el usuario manualmente.

**Cuando se quita el maquilador del pedido:**
- Limpiar visualmente los dos campos (enviar `null` al guardar si el usuario confirma, o simplemente vaciarlos en el formulario).

---

### 7.2 Detalle del pedido — sección del maquilador

Mostrar la sección solo si `externalProcessorId` no es null:

```
── Maquilador ──────────────────────────────────────────
Empresa:     Congelados Atlántico S.L. (B12345678)
Reg. san.:   12.34567/PO
Contacto:    María García · +34 986 000 000
Email:       produccion@maquilador.com

Destino en sus docs:   Cliente Nº1, Olano Italia
Lugar de carga:        Polígono Industrial, nave 4. Vigo (Pontevedra)
────────────────────────────────────────────────────────
```

Si `maquiladorDestination` es null, mostrar: `"No configurado (se usará 'Cliente #ID')"`.  
Si `loadingAddress` es null, mostrar: `"No configurado (se usará dirección del maquilador o empresa)"`.

---

### 7.3 Acciones en el detalle del pedido

Añadir al menú de documentos / acciones del pedido cuando hay maquilador asignado:

**Descargar documentos del maquilador:**

| Acción | Endpoint |
|---|---|
| Descargar CMR maquilador | `GET /api/v2/orders/{id}/pdf/maquilador-cmr` |
| Descargar letreros maquilador | `GET /api/v2/orders/{id}/pdf/maquilador-signs` |

**Enviar al maquilador:**

| Acción | Endpoint |
|---|---|
| Enviar documentación al maquilador | `POST /api/v2/orders/{id}/send-maquilador-documents` |

El botón de envío puede mostrar los emails del maquilador como referencia antes de confirmar:  
`"Se enviará a: produccion@maquilador.com (CC: admin@maquilador.com)"`

Si no hay `maquiladorDestination` relleno, mostrar un aviso antes de enviar:  
`"El campo 'Destino para documentos del maquilador' está vacío. Los documentos mostrarán 'Cliente #42'. ¿Continuar?"`

---

### 7.4 Visibilidad de acciones por estado del pedido

No hay restricciones por estado implementadas actualmente. Las acciones de descarga y envío están disponibles en cualquier estado (`pending`, `finished`, `incident`). El frontend puede optar por ocultarlas cuando el pedido está `finished` si conviene al flujo operativo.

---

## 8. Errores esperados y cómo manejarlos

| Código | Cuándo | Acción recomendada en UI |
|---|---|---|
| `422` en descarga/envío | El pedido no tiene maquilador asignado | Mostrar aviso: "Asigna un maquilador al pedido antes de generar sus documentos." |
| `422` en envío | El maquilador no tiene emails | Mostrar aviso: "El maquilador no tiene emails. Actualiza su ficha." + enlace a la ficha del maquilador |
| `403` | Usuario con rol Comercial | Ocultar las acciones de envío para Comercial |
| `401` | Sin autenticar | Redirigir a login |

---

## 9. Resumen de rutas nuevas

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/v2/orders/{id}/pdf/maquilador-cmr` | Descargar CMR del maquilador |
| `GET` | `/api/v2/orders/{id}/pdf/maquilador-signs` | Descargar letreros del maquilador |
| `POST` | `/api/v2/orders/{id}/send-maquilador-documents` | Enviar CMR + letreros al email del maquilador |

Rutas existentes actualizadas:

| Método | Ruta | Cambio |
|---|---|---|
| `POST` | `/api/v2/orders/{id}/send-custom-documents` | Acepta nuevos tipos `maquilador-cmr`, `maquilador-signs` y destinatario `external_processor` |

---

## 10. Registro de cambios

| Fecha | Cambio |
|---|---|
| 2026-06-25 | Campos `maquiladorDestination` y `loadingAddress` en pedidos |
| 2026-06-25 | PDF CMR maquilador (`maquilador-cmr`) |
| 2026-06-25 | PDF letreros maquilador (`maquilador-signs`) |
| 2026-06-25 | Endpoint `send-maquilador-documents` |
| 2026-06-25 | Fix: CMR normal ya no tiene "ISLA CRISTINA" hardcodeado — usa `loadingAddress` o settings |
| 2026-06-25 | `send-custom-documents` acepta tipos y destinatario maquilador |
