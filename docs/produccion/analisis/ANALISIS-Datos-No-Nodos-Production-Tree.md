# Análisis: Datos que NO son Nodos en el Endpoint Production Tree

**Endpoint**: `GET /v2/productions/{id}/process-tree`  
**Método**: `ProductionController@getProcessTree()`  
**Fecha**: 2025-01-27

---

## 📋 Estructura de la Respuesta

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [...],    // ✅ ESTOS SÍ SON NODOS
    "totals": {...}           // ❌ ESTOS NO SON NODOS - Son totales/resumen
  }
}
```

---

## 🎯 Datos que NO son Nodos

### 1. Campo `message` (Nivel raíz)

**Tipo**: String  
**Descripción**: Mensaje informativo de la respuesta

```json
"message": "Árbol de procesos obtenido correctamente."
```

---

### 2. Objeto `totals` (Dentro de `data`)

**Tipo**: Object  
**Descripción**: Totales globales de la producción  
**Método que lo genera**: `Production::calculateGlobalTotals()`

#### Propiedades del objeto `totals`:

##### 2.1. Totales de Entrada y Salida

- **`totalInputWeight`** (float): Peso total de entrada en kg
- **`totalOutputWeight`** (float): Peso total de salida en kg
- **`totalInputBoxes`** (int): Número total de cajas de entrada
- **`totalOutputBoxes`** (int): Número total de cajas de salida

##### 2.2. Totales de Merma (Waste)

- **`totalWaste`** (float): Peso total de merma en kg
- **`totalWastePercentage`** (float): Porcentaje de merma (0-100)

##### 2.3. Totales de Rendimiento (Yield)

- **`totalYield`** (float): Peso total de ganancia/rendimiento en kg
- **`totalYieldPercentage`** (float): Porcentaje de rendimiento (0-100)

##### 2.4. Totales de Venta (Sales)

- **`totalSalesWeight`** (float): Peso total en venta en kg
- **`totalSalesBoxes`** (int): Número total de cajas en venta
- **`totalSalesPallets`** (int): Número total de pallets en venta

##### 2.5. Totales de Stock (Almacenamiento)

- **`totalStockWeight`** (float): Peso total en stock en kg
- **`totalStockBoxes`** (int): Número total de cajas en stock
- **`totalStockPallets`** (int): Número total de pallets en stock

---

## 📊 Ejemplo Completo de `totals`

```json
{
  "totals": {
    "totalInputWeight": 730.0,
    "totalOutputWeight": 1300.0,
    "totalWaste": 0,
    "totalWastePercentage": 0,
    "totalYield": 600.0,
    "totalYieldPercentage": 82.19,
    "totalInputBoxes": 5,
    "totalOutputBoxes": 0,
    "totalSalesWeight": 725.0,
    "totalSalesBoxes": 145,
    "totalSalesPallets": 4,
    "totalStockWeight": 700.0,
    "totalStockBoxes": 41,
    "totalStockPallets": 2
  }
}
```

---

## 🔍 Lógica de Cálculo

### Merma vs Rendimiento

- **Si `input > output`**: Hay pérdida
  - `totalWaste = input - output`
  - `totalYield = 0`

- **Si `input < output`**: Hay ganancia
  - `totalWaste = 0`
  - `totalYield = output - input`

- **Si `input = output`**: Es neutro
  - `totalWaste = 0`
  - `totalYield = 0`

### Totales de Venta y Stock

Se calculan iterando sobre:
- **Venta**: Todos los productos del lote que están en órdenes de venta finalizadas
- **Stock**: Todos los productos del lote que están almacenados en cámaras/almacenes

---

## ✅ Resumen

**Datos que NO son nodos en el endpoint production tree:**

1. ✅ **`message`**: Mensaje informativo (string)
2. ✅ **`totals`**: Objeto completo con 13 propiedades:
   - 4 propiedades de entrada/salida
   - 2 propiedades de merma
   - 2 propiedades de rendimiento
   - 3 propiedades de venta
   - 3 propiedades de stock

**Total**: 14 campos que NO son nodos (1 mensaje + 13 propiedades en totals)

---

## 📝 Notas

- Los nodos están en el array `processNodes`
- Los totales son datos agregados/resumen que complementan los nodos
- Los totales se calculan dinámicamente desde los datos de producción
- El objeto `totals` proporciona una visión global de toda la producción

