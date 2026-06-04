# Guía Frontend: Recepciones y Palets

## 📋 Resumen

Esta guía explica los cambios recientes en la implementación de **recepciones de materia prima** y su integración con **palets**. Está orientada al equipo de frontend para entender cómo usar la API y qué cambios se han realizado.

---

## 🎯 Cambios Principales

### 1. Recepciones Ahora Crean Palets Automáticamente

**Antes**: Las recepciones eran solo registros contables/logísticos sin vínculo con el inventario físico.

**Ahora**: Al crear una recepción, se crean automáticamente **palets y cajas** en el inventario. Los palets son la unidad mínima almacenable según la lógica del ERP.

### 2. Dos Modos de Creación

La API soporta dos formas de crear recepciones:

- **Modo Automático**: Proporcionas líneas de productos y el sistema crea palets automáticamente
- **Modo Manual**: Proporcionas palets completos con sus cajas y el sistema crea las líneas automáticamente

### 3. Sistema de Costes

Los costes se calculan automáticamente desde las recepciones y se propagan a palets y cajas mediante accessors (campos calculados). No se almacenan en BD, siempre reflejan el precio actual de la recepción.

### 4. Restricciones en Palets de Recepción

Los palets que provienen de una recepción **no se pueden modificar ni eliminar directamente**. Todo debe hacerse desde la recepción.

---

## 📡 API de Recepciones

### Endpoint Base

```
/api/v2/raw-material-receptions
```

### Crear Recepción (Modo Automático)

**POST** `/api/v2/raw-material-receptions`

**Request Body**:
```json
{
  "supplier": {
    "id": 1
  },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "details": [
    {
      "product": {
        "id": 5
      },
      "netWeight": 500.00,
      "price": 12.50,
      "lot": "LOT-2025-001",
      "boxes": 20
    }
  ]
}
```

**Campos**:
- `supplier.id` (requerido): ID del proveedor
- `date` (requerido): Fecha de recepción (YYYY-MM-DD)
- `notes` (opcional): Notas adicionales
- `details` (requerido si no hay `pallets`): Array de líneas de productos
  - `details[].product.id` (requerido): ID del producto
  - `details[].netWeight` (requerido): Peso neto total en kg
  - `details[].price` (opcional): Precio por kg. Si no se proporciona, se intenta obtener del histórico
  - `details[].lot` (opcional): Lote. Si no se proporciona, se genera automáticamente
  - `details[].boxes` (opcional): Número de cajas. Si es 0 o null, se cuenta como 1

**Comportamiento**:
- Crea **1 palet por recepción** (no por línea)
- Distribuye el peso neto de cada línea entre las cajas especificadas
- Si no se indica `boxes`, crea 1 caja con todo el peso
- Crea las líneas de recepción con los datos proporcionados

### Crear Recepción (Modo Manual)

**POST** `/api/v2/raw-material-receptions`

**Request Body**:
```json
{
  "supplier": {
    "id": 1
  },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "pallets": [
    {
      "observations": "Palet 1",
      "store": {
        "id": 1
      },
      "boxes": [
        {
          "product": {
            "id": 5
          },
          "lot": "LOT-A",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": {
            "id": 5
          },
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": {
            "id": 6
          },
          "lot": "LOT-C",
          "gs1128": "GS1-003",
          "grossWeight": 30.0,
          "netWeight": 29.5
        }
      ],
      "prices": [
        {
          "product": {
            "id": 5
          },
          "lot": "LOT-A",
          "price": 12.50
        },
        {
          "product": {
            "id": 5
          },
          "lot": "LOT-B",
          "price": 13.00
        },
        {
          "product": {
            "id": 6
          },
          "lot": "LOT-C",
          "price": 15.00
        }
      ]
    }
  ]
}
```

**Campos**:
- `prices` (requerido si hay `pallets`): Array de precios en la raíz de la recepción (compartido por todos los palets)
  - `prices[].product.id` (requerido): ID del producto
  - `prices[].lot` (requerido): Lote
  - `prices[].price` (requerido): Precio por kg (≥ 0)
- `pallets` (requerido si no hay `details`): Array de palets
  - `pallets[].observations` (opcional): Observaciones del palet
  - `pallets[].palletTareWeightKg` (opcional): Tara/peso físico del palet vacío en kg
  - `pallets[].store.id` (opcional): ID del almacén (si se proporciona, el palet se crea como almacenado)
  - `pallets[].boxes` (requerido): Array de cajas
    - `boxes[].product.id` (requerido): ID del producto de la caja
    - `boxes[].lot` (opcional): Lote de la caja. Si no se proporciona, se genera automáticamente
    - `boxes[].gs1128` (requerido): Código GS1-128
    - `boxes[].grossWeight` (requerido): Peso bruto en kg
    - `boxes[].netWeight` (requerido): Peso neto en kg

**Comportamiento**:
- Crea los palets según especificación
- Cada caja puede tener su propio producto y lote (máxima flexibilidad)
- Un palet puede contener múltiples productos y lotes diferentes
- Los precios se especifican en el array `prices` en la raíz de la recepción (compartido por todos los palets)
- Si dos palets comparten el mismo producto+lote, solo se especifica el precio una vez en `prices`
- Si una combinación producto+lote no tiene precio en `prices`, se busca del histórico
- Agrupa cajas por producto y lote para crear líneas de recepción
- Crea líneas de recepción automáticamente con el resumen (suma de pesos por producto/lote)

### Actualizar Recepción

**PUT** `/api/v2/raw-material-receptions/{id}`

**Restricciones**:
- **No se puede editar si**:
  - Algún palet está vinculado a un pedido (`order_id !== null`)
  - Alguna caja está siendo usada en producción (`productionInputs()->exists()`)
- **Modo de edición debe coincidir con modo de creación**:
  - Si `creationMode = 'lines'` → Solo se puede editar con `details`
  - Si `creationMode = 'pallets'` → Solo se puede editar con `pallets`

**Request Body** (Modo LINES):
```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Notas actualizadas",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 500.00,
      "price": 12.50,
      "lot": "LOT-2025-001",
      "boxes": 20
    }
  ]
}
```

**Request Body** (Modo PALLETS):
```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Notas actualizadas",
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-A",
      "price": 12.50
    },
    {
      "product": { "id": 5 },
      "lot": "LOT-B",
      "price": 13.00
    }
  ],
  "pallets": [
    {
      "id": 15,  // ← ID del palet existente (opcional, si no viene se crea nuevo)
      "observations": "Palet 1",
      "store": { "id": 1 },
      "boxes": [
        {
          "id": 42,  // ← ID de la caja existente (opcional, si no viene se crea nueva)
          "product": { "id": 5 },
          "lot": "LOT-A",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 5 },
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**⚠️ IMPORTANTE**: Para editar en lugar de recrear:
- **Modo PALLETS**: Debes incluir los `id` de palets y cajas existentes en el request
- **Modo LINES**: No es necesario enviar IDs (las cajas se regeneran automáticamente)

**Comportamiento**:
- Valida las restricciones antes de editar
- **Modo PALLETS**: Edita palets y cajas existentes (si vienen con `id`), crea nuevos (si no vienen con `id`), elimina los que no están en el request
- **Modo LINES**: Mantiene el palet único, recrea las cajas según los nuevos detalles
- Regenera las líneas de recepción automáticamente

### Eliminar Recepción

**DELETE** `/api/v2/raw-material-receptions/{id}`

**Restricciones**:
- No se puede eliminar si los palets están en uso:
  - Vinculados a pedidos
  - Almacenados
  - Con cajas usadas en producción

**Comportamiento**:
- Si se cumplen las restricciones, elimina la recepción y todos sus palets (cascade)

### Response de Recepción

```json
{
  "id": 1,
  "supplier": {
    "id": 1,
    "name": "Proveedor Ejemplo"
  },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "creationMode": "lines",
  "netWeight": 500.00,
  "species": {...},
  "details": [
    {
      "id": 1,
      "product": {...},
      "netWeight": 500.00,
      "price": 12.50
    }
  ],
  "pallets": [
    {
      "id": 10,
      "observations": "Auto-generado desde recepción #1",
      "state": {
        "id": 1,
        "name": "registered"
      },
      "receptionId": 1,
      "isFromReception": true,
      "costPerKg": 12.50,
      "totalCost": 6250.00,
      "boxes": [...],
      "netWeight": 500.00
    }
  ],
  "totalAmount": 6250.00,
  "canEdit": true,
  "cannotEditReason": null
}
```

**Nuevos campos**:
- `canEdit` (boolean): Indica si la recepción se puede editar
- `cannotEditReason` (string | null): Razón por la que no se puede editar (si `canEdit = false`)

---

## 📦 API de Palets

### Endpoint Base

```
/api/v2/pallets
```

### Cambios Importantes

#### 1. Nuevos Campos en Response

Los palets ahora incluyen información de recepción y costes:

```json
{
  "id": 10,
  "palletTareWeightKg": 22.25,
  "receptionId": 1,
  "reception": {
    "id": 1,
    "date": "2025-01-15"
  },
  "isFromReception": true,
  "costPerKg": 12.50,
  "totalCost": 6250.00,
  // ... resto de campos
}
```

#### 2. Restricciones en Palets de Recepción

**⚠️ IMPORTANTE**: Los palets que provienen de una recepción (`isFromReception: true`) tienen restricciones según el modo de creación:

**Palets de recepciones creadas en modo LINES**:
- **No se pueden modificar** desde el endpoint de palets
- **No se pueden eliminar** desde el endpoint de palets
- Todo debe hacerse desde la recepción

**Palets de recepciones creadas en modo PALLETS**:
- **SÍ se pueden modificar** desde `PUT /api/v2/pallets/{id}` (con restricciones)
- **No se pueden eliminar** desde el endpoint de palets
- Al editar un palet, se regeneran automáticamente las líneas de recepción

**Restricciones para editar palets de recepción**:
- No se puede editar si el palet está vinculado a un pedido
- No se puede editar si alguna caja está en producción

**Mensajes de error**:
```json
{
  "error": "No se puede modificar un palet que proviene de una recepción creada por líneas. Modifique desde la recepción."
}
```

```json
{
  "error": "No se puede modificar el palet: está vinculado a un pedido"
}
```

```json
{
  "error": "No se puede modificar el palet: la caja #123 está siendo usada en producción"
}
```

### Actualizar Palet

**PUT** `/api/v2/pallets/{id}`

**Validación previa**:
- Si el palet pertenece a una recepción creada en modo `lines`, retorna error 403
- Si el palet pertenece a una recepción creada en modo `pallets`, permite editar (con restricciones)

**Restricciones para palets de recepción**:
- No se puede editar si está vinculado a un pedido
- No se puede editar si alguna caja está en producción

**Comportamiento**:
- Si el palet pertenece a una recepción en modo `pallets`, al editar se regeneran automáticamente las líneas de recepción
- Funciona normalmente para palets que NO provienen de recepción

### Eliminar Palet

**DELETE** `/api/v2/pallets/{id}`

**Validación previa**: Si el palet tiene `receptionId`, retorna error 403.

**Comportamiento normal**: Solo funciona para palets que NO provienen de recepción.

---

## 💰 Sistema de Costes

### Cálculo Automático

Los costes se calculan automáticamente mediante accessors (campos calculados):

- **Cajas**: `costPerKg` = precio del producto en la recepción
- **Cajas**: `totalCost` = `netWeight × costPerKg`
- **Palets**: `costPerKg` = media ponderada de las cajas
- **Palets**: `totalCost` = suma de costes de todas las cajas

### Campos en Response

**Cajas** (`Box::toArrayAssocV2()`):
```json
{
  "id": 1,
  "costPerKg": 12.50,
  "totalCost": 312.50,
  // ... resto de campos
}
```

**Palets** (`PalletResource`):
```json
{
  "id": 10,
  "costPerKg": 12.50,
  "totalCost": 6250.00,
  // ... resto de campos
}
```

**Nota**: Si no hay precio en la recepción, los costes serán `null`.

---

## 🔄 Flujo Recomendado para Frontend

### Crear Recepción

1. **Decidir modo**:
   - Si el usuario quiere especificar palets/cajas → Modo Manual (`pallets`)
   - Si solo tiene líneas de productos → Modo Automático (`details`)

2. **Modo Automático**:
   - Mostrar formulario con líneas de productos
   - Campos por línea: producto, peso neto, precio (opcional), lote (opcional), número de cajas (opcional)
   - Si no se proporciona precio, el backend intenta obtenerlo del histórico

3. **Modo Manual**:
   - Mostrar formulario para crear palets
   - Cada palet: producto, precio (requerido), lote (opcional), observaciones (opcional)
   - Cada palet tiene cajas: GS1-128, peso bruto, peso neto

4. **Después de crear**:
   - La respuesta incluye los palets creados
   - Mostrar información de palets y costes calculados

### Modificar Recepción

1. **Verificar si se puede editar**:
   - Usar el campo `canEdit` de la respuesta
   - Si `canEdit = false`, mostrar `cannotEditReason` como mensaje
   - Deshabilitar botón de edición si `canEdit = false`

2. **Determinar modo de edición**:
   - Si `creationMode = 'lines'` → Usar `details` (mismo formato que crear en modo automático)
   - Si `creationMode = 'pallets'` → Usar `pallets` (mismo formato que crear en modo manual)

3. **Si se puede modificar**:
   - Enviar request según el modo de creación
   - El sistema elimina y recrea todo
   - Las líneas de recepción se regeneran automáticamente

### Eliminar Recepción

1. **Verificar restricciones**:
   - Los palets no deben estar en uso
   - Mostrar mensaje claro si no se puede eliminar

2. **Si se puede eliminar**:
   - Confirmar acción (elimina recepción y palets)

### Mostrar Palets

1. **Verificar origen**:
   - Si `isFromReception: true` → Mostrar indicador visual
   - **Si la recepción fue creada en modo `pallets`**:
     - Permitir editar el palet (con validaciones)
     - Al editar, se regeneran las líneas de recepción automáticamente
   - **Si la recepción fue creada en modo `lines`**:
     - Deshabilitar botón de editar (debe editarse desde la recepción)
   - Deshabilitar botón de eliminar (siempre)
   - Mostrar link a la recepción

2. **Mostrar costes**:
   - Si `costPerKg` y `totalCost` no son null, mostrarlos
   - Si son null, indicar que no hay precio en la recepción

---

## ⚠️ Consideraciones Importantes

### 1. Precio por Defecto

Si no se proporciona `price` en modo automático:
- El backend busca el último precio del mismo producto y proveedor
- Si lo encuentra, lo usa automáticamente
- Si no lo encuentra, el precio queda en `null` y no se calculan costes

**Recomendación Frontend**: Mostrar el precio histórico si está disponible para ayudar al usuario.

### 2. Lotes

- Se permiten duplicados (no hay validación de unicidad)
- Si no se proporciona, se genera automáticamente
- En modo manual, todas las cajas de un palet comparten el mismo lote

### 3. Número de Cajas

- Si `boxes` es 0 o null, se cuenta como 1
- En modo automático, el peso se distribuye equitativamente entre las cajas

### 4. Validaciones de Modificación

- **Restricciones comunes** (aplican a ambos modos):
  - No se puede editar si algún palet está vinculado a un pedido
  - No se puede editar si alguna caja está en producción
- **Modo de edición**:
  - Debe coincidir con el modo de creación (`creationMode`)
  - Recepciones en modo `lines` solo se editan con `details`
  - Recepciones en modo `pallets` se pueden editar con `pallets` o editando palets individualmente

### 5. Estados de Palets

Los palets creados desde recepciones tienen estado **"registered"** (registrado) por defecto.

---

## 📝 Ejemplos de Uso

### Ejemplo 1: Recepción Simple (Modo Automático)

```json
POST /api/v2/raw-material-receptions
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 500.00,
      "price": 12.50,
      "boxes": 20
    }
  ]
}
```

**Resultado**: 1 palet con 20 cajas de 25 kg cada una.

### Ejemplo 2: Recepción con Múltiples Productos

```json
POST /api/v2/raw-material-receptions
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 500.00,
      "price": 12.50,
      "lot": "LOT-A",
      "boxes": 20
    },
    {
      "product": { "id": 6 },
      "netWeight": 300.00,
      "price": 15.00,
      "lot": "LOT-B",
      "boxes": 15
    }
  ]
}
```

**Resultado**: 1 palet con 35 cajas (20 del producto 5, 15 del producto 6).

### Ejemplo 3: Recepción Manual con Palets Específicos

```json
POST /api/v2/raw-material-receptions
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "pallets": [
    {
      "product": { "id": 5 },
      "price": 12.50,
      "lot": "LOT-001",
      "observations": "Palet principal",
      "boxes": [
        { "gs1128": "GS1-001", "grossWeight": 25.5, "netWeight": 25.0 },
        { "gs1128": "GS1-002", "grossWeight": 25.5, "netWeight": 25.0 }
      ]
    }
  ]
}
```

**Resultado**: 1 palet con 2 cajas específicas, línea de recepción creada automáticamente.

---

## 🔗 Referencias

- [Guía Frontend de Edición](./64-Guia-Frontend-Edicion-Recepciones.md)
- [Guía Backend de Edición](./65-Guia-Backend-Edicion-Recepciones.md)
- [Documentación Técnica Completa](./62-Plan-Implementacion-Recepciones-Palets-Costes.md)
- [Documentación de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Documentación de Palets](../inventario/31-Palets.md)

---

**Última actualización**: 2025-01-XX
