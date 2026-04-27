# Production Tree Balance Bug Report

## Context

- Endpoint under analysis: `GET /api/v2/productions/{id}/process-tree`
- Use case: production tree balance node for final process (`type: "balance"`)
- Goal: explain why `balance` for product T4 is inconsistent after changing T4 output weight.

---

## Payload 1 - Original Response (relevant extract)

```json
{
  "processId": 33,
  "process": {
    "name": "Eviscerado, clasificación y congelación en bloques o IQF",
    "type": "process"
  },
  "id": 304,
  "isFinal": true,
  "totalInputWeight": 700,
  "totalOutputWeight": 762.04,
  "outputs": [
    {
      "id": 1209,
      "productId": 203,
      "product": { "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
      "boxes": 14,
      "weightKg": 263.36,
      "costPerKg": 10.373119465996128,
      "totalCost": 2731.8647425647405
    }
  ],
  "children": [
    {
      "type": "sales",
      "id": "sales-304",
      "orders": [
        {
          "order": { "id": 2610 },
          "products": [
            {
              "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
              "totalBoxes": 7,
              "totalNetWeight": 131.68
            }
          ]
        }
      ]
    },
    {
      "type": "stock",
      "id": "stock-304",
      "stores": [
        {
          "products": [
            {
              "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
              "totalBoxes": 1,
              "totalNetWeight": 21.12
            }
          ]
        }
      ]
    },
    {
      "type": "balance",
      "id": "balance-304",
      "products": [
        {
          "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
          "produced": { "boxes": 14, "weight": 263.36 },
          "inSales": { "boxes": 7, "weight": 131.68 },
          "inStock": { "boxes": 1, "weight": 21.12 },
          "reprocessed": { "boxes": 0, "weight": 0 },
          "balance": { "boxes": 7, "weight": 131.68, "percentage": 50.0 }
        }
      ],
      "summary": { "totalBalanceBoxes": 7, "totalBalanceWeight": 131.68 }
    }
  ]
}
```

---

## Payload 2 - New Response After T4 Modification (relevant extract)

```json
{
  "processId": 33,
  "process": {
    "name": "Eviscerado, clasificación y congelación en bloques o IQF",
    "type": "process"
  },
  "id": 304,
  "isFinal": true,
  "totalInputWeight": 700,
  "totalOutputWeight": 630.36,
  "outputs": [
    {
      "id": 1209,
      "productId": 203,
      "product": { "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
      "boxes": 14,
      "weightKg": 131.68,
      "costPerKg": null,
      "totalCost": null,
      "sources": []
    }
  ],
  "children": [
    {
      "type": "sales",
      "id": "sales-304",
      "orders": [
        {
          "order": { "id": 2610 },
          "products": [
            {
              "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
              "totalBoxes": 7,
              "totalNetWeight": 131.68
            }
          ]
        }
      ]
    },
    {
      "type": "stock",
      "id": "stock-304",
      "stores": [
        {
          "products": [
            {
              "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
              "totalBoxes": 1,
              "totalNetWeight": 21.12
            }
          ]
        }
      ]
    },
    {
      "type": "balance",
      "id": "balance-304",
      "products": [
        {
          "product": { "id": 203, "name": "Pulpo eviscerado congelado en bloque T4 - Pulpo da Lua" },
          "produced": { "boxes": 14, "weight": 131.68 },
          "inSales": { "boxes": 7, "weight": 131.68 },
          "inStock": { "boxes": 1, "weight": 21.12 },
          "reprocessed": { "boxes": 0, "weight": 0 },
          "balance": { "boxes": 7, "weight": 131.68, "percentage": 100.0 }
        }
      ],
      "summary": { "totalBalanceBoxes": 7, "totalBalanceWeight": 131.68 }
    }
  ]
}
```

---

## Detailed Comparison

### What definitely changed (so response is not fully cached)

- Final process `totalOutputWeight`: `762.04 -> 630.36`
- T4 output `1209.weightKg`: `263.36 -> 131.68`
- T4 output cost: `10.3731 / 2731.8647 -> null / null`
- Multiple output `sources` IDs and timestamps updated (evidence of recomputation)

### What stayed unchanged and suspicious

- `balance.products[0].balance.weight` remains `131.68`
- `balance.products[0].balance.boxes` remains `7`
- `balance.summary.totalBalanceWeight` remains `131.68`
- Same list of 7 balance boxes and their weights

---

## Core Inconsistency (Math Check)

For T4 in the **new** response:

- produced = `131.68`
- inSales = `131.68`
- inStock = `21.12`
- reprocessed = `0`

Expected theoretical balance:

```text
produced - inSales - inStock - reprocessed
= 131.68 - 131.68 - 21.12 - 0
= -21.12
```

Observed API balance:

```text
balance.weight = +131.68
balance.boxes = 7
```

This is impossible if balance is intended to represent remaining/shortage based on produced vs destinations.

---

## Why this likely happens

The balance node logic appears to prioritize "physical missing boxes" detected by lot/product over the theoretical balance formula.  
So even after changing produced weight, balance is still anchored to the same pre-detected 7 boxes.

Symptoms that support this:

- `balance.weight` equals sum of `balance.boxes[*].netWeight` (~`131.68`)
- `balance.weight` does not track updated produced math
- `reprocessed` is `0`, so discrepancy does not come from reprocessed paths

---

## Suspected Faulty Logic Pattern

Likely branch behavior in balance computation:

1. Compute `calculatedMissing = produced - sales - stock - reprocessed`
2. If physical "missing boxes" exist, override with their weight and box count
3. Return overridden values in `balance`

This causes stale/incorrect balance whenever box grouping and produced output diverge.

---

## Impact

- Wrong operational balance in tree visualization
- False shortage/surplus interpretation for T4
- Margin/cost visibility degradation (already visible in new payload where T4 cost is null)
- Potential downstream errors in reconciliation decisions

---

## What Claude Code should verify first

1. In `Production` balance builder:
   - whether `balance.weight` is sourced from physical boxes instead of formula.
2. In missing-box data query:
   - whether it includes boxes that should not be considered for this final node context.
3. For final node product mapping:
   - whether missing boxes are scoped by final node outputs or only by lot/product.
4. Formula precedence:
   - enforce formula as source of truth for `balance.weight`,
   - use physical boxes only as explanatory detail when they match tolerance.

---

## Minimal Repro Summary

1. Open production `319`, final node `304`
2. Observe T4 output initially `263.36`
3. Modify T4 output to `131.68`
4. Call process tree endpoint again
5. Confirm:
   - output weight updates
   - balance remains stuck at previous physical-box-derived value pattern (`131.68`)
   - formula mismatch appears (`expected -21.12`, observed `+131.68`)

---

## Notes

- This report intentionally focuses on the T4 inconsistency path.
- Both original and modified payloads provided by user show deterministic mismatch.
- Behavior is consistent with partial recomputation + incorrect balance source precedence, not full response cache reuse.

