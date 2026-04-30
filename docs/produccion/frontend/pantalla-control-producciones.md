# Pantalla de control de producciones

> **Estado**: Implementado (2026-04-30). Endpoints activos: `GET /api/v2/productions/control-panel` y `GET /api/v2/productions/orphan-stock`.
> **Ambito**: Frontend de producciones, alertas operativas, conciliacion, trazabilidad de lotes, stock y costes.
> **Base actual**: API v2 de producciones, conciliacion por producto, cierre definitivo, stock/palets/cajas y regularizacion de costes.

---

## 1. Objetivo de la pantalla

Crear una pantalla tipo **centro de control de producciones** donde el usuario pueda ver rapidamente:

- Que producciones estan abiertas, cerradas o bloqueadas por incidencias.
- Que lotes tienen balance no conciliado.
- Que cajas/lotes existen en stock, venta o reproceso sin una produccion coherente detras.
- Que producciones tienen costes incompletos o procedencia de coste poco clara.
- Que acciones concretas conviene hacer para dejar una produccion cuadrada.

La pantalla no debe sustituir al detalle de una produccion. Debe funcionar como una bandeja de trabajo: detectar problemas, priorizarlos y llevar al usuario al sitio correcto para resolverlos.

### 1.1 Lectura por objetivos

Estos bloques ayudan a visualizar que queremos enseñar, de donde sale la informacion y como se podria mostrar en pantalla.

#### Objetivo A: producciones abiertas, cerradas o bloqueadas por incidencias

**Que queremos saber**

Identificar en que estado operativo esta cada lote: si sigue vivo, si ya esta cerrado definitivamente, si esta listo para cerrar, si no se puede cerrar por una limitacion operativa normal o si tiene datos incoherentes que requieren correccion.

Hay que separar claramente dos situaciones:

- **Limitacion operativa**: la produccion esta conciliada, pero no se puede cerrar porque aun queda producto en stock, hay un pedido pendiente o falta completar una salida logistica. No significa que el dato este mal.
- **Alerta grave**: la produccion no esta conciliada, sobra o falta producto, hay productos vendidos/en stock/reprocesados que no se han producido en ningun nodo, o aparecen cajas sin destino/origen coherente. Aqui si hay que corregir datos o completar la produccion.

**De donde sale**

- Estado abierto/cerrado: fechas de la produccion (`opened_at`, `closed_at`).
- Bloqueos de cierre: resultado de `closure-check`, especialmente `blockingReasons`.
- Limitaciones operativas de cierre: stock restante conciliado, pedidos pendientes, palets asignados a pedido pero no enviados.
- Alertas graves: conciliacion `warning/error`, balance distinto de cero, productos contabilizados sin output final, cajas huerfanas o procesos finales sin outputs.

**Como mostrarlo**

- Badge de estado en la tabla: `Abierta`, `Cerrada`, `Lista para cerrar`, `No cerrable`, `No conciliada`.
- Los estados no son mutuamente excluyentes: una produccion puede ser `Abierta` Y `No conciliada` a la vez. Jerarquia de prioridad para el badge: `Cerrada` > `No conciliada` > `Lista para cerrar` > `No cerrable` > `Abierta`.
- Separar visualmente `Limitaciones` y `Alertas graves`.
- No pintar como error rojo una produccion conciliada que simplemente conserva stock.
- Contador doble: numero de alertas graves y numero de limitaciones operativas.
- Al seleccionar una fila, panel lateral agrupado por: conciliacion, stock/logistica, procesos, pedidos, cajas.
- Accion principal segun caso: `Ver conciliacion`, `Ver stock pendiente`, `Resolver pedido`, `Completar proceso`, `Cerrar produccion`.

#### Objetivo B: lotes con balance no conciliado

**Que queremos saber**

Detectar producciones donde lo producido no coincide con lo vendido, lo que queda en stock y lo reprocesado.

**De donde sale**

- Conciliacion por producto de la produccion.
- Formula logica: `producido - vendido - stock - reprocesado`.
- Estado de conciliacion: `ok`, `warning`, `error`.

**Como mostrarlo**

- Columna `Balance` con kg positivos o negativos.
- Columna `Conciliacion` con semaforo operativo.
- Fila expandible por producto para ver donde esta la diferencia.
- Orden por mayor diferencia absoluta para atacar primero los lotes mas descuadrados.

#### Objetivo C: cajas o lotes en stock, venta o reproceso sin produccion coherente

**Que queremos saber**

Encontrar cajas que existen fisicamente o comercialmente, pero cuya historia no cuadra con la produccion declarada.

**De donde sale**

- Cajas con lote en palets de stock, venta o usadas como input de reproceso.
- Produccion asociada por `Production.lot = Box.lot`.
- Outputs finales de esa produccion por producto.
- Recepcion asociada por `pallet.reception_id`.

**Como mostrarlo**

- Bloque de alertas globales separado de la tabla de producciones.
- Agrupacion por `lote + producto`, con kg, cajas y ubicacion principal: stock, venta o reproceso.
- Mensajes claros: "producto en stock no declarado como producido", "lote en stock sin produccion", "caja sin recepcion ni produccion".
- Acciones: abrir busqueda por lote, abrir palet/caja, abrir produccion si existe.

#### Objetivo D: costes incompletos o procedencia de coste poco clara

**Que queremos saber**

Detectar producciones donde el flujo productivo puede estar conciliado, pero no podemos valorar bien el coste porque faltan costes en cajas, outputs o fuentes de coste.

Aqui "trazabilidad" no debe entenderse como destino fisico del producto. El destino ya queda cubierto por conciliacion, stock, venta, reproceso, cajas huerfanas y cierre. En este objetivo hablamos solo de **trazabilidad de coste**: de donde sale el coste de una caja o de un output.

**De donde sale**

- Coste de cajas: recepcion, produccion/lote/producto, coste manual o sin coste.
- Coste de outputs: `ProductionOutput.cost_per_kg`, `total_cost` y desglose de coste.
- Fuentes de outputs: `ProductionOutputSource`, que indica si el coste viene de productos de stock o de outputs padre consumidos.
- Regularizacion: cajas en stock o ventas cuyo `traceable_cost_per_kg` y `manual_cost_per_kg` son nulos.

**Como mostrarlo**

- Subestado por produccion: `Coste completo`, `Coste parcial`, `Sin coste`.
- Mini resumen en la fila: cajas sin coste, kg sin coste, outputs sin coste calculable.
- Aviso separado para outputs sin fuentes de coste o con fuentes insuficientes.
- Enlaces directos a regularizacion de costes, desglose de output o caja/palet afectado.
- No mezclar aqui `stock pendiente`, `cajas huerfanas` o `producto no producido`: esos casos pertenecen a conciliacion/cierre y son alertas operativas, no avisos de coste.

#### Objetivo E: acciones concretas para dejar una produccion cuadrada

**Que queremos saber**

No solo mostrar el problema, sino orientar cual es el siguiente paso operativo.

**De donde sale**

- Codigo y tipo de alerta.
- Entidad afectada: produccion, proceso, producto, palet, caja o pedido.
- Estado del lote: abierto, cerrado, bloqueado o listo para cerrar.

**Como mostrarlo**

- Columna `Siguiente accion` en la tabla.
- Panel lateral con acciones recomendadas ordenadas por impacto.
- Cada alerta debe tener una accion principal: `Completar proceso`, `Revisar output`, `Ubicar caja`, `Resolver pedido`, `Regularizar coste`, `Cerrar produccion`.
- Las acciones deben llevar al usuario al modulo correcto, no resolver todo desde esta pantalla.

---

## 2. Contexto de implementacion actual

El backend ya tiene varias piezas utiles para alimentar esta pantalla:

### Producciones

- `Production` representa el lote de produccion.
- El lote se identifica por `productions.lot`.
- Puede estar:
  - abierto: `opened_at != null` y `closed_at == null`;
  - cerrado definitivamente: `closed_at != null`.
- Una produccion cerrada bloquea cambios sobre cajas, palets y procesos de ese lote.

### Procesos

- `ProductionRecord` representa cada proceso del arbol.
- Puede tener padre mediante `parent_record_id`.
- Debe tener `started_at` y `finished_at` para cierre definitivo.
- Los procesos finales son relevantes porque sus `ProductionOutput` se usan como produccion declarada final.

### Entradas

- `ProductionInput` vincula una caja real (`boxes.id`) con un proceso.
- Una caja usada como input deja de estar disponible.
- El producto, lote y peso de la entrada salen de la caja.

### Salidas

- `ProductionOutput` declara producto producido, cajas y kg.
- Las salidas no crean cajas fisicas por si mismas; despues el stock/venta se concilia por cajas con el mismo lote.
- `ProductionOutputSource` permite trazar de donde viene el coste de una salida:
  - `stock_product`: materia prima consumida desde cajas de stock;
  - `parent_output`: output de un proceso padre consumido por un proceso hijo.

### Stock, cajas y palets

- `Box` contiene `article_id`, `lot`, `gross_weight`, `net_weight`, `manual_cost_per_kg` (columna en BD).
- `Pallet` tiene estados fijos:
  - `registered` = 1;
  - `stored` = 2;
  - `shipped` = 3;
  - `processed` = 4.
- Stock actual suele ser palet `registered` o `stored`, sin pedido.
- Una caja puede provenir de recepcion si su palet tiene `reception_id`.
- Una caja puede provenir de produccion si su coste se resuelve por `ProductionCostResolver` usando `lot + product_id`.

### Costes

El coste de una caja se resuelve asi:

```text
1. Coste trazable por recepcion
2. Coste trazable por produccion/lote/producto
3. Coste manual en boxes.manual_cost_per_kg
4. Sin coste
```

**Nota de implementacion**: `traceable_cost_per_kg` NO es una columna de base de datos. Es un accessor de Eloquent calculado por `ProductionCostResolver::getBoxTraceableCostPerKg($box)`. No se puede usar en un `WHERE` SQL directo. Para detectar cajas sin coste trazable hay que consultar si existe `ProductionCost` para el par `lot + product_id`, o delegar el calculo en `CostRegularizationService` / `ProductionCostResolver` a nivel de lote/producto, no de caja individual.

Esto ya conecta con `docs/inventario/34-regularizacion-costes-cajas.md`.

---

## 3. Propuesta de pantalla

Nombre tentativo:

- **Control de producciones**
- **Salud de producciones**
- **Panel de conciliacion de producciones**
- **Auditoria de lotes**

La opcion mas clara para usuarios operativos probablemente sea **Control de producciones**.

### 3.1 Cabecera de resumen

Mostrar tarjetas compactas con:


| Indicador                       | Utilidad                                                              |
| ------------------------------- | --------------------------------------------------------------------- |
| Producciones abiertas           | Detectar lotes vivos o sin cerrar.                                    |
| Producciones con alertas        | Priorizar trabajo operativo.                                          |
| Producciones no conciliadas     | Ver problemas reales de balance.                                      |
| Stock de lotes de produccion    | Detectar stock pendiente de salida o reproceso.                       |
| Cajas sin origen claro          | Detectar cajas que existen pero no vienen de recepcion ni produccion. |
| Cajas sin coste                 | Enlazar con regularizacion de costes.                                 |
| Producciones listas para cerrar | Accion rapida hacia cierre definitivo.                                |


### 3.2 Lista principal de producciones

Tabla densa y filtrable. Columnas sugeridas:


| Columna      | Detalle                                                              |
| ------------ | -------------------------------------------------------------------- |
| Lote         | `production.lot`, enlaza al detalle/arbol.                           |
| Fecha        | `date` o primer `started_at` raiz si existe.                         |
| Estado       | Abierta, cerrada, lista para cerrar, no cerrable por stock, no conciliada. |
| Especie      | `species`.                                                           |
| Entrada      | Kg y cajas de inputs de stock.                                       |
| Producido    | Kg y cajas de outputs finales.                                       |
| Vendido      | Kg y cajas en palets enviados/pedidos.                               |
| Stock        | Kg y cajas aun en palets `registered/stored`.                        |
| Reprocesado  | Kg y cajas consumidas como input en otra produccion.                 |
| Balance      | `producido - vendido - stock - reprocesado`.                         |
| Conciliacion | `ok`, `warning`, `error`.                                            |
| Coste        | Completo, parcial, sin coste.                                        |
| Acciones     | Ver arbol, ver conciliacion, verificar cierre, abrir regularizacion. |


### 3.3 Panel lateral de alertas y limitaciones

Al seleccionar una produccion, mostrar problemas agrupados por naturaleza, no solo por si bloquean o no el cierre:

- **Alertas graves**: indican datos incoherentes o produccion mal conciliada. Ejemplos: producto vendido no producido, balance con sobrante/faltante, cajas huerfanas.
- **Limitaciones operativas**: impiden cerrar, pero no significan que el dato este mal. Ejemplos: stock restante conciliado, pedido pendiente, palet pendiente de envio.
- **Avisos**: no bloquean todo, pero conviene revisar. Ejemplos: coste incompleto o fuentes de coste parciales.
- **Informativas**: oportunidades de limpieza o seguimiento.

Cada alerta debe tener:


| Campo     | Ejemplo                                                              |
| --------- | -------------------------------------------------------------------- |
| Tipo      | `reconciliation_not_ok`, `stock_without_production`, `missing_cost`. |
| Severidad | Critica, aviso, info.                                                |
| Mensaje   | "Hay 42.5 kg mas contabilizados que producidos".                     |
| Entidad   | Lote, producto, caja, palet, pedido o proceso.                       |
| Accion    | "Ver conciliacion", "Abrir palet", "Regularizar coste".              |


---

## 4. Alertas utiles

### 4.1 Balance no conciliado por producto

Ya existe base en `Production::getDetailedReconciliationByProduct()`.

Detecta por producto:

```text
balance = producido - (vendido + stock + reprocesado)
```

Estados actuales:


| Estado    | Criterio actual aproximado                                     |
| --------- | -------------------------------------------------------------- |
| `ok`      | Balance practicamente 0.                                       |
| `warning` | Diferencia menor o igual al 5%.                                |
| `error`   | Diferencia mayor al 5% o producto contabilizado sin producido. |

**Caso especial**: un balance negativo (mas kg en stock/venta/reproceso que producidos) es siempre `error` independientemente del porcentaje, porque indica producto que fisicamente no existe segun las declaraciones de produccion.


Uso en pantalla:

- Resumen por lote.
- Fila expandible por producto.
- Accion: abrir detalle de conciliacion o arbol de proceso.

### 4.2 Producto contabilizado pero no producido

Caso importante para tu ejemplo:

> Hay cajas con `lot = X` en stock, venta o reproceso, pero no hay `ProductionOutput` final para ese producto en la produccion del lote X.

El metodo de conciliacion ya contempla productos no producidos pero contabilizados y los marca como `error`.

Uso en pantalla:

- Mostrar como alerta critica.
- Explicar si aparece en stock, venta o reproceso.
- Accion: revisar outputs finales o corregir lote/producto de las cajas.

### 4.3 Stock de lote sin produccion asociada

Detectar cajas en stock cuyo `box.lot` no corresponde a ninguna `Production.lot` ni a una recepcion clara.

Consulta conceptual:

```text
cajas en palets inStock
AND box.lot no aparece en productions.lot
AND palet.reception_id IS NULL
```

Uso en pantalla:

- Alerta global, no necesariamente asociada a una produccion concreta.
- Agrupar por `lot + product_id`.
- Acciones:
  - crear/vincular produccion si procede;
  - corregir lote de cajas;
  - asignar coste manual si es un lote historico;
  - revisar creacion manual de palet.

### 4.4 Stock de lote con produccion, pero producto no producido

Variante mas precisa:

```text
box.lot existe en productions.lot
AND box.article_id no aparece como output final de esa produccion
AND la caja no proviene de recepcion
```

Uso:

- Alerta critica dentro de la produccion.
- Es mas accionable que "balance no cuadra", porque apunta a producto/lote concreto.

### 4.5 Cajas huerfanas del lote

Ya existe chequeo en `ProductionClosureService` y **ya devuelve el blocking reason `orphan_box`** en la respuesta de `closure-check`. El panel debe reutilizar este dato directamente en lugar de reimplementar la query.

La logica interna del servicio es equivalente a:

```text
Box::where('lot', production.lot)
  ->whereDoesntHave('productionInputs')
  ->whereDoesntHave('palletBox')
```

Uso:

- Mostrar cajas del lote que no estan en palet ni fueron reprocesadas.
- La fuente de verdad para el panel es el campo `orphan_box` de `blockingReasons` en el endpoint `closure-check`, no una query independiente.
- Accion: ubicar caja en palet, corregir lote o reprocesar.

### 4.6 Stock pendiente de lote que se quiere cerrar

Ya existe en `closure-check` como `stock_remaining`.

Uso:

- Para producciones abiertas antiguas.
- Para producciones con balance 0 pero stock aun pendiente.
- Clasificarlo como **limitacion operativa**, no como alerta grave, cuando el lote esta conciliado.
- Accion: vender, reprocesar, regularizar o mantener abierta.

### 4.7 Pedidos pendientes con palets del lote

Ya existe en `closure-check` como `pending_order`.

Uso:

- Alerta si un lote parece listo pero tiene pedidos `pending` o incidencias abiertas.
- Accion: ir al pedido.

### 4.8 Palets asignados a pedido pero no enviados

Ya existe en `closure-check` como `pallet_not_shipped`.

Uso:

- Evita cerrar lotes con venta a medio camino.
- Accion: revisar pedido/palet.

### 4.9 Procesos incompletos

Ya existe en `closure-check`:

- `no_processes`
- `process_not_started`
- `process_not_finished`
- `final_node_no_outputs`

Uso:

- Alerta operativa para producciones abiertas.
- Accion: completar proceso o registrar outputs.

### 4.10 Cajas sin coste

Conecta con el bloque de regularizacion de costes.

Detectar cajas sin coste implica verificar dos condiciones:

```text
boxes.manual_cost_per_kg IS NULL
AND no existe ProductionCost para (lot + article_id) de la caja
AND palet.reception_id IS NULL (sin coste por recepcion)
```

**Importante**: `traceable_cost_per_kg` es un accessor Eloquent calculado en tiempo de ejecucion por `ProductionCostResolver`, NO una columna de la tabla `boxes`. Cualquier filtro SQL que use `traceable_cost_per_kg` directamente fallara. El endpoint agregado debe calcular la ausencia de coste usando `CostRegularizationService` o `ProductionCostResolver` a nivel de lote/producto, no iterando caja por caja.

Uso:

- Alertar si afecta a stock actual (palets `registered` o `stored`).
- Alertar si afecta a ventas de producciones cerrables (palets `shipped`).
- Los avisos de coste no bloquean el cierre; son informativos para regularizacion.
- Accion: abrir regularizacion por producto/lote.

### 4.11 Outputs sin fuentes de coste suficientes

Para salidas de produccion:

- `ProductionOutput` sin `sources`.
- `ProductionOutputSource` con pesos que no suman el peso del output.
- `stock_product` cuyo `product_id` no existe en inputs del proceso.
- `parent_output` mal encadenado o sin consumo padre.

Parte se valida al guardar, pero la pantalla puede auditar datos historicos o importados.

Uso:

- Aviso de coste/procedencia, no alerta de destino.
- Accion: abrir salida y revisar desglose.

### 4.12 Integridad del lote de produccion

El lote de produccion debe ser unico entre producciones.

El backend ya lo refuerza en dos capas:

- `StoreProductionRequest`: `Rule::unique('tenant.productions', 'lot')`.
- `UpdateProductionRequest`: `Rule::unique('tenant.productions', 'lot')->ignore($productionId)`.
- Migracion `2026_04_20_120000_add_unique_index_to_productions_lot.php`: indice unico `productions_lot_unique`.

Uso:

- No se debe permitir crear dos producciones distintas con el mismo `lot`. El constraint de BD lo impide desde la migracion.
- La pantalla puede asumir que `Production.lot` identifica una unica produccion sin hacer comprobacion adicional.
- La deteccion de "duplicados" mencionada en V2 (seccion 9) solo aplica a tenants con datos anteriores a la migracion. En el resto de tenants es un caso imposible.

---

## 5. Filtros recomendados

Filtros principales:


| Filtro                        | Tipo                                                        |
| ----------------------------- | ----------------------------------------------------------- |
| Rango de fechas               | `dateFrom`, `dateTo`.                                       |
| Estado de produccion          | abierta, cerrada, con cierre posible, con cierre bloqueado. |
| Estado de conciliacion        | ok, warning, error.                                         |
| Severidad de alertas          | critica, aviso, info.                                       |
| Especie                       | `species_id`.                                               |
| Producto                      | `product_id`.                                               |
| Lote                          | busqueda por texto exacta/parcial.                          |
| Tiene stock pendiente         | si/no.                                                      |
| Tiene cajas sin coste         | si/no.                                                      |
| Tiene cajas sin origen        | si/no.                                                      |
| Solo producciones accionables | si/no.                                                      |

**Nota de implementacion**: Los filtros `Tiene cajas sin coste` y `Tiene cajas sin origen` son costosos porque `traceable_cost_per_kg` no es una columna SQL. El endpoint agregado debe pre-calcular estas flags en el servicio (usando `ProductionCostResolver` por lote/producto) y devolverlas como campos booleanos en la respuesta, no como filtros que generen sub-queries por caja.

Ordenes utiles:

- Mas alertas criticas primero.
- Mayor diferencia de balance primero.
- Producciones abiertas mas antiguas primero.
- Mayor stock pendiente primero.
- Mayor peso sin coste primero.

---

## 6. Vistas internas

### 6.1 Vista "Resumen"

Para operaciones:

- Indicadores operativos compactos.
- Top 10 lotes con mas diferencia de balance.
- Top 10 lotes con mas stock pendiente.
- Alertas criticas globales.

### 6.2 Vista "Conciliacion"

Para usuario que corrige datos:

- Tabla por lote y producto.
- Columnas: producido, vendido, stock, reprocesado, balance, estado.
- Expandir para ver palets, cajas, pedidos y procesos implicados.

### 6.3 Vista "Flujo del lote"

Para investigar el recorrido fisico/operativo de un lote. Esta vista ayuda a explicar una conciliacion o una alerta grave, pero no debe confundirse con la trazabilidad de coste del Objetivo D.

- Origen:
  - recepcion;
  - produccion;
  - manual/sin origen.
- Transformaciones:
  - inputs;
  - outputs;
  - consumos padre-hijo.
- Destino:
  - stock;
  - venta;
  - reproceso;
  - huerfano.

### 6.4 Vista "Costes"

Para tecnico/administrador:

- Outputs sin coste calculable.
- Cajas sin coste en stock.
- Cajas vendidas sin coste.
- Enlaces a regularizacion.

---

## 7. Endpoints actuales aprovechables

### Listado de producciones

```http
GET /api/v2/productions
```

Filtros actuales:

- `lot`
- `species_id`
- `status`
- `perPage`

Limitacion:

- No devuelve resumen agregado de conciliacion para toda la lista.
- Para una pantalla global habria que evitar llamar a `GET /productions/{id}` por cada fila.

### Detalle con conciliacion por producto

```http
GET /api/v2/productions/{id}
```

Devuelve `reconciliation` con:

- `products[]`
- `summary.totalProducedWeight`
- `summary.totalContabilizedWeight`
- `summary.totalBalanceWeight`
- `summary.overallStatus`

Tambien existe el endpoint dedicado `GET /api/v2/productions/{id}/reconciliation` (llama a `Production::reconcile()`). Antes de implementar el panel hay que confirmar si `show` y `/reconciliation` usan el mismo metodo interno o devuelven datos distintos, para usar una unica fuente de verdad en el endpoint agregado.

### Arbol de procesos

```http
GET /api/v2/productions/{id}/process-tree
```

Util para abrir detalle visual. Incluye nodos de venta, stock, reprocesados y balance.

### Totales

```http
GET /api/v2/productions/{id}/totals
```

Util para entrada/salida/merma/rendimiento.

### Verificacion de cierre

```http
GET /api/v2/productions/{id}/closure-check
```

Muy util para alertas accionables porque devuelve:

- `canClose`
- `blockingReasons[]`
- `summary`

### Regularizacion de costes

```http
GET /api/v2/cost-regularization/stock/missing-cost-boxes
```

Util para la vista de costes y para alertas por cajas sin coste.

---

## 8. Endpoint agregado recomendado

Para que la pantalla sea rapida, conviene crear un endpoint agregado en backend.

Nombre tentativo:

```http
GET /api/v2/productions/control-panel
```

o:

```http
GET /api/v2/production-dashboard
```

**Nota de rutas**: Si se usa `/api/v2/productions/control-panel`, esta ruta debe registrarse en `api.php` **antes** de la ruta `GET /api/v2/productions/{id}`. De lo contrario Laravel interpretara `control-panel` como un ID y devolvera error de modelo. La alternativa `/api/v2/production-dashboard` evita este riesgo por completo.

### 8.1 Respuesta orientativa

```json
{
  "summary": {
    "openProductions": 12,
    "closedProductions": 80,
    "boxesWithoutCost": 38
  },
  "productions": [
    {
      "id": 45,
      "lot": "LOT-2026-0045",
      "date": "2026-04-29",
      "status": "not_reconciled",
      "species": { "id": 1, "name": "Merluza" },
      "metrics": {
        "inputWeightKg": 1000.0,
        "producedWeightKg": 820.0,
        "salesWeightKg": 500.0,
        "stockWeightKg": 200.0,
        "reprocessedWeightKg": 100.0,
        "balanceWeightKg": 20.0
      },
      "reconciliation": {
        "status": "warning",
        "productsOk": 3,
        "productsWarning": 1,
        "productsError": 0
      },
      "closure": {
        "canClose": false,
        "blockingReasons": ["stock_remaining", "reconciliation_not_ok"]
      },
      "costs": {
        "hasMissingCosts": true,
        "missingCostBoxesCount": 4,
        "missingCostWeightKg": 55.2
      },
      "alerts": [
        {
          "severity": "warning",
          "code": "reconciliation_not_ok",
          "message": "Faltan 20 kg por contabilizar.",
          "action": null
        },
        {
          "severity": "info",
          "code": "missing_cost",
          "message": "Hay 4 cajas (55.2 kg) sin coste trazable conocido.",
          "action": null
        }
      ]
    }
  ],
  "pagination": {
    "currentPage": 1,
    "perPage": 25,
    "total": 90,
    "lastPage": 4
  }
}
```

### 8.2 Por que no usar solo endpoints actuales

Se podria hacer una primera version llamando a:

- `GET /productions`
- `GET /productions/{id}`
- `GET /productions/{id}/closure-check`
- endpoints de costes

Pero para una tabla con 25-50 filas generaria demasiadas llamadas y calculos repetidos. Un endpoint agregado permite:

- calcular alertas en SQL/servicio;
- paginar correctamente;
- ordenar por severidad o balance;
- devolver solo lo necesario para la pantalla.

---

## 9. Priorizacion para primera version

### V1: util y realista

Incluir:

- Listado de producciones con estado, lote, fecha, especie.
- Resumen de conciliacion por produccion.
- Balance total por produccion.
- Alertas de `closure-check`.
- Filtros por estado, lote, especie, conciliacion.
- Acciones hacia detalle, arbol, conciliacion y cierre.

Requiere:

- Endpoint agregado o ampliar `GET /productions` con campos calculados opcionales.

### V2: auditoria de lotes y stock

Incluir:

- Lotes en stock sin produccion ni recepcion.
- Productos en stock de lote producido pero no declarados como output.
- Cajas huerfanas (reutilizar `orphan_box` de `closure-check`; no reimplementar).
- Deteccion de lotes duplicados solo en tenants con datos previos a la migracion `2026_04_20` (en el resto es imposible por constraint unico en BD).

Requiere:

- Servicio nuevo de auditoria de lotes/cajas.

### V3: costes

Incluir:

- Cajas sin coste por produccion/lote.
- Outputs sin fuentes de coste coherentes.
- Enlaces a regularizacion por producto/lote.

Requiere:

- Reutilizar `CostRegularizationService`.
- Exponer agregados por lote y produccion.

---

## 10. Decisiones cerradas

1. La pantalla debe vivir dentro del modulo de Produccion.
   - Puede enlazar a Inventario o Costes cuando una alerta requiera resolver palets, cajas o regularizaciones.
2. El usuario principal de esta primera version es operaciones.
   - Prioridad: acciones concretas, detalle operativo y resolucion de incidencias.
   - No se plantea por ahora una vista especifica para direccion.
3. Las diferencias pequenas de balance se muestran como aviso serio.
   - `warning` (diferencia <= 5%) sigue bloqueando el cierre definitivo.
   - En la pantalla no debe presentarse como error tecnico, sino como incidencia operativa a revisar.
   - Los avisos de **coste** (cajas sin coste, outputs sin fuentes) NO bloquean el cierre. Son informativos para regularizacion posterior.
4. Los lotes de produccion no pueden duplicarse.
   - `Production.lot` es unico por tenant.
   - La pantalla puede usar el lote como identificador funcional de una unica produccion.
   - Si aparecen duplicados, se consideran error de datos/migracion y no caso soportado.
5. Definicion inicial de "sin origen".
   - Sin recepcion: el palet no tiene `reception_id`.
   - Sin produccion: no existe `Production.lot = box.lot` con output final para `box.article_id` (output de nodo final del arbol de procesos).
   - Sin coste trazable: no existe `ProductionCost` para `(lot + article_id)` de la caja, el palet no tiene `reception_id` y `boxes.manual_cost_per_kg` es null. No usar `traceable_cost_per_kg` directamente en SQL: es un accessor calculado, no una columna.

---

## 11. Implementacion tecnica (completada)

Los siguientes artefactos han sido creados e integrados en el proyecto:

| Artefacto | Ruta |
| --------- | ---- |
| Servicio principal | `app/Services/Production/ProductionControlPanelService.php` |
| Controlador principal | `app/Http/Controllers/v2/ProductionControlPanelController.php` |
| Form Request principal | `app/Http/Requests/v2/IndexProductionControlPanelRequest.php` |
| Servicio lotes huerfanos | `app/Services/Production/OrphanStockService.php` |
| Controlador lotes huerfanos | `app/Http/Controllers/v2/OrphanStockController.php` |
| Form Request lotes huerfanos | `app/Http/Requests/v2/IndexOrphanStockRequest.php` |

Ambas rutas estan registradas en `routes/api.php` **antes** del `Route::apiResource('productions', ...)` para evitar colision con el parametro `{id}`:

```php
Route::get('productions/control-panel', [ProductionControlPanelController::class, 'index'])->name('productions.controlPanel');
Route::get('productions/orphan-stock',  [OrphanStockController::class, 'index'])->name('productions.orphanStock');
```

Ambos endpoints requieren permiso `viewAny` sobre el modelo `Production`.

---

## 12. Guia de implementacion frontend

Esta seccion documenta el contrato exacto del endpoint implementado. Todo lo que el frontend necesita saber para construir la pantalla sin hacer suposiciones.

---

### 12.1 Endpoints y autenticacion

El panel de control se sirve desde **dos endpoints independientes**:

| Endpoint | Proposito |
| -------- | --------- |
| `GET /api/v2/productions/control-panel` | Lista paginada de producciones con metricas, reconciliacion, cierre y costes. Tambien devuelve un resumen global del tenant. |
| `GET /api/v2/productions/orphan-stock` | Lista paginada de lotes en stock sin produccion ni recepcion, con desglose por palet y producto. |

**Headers obligatorios (ambos endpoints):**

| Header          | Valor                      |
| --------------- | -------------------------- |
| `X-Tenant`      | subdominio del tenant      |
| `Authorization` | `Bearer {sanctum_token}`   |
| `Accept`        | `application/json`         |

**Autorizacion**: el usuario debe tener el permiso `viewAny` sobre el modelo `Production`. Si no lo tiene, la respuesta es `403 Forbidden`.

---

### 12.2 Parametros de peticion

Todos los parametros van en la query string. Ninguno es obligatorio.

| Parametro               | Tipo    | Default  | Valores validos                     | Descripcion                                      |
| ----------------------- | ------- | -------- | ----------------------------------- | ------------------------------------------------ |
| `lot`                   | string  | —        | max 255 chars                       | Busqueda parcial por texto (`LIKE %valor%`).     |
| `species_id`            | integer | —        | ID existente en `species`           | Filtra por especie.                              |
| `status`                | string  | —        | `open` \| `closed`                  | `open` = abierta; `closed` = cerrada.            |
| `date_from`             | date    | —        | formato `YYYY-MM-DD`                | Inicio del rango de fecha de produccion.         |
| `date_to`               | date    | —        | formato `YYYY-MM-DD`, >= `date_from`| Fin del rango de fecha de produccion.            |
| `reconciliation_status` | string  | —        | `ok` \| `warning` \| `error`        | Validado pero **no aplicado en SQL en V1**. Ver nota. |
| `per_page`              | integer | `25`     | 1–50                                | Filas por pagina.                                |
| `sort_by`               | string  | `id`     | `id` \| `date` \| `lot`             | Campo de ordenacion.                             |
| `sort_dir`              | string  | `desc`   | `asc` \| `desc`                     | Direccion de ordenacion.                         |
| `page`                  | integer | `1`      | >= 1                                | Pagina solicitada (paginacion estandar Laravel). |

> **Nota V1**: el filtro `reconciliation_status` es validado por el backend pero NO aplicado como clausula SQL. El backend devuelve la pagina completa segun los otros filtros y el frontend debe filtrar `productions[]` por `reconciliation.status` en cliente si usa este filtro. Esto cambiara en V2.

**Ejemplo de peticion:**

```
GET /api/v2/productions/control-panel?status=open&sort_by=date&sort_dir=desc&per_page=25&page=1
```

---

### 12.3 Estructura de respuesta completa

```jsonc
{
  "message": "Panel de control de producciones obtenido correctamente.",
  "data": {

    // ── RESUMEN GLOBAL ────────────────────────────────────────────────────
    // Refleja el estado de TODO el tenant, independientemente de los filtros activos.
    "summary": {
      "openProductions": 12,    // int: producciones con opened_at != null y closed_at = null
      "closedProductions": 80,  // int: producciones con closed_at != null
      "boxesWithoutCost": 38    // int: cajas en stock sin manual_cost_per_kg ni recepcion ni ProductionInput (aprox. V1)
    },

    // ── LISTA PAGINADA DE PRODUCCIONES ───────────────────────────────────
    "productions": [
      {
        "id": 45,                              // int
        "lot": "LOT-2026-0045",                // string: identificador unico del lote
        "date": "2026-04-29",                  // string|null: fecha en formato YYYY-MM-DD
        "status": "not_reconciled",            // string enum: ver 12.4
        "species": {                           // objeto|null: null si la produccion no tiene especie
          "id": 1,
          "name": "Merluza"
        },

        // Metricas de peso (todos los valores en kg, 3 decimales, float)
        "metrics": {
          "inputWeightKg": 1000.0,             // kg de inputs de stock (materia prima de stock)
          "producedWeightKg": 820.0,           // kg declarados como outputs de nodos finales
          "salesWeightKg": 500.0,              // kg en palets enviados (pedidos finalizados)
          "stockWeightKg": 200.0,              // kg en palets en stock (registered/stored sin pedido)
          "reprocessedWeightKg": 100.0,        // kg de cajas del lote usadas como input en otro proceso
          "balanceWeightKg": 20.0              // = producido - vendido - stock - reprocesado
        },

        // Conciliacion por producto
        "reconciliation": {
          "status": "warning",                 // string enum: ok | warning | error
          "productsOk": 3,                     // int: productos con balance ok
          "productsWarning": 1,                // int: productos con diferencia <= 5%
          "productsError": 0                   // int: productos con diferencia > 5% o no producidos
        },

        // Estado de cierre
        "closure": {
          "canClose": false,                           // bool: true si se puede cerrar ahora mismo
          "blockingReasons": ["stock_remaining"]        // string[]: codigos que impiden el cierre; ver 12.4
        },

        // Estado de coste (aproximacion V1)
        "costs": {
          "hasMissingCosts": true,             // bool
          "missingCostBoxesCount": 4,          // int: cajas sin coste conocido
          "missingCostWeightKg": 55.2          // float: kg sin coste conocido
        },

        // Alertas de esta produccion (ver 12.4 para todos los codigos posibles)
        // Puede ser array vacio [].
        "alerts": [
          {
            "severity": "warning",             // string enum: critical | warning | info
            "code": "reconciliation_not_ok",   // string enum: ver 12.4
            "message": "Faltan 20.0 kg por contabilizar.",
            "action": null                     // string|null: descripcion de la accion recomendada (texto, no ruta)
          },
          {
            "severity": "info",
            "code": "missing_cost",
            "message": "Hay 4 cajas (55.2 kg) sin coste trazable conocido.",
            "action": null
          }
        ]
      }
    ],

    // ── PAGINACION ───────────────────────────────────────────────────────
    "pagination": {
      "currentPage": 1,    // int
      "perPage": 25,       // int
      "total": 90,         // int: total de producciones que cumplen los filtros
      "lastPage": 4        // int: ultima pagina disponible
    }

  }
}
```

---

### 12.4 Enums y valores posibles

#### `status` de la produccion

Jerarquia de prioridad aplicada por el backend (solo se asigna el primer valor que aplica):

| Valor            | Condicion                                                          | Descripcion operativa                                  |
| ---------------- | ------------------------------------------------------------------ | ------------------------------------------------------ |
| `closed`         | `closed_at != null`                                                | Produccion cerrada definitivamente.                    |
| `not_reconciled` | `reconciliation.status` es `warning` o `error`                     | Hay diferencia de balance. Requiere correccion.        |
| `ready_to_close` | `closure.canClose = true`                                          | Todo en orden. Se puede cerrar ahora.                  |
| `not_closeable`  | `closure.blockingReasons` no vacio y conciliacion `ok`             | Conciliada pero hay limitacion operativa (stock, pedido...). |
| `open`           | Abierta sin problemas de conciliacion ni bloqueos de cierre        | En progreso normal.                                    |

#### `reconciliation.status`

| Valor     | Criterio                                                            |
| --------- | ------------------------------------------------------------------- |
| `ok`      | Balance de todos los productos practicamente a cero.                |
| `warning` | Algun producto con diferencia <= 5% del producido.                  |
| `error`   | Algun producto con diferencia > 5%, o producto contabilizado pero no producido (balance negativo). |

#### `severity` de una alerta

| Valor      | Significado                                                        |
| ---------- | ------------------------------------------------------------------ |
| `critical` | Datos incoherentes o error grave. Requiere correccion.             |
| `warning`  | Limitacion operativa o diferencia de balance. Impide el cierre.    |
| `info`     | Aviso no bloqueante. Conveniente revisar pero no urgente.          |

#### `code` de alertas por produccion

Todos los codigos posibles que pueden aparecer en `productions[].alerts[].code`:

| Codigo                  | Severity  | Origen              | Descripcion                                                   |
| ----------------------- | --------- | ------------------- | ------------------------------------------------------------- |
| `reconciliation_not_ok` | `critical` o `warning` | Conciliacion | Balance con diferencia. `critical` si `reconciliation.status = error`, `warning` si `warning`. |
| `no_processes`          | `warning`  | Closure check       | La produccion no tiene ningun proceso registrado.             |
| `process_not_started`   | `warning`  | Closure check       | Un proceso del arbol no tiene fecha de inicio.                |
| `process_not_finished`  | `warning`  | Closure check       | Un proceso del arbol no tiene fecha de fin.                   |
| `final_node_no_outputs` | `warning`  | Closure check       | Un nodo final del arbol no tiene outputs declarados.          |
| `pending_order`         | `warning`  | Closure check       | Hay un pedido pendiente o con incidencia abierta con palets del lote. |
| `pallet_not_shipped`    | `warning`  | Closure check       | Un palet esta asignado a un pedido pero no esta enviado.      |
| `stock_remaining`       | `warning`  | Closure check       | Quedan palets en stock sin pedido de este lote.               |
| `orphan_box`            | `warning`  | Closure check       | Una caja del lote no esta en ningun palet ni fue reprocesada. |
| `missing_cost`          | `info`     | Cost status         | Hay cajas sin coste manual ni de recepcion (aproximacion V1). |

> Los codigos `already_closed` y `not_open` no aparecen en `alerts[]` porque son estados, no incidencias de una produccion activa. Si `closure.blockingReasons` los contiene, la produccion ya estara en `status: closed` o sera un dato inconsistente.

---

### 12.5 Mapeo visual recomendado

#### Badges de `status`

| Valor            | Color sugerido | Etiqueta            |
| ---------------- | -------------- | ------------------- |
| `closed`         | Gris           | Cerrada             |
| `not_reconciled` | Rojo           | No conciliada       |
| `ready_to_close` | Verde          | Lista para cerrar   |
| `not_closeable`  | Naranja        | No cerrable         |
| `open`           | Azul           | Abierta             |

#### Semaforo de `reconciliation.status`

| Valor     | Color   | Icono sugerido |
| --------- | ------- | -------------- |
| `ok`      | Verde   | check          |
| `warning` | Amarillo| triangle-alert |
| `error`   | Rojo    | circle-x       |

#### Severidad de alertas

| Valor      | Color   | Icono sugerido |
| ---------- | ------- | -------------- |
| `critical` | Rojo    | flame / siren  |
| `warning`  | Naranja | triangle-alert |
| `info`     | Azul    | info-circle    |

#### Columna `Balance`

- `balanceWeightKg > 0`: positivo (sobra producto). Color **naranja** si `warning`, **rojo** si `error`.
- `balanceWeightKg < 0`: negativo (falta producto, mas contabilizado que producido). Siempre **rojo** (`error`).
- `balanceWeightKg = 0`: verde o neutro.
- Mostrar siempre con signo: `+20.5 kg` / `-8.3 kg` / `0 kg`.

#### Columna `Coste`

| Condicion                  | Etiqueta       | Color  |
| -------------------------- | -------------- | ------ |
| `costs.hasMissingCosts = false` | Coste ok  | Verde  |
| `costs.hasMissingCosts = true`  | Coste parcial | Naranja |
| `costs.missingCostBoxesCount = total de cajas` | Sin coste | Rojo |

> En V1 no hay campo `totalBoxes` en la respuesta para calcular el ultimo caso. Mostrar siempre "Coste parcial" cuando `hasMissingCosts = true`.

---

### 12.6 Panel lateral por produccion

Cuando el usuario selecciona una fila, mostrar un panel lateral con las alertas agrupadas por naturaleza. La fuente de datos es `productions[i].alerts[]`.

**Agrupacion recomendada:**

1. **Alertas graves** (`severity: critical`) — datos incoherentes que requieren correccion.
2. **Limitaciones operativas** (`severity: warning`, codigos: `stock_remaining`, `pending_order`, `pallet_not_shipped`) — el dato esta bien pero no se puede cerrar.
3. **Problemas de proceso** (`severity: warning`, codigos: `no_processes`, `process_not_started`, `process_not_finished`, `final_node_no_outputs`, `orphan_box`) — el arbol de procesos esta incompleto.
4. **Avisos de coste** (`severity: info`) — no bloquean, conviene revisar.

**Estructura de cada alerta en el panel:**

```
[icono severity] [message]
[boton accion principal]   ← ver 12.7
```

**Accion principal por produccion segun `status`:**

| `status`          | Accion principal visible en el panel |
| ----------------- | ------------------------------------ |
| `not_reconciled`  | "Ver conciliacion"                   |
| `ready_to_close`  | "Cerrar produccion"                  |
| `not_closeable`   | Primera alerta de tipo `warning`     |
| `open`            | "Ver arbol de procesos"              |
| `closed`          | "Ver detalle"                        |

---

### 12.7 Acciones y navegacion

Cada alerta del panel lateral debe llevar al usuario al modulo correcto. El campo `alert.action` en la respuesta es texto descriptivo (no una ruta); la ruta la construye el frontend con los datos del objeto.

#### Alertas por produccion

| Codigo                  | Modulo destino          | Datos disponibles en la alerta          | Ruta sugerida                          |
| ----------------------- | ----------------------- | --------------------------------------- | -------------------------------------- |
| `reconciliation_not_ok` | Conciliacion            | `production.id`                         | `/producciones/{id}` (tab conciliacion)|
| `no_processes`          | Arbol de procesos       | `production.id`                         | `/producciones/{id}` (tab arbol)       |
| `process_not_started`   | Proceso concreto        | `alert.recordId` (en campo `action`)    | `/production-records/{recordId}`       |
| `process_not_finished`  | Proceso concreto        | `alert.recordId` (en campo `action`)    | `/production-records/{recordId}`       |
| `final_node_no_outputs` | Proceso concreto        | `alert.recordId` (en campo `action`)    | `/production-records/{recordId}`       |
| `pending_order`         | Pedido                  | `alert.orderId` (en campo `action`)     | `/pedidos/{orderId}`                   |
| `pallet_not_shipped`    | Palet                   | `alert.palletId` (en campo `action`)    | `/palets/{palletId}`                   |
| `stock_remaining`       | Stock del lote          | `production.lot`                        | `/inventario?lot={lot}`                |
| `orphan_box`            | Caja                    | `alert.boxId` (en campo `action`)       | `/cajas/{boxId}`                       |
| `missing_cost`          | Regularizacion de costes| `production.lot`                        | `/costes/regularizacion?lot={lot}`     |

> **Nota sobre IDs en alertas**: los campos `recordId`, `orderId`, `palletId`, `boxId` estan incluidos en el texto del campo `action` de la respuesta pero NO como campos estructurados. En V1 el frontend los extrae del campo `message` o navega a la produccion general. En V2 se añadiran como campos dedicados en la respuesta de alerta.

---

### 12.8 Paginacion y filtros

#### Paginacion

```
GET /api/v2/productions/control-panel?page=2&per_page=25
```

- Usar `pagination.total` para mostrar el contador total ("90 producciones").
- Usar `pagination.lastPage` para construir los controles de pagina.
- El parametro `page` lo lee Laravel automaticamente de la query string.
- Los filtros se mantienen entre paginas: todos los parametros deben estar presentes en cada peticion.

#### Comportamiento de cada filtro

| Filtro          | Comportamiento en backend                                          |
| --------------- | ------------------------------------------------------------------ |
| `lot`           | SQL `LIKE %valor%` sobre `productions.lot`.                        |
| `species_id`    | SQL `WHERE species_id = ?`.                                        |
| `status=open`   | SQL `WHERE opened_at IS NOT NULL AND closed_at IS NULL`.           |
| `status=closed` | SQL `WHERE closed_at IS NOT NULL`.                                 |
| `date_from`     | SQL `WHERE DATE(date) >= ?`.                                       |
| `date_to`       | SQL `WHERE DATE(date) <= ?`.                                       |
| `reconciliation_status` | Validado, **NO filtrado en SQL**. El frontend filtra `productions[]` en cliente. |
| `sort_by`       | Ordena por la columna indicada. Default: `id`.                     |
| `sort_dir`      | `asc` o `desc`. Default: `desc`.                                   |

#### Contadores del `summary`

El `summary` siempre refleja el estado global del tenant, **independientemente de los filtros activos**. No cambia al filtrar la tabla. Es un indicador de salud global, no un agregado de la pagina visible.

---

### 12.9 Casos limite

| Situacion                              | Comportamiento de la respuesta                                   |
| -------------------------------------- | ---------------------------------------------------------------- |
| Sin producciones con los filtros       | `productions: []`, `pagination.total: 0`, `summary` se mantiene.|
| Produccion sin especie                 | `species: null`.                                                 |
| Produccion sin fecha declarada         | `date: null`.                                                    |
| Produccion recien creada sin procesos  | `metrics` todos a `0.0`, `reconciliation.status: ok`, `closure.blockingReasons: ["no_processes"]`, `status: not_closeable`. |
| Produccion cerrada con alertas         | `status: closed` aunque haya alertas historicas en `alerts[]`. El badge de cierre tiene prioridad. |
| `balanceWeightKg` positivo             | Sobra producto (producido > contabilizado). Naranja si `warning`, rojo si `error`. |
| `balanceWeightKg` negativo             | Falta producto (mas contabilizado que producido). Siempre `error`. Mostrar en rojo. |
| `costs.hasMissingCosts = false`        | No hay alerta `missing_cost` en `alerts[]`. La columna Coste muestra "Coste ok". |

---

### 12.10 Notas de implementacion para el equipo frontend

1. **No hacer llamadas adicionales por fila.** El endpoint agrega todo lo necesario para la tabla y el panel lateral. No llamar a `/productions/{id}` ni a `/closure-check` por cada fila.

2. **`summary` es global, no paginado.** Contiene tres contadores del tenant (`openProductions`, `closedProductions`, `boxesWithoutCost`). Mostrarlos como tarjetas fijas en la cabecera. No cambian al filtrar la tabla ni al cambiar de pagina. Para el conteo de lotes huerfanos usar `pagination.total` del endpoint `orphan-stock`.

3. **El filtro `reconciliation_status` es solo de cliente en V1.** Si el usuario filtra por estado de conciliacion, aplicarlo sobre el array `productions` en memoria o desactivar ese filtro hasta V2.

4. **Los IDs en alertas de proceso/pedido/palet/caja** estan en el campo `message` como texto. Para extraer `orderId`, `palletId`, `boxId` o `recordId`, parsear el mensaje o navegar a la produccion general. En V2 se añaden como campos estructurados.

5. **El estado `not_closeable` puede coexistir con alertas de tipo `info`.** Render el badge naranja aunque solo haya `missing_cost` en `alerts[]`; el bloqueo lo determina `closure.blockingReasons`, no la severidad de las alertas.

6. **El estado `ready_to_close` es accionable.** Cuando `status = ready_to_close` mostrar boton "Cerrar produccion" que llama a `POST /api/v2/productions/{id}/close` con `{ reason: "..." }`. Tras el cierre, invalidar la cache del panel y recargar.

7. **Los contadores `productsOk + productsWarning + productsError` suman el total de productos distintos de la produccion.** Si los tres son 0, la produccion no tiene outputs declarados.

8. **`inputWeightKg = 0` y `producedWeightKg = 0`** son habituales en producciones recien creadas. No tratar como error.

9. **El campo `closure.blockingReasons` es un array de strings** (codigos), no objetos. Para obtener el mensaje completo de cada razon hay que buscarlo en `alerts[]` por el mismo `code`.

---

## 13. Endpoint de lotes huerfanos en stock

Los lotes que tienen cajas en stock pero no pertenecen a ninguna produccion ni vienen de una recepcion se sirven desde un endpoint dedicado. El card de "Lotes huerfanos" en la cabecera del panel usa `pagination.total` de este endpoint como contador.

---

### 13.1 Endpoint y parametros

```
GET /api/v2/productions/orphan-stock
```

**Headers obligatorios**: identicos a los del endpoint principal (ver 12.1).

**Autorizacion**: permiso `viewAny` sobre el modelo `Production`. Respuesta `403` si no se tiene.

**Parametros de query string** (todos opcionales):

| Parametro  | Tipo    | Default | Valores validos       | Descripcion                                    |
| ---------- | ------- | ------- | --------------------- | ---------------------------------------------- |
| `lot`      | string  | —       | max 255 chars         | Busqueda parcial por texto (`LIKE %valor%`).   |
| `per_page` | integer | `25`    | 1–100                 | Lotes por pagina.                              |
| `sort_dir` | string  | `asc`   | `asc` \| `desc`       | Orden alfabetico del lote.                     |
| `page`     | integer | `1`     | >= 1                  | Pagina solicitada.                             |

**Definicion de "lote huerfano"**: lote cuyas cajas estan en palets con estado `registered` (1) o `stored` (2), sin `reception_id` en el palet, y cuyo valor de `lot` no aparece en ninguna fila de `productions.lot`.

---

### 13.2 Estructura de respuesta completa

```jsonc
{
  "message": "Lotes huerfanos en stock obtenidos correctamente.",
  "data": {

    // ── LISTA PAGINADA DE LOTES ───────────────────────────────────────────
    // La pagina contiene exactamente per_page lotes distintos (o menos en la ultima pagina).
    "lots": [
      {
        "lot": "L-2026-001",          // string: el lote (raiz de agrupacion)
        "totalWeightKg": 245.6,       // float (3 dec): kg totales del lote en stock
        "totalBoxes": 18,             // int: total de cajas del lote en stock
        "totalPallets": 3,            // int: numero de palets distintos que contienen el lote

        // Palets que contienen cajas de este lote
        "pallets": [
          {
            "id": 101,                // int: pallet.id
            "status": 2,              // int: 1 = registered, 2 = stored
            "statusLabel": "Almacenado",  // string: etiqueta legible del estado
            "location": "Camara 1 / A-03",  // string|null: "Almacen / Posicion" o solo "Almacen"; null si no tiene ubicacion
            "createdAt": "2026-03-15T10:22:00.000000Z",  // string: timestamp ISO 8601 de creacion del palet
            "weightKg": 120.0,        // float (3 dec): kg de cajas de ESTE lote en este palet
            "boxesCount": 9,          // int: cajas de ESTE lote en este palet

            // Desglose por producto dentro del palet
            "products": [
              {
                "id": 12,             // int: product.id (article_id de la caja)
                "name": "Merluza congelada 400-600",  // string: product.name
                "weightKg": 80.0,     // float (3 dec): kg de este producto en este palet
                "boxes": 6            // int: cajas de este producto en este palet
              },
              {
                "id": 15,
                "name": "Merluza congelada 600-800",
                "weightKg": 40.0,
                "boxes": 3
              }
            ]
          }
        ]
      }
    ],

    // ── PAGINACION ───────────────────────────────────────────────────────
    // La paginacion es por LOTE, no por fila. Un lote con varios palets cuenta como 1.
    "pagination": {
      "currentPage": 1,    // int
      "perPage": 25,       // int
      "total": 4,          // int: total de lotes huerfanos distintos (usar para el card de cabecera)
      "lastPage": 1        // int: ultima pagina disponible
    }

  }
}
```

---

### 13.3 Notas de implementacion para el equipo frontend

1. **La unidad de paginacion es el lote, no la fila.** Un lote con 3 palets y 12 productos ocupa 1 entrada en `lots[]` y consume 1 unidad del `per_page`. Esto significa que el numero de elementos del array `lots[]` nunca supera `per_page`, pero cada elemento puede tener N palets anidados.

2. **`pagination.total` es el numero de lotes huerfanos distintos.** Usarlo en el card de cabecera del panel de control ("X lotes sin produccion en stock"). No mezclar con `totalBoxes` ni `totalWeightKg`.

3. **`location` puede ser `null`.** Un palet con estado `registered` que aun no tiene posicion de almacen devuelve `location: null`. Mostrar "Sin ubicar" o similar.

4. **`statusLabel` esta en castellano.** Valores posibles: `"Registrado"` (status 1) y `"Almacenado"` (status 2). El campo `status` (int) permite comparar sin depender del idioma.

5. **`createdAt` es la fecha de creacion del palet**, no la fecha en que las cajas llegaron al lote. Es la mejor aproximacion disponible en V1 para saber desde cuando existe ese stock.

6. **Estructura de tabla recomendada**: tabla de primer nivel por lote (lot, totalWeightKg, totalBoxes, totalPallets), expandible para mostrar la sublista de palets, y a su vez expandible por palet para ver el desglose de productos.

7. **Accion principal por lote**: "Abrir inventario filtrado por lote" → `/inventario?lot={lot}`. No hay endpoint dedicado de detalle por lote en V1; la pantalla de inventario es el destino natural.

8. **El endpoint no devuelve alertas ni codigos de severidad.** Los lotes huerfanos son per se una anomalia; el frontend puede mostrarlos siempre con un icono de aviso sin necesitar un campo `severity` en la respuesta.
