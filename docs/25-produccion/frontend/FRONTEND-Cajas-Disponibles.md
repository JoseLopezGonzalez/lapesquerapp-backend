# Frontend - Endpoints para Cajas Disponibles

## 📋 Resumen

Este documento describe los endpoints y funcionalidades disponibles para que el frontend pueda identificar y trabajar con cajas disponibles (no usadas en producción) vs cajas utilizadas.

---

## 🎯 Endpoints Principales

### 1. Listar Cajas con Información de Disponibilidad

**Endpoint**: `GET /v2/boxes`

**Descripción**: Lista todas las cajas con información de disponibilidad incluida.

**Query Parameters**:
- `available=true`: Solo cajas disponibles (no usadas en producción)
- `available=false`: Solo cajas usadas en producción
- Sin parámetro: Todas las cajas (con flag `isAvailable`)

**Ejemplo de Request**:
```http
GET /v2/boxes?available=true&perPage=50
```

**Ejemplo de Response**:
```json
{
    "data": [
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
            "createdAt": "2024-01-15T10:00:00.000000Z",
            "isAvailable": true,
            "production": null
        },
        {
            "id": 124,
            "palletId": 45,
            "product": {...},
            "lot": "LOT-2024-001",
            "gs1128": "1234567890124",
            "grossWeight": 26.00,
            "netWeight": 25.50,
            "createdAt": "2024-01-15T10:00:00.000000Z",
            "isAvailable": false,
            "production": {
                "id": 5,
                "lot": "PROD-2024-001"
            }
        }
    ],
    "meta": {...},
    "links": {...}
}
```

**Campos Importantes**:
- `isAvailable`: `true` si la caja no ha sido usada en producción, `false` si ya fue usada
- `production`: `null` si está disponible, o objeto con `id` y `lot` de la producción donde se usó

---

### 2. Obtener Solo Cajas Disponibles (Endpoint Especializado)

**Endpoint**: `GET /v2/boxes/available`

**Descripción**: Endpoint optimizado específicamente para obtener cajas disponibles. Útil para seleccionar cajas para producción.

**Query Parameters**:
- `lot`: Filtrar por lote específico
- `product_id`: Filtrar por producto específico
- `product_ids`: Array de IDs de productos
- `pallet_id`: Filtrar por palet específico
- `pallet_ids`: Array de IDs de palets
- `onlyStored=true`: Solo cajas en palets almacenados (state_id = 2)
- `perPage`: Número de resultados por página (default: 50)

**Ejemplo de Request**:
```http
GET /v2/boxes/available?lot=LOT-2024-001&onlyStored=true&perPage=100
```

**Ejemplo de Response**:
```json
{
    "data": [
        {
            "id": 123,
            "palletId": 45,
            "product": {...},
            "lot": "LOT-2024-001",
            "isAvailable": true,
            "production": null
        }
    ],
    "meta": {...},
    "links": {...}
}
```

**Nota**: Este endpoint solo retorna cajas disponibles (`isAvailable: true`), por lo que el campo `production` siempre será `null`.

---

### 3. Listar Palets con Información de Disponibilidad

**Endpoint**: `GET /v2/pallets`

**Descripción**: Lista palets con información agregada de cajas disponibles y usadas.

**Query Parameters** (filtros de disponibilidad):
- `filters[hasAvailableBoxes]=true`: Solo palets que tienen al menos una caja disponible
- `filters[hasUsedBoxes]=true`: Solo palets que tienen al menos una caja usada

**Ejemplo de Request**:
```http
GET /v2/pallets?filters[hasAvailableBoxes]=true&perPage=20
```

**Ejemplo de Response**:
```json
{
    "data": [
        {
            "id": 45,
            "observations": "Palet de atún",
            "state": {...},
            "boxes": [
                {
                    "id": 123,
                    "isAvailable": true,
                    "production": null
                },
                {
                    "id": 124,
                    "isAvailable": false,
                    "production": {
                        "id": 5,
                        "lot": "PROD-2024-001"
                    }
                }
            ],
            "numberOfBoxes": 2,
            "availableBoxesCount": 1,
            "usedBoxesCount": 1,
            "totalAvailableWeight": 25.50,
            "totalUsedWeight": 25.50,
            "netWeight": 51.00
        }
    ]
}
```

**Campos Importantes en Palet**:
- `availableBoxesCount`: Número de cajas disponibles
- `usedBoxesCount`: Número de cajas usadas
- `totalAvailableWeight`: Peso total de cajas disponibles
- `totalUsedWeight`: Peso total de cajas usadas
- `netWeight`: Peso total de todas las cajas (disponibles + usadas)

---

## 💡 Casos de Uso para el Frontend

### Caso 1: Seleccionar Cajas para Producción

**Escenario**: El usuario necesita seleccionar cajas para asignar a un proceso de producción.

**Solución**:
```javascript
// Obtener solo cajas disponibles
const response = await fetch('/v2/boxes/available?onlyStored=true&perPage=100', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenant
    }
});

const { data: availableBoxes } = await response.json();

// Filtrar por lote si es necesario
const boxesForLot = availableBoxes.filter(box => box.lot === selectedLot);

// Mostrar solo cajas disponibles en el selector
```

---

### Caso 2: Mostrar Estado de Cajas en un Palet

**Escenario**: Mostrar qué cajas están disponibles y cuáles ya fueron usadas en un palet.

**Solución**:
```javascript
// Obtener palet con información de disponibilidad
const response = await fetch(`/v2/pallets/${palletId}`, {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenant
    }
});

const pallet = await response.json();

// Mostrar información agregada
console.log(`Cajas disponibles: ${pallet.availableBoxesCount}`);
console.log(`Cajas usadas: ${pallet.usedBoxesCount}`);
console.log(`Peso disponible: ${pallet.totalAvailableWeight} kg`);

// Mostrar estado individual de cada caja
pallet.boxes.forEach(box => {
    if (box.isAvailable) {
        console.log(`Caja ${box.id}: Disponible`);
    } else {
        console.log(`Caja ${box.id}: Usada en producción ${box.production.lot}`);
    }
});
```

---

### Caso 3: Filtrar Palets con Cajas Disponibles

**Escenario**: Mostrar solo palets que tienen cajas disponibles para despacho.

**Solución**:
```javascript
// Obtener solo palets con cajas disponibles
const response = await fetch('/v2/pallets?filters[hasAvailableBoxes]=true&filters[state]=stored', {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenant
    }
});

const { data: pallets } = await response.json();

// Mostrar solo palets que tienen stock disponible
pallets.forEach(pallet => {
    if (pallet.availableBoxesCount > 0) {
        // Mostrar palet en la lista
    }
});
```

---

### Caso 4: Validar Disponibilidad Antes de Asignar

**Escenario**: Validar que una caja está disponible antes de permitir asignarla a producción.

**Solución**:
```javascript
// Al seleccionar una caja, verificar disponibilidad
const boxId = selectedBoxId;

const response = await fetch(`/v2/boxes/${boxId}`, {
    headers: {
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenant
    }
});

const box = await response.json();

if (!box.isAvailable) {
    alert(`La caja ${box.id} ya fue usada en la producción ${box.production.lot}`);
    return;
}

// Proceder con la asignación
```

---

## 🔍 Filtros Disponibles

### Endpoint `/v2/boxes`

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `available` | `true`/`false` | Filtrar por disponibilidad |
| `lot` | string | Filtrar por lote |
| `lots` | array | Filtrar por múltiples lotes |
| `products` | array | Filtrar por IDs de productos |
| `species` | array | Filtrar por IDs de especies |
| `pallets` | array | Filtrar por IDs de palets |
| `palletState` | `stored`/`shipped` | Filtrar por estado del palet |
| `orderState` | `pending`/`finished`/`without_order` | Filtrar por estado del pedido |
| `onlyStored` | `true` | Solo cajas en palets almacenados |

### Endpoint `/v2/pallets`

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `filters[hasAvailableBoxes]` | `true` | Solo palets con cajas disponibles |
| `filters[hasUsedBoxes]` | `true` | Solo palets con cajas usadas |
| `filters[state]` | `stored`/`shipped` | Filtrar por estado |
| `filters[products]` | array | Filtrar por productos |
| `filters[species]` | array | Filtrar por especies |
| `filters[lots]` | array | Filtrar por lotes |

---

## ⚠️ Consideraciones Importantes

### 1. Carga de Relaciones

Los endpoints cargan automáticamente las relaciones necesarias (`productionInputs`) para calcular `isAvailable`. No es necesario hacer requests adicionales.

### 2. Rendimiento

- El endpoint `/v2/boxes/available` está optimizado para obtener solo cajas disponibles
- Usa `whereDoesntHave('productionInputs')` en la query, lo cual es eficiente
- Para grandes volúmenes, usa paginación (`perPage`)

### 3. Consistencia de Datos

- `isAvailable` se calcula en tiempo real basado en la existencia de `productionInputs`
- Si se elimina un `ProductionInput`, la caja vuelve a estar disponible automáticamente

### 4. Validación en Backend

Aunque el frontend puede filtrar cajas disponibles, **siempre se debe validar en el backend** antes de crear un `ProductionInput`. El backend rechazará cajas ya utilizadas.

---

## 📝 Ejemplos de Integración

### React/Vue Component Example

```javascript
// Componente para seleccionar cajas disponibles
const BoxSelector = ({ lot, onSelect }) => {
    const [boxes, setBoxes] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchAvailableBoxes = async () => {
            try {
                const response = await api.get('/v2/boxes/available', {
                    params: {
                        lot: lot,
                        onlyStored: true,
                        perPage: 100
                    }
                });
                setBoxes(response.data.data);
            } catch (error) {
                console.error('Error fetching boxes:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchAvailableBoxes();
    }, [lot]);

    if (loading) return <div>Cargando...</div>;

    return (
        <div>
            <h3>Cajas Disponibles ({boxes.length})</h3>
            {boxes.map(box => (
                <div key={box.id} onClick={() => onSelect(box)}>
                    <p>Caja #{box.id} - {box.netWeight} kg</p>
                    <p>Lote: {box.lot}</p>
                </div>
            ))}
        </div>
    );
};
```

---

## 🔗 Referencias

- Documentación de Cajas: `docs/23-inventario/32-Cajas.md`
- Documentación de Palets: `docs/23-inventario/31-Palets.md`
- Documentación de Producción Entradas: `docs/25-produccion/13-Produccion-Entradas.md`
- Investigación de Impacto: `docs/INVESTIGACION-Impacto-Cajas-Disponibles-Palets.md`

---

**Última actualización**: 2025-01-27

