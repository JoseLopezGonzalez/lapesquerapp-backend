---
title: ADR — Contrato público vs interno y política de exclusión de rutas administrativas
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 4. Contrato público vs interno y política de exclusión de rutas administrativas

## Contexto

`routes/api.php` registra en el mismo archivo tanto la API de negocio (`v2/*`, protegida por
`TenantMiddleware`) como ~27 rutas de `v2/superadmin/*` (gestión de tenants, impersonación,
seguridad, migraciones, feature flags) que quedan **fuera** del grupo `TenantMiddleware` y son de
altísimo privilegio. Publicar un único contrato OpenAPI que incluyera ambas superficies
documentaría públicamente la existencia, parámetros y forma de respuesta de endpoints
administrativos — riesgo de reconocimiento de superficie de ataque, señalado en
`API_CONTRACT_AUDIT.md` §14.

## Decisión

- Mantener **dos specs generados por Scribe** (ver ADR-0003): `config/scribe.php` (interno,
  incluye todo `api/*`) y `config/scribe_public.php` (público, extiende al interno y excluye
  explícitamente por prefijo).
- Prefijos excluidos del contrato público: `api/v2/superadmin/*`, `api/v2/public/impersonation/*`,
  `api/v2/debug/*`, `api/v2/internal/*`, `api/v2/system/*`, `GET /api/health`.
  `api/v2/public/tenant/{subdomain}` se incluye deliberadamente (endpoint público de resolución de
  tenant antes de login).
- Solo el spec público (`public/openapi/frontend.yaml`) se versiona en git y se publica en una URL
  estática; el spec interno (`public/docs/`) no se versiona.
- Cualquier endpoint administrativo/interno **nuevo** debe registrarse bajo uno de los prefijos ya
  excluidos, o añadir una exclusión nueva explícita antes de mergear — no se asume exclusión por
  defecto.
- Test de regresión obligatorio (`tests/Feature/ApiDocumentationTest.php::
  test_public_openapi_spec_excludes_sensitive_routes` y `::test_public_openapi_spec_includes_business_routes`)
  para detectar tanto una fuga de rutas sensibles como una exclusión demasiado agresiva.

## Consecuencias

### Positivas
- Aislamiento claro entre "lo que ve el frontend/app móvil" y "lo que usa el equipo backend para
  introspección total", sin necesidad de mantener dos routers ni dos árboles de controladores.
- El test de regresión hace que una fuga de rutas administrativas sea un fallo de CI detectable,
  no un hallazgo de auditoría manual posterior.

### Negativas / Trade-offs
- Las dos configs (`scribe.php`/`scribe_public.php`) deben mantenerse sincronizadas manualmente
  cuando cambian ajustes base (auth, tenant header) — `scribe_public.php` extiende a `scribe.php`
  precisamente para minimizar esta duplicación, pero la lista de exclusiones sigue siendo manual.
- Un desarrollador/agente que añade un endpoint administrativo nuevo bajo un prefijo no
  contemplado hoy (distinto de los 5 ya excluidos) puede exponerlo por descuido si no ejecuta
  `composer contract:test` antes de mergear.

### Neutras
- `v2/superadmin/*` sigue registrado en el mismo `routes/api.php` que el resto de la API (fuera
  del grupo `TenantMiddleware`) — esta ADR resuelve la exposición en el *contrato publicado*, no
  reorganiza `routes/api.php` en sí (fuera de alcance, ver `API-CONTRACT-008` en
  `docs/api-contract-master-plan.md` §4, "Bajo para el contrato, mitigado").

## Alternativas consideradas

- **Publicar un único contrato completo, protegido por autenticación adicional** (VPN, IP
  allowlist): descartada por mayor complejidad operativa sin beneficio claro frente a excluir las
  rutas sensibles del spec público directamente.
- **Confiar en documentación manual** ("no uses estas rutas desde el frontend") sin exclusión
  técnica del spec: descartada — no protege nada realmente, y es exactamente el tipo de acuerdo
  informal que un generador de tipos automatizado ignoraría sin darse cuenta.

## Referencias

- `API_CONTRACT_AUDIT.md` §14 (riesgos de publicar OpenAPI por URL).
- `config/scribe_public.php`.
- `tests/Feature/ApiDocumentationTest.php`.
- `docs/api-contract.md` §4 (tabla de prefijos excluidos y motivo).
- `docs/api-contract-master-plan.md` (decisiones D3/D4, deuda `API-CONTRACT-008`).
