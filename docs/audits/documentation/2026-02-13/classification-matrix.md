# Matriz de Clasificación

**FASE 2 — Auditoría de documentación PesquerApp Backend**  
**Fecha:** 2026-02-13

**Criterios de criticidad:**
- **CRÍTICO:** Componentes core de producción (entrada al proyecto, arquitectura, auth, deploy básico, referencia API/datos).
- **ALTO:** Características principales (módulos de negocio, referencia operativa).
- **MEDIO:** Utilidades, guías frontend, ejemplos, análisis y cambios concretos.
- **BAJO:** Contexto, histórico, planes pendientes, backlog.

**Estado:** ✅ Actualizado y usable | ⚠️ Obsoleto o incompleto | ❌ Críticamente incompleto o obsoleto

---

## Raíz y database

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| README.md | Setup | CRÍTICO | 85% | ✅ | Punto de entrada; enlaza docs y Sail. |
| database/migrations/companies/README.md | Referencia | MEDIO | 40% | ⚠️ | Última mod. 2025-08; revisar vigencia. |

---

## docs/ — Raíz e índice

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/README.md | Referencia | CRÍTICO | 90% | ✅ | Índice general API v2. |
| docs/PROBLEMAS-CRITICOS.md | Referencia | CRÍTICO | 85% | ✅ | Resumen 25 problemas; enlace a 98. |
| docs/INVENTORY.md | Auditoría | BAJO | 95% | ✅ | Salida FASE 1. |
| docs/prompts/PESQUERAPP_DOCUMENTATION_AUDIT_PROMPT.md | Auditoría | BAJO | 100% | ✅ | Especificación del agente. |

## docs/ — Estructura estándar (00–15, deployment, troubleshooting, 13, 14)

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/overview.md | Referencia | CRÍTICO | 90% | ✅ | Mapa navegación; enlaces a dominio. |
| docs/setup-local.md | Setup | CRÍTICO | 85% | ✅ | Hub → instrucciones + fundamentos/03. |
| docs/environment-variables.md | Setup | CRÍTICO | 75% | ✅ | Hub → fundamentos/03. |
| docs/architecture.md | Arquitectura | CRÍTICO | 85% | ✅ | Hub → fundamentos/00, 01, 02. |
| docs/database.md | BD | CRÍTICO | 75% | ✅ | Hub → referencia/95, migraciones. |
| docs/queues-jobs.md | Operaciones | ALTO | 70% | ✅ | Contenido breve; enlaces. |
| docs/scheduler-cron.md | Operaciones | ALTO | 70% | ✅ | Contenido breve. |
| docs/storage-files.md | Operaciones | ALTO | 70% | ✅ | Hub → utilidades. |
| docs/api-rest.md | API | CRÍTICO | 80% | ✅ | Hub → API-references, referencia/97. |
| docs/testing.md | Testing | ALTO | 65% | ⚠️ | Por completar ejemplos. |
| docs/observability-monitoring.md | Operaciones | ALTO | 65% | ⚠️ | Por completar. |
| docs/deployment/ (11a–11e) | Despliegue | CRÍTICO | 60–80% | ⚠️ | 11a, 11c presentes; detalle en instrucciones. |
| docs/troubleshooting/ | Referencia | CRÍTICO | 75% | ✅ | COMMON-ERRORS, DEBUGGING, PERFORMANCE. |
| docs/postmortems/ | Referencia | MEDIO | 40% | ⚠️ | Solo README; sin informes. |
| docs/architecture-decisions/ | ADR | ALTO | 40% | ⚠️ | Solo README; sin ADRs. |
| docs/multi-tenant-specs.md | Arquitectura | CRÍTICO | 80% | ✅ | Hub → fundamentos/01. |

---

## docs/00_ POR IMPLEMENTAR

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/00_ POR IMPLEMENTAR/guia-entorno-desarrollo-pesquerapp.md | Setup | ALTO | 70% | ⚠️ | Por integrar; solapa con instrucciones. |
| docs/00_ POR IMPLEMENTAR/IMPORTANTE/resumen-problema-solucion-productos-variantes.md | Plan | MEDIO | 50% | ⚠️ | Contexto productos/variantes/GS1. |

---

## docs/fundamentos

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/fundamentos/00-Introduccion.md | Arquitectura | CRÍTICO | 85% | ✅ | Visión y arquitectura. |
| docs/fundamentos/01-Arquitectura-Multi-Tenant.md | Arquitectura | CRÍTICO | 80% | ✅ | Multi-tenant, middleware, BD. |
| docs/fundamentos/02-Autenticacion-Autorizacion.md | Arquitectura | CRÍTICO | 85% | ✅ | Sanctum, magic link, OTP. |
| docs/fundamentos/03-Configuracion-Entorno.md | Setup | CRÍTICO | 75% | ✅ | .env, variables. |

---

## docs/instrucciones

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/instrucciones/deploy-desarrollo.md | Despliegue | CRÍTICO | 85% | ✅ | Sail, scripts. |
| docs/instrucciones/deploy-desarrollo-guiado.md | Despliegue | CRÍTICO | 85% | ✅ | Paso a paso primera vez. |
| docs/instrucciones/instalar-docker-wsl.md | Setup | ALTO | 80% | ✅ | Docker en WSL. |
| docs/instrucciones/IMPLEMENTATION_PLAN_DOCKER_SAIL.md | Despliegue | ALTO | 80% | ✅ | Plan Sail. |
| docs/instrucciones/EXECUTION_CHECKLIST.md | Despliegue | ALTO | 80% | ✅ | Checklist bloques. |
| docs/instrucciones/FINAL_VALIDATION_REPORT.md | Despliegue | MEDIO | 85% | ✅ | Validación Sail. |

---

## docs/frontend

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/frontend/Guia-Auth-Magic-Link-OTP.md | Frontend | ALTO | 85% | ✅ | Integración auth. |
| docs/frontend/Guia-Cambios-Roles-API-Paso-2.md | Frontend | ALTO | 80% | ✅ | Roles API. |
| docs/frontend/SETTINGS-EMAIL-CONFIGURATION.md | Frontend | MEDIO | 80% | ✅ | Email settings. |
| docs/frontend/SETTINGS-EMAIL-RESUMEN.md | Frontend | MEDIO | 75% | ✅ | Resumen email. |

---

## docs/API-references

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/api-references/README.md | API | CRÍTICO | 85% | ✅ | Índice módulos. |
| docs/api-references/autenticacion/README.md | API | CRÍTICO | 75% | ✅ | Endpoints auth. |
| docs/api-references/sistema/README.md | API | ALTO | 75% | ✅ | Usuarios, roles. |
| docs/api-references/catalogos/README.md | API | ALTO | 70% | ✅ | Catálogos. |
| docs/api-references/pedidos/README.md | API | ALTO | 70% | ✅ | Pedidos. |
| docs/api-references/inventario/README.md | API | ALTO | 70% | ✅ | Inventario. |
| docs/api-references/produccion/README.md | API | ALTO | 70% | ✅ | Producción. |
| docs/api-references/produccion-costos/README.md | API | ALTO | 65% | ✅ | Costes producción. |
| docs/api-references/recepciones-despachos/README.md | API | ALTO | 70% | ✅ | Recepciones/despachos. |
| docs/api-references/utilidades/README.md | API | MEDIO | 65% | ✅ | PDF, Excel, IA. |
| docs/api-references/productos/README.md | API | ALTO | 70% | ✅ | Productos. |
| docs/api-references/estadisticas/README.md | API | MEDIO | 65% | ✅ | Estadísticas. |

---

## docs/produccion (principal)

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/produccion/10-Produccion-General.md | Módulo | ALTO | 85% | ✅ | Visión módulo. |
| docs/produccion/11-Produccion-Lotes.md | Módulo | ALTO | 80% | ✅ | Lotes. |
| docs/produccion/12-Produccion-Procesos.md | Módulo | ALTO | 80% | ✅ | Procesos. |
| docs/produccion/12-Produccion-Procesos-ENDPOINT-GET.md | API | ALTO | 75% | ✅ | GET procesos. |
| docs/produccion/13-Produccion-Entradas.md | Módulo | ALTO | 75% | ✅ | Entradas. |
| docs/produccion/14-Produccion-Salidas.md | Módulo | ALTO | 75% | ✅ | Salidas. |
| docs/produccion/15-Produccion-Consumos-Outputs-Padre.md | Módulo | ALTO | 75% | ✅ | Consumos/outputs padre. |
| docs/produccion/ANALISIS-ERRORES-IMPLEMENTACION-COSTES.md | Plan | MEDIO | 50% | ⚠️ | Análisis errores costes. |
| docs/produccion/DOCUMENTACION-FRONTEND-Trazabilidad-Costes.md | Frontend | MEDIO | 60% | ⚠️ | Trazabilidad costes. |
| docs/produccion/ENDPOINT-Available-Products-For-Outputs.md | API | MEDIO | 70% | ✅ | Endpoint productos. |
| docs/produccion/FRONTEND-Consumos-Outputs-Padre.md | Frontend | MEDIO | 65% | ✅ | Consumos frontend. |
| docs/produccion/FRONTEND-Salidas-y-Consumos-Multiples.md | Frontend | MEDIO | 65% | ✅ | Salidas/consumos múltiples. |
| docs/produccion/INVESTIGACION-Salidas-y-Consumos.md | Plan | MEDIO | 50% | ⚠️ | Investigación. |
| docs/produccion/PROPUESTA-Trazabilidad-Costes-Producciones.md | Plan | MEDIO | 50% | ⚠️ | Propuesta costes. |
| docs/produccion/REFACTORIZACION-PRODUCCIONES-V2.md | Plan | MEDIO | 55% | ⚠️ | Refactor v2. |
| docs/produccion/RESUMEN-Implementacion-Multiples.md | Referencia | MEDIO | 60% | ✅ | Resumen múltiples. |

---

## docs/produccion/analisis

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/produccion/analisis/README.md | Referencia | MEDIO | 70% | ✅ | Índice análisis. |
| docs/produccion/analisis/ACTUALIZACION-ESTRUCTURA-FINAL-v3.md | Plan | MEDIO | 55% | ⚠️ | Estructura v3. |
| docs/produccion/analisis/ANALISIS-Datos-No-Nodos-Production-Tree.md | Plan | MEDIO | 50% | ⚠️ | Datos no nodos. |
| docs/produccion/analisis/ANALISIS-Nodo-No-Contabilizado.md | Plan | MEDIO | 50% | ⚠️ | Nodo no contabilizado. |
| docs/produccion/analisis/CONCILIACION-Nodo-Missing-vs-General.md | Plan | MEDIO | 50% | ⚠️ | Conciliación nodos. |
| docs/produccion/analisis/CONDICIONES-NODO-FINAL-PRODUCCION.md | Plan | MEDIO | 55% | ⚠️ | Condiciones nodo final. |
| docs/produccion/analisis/CONFIRMACION-Estructura-Final.md | Plan | MEDIO | 50% | ⚠️ | Confirmación estructura. |
| docs/produccion/analisis/DISENO-Conciliacion-Detallada-Productos.md | Plan | MEDIO | 50% | ⚠️ | Diseño conciliación. |
| docs/produccion/analisis/DISENO-Nodos-Re-procesados-y-Faltantes.md | Plan | MEDIO | 55% | ⚠️ | Nodos re-procesados. |
| docs/produccion/analisis/DISENO-Nodos-Venta-y-Stock-Production-Tree.md | Plan | MEDIO | 55% | ⚠️ | Nodos venta/stock. |
| docs/produccion/analisis/IMPLEMENTACION-Conciliacion-Detallada-Productos.md | Plan | MEDIO | 50% | ⚠️ | Implementación conciliación. |
| docs/produccion/analisis/IMPLEMENTACION-Nodos-Re-procesados-y-Faltantes.md | Plan | MEDIO | 55% | ⚠️ | Implementación nodos. |
| docs/produccion/analisis/INVESTIGACION-Impacto-Cajas-Disponibles-Palets.md | Plan | MEDIO | 50% | ⚠️ | Cajas/palets. |
| docs/produccion/analisis/RESUMEN-Decision-Dos-Nodos.md | Plan | MEDIO | 50% | ⚠️ | Decisión dos nodos. |
| docs/produccion/analisis/RESUMEN-Estructura-Final-Nodos.md | Plan | MEDIO | 50% | ⚠️ | Estructura final. |

---

## docs/produccion/cambios

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/produccion/cambios/README.md | Referencia | MEDIO | 70% | ✅ | Índice cambios. |
| docs/produccion/cambios/CAMBIO-Nodo-Missing-a-Balance.md | Plan | MEDIO | 55% | ⚠️ | Nodo missing/balance. |
| docs/produccion/cambios/CAMBIOS-Conciliacion-Endpoint-Produccion.md | Plan | MEDIO | 55% | ⚠️ | Cambios conciliación. |
| docs/produccion/cambios/CONCILIACION-Productos-No-Producidos-Formato.md | Plan | MEDIO | 55% | ⚠️ | Formato conciliación. |
| docs/produccion/cambios/FIX-Conciliacion-Productos-No-Producidos.md | Plan | MEDIO | 60% | ⚠️ | Fix conciliación. |
| docs/produccion/cambios/FIX-Nodo-Missing-Balance-Completo.md | Plan | MEDIO | 55% | ⚠️ | Fix nodo balance. |
| docs/produccion/cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v2.md | Frontend | MEDIO | 60% | ⚠️ | Cambios frontend v2. |
| docs/produccion/cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md | Frontend | MEDIO | 60% | ⚠️ | Cambios frontend v3. |

---

## docs/produccion/frontend

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/produccion/frontend/README.md | Referencia | ALTO | 80% | ✅ | Índice doc frontend. |
| docs/produccion/frontend/README-Documentacion-Frontend.md | Referencia | MEDIO | 75% | ✅ | Doc frontend. |
| docs/produccion/frontend/FRONTEND-Cajas-Disponibles.md | Frontend | ALTO | 75% | ✅ | Cajas disponibles. |
| docs/produccion/frontend/FRONTEND-Guia-Rapida-Nodos-Completos.md | Frontend | ALTO | 80% | ✅ | Guía rápida nodos. |
| docs/produccion/frontend/FRONTEND-Migracion-Missing-a-Balance.md | Frontend | MEDIO | 70% | ✅ | Migración missing/balance. |
| docs/produccion/frontend/FRONTEND-Nodos-Re-procesados-y-Faltantes.md | Frontend | MEDIO | 70% | ✅ | Nodos re-procesados. |
| docs/produccion/frontend/FRONTEND-Nodos-Venta-y-Stock-Diagrama.md | Frontend | MEDIO | 75% | ✅ | Diagrama venta/stock. |
| docs/produccion/frontend/FRONTEND-Relaciones-Padre-Hijo-Nodos.md | Frontend | MEDIO | 70% | ✅ | Relaciones padre/hijo. |
| docs/produccion/frontend/RESUMEN-Documentacion-Frontend-v4.md | Referencia | MEDIO | 75% | ✅ | Resumen v4. |
| docs/produccion/frontend/VERIFICACION-DOCS-FRONTEND.md | Referencia | MEDIO | 70% | ✅ | Verificación docs. |

---

## docs/pedidos

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/pedidos/20-Pedidos-General.md | Módulo | ALTO | 80% | ✅ | Visión pedidos. |
| docs/pedidos/21-Pedidos-Detalles-Planificados.md | Módulo | ALTO | 75% | ✅ | Detalles planificados. |
| docs/pedidos/22-Pedidos-Documentos.md | Módulo | ALTO | 75% | ✅ | Documentos PDF/email. |
| docs/pedidos/23-Pedidos-Incidentes.md | Módulo | ALTO | 75% | ✅ | Incidentes. |
| docs/pedidos/24-Pedidos-Estadisticas.md | Módulo | ALTO | 75% | ✅ | Estadísticas. |

---

## docs/inventario

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/inventario/30-Almacenes.md | Módulo | ALTO | 80% | ✅ | Almacenes. |
| docs/inventario/31-Palets.md | Módulo | ALTO | 80% | ✅ | Palets. |
| docs/inventario/31-Palets-Estados-Fijos.md | Módulo | ALTO | 75% | ✅ | Estados fijos palets. |
| docs/inventario/32-Cajas.md | Módulo | ALTO | 80% | ✅ | Cajas. |
| docs/inventario/33-Estadisticas-Stock.md | Módulo | ALTO | 75% | ✅ | Estadísticas stock. |

---

## docs/catalogos

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/catalogos/40-Productos.md | Módulo | ALTO | 85% | ✅ | Productos. |
| docs/catalogos/40-Productos-EJEMPLOS.md | Referencia | MEDIO | 80% | ✅ | Ejemplos productos. |
| docs/catalogos/41-Categorias-Familias-Productos.md | Módulo | ALTO | 75% | ✅ | Categorías/familias. |
| docs/catalogos/42-Especies.md | Módulo | ALTO | 75% | ✅ | Especies. |
| docs/catalogos/43-Zonas-Captura.md | Módulo | ALTO | 75% | ✅ | Zonas captura. |
| docs/catalogos/44-Clientes.md | Módulo | ALTO | 75% | ✅ | Clientes. |
| docs/catalogos/45-Proveedores.md | Módulo | ALTO | 75% | ✅ | Proveedores. |
| docs/catalogos/46-Transportes.md | Módulo | MEDIO | 75% | ✅ | Transportes. |
| docs/catalogos/47-Vendedores.md | Módulo | MEDIO | 75% | ✅ | Vendedores. |
| docs/catalogos/48-Terminos-Pago.md | Módulo | MEDIO | 75% | ✅ | Términos pago. |
| docs/catalogos/49-Paises.md | Módulo | MEDIO | 75% | ✅ | Países. |
| docs/catalogos/50-Incoterms.md | Módulo | MEDIO | 75% | ✅ | Incoterms. |
| docs/catalogos/51-Arte-Pesquera.md | Módulo | MEDIO | 75% | ✅ | Artes pesca. |
| docs/catalogos/52-Impuestos.md | Módulo | MEDIO | 75% | ✅ | Impuestos. |
| docs/catalogos/53-Procesos.md | Módulo | MEDIO | 75% | ✅ | Procesos. |

---

## docs/recepciones-despachos

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/recepciones-despachos/60-Recepciones-Materia-Prima.md | Módulo | ALTO | 85% | ✅ | Recepciones MP. |
| docs/recepciones-despachos/61-Despachos-Cebo.md | Módulo | ALTO | 75% | ✅ | Despachos cebo. |
| docs/recepciones-despachos/62-Liquidacion-Proveedores.md | Módulo | ALTO | 80% | ✅ | Liquidación. |
| docs/recepciones-despachos/62-Liquidacion-Proveedores-ERRORES-CORREGIDOS.md | Plan | MEDIO | 65% | ⚠️ | Errores corregidos. |
| docs/recepciones-despachos/62-Liquidacion-Proveedores-FRONTEND.md | Frontend | MEDIO | 70% | ✅ | Frontend liquidación. |
| docs/recepciones-despachos/62-Liquidacion-Proveedores-SELECCION-PDF.md | Módulo | MEDIO | 70% | ✅ | Selección PDF. |
| docs/recepciones-despachos/62-Plan-Implementacion-Recepciones-Palets-Costes.md | Plan | MEDIO | 55% | ⚠️ | Plan implementación. |
| docs/recepciones-despachos/63-Guia-Frontend-Recepciones-Palets.md | Frontend | MEDIO | 70% | ✅ | Guía frontend palets. |
| docs/recepciones-despachos/63-Liquidacion-Proveedores-PAGOS-GASTOS.md | Módulo | MEDIO | 70% | ✅ | Pagos/gastos. |
| docs/recepciones-despachos/64-Guia-Frontend-Edicion-Recepciones.md | Frontend | MEDIO | 70% | ✅ | Edición recepciones. |
| docs/recepciones-despachos/65-Guia-Backend-Edicion-Recepciones.md | Módulo | MEDIO | 70% | ✅ | Backend edición. |
| docs/recepciones-despachos/66-Cambios-Frontend-Estructura-Pallets-Precios.md | Frontend | MEDIO | 65% | ✅ | Cambios palets/precios. |
| docs/recepciones-despachos/67-Guia-Backend-v1-Recepcion-Lineas-Palet-Automatico.md | Módulo | MEDIO | 65% | ⚠️ | Mención v1; verificar vigencia. |
| docs/recepciones-despachos/68-Analisis-Cambios-API-v1-Migraciones.md | Plan | MEDIO | 50% | ⚠️ | Análisis v1. |
| docs/recepciones-despachos/68-Guia-Frontend-Cambios-Estructura-Pallets.md | Frontend | MEDIO | 65% | ✅ | Cambios estructura. |
| docs/recepciones-despachos/69-Cambio-API-Precios-Respuesta-Recepciones.md | API | MEDIO | 70% | ✅ | API precios. |
| docs/recepciones-despachos/69-Diseno-Edicion-Cajas-Disponibles-Recepciones.md | Plan | MEDIO | 65% | ✅ | Diseño cajas disponibles. |
| docs/recepciones-despachos/70-Guia-Frontend-Edicion-Cajas-Disponibles.md | Frontend | MEDIO | 70% | ✅ | Guía edición cajas. |

---

## docs/etiquetas

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/etiquetas/70-Etiquetas.md | Módulo | ALTO | 75% | ✅ | Sistema etiquetas. |

---

## docs/sistema

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/sistema/80-Usuarios.md | Módulo | CRÍTICO | 85% | ✅ | Usuarios. |
| docs/sistema/81-Roles.md | Módulo | CRÍTICO | 85% | ✅ | Roles. |
| docs/sistema/81-Roles-Plan-Migracion-Enum.md | Plan | MEDIO | 45% | ⚠️ | Plan enum; pendiente. |
| docs/sistema/82-Roles-Pasos-2-y-3-Pendientes.md | Plan | MEDIO | 40% | ⚠️ | Pasos pendientes. |
| docs/sistema/82-Sesiones.md | Módulo | ALTO | 75% | ✅ | Sesiones. |
| docs/sistema/83-Logs-Actividad.md | Módulo | ALTO | 75% | ✅ | Logs. |
| docs/sistema/84-Configuracion.md | Módulo | ALTO | 75% | ✅ | Configuración. |
| docs/sistema/85-Control-Horario.md | Módulo | ALTO | 75% | ✅ | Control horario. |
| docs/sistema/86-Control-Horario-FRONTEND.md | Frontend | MEDIO | 70% | ✅ | Control horario frontend. |
| docs/sistema/87-Plan-Auth-Magic-Link-OTP.md | Plan | ALTO | 50% | ⚠️ | Plan auth; parcialmente implementado. |
| docs/sistema/88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md | Módulo | ALTO | 80% | ✅ | Tokens, reenvío invitación. |
| docs/sistema/89-Auth-Contrasenas-Eliminadas.md | Referencia | ALTO | 75% | ✅ | Sin contraseñas. |
| docs/sistema/90-Analisis-Sin-Rastro-Password.md | Plan | MEDIO | 45% | ⚠️ | Análisis histórico. |

---

## docs/utilidades

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/utilidades/90-Generacion-PDF.md | Módulo | ALTO | 80% | ✅ | PDF. |
| docs/utilidades/91-Exportacion-Excel.md | Módulo | ALTO | 75% | ✅ | Excel. |
| docs/utilidades/92-Extraccion-Documentos-AI.md | Módulo | ALTO | 75% | ✅ | IA, documentos. |
| docs/utilidades/93-Plan-Integracion-Tesseract-OCR.md | Plan | MEDIO | 50% | ⚠️ | Plan Tesseract; pendiente. |

---

## docs/referencia

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/referencia/95-Modelos-Referencia.md | Referencia | CRÍTICO | 85% | ✅ | Modelos Eloquent. |
| docs/referencia/96-Recursos-API.md | Referencia | CRÍTICO | 80% | ✅ | API Resources. |
| docs/referencia/96-Restricciones-Entidades.md | Referencia | ALTO | 75% | ✅ | Restricciones. |
| docs/referencia/97-Rutas-Completas.md | Referencia | CRÍTICO | 85% | ✅ | Rutas v2. |
| docs/referencia/98-Errores-Comunes.md | Referencia | CRÍTICO | 85% | ✅ | 59 errores documentados. |
| docs/referencia/99-Glosario.md | Referencia | ALTO | 80% | ✅ | Glosario. |
| docs/referencia/100-Rendimiento-Endpoints.md | Plan | MEDIO | 65% | ✅ | Análisis rendimiento. |
| docs/referencia/101-Plan-Mejoras-GET-orders-id.md | Plan | MEDIO | 50% | ⚠️ | Plan mejora endpoint. |
| docs/referencia/102-Plan-Mejoras-GET-orders-active.md | Plan | MEDIO | 50% | ⚠️ | Plan mejora endpoint. |
| docs/referencia/ANALISIS-API-FRONTEND-BACKEND.md | Referencia | ALTO | 70% | ✅ | Análisis API vs frontend. |
| docs/referencia/PLAN-ELIMINACION-ARTICLE.md | Plan | MEDIO | 50% | ⚠️ | Migración Article→Product. |

---

## docs/por-hacer

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/por-hacer/README.md | Referencia | BAJO | 50% | ⚠️ | Backlog. |
| docs/por-hacer/01-Revision-Validaciones-Cliente-Pedido.md | Plan | MEDIO | 45% | ⚠️ | Pendiente revisión. |

---

## docs/ejemplos

| Documento | Tipo | Criticidad | Completitud | Estado | Notas |
|-----------|------|------------|-------------|--------|-------|
| docs/ejemplos/README.md | Referencia | MEDIO | 80% | ✅ | Índice ejemplos. |
| docs/ejemplos/EJEMPLO-RESPUESTA-PALLET.md | Referencia | MEDIO | 85% | ✅ | Ejemplo pallet. |
| docs/ejemplos/EJEMPLO-RESPUESTA-process-tree-v3.md | Referencia | MEDIO | 80% | ✅ | process-tree v3. |
| docs/ejemplos/EJEMPLO-RESPUESTA-process-tree-v4.md | Referencia | MEDIO | 80% | ✅ | process-tree v4. |
| docs/ejemplos/EJEMPLO-RESPUESTA-process-tree-v5-con-conciliacion.md | Referencia | MEDIO | 80% | ✅ | process-tree v5. |
| docs/ejemplos/EJEMPLO-RESPUESTA-production-record-completo.md | Referencia | MEDIO | 80% | ✅ | production-record. |

---

## Hallazgos por Dominio (FASE 2)

### ✅ Documentación Sólida
- **Raíz:** README.md, CHANGELOG.md, ROADMAP.md, SECURITY.md, TECH_DEBT.md
- **docs/ estándar:** overview, 01–15, deployment (11a–11e), troubleshooting, README, PROBLEMAS-CRITICOS
- **fundamentos/:** 00-Introduccion, 01-Arquitectura-Multi-Tenant, 02-Autenticacion-Autorizacion, 03-Configuracion-Entorno
- **instrucciones/:** deploy-desarrollo, deploy-desarrollo-guiado, instalar-docker-wsl, plan Sail, checklists
- **API-references/:** Todos los README por módulo
- **Módulos de negocio:** pedidos (20–24), inventario (30–33), catalogos (40–53), recepciones-despachos (60–70), sistema (80–90), utilidades (90–93)
- **referencia/:** 95-Modelos, 96-Recursos/Restricciones, 97-Rutas, 98-Errores-Comunes, 99-Glosario
- **produccion/:** 10–15, frontend/ (guías nodos), ejemplos

### ⚠️ Documentación que Necesita Actualización
- **database/migrations/companies/README.md** — Última mod. 2025-08; revisar vigencia
- **docs/00_ POR IMPLEMENTAR/** — Guías por integrar en 01-SETUP o instrucciones
- **Planes incompletos:** sistema (81-Roles-Plan-Migracion-Enum, 82-Roles-Pasos-2-y-3, 87-Plan-Auth, 90-Analisis-Sin-Rastro), utilidades (93-Plan-Tesseract), referencia (101, 102, PLAN-ELIMINACION-ARTICLE), por-hacer
- **produccion/analisis y cambios** — Muchos docs de diseño/plan marcados ⚠️; conservar como histórico o actualizar estado
- **recepciones-despachos/67-Guia-Backend-v1** — Mención v1; verificar si vigente o deprecar

### 🔴 Documentación que Requiere Reescritura Profunda
- **Ninguna** identificada con estado ❌. Los documentos ⚠️ se consideran actualizables sin reescritura completa.

### 🗑️ Documentación Candidata a Deprecación (o archivo en postmortems)
- Ninguna explícita. **67-Guia-Backend-v1-Recepcion-Lineas-Palet-Automatico.md** — Si API v1 ya no existe, mover a postmortems o añadir banner de deprecación.

---

## Resumen por criticidad

| Criticidad | Cantidad (aprox.) | Observación |
|------------|-------------------|-------------|
| CRÍTICO | 18 | Entrada, arquitectura, auth, deploy, referencia API/modelos/rutas/errores. |
| ALTO | 65 | Módulos de negocio, API-references, sistema, instrucciones, frontend clave. |
| MEDIO | 75 | Planes, análisis, guías frontend, utilidades, ejemplos, cambios. |
| BAJO | 4 | Por implementar, auditoría, backlog. |

## Resumen por estado

| Estado | Cantidad (aprox.) |
|-------|-------------------|
| ✅ Actualizado | ~125 |
| ⚠️ Obsoleto/Incompleto | ~37 |
| ❌ Crítico | 0 |

---

**Siguiente paso:** FASE 3 completada → [DOCUMENTATION_MAPPING_MATRIX.md](./DOCUMENTATION_MAPPING_MATRIX.md). FASE 4–5: Huérfanos, plan de reestructuración.
