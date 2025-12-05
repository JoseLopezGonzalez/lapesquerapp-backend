# 📚 Documentación Frontend - Production Tree

**Última actualización**: 2025-01-27  
**Versión actual**: v4 (con nodos re-procesados y faltantes)

---

## 🎯 Endpoint

```
GET /v2/productions/{id}/process-tree
```

---

## 📋 Documentos Disponibles

### Para Empezar (Recomendado)

1. **🚀 `FRONTEND-Guia-Rapida-Nodos-Completos.md`**
   - Guía rápida y visual
   - Estructura de los 4 tipos de nodos
   - Ejemplos simplificados
   - ⭐ **Empezar aquí**

2. **📊 `EJEMPLO-RESPUESTA-process-tree-v4-completo.json`**
   - Ejemplo JSON completo
   - Listo para usar en desarrollo
   - Incluye todos los tipos de nodos

3. **📖 `EJEMPLO-RESPUESTA-process-tree-v4.md`**
   - Explicación detallada del ejemplo
   - Balance completo
   - Casos de uso

### Documentación Detallada

4. **📘 `FRONTEND-Nodos-Re-procesados-y-Faltantes.md`**
   - Documentación completa de los nuevos nodos
   - Estructura detallada
   - Tipos TypeScript
   - Casos de uso

5. **📗 `FRONTEND-Cambios-Nodos-Venta-Stock-v3.md`**
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
└── missing       → Productos faltantes ✨ NUEVO
```

---

## 🔄 Versiones

- **v1**: Nodos de venta y stock iniciales (un nodo por producto+pedido/almacén)
- **v2**: Un nodo por producto con arrays internos
- **v3**: Un nodo por nodo final (agrupa todos los productos)
- **v4**: v3 + nodos de re-procesados y faltantes ✨ **ACTUAL**

---

## 📚 Archivos Relacionados

### Ejemplos JSON
- `EJEMPLO-RESPUESTA-process-tree-v3.json` (versión anterior)
- `EJEMPLO-RESPUESTA-process-tree-v4-completo.json` (versión actual)

### Documentación de Cambios
- `FRONTEND-Cambios-Nodos-Venta-Stock-v2.md` (v1 → v2)
- `FRONTEND-Cambios-Nodos-Venta-Stock-v3.md` (v2 → v3)
- `RESUMEN-Documentacion-Frontend-v4.md` (resumen v4)

---

**Para más detalles, consulta los documentos individuales** 📖

