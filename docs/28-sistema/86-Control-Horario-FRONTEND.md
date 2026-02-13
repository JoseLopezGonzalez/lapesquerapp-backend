# Frontend - Sistema de Fichajes (Time Punch System)

## 📋 Resumen

Este documento describe cómo utilizar la API de fichajes desde el frontend. El sistema permite registrar fichajes de empleados mediante tarjetas NFC, donde el sistema determina automáticamente si el fichaje es entrada (IN) o salida (OUT) basándose en el último evento registrado.

**Flujo**:
1. Lector NFC envía UID → Interfaz web captura UID
2. Interfaz web → Envía UID a la API
3. API determina automáticamente tipo (IN/OUT)
4. API responde con datos del empleado y evento

---

## 🎯 Endpoints Disponibles

### Empleados (Employees)

#### Listar Empleados
#### Crear Empleado
#### Mostrar Empleado
#### Actualizar Empleado
#### Eliminar Empleado
#### Opciones de Empleados

### Eventos de Fichaje (Punch Events)

#### Listar Eventos de Fichaje
#### Mostrar Evento Específico
#### Registrar Fichaje
#### Eliminar Evento

---

## 👥 Endpoints de Empleados

### Listar Empleados (Paginado)

**Endpoint**: `GET /api/v2/employees`

**Autenticación**: Requerida (`auth:sanctum`)

**Headers requeridos**:
- `Authorization: Bearer {token}`
- `X-Tenant`: Identificador del tenant

**Query Parameters**:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | Filtrar por ID exacto |
| `ids` | array | Filtrar por múltiples IDs |
| `name` | string | Filtrar por nombre (LIKE) |
| `nfc_uid` | string | Filtrar por UID NFC |
| `with_last_punch` | boolean | Incluir último evento de fichaje |
| `perPage` | integer | Resultados por página (default: 15) |

**Ejemplo de Request**:
```http
GET /api/v2/employees?with_last_punch=true&perPage=20
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
X-Tenant: brisamar
```

**Ejemplo de Response**:
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
    "total": 50,
    "last_page": 3
  },
  "links": {...}
}
```

### Crear Empleado

**Endpoint**: `POST /api/v2/employees`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | `string` | ✅ Sí | Nombre completo del empleado |
| `nfc_uid` | `string` | ✅ Sí | UID único de la tarjeta NFC |

**Ejemplo de Request**:
```json
{
  "name": "Juan Pérez",
  "nfc_uid": "ABC123DEF456"
}
```

**Ejemplo de Response (201)**:
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

### Mostrar Empleado

**Endpoint**: `GET /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Ejemplo de Response (200)**:
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

### Actualizar Empleado

**Endpoint**: `PUT /api/v2/employees/{id}` o `PATCH /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Body (JSON)**:

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | `string` | Opcional | Nombre completo del empleado |
| `nfc_uid` | `string` | Opcional | UID único de la tarjeta NFC |

### Eliminar Empleado

**Endpoint**: `DELETE /api/v2/employees/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

**Ejemplo de Response (200)**:
```json
{
  "message": "Empleado eliminado correctamente."
}
```

### Opciones de Empleados

**Endpoint**: `GET /api/v2/employees/options`

**Autenticación**: Requerida (`auth:sanctum`)

**Query Parameters**:
- `name`: Filtrar por nombre (opcional)

**Ejemplo de Response (200)**:
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

## 📋 Endpoints de Eventos de Fichaje

### Listar Eventos de Fichaje (Paginado)

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
| `date_start` | string | Filtrar desde fecha |
| `date_end` | string | Filtrar hasta fecha |
| `timestamp_start` | string | Filtrar desde timestamp (más preciso) |
| `timestamp_end` | string | Filtrar hasta timestamp (más preciso) |
| `perPage` | integer | Resultados por página (default: 15) |

**Ejemplo de Request**:
```http
GET /api/v2/punches?date=2026-01-15&event_type=IN&perPage=50
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
X-Tenant: brisamar
```

**Ejemplo de Response**:
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

### Mostrar Evento Específico

**Endpoint**: `GET /api/v2/punches/{id}`

**Autenticación**: Requerida (`auth:sanctum`)

---

### Registrar Fichaje

**Endpoint**: `POST /api/v2/punches`

**Descripción**: Registra un nuevo evento de fichaje. Acepta tanto UID NFC como `employee_id` (método manual).

**Autenticación**: No requerida (ruta pública dentro del tenant)

**Headers requeridos**:
- `X-Tenant`: Identificador del tenant (ej: "brisamar", "pymcolorao")
- `Content-Type`: `application/json`

**Headers opcionales**:
- No requiere autenticación (token) para este endpoint

---

## 📤 Request (Enviar)

### Parámetros del Body

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `uid` | `string` | Opcional* | UID de la tarjeta NFC del empleado |
| `employee_id` | `integer` | Opcional* | ID del empleado (método manual) |
| `device_id` | `string` | ✅ Sí | Identificador del dispositivo que registra el fichaje (ej: "raspberry-pi-01", "entrada-principal") |
| `timestamp` | `string` | ❌ No | Fecha y hora del evento en formato ISO 8601. Si no se proporciona, se usa la hora del servidor |

\* Debe proporcionar `uid` o `employee_id` (al menos uno)

### Ejemplo de Request (Con timestamp)

```http
POST /api/v2/punches
Content-Type: application/json
X-Tenant: brisamar

{
  "uid": "ABC123DEF456",
  "device_id": "raspberry-pi-entrada-principal",
  "timestamp": "2026-01-15T14:30:00"
}
```

### Ejemplo de Request (Sin timestamp - Usa hora del servidor - NFC)

```http
POST /api/v2/punches
Content-Type: application/json
X-Tenant: brisamar

{
  "uid": "ABC123DEF456",
  "device_id": "raspberry-pi-entrada-principal"
}
```

### Ejemplo de Request (Método Manual - con employee_id)

```http
POST /api/v2/punches
Content-Type: application/json
X-Tenant: brisamar

{
  "employee_id": 1,
  "device_id": "manual-web-interface"
}
```

### Ejemplo en JavaScript/TypeScript

```javascript
// Función para registrar un fichaje
async function registerPunch(uid, deviceId, timestamp = null) {
    const url = `${API_BASE_URL}/v2/punches`;
    
    const body = {
        uid: uid,
        device_id: deviceId
    };
    
    // Agregar timestamp solo si se proporciona
    if (timestamp) {
        body.timestamp = timestamp;
    }
    
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Tenant': TENANT_SUBDOMAIN // ej: 'brisamar'
        },
        body: JSON.stringify(body)
    });
    
    return response;
}
```

### Ejemplo con Axios

```javascript
import axios from 'axios';

async function registerPunch(uid, deviceId, timestamp = null) {
    const data = {
        uid: uid,
        device_id: deviceId
    };
    
    if (timestamp) {
        data.timestamp = timestamp;
    }
    
    try {
        const response = await axios.post('/v2/punches', data, {
            headers: {
                'X-Tenant': TENANT_SUBDOMAIN
            }
        });
        
        return response.data;
    } catch (error) {
        // Manejar error (ver sección de errores)
        throw error;
    }
}
```

---

## 📥 Response (Recibir)

### Respuesta Exitosa (201 Created)

**Status Code**: `201`

**Body**:
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

**Campos de `data`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `employee_name` | `string` | Nombre completo del empleado |
| `event_type` | `string` | Tipo de evento: `"IN"` (entrada) o `"OUT"` (salida) |
| `timestamp` | `string` | Fecha y hora del evento en formato `YYYY-MM-DD HH:mm:ss` |
| `device_id` | `string` | Identificador del dispositivo que registró el fichaje |

### Ejemplo de Manejo de Respuesta Exitosa

```javascript
async function registerPunch(uid, deviceId) {
    const response = await fetch('/v2/punches', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Tenant': TENANT_SUBDOMAIN
        },
        body: JSON.stringify({
            uid: uid,
            device_id: deviceId
        })
    });
    
    if (response.ok) {
        const result = await response.json();
        
        console.log(`✅ Fichaje registrado: ${result.data.employee_name}`);
        console.log(`Tipo: ${result.data.event_type}`);
        console.log(`Hora: ${result.data.timestamp}`);
        
        // Mostrar mensaje al usuario
        showSuccessMessage(
            `${result.data.employee_name} - ${result.data.event_type === 'IN' ? 'Entrada' : 'Salida'}`
        );
        
        return result.data;
    } else {
        // Manejar error (ver sección de errores)
        const error = await response.json();
        throw error;
    }
}
```

---

## ❌ Errores (Error Handling)

### 1. Empleado No Encontrado (404 Not Found)

**Cuándo ocurre**: El UID proporcionado no existe en la base de datos.

**Status Code**: `404`

**Body**:
```json
{
  "message": "Empleado no encontrado con el UID proporcionado.",
  "error": "EMPLOYEE_NOT_FOUND"
}
```

**Ejemplo de Manejo**:

```javascript
try {
    const result = await registerPunch('UID_INEXISTENTE', 'device-01');
} catch (error) {
    if (error.response?.status === 404) {
        const errorData = error.response.data;
        if (errorData.error === 'EMPLOYEE_NOT_FOUND') {
            // Mostrar mensaje al usuario
            showErrorMessage('Tarjeta no reconocida. Contacte con administración.');
        }
    }
}
```

---

### 2. Validación Fallida (422 Unprocessable Entity)

**Cuándo ocurre**: Faltan campos requeridos o tienen formato incorrecto.

**Status Code**: `422`

**Body**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "uid": ["The uid field is required."],
    "device_id": ["The device id field is required."],
    "timestamp": ["The timestamp does not match the format Y-m-d H:i:s."]
  }
}
```

**Ejemplo de Manejo**:

```javascript
try {
    const result = await registerPunch(null, 'device-01'); // uid faltante
} catch (error) {
    if (error.response?.status === 422) {
        const errorData = error.response.data;
        
        // Mostrar errores de validación
        if (errorData.errors?.uid) {
            console.error('UID requerido:', errorData.errors.uid[0]);
        }
        if (errorData.errors?.device_id) {
            console.error('Device ID requerido:', errorData.errors.device_id[0]);
        }
        
        // Mostrar mensaje al usuario
        showErrorMessage('Datos incompletos. Por favor, verifique.');
    }
}
```

---

### 3. Error Interno del Servidor (500 Internal Server Error)

**Cuándo ocurre**: Error inesperado en el servidor (problemas de base de datos, etc.).

**Status Code**: `500`

**Body**:
```json
{
  "message": "Error al registrar el fichaje.",
  "error": "PUNCH_REGISTRATION_FAILED"
}
```

**Ejemplo de Manejo**:

```javascript
try {
    const result = await registerPunch('ABC123', 'device-01');
} catch (error) {
    if (error.response?.status === 500) {
        const errorData = error.response.data;
        
        // Log del error para debugging
        console.error('Error del servidor:', errorData);
        
        // Mostrar mensaje genérico al usuario
        showErrorMessage('Error al registrar el fichaje. Por favor, intente nuevamente.');
        
        // Opcional: Reintentar
        // retryPunch(uid, deviceId);
    }
}
```

---

### 4. Error de Red (Network Error)

**Cuándo ocurre**: Problemas de conexión, servidor inaccesible, timeout.

**Ejemplo de Manejo**:

```javascript
try {
    const result = await registerPunch('ABC123', 'device-01');
} catch (error) {
    if (!error.response) {
        // Error de red (no hay respuesta del servidor)
        console.error('Error de conexión:', error.message);
        showErrorMessage('Sin conexión al servidor. Verifique su conexión.');
        
        // Opcional: Guardar en cola local para reintentar después
        // savePunchToQueue(uid, deviceId);
    }
}
```

---

## 💡 Casos de Uso para el Frontend

### Caso 1: Listar Empleados en Cards para Fichaje Manual

**Escenario**: Mostrar empleados en cards para que el usuario seleccione uno y registre un fichaje.

**Implementación**:

```javascript
// Listar empleados
async function getEmployees() {
    const response = await fetch('/api/v2/employees?with_last_punch=true&perPage=100', {
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    const result = await response.json();
    return result.data;
}

// Registrar fichaje por employee_id
async function registerPunchByEmployeeId(employeeId, deviceId = 'manual-web-interface') {
    const response = await fetch('/api/v2/punches', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Tenant': TENANT_SUBDOMAIN
        },
        body: JSON.stringify({
            employee_id: employeeId,
            device_id: deviceId
        })
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, data: result.data };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}

// Componente React/Vue
function EmployeeCardsPunch() {
    const [employees, setEmployees] = useState([]);
    const [loading, setLoading] = useState(false);
    
    useEffect(() => {
        getEmployees().then(setEmployees);
    }, []);
    
    const handlePunch = async (employeeId) => {
        setLoading(true);
        const result = await registerPunchByEmployeeId(employeeId);
        
        if (result.success) {
            alert(`✅ ${result.data.employee_name} - ${result.data.event_type}`);
            // Actualizar lista
            getEmployees().then(setEmployees);
        } else {
            alert(`❌ Error: ${result.error.message}`);
        }
        
        setLoading(false);
    };
    
    return (
        <div className="employee-grid">
            {employees.map(employee => (
                <div 
                    key={employee.id} 
                    className="employee-card"
                    onClick={() => handlePunch(employee.id)}
                    disabled={loading}
                >
                    <h3>{employee.name}</h3>
                    {employee.lastPunchEvent && (
                        <p>
                            Último: {employee.lastPunchEvent.event_type === 'IN' ? '✅ Entrada' : '🚪 Salida'}
                            <br />
                            {employee.lastPunchEvent.timestamp}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}
```

---

### Caso 2: Listar Eventos de Fichaje del Día

**Escenario**: Mostrar todos los fichajes del día con filtros.

**Implementación**:

```javascript
// Listar eventos del día
async function getTodayPunches(date = null) {
    const today = date || new Date().toISOString().split('T')[0];
    const response = await fetch(
        `/api/v2/punches?date=${today}&perPage=100`,
        {
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-Tenant': TENANT_SUBDOMAIN
            }
        }
    );
    
    const result = await response.json();
    return {
        data: result.data,
        meta: result.meta,
        links: result.links
    };
}

// Filtrar por empleado
async function getEmployeePunches(employeeId, dateStart = null, dateEnd = null) {
    let url = `/api/v2/punches?employee_id=${employeeId}&perPage=50`;
    
    if (dateStart) url += `&date_start=${dateStart}`;
    if (dateEnd) url += `&date_end=${dateEnd}`;
    
    const response = await fetch(url, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    const result = await response.json();
    return result.data;
}

// Filtrar por tipo (IN/OUT)
async function getPunchesByType(eventType, date = null) {
    const today = date || new Date().toISOString().split('T')[0];
    const response = await fetch(
        `/api/v2/punches?event_type=${eventType}&date=${today}&perPage=100`,
        {
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-Tenant': TENANT_SUBDOMAIN
            }
        }
    );
    
    const result = await response.json();
    return result.data;
}
```

---

### Caso 3: Interfaz Simple de Fichaje

**Escenario**: Una página web simple donde el usuario pasa su tarjeta NFC y el sistema muestra el resultado.

**Implementación**:

```javascript
// Componente React/Vue ejemplo
function PunchClock() {
    const [uid, setUid] = useState('');
    const [loading, setLoading] = useState(false);
    const [lastPunch, setLastPunch] = useState(null);
    
    const deviceId = 'raspberry-pi-entrada-principal';
    
    // Función que se llama cuando el lector NFC envía el UID
    const handleNFCRead = async (nfcUID) => {
        setLoading(true);
        setUid(nfcUID);
        
        try {
            const response = await fetch('/v2/punches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Tenant': TENANT_SUBDOMAIN
                },
                body: JSON.stringify({
                    uid: nfcUID,
                    device_id: deviceId
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                setLastPunch(result.data);
                
                // Mostrar feedback visual
                playSuccessSound();
                showSuccessAnimation();
            } else {
                const error = await response.json();
                
                if (error.error === 'EMPLOYEE_NOT_FOUND') {
                    playErrorSound();
                    showError('Tarjeta no reconocida');
                } else {
                    showError('Error al registrar fichaje');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Error de conexión');
        } finally {
            setLoading(false);
        }
    };
    
    return (
        <div className="punch-clock">
            <h1>Control de Fichajes</h1>
            
            {lastPunch && (
                <div className="last-punch">
                    <p><strong>{lastPunch.employee_name}</strong></p>
                    <p>{lastPunch.event_type === 'IN' ? '✅ Entrada' : '🚪 Salida'}</p>
                    <p>{lastPunch.timestamp}</p>
                </div>
            )}
            
            {loading && <p>Procesando...</p>}
            
            {/* Simular lectura NFC (en producción vendría del lector) */}
            <button onClick={() => handleNFCRead('ABC123DEF456')}>
                Simular Lectura NFC
            </button>
        </div>
    );
}
```

---

### Caso 2: Integración con Lector NFC (Raspberry Pi)

**Escenario**: Una Raspberry Pi con lector NFC que emula un teclado USB. El UID se envía como texto automáticamente.

**Implementación**:

```javascript
// Función que captura el UID del lector NFC
let uidBuffer = '';
let uidTimeout = null;

// El lector NFC emula un teclado, así que el UID llega como texto
document.addEventListener('keypress', (event) => {
    // Si es Enter, procesar el UID acumulado
    if (event.key === 'Enter') {
        const uid = uidBuffer.trim();
        
        if (uid.length > 0) {
            processPunch(uid);
        }
        
        uidBuffer = '';
        clearTimeout(uidTimeout);
    } else {
        // Acumular caracteres
        uidBuffer += event.key;
        
        // Resetear buffer después de 2 segundos de inactividad
        clearTimeout(uidTimeout);
        uidTimeout = setTimeout(() => {
            uidBuffer = '';
        }, 2000);
    }
});

async function processPunch(uid) {
    const deviceId = getDeviceId(); // Obtener ID del dispositivo desde configuración
    
    try {
        const response = await fetch('/v2/punches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Tenant': TENANT_SUBDOMAIN
            },
            body: JSON.stringify({
                uid: uid,
                device_id: deviceId
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            // Mostrar en pantalla o LED
            displayResult(result.data);
            playSuccessBeep();
        } else {
            displayError(result.message);
            playErrorBeep();
        }
    } catch (error) {
        displayError('Error de conexión');
        playErrorBeep();
    }
}

function displayResult(data) {
    const message = `${data.employee_name} - ${data.event_type === 'IN' ? 'Entrada' : 'Salida'}`;
    console.log(message);
    // Mostrar en pantalla LCD o monitor
}
```

---

### Caso 3: Manejo de Errores con Reintentos

**Escenario**: Manejar errores de red y reintentar automáticamente.

**Implementación**:

```javascript
async function registerPunchWithRetry(uid, deviceId, maxRetries = 3) {
    let attempt = 0;
    
    while (attempt < maxRetries) {
        try {
            const response = await fetch('/v2/punches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Tenant': TENANT_SUBDOMAIN
                },
                body: JSON.stringify({
                    uid: uid,
                    device_id: deviceId
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                return { success: true, data: result.data };
            } else {
                const error = await response.json();
                
                // No reintentar errores 404 o 422
                if (response.status === 404 || response.status === 422) {
                    return { success: false, error: error };
                }
                
                // Reintentar errores 500 o de red
                throw new Error('Retryable error');
            }
        } catch (error) {
            attempt++;
            
            if (attempt >= maxRetries) {
                return {
                    success: false,
                    error: {
                        message: 'Error al conectar con el servidor después de varios intentos',
                        error: 'NETWORK_ERROR'
                    }
                };
            }
            
            // Esperar antes de reintentar (backoff exponencial)
            await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
        }
    }
}

// Uso
const result = await registerPunchWithRetry('ABC123', 'device-01');

if (result.success) {
    console.log('Fichaje registrado:', result.data);
} else {
    console.error('Error:', result.error);
}
```

---

### Caso 4: Cola Local de Fichajes

**Escenario**: Guardar fichajes en cola local si no hay conexión y enviarlos cuando se recupere.

**Implementación**:

```javascript
// Almacenamiento local (LocalStorage o IndexedDB)
const PUNCH_QUEUE_KEY = 'punch_queue';

// Agregar a la cola
function addToQueue(uid, deviceId, timestamp) {
    const queue = getQueue();
    queue.push({
        uid: uid,
        device_id: deviceId,
        timestamp: timestamp,
        createdAt: new Date().toISOString()
    });
    localStorage.setItem(PUNCH_QUEUE_KEY, JSON.stringify(queue));
}

// Obtener cola
function getQueue() {
    const queue = localStorage.getItem(PUNCH_QUEUE_KEY);
    return queue ? JSON.parse(queue) : [];
}

// Procesar cola
async function processQueue() {
    const queue = getQueue();
    
    if (queue.length === 0) {
        return;
    }
    
    console.log(`Procesando ${queue.length} fichajes en cola...`);
    
    for (const punch of queue) {
        try {
            const response = await fetch('/v2/punches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Tenant': TENANT_SUBDOMAIN
                },
                body: JSON.stringify({
                    uid: punch.uid,
                    device_id: punch.device_id,
                    timestamp: punch.timestamp
                })
            });
            
            if (response.ok) {
                // Remover de la cola
                removeFromQueue(punch);
                console.log('Fichaje enviado:', punch);
            } else {
                // Mantener en cola si es error del servidor
                console.error('Error al enviar fichaje:', await response.json());
            }
        } catch (error) {
            // Mantener en cola si hay error de red
            console.error('Error de red, manteniendo en cola:', error);
            break; // Salir del loop si no hay conexión
        }
    }
}

// Registrar fichaje (con cola como fallback)
async function registerPunch(uid, deviceId) {
    try {
        const response = await fetch('/v2/punches', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Tenant': TENANT_SUBDOMAIN
            },
            body: JSON.stringify({
                uid: uid,
                device_id: deviceId
            })
        });
        
        if (response.ok) {
            const result = await response.json();
            return { success: true, data: result.data };
        } else {
            const error = await response.json();
            return { success: false, error: error };
        }
    } catch (error) {
        // Sin conexión: agregar a cola
        addToQueue(uid, deviceId, new Date().toISOString());
        return {
            success: false,
            error: {
                message: 'Sin conexión. Fichaje guardado en cola.',
                error: 'QUEUED'
            }
        };
    }
}

// Procesar cola cuando se recupere la conexión
window.addEventListener('online', () => {
    processQueue();
});

// Procesar cola periódicamente
setInterval(processQueue, 60000); // Cada minuto
```

---

## 📝 Ejemplos de Integración Completa

### React Hook Example

```javascript
import { useState, useCallback } from 'react';

function usePunchClock(deviceId) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [lastPunch, setLastPunch] = useState(null);
    
    const registerPunch = useCallback(async (uid) => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await fetch('/v2/punches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Tenant': TENANT_SUBDOMAIN
                },
                body: JSON.stringify({
                    uid: uid,
                    device_id: deviceId
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                setLastPunch(result.data);
                return { success: true, data: result.data };
            } else {
                const errorData = await response.json();
                setError(errorData);
                return { success: false, error: errorData };
            }
        } catch (err) {
            const networkError = {
                message: 'Error de conexión',
                error: 'NETWORK_ERROR'
            };
            setError(networkError);
            return { success: false, error: networkError };
        } finally {
            setLoading(false);
        }
    }, [deviceId]);
    
    return {
        registerPunch,
        loading,
        error,
        lastPunch
    };
}

// Uso
function PunchClockComponent() {
    const { registerPunch, loading, error, lastPunch } = usePunchClock('device-01');
    
    const handleNFCRead = async (uid) => {
        const result = await registerPunch(uid);
        
        if (result.success) {
            alert(`✅ ${result.data.employee_name} - ${result.data.event_type}`);
        } else {
            if (result.error.error === 'EMPLOYEE_NOT_FOUND') {
                alert('❌ Tarjeta no reconocida');
            } else {
                alert('❌ Error al registrar fichaje');
            }
        }
    };
    
    return (
        <div>
            {/* UI aquí */}
        </div>
    );
}
```

---

## ⚠️ Consideraciones Importantes

### 1. Headers Requeridos

- **`X-Tenant`**: Siempre debe estar presente en todas las requests
- **`Content-Type`**: Debe ser `application/json`

### 2. Formato de Timestamp

Si envías `timestamp`, debe estar en formato ISO 8601:
- ✅ `"2026-01-15T14:30:00"`
- ✅ `"2026-01-15T14:30:00.000Z"`
- ❌ `"2026-01-15 14:30:00"` (no recomendado)

Si no envías `timestamp`, el servidor usará la hora actual automáticamente.

### 3. Device ID

El `device_id` debe ser consistente para cada dispositivo físico:
- Ejemplo: `"raspberry-pi-entrada-principal"`
- Ejemplo: `"raspberry-pi-almacen"`
- Ejemplo: `"device-01"`

### 4. Tipo de Evento Automático

El sistema determina automáticamente si es `IN` o `OUT`:
- No necesitas especificar el tipo
- Se basa en el último evento del empleado
- Si no hay evento previo → `IN`
- Si último fue `OUT` → `IN`
- Si último fue `IN` → `OUT`

### 5. Manejo de Errores

Siempre maneja los siguientes casos:
- **404**: Empleado no encontrado (tarjeta no válida)
- **422**: Validación fallida (datos incompletos)
- **500**: Error del servidor (reintentar)
- **Network Error**: Sin conexión (guardar en cola)

---

## 🔗 Referencias

- **Documentación Backend**: `docs/28-sistema/85-Control-Horario.md`
- **Documentación API**: `docs/30-referencia/97-Rutas-Completas.md`

---

## 📚 Ejemplos JSON de Respuestas

### Respuesta Exitosa (IN)
```json
{
  "message": "Fichaje registrado correctamente.",
  "data": {
    "employee_name": "Juan Pérez",
    "event_type": "IN",
    "timestamp": "2026-01-15 08:30:00",
    "device_id": "raspberry-pi-entrada-principal"
  }
}
```

### Respuesta Exitosa (OUT)
```json
{
  "message": "Fichaje registrado correctamente.",
  "data": {
    "employee_name": "Juan Pérez",
    "event_type": "OUT",
    "timestamp": "2026-01-15 17:00:00",
    "device_id": "raspberry-pi-entrada-principal"
  }
}
```

### Error: Empleado No Encontrado
```json
{
  "message": "Empleado no encontrado con el UID proporcionado.",
  "error": "EMPLOYEE_NOT_FOUND"
}
```

### Error: Validación
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "uid": ["The uid field is required."],
    "device_id": ["The device id field is required."]
  }
}
```

---

---

## 📝 Ejemplos de Integración con CRUD Completo

### Gestión Completa de Empleados

```javascript
// Crear empleado
async function createEmployee(name, nfcUid) {
    const response = await fetch('/api/v2/employees', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        },
        body: JSON.stringify({
            name: name,
            nfc_uid: nfcUid
        })
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, data: result.data };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}

// Actualizar empleado
async function updateEmployee(id, name, nfcUid) {
    const response = await fetch(`/api/v2/employees/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        },
        body: JSON.stringify({
            name: name,
            nfc_uid: nfcUid
        })
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, data: result.data };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}

// Eliminar empleado
async function deleteEmployee(id) {
    const response = await fetch(`/api/v2/employees/${id}`, {
        method: 'DELETE',
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, message: result.message };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}
```

### Gestión de Eventos de Fichaje

```javascript
// Listar eventos con filtros avanzados
async function getPunches(filters = {}) {
    const params = new URLSearchParams();
    
    if (filters.employeeId) params.append('employee_id', filters.employeeId);
    if (filters.eventType) params.append('event_type', filters.eventType);
    if (filters.date) params.append('date', filters.date);
    if (filters.dateStart) params.append('date_start', filters.dateStart);
    if (filters.dateEnd) params.append('date_end', filters.dateEnd);
    if (filters.deviceId) params.append('device_id', filters.deviceId);
    params.append('perPage', filters.perPage || 15);
    
    const response = await fetch(`/api/v2/punches?${params.toString()}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    const result = await response.json();
    return {
        data: result.data,
        meta: result.meta,
        links: result.links
    };
}

// Mostrar evento específico
async function getPunchEvent(id) {
    const response = await fetch(`/api/v2/punches/${id}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, data: result.data };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}

// Eliminar evento
async function deletePunchEvent(id) {
    const response = await fetch(`/api/v2/punches/${id}`, {
        method: 'DELETE',
        headers: {
            'Authorization': `Bearer ${token}`,
            'X-Tenant': TENANT_SUBDOMAIN
        }
    });
    
    if (response.ok) {
        const result = await response.json();
        return { success: true, message: result.message };
    } else {
        const error = await response.json();
        return { success: false, error: error };
    }
}
```

---

**Última actualización**: 2026-01-15

