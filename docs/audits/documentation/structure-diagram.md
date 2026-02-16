# Diagrama de Estructura — Documentación PesquerApp Backend

**Fecha:** 2026-02-16

---

## Estructura Actual

```
lapesquerapp-backend/
│
├── CLAUDE.md .......................... (230 líneas) ✅ Contexto IA canónico
├── README.md .......................... (108 líneas) ⚠️ Versión Laravel incorrecta
├── SECURITY.md ........................ (84 líneas)  ✅ Políticas seguridad
├── QUICK_START.md ..................... (69 líneas)  ✅ Workflow Claude Code
├── EXECUTION_CHECKLIST.md ............. (39 líneas)  ✅ Checklist ejecución
├── CHANGELOG.md ....................... (7 líneas)   🗑️ STUB VACÍO
├── ROADMAP.md ......................... (7 líneas)   🗑️ STUB VACÍO
├── TECH_DEBT.md ....................... (7 líneas)   🗑️ STUB VACÍO
│
├── .agents/skills/ .................... (11 archivos, 3.566 líneas)
│   ├── code-refactoring-refactor-clean/
│   ├── laravel-11-12-app-guidelines/ .. ⚠️ Laravel 11/12 en proyecto L10
│   ├── laravel-code-review-requests/
│   └── laravel-specialist/
│
├── .ai_standards/ ..................... (5 archivos, 573 líneas)
│   ├── AGENT_MEMORY_SYSTEM.md
│   ├── COLETILLA_PROTOCOLO_MEMORIA.md
│   ├── PROTOCOLO_PARA_CHAT.md
│   ├── QUICK_START_GUIDE.md
│   └── README.md
│
├── .ai_work_context/ .................. (67 archivos, 2.435 líneas) ⚠️ EFÍMERO
│   ├── 20260215_0927/ ................. Sesión fichajes
│   ├── 20260215_1012/ ................. Sesión fichajes cont.
│   ├── 20260215_1118/ ................. Sesión proveedores
│   ├── 20260215_1200/ ................. Sesión estadísticas
│   ├── 20260215_1415/ ................. Sesión auditoría
│   ├── 20260215_1430/ ................. Sesión catálogos/API
│   ├── 20260215_1500/ ................. Sesión CORS
│   └── 20260216_fichajes/ ............. Sesión fichajes actualización
│
├── .scribe/ ........................... (2 archivos, 20 líneas)
│
├── database/migrations/companies/
│   └── README.md ...................... (270 líneas) ✅
│
└── docs/ .............................. (196 archivos, 80.788 líneas)
    │
    ├── ─── ESTRUCTURA CANÓNICA (00-15) ───
    │
    ├── README.md ...................... (211 líneas) ✅ Índice por dominio
    ├── overview.md ................. (90 líneas)  ✅ Índice por número
    ├── core-consolidation-plan-erp-saas.md .. (544 líneas) ✅ Plan estratégico
    ├── setup-local.md .............. (36 líneas)  ✅ → instrucciones
    ├── environment-variables.md .... (29 líneas)  ✅ → fundamentos
    ├── architecture.md ............. (28 líneas)  ✅ → fundamentos
    ├── database.md ................. (34 líneas)  ✅ → modelos
    ├── queues-jobs.md .............. (62 líneas)  ✅ Operativo
    ├── scheduler-cron.md ........... (68 líneas)  ✅ Operativo
    ├── storage-files.md ............ (29 líneas)  ✅ → utilidades
    ├── api-rest.md ................. (39 líneas)  ✅ → api-references
    ├── testing.md .................. (244 líneas) ✅ Exhaustivo
    ├── observability-monitoring.md . (48 líneas)  ⚠️ Genérico
    ├── multi-tenant-specs.md ... (25 líneas)  ✅ → fundamentos
    │
    ├── deployment/
    │   ├── 11a-DEVELOPMENT.md ......... (13 líneas)  ✅
    │   ├── 11b-STAGING.md ............. (3 líneas)   🗑️ STUB
    │   ├── 11c-PRODUCTION.md .......... (110 líneas) ✅
    │   ├── 11d-ROLLBACK-PROCEDURES.md . (3 líneas)   🗑️ STUB CRÍTICO
    │   └── 11e-RUNBOOK.md ............. (3 líneas)   🗑️ STUB CRÍTICO
    │
    ├── troubleshooting/
    │   ├── COMMON-ERRORS.md ........... (9 líneas)   ✅ → referencia
    │   ├── DEBUGGING-GUIDE.md ......... (5 líneas)   🗑️ STUB
    │   └── PERFORMANCE-ISSUES.md ...... (7 líneas)   ✅ → referencia
    │
    ├── postmortems/
    │   └── README.md .................. (5 líneas)   🗑️ STUB
    │
    ├── architecture-decisions/
    │   ├── README.md .................. (18 líneas)  ✅
    │   ├── 0000-ADR-TEMPLATE.md ....... (36 líneas)  ✅
    │   └── 0001-API-v2-only.md ........ (42 líneas)  ✅ Ejemplar
    │
    ├── ─── DOCUMENTACIÓN POR DOMINIO (20-35) ───
    │
    ├── fundamentos/ ................ (5 archivos, 1.931 líneas) ✅ SÓLIDO
    │   ├── 00-Introduccion.md
    │   ├── 01-Arquitectura-Multi-Tenant.md
    │   ├── 02-Autenticacion-Autorizacion.md
    │   ├── 02-Convencion-Tenant-Jobs.md  ⚠️ Numeración duplicada
    │   └── 03-Configuracion-Entorno.md
    │
    ├── instrucciones/ .............. (19 archivos, 3.241 líneas) ⚠️ DUPLICACIONES
    │   ├── 🔴 9× CORS-*.md ........... (808 líneas) → CONSOLIDAR EN 1
    │   ├── ENV-REFERENCIA-COMPLETA.md
    │   ├── EXECUTION_CHECKLIST.md ..... Docker Sail
    │   ├── FINAL_VALIDATION_REPORT.md
    │   ├── IMPLEMENTATION_PLAN_DOCKER_SAIL.md
    │   ├── TESTING-Coverage.md
    │   ├── actualizacion-seeders-migraciones.md
    │   ├── 🔴 deploy-desarrollo-guiado.md } → CONSOLIDAR EN 1
    │   ├── 🔴 deploy-desarrollo.md    } → CONSOLIDAR EN 1
    │   ├── guia-completa-entorno-sail-windows.md (983 líneas)
    │   └── instalar-docker-wsl.md
    │
    ├── pedidos/ .................... (5 archivos, 1.964 líneas) ✅ BIEN
    │   ├── 20-Pedidos-General.md
    │   ├── 21-Pedidos-Detalles-Planificados.md
    │   ├── 22-Pedidos-Documentos.md
    │   ├── 23-Pedidos-Incidentes.md
    │   └── 24-Pedidos-Estadisticas.md
    │
    ├── inventario/ ................. (5 archivos, 2.273 líneas) ✅ BIEN
    │   ├── 30-Almacenes.md
    │   ├── 31-Palets.md
    │   ├── 31-Palets-Estados-Fijos.md . ⚠️ Numeración duplicada
    │   ├── 32-Cajas.md
    │   └── 33-Estadisticas-Stock.md
    │
    ├── catalogos/ .................. (16 archivos, 4.376 líneas) ✅ EXHAUSTIVO
    │   ├── 40-Productos.md + 40-Productos-EJEMPLOS.md
    │   ├── 41 a 54: Categorías, Familias, Especies, Zonas,
    │   │   Clientes, Proveedores, Transportes, Vendedores,
    │   │   Términos Pago, Países, Incoterms, Arte Pesquera,
    │   │   Impuestos, Procesos, Variantes GS1
    │   └── (cobertura completa de todos los maestros)
    │
    ├── produccion/ ................. (46 archivos, ~20.300 líneas) 🔴 PROBLEMÁTICO
    │   │
    │   ├── REFERENCIA VIGENTE (7):
    │   │   ├── 10-Produccion-General.md .......... (415)
    │   │   ├── 11-Produccion-Lotes.md ............ (2.137) ⚡ MEGA
    │   │   ├── 12-Produccion-Procesos.md ......... (1.613) ⚡ MEGA
    │   │   ├── 12-Produccion-Procesos-ENDPOINT-GET.md (354)
    │   │   ├── 13-Produccion-Entradas.md ......... (474)
    │   │   ├── 14-Produccion-Salidas.md .......... (547)
    │   │   └── 15-Produccion-Consumos-Outputs-Padre.md (652)
    │   │
    │   ├── FRONTEND (para mover a frontend/):
    │   │   ├── DOCUMENTACION-FRONTEND-*.md
    │   │   ├── FRONTEND-*.md (3 archivos)
    │   │   └── frontend/ (10 archivos)
    │   │
    │   ├── HISTÓRICO (para archivar):
    │   │   ├── PROPUESTA-*.md (2.276 líneas)
    │   │   ├── INVESTIGACION-*.md
    │   │   ├── REFACTORIZACION-*.md
    │   │   ├── RESUMEN-*.md
    │   │   └── ANALISIS-ERRORES-*.md
    │   │
    │   ├── analisis/ .................. (13 archivos) → MAYORMENTE HISTÓRICO
    │   │   └── (diseños, análisis, implementaciones completadas)
    │   │
    │   └── cambios/ ................... (7 archivos) → MAYORMENTE HISTÓRICO
    │       └── (fixes, cambios, migraciones completadas)
    │
    ├── recepciones-despachos/ ...... (15 archivos, 7.487 líneas) ⚠️ MIXTO
    │   ├── ✅ 60-Recepciones-Materia-Prima.md
    │   ├── ✅ 61-Despachos-Cebo.md
    │   ├── ✅ 62-Liquidacion-Proveedores.md (+ FRONTEND, SELECCION-PDF, PAGOS-GASTOS)
    │   ├── ⚠️ 62-Plan-Implementacion-*.md (1.090 líneas, histórico)
    │   ├── ✅ 63-65: Guías frontend/backend recepciones
    │   ├── ❌ 67-Guia-Backend-v1-*.md (DEPRECADO)
    │   ├── ❌ 68-Analisis-Cambios-API-v1-*.md (DEPRECADO)
    │   └── ✅ 69-70: Diseño y guía cajas disponibles
    │
    ├── etiquetas/ .................. (1 archivo, 290 líneas) ✅ CONCISO
    │   └── 70-Etiquetas.md
    │
    ├── sistema/ .................... (11 archivos, 3.771 líneas) ✅ BUENO
    │   ├── 80-Usuarios.md
    │   ├── 81-Roles.md + 81-Roles-Plan-Migracion-Enum.md
    │   ├── 82-Sesiones.md + 82-Roles-Pasos-2-y-3-Pendientes.md
    │   ├── 83-Logs-Actividad.md
    │   ├── 84-Configuracion.md
    │   ├── 85-Control-Horario.md
    │   ├── 86-Control-Horario-FRONTEND.md ..... ⚠️ Debería ir en frontend
    │   ├── 87-89: Auth magic link, tokens, contraseñas
    │   └── 90-Analisis-Sin-Rastro-Password.md
    │
    ├── utilidades/ ................. (4 archivos, 1.999 líneas) ✅ BIEN
    │   ├── 90-Generacion-PDF.md
    │   ├── 91-Exportacion-Excel.md
    │   ├── 92-Extraccion-Documentos-AI.md
    │   └── 93-Plan-Integracion-Tesseract-OCR.md
    │
    ├── referencia/ ................. (11 archivos, 5.304 líneas) ✅ SÓLIDO
    │   ├── 95-99: Modelos, Recursos API, Restricciones, Rutas, Errores, Glosario
    │   ├── 100-102: Rendimiento endpoints, Planes mejora orders
    │   ├── ⚠️ PLAN-ELIMINACION-ARTICLE.md (1.140 líneas, completado)
    │   └── ⚠️ ANALISIS-API-FRONTEND-BACKEND.md (histórico)
    │
    ├── api-references/ ............. (12 archivos, 6.435 líneas) ✅ ESTRUCTURADO
    │   ├── README.md (índice)
    │   └── */README.md: autenticacion, catalogos, estadisticas,
    │       inventario, pedidos, produccion, produccion-costos,
    │       productos, recepciones-despachos, sistema, utilidades
    │
    ├── ejemplos/ ................... (6 archivos, 1.457 líneas) ⚠️ VERSIONES
    │   ├── EJEMPLO-RESPUESTA-PALLET.md
    │   ├── ⚠️ process-tree-v3.md (supersedido)
    │   ├── ⚠️ process-tree-v4.md (supersedido)
    │   ├── ✅ process-tree-v5-con-conciliacion.md (vigente)
    │   └── EJEMPLO-RESPUESTA-production-record-completo.md
    │
    ├── frontend/ ................... (6 archivos, 1.300 líneas) ✅ ENFOCADO
    │   ├── API-Conventions.md
    │   ├── API-CAMBIO-Tenant-Endpoint-Data-Wrapper.md
    │   ├── Guia-Auth-Magic-Link-OTP.md
    │   ├── Guia-Cambios-Roles-API-Paso-2.md
    │   └── SETTINGS-EMAIL-*.md (2 archivos)
    │
    ├── por-hacer/ .................. (2 archivos, 223 líneas) ✅
    ├── prompts/ .................... (12 archivos, 4.533 líneas) ⚠️ EVALUAR UBICACIÓN
    │
    ├── ─── AUDITORÍAS Y META-DOCUMENTACIÓN ───
    │
    ├── audits/
    │   ├── findings/ .................. (5 archivos, 412 líneas) ✅
    │   ├── indexes-audit-2026-02-15.md
    │   ├── laravel-backend-global-audit.md ... (221 líneas) ✅
    │   ├── laravel-evolution-log.md .......... (1.670 líneas) ✅ CLAVE
    │   └── documentation/ ................... (ESTA AUDITORÍA)
    │
    ├── ⚠️ ARTEFACTOS AUDITORÍA PREVIA (10 archivos en docs/ raíz):
    │   ├── DOCUMENTATION_AUDIT_REPORT.md
    │   ├── INVENTORY.md, CLASSIFICATION_MATRIX.md
    │   ├── CURRENT_STATE_SNAPSHOT.md
    │   ├── DOCUMENTATION_MAPPING_MATRIX.md
    │   ├── DOCUMENTATION_ORPHANS_AND_CATEGORIES.md
    │   ├── DOCUMENTATION_RESTRUCTURING_CHECKLIST.md
    │   ├── DOCUMENTATION_TODO_FLOW.md
    │   ├── GAPS_ANALYSIS.md
    │   └── API_DOCUMENTATION_GUIDE.md
    │
    ├── tasks/ ......................... (2 archivos, 74 líneas)
    │
    └── audits/
    │   ├── PROBLEMAS-CRITICOS.md ...... (74 líneas)
    │   ├── POLITICA-DEPRECADOS.md ..... Política archivado
    │   ├── CONVENCIONES-DOCUMENTACION.md
    │   ├── findings/
    │   ├── laravel-*.md
    │   └── documentation/ ............. MANIFEST, REORGANIZATION_PLAN, etc.
```

---

## Estructura Propuesta (Post-Reorganización)

```
lapesquerapp-backend/
│
├── CLAUDE.md .......................... ✅ Sin cambios
├── README.md .......................... ✅ Corregido (Laravel 10)
├── SECURITY.md ........................ ✅ Paths corregidos
├── QUICK_START.md ..................... ✅ Sin cambios
├── EXECUTION_CHECKLIST.md ............. ✅ Sin cambios
│
├── .agents/
│   ├── skills/ ........................ Sin cambios
│   └── prompts/ ....................... (movidos desde docs/prompts/) [opcional]
│
├── .ai_standards/ ..................... Sin cambios
├── .ai_work_context/ .................. En .gitignore [recomendado]
│
└── docs/
    │
    ├── README.md
    ├── overview.md
    ├── core-consolidation-plan-erp-saas.md
    ├── 01 a 15 ........................ Sin cambios (stubs poblados)
    │
    ├── fundamentos/ ................ Sin cambios
    │
    ├── instrucciones/
    │   ├── CORS-GUIA-DEFINITIVA.md .... NUEVO (consolidado)
    │   ├── deploy-desarrollo.md ....... CONSOLIDADO
    │   ├── (resto sin cambios)
    │   └── _archivo-cors/ ............. 9 archivos CORS originales
    │
    ├── pedidos/ .................... Sin cambios
    ├── inventario/ ................. Sin cambios
    ├── catalogos/ .................. Sin cambios
    │
    ├── produccion/
    │   ├── 00-ESTADO-ACTUAL.md ........ NUEVO
    │   ├── 10-15 (referencia vigente) . Sin cambios
    │   ├── ENDPOINT-*.md .............. Sin cambios
    │   └── _archivo/ .................. Propuestas/análisis implementados
    │
    ├── recepciones-despachos/ ...... Sin cambios (deprecados archivados)
    ├── etiquetas/ .................. Sin cambios
    ├── sistema/ .................... Sin cambios (86-FRONTEND movido)
    ├── utilidades/ ................. Sin cambios
    ├── referencia/ ................. Sin cambios (PLAN-ARTICLE archivado)
    ├── api-references/ ............. Sin cambios
    ├── ejemplos/ ................... v3/v4 eliminados, solo v5
    │
    ├── frontend/
    │   ├── (existentes)
    │   ├── Control-Horario-FRONTEND.md  (movido desde sistema)
    │   └── produccion/ ................ (opcional; frontend en produccion/frontend)
    │
    ├── por-hacer/ .................. ROADMAP, TECH_DEBT, por-implementar (unificado)
    ├── prompts/ .................... Mantener en docs (DECISIONES-F1)
    │
    ├── audits/
    │   ├── PROBLEMAS-CRITICOS.md ...... Resumen problemas críticos
    │   ├── POLITICA-DEPRECADOS.md ..... No eliminar archivados
    │   ├── CONVENCIONES-DOCUMENTACION.md
    │   ├── findings/
    │   ├── documentation/
    │   │   ├── 2026-02-13/ ............ Auditoría previa (movida)
    │   │   ├── AUDIT_REPORT.md, MANIFEST.md, REORGANIZATION_PLAN.md
    │   │   ├── STRUCTURE_DIAGRAM.md, VALIDATION_CHECKLIST.md
    │   │   ├── PLAN-PENDIENTES-DOCUMENTACION.md, DECISIONES-F1.md
    │   │   └── FASE-A/B/C/E resultados, etc.
    │   ├── laravel-backend-global-audit.md
    │   ├── laravel-evolution-log.md
    │   └── indexes-audit-2026-02-15.md
    │
    ├── _archivo/
    │   ├── api-v1/ .................... Docs deprecados v1
    │   └── planes-completados/ ........ Planes ya ejecutados
    │
    └── tasks/ ......................... Sin cambios
```

---

## Leyenda

| Símbolo | Significado |
|---------|-------------|
| ✅ | Documento actualizado y bien ubicado |
| ⚠️ | Necesita atención (actualización, reubicación o evaluación) |
| 🗑️ | Stub vacío sin contenido útil |
| ❌ | Deprecado |
| 🔴 | Problema que requiere acción |
| ⚡ | Mega-documento (> 1.000 líneas) |
| NUEVO | Documento a crear |
| → | Delega contenido a otro documento |

---

**Generado:** 2026-02-16
