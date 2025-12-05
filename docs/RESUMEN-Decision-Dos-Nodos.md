# ✅ Resumen: Decisión - Dos Nodos Separados

**Fecha**: 2025-01-27  
**Decisión**: Separar en **DOS nodos diferentes** en lugar de uno solo

---

## 📋 Decisión Tomada

En lugar de crear un solo nodo de "no contabilizados", se crearán **DOS nodos separados**:

### 1. 🔄 Nodo de Re-procesados / Consumidos

**Tipo**: `reprocessed`  
**ID**: `reprocessed-{finalNodeId}`

**Contiene**: Cajas que fueron **usadas como materia prima** en otro proceso de producción.

**Características**:
- `isAvailable = false` (no están disponibles)
- Tienen registro en `production_inputs`
- **Tienen un destino claro**: otro proceso de producción
- Útil para **trazabilidad** y seguimiento de flujo de materiales

---

### 2. ⚠️ Nodo de Faltantes / No Contabilizados

**Tipo**: `missing`  
**ID**: `missing-{finalNodeId}`

**Contiene**: Cajas que **realmente faltan** o no están contabilizadas.

**Características**:
- `isAvailable = true` (están disponibles)
- NO están en venta (sin pedido)
- NO están en stock (sin almacén)
- NO fueron consumidas (sin `production_inputs`)
- **Estado desconocido**: perdidas, error de registro, etc.
- Útil para **detección de problemas** operativos

---

## 🎯 Por Qué Separar

1. **Semántica diferente**: 
   - Re-procesados = flujo normal de materiales
   - Faltantes = problema a investigar

2. **Casos de uso diferentes**:
   - Re-procesados: Seguimiento de transformación de productos
   - Faltantes: Alertas de discrepancias

3. **Información diferente**:
   - Re-procesados: Muestran DÓNDE se usaron (proceso destino)
   - Faltantes: Muestran CUÁNTO falta y QUÉ cajas son

---

## 📊 Estructura Final del Árbol

```
Nodo Final
├── sales (productos en venta)
├── stock (productos almacenados)
├── reprocessed (productos re-procesados) ✨ NUEVO
└── missing (productos faltantes) ✨ NUEVO
```

---

## 📚 Documentación

- **Diseño completo**: `DISENO-Nodos-Re-procesados-y-Faltantes.md`
- **Análisis original**: `ANALISIS-Nodo-No-Contabilizado.md`

---

**Estado**: ✅ **Decisión Confirmada** - Lista para implementación

