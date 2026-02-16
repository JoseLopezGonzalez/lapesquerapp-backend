# Roles y permisos — Pasos 2 y 3 pendientes

Documento para retomar en otra sesión. El **Paso 1** (backend + documentación) está completado.

**Contexto:** Estrategia de Roles y Permisos en PesquerApp (roles fijos en enum, un rol por usuario). Backend migrado; documentación actualizada.

---

## Estado actual (hecho)

- **Backend:** Enum `App\Enums\Role`, columna `users.role`, migraciones ejecutadas en todos los tenants, CRUD de roles eliminado, solo `GET /v2/roles/options`, API de usuarios y auth con `role` (string).
- **Documentación:** 81-Roles, 80-Usuarios, 02-Autenticacion-Autorizacion, API-references (sistema y autenticación), referencia (95, 96, 97) actualizados.

---

## Paso 2: Frontend

Objetivo: adaptar el frontend al nuevo contrato de la API (un rol por usuario, sin CRUD de roles).

### 2.1 Contrato de API (recordatorio)

| Dónde | Antes | Ahora |
|-------|--------|--------|
| Login `user` | `roles: string[]` | `role: string` |
| Me `user` | `roles: string[]` | `role: string` |
| User (listado/show) | `roles: string[]` | `role: string` |
| Crear usuario body | `role_ids: number[]` | `role: string` |
| Actualizar usuario body | `role_ids?: number[]` | `role?: string` |
| Filtro listado usuarios | `roles` (array) | `role` (string, un valor) |
| Roles API | CRUD + options | Solo `GET /v2/roles/options` |

Valores de `role`: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`.

Respuesta de `GET /v2/roles/options`: `[{ "id": "tecnico", "name": "Técnico" }, ...]`. El `id` es string (valor del enum); usarlo como valor del select al enviar `role` en crear/editar usuario.

### 2.2 Checklist Frontend

- [ ] **Tipos/ interfaces**
  - [ ] Usuario: `role: string` (opcionalmente tipo literal con los 6 valores).
  - [ ] Eliminar `roles: string[]` y `role_ids` de tipos de usuario/request.

- [ ] **Login y sesión**
  - [ ] Tras login, guardar `user.role` (no `user.roles`).
  - [ ] Donde se use “rol del usuario” (menú, permisos UI), leer `user.role`.

- [ ] **Endpoint “me”**
  - [ ] Consumir `role` (string) en lugar de `roles` (array).

- [ ] **Pantalla listado de usuarios**
  - [ ] Mostrar columna/valor “Rol” desde `role` (string).
  - [ ] Si hay filtro por rol: enviar `role` (query param string), no `roles` (array).
  - [ ] Respuesta: cada item tiene `role`, no `roles`.

- [ ] **Pantalla crear usuario**
  - [ ] Select de rol: datos desde `GET /v2/roles/options`; valor del option = `id` (string).
  - [ ] Body del POST: `role` (string), no `role_ids` (array).

- [ ] **Pantalla editar usuario**
  - [ ] Select de rol: mismo origen (roles/options); valor inicial = `user.role`.
  - [ ] Body del PUT: `role` (string) si se cambia, no `role_ids`.

- [ ] **Pantallas de “Roles” (CRUD)**
  - [ ] Eliminar: listado de roles, crear rol, editar rol, eliminar rol.
  - [ ] Mantener solo el uso de `GET /v2/roles/options` para rellenar el select en usuarios.

- [ ] **Comprobaciones por rol en la UI**
  - [ ] Sustituir comprobaciones sobre `user.roles` (array) por `user.role` (string).
  - [ ] Ej.: “si puede gestionar usuarios” → `user.role === 'tecnico'` (o el rol que corresponda).

- [ ] **Tests E2E / integración**
  - [ ] Actualizar fixtures y aserciones que usen `roles` o `role_ids`.
  - [ ] Comprobar login, me, listado usuarios, crear/editar usuario con `role`.

### 2.3 Notas

- Si el frontend usa un store de usuario (Zustand, Redux, etc.), actualizar el modelo de “usuario logueado” a `role: string`.
- Si hay constantes o mapeos de “rol → etiqueta”, alinear con los 6 valores y etiquetas del backend (Técnico, Administrador, Dirección, Administración, Comercial, Operario).

---

## Paso 3: Siguiente fase de la estrategia (permisos y políticas)

Objetivo: avanzar según el documento de estrategia: permisos macro, Policies en backend, y adaptar frontend por rol donde aplique.

### 3.1 Permisos macro (backend)

- [ ] **Definir permisos por módulo**  
  Ejemplos del documento: `pedido.ver`, `pedido.crear`, `pedido.descargar_documentos`, `pedido.registrar_produccion`, `pedido.registrar_incidencia`, `pedido.cerrar`, etc.
- [ ] **Decidir dónde viven**  
  Opciones: enum/constantes (ej. `App\Enums\Permission`) o config; sin tabla si se sigue “todo en código”.
- [ ] **Mapear rol → permisos**  
  Por cada rol (tecnico, administrador, direccion, etc.), lista de permisos que tiene.
- [ ] **Comprobar permisos en API**  
  Middleware o helper que compruebe “usuario tiene permiso X” (derivado de su rol). Usar en rutas además de o en lugar de solo `role:tecnico`, etc., donde interese granularidad por permiso.

### 3.2 Policies (backend)

- [ ] **Identificar módulos con reglas finas**  
  Pedidos, Producción, Calidad, etc., donde la autorización dependa de: rol, propiedad del recurso, estado del flujo, tipo de documento.
- [ ] **Crear Policies (Laravel)**  
  Una Policy por modelo/módulo relevante; métodos como `view`, `create`, `update`, `delete`, y acciones específicas (ej. `downloadDocument`, `registerProduction`).
- [ ] **Reglas dentro de la Policy**  
  Ejemplos del documento:
  - Comercial: solo sus pedidos.
  - Comercial: solo ciertos tipos de documentos.
  - Operario: ver pedidos sin precios ni clientes.
  - Ciertas acciones solo en determinados estados del pedido.
- [ ] **Usar en controladores**  
  `$this->authorize('accion', $recurso)` (o equivalente) en los endpoints que lo requieran; el middleware de permiso puede seguir siendo la “puerta gruesa” y la Policy la decisión final.

### 3.3 Frontend por rol/permiso

- [ ] **Ocultar/mostrar según rol**  
  Botones, enlaces y vistas según `user.role` (y más adelante permisos si se exponen al frontend).
- [ ] **Vistas simplificadas**  
  Ej.: operario sin datos sensibles; comercial solo “sus” pedidos (el filtrado real sigue en backend).
- [ ] **No confiar en UI para seguridad**  
  La API es la fuente de verdad; ocultar en frontend es solo UX.

### 3.4 Orden sugerido para Paso 3

1. Listar módulos y acciones que requieren control fino.
2. Definir permisos macro (nombres y asignación por rol).
3. Implementar comprobación de permisos en rutas/controladores (middleware o `can`).
4. Añadir Policies donde la regla dependa de recurso/estado/contexto.
5. Ajustar frontend (menús, botones, vistas) según rol (y permisos si se usan en cliente).

---

## Referencias

- **Estrategia completa:** documento interno “Estrategia de Roles y Permisos en PesquerApp”.
- **Backend roles:** `docs/28-sistema/81-Roles.md`, `docs/28-sistema/80-Usuarios.md`.
- **API:** `docs/31-api-references/sistema/README.md`, `docs/31-api-references/autenticacion/README.md`.
- **Plan migración enum (ya ejecutado):** `docs/28-sistema/81-Roles-Plan-Migracion-Enum.md`.

---

**Última actualización:** generado para retomar Paso 2 (Frontend) y Paso 3 (Permisos y políticas) en otra sesión.
