# 📄 Ejemplo de Respuesta: Production Tree v4 (Completo)

**Endpoint**: `GET /v2/productions/{id}/process-tree`  
**Versión**: v4 - Con nodos de Re-procesados y Faltantes

---

## 📋 Resumen del Ejemplo

Este ejemplo muestra un **nodo final** que produce 2 productos y tiene los **4 tipos de nodos hijos**:

- ✅ **sales-2**: Productos en venta (2 pedidos)
- ✅ **stock-2**: Productos almacenados (1 almacén)
- ✅ **reprocessed-2**: Productos re-procesados (1 proceso) ✨ NUEVO
- ✅ **missing-2**: Productos faltantes (cálculo completo) ✨ NUEVO

---

## 🎯 Nodo Final

**ID**: 2  
**Nombre del Proceso**: "Fileteado"  
**Productos que produce**:
- Producto 5: "Filetes de Atún" (10 cajas, 50kg)
- Producto 6: "Atún en Aceite" (5 cajas, 50kg)

---

## 📊 Nodos Hijos

### 1. Nodo de Venta (`sales-2`)

**Contiene**:
- **2 pedidos** diferentes (#00123 y #00124)
- **Pedido #00123**: 
  - Producto 5: 5 cajas (25kg)
  - Producto 6: 3 cajas (30kg)
- **Pedido #00124**:
  - Producto 5: 3 cajas (15kg)

**Total**: 11 cajas, 70kg

---

### 2. Nodo de Stock (`stock-2`)

**Contiene**:
- **1 almacén**: "Almacén Central"
  - Producto 5: 1 caja (5kg)

**Total**: 1 caja, 5kg

---

### 3. Nodo de Re-procesados (`reprocessed-2`) ✨ NUEVO

**Contiene**:
- **1 proceso**: "Enlatado"
  - Producto 5: 2 cajas (10kg) usadas como materia prima

**Información del proceso**:
- Production Record ID: 15
- Producción ID: 2
- Fecha inicio: 2024-02-10 08:00:00
- Fecha fin: 2024-02-10 12:00:00

**Total**: 2 cajas, 10kg

---

### 4. Nodo de Faltantes (`missing-2`) ✨ NUEVO

**Contiene cálculo completo para cada producto**:

#### Producto 5: "Filetes de Atún"
- ✅ **Producido**: 10 cajas (50kg)
- ✅ **En Venta**: 8 cajas (40kg)
- ✅ **En Stock**: 1 caja (5kg)
- ✅ **Re-procesado**: 2 cajas (10kg)
- ✅ **Faltante**: 0 cajas (0kg) - Todo contabilizado ✅

#### Producto 6: "Atún en Aceite"
- ✅ **Producido**: 5 cajas (50kg)
- ✅ **En Venta**: 3 cajas (30kg)
- ✅ **En Stock**: 0 cajas (0kg)
- ✅ **Re-procesado**: 0 cajas (0kg)
- ⚠️ **Faltante**: 2 cajas (20kg) - **40% faltante**

**Cajas faltantes del Producto 6**:
- Caja ID: 5678 (10kg)
- Caja ID: 5679 (10kg)

---

## 🔢 Balance Completo

### Producto 5: "Filetes de Atún"
```
Producido:    10 cajas (50kg)
├── En Venta:     8 cajas (40kg)
├── En Stock:     1 caja  (5kg)
├── Re-procesado: 2 cajas (10kg)
└── Faltante:     0 cajas (0kg)
────────────────────────────────
Total:           11 cajas (55kg) ✅
```

**Nota**: El total muestra 11 cajas porque se cuenta en venta (8) + stock (1) + re-procesado (2).

### Producto 6: "Atún en Aceite"
```
Producido:    5 cajas (50kg)
├── En Venta:     3 cajas (30kg)
├── En Stock:     0 cajas (0kg)
├── Re-procesado: 0 cajas (0kg)
└── Faltante:     2 cajas (20kg) ⚠️
────────────────────────────────
Total:           5 cajas (50kg) ✅
```

**Nota**: Hay 2 cajas faltantes (40% del total producido).

---

## 📐 Estructura Visual

```
Nodo Final "Fileteado" (ID: 2)
│
├── Produce: Producto 5 (10 cajas), Producto 6 (5 cajas)
│
├── sales-2
│   └── 2 pedidos
│       ├── #00123 → Producto 5 (5 cajas) + Producto 6 (3 cajas)
│       └── #00124 → Producto 5 (3 cajas)
│
├── stock-2
│   └── 1 almacén
│       └── Central → Producto 5 (1 caja)
│
├── reprocessed-2 ✨
│   └── 1 proceso
│       └── Enlatado → Producto 5 (2 cajas)
│
└── missing-2 ✨
    └── 2 productos
        ├── Producto 5: Todo contabilizado ✅
        └── Producto 6: 2 cajas faltantes ⚠️ (40%)
```

---

## 📚 Ver Archivos

- **JSON completo**: `EJEMPLO-RESPUESTA-process-tree-v4-completo.json`
- **Documentación frontend**: `FRONTEND-Nodos-Re-procesados-y-Faltantes.md`
- **Guía rápida**: `FRONTEND-Guia-Rapida-Nodos-Completos.md`

---

**Ejemplo creado**: 2025-01-27

