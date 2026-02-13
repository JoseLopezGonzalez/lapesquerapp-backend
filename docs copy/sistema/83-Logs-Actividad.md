# Sistema - Logs de Actividad (Activity Logs)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `ActivityLog` registra **todas las acciones realizadas por usuarios autenticados** en el sistema. Se crea automáticamente mediante el middleware `LogActivity` que se ejecuta en cada request.

**Archivo del modelo**: `app/Models/ActivityLog.php`

**Middleware**: `app/Http/Middleware/LogActivity.php`

**Propósito**: Auditoría, seguridad, y seguimiento de actividad de usuarios.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `activity_logs`

**Migración base**: `database/migrations/companies/2025_01_11_215159_create_activity_logs_table.php`

**Migración adicional**:
- `2025_01_12_211945_update_activity_logs_table.php` - Agrega `country`, `city`, `region`, `platform`, `path`, `method`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del log |
| `user_id` | bigint | YES | FK a `users` - Usuario que realizó la acción |
| `ip_address` | string | YES | Dirección IP del cliente |
| `device` | string | YES | Tipo de dispositivo |
| `browser` | string | YES | Navegador (ej: "Chrome", "Firefox") |
| `location` | string | YES | Ubicación formateada (ej: "España, Madrid") |
| `country` | string | YES | País (obtenido por geolocalización) |
| `city` | string | YES | Ciudad |
| `region` | string | YES | Región |
| `platform` | string | YES | Plataforma SO (ej: "Windows", "Linux") |
| `path` | string | YES | Ruta del endpoint accedido |
| `method` | string | YES | Método HTTP (GET, POST, etc.) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**⚠️ Nota**: Los campos `action` y `details` fueron eliminados en una migración posterior (línea 24 de la migración de actualización los elimina en `down()`).

**⚠️ Nota**: El campo `token_id` está en fillable del modelo pero **NO existe en la tabla**.

**Índices**:
- `id` (primary key)
- Foreign key a `users`

**Constraints**:
- `user_id` → `users.id` (onDelete: cascade)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'user_id',
    'token_id', // ⚠️ No existe en BD
    'ip_address',
    'country',
    'city',
    'region',
    'platform',
    'browser',
    'device',
    'path',
    'method',
    'location',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `user()` - Usuario
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```
- Relación muchos-a-uno con `User`
- Usuario que realizó la acción

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ActivityLogController.php`

**Permisos requeridos**: `role:superuser` (solo superusuarios pueden ver logs)

### Métodos del Controlador

#### `index(Request $request)` - Listar Logs
```php
GET /v2/activity-logs
```

**Filtros disponibles** (query parameters):
- `users`: Filtrar por usuarios (array de IDs)
- `ipAddresses`: Filtrar por direcciones IP (array)
- `countries`: Filtrar por países (array)
- `city`: Buscar por ciudad (LIKE)
- `path`: Buscar por ruta (LIKE)
- `dates[start]`: Fecha inicio
- `dates[end]`: Fecha fin

**Orden**: Por `created_at` descendente (más reciente primero)

**Query parameters**: `per_page` (default: 10)

**Respuesta**: Collection paginada de `ActivityLogResource`

**Nota**: ⚠️ Solo tiene método `index()`. No hay `show()`, `store()`, `update()`, ni `destroy()`.

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ActivityLogResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "user": {
        "id": 5,
        "name": "Juan Pérez",
        "email": "juan@example.com"
    },
    "ipAddress": "192.168.1.100",
    "tokenId": 10,
    "device": "Desktop",
    "browser": "Chrome",
    "location": "España, Madrid",
    "country": "España",
    "city": "Madrid",
    "region": "Madrid",
    "platform": "Windows",
    "path": "/v2/orders",
    "method": "GET",
    "createdAt": "2025-01-15 14:30:00",
    "updatedAt": "2025-01-15 14:30:00"
}
```

---

## 🔄 Middleware LogActivity

**Archivo**: `app/Http/Middleware/LogActivity.php`

**Registro**: `app/Http/Kernel.php` (líneas 65, 72)
- Aplicado a grupo `web`
- Aplicado a grupo `api`

### Funcionamiento

1. **Se ejecuta después de la request** (línea 16)
2. **Obtiene IP del cliente** (línea 20)
3. **Geolocaliza IP** usando `Stevebauman\Location` (línea 25)
4. **Analiza User-Agent** usando `Jenssegers\Agent` (línea 31)
5. **Crea log solo si usuario está autenticado** (línea 40)

### Información Registrada

- **Usuario**: `auth()->id()`
- **Token**: ID del token de Sanctum actual (intenta guardar aunque campo no existe)
- **IP**: Dirección IP del cliente
- **Ubicación**: País, ciudad, región (vía geolocalización)
- **Dispositivo**: Plataforma, navegador, dispositivo (vía User-Agent)
- **Request**: Path y método HTTP

### Dependencias Externas

- **`jenssegers/agent`**: Para analizar User-Agent
- **`stevebauman/location`**: Para geolocalización de IP

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser`: Solo superusuarios pueden ver logs

**Rutas**: Todas bajo `/v2/activity-logs/*`

**Rutas definidas**:
- `GET /v2/activity-logs` - Listar logs

**Rutas NO disponibles**:
- `POST /v2/activity-logs` - No existe (se crean automáticamente)
- `GET /v2/activity-logs/{id}` - No existe
- `PUT /v2/activity-logs/{id}` - No existe
- `DELETE /v2/activity-logs/{id}` - No existe

---

## 📊 Creación Automática de Logs

Los logs se crean automáticamente en **cada request** de usuarios autenticados:

1. Middleware `LogActivity` intercepta la request
2. Se ejecuta la request normalmente
3. Después de la respuesta, se crea el log
4. Solo se registra si `auth()->check()` retorna `true`

**No se registran**:
- Requests de usuarios no autenticados
- Errores en el middleware (se capturan y se logean, pero no se crea ActivityLog)

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo token_id No Existe en BD

1. **token_id en Fillable Pero No en Migración** (`app/Models/ActivityLog.php:17`)
   - Campo `token_id` está en fillable pero no existe en la tabla
   - **Líneas**: 17
   - **Problema**: No se puede guardar token_id aunque se intenta en middleware
   - **Recomendación**: 
     - Agregar migración para crear campo `token_id`
     - O eliminar de fillable si no se necesita

### ⚠️ Campos action y details Fueron Eliminados

2. **Campos Eliminados Pero Referenciados** (`database/migrations/companies/2025_01_12_211945_update_activity_logs_table.php:24`)
   - `action` y `details` fueron eliminados en migración
   - **Problema**: Código comentado en middleware referencia estos campos
   - **Recomendación**: Limpiar código comentado

### ⚠️ CRUD Incompleto

3. **Solo index() Implementado** (`app/Http/Controllers/v2/ActivityLogController.php`)
   - Solo tiene método `index()`
   - **Problema**: No se puede ver un log específico ni eliminar logs
   - **Recomendación**: Agregar métodos si se necesitan

### ⚠️ Sin Eager Loading de Usuario

4. **No Carga Relación user** (`app/Http/Controllers/v2/ActivityLogController.php:16`)
   - No carga relación `user` en index
   - **Problema**: N+1 queries cuando se accede a `user` en resource
   - **Recomendación**: Agregar `->with('user')`

### ⚠️ Sin Filtro por Token

5. **No Se Puede Filtrar por Token** (`app/Http/Controllers/v2/ActivityLogController.php`)
   - No hay filtro por `token_id`
   - **Problema**: No se puede ver actividad de una sesión específica
   - **Recomendación**: Agregar filtro si se necesita

### ⚠️ Sin Filtro por Método HTTP

6. **No Se Puede Filtrar por Método HTTP** (`app/Http/Controllers/v2/ActivityLogController.php`)
   - No hay filtro por `method`
   - **Recomendación**: Agregar si se necesita

### ⚠️ Geolocalización Puede Fallar

7. **Geolocalización Sin Manejo Robusto** (`app/Http/Middleware/LogActivity.php:24-28`)
   - Si falla geolocalización, campos quedan como "Desconocido"
   - **Estado**: Manejo básico con try-catch
   - **Recomendación**: Mejorar manejo de errores si es crítico

### ⚠️ Performance en Cada Request

8. **LogActivity en Cada Request** (`app/Http/Kernel.php:65, 72`)
   - Se ejecuta en cada request de usuarios autenticados
   - **Problema**: Puede ser costoso (geolocalización, análisis User-Agent)
   - **Recomendación**: 
     - Considerar queue para creación de logs
     - O filtrar rutas que no necesitan logging

### ⚠️ Sin Paginación Consistente

9. **per_page vs perPage** (`app/Http/Controllers/v2/ActivityLogController.php:63`)
   - Usa `per_page` pero otros controladores usan `perPage`
   - **Problema**: Inconsistencia en API
   - **Recomendación**: Usar `perPage` para consistencia

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.
