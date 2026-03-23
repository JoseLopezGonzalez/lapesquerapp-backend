# Uso de Componentes Estructurales Laravel — PesquerApp Backend

**Fecha**: 2026-03-23

---

## Foto actual

| Componente | Estado | Evidencia resumida |
|-----------|--------|--------------------|
| Services | Fuerte | `63` clases en `app/Services`, `app/Services/v2`, `app/Services/Production`, `app/Services/Superadmin`. |
| Form Requests | Fuerte | `185` clases en `app/Http/Requests`. |
| Policies | Fuerte | `42` policies registradas y uso amplio de `authorize()`. |
| API Resources | Fuerte | `51` recursos. |
| Jobs | Presente | `OnboardTenantJob`, `MigrateTenantJob`. |
| Events / Listeners | Débil | No hay capa rica de eventos de dominio. |
| Actions / DTOs | Ausente | El proyecto no depende de estos patrones. |

## Lectura

- La madurez real del backend viene de `Service + Request + Policy + Resource`, no de patrones más ceremoniales.
- La auditoría canónica anterior estaba desfasada respecto a `Jobs`.
- La deuda estructural ya no es ausencia de componentes, sino aplicación desigual en hotspots concretos.

## Prioridad

1. Mantener el estándar actual.
2. Extender `FormRequest` a la validación inline residual.
3. Documentar patrón de jobs tenant-aware.
4. Introducir eventos solo donde aporten desacoplamiento real.

## Valoración

**Madurez estructural Laravel**: **8/10**
