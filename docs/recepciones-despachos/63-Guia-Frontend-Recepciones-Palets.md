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
      "product": {
        "id": 5
      },
      "price": 12.50,
      "lot": "LOT-2025-001",
      "observations": "Palet 1",
      "boxes": [
        {
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**Campos**:
- `pallets` (requerido si no hay `details`): Array de palets
  - `pallets[].product.id` (requerido): ID del producto
  - `pallets[].price` (requerido): Precio por kg (obligatorio en modo manual)
  - `pallets[].lot` (opcional): Lote para todas las cajas del palet
  - `pallets[].observations` (opcional): Observaciones del palet
  - `pallets[].boxes` (requerido): Array de cajas
    - `boxes[].gs1128` (requerido): Código GS1-128
    - `boxes[].grossWeight` (requerido): Peso bruto en kg
    - `boxes[].netWeight` (requerido): Peso neto en kg

**Comportamiento**:
- Crea los palets según especificación
- Crea las cajas dentro de cada palet
- Agrupa cajas por producto y lote
- Crea líneas de recepción automáticamente con el resumen (suma de pesos por producto/lote)

### Actualizar Recepción

**PUT** `/api/v2/raw-material-receptions/{id}`

**Restricciones**:
- Solo se puede modificar si hay **un solo palet** asociado
- El palet **NO debe estar en uso**:
  - No vinculado a un pedido
  - No almacenado
  - Sin cajas usadas en producción

**Request Body**: Similar a crear en modo automático (solo acepta `details`)

**Comportamiento**:
- Si se cumplen las restricciones, elimina el palet y cajas existentes
- Recrea todo según los nuevos `details`

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
  "totalAmount": 6250.00
}
```

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

**⚠️ IMPORTANTE**: Los palets que provienen de una recepción (`isFromReception: true`) tienen restricciones:

**No se pueden modificar**:
- `PUT /api/v2/pallets/{id}` retorna error 403 si `receptionId` no es null
- No se pueden añadir, modificar ni eliminar cajas
- Todo debe hacerse desde la recepción

**No se pueden eliminar**:
- `DELETE /api/v2/pallets/{id}` retorna error 403 si `receptionId` no es null
- Solo se pueden eliminar eliminando la recepción

**Mensaje de error**:
```json
{
  "error": "No se puede modificar/eliminar un palet que proviene de una recepción. Modifique desde la recepción."
}
```

### Actualizar Palet

**PUT** `/api/v2/pallets/{id}`

**Validación previa**: Si el palet tiene `receptionId`, retorna error 403.

**Comportamiento normal**: Solo funciona para palets que NO provienen de recepción.

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

1. **Verificar restricciones**:
   - Solo se puede modificar si hay 1 palet
   - El palet no debe estar en uso
   - Mostrar mensaje claro si no se puede modificar

2. **Si se puede modificar**:
   - Usar el mismo formato que crear (solo modo automático con `details`)
   - El sistema elimina y recrea todo

### Eliminar Recepción

1. **Verificar restricciones**:
   - Los palets no deben estar en uso
   - Mostrar mensaje claro si no se puede eliminar

2. **Si se puede eliminar**:
   - Confirmar acción (elimina recepción y palets)

### Mostrar Palets

1. **Verificar origen**:
   - Si `isFromReception: true` → Mostrar indicador visual
   - Deshabilitar botones de editar/eliminar
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

- Solo se puede modificar recepción con 1 palet
- El palet no debe estar en uso
- Si hay más palets, mostrar mensaje: "No se puede modificar una recepción con más de un palet"

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

- [Documentación Técnica Completa](./62-Plan-Implementacion-Recepciones-Palets-Costes.md)
- [Documentación de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Documentación de Palets](../inventario/31-Palets.md)

---

**Última actualización**: 2025-01-XX

