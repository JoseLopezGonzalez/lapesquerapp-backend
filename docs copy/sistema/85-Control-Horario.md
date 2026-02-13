# Sistema - Control Horario (Time Punch System)

## ⚠️ Estado de la API
- **v1**: No implementada
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de **Control Horario** permite registrar fichajes de empleados mediante tarjetas NFC. El sistema funciona con eventos históricos que se registran automáticamente como entrada (IN) o salida (OUT) basándose en el último evento registrado del empleado.

**Flujo**:
- Un lector NFC conectado a una Raspberry Pi emula un teclado USB
- Cuando un empleado pasa su tarjeta NFC, el lector envía el UID como texto
- Una interfaz web envía ese UID a la API
- La API determina automáticamente si es entrada o salida y registra el evento

**Características**:
- Sistema basado en eventos históricos (no se borran eventos)
- Determinación automática de tipo de evento (IN/OUT)
- Soporte multi-tenant
- Identificación de dispositivo de registro
- Log histórico completo e inmutable

---

## 🗄️ Estructura de Base de Datos

### Tabla: `employees`

**Migración**: `database/migrations/companies/2026_01_15_211200_create_employees_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del empleado |
| `name` | string | NO | Nombre completo del empleado |
| `nfc_uid` | string | NO | UID único de la tarjeta NFC (único) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `nfc_uid` (unique)

**Nota**: Cada empleado debe tener un UID NFC único asociado. Este UID es el que envía el lector NFC cuando se pasa la tarjeta.

### Tabla: `punch_events`

**Migración**: `database/migrations/companies/2026_01_15_211201_create_punch_events_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del evento |
| `employee_id` | bigint | NO | FK a `employees` - Empleado que registra el fichaje |
| `event_type` | enum | NO | Tipo de evento: `IN` (entrada) o `OUT` (salida) |
| `device_id` | string | NO | Identificador del dispositivo que registró el fichaje |
| `timestamp` | timestamp | NO | Hora exacta del evento de fichaje |
| `created_at` | timestamp | NO | Fecha de creación del registro |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `employee_id` (foreign key a `employees`)
- Índice compuesto: `(employee_id, timestamp)` - Para consultas eficientes del último evento

**Restricciones**:
- `event_type` solo puede ser `IN` o `OUT`
- Los eventos **nunca se borran ni modifican** - es un log histórico

---

## 📦 Modelos Eloquent

### Employee

**Archivo**: `app/Models/Employee.php`

**Traits**:
- `UsesTenantConnection` - Multi-tenancy
- `HasFactory` - Para testing y seeders

**Fillable Attributes**:
```php
protected $fillable = [
    'name',
    'nfc_uid',
];
```

**Relaciones**:
- `punchEvents()`: HasMany → `PunchEvent` - Todos los eventos de fichaje del empleado
- `lastPunchEvent()`: HasOne → `PunchEvent` - Último evento de fichaje (más reciente)

**Ejemplo de uso**:
```php
$employee = Employee::where('nfc_uid', 'ABC123')->first();
$lastEvent = $employee->lastPunchEvent;
$allEvents = $employee->punchEvents()->orderBy('timestamp', 'desc')->get();
```

### PunchEvent

**Archivo**: `app/Models/PunchEvent.php`

**Traits**:
- `UsesTenantConnection` - Multi-tenancy
- `HasFactory` - Para testing y seeders

**Fillable Attributes**:
```php
protected $fillable = [
    'employee_id',
    'event_type',
    'device_id',
    'timestamp',
];
```

**Casts**:
```php
protected $casts = [
    'timestamp' => 'datetime',
];
```

**Constantes**:
```php
const TYPE_IN = 'IN';
const TYPE_OUT = 'OUT';
```

**Relaciones**:
- `employee()`: BelongsTo → `Employee` - Empleado que registró el evento

---

## 🔌 Endpoints API

### Empleados (Employees)

#### Listar Empleados

**Endpoint**: `GET /api/v2/employees`

**Autenticación**: Requerida (`auth:sanctum`)

**Query Parameters**:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | Filtrar por ID exacto |
| `ids` | array | Filtrar por múltiples IDs |
| `name` | string | Filtrar por nombre (LIKE) |
| `nfc_uid` | string | Filtrar por UID NFC |
| `with_last_punch` | boolean | Incluir último evento de fichaje |
| `perPage` | integer | Resultados por página (default: 15) |

**Ejemplo de request**:
```http
GET /api/v2/employees?with_last_punch=true&perPage=20
```

**Response exitoso (200)**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "nfcUid": "ABC123DEF456",
      "lastPunchEvent": {
        "event_type": "IN",
        "timestamp": "2026-01-15 08:30:00"
      },
      "createdAt": "2026-01-10T10:00:00.000000Z",
      "updatedAt": "2026-01-10T10:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50
  },
  "links": {...}
}
```

#### Crear Empleado

**Endpoint**: `POST /api/v2/employees`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string | Sí | Nombre completo del empleado |
| `nfc_uid` | string | Sí | UID único de la tarjeta NFC |

**Ejemplo de request**:
```json
{
  "name": "Juan Pérez",
  "nfc_uid": "ABC123DEF456"
}
```

**Response exitoso (201)**:
```json
{
  "message": "Empleado creado correctamente.",
  "data": {
    "id": 1,
    "name": "Juan Pérez",
    "nfcUid": "ABC123DEF456",
    "createdAt": "2026-01-15T10:00:00.000000Z",
    "updatedAt": "2026-01-15T10:00:00.000000Z"
  }
}
```

#### Mostrar Empleado

**Endpoint**: `GET /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Response exitoso (200)**:
```json
{
  "message": "Empleado obtenido correctamente.",
  "data": {
    "id": 1,
    "name": "Juan Pérez",
    "nfcUid": "ABC123DEF456",
    "lastPunchEvent": {
      "event_type": "IN",
      "timestamp": "2026-01-15 08:30:00"
    },
    "createdAt": "2026-01-10T10:00:00.000000Z",
    "updatedAt": "2026-01-10T10:00:00.000000Z"
  }
}
```

#### Actualizar Empleado

**Endpoint**: `PUT /api/v2/employees/{id}` o `PATCH /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string | Opcional | Nombre completo del empleado |
| `nfc_uid` | string | Opcional | UID único de la tarjeta NFC |

**Response exitoso (200)**:
```json
{
  "message": "Empleado actualizado correctamente.",
  "data": {
    "id": 1,
    "name": "Juan Pérez Actualizado",
    "nfcUid": "ABC123DEF456",
    "createdAt": "2026-01-10T10:00:00.000000Z",
    "updatedAt": "2026-01-15T11:00:00.000000Z"
  }
}
```

#### Eliminar Empleado

**Endpoint**: `DELETE /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Response exitoso (200)**:
```json
{
  "message": "Empleado eliminado correctamente."
}
```

#### Eliminar Múltiples Empleados

**Endpoint**: `DELETE /api/v2/employees`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:
```json
{
  "ids": [1, 2, 3]
}
```

**Response exitoso (200)**:
```json
{
  "message": "Empleados eliminados correctamente."
}
```

#### Opciones de Empleados

**Endpoint**: `GET /api/v2/employees/options`

**Autenticación**: Requerida (`auth:sanctum`)

**Query Parameters**:
- `name`: Filtrar por nombre (opcional)

**Response exitoso (200)**:
```json
[
  {
    "id": 1,
    "name": "Juan Pérez",
    "nfcUid": "ABC123DEF456"
  },
  {
    "id": 2,
    "name": "María García",
    "nfcUid": "DEF456GHI789"
  }
]
```

---

### Eventos de Fichaje (Punch Events)

#### Listar Eventos de Fichaje

**Endpoint**: `GET /api/v2/punches`

**Autenticación**: Requerida (`auth:sanctum`)

**Query Parameters**:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | Filtrar por ID exacto |
| `ids` | array | Filtrar por múltiples IDs |
| `employee_id` | integer | Filtrar por empleado específico |
| `employee_ids` | array | Filtrar por múltiples empleados |
| `event_type` | string | Filtrar por tipo (`IN` o `OUT`) |
| `device_id` | string | Filtrar por dispositivo |
| `date` | string | Filtrar por día específico (ej: `2026-01-15`) |
| `date_start` | string | Filtrar desde fecha (incluye todo el día) |
| `date_end` | string | Filtrar hasta fecha (incluye todo el día) |
| `timestamp_start` | string | Filtrar desde timestamp (más preciso) |
| `timestamp_end` | string | Filtrar hasta timestamp (más preciso) |
| `perPage` | integer | Resultados por página (default: 15) |

**Ejemplo de request**:
```http
GET /api/v2/punches?date=2026-01-15&event_type=IN&perPage=50
```

**Response exitoso (200)**:
```json
{
  "data": [
    {
      "id": 1,
      "employee": {
        "id": 1,
        "name": "Juan Pérez",
        "nfcUid": "ABC123DEF456"
      },
      "employeeId": 1,
      "eventType": "IN",
      "deviceId": "raspberry-pi-entrada-principal",
      "timestamp": "2026-01-15 08:30:00",
      "createdAt": "2026-01-15T08:30:01.000000Z",
      "updatedAt": "2026-01-15T08:30:01.000000Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

#### Mostrar Evento Específico

**Endpoint**: `GET /api/v2/punches/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Response exitoso (200)**:
```json
{
  "message": "Evento de fichaje obtenido correctamente.",
  "data": {
    "id": 1,
    "employee": {
      "id": 1,
      "name": "Juan Pérez",
      "nfcUid": "ABC123DEF456"
    },
    "employeeId": 1,
    "eventType": "IN",
    "deviceId": "raspberry-pi-entrada-principal",
    "timestamp": "2026-01-15 08:30:00",
    "createdAt": "2026-01-15T08:30:01.000000Z",
    "updatedAt": "2026-01-15T08:30:01.000000Z"
  }
}
```

#### Registrar Fichaje

Registra un nuevo evento de fichaje. Acepta tanto UID NFC como `employee_id` (método manual).

**Endpoint**: `POST /api/v2/punches`

**Autenticación**: No requerida (ruta pública dentro del tenant)

**Headers requeridos**:
- `X-Tenant`: Identificador del tenant
- `Content-Type`: `application/json`

**Body (JSON)**:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `uid` | string | Opcional* | UID de la tarjeta NFC del empleado |
| `employee_id` | integer | Opcional* | ID del empleado (método manual) |
| `device_id` | string | Sí | Identificador del dispositivo que registra el fichaje |
| `timestamp` | string | No | Fecha y hora del evento en formato ISO 8601. Si no se proporciona, se usa la hora del servidor |

\* Debe proporcionar `uid` o `employee_id` (al menos uno)

**Ejemplo de request (NFC)**:
```json
{
  "uid": "ABC123DEF456",
  "device_id": "raspberry-pi-entrada-principal"
}
```

**Ejemplo de request (Manual)**:
```json
{
  "employee_id": 1,
  "device_id": "manual-web-interface"
}
```

**Ejemplo de request (con timestamp)**:
```json
{
  "employee_id": 1,
  "device_id": "raspberry-pi-entrada-principal",
  "timestamp": "2026-01-15T14:30:00"
}
```

**Response exitoso (201)**:
```json
{
  "message": "Fichaje registrado correctamente.",
  "data": {
    "employee_name": "Juan Pérez",
    "event_type": "IN",
    "timestamp": "2026-01-15 14:30:00",
    "device_id": "raspberry-pi-entrada-principal"
  }
}
```

**Response error - Empleado no encontrado (404)**:
```json
{
  "message": "Empleado no encontrado.",
  "error": "EMPLOYEE_NOT_FOUND"
}
```

**Response error - Validación (422)**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "uid": ["Debe proporcionar uid o employee_id."],
    "employee_id": ["Debe proporcionar uid o employee_id."],
    "device_id": ["The device id field is required."]
  }
}
```

#### Eliminar Evento

**Endpoint**: `DELETE /api/v2/punches/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Nota**: Normalmente los eventos históricos no se deberían eliminar, pero se permite para casos especiales (correcciones, etc.)

**Response exitoso (200)**:
```json
{
  "message": "Evento de fichaje eliminado correctamente."
}
```

#### Eliminar Múltiples Eventos

**Endpoint**: `DELETE /api/v2/punches`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:
```json
{
  "ids": [1, 2, 3]
}
```

**Response exitoso (200)**:
```json
{
  "message": "Eventos de fichaje eliminados correctamente."
}
```

---

## 🔄 Lógica de Determinación de Tipo de Evento

El sistema determina automáticamente si un nuevo evento es **IN** (entrada) o **OUT** (salida) basándose en el último evento registrado del empleado:

### Algoritmo

1. Se busca el último evento del empleado ordenado por `timestamp` descendente
2. **Si no existe evento previo** → El nuevo evento es **IN**
3. **Si el último evento es OUT** → El nuevo evento es **IN**
4. **Si el último evento es IN** → El nuevo evento es **OUT**

### Ejemplo de flujo

| Hora | Último Evento | Nuevo Evento | Resultado |
|------|---------------|--------------|-----------|
| 08:00 | - | - | **IN** (primer fichaje del día) |
| 13:00 | IN (08:00) | - | **OUT** (último fue IN) |
| 14:00 | OUT (13:00) | - | **IN** (último fue OUT) |
| 17:00 | IN (14:00) | - | **OUT** (último fue IN) |

### Características

- **No se puede cambiar el tipo** una vez registrado
- **Los eventos nunca se modifican ni borran**
- **El sistema asume que siempre alternan** entre IN y OUT
- **Si hay un error de registro** (ej: olvidó fichar), el siguiente fichaje seguirá la secuencia lógica

---

## 🏗️ Arquitectura

### Flujo Completo

```
1. Empleado pasa tarjeta NFC → Lector NFC
2. Lector NFC emula teclado USB → Envía UID como texto
3. Raspberry Pi recibe UID → Interfaz web captura UID
4. Interfaz web → POST /api/v2/punches {uid, device_id, timestamp?}
5. Backend busca Employee por nfc_uid
6. Backend consulta último PunchEvent del empleado
7. Backend determina tipo (IN/OUT) automáticamente
8. Backend crea nuevo PunchEvent en transacción
9. Backend responde con datos del empleado y evento
```

### Controladores

#### EmployeeController

**Archivo**: `app/Http/Controllers/v2/EmployeeController.php`

**Métodos**:
- `index(Request $request)`: Lista empleados (paginado con filtros)
- `store(Request $request)`: Crea un nuevo empleado
- `show(string $id)`: Muestra un empleado específico
- `update(Request $request, string $id)`: Actualiza un empleado
- `destroy(string $id)`: Elimina un empleado
- `destroyMultiple(Request $request)`: Elimina múltiples empleados
- `options(Request $request)`: Obtiene opciones de empleados (para selects)

#### PunchController

**Archivo**: `app/Http/Controllers/v2/PunchController.php`

**Métodos**:
- `index(Request $request)`: Lista eventos de fichaje (paginado con filtros)
- `show(string $id)`: Muestra un evento específico
- `store(Request $request)`: Registra un nuevo fichaje (acepta UID NFC o employee_id)
- `destroy(string $id)`: Elimina un evento
- `destroyMultiple(Request $request)`: Elimina múltiples eventos

**Transacciones**:
- La creación del evento se realiza dentro de una transacción de base de datos para garantizar la integridad

---

## 📝 Ejemplos de Uso

### Crear un empleado (seeder o manual)

```php
$employee = Employee::create([
    'name' => 'Juan Pérez',
    'nfc_uid' => 'ABC123DEF456',
]);
```

### Consultar eventos de un empleado

```php
$employee = Employee::where('nfc_uid', 'ABC123DEF456')->first();

// Último evento
$lastEvent = $employee->lastPunchEvent;

// Todos los eventos del día
$todayEvents = $employee->punchEvents()
    ->whereDate('timestamp', today())
    ->orderBy('timestamp', 'asc')
    ->get();

// Todos los eventos
$allEvents = $employee->punchEvents()
    ->orderBy('timestamp', 'desc')
    ->get();
```

### Consultar todos los fichajes del día

```php
$events = PunchEvent::whereDate('timestamp', today())
    ->with('employee')
    ->orderBy('timestamp', 'desc')
    ->get();
```

---

## ⚠️ Limitaciones Actuales (MVP)

El sistema actual es un **MVP (Minimum Viable Product)** y **NO incluye**:

- ❌ Sistema de turnos (mañana, tarde, noche)
- ❌ Cálculo de horas trabajadas
- ❌ Detección de horas extra
- ❌ Cálculos salariales
- ❌ Validaciones de horarios de trabajo
- ❌ Notificaciones por fichajes fuera de horario
- ❌ Reportes de asistencia
- ❌ Gestión de ausencias o vacaciones
- ❌ Integración con sistemas de nómina

**Todo esto quedará para futuras iteraciones**.

---

## 🔐 Seguridad y Multi-Tenancy

- El sistema utiliza el trait `UsesTenantConnection` en todos los modelos
- Todas las consultas se filtran automáticamente por tenant
- El endpoint es público pero requiere el header `X-Tenant` para identificar el tenant
- **Nota**: Si se requiere mayor seguridad, se puede agregar autenticación básica o API keys por dispositivo

---

## 📚 Referencias

- **Modelos**: `app/Models/Employee.php`, `app/Models/PunchEvent.php`
- **Controladores**: 
  - `app/Http/Controllers/v2/EmployeeController.php`
  - `app/Http/Controllers/v2/PunchController.php`
- **Resources**: 
  - `app/Http/Resources/v2/EmployeeResource.php`
  - `app/Http/Resources/v2/PunchEventResource.php`
- **Migraciones**: 
  - `database/migrations/companies/2026_01_15_211200_create_employees_table.php`
  - `database/migrations/companies/2026_01_15_211201_create_punch_events_table.php`
- **Rutas**: `routes/api.php` (dentro del grupo `v2`)
- **Documentación Frontend**: `docs/sistema/86-Control-Horario-FRONTEND.md` - Guía completa para desarrolladores frontend sobre cómo usar las APIs

