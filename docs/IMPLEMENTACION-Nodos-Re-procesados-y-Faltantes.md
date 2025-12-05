# ✅ Implementación: Nodos de Re-procesados y Faltantes

**Fecha**: 2025-01-27  
**Estado**: ✅ **COMPLETADO**

---

## 📋 Resumen de Implementación

Se han implementado **DOS nuevos nodos** que cuelgan del nodo final:

1. **Nodo de Re-procesados** (`reprocessed`): Cajas usadas como materia prima en otro proceso
2. **Nodo de Balance** (`balance`): Balance completo (faltantes y sobras) de productos producidos

---

## 🔧 Métodos Implementados

### 1. Obtención de Datos

#### `getReprocessedDataByProduct(string $lot)`
- Obtiene cajas del lote que fueron consumidas en otros procesos
- Filtra por: `lot = $lot` + `has productionInputs`
- Agrupa por producto y proceso de destino
- Retorna: `array {productId => {productionRecordId => data}}`

#### `getMissingDataByProduct(string $lot)`
- Obtiene cajas del lote que están disponibles pero no contabilizadas
- Filtra por:
  - `lot = $lot`
  - `isAvailable = true` (sin `productionInputs`)
  - NO están en venta (sin palet con `order_id`)
  - NO están en stock (sin palet almacenado)
- Retorna: `array {productId => {product, boxes}}`

### 2. Creación de Nodos

#### `createReprocessedNodeForFinalNode(int $finalNodeId, array $reprocessedDataByProduct)`
- Crea UN SOLO nodo de re-procesados por nodo final
- Agrupa todos los productos del nodo final que fueron re-procesados
- Estructura:
  - `processes[]`: Array de procesos donde se usaron
  - Dentro de cada proceso: `products[]` con las cajas usadas

#### `createMissingNodeForFinalNode(int $finalNodeId, ...)`
- Crea UN SOLO nodo de balance por nodo final
- Agrupa todos los productos del nodo final que tienen desbalance (faltantes o sobras)
- Calcula: `producido - venta - stock - re-procesado = balance`
- Estructura:
  - `products[]`: Array de productos con su balance
  - Cada producto muestra: `produced`, `inSales`, `inStock`, `reprocessed`, `balance`
  - Lista las cajas individuales que faltan (si hay)

### 3. Integración

#### `attachSalesAndStockNodes(array $processNodes)`
- Actualizado para obtener datos de re-procesados y faltantes
- Llama a `attachAllNodesToFinalNodes()` con todos los datos

#### `attachAllNodesToFinalNodes(...)`
- Renombrado desde `attachSalesAndStockNodesToFinalNodes()`
- Ahora añade 4 tipos de nodos:
  - `sales`
  - `stock`
  - `reprocessed` ✨ NUEVO
  - `missing` ✨ NUEVO

#### `createOrphanNodes(...)`
- Actualizado para manejar nodos huérfanos de re-procesados y faltantes
- Crea nodos huérfanos cuando hay ambigüedad o sin nodo final

---

## 📊 Estructura de los Nodos

### Nodo de Re-procesados

```json
{
  "type": "reprocessed",
  "id": "reprocessed-{finalNodeId}",
  "parentRecordId": {finalNodeId},
  "productionId": 1,
  "processes": [
    {
      "process": {
        "id": 10,
        "name": "Enlatado",
        "type": "processing"
      },
      "productionRecord": {
        "id": 15,
        "productionId": 2,
        "startedAt": "2024-02-10T08:00:00Z",
        "finishedAt": "2024-02-10T12:00:00Z"
      },
      "products": [
        {
          "product": {
            "id": 5,
            "name": "Filetes de Atún"
          },
          "boxes": [
            {
              "id": 1234,
              "netWeight": 9.5,
              "gs1_128": "1234567890123"
            }
          ],
          "totalBoxes": 5,
          "totalNetWeight": 47.5
        }
      ],
      "totalBoxes": 5,
      "totalNetWeight": 47.5
    }
  ],
  "summary": {
    "processesCount": 1,
    "productsCount": 1,
    "boxesCount": 5,
    "netWeight": 47.5
  }
}
```

### Nodo de Balance

```json
{
  "type": "balance",
  "id": "balance-{finalNodeId}",
  "parentRecordId": {finalNodeId},
  "productionId": 1,
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "produced": {
        "boxes": 100,
        "weight": 1000.0
      },
      "inSales": {
        "boxes": 50,
        "weight": 500.0
      },
      "inStock": {
        "boxes": 30,
        "weight": 300.0
      },
      "reprocessed": {
        "boxes": 15,
        "weight": 150.0
      },
      "balance": {
        "boxes": 5,
        "weight": 50.0,
        "percentage": 5.0
      },
      "boxes": [
        {
          "id": 1234,
          "netWeight": 10.0,
          "gs1_128": "1234567890123",
          "location": null
        }
      ]
    }
  ],
  "summary": {
    "productsCount": 1,
    "totalBalanceBoxes": 5,
    "totalBalanceWeight": 50.0
  }
}
```

---

## ✅ Verificación

- ✅ Sintaxis PHP correcta
- ✅ Métodos implementados
- ✅ Integración completa
- ✅ Nodos huérfanos actualizados

---

## 📝 Próximos Pasos

1. Probar con datos reales
2. Verificar queries de faltantes
3. Actualizar documentación del frontend
4. Crear ejemplos JSON completos

---

**Estado**: ✅ **Implementación Completada**

