# Sistema de Payloads QR

## Objetivo

Los QR internos impresos por La PesquerApp usan un payload compacto basado en pares `CLAVE=VALOR` separados por `;`.

El front debe resolverlos en directo, sin endpoint de resolucion backend obligatorio. Cada buscador o bloque de UI parsea el QR y usa solo la entidad que necesita.

## Formato

```text
CLAVE=VALOR;CLAVE=VALOR
```

Ejemplo de etiqueta de expedicion de palet:

```text
P=123;O=456
```

Reglas:

- Separador de campos: `;`
- Separador clave/valor: `=`
- Claves en mayuscula
- Valores sin espacios
- El orden de campos no debe ser relevante
- El front debe ignorar claves que no entienda

## Iniciales de entidades

| Inicial | Entidad | Campo esperado |
|---------|---------|----------------|
| `P` | Palet | `pallet.id` |
| `O` | Pedido | `order.id` |
| `B` | Caja | `box.id` |
| `C` | Cliente | `customer.id` |
| `R` | Recepcion | `raw_material_reception.id` |
| `T` | Transporte | `transport.id` |
| `S` | Almacen | `store.id` |
| `L` | Lote | `box.lot` o lote operativo |

## Comportamiento esperado en frontend

Un parser comun debe convertir el texto escaneado en un mapa:

```ts
function parseQrPayload(raw: string): Record<string, string> {
  return Object.fromEntries(
    raw.trim().split(';').map((part) => {
      const [key, value] = part.split('=')
      return [key, value]
    }).filter(([key, value]) => key && value)
  )
}
```

Cada pantalla decide que clave prioriza:

- Buscador de palets: usa `P`
- Buscador de pedidos: usa `O`
- Buscador de cajas: usa `B`
- Buscador de clientes: usa `C`
- Buscador global: puede mostrar todas las entidades detectadas

## Payload actual

Etiqueta de expedicion de palet:

```text
P={palletId};O={orderId}
```

No se incluyen datos descriptivos como cliente, producto, peso o estado. El QR identifica entidades; los datos vivos se consultan mediante las pantallas/API existentes.
