# Transformadores Externos / Maquiladores — Guía de integración frontend

**Fecha:** 2026-06-25  
**Base path:** `/api/v2/`  
**Entidad:** `ExternalProcessor`  
**Pantalla sugerida:** `Catálogos > Transformadores externos`

---

## 1. Resumen del feature

El backend ya expone un CRUD completo para gestionar **transformadores externos** o **maquiladores**. Esta entidad representa empresas externas que transforman producto del tenant, pero en esta primera entrega funciona solo como **maestro independiente**.

No hay todavía vínculos con:

- usuarios externos;
- almacenes;
- pedidos;
- palets;
- procesos de producción;
- costes de maquila.

La interfaz frontend debe centrarse en una pantalla de catálogo sencilla y completa: listar, filtrar, crear, editar, ver detalle, activar/desactivar y eliminar.

---

## 2. Headers obligatorios

Igual que el resto de la API:

```http
X-Tenant: {subdominio}
Authorization: Bearer {token}
Accept: application/json
```

Para `POST`, `PUT` y `PATCH`:

```http
Content-Type: application/json
```

---

## 3. Permisos por rol

El backend aplica policy. Aunque la ruta esté dentro del grupo autenticado general, la respuesta final depende del rol.

| Acción | `tecnico` | `administrador` | `direccion` | `administracion` | `comercial` | `operario` | `supervisor` | usuarios externos |
|--------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Listar | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Ver detalle | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Crear | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Editar | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Activar/desactivar | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Eliminar | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Options | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Reglas de UI

- Ocultar la sección completa a roles sin permiso.
- Ocultar acciones de eliminar para `administracion`.
- Aunque se oculten botones, manejar siempre `403 Forbidden`.

---

## 4. Modelo de datos frontend

### Tipo recomendado

```ts
type ExternalProcessor = {
  id: number;
  name: string;
  legalName: string | null;
  vatNumber: string;
  sanitaryRegistrationNumber: string | null;
  contactPerson: string | null;
  phone: string | null;
  emails: string[];
  ccEmails: string[];
  address: string | null;
  city: string | null;
  postalCode: string | null;
  province: string | null;
  country?: {
    id: number;
    name: string;
  } | null;
  isActive: boolean;
  notes: string | null;
  createdAt: string | null;
  updatedAt: string | null;
};
```

### Campo `country`

El campo `country` se devuelve en listado, detalle, creación y edición. Si no hay país asignado, puede venir como `null` o no venir materializado según el estado de carga del resource. El frontend debe tratarlo como opcional.

---

## 5. Endpoints

### 5.1 Listar transformadores externos

```http
GET /api/v2/external-processors
```

**Query params opcionales:**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | Filtrar por ID exacto. |
| `ids[]` | integer[] | Filtrar por varios IDs. |
| `name` | string | Búsqueda parcial por `name` o `legalName`. |
| `vatNumber` | string | Búsqueda parcial por CIF/NIF/VAT. |
| `sanitaryRegistrationNumber` | string | Búsqueda parcial por registro sanitario. |
| `isActive` | boolean | Filtrar activos/inactivos. En query usar `1`/`0` o `true`/`false`. |
| `countryId` | integer | Filtrar por país. |
| `perPage` | integer | Elementos por página, 1-100. Default: 12. |
| `page` | integer | Página actual. |

**Ejemplo:**

```http
GET /api/v2/external-processors?name=atlántico&isActive=1&perPage=12&page=1
```

**Respuesta 200:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Congelados Atlántico S.L.",
      "legalName": "Congelados Atlántico Sociedad Limitada",
      "vatNumber": "B12345678",
      "sanitaryRegistrationNumber": "12.34567/PO",
      "contactPerson": "María García",
      "phone": "+34 986 000 000",
      "emails": ["produccion@empresa.com"],
      "ccEmails": ["administracion@empresa.com"],
      "address": "Polígono Industrial, nave 4",
      "city": "Vigo",
      "postalCode": "36201",
      "province": "Pontevedra",
      "country": {
        "id": 1,
        "name": "España"
      },
      "isActive": true,
      "notes": "Transformador externo principal.",
      "createdAt": "2026-06-25T09:00:00+00:00",
      "updatedAt": "2026-06-25T09:00:00+00:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 12,
    "to": 1,
    "total": 1
  }
}
```

### 5.2 Crear transformador externo

```http
POST /api/v2/external-processors
Content-Type: application/json
```

**Body:**

```json
{
  "name": "Congelados Atlántico S.L.",
  "legalName": "Congelados Atlántico Sociedad Limitada",
  "vatNumber": "B12345678",
  "sanitaryRegistrationNumber": "12.34567/PO",
  "contactPerson": "María García",
  "phone": "+34 986 000 000",
  "emails": ["produccion@empresa.com"],
  "ccEmails": ["administracion@empresa.com"],
  "address": "Polígono Industrial, nave 4",
  "city": "Vigo",
  "postalCode": "36201",
  "province": "Pontevedra",
  "countryId": 1,
  "isActive": true,
  "notes": "Transformador externo principal."
}
```

| Campo | Tipo | Req. | Validación |
|-------|------|------|------------|
| `name` | string | ✅ | Máx. 255 caracteres. |
| `vatNumber` | string | ✅ | Máx. 32 caracteres. Único por tenant. |
| `legalName` | string/null | ❌ | Máx. 255 caracteres. |
| `sanitaryRegistrationNumber` | string/null | ❌ | Máx. 64 caracteres. |
| `contactPerson` | string/null | ❌ | Máx. 255 caracteres. |
| `phone` | string/null | ❌ | Máx. 50 caracteres. |
| `emails` | string[] | ❌ | Emails válidos y no duplicados. |
| `ccEmails` | string[] | ❌ | Emails válidos y no duplicados. |
| `address` | string/null | ❌ | Máx. 1000 caracteres. |
| `city` | string/null | ❌ | Máx. 255 caracteres. |
| `postalCode` | string/null | ❌ | Máx. 20 caracteres. |
| `province` | string/null | ❌ | Máx. 255 caracteres. |
| `countryId` | integer/null | ❌ | Debe existir en `countries`. |
| `isActive` | boolean | ❌ | Default backend: `true`. |
| `notes` | string/null | ❌ | Máx. 2000 caracteres. |

**Respuesta 201:**

```json
{
  "message": "Transformador externo creado correctamente.",
  "data": {
    "id": 1,
    "name": "Congelados Atlántico S.L.",
    "legalName": "Congelados Atlántico Sociedad Limitada",
    "vatNumber": "B12345678",
    "sanitaryRegistrationNumber": "12.34567/PO",
    "contactPerson": "María García",
    "phone": "+34 986 000 000",
    "emails": ["produccion@empresa.com"],
    "ccEmails": ["administracion@empresa.com"],
    "address": "Polígono Industrial, nave 4",
    "city": "Vigo",
    "postalCode": "36201",
    "province": "Pontevedra",
    "country": {
      "id": 1,
      "name": "España"
    },
    "isActive": true,
    "notes": "Transformador externo principal.",
    "createdAt": "2026-06-25T09:00:00+00:00",
    "updatedAt": "2026-06-25T09:00:00+00:00"
  }
}
```

### 5.3 Ver detalle

```http
GET /api/v2/external-processors/{externalProcessorId}
```

**Respuesta 200:**

```json
{
  "message": "Transformador externo obtenido correctamente.",
  "data": {
    "id": 1,
    "name": "Congelados Atlántico S.L.",
    "vatNumber": "B12345678",
    "isActive": true
  }
}
```

La respuesta contiene el mismo shape completo de `ExternalProcessor` que creación/listado.

### 5.4 Editar

```http
PUT /api/v2/external-processors/{externalProcessorId}
Content-Type: application/json
```

También se acepta `PATCH` por `apiResource`.

El backend usa validación `sometimes`: solo se actualizan los campos enviados. Para limpiar un campo opcional, enviar `null`. Para limpiar emails, enviar arrays vacíos.

**Ejemplo:**

```json
{
  "name": "Congelados Atlántico S.L.",
  "vatNumber": "B12345678",
  "notes": null,
  "emails": [],
  "ccEmails": [],
  "isActive": false
}
```

**Respuesta 200:**

```json
{
  "message": "Transformador externo actualizado correctamente.",
  "data": {
    "id": 1,
    "name": "Congelados Atlántico S.L.",
    "emails": [],
    "ccEmails": [],
    "isActive": false,
    "notes": null
  }
}
```

### 5.5 Eliminar

```http
DELETE /api/v2/external-processors/{externalProcessorId}
```

**Respuesta 200:**

```json
{
  "message": "Transformador externo eliminado correctamente."
}
```

> En la versión actual no hay relaciones con pedidos/palets/almacenes, por lo que el borrado físico está permitido. Cuando se vincule con operativa, el frontend deberá esperar restricciones nuevas.

### 5.6 Eliminar múltiples

```http
DELETE /api/v2/external-processors
Content-Type: application/json
```

**Body:**

```json
{
  "ids": [1, 2, 3]
}
```

**Respuesta 200:**

```json
{
  "message": "Transformadores externos eliminados correctamente."
}
```

### 5.7 Options para selects

```http
GET /api/v2/external-processors/options
```

Devuelve solo transformadores activos (`isActive = true`).

**Respuesta 200:**

```json
[
  {
    "id": 1,
    "name": "Congelados Atlántico S.L.",
    "vatNumber": "B12345678",
    "isActive": true
  }
]
```

---

## 6. Endpoints auxiliares

### Países

Para el selector de país:

```http
GET /api/v2/countries/options
```

Usar `countryId` en el formulario de transformador externo. Si el usuario no selecciona país, enviar `countryId: null` o no enviar el campo.

---

## 7. Diseño de interfaz recomendado

### 7.1 Ubicación

Añadir una entrada en navegación:

```text
Catálogos
  Transformadores externos
```

Etiqueta alternativa aceptable: `Maquiladores`. Si el ERP usa lenguaje más formal, preferir `Transformadores externos` en título y `Maquilador` como texto secundario o alias.

### 7.2 Pantalla de listado

Elementos recomendados:

- Título: `Transformadores externos`
- Botón principal: `Nuevo transformador`
- Buscador por nombre/razón social.
- Filtro por estado: `Todos`, `Activos`, `Inactivos`.
- Filtros secundarios: CIF/NIF/VAT, registro sanitario, país.
- Tabla paginada.

Columnas sugeridas:

| Columna | Campo | Notas |
|---------|-------|-------|
| Nombre | `name` | Principal. Mostrar `legalName` debajo si existe. |
| CIF/NIF/VAT | `vatNumber` | Siempre visible. |
| Registro sanitario | `sanitaryRegistrationNumber` | Mostrar `-` si no existe. |
| Contacto | `contactPerson` | Debajo puede ir `phone`. |
| Email | `emails[0]` | Mostrar primer email y contador si hay más. |
| Ubicación | `city`, `province`, `country.name` | Compacto. |
| Estado | `isActive` | Badge Activo/Inactivo. |
| Acciones | — | Ver/editar/eliminar. |

### 7.3 Estados vacíos

Sin resultados por primera vez:

```text
No hay transformadores externos todavía.
```

Con filtros aplicados:

```text
No se encontraron transformadores con los filtros aplicados.
```

### 7.4 Acciones por fila

- Ver detalle.
- Editar.
- Activar/desactivar desde edición o acción rápida.
- Eliminar con confirmación.

Para eliminación múltiple:

- Permitir selección por checkbox.
- Mostrar acción `Eliminar seleccionados` solo a roles con permiso de borrado.
- Confirmar antes de llamar al endpoint.

---

## 8. Formulario de creación/edición

### Organización recomendada

Usar modal amplio, drawer o página dedicada según patrón existente. Para no hacer la pantalla pesada, agrupar en secciones.

#### Datos de empresa

| Campo UI | Campo API | Tipo control |
|----------|-----------|--------------|
| Nombre | `name` | Input requerido. |
| Razón social | `legalName` | Input opcional. |
| CIF/NIF/VAT | `vatNumber` | Input requerido. |
| Registro sanitario | `sanitaryRegistrationNumber` | Input opcional. |
| Activo | `isActive` | Switch/toggle. |

#### Contacto

| Campo UI | Campo API | Tipo control |
|----------|-----------|--------------|
| Persona de contacto | `contactPerson` | Input. |
| Teléfono | `phone` | Input. |
| Emails | `emails` | Control de lista/chips de email. |
| Emails en copia | `ccEmails` | Control de lista/chips de email. |

#### Dirección

| Campo UI | Campo API | Tipo control |
|----------|-----------|--------------|
| Dirección | `address` | Textarea corto. |
| Ciudad | `city` | Input. |
| Código postal | `postalCode` | Input. |
| Provincia | `province` | Input. |
| País | `countryId` | Select con `/countries/options`. |

#### Notas internas

| Campo UI | Campo API | Tipo control |
|----------|-----------|--------------|
| Notas | `notes` | Textarea. Máx. 2000. |

### Payload mínimo para crear

```json
{
  "name": "Maquilador Norte S.L.",
  "vatNumber": "B12345678"
}
```

### Payload recomendado para editar

Enviar solo campos modificados si el cliente HTTP lo permite con claridad. Si se envía el formulario completo, asegurarse de que los opcionales vacíos van como `null` o arrays vacíos, no como `undefined` dentro de JSON.

```ts
const payload = {
  name: form.name,
  legalName: form.legalName || null,
  vatNumber: form.vatNumber,
  sanitaryRegistrationNumber: form.sanitaryRegistrationNumber || null,
  contactPerson: form.contactPerson || null,
  phone: form.phone || null,
  emails: form.emails,
  ccEmails: form.ccEmails,
  address: form.address || null,
  city: form.city || null,
  postalCode: form.postalCode || null,
  province: form.province || null,
  countryId: form.countryId || null,
  isActive: form.isActive,
  notes: form.notes || null,
};
```

---

## 9. Validación frontend recomendada

Replicar validaciones básicas para UX, sin sustituir al backend.

| Campo | Validación frontend |
|-------|---------------------|
| `name` | Requerido, trim no vacío, máx. 255. |
| `vatNumber` | Requerido, trim no vacío, máx. 32. |
| `legalName` | Máx. 255. |
| `sanitaryRegistrationNumber` | Máx. 64. |
| `contactPerson` | Máx. 255. |
| `phone` | Máx. 50. |
| `emails` | Formato email, sin duplicados dentro de la lista. |
| `ccEmails` | Formato email, sin duplicados dentro de la lista. |
| `address` | Máx. 1000. |
| `postalCode` | Máx. 20. |
| `notes` | Máx. 2000. |

### Importante sobre email

El backend valida con `email:rfc,dns`, por lo que algunos dominios ficticios pueden fallar aunque tengan forma de email. En entorno real no debería ser un problema; en mocks/tests frontend usar dominios reales o desactivar validaciones artificiales que contradigan al backend.

---

## 10. Gestión de errores

| Código | Cuándo ocurre | Qué mostrar |
|--------|---------------|-------------|
| `401` | Token inválido/expirado | Redirigir a login. |
| `403` | Rol sin permiso | "No tienes permiso para realizar esta acción." |
| `404` | ID inexistente | "El transformador externo no existe o ha sido eliminado." |
| `422` | Validación fallida | Mostrar errores por campo. |
| `500` | Error inesperado | Mensaje genérico y log/telemetría si existe. |

### Error 422 típico

```json
{
  "message": "Error de validación.",
  "userMessage": "The vatNumber field has already been taken.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "vatNumber": [
      "The vatNumber field has already been taken."
    ]
  }
}
```

### Mapeo de errores por campo

El frontend debe mapear directamente:

| Campo API | Campo formulario |
|-----------|------------------|
| `name` | Nombre |
| `vatNumber` | CIF/NIF/VAT |
| `legalName` | Razón social |
| `sanitaryRegistrationNumber` | Registro sanitario |
| `contactPerson` | Persona de contacto |
| `phone` | Teléfono |
| `emails.0`, `emails.1`, ... | Emails |
| `ccEmails.0`, `ccEmails.1`, ... | Emails en copia |
| `countryId` | País |
| `notes` | Notas |

---

## 11. Flujos de usuario

### Crear transformador

```text
1. Usuario abre Catálogos > Transformadores externos.
2. Pulsa Nuevo transformador.
3. Frontend carga países si el select no está cacheado.
4. Usuario rellena nombre y CIF/NIF/VAT como mínimo.
5. Frontend valida campos básicos.
6. POST /api/v2/external-processors.
7. Si 201, cerrar formulario y refrescar listado o insertar `data` en estado local.
```

### Editar transformador

```text
1. Usuario abre acción Editar.
2. Si la fila ya tiene todos los datos, puede precargar el formulario.
3. Opcionalmente hacer GET detalle para asegurar datos frescos.
4. PUT/PATCH /api/v2/external-processors/{id}.
5. Si 200, actualizar fila con `data`.
```

### Desactivar transformador

No existe endpoint separado de desactivación. Usar edición:

```http
PATCH /api/v2/external-processors/{id}
```

```json
{
  "isActive": false
}
```

### Reactivar transformador

```json
{
  "isActive": true
}
```

### Eliminar transformador

```text
1. Usuario pulsa eliminar.
2. Mostrar confirmación con nombre del transformador.
3. DELETE /api/v2/external-processors/{id}.
4. Si 200, quitar de la lista local.
```

Confirmación sugerida:

```text
¿Eliminar "Congelados Atlántico S.L."?
```

---

## 12. Cliente API sugerido

```ts
const base = '/api/v2/external-processors';

export async function listExternalProcessors(params) {
  return api.get(base, { params });
}

export async function getExternalProcessor(id: number) {
  return api.get(`${base}/${id}`);
}

export async function createExternalProcessor(payload) {
  return api.post(base, payload);
}

export async function updateExternalProcessor(id: number, payload) {
  return api.patch(`${base}/${id}`, payload);
}

export async function deleteExternalProcessor(id: number) {
  return api.delete(`${base}/${id}`);
}

export async function deleteExternalProcessors(ids: number[]) {
  return api.delete(base, { data: { ids } });
}

export async function getExternalProcessorOptions() {
  return api.get(`${base}/options`);
}
```

### Serialización de arrays en query

Para `ids[]`, usar la convención que ya use el cliente HTTP del frontend con otros endpoints. El backend espera array:

```http
GET /api/v2/external-processors?ids[]=1&ids[]=2
```

---

## 13. Checklist de implementación frontend

- Añadir ruta/pantalla en catálogo.
- Añadir permisos de navegación por rol.
- Crear servicio API para `external-processors`.
- Implementar tabla paginada con filtros.
- Implementar formulario de creación/edición.
- Integrar selector de país con `/countries/options`.
- Soportar `emails` y `ccEmails` como arrays.
- Permitir limpiar campos opcionales enviando `null` o arrays vacíos.
- Añadir toggle de activo/inactivo.
- Añadir confirmación de eliminación individual.
- Añadir eliminación múltiple si la tabla ya soporta selección.
- Manejar `401`, `403`, `404`, `422`.
- Refrescar o actualizar estado local tras crear/editar/eliminar.

---

## 14. Fuera de alcance por ahora

No construir todavía UI para:

- asociar maquilador a usuarios externos;
- asociar maquilador a almacenes;
- vincular con pedidos;
- vincular con palets;
- gestionar tarifas/costes;
- gestionar contratos o certificaciones;
- adjuntar documentos.

Esos flujos dependerán de futuras relaciones backend. La pantalla actual debe ser un maestro de catálogo limpio y preparado, nada más.
