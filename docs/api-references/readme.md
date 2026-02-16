# API References

Documentación completa de la API v2 con formato tipo Swagger. Esta documentación incluye todos los endpoints con sus requests y respuestas (exitosas y erróneas).

## 📋 Estructura

Esta documentación está organizada por módulos:

- **[Autenticación](./autenticacion/README.md)** — Login, logout, sesión
- **[Pedidos](./pedidos/README.md)** — Endpoints de pedidos, detalles planificados, incidentes
- **[Productos](./productos/README.md)** — Productos, categorías, familias
- **[Inventario](./inventario/README.md)** — Almacenes, palets, cajas
- **[Producción](./produccion/README.md)** — Lotes, registros, entradas y salidas
- **[Catálogos](./catalogos/README.md)** — Clientes, proveedores, especies, transportes, etc.
- **[Estadísticas](./estadisticas/README.md)** — Estadísticas y reportes
- **[Utilidades](./utilidades/README.md)** — Generación de PDFs y exportación Excel
- **[Sistema](./sistema/README.md)** — Usuarios, roles, logs de actividad
- **[Recepciones y Despachos](./recepciones-despachos/README.md)** — Recepciones de materia prima, despachos de cebo, liquidaciones
- **[Costos de Producción](./produccion-costos/README.md)** — Catálogo de costos y costos de producción

Visión general y contexto de negocio de cada módulo: [pedidos](../pedidos/), [inventario](../inventario/), [catalogos](../catalogos/), [produccion](../produccion/), [recepciones-despachos](../recepciones-despachos/), [sistema](../sistema/), [utilidades](../utilidades/).

## 🔑 Información General

### Base URL
```
/api/v2
```

### Headers Requeridos

#### Todas las rutas (excepto públicas)
```http
X-Tenant: {subdomain}
```

#### Rutas protegidas
```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

### Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| 200 | Éxito |
| 201 | Creado exitosamente |
| 400 | Solicitud incorrecta |
| 401 | No autenticado |
| 403 | Prohibido (sin permisos) |
| 404 | No encontrado |
| 422 | Error de validación |
| 500 | Error interno del servidor |

### Formato de Respuestas

#### Respuesta Exitosa
```json
{
  "message": "Mensaje descriptivo",
  "data": {
    // Datos del recurso
  }
}
```

#### Error de Validación (422)
```json
{
  "message": "Error de validación.",
  "userMessage": "Mensaje legible para el usuario",
  "errors": {
    "campo": ["El campo campo es obligatorio."]
  }
}
```

#### Error de Autenticación (401)
```json
{
  "message": "No autenticado."
}
```

#### Error Genérico (400, 404, 500)
```json
{
  "message": "Mensaje descriptivo del error",
  "userMessage": "Mensaje legible para el usuario",
  "error": "Detalle técnico del error (opcional, solo en 500)"
}
```

**Nota:** Todos los errores devueltos por la API incluyen `userMessage` con un mensaje en lenguaje natural para el usuario final. El campo `message` es más técnico y puede contener detalles para programadores.

## 🔐 Autenticación

La API usa Laravel Sanctum para autenticación basada en tokens. El acceso es **solo por Magic Link u OTP** (no hay login con contraseña).

1. El usuario introduce su email; el frontend llama a **POST /api/v2/auth/magic-link/request** o **POST /api/v2/auth/otp/request**.
2. Tras el clic en el enlace o al canjear el código (**POST /api/v2/auth/magic-link/verify** o **POST /api/v2/auth/otp/verify**), se recibe un `access_token` y `user`.
3. Incluye el token en el header `Authorization: Bearer {access_token}` y `X-Tenant: {subdomain}` en todas las peticiones protegidas.

Ver [Autenticación](autenticacion/README.md) para el detalle de los endpoints.

## 📚 Navegación

Cada módulo contiene su propia documentación con todos los endpoints. Cada endpoint incluye:

- **Método HTTP** y **URL**
- **Headers** requeridos
- **Parámetros** (query, path, body)
- **Request Body** (si aplica)
- **Respuesta Exitosa** (con ejemplo JSON)
- **Respuestas Erróneas** (con ejemplos JSON)

## 🔗 Referencias Cruzadas

- [Rutas Completas](../referencia/97-Rutas-Completas.md) - Listado completo de todas las rutas
- [Fundamentos](../fundamentos/00-Introduccion.md) — Introducción y conceptos
- [Autenticación y Autorización](../fundamentos/02-Autenticacion-Autorizacion.md) - Guía detallada de autenticación

