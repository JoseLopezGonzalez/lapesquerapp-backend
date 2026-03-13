---
title: External Users Frontend Guide
description: Guía funcional y técnica para implementar en frontend el acceso de usuarios externos (maquilador) usando el backend actual.
updated: 2026-03-13
audience: Frontend Engineers
---

# External Users Frontend Guide

## Resumen

Esta guía describe cómo debe integrarse el frontend con la funcionalidad de `ExternalUser` ya implementada en backend.

El objetivo de negocio es permitir que un proveedor externo tipo `maquilador` entre en PesquerApp con el mismo login que un usuario interno, pero vea únicamente una experiencia limitada al StoresManager y solo sobre los almacenes que el tenant le haya vinculado.

La seguridad no depende del frontend. El backend ya filtra rutas, almacenes y palets. El frontend debe:

- detectar el tipo de actor autenticado
- renderizar la navegación adecuada
- usar solo los endpoints permitidos
- ocultar acciones que backend no permite
- manejar correctamente respuestas `403` y estados de usuario desactivado

---

# 1. Contexto funcional

## Qué problema resuelve

Hay operativas que ocurren fuera de las instalaciones del tenant, pero que siguen formando parte del inventario del tenant. El caso inmediato es el maquilador:

1. el tenant envía materia prima
2. el maquilador procesa producto en su planta
3. el maquilador registra directamente en PesquerApp los palets generados

Esto evita trabajo manual posterior y mantiene la trazabilidad del inventario dentro del tenant.

## Qué actor existe en v1

En esta primera entrega solo existe:

- `externalUserType = maquilador`

El diseño es extensible, pero frontend no debe asumir más tipos por ahora.

## Qué experiencia debe tener el usuario externo

El usuario externo:

- entra desde el mismo login que los usuarios internos
- no tiene portal separado
- no ve panel admin
- no ve pedidos
- no ve clientes, proveedores ni otros módulos
- solo usa la experiencia de almacenes y palets

En términos de producto, el frontend debe comportarse como una “app reducida” dentro de la misma aplicación.

---

# 2. Principios de implementación frontend

## Principio 1: un solo login

El login inicial no pregunta si el usuario es interno o externo. El usuario introduce su email y el tenant viene determinado por el subdominio o por la cabecera `X-Tenant`.

El backend decide si ese email pertenece a:

- un `User` interno
- un `ExternalUser`

El frontend no decide el tipo de usuario antes de autenticar.

## Principio 2: el backend manda

El frontend no debe intentar “proteger” datos mediante filtros locales como mecanismo principal.

Debe asumir que:

- la lista de stores ya viene filtrada
- la lista de pallets ya viene filtrada
- los destinos válidos de movimiento ya vienen filtrados
- una acción puede devolver `403` aunque la UI la haya ocultado

La UI debe reflejar el scope que el backend ya impone.

## Principio 3: misma base técnica, distinta experiencia

No hay que construir un segundo frontend ni un segundo árbol de rutas completo.

Lo correcto es:

- mantener el mismo sistema de auth
- mantener el mismo shell técnico de la SPA
- renderizar un layout y navegación reducidos cuando `actorType = external_user`

---

# 3. Modelo mental para frontend

## Tipos de actor que puede recibir el frontend

El frontend debe asumir dos tipos:

- `internal_user`
- `external_user`

La distinción llega desde backend.

## Campos clave del contrato de sesión

En `POST /api/v2/auth/otp/verify`, `POST /api/v2/auth/magic-link/verify` y `GET /api/v2/me`, el backend devuelve estos campos clave:

```json
{
  "actorType": "internal_user | external_user",
  "role": "administrador | tecnico | direccion | administracion | comercial | operario | null",
  "externalUserType": "maquilador | null",
  "allowedStoreIds": [1, 2, 3]
}
```

## Regla de decisión principal

La lógica principal del frontend debe ser:

```ts
if (session.actorType === 'external_user') {
  renderExternalStoresExperience()
} else {
  renderInternalApp()
}
```

No hay que inferir un usuario externo por `role = null` sin mirar `actorType`.

---

# 4. Flujo de autenticación

## 4.1 Request access

Endpoint:

```http
POST /api/v2/auth/request-access
```

Payload:

```json
{
  "email": "usuario@proveedor.com"
}
```

Comportamiento:

- mismo formulario para internos y externos
- respuesta genérica para no revelar si el email existe
- si el actor existe y está activo, backend envía email con magic link + OTP

Respuesta esperada:

```json
{
  "message": "Si el correo está registrado y activo, recibirás un correo con un enlace y un código para acceder."
}
```

## 4.2 Verificación por OTP

Endpoint:

```http
POST /api/v2/auth/otp/verify
```

Payload:

```json
{
  "email": "usuario@proveedor.com",
  "code": "123456"
}
```

Respuesta:

```json
{
  "access_token": "token",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Maquilador Test",
    "email": "usuario@proveedor.com",
    "assigned_store_id": null,
    "assignedStoreId": null,
    "company_name": "Proveedor Test",
    "companyName": "Proveedor Test",
    "company_logo_url": null,
    "companyLogoUrl": null,
    "active": true,
    "role": null,
    "actorType": "external_user",
    "externalUserType": "maquilador",
    "allowedStoreIds": [10, 11]
  }
}
```

## 4.3 Verificación por magic link

Endpoint:

```http
POST /api/v2/auth/magic-link/verify
```

Payload:

```json
{
  "token": "plain-token-from-email"
}
```

La respuesta tiene el mismo shape que la verificación OTP.

## 4.4 Recuperar sesión actual

Endpoint:

```http
GET /api/v2/me
```

Este endpoint es la fuente de verdad para hidratar la sesión al recargar la SPA.

Para `external_user` la respuesta incluye:

```json
{
  "id": 1,
  "name": "Maquilador Test",
  "email": "usuario@proveedor.com",
  "assigned_store_id": null,
  "assignedStoreId": null,
  "company_name": "Proveedor Test",
  "companyName": "Proveedor Test",
  "company_logo_url": null,
  "companyLogoUrl": null,
  "active": true,
  "role": null,
  "actorType": "external_user",
  "externalUserType": "maquilador",
  "allowedStoreIds": [10, 11],
  "created_at": "2026-03-13T12:00:00Z",
  "updated_at": "2026-03-13T12:00:00Z",
  "features": []
}
```

## 4.5 Logout

Endpoint:

```http
POST /api/v2/logout
```

Comportamiento:

- elimina solo el token actual
- el frontend debe borrar su sesión local y volver al login

---

# 5. Estados de sesión que debe manejar el frontend

## Estado: no autenticado

Mostrar el login normal.

## Estado: autenticado como `internal_user`

Comportamiento habitual actual.

## Estado: autenticado como `external_user`

Renderizar modo externo:

- layout reducido
- sin navegación administrativa
- entrypoint principal: almacenes

## Estado: usuario externo desactivado

Si backend responde `403` con mensaje de desactivación, el frontend debe:

1. invalidar la sesión local
2. redirigir al login
3. mostrar mensaje informativo

Ejemplo de payload de error:

```json
{
  "message": "Acción no autorizada.",
  "userMessage": "Tu acceso externo está desactivado. Contacta con el tenant."
}
```

## Estado: token inválido o expirado

Si cualquier request devuelve `401`:

1. limpiar token local
2. redirigir al login
3. mostrar mensaje estándar de sesión expirada

---

# 6. Navegación y layout

## 6.1 Navegación para usuario interno

Se mantiene la navegación actual.

## 6.2 Navegación para usuario externo

La navegación debe limitarse a:

- pantalla de almacenes
- detalle de almacén
- detalle/edición de palet
- logout

## 6.3 Qué no debe aparecer en UI externa

No mostrar:

- usuarios
- usuarios externos
- sesiones
- activity logs
- pedidos
- clientes
- proveedores
- productos fuera del contexto de cajas/palets
- estadísticas
- configuración del tenant
- acciones de borrar palet
- acciones de vincular/desvincular pedido

## 6.4 Recomendación de layout

Para `external_user`, el shell debería incluir:

- cabecera simple con nombre del actor y del tenant
- una única entrada de navegación: `Almacenes`
- acceso a logout

Evitar placeholders de menú vacíos o entradas deshabilitadas visibles.

---

# 7. Módulo admin que también debe implementar frontend

Además del modo de acceso externo, el frontend interno debe añadir la gestión administrativa de usuarios externos.

## 7.1 Nueva entidad administrativa

Recurso:

- `external-users`

Debe integrarse igual que otras entidades simples del panel.

## 7.2 Endpoints admin disponibles

Solo para usuarios internos con rol permitido:

- `GET /api/v2/external-users`
- `POST /api/v2/external-users`
- `GET /api/v2/external-users/{id}`
- `PUT /api/v2/external-users/{id}`
- `DELETE /api/v2/external-users/{id}`
- `POST /api/v2/external-users/{id}/resend-access`
- `POST /api/v2/external-users/{id}/activate`
- `POST /api/v2/external-users/{id}/deactivate`

## 7.3 Shape del recurso

Respuesta de recurso:

```json
{
  "id": 1,
  "name": "Maquila Frío Sur",
  "companyName": "Frío Sur S.L.",
  "email": "maquila1@pesquerapp.test",
  "type": "maquilador",
  "isActive": true,
  "notes": "Proveedor externo de maquila principal.",
  "storesCount": 2,
  "created_at": "2026-03-13T12:00:00Z",
  "updated_at": "2026-03-13T12:00:00Z"
}
```

## 7.4 Tabla admin recomendada

Columnas:

- nombre
- empresa
- email
- tipo
- activo/inactivo
- número de almacenes
- fecha de creación

Acciones por fila:

- ver/editar
- reenviar acceso
- activar/desactivar
- eliminar

## 7.5 Formulario admin recomendado

Campos:

- `name`
- `company_name`
- `email`
- `type`
- `is_active`
- `notes`

Reglas de UX:

- `type` puede venir preseleccionado y bloqueado en `maquilador` en v1
- si `is_active = true` al crear, avisar de que se enviará acceso automáticamente
- si backend devuelve validación por email duplicado con usuarios internos, mostrar el mensaje de error tal cual

---

# 8. Cambios frontend en Stores admin

El frontend interno también debe adaptar la entidad `Store`.

## 8.1 Nuevos campos

- `storeType`
- `externalUser`

## 8.2 Listado de stores

En el listado interno, mostrar:

- columna `Tipo`
- columna `Usuario externo`

## 8.3 Filtros de stores

El backend acepta:

- `store_type`
- `external_user_id`

Se recomienda:

- filtro `Todos / Interno / Externo`
- filtro por usuario externo visible cuando tenga sentido

## 8.4 Formulario store

Campos nuevos:

- selector `Tipo de almacén`
- selector `Usuario externo`

Comportamiento:

- si `storeType = interno`, limpiar `external_user_id`
- si `storeType = externo`, exigir `external_user_id`

---

# 9. Módulo StoresManager para usuario externo

## 9.1 Pantalla inicial

La home externa debe ser el listado de almacenes filtrado.

Endpoint:

```http
GET /api/v2/stores
```

Respuesta paginada con cada store incluyendo:

```json
{
  "id": 10,
  "name": "Maquila Frío Sur - Cámara 1",
  "temperature": -18,
  "capacity": 18000,
  "storeType": "externo",
  "externalUser": {
    "id": 1,
    "name": "Maquila Frío Sur",
    "email": "maquila1@pesquerapp.test",
    "type": "maquilador"
  },
  "netWeightPallets": 0,
  "totalNetWeight": 0,
  "content": {
    "pallets": [],
    "boxes": [],
    "bigBoxes": []
  },
  "map": { "...": "..." }
}
```

## 9.2 Selector de stores

Endpoint:

```http
GET /api/v2/stores/options
```

Respuesta:

```json
[
  { "id": 10, "name": "Maquila Frío Sur - Cámara 1" },
  { "id": 11, "name": "Maquila Frío Sur - Expediciones" }
]
```

En usuario externo este selector ya viene filtrado. Debe reutilizarse en:

- selector de destino de movimiento
- cualquier picker de store dentro del StoresManager

## 9.3 Detalle de almacén

Endpoint:

```http
GET /api/v2/stores/{id}
```

Respuesta:

```json
{
  "data": {
    "id": 10,
    "name": "Maquila Frío Sur - Cámara 1",
    "temperature": -18,
    "capacity": 18000,
    "storeType": "externo",
    "externalUser": {
      "id": 1,
      "name": "Maquila Frío Sur",
      "email": "maquila1@pesquerapp.test",
      "type": "maquilador"
    },
    "netWeightPallets": 100,
    "totalNetWeight": 100,
    "content": {
      "pallets": [/* pallets completos */],
      "boxes": [],
      "bigBoxes": []
    },
    "map": { "...": "..." }
  }
}
```

Si intenta abrir un store fuera de scope, backend devolverá `403`.

## 9.4 Listado de palets

Endpoint:

```http
GET /api/v2/pallets
```

Para usuario externo, backend solo devuelve palets de sus stores.

No hace falta aplicar filtros extra de seguridad en frontend.

## 9.5 Detalle de palet

Endpoint:

```http
GET /api/v2/pallets/{id}
```

Shape relevante:

```json
{
  "data": {
    "id": 100,
    "observations": "Texto",
    "state": {
      "id": 2,
      "name": "stored"
    },
    "productsNames": ["Producto A"],
    "boxes": [/* cajas */],
    "lots": ["LOT-123"],
    "netWeight": 120.5,
    "position": 1,
    "store": {
      "id": 10,
      "name": "Maquila Frío Sur - Cámara 1"
    },
    "orderId": null,
    "numberOfBoxes": 24,
    "availableBoxesCount": 24,
    "usedBoxesCount": 0,
    "totalAvailableWeight": 120.5,
    "totalUsedWeight": 0,
    "receptionId": null,
    "costPerKg": null,
    "totalCost": null
  }
}
```

## 9.6 Crear palet

Endpoint:

```http
POST /api/v2/pallets
```

Payload mínimo recomendado para externo:

```json
{
  "observations": "Producción turno mañana",
  "store": { "id": 10 },
  "boxes": [
    {
      "product": { "id": 1 },
      "lot": "LOT-001",
      "gs1128": "GS1-001",
      "grossWeight": 10.5,
      "netWeight": 9.8
    }
  ]
}
```

Reglas importantes:

- para externo, `store.id` debe pertenecer a `allowedStoreIds`
- no enviar `orderId`
- no exponer en UI campos de pedido

## 9.7 Editar palet

Endpoint:

```http
PUT /api/v2/pallets/{id}
```

Payload típico:

```json
{
  "id": 100,
  "observations": "Texto actualizado",
  "boxes": [
    {
      "id": 200,
      "product": { "id": 1 },
      "lot": "LOT-001",
      "gs1128": "GS1-001",
      "grossWeight": 10.5,
      "netWeight": 9.8
    }
  ]
}
```

No mostrar en UI:

- vincular pedido
- desvincular pedido
- cambio de orderId

## 9.8 Mover palet entre stores

Endpoint:

```http
POST /api/v2/pallets/move-to-store
```

Payload:

```json
{
  "pallet_id": 100,
  "store_id": 11
}
```

Backend validará:

- que el palet pertenece a un store del externo
- que el store destino también pertenece al externo

El frontend debe usar solo stores de `stores/options` para construir el selector.

## 9.9 Mover varios palets

Endpoint:

```http
POST /api/v2/pallets/move-multiple-to-store
```

Payload:

```json
{
  "pallet_ids": [100, 101, 102],
  "store_id": 11
}
```

## 9.10 Asignar y quitar posición

Endpoints:

```http
POST /api/v2/pallets/assign-to-position
POST /api/v2/pallets/{id}/unassign-position
```

Payload para asignación:

```json
{
  "position_id": 1,
  "pallet_ids": [100]
}
```

## 9.11 Timeline

Endpoint:

```http
GET /api/v2/pallets/{id}/timeline
```

Debe mostrarse porque forma parte de la trazabilidad operativa.

## 9.12 Acción prohibida: borrar palet

No renderizar botones de:

- borrar palet
- borrar múltiples palets
- limpiar timeline

El backend devolverá `403` si se intenta.

---

# 10. Rutas permitidas y bloqueadas

## Permitidas para `external_user`

- `GET /api/v2/me`
- `POST /api/v2/logout`
- `GET /api/v2/stores`
- `GET /api/v2/stores/{id}`
- `GET /api/v2/stores/options`
- `GET /api/v2/pallets`
- `GET /api/v2/pallets/{id}`
- `POST /api/v2/pallets`
- `PUT /api/v2/pallets/{id}`
- `POST /api/v2/pallets/move-to-store`
- `POST /api/v2/pallets/move-multiple-to-store`
- `POST /api/v2/pallets/assign-to-position`
- `POST /api/v2/pallets/{id}/unassign-position`
- `GET /api/v2/pallets/{id}/timeline`

## Bloqueadas para `external_user`

- `/api/v2/users*`
- `/api/v2/external-users*`
- `/api/v2/sessions*`
- `/api/v2/activity-logs*`
- `/api/v2/orders*`
- `/api/v2/statistics*`
- `/api/v2/settings*`
- endpoints de link/unlink de pedidos con palets
- borrado de palets

## Regla UX

Si la UI externa nunca navega a esas rutas bloqueadas, mejor.

Si por bug o navegación manual se llega a ellas y backend devuelve `403`, mostrar pantalla o toast de acceso no permitido y redirigir a la home externa.

---

# 11. Estrategia de estado en frontend

## Store de sesión recomendado

Persistir, como mínimo:

- `accessToken`
- `actorType`
- `role`
- `externalUserType`
- `allowedStoreIds`
- `userName`
- `email`
- `companyName`

## Selectores recomendados

- `isExternalActor`
- `isInternalActor`
- `canAccessAdmin`
- `canAccessOrders`
- `canDeletePallet`

Ejemplo:

```ts
const isExternalActor = session?.actorType === 'external_user'
const canAccessAdmin = session?.actorType === 'internal_user'
const canDeletePallet = session?.actorType === 'internal_user'
```

No condicionar permisos complejos de negocio a `role` cuando el actor es externo.

---

# 12. Errores y mensajes que debe soportar la UI

## 400 en verificación de OTP o magic link

Mensaje típico:

```json
{
  "message": "El código no es válido o ha expirado. Solicita uno nuevo."
}
```

Comportamiento:

- mostrar mensaje inline o toast
- permitir reintentar
- permitir volver a solicitar acceso

## 403 por usuario desactivado

Mensaje típico:

```json
{
  "message": "Acción no autorizada.",
  "userMessage": "Tu acceso externo está desactivado. Contacta con el tenant."
}
```

Comportamiento:

- cerrar sesión local
- redirigir a login
- mostrar mensaje persistente o toast

## 403 por acceso fuera de scope

Ejemplos:

- abrir store ajeno
- mover a store no permitido
- usar ruta admin

Comportamiento:

- mostrar mensaje de acción no autorizada
- opcionalmente redirigir a `/stores`

## 422 de validación

Casos frecuentes:

- email duplicado entre `users` y `external_users`
- crear store externo sin `external_user_id`
- crear/editar palet con store fuera del scope externo

Comportamiento:

- mapear errores al formulario correspondiente

---

# 13. Decisiones UX que ya están cerradas

Estas decisiones ya no deberían reabrirse al implementar frontend:

- no hay portal separado para externos
- no hay segundo login
- la detección del tipo de actor ocurre después de autenticar
- el usuario externo entra en una app reducida
- el usuario externo no gestiona pedidos
- el usuario externo no puede borrar palets
- el selector de stores para movimiento usa únicamente `stores/options`
- el backend sigue siendo la fuente de verdad del scope

---

# 14. Lo que frontend no debe asumir

No asumir:

- que `role = null` implica error
- que `features` siempre tiene contenido
- que `assignedStoreId` existe para externo
- que todos los stores del tenant son visibles
- que un `storeType = externo` siempre tendrá el mismo usuario en memoria si la lista no se refresca
- que un usuario externo seguirá activo toda la sesión

Puede ser desactivado por el tenant en cualquier momento.

---

# 15. Plan de implementación frontend recomendado

## Fase 1: soporte de sesión

- extender el store de auth
- persistir `actorType`, `externalUserType`, `allowedStoreIds`
- adaptar bootstrap de `GET /me`

## Fase 2: shell externo

- layout reducido
- navegación limitada
- redirección inicial a almacenes para actor externo

## Fase 3: StoresManager

- reutilizar páginas actuales de stores y pallets
- ocultar acciones no permitidas
- consumir stores/pallets ya filtrados
- limitar selector destino a `stores/options`

## Fase 4: admin interno

- añadir entidad `External Users`
- adaptar formulario/listado de `Store`

## Fase 5: QA funcional

- login interno sigue funcionando
- login externo muestra solo experiencia reducida
- usuario externo no ve ni puede abrir rutas admin
- usuario externo no puede borrar ni vincular pedidos
- al desactivar el usuario, la sesión cae correctamente

---

# 16. Checklist de aceptación frontend

## Auth

- el login único funciona para internos y externos
- `GET /me` hidrata correctamente un `external_user`
- un usuario externo desactivado es expulsado correctamente

## UI externa

- no aparece menú admin
- no aparecen pedidos
- no aparecen clientes/proveedores
- no aparece borrado de palets

## Stores y pallets

- el listado de stores muestra solo los vinculados
- el detalle de store funciona
- el detalle de palet funciona
- crear palet funciona
- editar palet funciona
- mover palet entre stores propios funciona
- el selector destino nunca ofrece stores ajenos

## Admin interno

- existe listado de `external_users`
- se puede crear uno nuevo
- se puede activar/desactivar
- se puede reenviar acceso
- stores internos pueden marcarse como externos y vincularse a un `external_user`

---

# 17. Referencias backend

Documentos y archivos útiles para frontend:

- [100. External Users.md](/home/jose/lapesquerapp-backend/docs/por-hacer/100.%20External%20Users.md)
- [external-users-deploy.md](/home/jose/lapesquerapp-backend/docs/instrucciones/external-users-deploy.md)
- [AuthController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/AuthController.php)
- [routes/api.php](/home/jose/lapesquerapp-backend/routes/api.php)
- [ExternalUserController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/ExternalUserController.php)

---

# 18. Estado actual

Backend:

- implementado
- migrado y sembrado en desarrollo con Sail

Frontend:

- pendiente de integración completa
- este documento debe considerarse la especificación de implementación frontend para v1
