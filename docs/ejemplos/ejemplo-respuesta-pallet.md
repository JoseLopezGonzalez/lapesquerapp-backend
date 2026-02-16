# Ejemplo de Respuesta: GET /v2/pallets/{id}

## 📋 Respuesta del Endpoint

Cuando llamas a `GET /api/v2/pallets/{palletId}`, recibes una respuesta que incluye información de disponibilidad para cada caja.

## 📦 Estructura de la Respuesta

```json
{
    "id": 45,
    "observations": "Palet de atún",
    "state": {
        "id": 2,
        "name": "stored"
    },
    "productsNames": ["Atún fresco"],
    "boxes": [
        {
            "id": 123,
            "palletId": 45,
            "product": {
                "id": 10,
                "name": "Atún fresco",
                "species": {...},
                "captureZone": {...}
            },
            "lot": "LOT-2024-001",
            "gs1128": "1234567890123",
            "grossWeight": 26.00,
            "netWeight": 25.50,
            "createdAt": "2024-01-15",
            "isAvailable": true,  // ✅ ESTA CAJA SÍ SE PUEDE COGER
            "production": null    // No ha sido usada en producción
        },
        {
            "id": 124,
            "palletId": 45,
            "product": {
                "id": 10,
                "name": "Atún fresco",
                "species": {...},
                "captureZone": {...}
            },
            "lot": "LOT-2024-001",
            "gs1128": "1234567890124",
            "grossWeight": 26.00,
            "netWeight": 25.50,
            "createdAt": "2024-01-15",
            "isAvailable": false,  // ❌ ESTA CAJA NO SE PUEDE COGER
            "production": {        // Ya fue usada en esta producción
                "id": 5,
                "lot": "PROD-2024-001"
            }
        },
        {
            "id": 125,
            "palletId": 45,
            "product": {...},
            "lot": "LOT-2024-001",
            "gs1128": "1234567890125",
            "grossWeight": 26.00,
            "netWeight": 25.50,
            "createdAt": "2024-01-15",
            "isAvailable": true,   // ✅ ESTA CAJA SÍ SE PUEDE COGER
            "production": null
        }
    ],
    "lots": ["LOT-2024-001"],
    "netWeight": 77.00,
    "position": "A-12",
    "store": {
        "id": 1,
        "name": "Almacén Principal"
    },
    "orderId": 10,
    "numberOfBoxes": 3,
    "availableBoxesCount": 2,      // 2 cajas disponibles
    "usedBoxesCount": 1,           // 1 caja usada
    "totalAvailableWeight": 51.00, // Peso de cajas disponibles
    "totalUsedWeight": 25.50       // Peso de cajas usadas
}
```

## 💡 Cómo Usar Esta Información en el Frontend

### Opción 1: Filtrar Cajas Disponibles

```javascript
// Obtener el palet
const response = await fetch(`/api/v2/pallets/${palletId}`, {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenant
    }
});

const pallet = await response.json();

// Filtrar solo cajas disponibles
const availableBoxes = pallet.boxes.filter(box => box.isAvailable);

// Filtrar cajas NO disponibles (ya usadas)
const usedBoxes = pallet.boxes.filter(box => !box.isAvailable);

console.log(`Cajas disponibles: ${availableBoxes.length}`);
console.log(`Cajas usadas: ${usedBoxes.length}`);
```

### Opción 2: Mostrar Estado Visual

```javascript
pallet.boxes.forEach(box => {
    if (box.isAvailable) {
        // Mostrar caja como disponible (verde, habilitada)
        renderBox(box, { 
            status: 'available',
            canSelect: true 
        });
    } else {
        // Mostrar caja como usada (gris, deshabilitada)
        renderBox(box, { 
            status: 'used',
            canSelect: false,
            usedInProduction: box.production.lot 
        });
    }
});
```

### Opción 3: Validar Antes de Seleccionar

```javascript
function selectBox(boxId) {
    const box = pallet.boxes.find(b => b.id === boxId);
    
    if (!box.isAvailable) {
        alert(`Esta caja ya fue usada en la producción ${box.production.lot}`);
        return;
    }
    
    // Proceder con la selección
    addBoxToProduction(box);
}
```

### Opción 4: Usar Información Agregada

```javascript
// Usar los contadores agregados para mostrar resumen
console.log(`Total cajas: ${pallet.numberOfBoxes}`);
console.log(`Disponibles: ${pallet.availableBoxesCount}`);
console.log(`Usadas: ${pallet.usedBoxesCount}`);
console.log(`Peso disponible: ${pallet.totalAvailableWeight} kg`);
console.log(`Peso usado: ${pallet.totalUsedWeight} kg`);
```

## ✅ Resumen

**Para saber qué cajas NO puede coger:**

1. **Revisar el campo `isAvailable`** de cada caja en el array `boxes`:
   - `isAvailable: true` → ✅ **SÍ se puede coger**
   - `isAvailable: false` → ❌ **NO se puede coger**

2. **Si `isAvailable: false`**, el campo `production` te dice en qué producción se usó:
   ```javascript
   if (!box.isAvailable) {
       console.log(`Usada en producción: ${box.production.lot}`);
   }
   ```

3. **Usar los contadores agregados** para mostrar resumen:
   - `availableBoxesCount`: Cuántas cajas están disponibles
   - `usedBoxesCount`: Cuántas cajas ya fueron usadas

---

**El endpoint ya incluye toda esta información automáticamente. Solo necesitas revisar el campo `isAvailable` de cada caja.**

