# Selección de Recepciones y Salidas para PDF - Guía Frontend

## 📋 Resumen

El endpoint de generación de PDF ahora permite que el usuario seleccione qué recepciones y salidas de cebo quiere incluir en el documento PDF. Si no se selecciona nada, se incluyen todas por defecto.

---

## 🎯 Funcionalidad Requerida

En la pantalla de detalle de liquidación, el usuario debe poder:

1. **Seleccionar/deseleccionar recepciones individuales** para incluir o excluir del PDF
2. **Seleccionar/deseleccionar salidas de cebo individuales** para incluir o excluir del PDF
3. **Generar el PDF** con solo los items seleccionados
4. **Ver un resumen** de cuántos items están seleccionados

---

## 🔌 Endpoint Actualizado

**Endpoint**: `GET /v2/supplier-liquidations/{supplierId}/pdf`

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio
- `dates[end]` (required): Fecha de fin
- `receptions[]` (optional): Array de IDs de recepciones a incluir
- `dispatches[]` (optional): Array de IDs de salidas de cebo a incluir

**Ejemplo sin selección (incluye todo)**:
```
GET /v2/supplier-liquidations/1/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Ejemplo con selección**:
```
GET /v2/supplier-liquidations/1/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31&receptions[]=101&receptions[]=102&dispatches[]=201&dispatches[]=202
```

---

## 💡 Implementación Sugerida

### 1. Estado de Selección

Mantener un estado local con los IDs seleccionados:

```javascript
// Ejemplo en React/Vue
const [selectedReceptions, setSelectedReceptions] = useState([]);
const [selectedDispatches, setSelectedDispatches] = useState([]);
```

### 2. UI de Selección

**Para cada recepción**:
- Agregar un checkbox/toggle al inicio de cada fila de recepción
- Al hacer clic, agregar o quitar el ID de la recepción del array `selectedReceptions`

**Para cada salida de cebo**:
- Agregar un checkbox/toggle al inicio de cada fila de salida
- Al hacer clic, agregar o quitar el ID de la salida del array `selectedDispatches`

**Botones de ayuda**:
- "Seleccionar todas las recepciones"
- "Deseleccionar todas las recepciones"
- "Seleccionar todas las salidas"
- "Deseleccionar todas las salidas"

### 3. Generación del PDF

Al hacer clic en "Generar PDF":

```javascript
// Construir la URL con los parámetros
const params = new URLSearchParams({
  'dates[start]': startDate,
  'dates[end]': endDate
});

// Agregar recepciones seleccionadas (solo si hay selección)
if (selectedReceptions.length > 0) {
  selectedReceptions.forEach(id => {
    params.append('receptions[]', id);
  });
}

// Agregar salidas seleccionadas (solo si hay selección)
if (selectedDispatches.length > 0) {
  selectedDispatches.forEach(id => {
    params.append('dispatches[]', id);
  });
}

// Llamar al endpoint
const url = `/v2/supplier-liquidations/${supplierId}/pdf?${params.toString()}`;
window.open(url, '_blank'); // O usar fetch para descargar
```

### 4. Indicador Visual

Mostrar un contador o indicador de cuántos items están seleccionados:

```
"X recepciones seleccionadas | Y salidas seleccionadas"
```

---

## 📝 Comportamiento del Backend

### Si NO se envían parámetros de selección:
- Se incluyen **todas las recepciones** del rango de fechas
- Se incluyen **todas las salidas de cebo** del rango de fechas
- El resumen se calcula con todos los datos

### Si se envían parámetros de selección:
- Solo se incluyen las recepciones con IDs en `receptions[]`
- Solo se incluyen las salidas con IDs en `dispatches[]`
- Las salidas relacionadas dentro de recepciones también se filtran si están en `dispatches[]`
- El resumen se recalcula automáticamente con solo los datos seleccionados

### Validación:
- El backend valida que los IDs existan en la base de datos
- Si un ID no existe, se devuelve un error 422

---

## 🎨 Ejemplo de UI

```
┌─────────────────────────────────────────────────────────┐
│ Detalle de Liquidación - Proveedor ABC                   │
│ Período: 01/01/2024 - 31/01/2024                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ [✓] Seleccionar todas recepciones                       │
│ [✓] Seleccionar todas salidas                            │
│                                                          │
│ 📊 3 recepciones seleccionadas | 2 salidas seleccionadas│
│                                                          │
├─────────────────────────────────────────────────────────┤
│ RECEPCIONES                                              │
├─────────────────────────────────────────────────────────┤
│ [✓] Recepción #101 - 15/01/2024                         │
│     Pulpo Fresco +1kg | 22,00 kg | 10,50 €/kg | 231,00€│
│     Total: 65,00 kg | 10,72 €/kg | 697,00 €            │
│                                                          │
│ [✓] Recepción #102 - 16/01/2024                         │
│     ...                                                  │
│                                                          │
│ [ ] Recepción #103 - 17/01/2024                         │
│     ...                                                  │
│                                                          │
├─────────────────────────────────────────────────────────┤
│ SALIDAS DE CEBO                                         │
├─────────────────────────────────────────────────────────┤
│ [✓] Salida #201 - 22/01/2024                           │
│     Caballa congelada | 100,00 kg | 1,25 €/kg | 125,00€ │
│                                                          │
│ [✓] Salida #202 - 23/01/2024                           │
│     ...                                                  │
│                                                          │
│ [ ] Salida #203 - 24/01/2024                           │
│     ...                                                  │
│                                                          │
├─────────────────────────────────────────────────────────┤
│ [Generar PDF] [Volver]                                  │
└─────────────────────────────────────────────────────────┘
```

---

## ✅ Checklist de Implementación

- [ ] Agregar checkboxes/toggles a cada recepción
- [ ] Agregar checkboxes/toggles a cada salida de cebo
- [ ] Implementar estado para IDs seleccionados
- [ ] Implementar funciones de seleccionar/deseleccionar todo
- [ ] Mostrar contador de items seleccionados
- [ ] Modificar función de generar PDF para incluir parámetros de selección
- [ ] Manejar caso cuando no hay selección (enviar sin parámetros)
- [ ] Probar con diferentes combinaciones de selección
- [ ] Validar que el PDF generado solo incluye los items seleccionados

---

## 🔍 Casos de Uso

### Caso 1: Seleccionar solo algunas recepciones
- Usuario selecciona recepciones #101, #102
- No selecciona ninguna salida
- PDF incluye solo esas 2 recepciones y todas las salidas del rango

### Caso 2: Seleccionar solo algunas salidas
- Usuario no selecciona recepciones (o selecciona todas)
- Selecciona solo salidas #201, #202
- PDF incluye todas las recepciones y solo esas 2 salidas

### Caso 3: Selección mixta
- Usuario selecciona recepciones #101, #102
- Usuario selecciona salidas #201, #202
- PDF incluye solo esas recepciones y solo esas salidas

### Caso 4: Sin selección
- Usuario no selecciona nada
- PDF incluye todo (comportamiento por defecto)

---

## 📚 Notas Técnicas

- Los arrays se envían como `receptions[]=101&receptions[]=102` en la URL
- Si no se envía ningún parámetro de selección, se incluyen todos los items
- El resumen del PDF se recalcula automáticamente con los datos filtrados
- Las salidas relacionadas dentro de recepciones también se filtran si están en `dispatches[]`

---

## 🚨 Errores Posibles

- **422 Unprocessable Entity**: Si algún ID no existe en la base de datos
- **404 Not Found**: Si el proveedor no existe
- **500 Internal Server Error**: Error al generar el PDF

Manejar estos errores de forma amigable en el frontend.

