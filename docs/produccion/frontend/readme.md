# Documentación Frontend - Production Tree

Esta carpeta contiene toda la documentación relacionada con el frontend del módulo de producción, específicamente el endpoint `GET /v2/productions/{id}/process-tree`.

## 📚 Estructura de Documentación

### 🚀 Para Empezar

1. **[README-Documentacion-Frontend.md](./README-Documentacion-Frontend.md)** - Índice general de la documentación frontend
2. **[FRONTEND-Guia-Rapida-Nodos-Completos.md](./FRONTEND-Guia-Rapida-Nodos-Completos.md)** - Guía rápida y visual de los nodos
3. **[FRONTEND-Migracion-Missing-a-Balance.md](./FRONTEND-Migracion-Missing-a-Balance.md)** ⚠️ **LEER PRIMERO** - Guía de migración breaking change

### 📖 Documentación Detallada

- **[Pantalla de control de producciones](./pantalla-control-producciones.md)** - Propuesta inicial para panel de resumen, alertas, conciliacion, trazabilidad y costes
- **[FRONTEND-Nodos-Re-procesados-y-Faltantes.md](./FRONTEND-Nodos-Re-procesados-y-Faltantes.md)** - Documentación completa de nodos re-procesados y balance
- **[FRONTEND-Relaciones-Padre-Hijo-Nodos.md](./FRONTEND-Relaciones-Padre-Hijo-Nodos.md)** - Explicación de relaciones entre nodos
- **[FRONTEND-Nodos-Venta-y-Stock-Diagrama.md](./FRONTEND-Nodos-Venta-y-Stock-Diagrama.md)** - Diagrama de nodos de venta y stock
- **[FRONTEND-Cajas-Disponibles.md](./FRONTEND-Cajas-Disponibles.md)** - Documentación sobre cajas disponibles

### 📊 Resúmenes

- **[RESUMEN-Documentacion-Frontend-v4.md](./RESUMEN-Documentacion-Frontend-v4.md)** - Resumen de la versión v4
- **[VERIFICACION-DOCS-FRONTEND.md](./VERIFICACION-DOCS-FRONTEND.md)** - Verificación de documentación

### 🔗 Documentación Relacionada

- **Cambios y migraciones**: Ver [`../cambios/`](../cambios/)
- **Análisis y diseño**: Ver [`../analisis/`](../analisis/)
- **Ejemplos JSON**: Ver [`../../ejemplos/`](../../ejemplos/)

## 🎯 Versiones

- **v1**: Nodos de venta y stock iniciales
- **v2**: Un nodo por producto con arrays internos
- **v3**: Un nodo por nodo final (agrupa todos los productos)
- **v4**: v3 + nodos de re-procesados y balance ✨ **ACTUAL**

## 📝 Notas

- Todos los documentos hacen referencia al endpoint `/v2/productions/{id}/process-tree`
- La versión actual es **v4** con nodos `sales`, `stock`, `reprocessed` y `balance`
- El nodo `missing` fue renombrado a `balance` (ver migración)
