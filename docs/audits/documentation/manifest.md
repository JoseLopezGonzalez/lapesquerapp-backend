# MANIFEST — Inventario de Documentación PesquerApp Backend

**Fecha:** 2026-02-16
**Total documentos:** 312 archivos | **~88.205 líneas**

---

## Estadísticas Globales

| Categoría | Archivos | Líneas | % del total |
|-----------|----------|--------|-------------|
| `docs/` — Documentación principal | 196 | 80.788 | 91,6% |
| `.ai_work_context/` — Sesiones IA | 67 | 2.435 | 2,8% |
| `.agents/skills/` — Skills IA | 11 | 3.566 | 4,0% |
| `.ai_standards/` — Estándares IA | 5 | 573 | 0,6% |
| Raíz del proyecto | 8 | 551 | 0,6% |
| `database/` | 1 | 270 | 0,3% |
| `.scribe/` | 2 | 20 | <0,1% |
| Otros (`public/robots.txt`) | 1 | 2 | <0,1% |

---

## Índice por Categoría

### 1. Raíz del Proyecto

| Archivo | Líneas | Estado | Relevancia |
|---------|--------|--------|------------|
| CLAUDE.md | 230 | ✅ | Crítico |
| README.md | 108 | ⚠️ | Crítico |
| SECURITY.md | 84 | ✅ | Crítico |
| QUICK_START.md | 69 | ✅ | Importante |
| EXECUTION_CHECKLIST.md | 39 | ✅ | Importante |
| CHANGELOG.md | 7 | Stub (ref. tags) | Importante |
| ROADMAP.md | — | Movido a docs/por-hacer/ | — |
| TECH_DEBT.md | — | Movido a docs/por-hacer/ | — |

### 2. docs/ — Estructura Canónica (00-15)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| docs/overview.md | 90 | ✅ |
| docs/core-consolidation-plan-erp-saas.md | 544 | ✅ |
| docs/setup-local.md | 36 | ✅ |
| docs/environment-variables.md | 29 | ✅ |
| docs/architecture.md | 28 | ✅ |
| docs/database.md | 34 | ✅ |
| docs/queues-jobs.md | 62 | ✅ |
| docs/scheduler-cron.md | 68 | ✅ |
| docs/storage-files.md | 29 | ✅ |
| docs/api-rest.md | 39 | ✅ |
| docs/testing.md | 244 | ✅ |
| docs/observability-monitoring.md | 48 | ⚠️ Genérico |
| docs/deployment/11a-DEVELOPMENT.md | 13 | ✅ |
| docs/deployment/11b-STAGING.md | 3 | 🗑️ Stub |
| docs/deployment/11c-PRODUCTION.md | 110 | ✅ |
| docs/deployment/11d-ROLLBACK-PROCEDURES.md | 3 | 🗑️ Stub |
| docs/deployment/11e-RUNBOOK.md | 3 | 🗑️ Stub |
| docs/troubleshooting/COMMON-ERRORS.md | 9 | ✅ |
| docs/troubleshooting/DEBUGGING-GUIDE.md | 5 | 🗑️ Stub |
| docs/troubleshooting/PERFORMANCE-ISSUES.md | 7 | ✅ |
| docs/postmortems/README.md | 5 | 🗑️ Stub |
| docs/architecture-decisions/README.md | 18 | ✅ |
| docs/architecture-decisions/0000-ADR-TEMPLATE.md | 36 | ✅ |
| docs/architecture-decisions/0001-API-v2-only.md | 42 | ✅ |
| docs/multi-tenant-specs.md | 25 | ✅ |
| docs/README.md | 211 | ✅ |

### 3. docs/fundamentos (5 archivos)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| 00-Introduccion.md | 324 | ✅ |
| 01-Arquitectura-Multi-Tenant.md | 509 | ✅ |
| 02-Autenticacion-Autorizacion.md | 453 | ✅ |
| 02-Convencion-Tenant-Jobs.md | 154 | ✅ |
| 03-Configuracion-Entorno.md | 491 | ✅ |

### 4. docs/instrucciones (19 archivos)

| Archivo | Líneas | Estado | Notas |
|---------|--------|--------|-------|
| APACHE-CORS-INSTRUCCIONES.md | 62 | ⚠️ | CORS - consolidar |
| CORS-ANALISIS-8097331-PROFUNDO.md | 110 | ⚠️ | CORS - consolidar |
| CORS-ANALISIS-COMMIT-8097331.md | 52 | ⚠️ | CORS - consolidar |
| CORS-COOLIFY-TRAEFIK-SOLUCION.md | 127 | ⚠️ | CORS - consolidar |
| CORS-DIAGNOSTICO-Y-OPCIONES.md | 112 | ⚠️ | CORS - consolidar |
| CORS-PRODUCCION-TROUBLESHOOTING.md | 119 | ⚠️ | CORS - consolidar |
| CORS-SOLUCION-COMPLETA.md | 88 | ⚠️ | CORS - consolidar |
| CORS-VALIDACION-Y-TROUBLESHOOTING.md | 75 | ⚠️ | CORS - consolidar |
| CORS-proxy-Origin.md | 63 | ⚠️ | CORS - consolidar |
| ENV-REFERENCIA-COMPLETA.md | 325 | ✅ | |
| EXECUTION_CHECKLIST.md | 96 | ✅ | Docker Sail |
| FINAL_VALIDATION_REPORT.md | 153 | ✅ | Docker Sail |
| IMPLEMENTATION_PLAN_DOCKER_SAIL.md | 211 | ✅ | |
| TESTING-Coverage.md | 98 | ✅ | |
| actualizacion-seeders-migraciones.md | 168 | ✅ | |
| deploy-desarrollo-guiado.md | 205 | ⚠️ | Duplica con siguiente |
| deploy-desarrollo.md | 254 | ⚠️ | Duplica con anterior |
| guia-completa-entorno-sail-windows.md | 983 | ✅ | |
| instalar-docker-wsl.md | 114 | ✅ | |

### 5. docs/pedidos (5 archivos, 1.964 líneas)

| Archivo | Líneas |
|---------|--------|
| 20-Pedidos-General.md | 246 |
| 21-Pedidos-Detalles-Planificados.md | 357 |
| 22-Pedidos-Documentos.md | 473 |
| 23-Pedidos-Incidentes.md | 429 |
| 24-Pedidos-Estadisticas.md | 459 |

### 6. docs/inventario (5 archivos, 2.273 líneas)

| Archivo | Líneas |
|---------|--------|
| 30-Almacenes.md | 543 |
| 31-Palets-Estados-Fijos.md | 254 |
| 31-Palets.md | 745 |
| 32-Cajas.md | 431 |
| 33-Estadisticas-Stock.md | 300 |

### 7. docs/catalogos (15 archivos, 4.376 líneas)

| Archivo | Líneas |
|---------|--------|
| 40-Productos.md | 559 |
| 40-Productos-EJEMPLOS.md | 530 |
| 41-Categorias-Familias-Productos.md | 477 |
| 42-Especies.md | 328 |
| 43-Zonas-Captura.md | 253 |
| 44-Clientes.md | 483 |
| 45-Proveedores.md | 342 |
| 46-Transportes.md | 255 |
| 47-Vendedores.md | 246 |
| 48-Terminos-Pago.md | 186 |
| 49-Paises.md | 197 |
| 50-Incoterms.md | 172 |
| 51-Arte-Pesquera.md | 164 |
| 52-Impuestos.md | 163 |
| 53-Procesos.md | 235 |
| 54-Productos-Variantes-GS1-Resumen.md | 244 |

### 8. docs/produccion (46 archivos, ~20.300 líneas)

#### Raíz (16 archivos)
| Archivo | Líneas | Tipo |
|---------|--------|------|
| 10-Produccion-General.md | 415 | Referencia |
| 11-Produccion-Lotes.md | 2.137 | Referencia |
| 12-Produccion-Procesos.md | 1.613 | Referencia |
| 12-Produccion-Procesos-ENDPOINT-GET.md | 354 | Referencia |
| 13-Produccion-Entradas.md | 474 | Referencia |
| 14-Produccion-Salidas.md | 547 | Referencia |
| 15-Produccion-Consumos-Outputs-Padre.md | 652 | Referencia |
| ANALISIS-ERRORES-IMPLEMENTACION-COSTES.md | 335 | Histórico |
| DOCUMENTACION-FRONTEND-Trazabilidad-Costes.md | 1.066 | Frontend |
| ENDPOINT-Available-Products-For-Outputs.md | 233 | Referencia |
| FRONTEND-Consumos-Outputs-Padre.md | 626 | Frontend |
| FRONTEND-Salidas-y-Consumos-Multiples.md | 721 | Frontend |
| INVESTIGACION-Salidas-y-Consumos.md | 445 | Histórico |
| PROPUESTA-Trazabilidad-Costes-Producciones.md | 2.276 | Histórico |
| REFACTORIZACION-PRODUCCIONES-V2.md | 324 | Histórico |
| RESUMEN-Implementacion-Multiples.md | 138 | Histórico |

#### analisis/ (13 archivos)
| Archivo | Líneas | Tipo |
|---------|--------|------|
| README.md | 43 | Índice |
| ACTUALIZACION-ESTRUCTURA-FINAL-v3.md | 167 | Histórico |
| ANALISIS-Datos-No-Nodos-Production-Tree.md | 146 | Histórico |
| ANALISIS-Nodo-No-Contabilizado.md | 366 | Histórico |
| CONCILIACION-Nodo-Missing-vs-General.md | 224 | Histórico |
| CONDICIONES-NODO-FINAL-PRODUCCION.md | 279 | Histórico |
| CONFIRMACION-Estructura-Final.md | 60 | Histórico |
| DISENO-Conciliacion-Detallada-Productos.md | 415 | Histórico |
| DISENO-Nodos-Re-procesados-y-Faltantes.md | 418 | Histórico |
| DISENO-Nodos-Venta-y-Stock-Production-Tree.md | 1.387 | Histórico |
| IMPLEMENTACION-Conciliacion-Detallada-Productos.md | 299 | Histórico |
| IMPLEMENTACION-Nodos-Re-procesados-y-Faltantes.md | 205 | Histórico |
| INVESTIGACION-Impacto-Cajas-Disponibles-Palets.md | 429 | Histórico |
| RESUMEN-Decision-Dos-Nodos.md | 80 | Histórico |
| RESUMEN-Estructura-Final-Nodos.md | 192 | Histórico |

#### cambios/ (7 archivos)
| Archivo | Líneas | Tipo |
|---------|--------|------|
| README.md | 25 | Índice |
| CAMBIO-Nodo-Missing-a-Balance.md | 228 | Histórico |
| CAMBIOS-Conciliacion-Endpoint-Produccion.md | 95 | Histórico |
| CONCILIACION-Productos-No-Producidos-Formato.md | 275 | Histórico |
| FIX-Conciliacion-Productos-No-Producidos.md | 216 | Histórico |
| FIX-Nodo-Missing-Balance-Completo.md | 165 | Histórico |
| FRONTEND-Cambios-Nodos-Venta-Stock-v2.md | 826 | Histórico |
| FRONTEND-Cambios-Nodos-Venta-Stock-v3.md | 587 | Histórico |

#### frontend/ (10 archivos)
| Archivo | Líneas | Tipo |
|---------|--------|------|
| README.md | 43 | Índice |
| README-Documentacion-Frontend.md | 103 | Índice |
| FRONTEND-Cajas-Disponibles.md | 399 | Frontend |
| FRONTEND-Guia-Rapida-Nodos-Completos.md | 267 | Frontend |
| FRONTEND-Migracion-Missing-a-Balance.md | 461 | Histórico |
| FRONTEND-Nodos-Re-procesados-y-Faltantes.md | 606 | Frontend |
| FRONTEND-Nodos-Venta-y-Stock-Diagrama.md | 979 | Frontend |
| FRONTEND-Relaciones-Padre-Hijo-Nodos.md | 445 | Frontend |
| RESUMEN-Documentacion-Frontend-v4.md | 112 | Frontend |
| VERIFICACION-DOCS-FRONTEND.md | 144 | Histórico |

### 9. docs/recepciones-despachos (15 archivos, 7.487 líneas)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| 60-Recepciones-Materia-Prima.md | 616 | ✅ |
| 61-Despachos-Cebo.md | 452 | ✅ |
| 62-Liquidacion-Proveedores.md | 550 | ✅ |
| 62-Liquidacion-Proveedores-ERRORES-CORREGIDOS.md | 126 | ⚠️ Histórico |
| 62-Liquidacion-Proveedores-FRONTEND.md | 469 | ✅ Frontend |
| 62-Liquidacion-Proveedores-SELECCION-PDF.md | 227 | ✅ |
| 62-Plan-Implementacion-Recepciones-Palets-Costes.md | 1.090 | ⚠️ Histórico |
| 63-Guia-Frontend-Recepciones-Palets.md | 652 | ✅ Frontend |
| 63-Liquidacion-Proveedores-PAGOS-GASTOS.md | 259 | ✅ |
| 64-Guia-Frontend-Edicion-Recepciones.md | 534 | ✅ Frontend |
| 65-Guia-Backend-Edicion-Recepciones.md | 520 | ✅ |
| 66-Cambios-Frontend-Estructura-Pallets-Precios.md | 420 | ⚠️ Histórico |
| 67-Guia-Backend-v1-Recepcion-Lineas-Palet-Automatico.md | 538 | ❌ Deprecado |
| 68-Analisis-Cambios-API-v1-Migraciones.md | 381 | ❌ Deprecado |
| 68-Guia-Frontend-Cambios-Estructura-Pallets.md | 670 | ⚠️ Histórico |
| 69-Cambio-API-Precios-Respuesta-Recepciones.md | 68 | ✅ |
| 69-Diseno-Edicion-Cajas-Disponibles-Recepciones.md | 781 | ✅ |
| 70-Guia-Frontend-Edicion-Cajas-Disponibles.md | 655 | ✅ Frontend |

### 10. docs/etiquetas (1 archivo)

| Archivo | Líneas |
|---------|--------|
| 70-Etiquetas.md | 290 |

### 11. docs/sistema (11 archivos, 3.771 líneas)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| 80-Usuarios.md | 183 | ✅ |
| 81-Roles.md | 112 | ✅ |
| 81-Roles-Plan-Migracion-Enum.md | 265 | ⚠️ Plan |
| 82-Sesiones.md | 244 | ✅ |
| 82-Roles-Pasos-2-y-3-Pendientes.md | 139 | ⚠️ Pendiente |
| 83-Logs-Actividad.md | 304 | ✅ |
| 84-Configuracion.md | 323 | ✅ |
| 85-Control-Horario.md | 697 | ✅ |
| 86-Control-Horario-FRONTEND.md | 1.437 | ⚠️ Ubicación |
| 87-Plan-Auth-Magic-Link-OTP.md | 149 | ⚠️ Plan |
| 88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md | 129 | ✅ |
| 89-Auth-Contrasenas-Eliminadas.md | 53 | ✅ |
| 90-Analisis-Sin-Rastro-Password.md | 56 | ⚠️ Histórico |

### 12. docs/utilidades (4 archivos, 1.999 líneas)

| Archivo | Líneas |
|---------|--------|
| 90-Generacion-PDF.md | 370 |
| 91-Exportacion-Excel.md | 550 |
| 92-Extraccion-Documentos-AI.md | 412 |
| 93-Plan-Integracion-Tesseract-OCR.md | 667 |

### 13. docs/referencia (8 archivos, 5.304 líneas)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| 95-Modelos-Referencia.md | 940 | ✅ |
| 96-Recursos-API.md | 693 | ✅ |
| 96-Restricciones-Entidades.md | 945 | ✅ |
| 97-Rutas-Completas.md | 608 | ✅ |
| 98-Errores-Comunes.md | 838 | ✅ |
| 99-Glosario.md | 554 | ✅ |
| 100-Rendimiento-Endpoints.md | 304 | ✅ |
| 101-Plan-Mejoras-GET-orders-id.md | 275 | ⚠️ Plan |
| 102-Plan-Mejoras-GET-orders-active.md | 204 | ⚠️ Plan |
| ANALISIS-API-FRONTEND-BACKEND.md | 569 | ⚠️ Histórico |
| PLAN-ELIMINACION-ARTICLE.md | 1.140 | ⚠️ Completado |

### 14. docs/api-references (12 archivos, 6.435 líneas)

| Archivo | Líneas |
|---------|--------|
| README.md | 122 |
| autenticacion/README.md | 225 |
| catalogos/README.md | 357 |
| estadisticas/README.md | 438 |
| inventario/README.md | 853 |
| pedidos/README.md | 1.110 |
| produccion/README.md | 983 |
| produccion-costos/README.md | 450 |
| productos/README.md | 766 |
| recepciones-despachos/README.md | 797 |
| sistema/README.md | 807 |
| utilidades/README.md | 527 |

### 15. docs/ejemplos (6 archivos, 1.457 líneas)

| Archivo | Líneas | Estado |
|---------|--------|--------|
| README.md | 34 | ✅ |
| EJEMPLO-RESPUESTA-PALLET.md | 180 | ✅ |
| EJEMPLO-RESPUESTA-process-tree-v3.md | 546 | ⚠️ Supersedido |
| EJEMPLO-RESPUESTA-process-tree-v4.md | 162 | ⚠️ Supersedido |
| EJEMPLO-RESPUESTA-process-tree-v5-con-conciliacion.md | 265 | ✅ Vigente |
| EJEMPLO-RESPUESTA-production-record-completo.md | 270 | ✅ |

### 16. docs/frontend (6 archivos, 1.300 líneas)

| Archivo | Líneas |
|---------|--------|
| API-CAMBIO-Tenant-Endpoint-Data-Wrapper.md | 133 |
| API-Conventions.md | 178 |
| Guia-Auth-Magic-Link-OTP.md | 188 |
| Guia-Cambios-Roles-API-Paso-2.md | 290 |
| SETTINGS-EMAIL-CONFIGURATION.md | 421 |
| SETTINGS-EMAIL-RESUMEN.md | 90 |

### 17. docs/por-hacer, prompts, audits, tasks, etc.

| Carpeta | Archivos | Líneas |
|---------|----------|--------|
| docs/por-hacer/ | 2 | 223 |
| docs/prompts/ | 12 | 4.533 |
| docs/audits/ | 8 | 2.352 |
| docs/audits/documentation/ | (esta auditoría) | — |
| docs/por-hacer/ (incl. ex por-implementar) | 6 | — |
| docs/tasks/ | 2 | 74 |
| docs/ raíz (artefactos auditoría) | 10 | 1.459 |

---

## Mapa de Interdependencias

```
CLAUDE.md (canónico)
├── docs/core-consolidation-plan-erp-saas.md
├── docs/prompts/01_Laravel incremental evolution prompt.md
├── docs/audits/laravel-backend-global-audit.md
├── docs/audits/laravel-evolution-log.md
├── docs/fundamentos/01-Arquitectura-Multi-Tenant.md
└── docs/audits/findings/*

README.md
├── docs/overview.md
├── docs/audits/PROBLEMAS-CRITICOS.md
├── docs/README.md
├── docs/fundamentos/03-Configuracion-Entorno.md
└── docs/instrucciones/guia-completa-entorno-sail-windows.md

docs/overview.md → docs/01-15 (estructura canónica)
docs/README.md → docs/20-35 (estructura por dominio)
docs/api-references/* ↔ docs/22-28/* (complementarios: API vs dominio; ver FASE-C-DUPLICADOS-RESULTADOS.md)
```

---

**Generado:** 2026-02-16 | **Próxima revisión:** 2026-03-16
