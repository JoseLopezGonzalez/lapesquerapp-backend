# Guía para el Frontend — Cambios de Roles en la API (Paso 2)

Este documento describe los cambios en la API de **roles** y **usuarios** (rol único por usuario, sin CRUD de roles). El backend ya está desplegado con estos cambios.

> **Documento principal de auth y usuarios:** para login (magic link / OTP), crear usuario sin contraseña y reenviar invitación, usa **`Guia-Frontend-Actualizaciones-Auth-y-Usuarios.md`**.

---

## 1. Resumen en una frase

**Cada usuario tiene un solo rol (string).** Ya no hay varios roles por usuario ni CRUD de roles; solo existe el endpoint de opciones para rellenar el desplegable de “Rol” al crear/editar usuarios.

---

## 2. Valores de rol (lista fija)

El rol es siempre uno de estos **strings**:

| Valor (en API y BD) | Etiqueta para mostrar |
|---------------------|------------------------|
| `tecnico`           | Técnico                |
| `administrador`     | Administrador          |
| `direccion`         | Dirección              |
| `administracion`    | Administración         |
| `comercial`         | Comercial              |
| `operario`          | Operario               |

Usar exactamente estos valores al enviar `role` en requests. Para mostrar en UI, usar la etiqueta (puedes mapear en el frontend o usar la que devuelve `GET /v2/roles/options`).

---

## 3. Cambios por endpoint

### 3.1 Login — `POST /v2/login`

**Antes:**
```json
{
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "assignedStoreId": 1,
    "companyName": "...",
    "companyLogoUrl": "...",
    "roles": ["admin", "manager"]
  }
}
```

**Ahora:**
```json
{
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "assignedStoreId": 1,
    "companyName": "...",
    "companyLogoUrl": "...",
    "role": "administrador"
  }
}
```

**Qué hacer en frontend:**
- Dejar de usar `user.roles` (array).
- Usar **`user.role`** (string) para saber el rol del usuario (menús, permisos de pantalla, etc.).
- Actualizar tipos/ interfaces: de `roles: string[]` a `role: string`.

---

### 3.2 Usuario actual — `GET /v2/me`

**Antes:** La respuesta incluía `roles: string[]`.

**Ahora:** La respuesta incluye **`role: string`** (uno de los 6 valores de la tabla anterior).

**Qué hacer en frontend:**
- Leer y guardar `user.role` en lugar de `user.roles`.
- Ajustar el tipo del “usuario logueado” en store/contexto a `role: string`.

---

### 3.3 Listado de usuarios — `GET /v2/users`

**Query params (filtro por rol):**
- **Antes:** `roles` (array de strings, ej. `roles[]=admin&roles[]=manager`).
- **Ahora:** **`role`** (un solo string), ej. `?role=administracion`.

**Respuesta (cada item de `data`):**
- **Antes:** `roles: string[]` (ej. `["admin", "manager"]`).
- **Ahora:** **`role: string`** (ej. `"administracion"`).

**Qué hacer en frontend:**
- En la tabla/listado, mostrar una columna “Rol” con el valor de `role` (o su etiqueta).
- Si hay filtro por rol: un único select; enviar el valor como query param `role`.
- Tipos: en el tipo “Usuario” de listado, usar `role: string` en lugar de `roles: string[]`.

---

### 3.4 Crear usuario — `POST /v2/users`

**Body (sin campo `password`; el acceso es por magic link u OTP):**

**Ahora:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "role": "administracion",
  "active": true
}
```

**Reglas de validación backend:**
- `name`, `email`, `role` son **obligatorios**.
- `role` debe ser uno de: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`.
- **No** hay campo `password`; los usuarios entran por magic link u OTP. Usar “Reenviar invitación” para enviarles el enlace.

**Qué hacer en frontend:**
- Enviar **`role`** (string), no `role_ids`.
- **No** incluir campo contraseña en el formulario.
- El desplegable de rol con **`GET /v2/roles/options`**; el valor enviado es el `id` (string) de cada opción.

> **Auth y usuarios actuales:** ver **`Guia-Frontend-Actualizaciones-Auth-y-Usuarios.md`** para login (magic link/OTP), crear usuario y reenviar invitación.

---

### 3.5 Actualizar usuario — `PUT /v2/users/{id}`

**Body:**

**Ahora:** Se pueden enviar **`name?`**, **`email?`**, **`role?`** (string, uno de los 6 valores), **`active?`**. No hay campo `password`.

**Qué hacer en frontend:**
- En el formulario de edición, un único select de rol con valor inicial `user.role`.
- Al guardar, enviar `role` (string) si cambió. No incluir campo contraseña.

---

### 3.6 Opciones de roles — `GET /v2/roles/options`

**Sigue existiendo.** Es el único endpoint de “roles” que se usa en frontend (para rellenar el select al crear/editar usuario).

**Respuesta (ejemplo):**
```json
[
  { "id": "tecnico", "name": "Técnico" },
  { "id": "administrador", "name": "Administrador" },
  { "id": "direccion", "name": "Dirección" },
  { "id": "administracion", "name": "Administración" },
  { "id": "comercial", "name": "Comercial" },
  { "id": "operario", "name": "Operario" }
]
```

**Importante:**
- `id` es **string** (no número). Ese es el valor que debe enviarse en `role` en POST/PUT de usuarios.
- `name` es la etiqueta para mostrar en el desplegable.

**Qué hacer en frontend:**
- Usar este endpoint para poblar el select “Rol” en crear y editar usuario.
- Tipo de opción: `{ id: string; name: string }`.
- Valor del `<select>` / formulario: el `id` (string).

---

### 3.7 Endpoints de roles que ya no existen

Los siguientes endpoints **se han eliminado**. No deben usarse ni enlazarse desde la UI:

| Método | Ruta                 | Antes              | Ahora   |
|--------|----------------------|--------------------|---------|
| GET    | `/v2/roles`          | Listar roles       | Eliminado |
| POST   | `/v2/roles`          | Crear rol          | Eliminado |
| GET    | `/v2/roles/{id}`     | Ver rol            | Eliminado |
| PUT    | `/v2/roles/{id}`     | Actualizar rol     | Eliminado |
| DELETE | `/v2/roles/{id}`     | Eliminar rol       | Eliminado |

**Qué hacer en frontend:**
- Quitar todas las pantallas o rutas de “gestión de roles” (listado, crear, editar, eliminar rol).
- Quitar llamadas a estos endpoints.
- Mantener solo la llamada a **`GET /v2/roles/options`** para el select de rol en usuarios.

---

## 4. Tipos / TypeScript (sugerencia)

```ts
// Rol: solo estos valores
export type RoleValue =
  | 'tecnico'
  | 'administrador'
  | 'direccion'
  | 'administracion'
  | 'comercial'
  | 'operario';

// Usuario (login, me, listado, show)
export interface User {
  id: number;
  name: string;
  email: string;
  assignedStoreId?: number;
  companyName?: string | null;
  companyLogoUrl?: string | null;
  role: RoleValue;  // antes: roles: string[]
  created_at?: string;
  updated_at?: string;
  active?: boolean;
}

// Opción para el select de rol (respuesta de GET /v2/roles/options)
export interface RoleOption {
  id: RoleValue;
  name: string;
}

// Crear usuario (sin password; acceso por magic link u OTP)
export interface CreateUserPayload {
  name: string;
  email: string;
  role: RoleValue;
  active?: boolean;
}

// Actualizar usuario (sin password)
export interface UpdateUserPayload {
  name?: string;
  email?: string;
  role?: RoleValue;
  active?: boolean;
}
```

---

## 5. Comprobar permisos / mostrar u ocultar por rol

Si actualmente compruebas algo como “tiene rol admin” o “tiene alguno de estos roles”:

**Antes (ejemplo):**
```ts
user.roles.includes('admin')
user.roles.some(r => ['admin', 'manager'].includes(r))
```

**Ahora:**
```ts
user.role === 'administrador'
['tecnico', 'administrador'].includes(user.role)
```

Ajustar todas las condiciones que usen `user.roles` para que usen **`user.role`** (string único).

---

## 6. Checklist de actualización

- [ ] Tipos: `User` con `role: string` (o `RoleValue`), sin `roles`.
- [ ] Tipos: payloads de crear/editar usuario con `role: string`, sin `role_ids`.
- [ ] Login: guardar y usar `user.role` (no `user.roles`).
- [ ] Endpoint `me`: leer `user.role`.
- [ ] Listado usuarios: mostrar `role`; filtro por `role` (query param string).
- [ ] Formulario crear usuario: select de rol desde `GET /v2/roles/options`; enviar `role` (string) en el body.
- [ ] Formulario editar usuario: mismo select; enviar `role` si se cambia.
- [ ] Eliminar pantallas/rutas de CRUD de roles (listar, crear, editar, eliminar rol).
- [ ] Eliminar llamadas a `GET/POST/PUT/DELETE /v2/roles` y `GET /v2/roles/{id}`.
- [ ] Todas las comprobaciones de “rol” en la UI usan `user.role` (string).
- [ ] Tests / E2E: actualizar datos y aserciones que usen `roles` o `role_ids`.

---

## 7. Errores frecuentes si no se actualiza

- **401/403 o datos raros en login/me:** Si el frontend espera `user.roles` (array), ese campo ya no existe; usar `user.role`.
- **Validación 422 al crear usuario:** Si se envía `role_ids` o no se envía `role`, el backend devuelve error. Hace falta enviar `role` (string).
- **Select de rol vacío o con IDs numéricos:** Las opciones vienen de `GET /v2/roles/options`; el `id` es string (ej. `"tecnico"`), no número.
- **Pantallas de “Roles” rotas:** Esos endpoints ya no existen; hay que quitar las pantallas y usar solo `roles/options` para el select en usuarios.

---

## 8. Dónde ampliar información

- Contrato detallado de usuarios y roles: `docs/31-api-references/sistema/README.md`.
- Autenticación (login, me): `docs/31-api-references/autenticacion/README.md`.
- Resumen de pasos pendientes (Paso 2 y 3): `docs/28-sistema/82b-Roles-Pasos-Pendientes.md`.

Si algo no cuadra con lo que devuelve la API, comprobar que el backend esté actualizado con la migración de roles (enum y columna `users.role`).
