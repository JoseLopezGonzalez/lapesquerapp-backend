# Regularizacion de costes manuales de cajas

> **Estado**: Backend implementado. Pendiente de integracion frontend.
> **Fecha**: 2026-04-29
> **Ambito**: Cajas sin coste en ventas y stock actual.

---

## 1. Objetivo

Crear un bloque operativo para localizar cajas sin coste calculable y asignarles un coste manual por kg de forma masiva.

El bloque debe cubrir dos escenarios:

1. **Ventas sin coste**: cajas vendidas dentro de un rango de fechas cuyos costes no se pueden resolver por recepcion, produccion ni coste manual.
2. **Stock actual sin coste**: cajas actualmente en stock cuyos costes no se pueden resolver por recepcion, produccion ni coste manual.

La accion principal sera aplicar un **coste manual medio por producto**. El usuario selecciona uno o varios productos, introduce un coste €/kg para cada producto y el backend aplica ese valor a todas las cajas afectadas de esos productos.

---

## 2. Contexto actual

El coste de una caja se resuelve con este orden:

```text
1. Coste trazable por recepcion
2. Coste trazable por produccion/lote
3. Coste manual en boxes.manual_cost_per_kg
4. Sin coste
```

El campo `boxes.manual_cost_per_kg` ya existe y se trata como coste normal cuando no hay coste trazable.

La edicion directa de este campo solo debe permitirse desde la **edicion de palet**, y solo para roles `administrador` y `tecnico`.

Este nuevo bloque no cambia esa semantica: tambien escribira en `boxes.manual_cost_per_kg`, pero mediante una accion masiva controlada.

---

## 3. Roles y permisos

Solo pueden ver y ejecutar este bloque:

- `administrador`
- `tecnico`

Para otros roles:

- No se debe mostrar el bloque en frontend.
- El backend debe devolver `403 Forbidden` si intentan consultar o aplicar regularizaciones.

---

## 4. Definicion de "caja sin coste"

Una caja se considera sin coste cuando:

- `traceableCostPerKg === null`
- `manual_cost_per_kg === null`
- por tanto, `cost_per_kg === null`

No deben aparecer cajas que ya tengan coste manual, aunque no tengan coste trazable.

No deben aparecer cajas que ya tengan coste por recepcion o por produccion, aunque `manual_cost_per_kg` sea null.

---

## 5. Bloque A: ventas sin coste

### 5.1 Objetivo

Listar cajas de ventas dentro de un rango de fechas que no tienen coste calculable.

### 5.2 Filtro principal

El rango se aplicara sobre `orders.load_date`, igual que los endpoints de rentabilidad.

Parametros:


| Parametro       | Tipo         | Requerido | Descripcion                                |
| --------------- | ------------ | --------- | ------------------------------------------ |
| `dateFrom`      | `YYYY-MM-DD` | Si        | Inicio del rango sobre `orders.load_date`. |
| `dateTo`        | `YYYY-MM-DD` | Si        | Fin del rango sobre `orders.load_date`.    |
| `productIds[]`  | `integer[]`  | No        | Limita a productos concretos.              |
| `customerIds[]` | `integer[]`  | No        | Opcional para acotar trabajo.              |
| `orderIds[]`    | `integer[]`  | No        | Opcional para acotar trabajo.              |


### 5.3 Criterio de inclusion

Incluir una caja si:

- pertenece a un palet vinculado a un pedido;
- el pedido tiene `load_date` dentro del rango;
- el pedido esta en estado `finished`;
- la caja no tiene coste;

### 5.4 Datos que debe devolver

Respuesta orientativa:

```json
{
  "period": {
    "from": "2026-01-01",
    "to": "2026-03-31"
  },
  "summary": {
    "boxesCount": 28,
    "netWeightKg": 412.35,
    "productsCount": 4,
    "ordersCount": 12
  },
  "products": [
    {
      "product": {
        "id": 12,
        "name": "Merluza 400-600"
      },
      "boxesCount": 10,
      "netWeightKg": 120.5,
      "ordersCount": 5,
      "suggestedManualCostPerKg": null
    }
  ],
  "boxes": [
    {
      "id": 123,
      "palletId": 45,
      "orderId": 789,
      "orderFormattedId": "PED-2026-0001",
      "loadDate": "2026-03-15",
      "customer": {
        "id": 7,
        "name": "Cliente"
      },
      "product": {
        "id": 12,
        "name": "Merluza 400-600"
      },
      "lot": "LOTE-ANTIGUO",
      "gs1128": "...",
      "netWeightKg": 12.5,
      "traceableCostPerKg": null,
      "manualCostPerKg": null
    }
  ]
}
```

---

## 6. Bloque B: stock actual sin coste

### 6.1 Objetivo

Listar cajas que forman parte del stock actual y no tienen coste calculable.

### 6.2 Filtros

Parametros:


| Parametro      | Tipo         | Requerido | Descripcion                          |
| -------------- | ------------ | --------- | ------------------------------------ |
| `productIds[]` | `integer[]`  | No        | Limita a productos concretos.        |
| `storeIds[]`   | `integer[]`  | No        | Limita a almacenes concretos.        |
| `lot`          | `string`     | No        | Busca por lote.                      |
| `createdFrom`  | `YYYY-MM-DD` | No        | Fecha de creacion minima de la caja. |
| `createdTo`    | `YYYY-MM-DD` | No        | Fecha de creacion maxima de la caja. |


### 6.3 Criterio de inclusion

Incluir una caja si:

- pertenece a un palet en estado `registered` o `stored`;
- el palet no esta vinculado a un pedido, o su pedido no esta `finished` ni `incident`;
- la caja no tiene coste.

Notas:

- Si la caja esta usada en produccion pero sigue en un palet `registered` o `stored`, cuenta como stock para este bloque.
- Los palets `processed` se excluyen.

### 6.4 Datos que debe devolver

Respuesta orientativa:

```json
{
  "summary": {
    "boxesCount": 34,
    "netWeightKg": 520.75,
    "productsCount": 6,
    "palletsCount": 11
  },
  "products": [
    {
      "product": {
        "id": 12,
        "name": "Merluza 400-600"
      },
      "boxesCount": 8,
      "netWeightKg": 98.2,
      "palletsCount": 3,
      "suggestedManualCostPerKg": null
    }
  ],
  "boxes": [
    {
      "id": 456,
      "palletId": 90,
      "palletState": "stored",
      "store": {
        "id": 3,
        "name": "Camara 1"
      },
      "product": {
        "id": 12,
        "name": "Merluza 400-600"
      },
      "lot": "LOTE-STOCK",
      "gs1128": "...",
      "netWeightKg": 10.2,
      "traceableCostPerKg": null,
      "manualCostPerKg": null
    }
  ]
}
```

---

## 7. Dialog de aplicacion masiva por producto

### 7.1 Flujo de usuario

1. El usuario abre el bloque de regularizacion.
2. Selecciona pestana:
  - `Ventas sin coste`
  - `Stock actual sin coste`
3. El sistema muestra resumen por producto y detalle de cajas.
4. El usuario pulsa accion tipo `Aplicar costes medios`.
5. Se abre dialog con una fila por producto afectado.
6. El usuario introduce `manualCostPerKg` por producto.
7. El frontend envia la operacion masiva.
8. El backend actualiza `boxes.manual_cost_per_kg` en todas las cajas sin coste que coincidan con ese producto y con el alcance seleccionado.
9. El backend devuelve resumen de cambios.
10. El frontend refresca el listado.

### 7.2 Reglas del dialog

- Solo mostrar productos que tienen al menos una caja sin coste en el listado actual.
- Permitir dejar productos sin coste informado; esos productos no se modifican.
- Validar `manualCostPerKg >= 0`.
- Recomendacion: exigir confirmacion si se van a actualizar mas de 100 cajas.
- Mostrar impacto antes de confirmar:
  - cajas afectadas;
  - kg afectados;
  - coste total estimado: `kg * manualCostPerKg`.

### 7.3 Payload orientativo

```json
{
  "scope": "sales",
  "filters": {
    "dateFrom": "2026-01-01",
    "dateTo": "2026-03-31",
    "productIds": [12, 18]
  },
  "productCosts": [
    {
      "productId": 12,
      "manualCostPerKg": 2.75
    },
    {
      "productId": 18,
      "manualCostPerKg": 3.10
    }
  ]
}
```

Para stock:

```json
{
  "scope": "stock",
  "filters": {
    "storeIds": [3],
    "productIds": [12, 18]
  },
  "productCosts": [
    {
      "productId": 12,
      "manualCostPerKg": 2.75
    }
  ]
}
```

---

## 8. Endpoints implementados

### 8.1 Ventas sin coste

```http
GET /api/v2/cost-regularization/sales/missing-cost-boxes
```

Permiso:

- `administrador`
- `tecnico`

### 8.2 Stock actual sin coste

```http
GET /api/v2/cost-regularization/stock/missing-cost-boxes
```

Permiso:

- `administrador`
- `tecnico`

### 8.3 Aplicar costes manuales por producto

```http
POST /api/v2/cost-regularization/manual-costs/apply-by-product
```

Permiso:

- `administrador`
- `tecnico`

Comportamiento:

- recalcula en backend el universo de cajas a partir de `scope + filters`;
- no confia en una lista de ids enviada por frontend como unica fuente;
- solo actualiza cajas que siguen sin coste en el momento de ejecutar;
- ignora cajas que hayan recibido coste por otro proceso entre la consulta y la aplicacion;
- devuelve cuantas cajas se actualizaron y cuantas quedaron fuera.

Respuesta orientativa:

```json
{
  "scope": "sales",
  "updatedBoxesCount": 24,
  "skippedBoxesCount": 4,
  "updatedNetWeightKg": 350.75,
  "estimatedManualCost": 982.41,
  "products": [
    {
      "product": {
        "id": 12,
        "name": "Merluza 400-600"
      },
      "manualCostPerKg": 2.75,
      "updatedBoxesCount": 10,
      "updatedNetWeightKg": 120.5,
      "estimatedManualCost": 331.38
    }
  ]
}
```

---

## 9. Reglas de backend

### 9.1 Seguridad

- Autorizar en FormRequest o Policy.
- Rechazar cualquier rol distinto de `administrador` y `tecnico`.
- Registrar validaciones de entrada con mensajes claros.

### 9.2 Idempotencia practica

La operacion masiva no debe volver a actualizar cajas que ya tienen coste.

Condicion de actualizacion:

```text
traceableCostPerKg === null
manual_cost_per_kg IS NULL
```

### 9.3 Transaccion

La aplicacion masiva debe ejecutarse en transaccion.

Si falla una parte de la operacion, no debe quedar una aplicacion parcial.

### 9.4 Concurrencia

Antes de escribir cada caja, el backend debe comprobar de nuevo que sigue sin coste.

Esto evita que una caja reciba coste manual si, entre la consulta y la confirmacion, se corrigio una recepcion o una produccion y ya tiene coste trazable.

### 9.5 Auditoria

Hoy solo existe `boxes.manual_cost_per_kg`.

Decision implementada:

- No se registra evento extra de auditoria.
- No se crea tabla adicional.
- La fuente persistida es `boxes.manual_cost_per_kg`.

---

## 10. Consideraciones de frontend

### 10.1 Vista principal

Estructura recomendada:

- Tabs:
  - `Ventas sin coste`
  - `Stock actual sin coste`
- Filtros arriba.
- Resumen por producto.
- Tabla de cajas.
- Accion primaria: `Aplicar costes medios`.

### 10.2 Tabla de cajas

Columnas recomendadas para ventas:

- Caja
- Pedido
- Fecha carga
- Cliente
- Producto
- Lote
- Kg
- Palet

Columnas recomendadas para stock:

- Caja
- Palet
- Estado palet
- Almacen
- Producto
- Lote
- Kg

### 10.3 Dialog

Columnas del dialog:

- Producto
- Cajas afectadas
- Kg afectados
- Coste manual €/kg
- Coste total estimado

El dialog no debe pedir coste caja a caja. La unidad de entrada es producto.

---

## 10.4 Integracion frontend implementada

### Permisos

El bloque solo debe estar visible para usuarios con:

- `role = administrador`
- `role = tecnico`

Si otro rol llama al backend, recibira `403 Forbidden`.

### Rutas reales

```http
GET /api/v2/cost-regularization/sales/missing-cost-boxes
GET /api/v2/cost-regularization/stock/missing-cost-boxes
POST /api/v2/cost-regularization/manual-costs/apply-by-product
```

Cabeceras:

```http
Authorization: Bearer {token}
X-Tenant: {tenant}
Accept: application/json
```

### GET ventas sin coste

```http
GET /api/v2/cost-regularization/sales/missing-cost-boxes?dateFrom=2026-03-01&dateTo=2026-03-31
```

Query params:

| Parametro | Tipo | Requerido | Ejemplo |
|---|---:|---:|---|
| `dateFrom` | `YYYY-MM-DD` | Si | `2026-03-01` |
| `dateTo` | `YYYY-MM-DD` | Si | `2026-03-31` |
| `productIds[]` | `number[]` | No | `productIds[]=12&productIds[]=18` |
| `customerIds[]` | `number[]` | No | `customerIds[]=7` |
| `orderIds[]` | `number[]` | No | `orderIds[]=123` |

Respuesta:

```ts
type MissingSalesCostBoxesResponse = {
  period: {
    from: string;
    to: string;
  };
  summary: {
    boxesCount: number;
    netWeightKg: number;
    productsCount: number;
    ordersCount: number;
  };
  products: MissingSalesCostProductSummary[];
  boxes: MissingSalesCostBox[];
};

type MissingSalesCostProductSummary = {
  product: ProductRef;
  boxesCount: number;
  netWeightKg: number;
  ordersCount: number;
  suggestedManualCostPerKg: null;
};

type MissingSalesCostBox = {
  id: number;
  palletId: number;
  orderId: number;
  orderFormattedId: string;
  loadDate: string | null;
  customer: {
    id: number;
    name: string;
  } | null;
  product: ProductRef;
  lot: string | null;
  gs1128: string | null;
  netWeightKg: number;
  traceableCostPerKg: null;
  manualCostPerKg: null;
};

type ProductRef = {
  id: number;
  name: string | null;
};
```

### GET stock actual sin coste

```http
GET /api/v2/cost-regularization/stock/missing-cost-boxes
```

Query params:

| Parametro | Tipo | Requerido | Ejemplo |
|---|---:|---:|---|
| `productIds[]` | `number[]` | No | `productIds[]=12&productIds[]=18` |
| `storeIds[]` | `number[]` | No | `storeIds[]=3` |
| `lot` | `string` | No | `LOT-001` |
| `createdFrom` | `YYYY-MM-DD` | No | `2026-03-01` |
| `createdTo` | `YYYY-MM-DD` | No | `2026-03-31` |

Respuesta:

```ts
type MissingStockCostBoxesResponse = {
  summary: {
    boxesCount: number;
    netWeightKg: number;
    productsCount: number;
    palletsCount: number;
  };
  products: MissingStockCostProductSummary[];
  boxes: MissingStockCostBox[];
};

type MissingStockCostProductSummary = {
  product: ProductRef;
  boxesCount: number;
  netWeightKg: number;
  palletsCount: number;
  suggestedManualCostPerKg: null;
};

type MissingStockCostBox = {
  id: number;
  palletId: number | null;
  palletState: 'registered' | 'stored' | 'shipped' | 'processed' | 'unknown' | null;
  store: {
    id: number;
    name: string;
  } | null;
  product: ProductRef;
  lot: string | null;
  gs1128: string | null;
  netWeightKg: number;
  traceableCostPerKg: null;
  manualCostPerKg: null;
};
```

### POST aplicar costes manuales por producto

```http
POST /api/v2/cost-regularization/manual-costs/apply-by-product
```

Payload para ventas:

```json
{
  "scope": "sales",
  "filters": {
    "dateFrom": "2026-03-01",
    "dateTo": "2026-03-31",
    "productIds": [12, 18],
    "customerIds": [7],
    "orderIds": [123]
  },
  "productCosts": [
    {
      "productId": 12,
      "manualCostPerKg": 2.75
    },
    {
      "productId": 18,
      "manualCostPerKg": 3.1
    }
  ]
}
```

Payload para stock:

```json
{
  "scope": "stock",
  "filters": {
    "productIds": [12],
    "storeIds": [3],
    "lot": "LOT-001",
    "createdFrom": "2026-03-01",
    "createdTo": "2026-03-31"
  },
  "productCosts": [
    {
      "productId": 12,
      "manualCostPerKg": 2.75
    }
  ]
}
```

Respuesta:

```ts
type ApplyManualCostsByProductResponse = {
  scope: 'sales' | 'stock';
  updatedBoxesCount: number;
  skippedBoxesCount: number;
  updatedNetWeightKg: number;
  estimatedManualCost: number;
  products: Array<{
    product: ProductRef;
    manualCostPerKg: number;
    updatedBoxesCount: number;
    updatedNetWeightKg: number;
    estimatedManualCost: number;
  }>;
};
```

### Validaciones relevantes

El backend valida:

- `scope`: requerido, solo `sales` o `stock`.
- `filters`: requerido.
- Si `scope = sales`, `filters.dateFrom` y `filters.dateTo` son requeridos.
- `dateTo >= dateFrom`.
- `productCosts`: requerido, array con al menos una fila.
- `productCosts.*.productId`: requerido, existente y no repetido.
- `productCosts.*.manualCostPerKg`: requerido, numerico y `>= 0`.

Errores habituales:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "productCosts.0.manualCostPerKg": [
      "The productCosts.0.manualCostPerKg field must be at least 0."
    ]
  }
}
```

### Comportamiento importante para UI

- El backend recalcula el universo de cajas al aplicar. El frontend no debe mandar IDs de cajas.
- Si entre cargar la tabla y confirmar el dialog alguna caja recibe coste, el backend la omite y la cuenta en `skippedBoxesCount`.
- Despues de aplicar costes, el frontend debe refrescar la pestana actual.
- `scope = both` no existe. El usuario aplica sobre una pestana cada vez.
- Los valores se aplican solo a productos presentes en `productCosts`.
- Un producto visible en `products` puede no enviarse en `productCosts`; en ese caso no se modifica.

### Modelo de estado recomendado

```ts
type CostRegularizationTab = 'sales' | 'stock';

type ProductCostDraft = {
  productId: number;
  productName: string | null;
  boxesCount: number;
  netWeightKg: number;
  manualCostPerKg: number | null;
};
```

### UX recomendada

- Tabs: `Ventas sin coste` y `Stock actual sin coste`.
- En ventas, exigir rango de fechas antes de consultar.
- Mostrar cards/resumen:
  - cajas sin coste;
  - kg afectados;
  - productos afectados;
  - pedidos o palets afectados.
- Mostrar tabla de detalle debajo.
- Boton principal: `Aplicar costes medios`.
- Dialog con una fila por producto y un input numerico `€/kg`.
- Mostrar coste total estimado por producto: `netWeightKg * manualCostPerKg`.
- Deshabilitar confirmar si ningun producto tiene coste informado.
- Tras respuesta exitosa, mostrar resumen:
  - cajas actualizadas;
  - cajas omitidas;
  - kg actualizados;
  - coste manual aplicado.

---

## 11. Tests recomendados

### Backend

- Lista ventas sin coste por rango.
- Lista stock actual sin coste.
- No incluye cajas con coste por recepcion.
- No incluye cajas con coste por produccion.
- No incluye cajas con `manual_cost_per_kg`.
- Aplica coste manual por producto en ventas.
- Aplica coste manual por producto en stock.
- No aplica coste a productos no enviados.
- Rechaza rol no autorizado.
- Recalcula universo en backend y no actualiza cajas que ya recibieron coste entre consulta y aplicacion.

### Frontend

- Render de tabs y filtros.
- Dialog con resumen por producto.
- Validacion de coste numerico y no negativo.
- Confirmacion con impacto.
- Refresco de listado tras aplicar.
- Ocultacion del bloque para roles no autorizados.

---

## 12. Decisiones cerradas

- Ventas usa solo pedidos `finished`.
- Stock actual incluye palets `registered` y `stored` sin pedido terminado ni con incidencia.
- Si la caja esta usada en produccion pero sigue en ese stock, aparece igualmente.
- No se registra auditoria adicional.
- La aplicacion masiva se ejecuta por una pestana cada vez: `scope = sales` o `scope = stock`.
- No se implementa `scope = both` para evitar aplicar costes con filtros de naturaleza distinta.

## 13. Archivos backend implementados

- `app/Http/Controllers/v2/CostRegularizationController.php`
- `app/Http/Requests/v2/MissingSalesCostBoxesRequest.php`
- `app/Http/Requests/v2/MissingStockCostBoxesRequest.php`
- `app/Http/Requests/v2/ApplyManualBoxCostsByProductRequest.php`
- `app/Http/Requests/v2/Concerns/AuthorizesCostRegularization.php`
- `app/Services/v2/CostRegularizationService.php`
- `tests/Feature/CostRegularizationApiTest.php`
