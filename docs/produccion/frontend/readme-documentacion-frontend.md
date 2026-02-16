# 📚 Documentación Frontend - Production Tree

**Última actualización**: 2025-01-27  
**Versión actual**: v4 (con nodos re-procesados y balance)  
**⚠️ IMPORTANTE**: El nodo `missing` ha sido renombrado a `balance` - Ver migración abajo

---

## 🎯 Endpoint

```
GET /v2/productions/{id}/process-tree
```

---

## 📋 Documentos Disponibles

### ⚠️ Migración Importante

**🚨 `FRONTEND-Migracion-Missing-a-Balance.md`** ⭐ **LEER PRIMERO**
   - Guía completa de migración de `missing` → `balance`
   - Cambios en TypeScript
   - Ejemplos de código actualizados
   - Checklist de migración
   - **Breaking change** - Requiere actualización inmediata

### Para Empezar (Recomendado)

1. **🚀 `FRONTEND-Guia-Rapida-Nodos-Completos.md`**
   - Guía rápida y visual
   - Estructura de los 4 tipos de nodos
   - Ejemplos simplificados
   - ⭐ **Empezar aquí** (después de leer la migración)

2. **📊 [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json`](../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json)**
   - Ejemplo JSON completo
   - Listo para usar en desarrollo
   - Incluye todos los tipos de nodos

3. **📖 [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4.md`](../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4.md)**
   - Explicación detallada del ejemplo
   - Balance completo
   - Casos de uso

### Documentación Detallada

4. **📘 `FRONTEND-Nodos-Re-procesados-y-Faltantes.md`**
   - Documentación completa de los nuevos nodos
   - Estructura detallada
   - Tipos TypeScript
   - Casos de uso

5. **📗 [`../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md`](../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md)**
   - Documentación de nodos de venta y stock
   - Estructura v3 (un nodo por nodo final)

6. **📙 `FRONTEND-Relaciones-Padre-Hijo-Nodos.md`**
   - Explicación de relaciones padre-hijo
   - Cómo enlazar nodos

---

## 🎯 Estructura Actual (v4)

Un nodo final puede tener hasta **4 tipos de nodos hijos**:

```
Nodo Final
├── sales         → Productos en venta
├── stock         → Productos almacenados
├── reprocessed   → Productos re-procesados ✨ NUEVO
└── balance       → Balance de productos (faltantes y sobras) ✨ NUEVO
```

---

## 🔄 Versiones

- **v1**: Nodos de venta y stock iniciales (un nodo por producto+pedido/almacén)
- **v2**: Un nodo por producto con arrays internos
- **v3**: Un nodo por nodo final (agrupa todos los productos)
- **v4**: v3 + nodos de re-procesados y balance ✨ **ACTUAL**

---

## 📚 Archivos Relacionados

### Ejemplos JSON
- [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v3.json`](../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v3.json) (versión anterior)
- [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json`](../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json) (versión actual)

### Documentación de Cambios
- **[`FRONTEND-Migracion-Missing-a-Balance.md`](./FRONTEND-Migracion-Missing-a-Balance.md)** ⚠️ **ACTUAL** - Migración missing → balance
- [`../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v2.md`](../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v2.md) (v1 → v2)
- [`../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md`](../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md) (v2 → v3)
- [`RESUMEN-Documentacion-Frontend-v4.md`](./RESUMEN-Documentacion-Frontend-v4.md) (resumen v4)
- [`../cambios/CAMBIO-Nodo-Missing-a-Balance.md`](../cambios/CAMBIO-Nodo-Missing-a-Balance.md) (documentación del cambio en backend)

---

**Para más detalles, consulta los documentos individuales** 📖

