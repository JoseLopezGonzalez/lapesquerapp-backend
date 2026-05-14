# Production tree filtrado por cliente o pedido

## Objetivo

Permitir visualizar el mismo `production tree` que actualmente se muestra para un lote de producción, pero filtrado por un cliente o por un pedido concreto.

La vista filtrada no debe ser una trazabilidad inversa ni un árbol conceptual distinto. Debe mantener la misma dirección y estructura lógica del árbol normal del lote:

```text
lote -> procesos -> outputs finales -> expedición/venta
```

La diferencia es que solo deben aparecer los nodos, productos, cantidades y métricas que afecten a la parte del lote finalmente expedida para el cliente o pedido seleccionado.

## Punto de partida actual

El endpoint actual del árbol completo es:

```http
GET /api/v2/productions/{id}/process-tree
```

La respuesta se genera aproximadamente así:

1. Se localiza la producción por `id`.
2. Se construye el árbol de procesos raíz e hijos.
3. Cada `ProductionRecord` se transforma a estructura de nodo.
4. Después se añaden nodos derivados de salida: `sales`, `stock`, `reprocessed` y `balance`.
5. Se calculan totales globales del lote.

La venta actual se infiere desde palets vinculados a pedidos y cajas del lote. Para la vista filtrada interesa restringir ese conjunto a un cliente o pedido.

## Alcance funcional

La vista filtrada debe soportar, como mínimo:

- Filtrar por `customerId`.
- Filtrar por `orderId`.
- Mantener el mismo formato general del tree para que el frontend pueda reutilizar el render actual.
- Mostrar solo ramas relevantes.
- Mostrar solo productos relevantes dentro de cada nodo.
- Mostrar solo pedidos/clientes relevantes dentro de nodos `sales`.
- Recalcular cantidades y métricas según la parte filtrada.

## Definición de "relevante"

Un dato del lote es relevante para el filtro si contribuye a productos finales que aparecen en cajas expedidas para el cliente o pedido seleccionado.

Para pedido:

```text
producción/lote + orderId -> cajas expedidas de ese lote en ese pedido
```

Para cliente:

```text
producción/lote + customerId -> pedidos expedidos de ese cliente -> cajas expedidas de ese lote
```

La condición de expedición debería ser estricta:

- Palet con `order_id`.
- Palet en estado `shipped`.
- Pedido en estado `finished` o `incident`.
- Caja perteneciente al lote de la producción.
- Caja no consumida como input de otra producción.

Nota: un pedido en estado `incident` cuenta como expedido. El estado `incident` refleja que hubo una incidencia, pero no invalida la expedición si los palets correspondientes están enviados.

## Comportamiento esperado del árbol filtrado

### Nodos de proceso

Los nodos de proceso deben mantenerse si tienen alguna contribución al filtro. Si un proceso no aporta ningún producto final expedido al cliente/pedido, debe ocultarse.

Si un nodo produce varios productos pero solo algunos afectan al filtro, el nodo puede permanecer, pero sus `outputs`, cantidades y métricas deben reflejar solo los productos relevantes.

### Nodos finales

Un nodo final debe mostrarse solo si alguno de sus outputs coincide con productos expedidos al cliente/pedido dentro del lote.

Si el nodo final produce productos adicionales no incluidos en el filtro, esos productos no deben aparecer en la vista filtrada.

### Nodo `sales`

El nodo `sales` debe mostrar únicamente:

- El pedido filtrado, si el filtro es por `orderId`.
- Los pedidos del cliente seleccionado, si el filtro es por `customerId`.
- Los productos del lote realmente expedidos dentro de esos pedidos.
- Los palets/cajas correspondientes.
- Importes, costes y márgenes recalculados sobre esas cajas.

### Nodos `stock`, `reprocessed` y `balance`

Para la primera versión conviene no mezclarlos en la vista filtrada, salvo decisión explícita.

Motivo: el objetivo es responder "qué parte del lote corresponde a este cliente/pedido expedido". Stock, reprocesado y balance son contexto global del lote y pueden confundir si aparecen junto a una vista parcial.

Opciones a valorar:

- Opción A: ocultarlos siempre en vista filtrada.
- Opción B: mostrarlos solo si afectan directamente a productos incluidos en el filtro.
- Opción C: mantenerlos como contexto, pero marcados como información global no filtrada.

La opción recomendada inicialmente es A.

## Cálculo de cantidades

El reto principal es recalcular cantidades hacia dentro del mismo árbol sin cambiar su dirección.

Ejemplo:

```text
Lote completo:
- Nodo final produce 100 kg de producto A.
- Cliente X recibe 25 kg de producto A.

Vista filtrada:
- Ese nodo final debe mostrar 25 kg de producto A.
- Las cantidades de inputs, costes y mermas asociadas a esa rama deben ajustarse a esa porción.
```

Si existe trazabilidad exacta caja -> output de producción, se puede calcular de forma exacta.

Si no existe esa relación y solo se dispone de lote + producto, la propuesta razonable es prorratear:

```text
factor = kg expedidos filtrados del producto / kg producidos del producto en el nodo final
```

Ese factor se usaría para ajustar:

- `weightKg`
- `boxes`, cuando sea posible o tenga sentido
- `totalInputWeight`
- `totalOutputWeight`
- `totalInputBoxes`
- `totalOutputBoxes`
- costes totales
- mermas y rendimientos
- totales globales de la respuesta filtrada

Los valores unitarios como `costPerKg`, `salePricePerKg` o porcentajes no deberían prorratearse directamente; deberían recalcularse desde importes y kg filtrados.

## Ambigüedad por producto repetido

Caso delicado:

```text
Nodo final 1 produce 60 kg de producto A.
Nodo final 2 produce 40 kg de producto A.
Cliente X recibe 25 kg de producto A.
```

Si no sabemos de qué nodo salieron físicamente esas cajas, hay varias opciones:

- Repartir proporcionalmente entre nodos: 15 kg al nodo 1 y 10 kg al nodo 2.
- Asignar por orden cronológico de producción o salida.
- Exigir trazabilidad más fina antes de ofrecer esta vista como exacta.
- Marcar la respuesta con un indicador de atribución estimada.

Decisión pendiente:

```text
¿Aceptamos prorrateo proporcional cuando el mismo producto aparece en varios nodos finales del lote?
```

## Forma propuesta de API

Mantener el endpoint actual y añadir parámetros opcionales:

```http
GET /api/v2/productions/{id}/process-tree?customerId=123
GET /api/v2/productions/{id}/process-tree?orderId=456
```

Reglas:

- El filtro debe aceptar `customerId` u `orderId`, pero no ambos a la vez en la primera versión.
- Si no se envía ningún filtro, el comportamiento debe ser exactamente el actual.
- Si se envía filtro y no hay cajas expedidas para ese lote, devolver árbol vacío con metadatos claros, no error 500.

Posible metadato adicional, solo si aporta claridad al frontend sin cambiar las claves actuales:

```json
{
  "filter": {
    "type": "customer",
    "customerId": 123,
    "orderId": null,
    "mode": "expedited"
  }
}
```

## Flujo lógico del builder filtrado

1. Construir el árbol completo del lote igual que ahora.
2. Resolver el scope del filtro:
  - pedidos aplicables
  - cajas expedidas
  - productos
  - kg/cajas por producto
  - precios, impuestos, costes y márgenes de venta
3. Determinar qué outputs finales del árbol coinciden con los productos del scope.
4. Calcular factores de participación por producto y nodo final.
5. Podar ramas sin contribución.
6. Filtrar productos dentro de nodos que sí permanecen.
7. Recalcular cantidades, costes, mermas y totales.
8. Añadir nodo `sales` filtrado.
9. Devolver el mismo shape principal de `processNodes` y `totals`.

## Reglas de autorización

Debe mantenerse la autorización actual de `ProductionPolicy::view`.

Además, si se filtra por pedido o cliente, habría que valorar:

- Si el usuario puede ver ese pedido.
- Si el usuario puede ver ese cliente.
- Si roles comerciales deben quedar limitados a sus propios clientes/pedidos.

Esto es especialmente importante porque un lote de producción puede incluir productos expedidos a varios clientes.

## Casos de prueba recomendados

- Sin filtros: la respuesta no cambia.
- Filtro por pedido con un único producto.
- Filtro por cliente con varios pedidos del mismo lote.
- Filtro por cliente que no tiene expediciones de ese lote.
- Envío simultáneo de `customerId` y `orderId`: debe rechazarse por validación en la primera versión.
- Lote con productos vendidos a varios clientes: solo aparece el cliente filtrado.
- Nodo final con varios productos: solo aparece el producto expedido al filtro.
- Producto repetido en varios nodos finales: validar la regla de atribución acordada.
- Cálculo de márgenes: `saleSubtotal`, `saleTotal`, `costTotal`, `marginTotalExTax`.
- Permisos para usuario comercial.

## Decisiones antes de implementar

### Decididas

- El filtro acepta `customerId` u `orderId`. No se combinan ambos en la primera versión.
- Un pedido en estado `incident` cuenta como expedido siempre que sus palets estén `shipped`.
- El frontend debe mantener las mismas claves principales del tree actual. Solo se añadirán metadatos nuevos si aportan valor claro a la experiencia visual o a la interpretación del filtro.
- La primera versión oculta `stock`, `reprocessed` y `balance` en la vista filtrada.
- Cuando el mismo producto aparece en varios nodos finales y no hay trazabilidad exacta, se usa reparto proporcional.
- Las cajas registradas no se vinculan de forma exacta a `production_outputs`. La vinculación actual entre cajas y nodos se hace por producto y lote. Cualquier enfoque anterior de vincular cajas de salida directamente con nodos está deprecado o no se usa.

### Abiertas

- No quedan decisiones funcionales abiertas antes de diseñar la implementación técnica.

## Propuesta inicial

Implementar una primera versión conservadora:

- Mismo endpoint, con `customerId` u `orderId` opcional y mutuamente excluyentes.
- Sin filtros, comportamiento actual intacto.
- Con filtro, árbol podado y recalculado.
- Solo nodo `sales` filtrado como salida final.
- `stock`, `reprocessed` y `balance` ocultos en vista filtrada.
- Prorrateo proporcional cuando no exista trazabilidad exacta por output.
- Mantener las claves principales actuales de la respuesta. Añadir metadatos solo si ayudan a interpretar el filtrado sin romper el render existente.

