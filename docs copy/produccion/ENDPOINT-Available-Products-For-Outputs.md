# Endpoint: Productos Disponibles para Outputs

**Endpoint**: `GET /v2/productions/{id}/available-products-for-outputs`  
**Propósito**: Obtener productos con ese lote y sus totales (cajas y peso) para facilitar la creación de outputs en el frontend.

---

## 📋 Descripción

Este endpoint detecta todos los productos que existen en el sistema con el lote de la producción, agrupándolos y mostrando sus totales de cajas y peso desde diferentes fuentes:

- **Venta**: Productos del lote en pedidos
- **Stock**: Productos del lote en almacenes
- **Re-procesados**: Productos del lote usados en otros procesos

El objetivo es facilitar al usuario en el frontend la creación de `production_outputs` basándose en datos reales del sistema, evitando errores de tipeo y asegurando que los outputs reflejen la realidad.

---

## 🔗 Endpoint

```
GET /v2/productions/{id}/available-products-for-outputs
```

### Parámetros

- `id` (path, requerido): ID de la producción

### Respuesta Exitosa (200)

```json
{
  "message": "Productos disponibles obtenidos correctamente.",
  "data": [
    {
      "product": {
        "id": 104,
        "name": "Pulpo Fresco Rizado"
      },
      "totalBoxes": 50,
      "totalWeight": 250.5,
      "sources": {
        "sales": {
          "boxes": 20,
          "weight": 100.0
        },
        "stock": {
          "boxes": 25,
          "weight": 125.5
        },
        "reprocessed": {
          "boxes": 5,
          "weight": 25.0
        }
      }
    },
    {
      "product": {
        "id": 105,
        "name": "Pulpo Fresco Entero"
      },
      "totalBoxes": 30,
      "totalWeight": 150.0,
      "sources": {
        "sales": {
          "boxes": 0,
          "weight": 0.0
        },
        "stock": {
          "boxes": 30,
          "weight": 150.0
        },
        "reprocessed": {
          "boxes": 0,
          "weight": 0.0
        }
      }
    }
  ]
}
```

---

## 📊 Estructura de la Respuesta

### Campo `data`

Array de objetos, cada uno representa un producto con ese lote:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `product` | object | Información del producto |
| `product.id` | integer | ID del producto |
| `product.name` | string | Nombre del producto |
| `totalBoxes` | integer | Total de cajas (suma de todas las fuentes) |
| `totalWeight` | float | Total de peso en kg (suma de todas las fuentes) |
| `sources` | object | Desglose por fuente |
| `sources.sales` | object | Totales desde venta |
| `sources.sales.boxes` | integer | Cajas en venta |
| `sources.sales.weight` | float | Peso en venta (kg) |
| `sources.stock` | object | Totales desde stock |
| `sources.stock.boxes` | integer | Cajas en stock |
| `sources.stock.weight` | float | Peso en stock (kg) |
| `sources.reprocessed` | object | Totales desde reprocesados |
| `sources.reprocessed.boxes` | integer | Cajas reprocesadas |
| `sources.reprocessed.weight` | float | Peso reprocesado (kg) |

---

## 🎯 Casos de Uso

### Caso 1: Crear Outputs Basándose en Datos Reales

**Escenario**: El usuario necesita crear outputs para un proceso de producción, pero no sabe exactamente qué productos y cantidades existen con ese lote.

**Solución**: 
1. Llamar al endpoint para obtener productos disponibles
2. Mostrar lista de productos con sus totales
3. Permitir al usuario seleccionar productos y usar los totales como base
4. Crear outputs con datos reales

### Caso 2: Validar Datos Antes de Crear Outputs

**Escenario**: El usuario quiere verificar qué productos realmente existen antes de crear outputs.

**Solución**: 
1. Llamar al endpoint
2. Comparar con lo que el usuario planea crear
3. Detectar discrepancias antes de guardar

### Caso 3: Autocompletar Formulario de Outputs

**Escenario**: El frontend quiere autocompletar un formulario con productos y cantidades sugeridas.

**Solución**: 
1. Llamar al endpoint
2. Usar `totalBoxes` y `totalWeight` como valores sugeridos
3. Permitir al usuario ajustar si es necesario

---

## 💡 Ejemplo de Uso en Frontend

```javascript
// Obtener productos disponibles
const response = await fetch(`/v2/productions/${productionId}/available-products-for-outputs`);
const { data: products } = await response.json();

// Mostrar en formulario de creación de outputs
products.forEach(product => {
  console.log(`${product.product.name}: ${product.totalBoxes} cajas, ${product.totalWeight}kg`);
  
  // Usar estos datos para pre-llenar formulario
  // product.product.id -> product_id
  // product.totalBoxes -> boxes (sugerido)
  // product.totalWeight -> weight_kg (sugerido)
});
```

---

## ⚠️ Consideraciones

### 1. Solo Productos Disponibles

El endpoint solo cuenta cajas que:
- Tienen el lote de la producción
- Están disponibles (no fueron consumidas como inputs)
- Están en venta, stock o fueron reprocesadas

### 2. No Incluye Productos Ya Producidos

El endpoint **NO** incluye productos que ya están registrados como producidos en outputs de la producción. Solo muestra productos que existen físicamente pero no están registrados.

### 3. Ordenamiento

Los productos se ordenan alfabéticamente por nombre para facilitar la búsqueda en el frontend.

### 4. Productos Sin Datos

Si un producto no tiene datos en ninguna fuente, no aparecerá en la respuesta.

---

## 🔍 Lógica Interna

El método `getAvailableProductsForOutputs()`:

1. Obtiene datos de venta, stock y reprocesados usando los métodos privados existentes
2. Agrupa todos los productos únicos
3. Para cada producto, calcula:
   - Totales desde venta
   - Totales desde stock
   - Totales desde reprocesados
   - Totales generales
4. Ordena por nombre de producto
5. Retorna array con estructura simplificada

---

## 📝 Notas Técnicas

- **Rendimiento**: El método reutiliza los métodos privados existentes (`getSalesDataByProduct`, `getStockDataByProduct`, `getReprocessedDataByProduct`), por lo que es eficiente
- **Caché**: No implementa caché, pero podría beneficiarse de ella si se usa frecuentemente
- **Filtros**: No acepta filtros adicionales, siempre retorna todos los productos con ese lote

---

## ✅ Testing Recomendado

1. **Test 1**: Producción con productos en venta
   - Debe retornar productos con datos de venta

2. **Test 2**: Producción con productos en stock
   - Debe retornar productos con datos de stock

3. **Test 3**: Producción con productos reprocesados
   - Debe retornar productos con datos de reprocesados

4. **Test 4**: Producción sin productos disponibles
   - Debe retornar array vacío

5. **Test 5**: Producción con productos en múltiples fuentes
   - Debe sumar correctamente los totales

---

**Autor**: Nueva funcionalidad  
**Fecha**: 2025-01-XX  
**Versión**: 1.0

