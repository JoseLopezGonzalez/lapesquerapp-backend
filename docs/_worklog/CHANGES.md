# Changelog — Limpieza /docs (Fase 1)

Registro incremental de cambios por archivo. Formato: `ruta original → nuevo nombre | acción | motivo`

---

## Procesados

| Original | Nuevo | Acción | Motivo |
|----------|-------|--------|--------|
| docs/README.md | docs/00-docs-index.md | UPDATE + RENAME | Contenido principal movido a 00-docs-index; README reducido a redirect para convención GitHub |
| _archivo/api-v1/67-Guia-Backend-v1-Recepcion-Lineas-Palet-Automatico.md | _archivo/api-v1/01-api-v1-recepcion-lineas-palet-archived.md | UPDATE + RENAME | Archivado, enlace deprecado corregido |
| _archivo/api-v1/68-Analisis-Cambios-API-v1-Migraciones.md | _archivo/api-v1/02-api-v1-migraciones-analisis-archived.md | KEEP + RENAME | Archivado, formato nombre |
| _archivo/api-v1/README.md | _archivo/api-v1/00-archived-api-v1-index.md | RENAME | Formato NN-topic |
| _archivo/planes-completados/62-Plan-Implementacion-Recepciones-Palets-Costes.md | _archivo/planes-completados/01-plan-recepciones-palets-costes-completed.md | RENAME | Formato NN-topic, archivado |
| _archivo/planes-completados/87-Plan-Auth-Magic-Link-OTP.md | _archivo/planes-completados/02-plan-auth-magic-link-otp-completed.md | RENAME | Formato NN-topic, archivado |
| _archivo/planes-completados/PLAN-ELIMINACION-ARTICLE.md | _archivo/planes-completados/03-plan-eliminacion-article-completed.md | RENAME | Formato NN-topic, archivado |
| _archivo/planes-completados/README.md | _archivo/planes-completados/00-archived-plans-index.md | UPDATE + RENAME | Índice con enlaces |
| docs/api-rest.md | docs/08-api-rest-overview.md | UPDATE + RENAME | Formato NN-topic, enlaces corregidos |
| docs/setup-local.md | docs/01-setup-local.md | UPDATE + RENAME | Enlace deploy corregido |
| docs/architecture.md | docs/03-architecture-overview.md | RENAME | Formato NN-topic |
| docs/database.md | docs/04-database-overview.md | RENAME | Formato NN-topic |
| docs/overview.md | docs/00-overview.md | RENAME | Formato NN-topic |
| docs/environment-variables.md | docs/02-environment-variables.md | RENAME | Formato NN-topic |
| docs/queues-jobs.md | docs/05-queues-jobs.md | RENAME | Formato NN-topic |
| docs/scheduler-cron.md | docs/06-scheduler-cron.md | RENAME | Formato NN-topic |
| docs/storage-files.md | docs/07-storage-files.md | RENAME | Formato NN-topic |
| docs/testing.md | docs/09-testing.md | RENAME | Formato NN-topic |
| docs/observability-monitoring.md | docs/10-observability-monitoring.md | RENAME | Formato NN-topic |
| docs/multi-tenant-specs.md | docs/15-multi-tenant-specs.md | RENAME | Formato NN-topic |

---

## Batch Fase 1 continuación (2026-02-16)

| Carpeta | Acción | Archivos |
|---------|--------|----------|
| catalogos/ | RENAME lowercase | 16 archivos |
| fundamentos/ | RENAME lowercase | 5 archivos |
| inventario/ | RENAME lowercase | 5 archivos |
| pedidos/ | RENAME lowercase | 5 archivos |
| recepciones-despachos/ | RENAME lowercase | 14 archivos |
| instrucciones/ | RENAME lowercase + kebab | 7 + _archivo-cors 9 |
| sistema/ | RENAME lowercase | 11 archivos |
| utilidades/ | RENAME lowercase | 4 archivos |
| etiquetas/ | RENAME lowercase | 1 archivo |
| ejemplos/ | RENAME lowercase | 6 archivos |
| frontend/ | RENAME lowercase | 7 archivos |
| por-hacer/ | RENAME lowercase | 6 archivos |
| referencia/ | RENAME lowercase | 10 archivos |
| troubleshooting/ | RENAME lowercase | 3 archivos |
| architecture-decisions/ | RENAME lowercase | 3 archivos |
| produccion/ | RENAME lowercase recursivo | ~55 archivos |
| api-references/ | RENAME readme→readme | 12 READMEs |
| audits/ | RENAME lowercase + kebab | ~26 archivos |
| tasks/ | RENAME lowercase + kebab | 2 archivos |
| prompts/ | RENAME lowercase + kebab | 13 archivos |
