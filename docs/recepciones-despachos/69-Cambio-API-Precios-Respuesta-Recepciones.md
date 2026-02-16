# Cambio API: Array `prices` en respuesta de recepciones

## Resumen

Se ha agregado el campo `prices` en la respuesta de recepciones creadas en modo **pallets**, devolviendo los precios en el mismo formato que se envían en el request.

## Formato

La respuesta ahora incluye un array `prices` con la siguiente estructura:

```json
{
  "data": {
    "id": 8509,
    "creationMode": "pallets",
    "prices": [
      {
        "product": { "id": 15 },
        "lot": "110825OCC01001",
        "price": 20
      },
      {
        "product": { "id": 101 },
        "lot": "190925OCC01369",
        "price": 10
      }
    ],
    "details": [...],
    "pallets": [...]
  }
}
```

## Características

- **Solo disponible** para recepciones con `creationMode === "pallets"`
- **Formato idéntico** al request: mismo formato que se envía en `POST/PUT`
- **Solo incluye** productos con precio (excluye `null`)
- **Listo para editar**: puede enviarse directamente de vuelta en el mismo formato

## Uso en Frontend

### Mostrar precios
```javascript
// Mostrar los precios de la recepción
reception.prices?.forEach(price => {
  console.log(`Producto ${price.product.id}, Lote ${price.lot}: ${price.price}`);
});
```

### Editar y enviar
```javascript
// Modificar precio
reception.prices[0].price = 25;

// Enviar de vuelta (mismo formato)
await updateReception(receptionId, {
  ...reception,
  prices: reception.prices  // Formato ya correcto
});
```

## Notas

- Si `creationMode !== "pallets"`, el array `prices` estará vacío `[]`
- El array `prices` siempre estará presente en la respuesta (puede estar vacío)
- Los precios se obtienen de las líneas de recepción (`details`), agrupadas por `product_id + lot`

