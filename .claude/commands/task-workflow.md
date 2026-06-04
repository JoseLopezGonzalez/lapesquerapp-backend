Ejecuta el flujo de evolución incremental de un bloque del ERP según el workflow definido en CLAUDE.md §18.

Bloque objetivo: $ARGUMENTS (ej. "A.6 Producción", "A.19 CRM comercial", o nombre de módulo)

---

## STEP 0a — Scope & Entity Mapping

Lista todas las entidades, modelos, controladores, servicios, rutas, tests y artefactos del bloque.
Confirma el scope con el usuario antes de continuar.

## STEP 0 — Comportamiento Actual

Documenta sin modificar nada:
- Estados posibles y transiciones (con reglas de negocio)
- Impacto en stock, totales u otros modelos
- Permisos actuales (roles + policies)
- Flujos de entrada/salida de datos

## STEP 1 — Análisis

Evalúa el estado actual:
- **Rating ANTES** (1–10): justificado por componente
- Componentes estructurales presentes (Controller, Request, Resource, Policy, Service, Tests)
- Deuda técnica identificada (queries N+1, lógica en controller, falta de tests, etc.)
- Riesgos de modificación

## STEP 2 — Cambios Propuestos

Describe los cambios en prosa, sin código. Espera aprobación explícita del usuario antes de continuar al STEP 3.

Formato por cambio:
- **Qué**: descripción del cambio
- **Por qué**: deuda o riesgo que resuelve
- **Impacto**: qué archivos/componentes afecta

## STEP 3 — Implementación

Solo tras aprobación del STEP 2:
- Implementa los cambios preservando comportamiento existente
- Mantén aislamiento multi-tenant (UsesTenantConnection, exists:tenant.*)
- Usa transacciones DB donde corresponda
- Delega lógica compleja a Services

## STEP 4 — Validación

- **Rating DESPUÉS** (1–10): justificado
- Ejecuta tests existentes: `php artisan test --filter={Bloque}`
- Verifica endpoints manualmente si aplica
- Si Rating < 9: documenta "Gap to 10/10" y propone siguiente sub-bloque

## STEP 5 — Evolution Log

Genera la entrada para `docs/audits/laravel-evolution-log.md`:

```
## [FECHA] {BLOQUE} — {TÍTULO CORTO}

**Rating**: {ANTES}/10 → {DESPUÉS}/10
**Prioridad**: Alta/Media/Baja | **Complejidad**: Alta/Media/Baja | **Estado**: ✅ Completado

### Cambios
- ...

### Tests
- ...

### Gap to 10/10 (si aplica)
- ...
```
