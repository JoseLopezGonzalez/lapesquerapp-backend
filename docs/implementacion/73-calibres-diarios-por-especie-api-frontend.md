# Calibres diarios por especie — API para Frontend

**Estado**: Referencia para integración  
**Última actualización**: 2026-02-19

Documento de referencia para el equipo frontend: uso del endpoint que alimenta el componente **"Calibres diarios por especie"** (gráfico de anillos + leyenda con peso total, desglose por producto/calibre y porcentajes).

---

## 1. Convenciones

### Base URL y headers

- **Base URL**: `/api/v2` (el host lo define el frontend).
- **Headers** en todas las peticiones:
  - `Authorization: Bearer {access_token}` — Token Sanctum.
  - `X-Tenant: {subdomain}` — Subdominio del tenant (empresa).

### Permisos

- El usuario debe tener permiso para **ver recepciones de materia prima** (`viewAny` sobre `RawMaterialReception`). Si no lo tiene, el backend responde **403 Forbidden**.

---

## 2. Endpoint

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v2/raw-material-receptions/daily-calibers-by-species` | Desglose diario de pesos por producto (calibre) para una especie y fecha |

---

## 3. Request

### URL y parámetros (query)

**Método:** `GET`

**Ruta:** `/api/v2/raw-material-receptions/daily-calibers-by-species`

**Parámetros (query string):**

| Parámetro   | Tipo   | Obligatorio | Descripción |
|------------|--------|-------------|-------------|
| `date`     | string | Sí          | Fecha del día en formato `Y-m-d` (ej. `2026-02-18`) |
| `speciesId`| number | Sí          | ID de la especie (tenant) |

### Ejemplo de request

```http
GET /api/v2/raw-material-receptions/daily-calibers-by-species?date=2026-02-18&speciesId=3
Authorization: Bearer {token}
X-Tenant: {subdomain}
```

---

## 4. Respuesta exitosa (200)

El cuerpo es un objeto JSON con el total en kg y la lista de “calibres” (productos) con nombre, peso y porcentaje.

### Estructura

| Campo              | Tipo   | Descripción |
|--------------------|--------|--------------|
| `total_weight_kg`  | number | Suma de todos los pesos (kg) del día para esa especie; 2 decimales |
| `calibers`         | array  | Lista de objetos, ordenada por `weight_kg` descendente |

Cada elemento de `calibers`:

| Campo        | Tipo   | Descripción |
|-------------|--------|--------------|
| `product_id`| number | ID del producto (tenant); útil para enlaces o detalle |
| `name`      | string | Nombre del producto (etiqueta del calibre en gráfico/leyenda) |
| `weight_kg` | number | Peso total en kg (2 decimales) |
| `percentage`| number | Porcentaje sobre `total_weight_kg` (2 decimales). Si el total es 0, será 0 |

### Ejemplo de respuesta (200)

```json
{
  "total_weight_kg": 1789.5,
  "calibers": [
    {
      "product_id": 15,
      "name": "Pulpo Fresco +1kg",
      "weight_kg": 812.0,
      "percentage": 45.42
    },
    {
      "product_id": 17,
      "name": "Pulpo Fresco +2kg",
      "weight_kg": 478.0,
      "percentage": 26.72
    },
    {
      "product_id": 20,
      "name": "Pulpo Fresco -1kg",
      "weight_kg": 290.0,
      "percentage": 16.21
    },
    {
      "product_id": 12,
      "name": "Pulpo Fresco Roto",
      "weight_kg": 209.5,
      "percentage": 11.71
    }
  ]
}
```

### Sin datos

Si no hay recepciones para esa fecha o no hay productos de esa especie ese día, el backend responde **200** con:

```json
{
  "total_weight_kg": 0,
  "calibers": []
}
```

---

## 5. Errores

| Código | Situación | Respuesta |
|--------|-----------|-----------|
| **401** | No autenticado o token inválido | Body estándar de error de API |
| **403** | Usuario sin permiso para ver recepciones | Body estándar de error de API |
| **422** | Validación fallida (`date` inválida o `speciesId` no existente en tenant) | Objeto con `message` y `errors` (clave por campo) |

### Ejemplo de respuesta 422

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "speciesId": ["The selected species id is invalid."]
  }
}
```

---

## 6. Uso en el componente "Calibres diarios por especie"

- **Filtros**: el frontend debe tener un selector de **especie** (valores del catálogo de especies del tenant) y un selector de **fecha** (un solo día).
- **Llamada**: al cambiar especie o fecha, hacer `GET` a este endpoint con `date` en `Y-m-d` y `speciesId` con el ID de la especie elegida.
- **Visualización**:
  - Mostrar `total_weight_kg` como total del día (ej. “1.789,50 kg” con formato local).
  - Usar `calibers` para el gráfico de anillos y la leyenda: cada ítem es un segmento (nombre, peso, porcentaje). El backend no devuelve color; el frontend puede asignar colores por índice o por `product_id` para mantener consistencia entre cargas.

---

## 7. Referencias

- Especificación y plan de implementación del endpoint: documento de plan “Calibres diarios por especie” (especificación backend).
- Convenciones generales API v2: [CLAUDE.md](../../CLAUDE.md) sección 12 (API REST v2 Design).
