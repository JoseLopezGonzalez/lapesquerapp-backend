# Liquidación de Proveedores - Sistema de Pagos y Gastos

## 📋 Visión General

Este documento describe la lógica compleja para el manejo de pagos (efectivo/transferencia) y gastos de gestión en las liquidaciones de proveedores, especialmente cuando hay salidas de cebo con IVA.

---

## 🧮 Lógica de Cálculo de Totales

### Variables Base

- **Total Recepción (sin IVA)**: Suma de todos los importes calculados de las recepciones
- **Total Declarado (sin IVA)**: Suma de todos los importes declarados de las recepciones
- **Total Declarado (con IVA)**: Total Declarado (sin IVA) + IVA del declarado (si aplica)
- **Total Salida Cebo (sin IVA)**: Suma de `base_amount` de todas las salidas de cebo
- **Total Salida Cebo (con IVA)**: Suma de `total_amount` de todas las salidas de cebo (base + IVA)

### Condición: Cebo con IVA

**Si hay salidas de cebo con IVA** (`total_dispatches_iva_amount > 0`), el frontend debe mostrar:
- Un selector/checkbox para elegir el método de descuento: **Efectivo** o **Transferencia**

---

## 💰 Cálculo de Total Efectivo

**Fórmula:**
```
Total Efectivo = Total Recepción (sin IVA) - Total Declarado (sin IVA) - Total Salida Cebo (con IVA)
```

**Condiciones:**
- Solo se calcula si hay cebo con IVA Y está seleccionado "Efectivo"
- Si no hay cebo con IVA, este total no se muestra/calcula

**Ejemplo:**
- Total Recepción: 10.000,00 €
- Total Declarado: 8.000,00 €
- Total Salida Cebo (con IVA): 1.100,00 € (1.000,00 € base + 100,00 € IVA)
- **Total Efectivo = 10.000,00 - 8.000,00 - 1.100,00 = 900,00 €**

---

## 🏦 Cálculo de Total Transferencia

**Fórmula:**
```
Total Transferencia = Total Declarado (con IVA) - Total Salida Cebo (con IVA)
```

**Condiciones:**
- Solo se calcula si hay cebo con IVA Y está seleccionado "Transferencia"
- Si no hay cebo con IVA, este total no se muestra/calcula
- El Total Declarado (con IVA) se calcula asumiendo que el declarado también tiene IVA aplicado

**Nota sobre Total Declarado (con IVA):**
- Si el Total Declarado tiene IVA, se debe calcular: `Total Declarado (sin IVA) * 1.10`
- Si no tiene IVA, usar directamente: `Total Declarado (sin IVA)`

**Ejemplo:**
- Total Declarado (sin IVA): 8.000,00 €
- Total Declarado (con IVA): 8.800,00 € (8.000,00 * 1.10)
- Total Salida Cebo (con IVA): 1.100,00 €
- **Total Transferencia = 8.800,00 - 1.100,00 = 7.700,00 €**

---

## 📊 Gasto de Gestión

### Descripción

El gasto de gestión es un porcentaje adicional que se aplica sobre el importe declarado (sin IVA) cuando se selecciona esta opción.

**Fórmula:**
```
Gasto de Gestión = Total Declarado (sin IVA) * 0.025 (2.5%)
```

**Aplicación:**
- El gasto de gestión se **resta** del Total Transferencia
- Solo se aplica si está marcado el checkbox "Lleva gasto de gestión"
- Es un 2.5% de suplido sobre el importe sin IVA declarado

**Ejemplo:**
- Total Declarado (sin IVA): 8.000,00 €
- Gasto de Gestión: 8.000,00 * 0.025 = 200,00 €
- Total Transferencia (antes del gasto): 7.700,00 €
- **Total Transferencia (final) = 7.700,00 - 200,00 = 7.500,00 €**

---

## 🔄 Flujo Completo de Cálculo

### Escenario 1: Cebo con IVA - Efectivo

1. Usuario selecciona recepciones y salidas de cebo
2. Sistema detecta que hay IVA en cebo (`total_dispatches_iva_amount > 0`)
3. Frontend muestra selector: "Descontar cebo de: [ ] Efectivo [ ] Transferencia"
4. Usuario selecciona "Efectivo"
5. **Cálculo:**
   - Total Efectivo = Total Recepción - Total Declarado - Total Salida Cebo (con IVA)
6. Si hay gasto de gestión marcado:
   - Gasto de Gestión = Total Declarado (sin IVA) * 0.025
   - (No se aplica al efectivo, solo a transferencia)

### Escenario 2: Cebo con IVA - Transferencia

1. Usuario selecciona recepciones y salidas de cebo
2. Sistema detecta que hay IVA en cebo
3. Frontend muestra selector: "Descontar cebo de: [ ] Efectivo [x] Transferencia"
4. Usuario selecciona "Transferencia"
5. **Cálculo:**
   - Total Declarado (con IVA) = Total Declarado (sin IVA) * 1.10
   - Total Transferencia = Total Declarado (con IVA) - Total Salida Cebo (con IVA)
6. Si hay gasto de gestión marcado:
   - Gasto de Gestión = Total Declarado (sin IVA) * 0.025
   - Total Transferencia (final) = Total Transferencia - Gasto de Gestión

### Escenario 3: Cebo sin IVA

1. No hay IVA en cebo (`total_dispatches_iva_amount = 0`)
2. No se muestra selector de efectivo/transferencia
3. Se calculan los totales normales sin esta lógica

---

## 📡 Endpoints y Parámetros

### GET `/v2/supplier-liquidations/{supplierId}/details`

**Response actualizado:**
```json
{
  "summary": {
    "total_receptions": 5,
    "total_dispatches": 3,
    "total_receptions_weight": 1000.00,
    "total_receptions_amount": 10000.00,
    "total_dispatches_weight": 500.00,
    "total_dispatches_base_amount": 5000.00,
    "total_dispatches_iva_amount": 500.00,
    "total_dispatches_amount": 5500.00,
    "total_declared_weight": 800.00,
    "total_declared_amount": 8000.00,
    "weight_difference": 200.00,
    "amount_difference": 2000.00,
    "net_amount": 2000.00,
    "has_iva_in_dispatches": true,  // ✅ NUEVO: Indica si hay IVA en cebo
    "total_declared_with_iva": 8800.00  // ✅ NUEVO: Total declarado con IVA (si aplica)
  }
}
```

### GET `/v2/supplier-liquidations/{supplierId}/pdf`

**Query Parameters nuevos:**
- `payment_method` (opcional): `"cash"` o `"transfer"` - Solo se envía si hay IVA en cebo
- `has_management_fee` (opcional): `true` o `false` - Indica si lleva gasto de gestión

**Ejemplo de URL:**
```
/v2/supplier-liquidations/8/pdf?dates[start]=2025-12-26&dates[end]=2025-12-30&receptions[]=8628&dispatches[]=2080&payment_method=transfer&has_management_fee=true
```

**Response del PDF:**
- Incluirá los totales calculados según la lógica:
  - Total Efectivo (si `payment_method=cash`)
  - Total Transferencia (si `payment_method=transfer`)
  - Gasto de Gestión (si `has_management_fee=true`)
  - Total Transferencia Final (con gasto de gestión descontado)

---

## 🎨 Interfaz Frontend

### Selector de Método de Pago (solo si hay IVA en cebo)

```html
<div v-if="summary.has_iva_in_dispatches">
  <label>Descontar cebo de:</label>
  <input type="radio" v-model="paymentMethod" value="cash"> Efectivo
  <input type="radio" v-model="paymentMethod" value="transfer"> Transferencia
</div>
```

### Checkbox de Gasto de Gestión

```html
<label>
  <input type="checkbox" v-model="hasManagementFee">
  Lleva gasto de gestión (2.5% sobre declarado sin IVA)
</label>
```

### Visualización de Totales

**Si hay IVA y se selecciona Efectivo:**
```
Total Efectivo: X.XXX,XX €
```

**Si hay IVA y se selecciona Transferencia:**
```
Total Declarado (con IVA): X.XXX,XX €
Total Salida Cebo (con IVA): X.XXX,XX €
Total Transferencia: X.XXX,XX €
Gasto de Gestión (si aplica): X.XXX,XX €
Total Transferencia Final: X.XXX,XX €
```

---

## ⚠️ Notas Importantes

1. **IVA en Declarado**: Se asume que el Total Declarado también puede tener IVA. Si el sistema no maneja IVA en declarado, usar directamente el valor sin IVA.

2. **Validación**: El frontend debe validar que:
   - Si hay IVA en cebo, se debe seleccionar un método de pago
   - El método de pago solo se envía si hay IVA

3. **PDF**: El PDF debe mostrar claramente:
   - Qué método de pago se usó (si aplica)
   - Si hay gasto de gestión y su monto
   - Los totales finales calculados

4. **Compatibilidad**: Si no se envían los nuevos parámetros, el sistema debe funcionar como antes (sin esta lógica).

---

## 📝 Ejemplo Completo

**Datos:**
- Total Recepción: 10.000,00 €
- Total Declarado (sin IVA): 8.000,00 €
- Total Salida Cebo (base): 1.000,00 €
- Total Salida Cebo (IVA): 100,00 €
- Total Salida Cebo (total): 1.100,00 €
- Hay IVA en cebo: Sí
- Método seleccionado: Transferencia
- Gasto de gestión: Sí

**Cálculos:**
1. Total Declarado (con IVA) = 8.000,00 * 1.10 = 8.800,00 €
2. Total Transferencia = 8.800,00 - 1.100,00 = 7.700,00 €
3. Gasto de Gestión = 8.000,00 * 0.025 = 200,00 €
4. **Total Transferencia Final = 7.700,00 - 200,00 = 7.500,00 €**

---

## 🔧 Implementación Backend

Ver archivo: `app/Http/Controllers/v2/SupplierLiquidationController.php`

**Métodos modificados:**
- `getDetails()`: Añade `has_iva_in_dispatches` y `total_declared_with_iva` al summary
- `generatePdf()`: Acepta `payment_method` y `has_management_fee`, calcula totales y los pasa a la vista
- Vista PDF: Muestra los nuevos totales según la lógica implementada

