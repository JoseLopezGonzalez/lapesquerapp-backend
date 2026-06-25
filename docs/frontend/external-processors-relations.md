# Transformadores Externos / Maquiladores — Relaciones con otras entidades

**Fecha:** 2026-06-25  
**Estado:** Documento vivo para evolucionar relaciones frontend/backend  
**Entidad base:** `ExternalProcessor`  

---

## 1. Objetivo

Este documento registra las relaciones funcionales y técnicas entre **transformadores externos / maquiladores** y otras entidades del ERP.

Debe actualizarse cada vez que el backend añada una relación nueva para que el frontend pueda adaptar pantallas, formularios, filtros y flujos sin depender de suposiciones.

Relaciones previstas a futuro:

- Pedidos.
- Usuarios externos.
- Almacenes.
- Palets.
- Documentos.
- Emails automáticos.
- Producción externa / maquila.

La primera relación implementada es con **pedidos**.

---

## 2. Relación implementada: pedidos

### 2.1 Resumen funcional

Un pedido puede tener o no tener un transformador externo asignado.

Casos de uso actuales:

- El pedido sale directamente desde fábrica/planta del maquilador.
- El equipo interno necesita identificar qué pedidos dependen de un maquilador.
- El frontend puede mostrar y filtrar pedidos por maquilador.

Casos de uso futuros:

- Permitir que el maquilador saque documentos asociados a sus pedidos.
- Enviar documentos por email al maquilador de forma automática.
- Usar el maquilador como origen operativo/logístico del pedido.
- Preparar vistas limitadas para actores externos.

### 2.2 Reglas actuales

- La relación es opcional.
- Un pedido puede tener como máximo un maquilador.
- Un maquilador puede estar asociado a muchos pedidos.
- El pedido puede crearse sin maquilador.
- El maquilador puede asignarse, cambiarse o quitarse al editar el pedido.
- No hay todavía restricciones por estado del pedido.
- No hay todavía permisos especiales para usuarios externos.

### 2.3 Modelo conceptual

```text
ExternalProcessor 1 ─── N Order

orders.external_processor_id nullable
```

---

## 3. Cambios de contrato en pedidos

### 3.1 Crear pedido

Endpoint existente:

```http
POST /api/v2/orders
```

Nuevo campo opcional:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `externalProcessor` | integer/null | No | ID del transformador externo/maquilador asociado al pedido. |

Validación backend:

```php
'externalProcessor' => 'nullable|integer|exists:tenant.external_processors,id'
```

Ejemplo:

```json
{
  "customer": 15,
  "entryDate": "2026-06-25",
  "loadDate": "2026-06-26",
  "payment": 1,
  "salesperson": 2,
  "transport": 4,
  "externalProcessor": 7,
  "billingAddress": "Dirección fiscal",
  "shippingAddress": "Dirección de entrega"
}
```

Para crear sin maquilador:

```json
{
  "customer": 15,
  "entryDate": "2026-06-25",
  "loadDate": "2026-06-26",
  "externalProcessor": null
}
```

También se puede omitir el campo.

### 3.2 Editar pedido

Endpoint existente:

```http
PUT /api/v2/orders/{orderId}
PATCH /api/v2/orders/{orderId}
```

Nuevo campo opcional:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `externalProcessor` | integer/null | Asigna, cambia o limpia el maquilador del pedido. |

Asignar:

```json
{
  "externalProcessor": 7
}
```

Quitar maquilador:

```json
{
  "externalProcessor": null
}
```

Si el campo no se envía, la relación actual no cambia.

### 3.3 Listar pedidos

Endpoint existente:

```http
GET /api/v2/orders
```

Nuevo filtro:

| Query param | Tipo | Descripción |
|-------------|------|-------------|
| `externalProcessors[]` | integer[] | Filtra pedidos asociados a uno o varios maquiladores. |

Ejemplo:

```http
GET /api/v2/orders?externalProcessors[]=7&externalProcessors[]=9
```

### 3.4 Respuesta de pedidos

Los resources de pedido devuelven dos campos nuevos:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `externalProcessorId` | integer/null | ID del maquilador asociado, o `null`. |
| `externalProcessor` | object/null | Datos compactos del maquilador asociado, o `null`. |

Ejemplo en `GET /api/v2/orders`:

```json
{
  "id": 42,
  "customer": {
    "id": 15,
    "name": "Cliente Ejemplo"
  },
  "status": "pending",
  "loadDate": "2026-06-26",
  "externalProcessorId": 7,
  "externalProcessor": {
    "id": 7,
    "name": "Congelados Atlántico S.L.",
    "legalName": "Congelados Atlántico Sociedad Limitada",
    "vatNumber": "B12345678",
    "sanitaryRegistrationNumber": "12.34567/PO",
    "contactPerson": "María García",
    "phone": "+34 986 000 000",
    "emails": ["produccion@maquilador.com"],
    "ccEmails": ["administracion@maquilador.com"],
    "address": "Polígono Industrial, nave 4",
    "city": "Vigo",
    "postalCode": "36201",
    "province": "Pontevedra",
    "country": {
      "id": 1,
      "name": "España"
    },
    "isActive": true
  }
}
```

Si no tiene maquilador:

```json
{
  "externalProcessorId": null,
  "externalProcessor": null
}
```

---

## 4. Cambios recomendados en frontend

### 4.1 Formulario de pedido

Añadir un selector opcional:

```text
Maquilador / Transformador externo
```

Fuente de datos:

```http
GET /api/v2/external-processors/options
```

Respuesta:

```json
[
  {
    "id": 7,
    "name": "Congelados Atlántico S.L.",
    "vatNumber": "B12345678",
    "isActive": true
  }
]
```

Comportamiento:

- El selector debe permitir dejar el valor vacío.
- Si se selecciona un valor, enviar `externalProcessor: id`.
- Si se limpia el selector en edición, enviar `externalProcessor: null`.
- Mostrar preferiblemente `name` y, si cabe, `vatNumber` como texto auxiliar.

### 4.2 Detalle de pedido

Mostrar una sección o campo dentro de datos generales:

```text
Maquilador: Congelados Atlántico S.L.
CIF: B12345678
Registro sanitario: 12.34567/PO
Contacto: María García · +34 986 000 000
```

Si no tiene:

```text
Maquilador: Sin asignar
```

### 4.3 Listado de pedidos

Opciones:

- Añadir columna compacta `Maquilador`.
- O mostrarlo en una segunda línea dentro de la columna de cliente/logística.

Columna sugerida:

| Campo UI | Fuente |
|----------|--------|
| Maquilador | `externalProcessor.name` |
| CIF | `externalProcessor.vatNumber` |
| Estado del maquilador | `externalProcessor.isActive` |

Si no hay maquilador, mostrar `-` o `Sin maquilador`.

### 4.4 Filtros de pedidos

Añadir filtro multi-select:

```text
Maquiladores
```

Enviar:

```http
externalProcessors[]=7&externalProcessors[]=9
```

El filtro debería usar `/external-processors/options`, que solo devuelve maquiladores activos.

Nota: si en el futuro se necesita filtrar por maquiladores inactivos asociados a histórico, habrá que añadir un endpoint/options que incluya inactivos o usar el listado completo con `isActive=false`.

---

## 5. Validaciones y errores

### 5.1 Validación al crear/editar pedido

Si se envía un ID inexistente:

```json
{
  "message": "Error de validación.",
  "errors": {
    "externalProcessor": [
      "El transformador externo seleccionado no existe."
    ]
  }
}
```

### 5.2 Qué debe hacer el frontend

- Si recibe `422` en `externalProcessor`, marcar el selector de maquilador.
- Si recibe `403`, ocultar o bloquear la acción según permisos generales de pedido.
- Si el maquilador asignado aparece como `isActive: false`, mostrarlo igualmente en el histórico del pedido, pero evitar seleccionarlo para nuevos pedidos si no aparece en `options`.

---

## 6. Impacto en pantallas existentes

### Pantalla de pedidos

Actualizar:

- tabla/listado;
- filtros;
- formulario de crear pedido;
- formulario de editar pedido;
- detalle de pedido.

### Pantalla de transformadores externos

Por ahora no hay contador ni pestaña de pedidos en el CRUD de maquiladores.

Puede añadirse más adelante:

- contador `orders_count`;
- pestaña "Pedidos";
- enlace filtrado a `/orders?externalProcessors[]=id`.

No está implementado todavía.

---

## 7. Fuera de alcance de esta relación

Aunque esta relación prepara el terreno, todavía no se ha implementado:

- permisos de maquiladores para ver sus pedidos;
- envío automático de emails al maquilador;
- generación/descarga de documentos por maquilador;
- relación con palets;
- relación con almacenes externos;
- costes de transformación;
- trazabilidad de maquila.

---

## 8. Registro de relaciones

| Fecha | Relación | Estado | Notas |
|-------|----------|--------|-------|
| 2026-06-25 | `orders.external_processor_id` | Implementada | Pedido opcionalmente asociado a un maquilador. |

