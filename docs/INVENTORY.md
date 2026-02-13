# Inventario de Documentación Existente

**Inventario FASE 1 — Auditoría de documentación PesquerApp Backend**  
**Fecha de inventario:** 2026-02-13  
**Alcance:** Documentación en repositorio (excluye node_modules y vendor).

---

## Resumen Ejecutivo

| Métrica | Valor |
|---------|--------|
| **Total documentos .md** | **203** |
| En raíz del proyecto | 5 |
| En docs/ (total) | 193 |
| En .ai_standards/ | 4 |
| En database/ | 1 |
| En estructura canónica (00–15, 11-DEPLOYMENT, 12-TROUBLESHOOTING, 13/14) | ~35 (numeración estándar + subcarpetas) |
| En carpetas por dominio (fundamentos, instrucciones, produccion, etc.) | ~158 |
| Huérfanos o malpuestos (por definir en FASE 3) | A determinar |
| Antigüedad predominante | 2025-12 – 2026-02 (mayoría reciente) |

*Snapshot detallado del estado actual: [CURRENT_STATE_SNAPSHOT.md](./CURRENT_STATE_SNAPSHOT.md).*

---

## Por Ubicación

### Raíz del proyecto

| Archivo     | Descripción                                                                 |
| ----------- | --------------------------------------------------------------------------- |
| README.md   | PesquerApp Laravel API (Backend), características, instalación, Sail        |
| CHANGELOG.md| Historial de cambios                                                        |
| ROADMAP.md  | Hoja de ruta                                                                |
| SECURITY.md | Políticas de seguridad                                                      |
| TECH_DEBT.md| Deuda técnica                                                               |

### database/

| Archivo                                   | Descripción                    |
| ----------------------------------------- | ------------------------------ |
| database/migrations/companies/README.md   | Documentación de migraciones por tenant/empresa |

### docs/

Documentación principal. Estructura actual:

- **docs/README.md** — Índice general documentación API v2
- **docs/PROBLEMAS-CRITICOS.md** — Resumen ejecutivo 25 problemas críticos
- **docs/prompts/** — Prompts de agente (auditoría documentación)
- **docs/00_ POR IMPLEMENTAR/** — Guías y resúmenes pendientes de integrar
- **docs/fundamentos/** — Introducción, arquitectura multi-tenant, auth, configuración
- **docs/instrucciones/** — Deploy desarrollo, Docker Sail, WSL, validación
- **docs/frontend/** — Guías frontend (auth, roles, email)
- **docs/API-references/** — README por módulo (autenticación, catálogos, pedidos, etc.)
- **docs/produccion/** — Módulo producción (general, lotes, procesos, entradas/salidas, frontend, análisis, cambios)
- **docs/pedidos/** — Pedidos general, detalles, documentos, incidentes, estadísticas
- **docs/inventario/** — Almacenes, palets, cajas, estadísticas stock
- **docs/catalogos/** — Productos, categorías, especies, zonas, clientes, proveedores, etc.
- **docs/recepciones-despachos/** — Recepciones materia prima, despachos cebo, liquidación proveedores
- **docs/etiquetas/** — Sistema etiquetas
- **docs/sistema/** — Usuarios, roles, sesiones, logs, configuración, control horario, auth
- **docs/utilidades/** — PDF, Excel, extracción IA, plan Tesseract OCR
- **docs/referencia/** — Modelos, recursos API, rutas, errores, glosario, planes mejora
- **docs/por-hacer/** — To-do y revisiones pendientes
- **docs/ejemplos/** — Ejemplos respuestas JSON (process-tree, pallet, production-record)

### .ai_standards/

| Archivo | Descripción |
| --------| ----------- |
| AGENT_MEMORY_SYSTEM.md | Sistema de memoria de trabajo para agentes IA |
| COLETILLA_PROTOCOLO_MEMORIA.md | Coletilla para aplicar protocolo de memoria |
| QUICK_START_GUIDE.md | Guía rápida uso del sistema |
| README.md | Índice estándares IA |

*Documentación para automatización con agentes (Cursor/IA); puede considerarse fuera del árbol canónico 00–16.*

### Inline (código fuente)

- **app/:** No se ha identificado documentación formal tipo docblock PHPDoc sistemática como artefacto inventariado; existen comentarios y docblocks dispersos en clases y métodos.
- Se considera fuera del alcance de este inventario el análisis línea a línea de comentarios en código; se deja constancia de que la documentación principal es la contenida en archivos `.md`.

---

## Metadatos de Cada Documento

Criterios usados para **Estado**:  
- **Actualizado:** Modificado en los últimos ~3 meses o referenciado como vigente en docs/README.  
- **Obsoleto:** Sin modificación reciente y/o contenido sustituido por otro doc o por código.  
- **Incompleto:** Plan, análisis, guía parcial o documento explícitamente “por implementar”.

**Dependencias documentales:** se indican cuando un documento referencia o depende de otros listados en este inventario.  
**Audiencia:** Backend = desarrolladores backend, Frontend = integración frontend, DevOps = despliegue/operaciones, Arquitectura = decisiones de diseño.

---

### Raíz

| Título                         | Ubicación   | Fecha última mod. | Estado      | Palabras clave                    | Dependencias     | Audiencia        |
| ------------------------------ | ----------- | ----------------- | ----------- | --------------------------------- | ---------------- | ---------------- |
| PesquerApp – Laravel API      | README.md   | 2026-02-13        | Actualizado | API, multi-tenant, Sail, Laravel  | docs/, .env      | Todos            |

### database/migrations/companies

| Título                    | Ubicación                              | Fecha última mod. | Estado   | Palabras clave     | Dependencias | Audiencia |
| ------------------------- | -------------------------------------- | ----------------- | -------- | ------------------ | ------------ | --------- |
| Migraciones companies     | database/migrations/companies/README.md | 2025-08-08        | Obsoleto | migrations, tenant | —            | Backend   |

### docs/ (raíz docs)

| Título / Nombre           | Ubicación        | Fecha última mod. | Estado      | Palabras clave              | Dependencias              | Audiencia |
| ------------------------- | ---------------- | ----------------- | ----------- | --------------------------- | ------------------------- | --------- |
| Documentación Técnica API v2 | docs/README.md   | 2026-02-13        | Actualizado | índice, API v2, módulos     | Todas las carpetas docs/  | Todos     |
| Problemas Críticos        | docs/PROBLEMAS-CRITICOS.md | 2026-02-13 | Actualizado | problemas, resumen, 25 críticos | referencia/98-Errores-Comunes.md | Backend, Arquitectura |
| Prompt Auditoría Doc      | docs/prompts/PESQUERAPP_DOCUMENTATION_AUDIT_PROMPT.md | 2026-02-13 | Actualizado | auditoría, fases, estándares | — | Agente / Mantenedor |

### docs/00_ POR IMPLEMENTAR

| Título / Nombre           | Ubicación | Fecha última mod. | Estado     | Palabras clave        | Dependencias | Audiencia |
| ------------------------- | --------- | ----------------- | ---------- | --------------------- | ------------ | --------- |
| Guía entorno desarrollo  | docs/00_ POR IMPLEMENTAR/guia-entorno-desarrollo-pesquerapp.md | 2026-02-12 | Incompleto | Sail, Docker, WSL, primera vez | instrucciones/ | DevOps, Backend |
| Resumen productos/variantes | docs/00_ POR IMPLEMENTAR/IMPORTANTE/resumen-problema-solucion-productos-variantes.md | 2026-02-12 | Incompleto | productos, variantes, GS1 | — | Backend |

### docs/fundamentos

| Título / Nombre           | Ubicación | Fecha última mod. | Estado      | Palabras clave     | Dependencias | Audiencia |
| ------------------------- | --------- | ----------------- | ----------- | ------------------ | ------------ | --------- |
| Introducción             | docs/fundamentos/00-Introduccion.md | 2026-02-10 | Actualizado | arquitectura, visión | — | Todos |
| Arquitectura Multi-Tenant | docs/fundamentos/01-Arquitectura-Multi-Tenant.md | 2025-12-01 | Actualizado | multi-tenant, middleware, BD | 00-Introduccion | Backend, Arquitectura |
| Autenticación y Autorización | docs/fundamentos/02-Autenticacion-Autorizacion.md | 2026-02-10 | Actualizado | Sanctum, roles, magic link, OTP | sistema/81-Roles | Backend, Frontend |
| Configuración Entorno    | docs/fundamentos/03-Configuracion-Entorno.md | 2025-12-01 | Actualizado | .env, variables, conexiones | 01-Arquitectura | Backend, DevOps |

### docs/instrucciones

| Título / Nombre           | Ubicación | Fecha última mod. | Estado      | Palabras clave     | Dependencias | Audiencia |
| ------------------------- | --------- | ----------------- | ----------- | ------------------ | ------------ | --------- |
| Deploy desarrollo        | docs/instrucciones/deploy-desarrollo.md | 2026-02-13 | Actualizado | Sail, Docker, deploy | deploy-desarrollo-guiado | DevOps |
| Deploy desarrollo guiado | docs/instrucciones/deploy-desarrollo-guiado.md | 2026-02-12 | Actualizado | paso a paso, primera vez | Sail, .env | DevOps |
| Instalar Docker WSL       | docs/instrucciones/instalar-docker-wsl.md | 2026-02-13 | Actualizado | WSL, Docker, Ubuntu | — | DevOps |
| Plan Docker Sail         | docs/instrucciones/IMPLEMENTATION_PLAN_DOCKER_SAIL.md | 2026-02-13 | Actualizado | plan, implementación | EXECUTION_CHECKLIST | DevOps |
| Checklist ejecución      | docs/instrucciones/EXECUTION_CHECKLIST.md | 2026-02-12 | Actualizado | checklist, bloques | IMPLEMENTATION_PLAN | DevOps |
| Informe validación final | docs/instrucciones/FINAL_VALIDATION_REPORT.md | 2026-02-13 | Actualizado | validación, Sail, servicios | deploy-* | DevOps |

### docs/frontend

| Título / Nombre           | Ubicación | Fecha última mod. | Estado      | Palabras clave     | Dependencias | Audiencia |
| ------------------------- | --------- | ----------------- | ----------- | ------------------ | ------------ | --------- |
| Guía Auth Magic Link OTP | docs/frontend/Guia-Auth-Magic-Link-OTP.md | 2026-02-10 | Actualizado | auth, magic link, OTP | fundamentos/02, sistema/87 | Frontend |
| Guía Cambios Roles API   | docs/frontend/Guia-Cambios-Roles-API-Paso-2.md | 2026-02-10 | Actualizado | roles, API paso 2 | sistema/81-Roles | Frontend |
| Settings Email configuración | docs/frontend/SETTINGS-EMAIL-CONFIGURATION.md | 2026-01-23 | Actualizado | email, settings | SETTINGS-EMAIL-RESUMEN | Frontend |
| Settings Email resumen   | docs/frontend/SETTINGS-EMAIL-RESUMEN.md | 2026-01-23 | Actualizado | email, resumen | — | Frontend |

### docs/API-references

Todos los archivos son README.md por módulo. Fechas: 2026-02-10 (autenticación, sistema), 2026-01-17 (pedidos, recepciones-despachos, inventario, produccion, produccion-costos), 2026-01-16 (utilidades, productos, estadísticas, catalogos).

| Título / Nombre     | Ubicación (bajo docs/API-references/) | Fecha última mod. | Estado      | Palabras clave | Dependencias | Audiencia |
| ------------------- | -------------------------------------- | ----------------- | ----------- | -------------- | ------------ | --------- |
| API References      | README.md                               | 2026-02-10        | Actualizado | índice módulos | Módulos abajo | Backend, Frontend |
| Autenticación       | autenticacion/README.md                 | 2026-02-10        | Actualizado | auth, endpoints | fundamentos/02 | Backend |
| Sistema             | sistema/README.md                      | 2026-02-10        | Actualizado | usuarios, roles | sistema/* | Backend |
| Catálogos           | catalogos/README.md                    | 2026-01-16        | Actualizado | productos, clientes… | catalogos/* | Backend |
| Pedidos             | pedidos/README.md                      | 2026-01-17        | Actualizado | orders         | pedidos/* | Backend |
| Inventario          | inventario/README.md                    | 2026-01-17        | Actualizado | almacenes, palets | inventario/* | Backend |
| Producción          | produccion/README.md                    | 2026-01-17        | Actualizado | production     | produccion/* | Backend |
| Producción costos   | produccion-costos/README.md            | 2026-01-17        | Actualizado | costes         | produccion/* | Backend |
| Recepciones y Despachos | recepciones-despachos/README.md   | 2026-01-17        | Actualizado | recepciones    | recepciones-despachos/* | Backend |
| Utilidades          | utilidades/README.md                    | 2026-01-16        | Actualizado | PDF, Excel, IA | utilidades/* | Backend |
| Productos           | productos/README.md                     | 2026-01-16        | Actualizado | products       | catalogos/40 | Backend |
| Estadísticas        | estadisticas/README.md                  | 2026-01-16        | Actualizado | reportes       | pedidos/24, etc. | Backend |

### docs/produccion

Documentación muy extensa. Resumen por subcarpeta:

- **Raíz produccion/:** 10-Produccion-General, 11-Produccion-Lotes, 12-Produccion-Procesos, 12-Produccion-Procesos-ENDPOINT-GET, 13-Entradas, 14-Salidas, 15-Consumos-Outputs-Padre; más ANALISIS-*, PROPUESTA-*, REFACTORIZACION-*, ENDPOINT-*, DOCUMENTACION-FRONTEND-*, FRONTEND-*, INVESTIGACION-*, RESUMEN-*. Fechas entre 2025-12-01 y 2026-01-16.
- **produccion/frontend/:** Guías frontend process-tree, nodos, cajas disponibles (varios). Última mod. 2025-12-05.
- **produccion/analisis/:** Análisis, diseños, implementación conciliación y nodos. Última mod. 2025-12-05.
- **produccion/cambios/:** Cambios, fixes, migraciones nodos. Última mod. 2025-12-05.

| Título (representativo) | Ubicación (ej.) | Fecha última mod. | Estado      | Audiencia |
| ----------------------- | ----------------- | ----------------- | ----------- | ---------- |
| Producción General      | docs/produccion/10-Produccion-General.md | 2026-01-16 | Actualizado | Backend, Arquitectura |
| Producción Lotes        | docs/produccion/11-Produccion-Lotes.md   | 2025-12-04 | Actualizado | Backend |
| Producción Procesos     | docs/produccion/12-Produccion-Procesos.md | 2025-12-04 | Actualizado | Backend |
| … (resto archivos)      | docs/produccion/*, frontend/, analisis/, cambios/ | 2025-12-01–2025-12-05 | Actualizado / Incompleto (planes) | Backend, Frontend |

### docs/pedidos

| Título (representativo) | Ubicación (ej.) | Fecha última mod. | Estado   | Audiencia |
| ----------------------- | ----------------- | ----------------- | -------- | ---------- |
| Pedidos General / Detalles / Documentos / Incidentes / Estadísticas | docs/pedidos/20-24*.md | 2025-12-01 | Actualizado | Backend |

### docs/inventario

| Título (representativo) | Ubicación (ej.) | Fecha última mod. | Estado   | Audiencia |
| ----------------------- | ----------------- | ----------------- | -------- | ---------- |
| Almacenes, Palets, Palets Estados Fijos, Cajas, Estadísticas Stock | docs/inventario/30-33*.md, 31-Palets-Estados-Fijos.md | 2025-12-01–2025-12-16 | Actualizado | Backend |

### docs/catalogos

| Título (representativo) | Ubicación (ej.) | Fecha última mod. | Estado   | Audiencia |
| ----------------------- | ----------------- | ----------------- | -------- | ---------- |
| Productos, Categorías, Especies, Zonas, Clientes, Proveedores, etc. (40–53) | docs/catalogos/*.md | 2025-12-01–2026-01-27 | Actualizado | Backend |

### docs/recepciones-despachos

| Título (representativo) | Ubicación (ej.) | Fecha última mod. | Estado   | Audiencia |
| ----------------------- | ----------------- | ----------------- | -------- | ---------- |
| Recepciones materia prima, Despachos cebo, Liquidación proveedores, guías frontend/backend | docs/recepciones-despachos/*.md | 2025-12-08–2026-01-12 | Actualizado | Backend, Frontend |

### docs/etiquetas

| Título | Ubicación | Fecha última mod. | Estado   | Audiencia |
| ------ | --------- | ----------------- | -------- | ---------- |
| Etiquetas | docs/etiquetas/70-Etiquetas.md | 2025-12-01 | Actualizado | Backend |

### docs/sistema

| Título / Nombre           | Ubicación | Fecha última mod. | Estado      | Palabras clave     | Audiencia |
| ------------------------- | --------- | ----------------- | ----------- | ------------------ | --------- |
| Usuarios                  | docs/sistema/80-Usuarios.md | 2026-02-10 | Actualizado | users | Backend |
| Roles                     | docs/sistema/81-Roles.md | 2026-02-10 | Actualizado | roles, permisos | Backend |
| Roles Plan Migración Enum | docs/sistema/81-Roles-Plan-Migracion-Enum.md | 2026-02-10 | Incompleto | enum, plan | Backend |
| Roles Pasos 2 y 3         | docs/sistema/82-Roles-Pasos-2-y-3-Pendientes.md | 2026-02-10 | Incompleto | pendientes | Backend |
| Sesiones                  | docs/sistema/82-Sesiones.md | 2026-01-16 | Actualizado | sesiones | Backend |
| Logs Actividad            | docs/sistema/83-Logs-Actividad.md | 2025-12-01 | Actualizado | logs | Backend |
| Configuración             | docs/sistema/84-Configuracion.md | 2025-12-01 | Actualizado | settings | Backend |
| Control Horario           | docs/sistema/85-Control-Horario.md | 2026-01-15 | Actualizado | control horario | Backend |
| Control Horario Frontend  | docs/sistema/86-Control-Horario-FRONTEND.md | 2026-01-15 | Actualizado | frontend | Frontend |
| Plan Auth Magic Link OTP  | docs/sistema/87-Plan-Auth-Magic-Link-OTP.md | 2026-02-10 | Incompleto | plan, auth | Backend |
| Auth Tokens Reenvío       | docs/sistema/88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md | 2026-02-10 | Actualizado | tokens, invitación | Backend |
| Auth Contraseñas Eliminadas | docs/sistema/89-Auth-Contrasenas-Eliminadas.md | 2026-02-10 | Actualizado | contraseñas | Backend |
| Análisis Sin Rastro Password | docs/sistema/90-Analisis-Sin-Rastro-Password.md | 2026-02-10 | Incompleto | análisis | Backend |

### docs/utilidades

| Título / Nombre           | Ubicación | Fecha última mod. | Estado   | Audiencia |
| ------------------------- | --------- | ----------------- | -------- | ---------- |
| Generación PDF            | docs/utilidades/90-Generacion-PDF.md | 2025-12-01 | Actualizado | Backend |
| Exportación Excel         | docs/utilidades/91-Exportacion-Excel.md | 2025-12-01 | Actualizado | Backend |
| Extracción Documentos AI  | docs/utilidades/92-Extraccion-Documentos-AI.md | 2025-12-01 | Actualizado | Backend |
| Plan Tesseract OCR        | docs/utilidades/93-Plan-Integracion-Tesseract-OCR.md | 2026-02-03 | Incompleto | Backend |

### docs/referencia

| Título / Nombre           | Ubicación | Fecha última mod. | Estado      | Audiencia |
| ------------------------- | --------- | ----------------- | ----------- | ---------- |
| Modelos Referencia        | docs/referencia/95-Modelos-Referencia.md | 2026-02-10 | Actualizado | Backend |
| Recursos API              | docs/referencia/96-Recursos-API.md | 2026-02-10 | Actualizado | Backend |
| Restricciones Entidades   | docs/referencia/96-Restricciones-Entidades.md | 2026-02-10 | Actualizado | Backend |
| Rutas Completas           | docs/referencia/97-Rutas-Completas.md | 2026-02-10 | Actualizado | Backend |
| Errores Comunes           | docs/referencia/98-Errores-Comunes.md | 2026-01-16 | Actualizado | Backend |
| Glosario                  | docs/referencia/99-Glosario.md | 2025-12-01 | Actualizado | Todos |
| Rendimiento Endpoints     | docs/referencia/100-Rendimiento-Endpoints.md | 2026-02-07 | Actualizado | Backend |
| Plan Mejoras GET orders   | docs/referencia/101-102-*.md | 2026-02-07 | Incompleto | Backend |
| Análisis API Frontend-Backend | docs/referencia/ANALISIS-API-FRONTEND-BACKEND.md | 2026-01-17 | Actualizado | Backend, Frontend |
| Plan Eliminación Article  | docs/referencia/PLAN-ELIMINACION-ARTICLE.md | 2026-01-16 | Incompleto | Backend |

### docs/por-hacer

| Título / Nombre           | Ubicación | Fecha última mod. | Estado     | Audiencia |
| ------------------------- | --------- | ----------------- | ---------- | ---------- |
| Por Hacer (To Do)         | docs/por-hacer/README.md | 2026-01-21 | Incompleto | Backend |
| Revisión Validaciones Cliente/Pedido | docs/por-hacer/01-Revision-Validaciones-Cliente-Pedido.md | 2026-01-21 | Incompleto | Backend |

### docs/ejemplos

| Título / Nombre           | Ubicación | Fecha última mod. | Estado   | Audiencia |
| ------------------------- | --------- | ----------------- | -------- | ---------- |
| README ejemplos           | docs/ejemplos/README.md | 2025-12-05 | Actualizado | Backend, Frontend |
| EJEMPLO-RESPUESTA-*       | docs/ejemplos/EJEMPLO-RESPUESTA-*.md | 2025-12-04–2025-12-05 | Actualizado | Backend, Frontend |

---

## Resumen cuantitativo

| Ubicación           | N.º documentos |
| ------------------- | --------------- |
| Raíz                | 5               |
| database/           | 1               |
| docs/ (total)       | 193             |
| .ai_standards/      | 4               |
| **Total proyecto**  | **203** .md     |

---

## Análisis Inicial

- **Patrón dominante de nomenclatura:** Prefijos numéricos por dominio (20–24 pedidos, 30–33 inventario, 40–53 catálogos, 80–90 sistema) y nombres temáticos (GUIA-*, FRONTEND-*, ANALISIS-*, PLAN-*). Estructura 00–15 ya presente en docs/.
- **Brecha entre estructura actual y objetivo:** La estructura canónica (00–15, 11-DEPLOYMENT, 12-TROUBLESHOOTING, 13-POSTMORTEMS, 14-ARCHITECTURE-DECISIONS) está ya creada; falta 16-OPERATIONS. La mayoría del contenido detallado vive en carpetas por dominio (fundamentos, instrucciones, produccion, referencia, etc.), que deben mapearse a la estructura objetivo en FASE 3.
- **Documentos que sugieren temas no en estructura:** API-references por módulo, ejemplos JSON, por-hacer, 00_ POR IMPLEMENTAR, prompts y .ai_standards; decidir en FASE 4 si generan nuevas categorías o se integran en 08-API-REST / instrucciones / operaciones.

## Hallazgos iniciales (para FASE 2)

1. **Estructura actual:** Conviven estructura estándar 00–15 (con 11-DEPLOYMENT, 12-TROUBLESHOOTING, 13/14) y organización por dominio (fundamentos, instrucciones, módulos de negocio, referencia).
2. **Documentos en raíz objetivo:** Presentes README, CHANGELOG, ROADMAP, SECURITY, TECH_DEBT; en docs/ existen 00-OVERVIEW, 01–15, 11-DEPLOYMENT, 12-TROUBLESHOOTING, 13-POSTMORTEMS, 14-ARCHITECTURE-DECISIONS.
3. **Contenido equivalente** disperso: setup/entorno (instrucciones + fundamentos), arquitectura (fundamentos/01 + 03-ARCHITECTURE), API (API-references + referencia/97), problemas (PROBLEMAS-CRITICOS, 98-Errores-Comunes).
4. **Multi-tenant:** Cubierto en fundamentos/01 y en 15-MULTI-TENANT-SPECIFICS.md.
5. **Deployment/troubleshooting:** 11-DEPLOYMENT (11a–11e) y 12-TROUBLESHOOTING ya existen; contenido operativo también en docs/instrucciones.
6. **Estado:** Mayoría de docs recientes (2025-12 – 2026-02); algunos planes/análisis incompletos; database/migrations/companies/README más antiguo (2025-08).
7. **Documentación inline:** No inventariada como artefactos; documentación principal es .md. No se detectan referencias a wikis externos (Notion, Confluence).

---

**Siguiente paso:** FASE 2 — Clasificación (matriz por tipo, criticidad, completitud, estado) y generación de `CLASSIFICATION_MATRIX.md`.
