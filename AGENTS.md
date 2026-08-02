# AGENTS.md

Fuente única de instrucciones para agentes de IA en este repositorio: **[`CLAUDE.md`](./CLAUDE.md)**.

Este archivo existe solo como punto de entrada para herramientas que buscan `AGENTS.md` por
convención (GitHub Copilot, otros CLIs agénticos). No dupliques reglas aquí — léelas en
`CLAUDE.md` y, si trabajas en algo relacionado con la API REST v2, presta especial atención a:

- **§19 Contrato OpenAPI de la API** — Laravel es la fuente de verdad del contrato; reglas
  obligatorias antes de tocar rutas, Form Requests, Resources o controladores de `v2/*`.
- **`docs/api-contract/API_CONTRACT_MASTER_PLAN.md`** — plan maestro y fuente de seguimiento del
  contrato: fases, deuda contractual (`API-CONTRACT-XXX`), decisiones y próxima acción
  recomendada. Léelo antes de trabajar en cualquier cosa relacionada con el contrato de la API.
- **`docs/api-contract.md`** — cómo generar, verificar y publicar el contrato OpenAPI.
- **§18 Workflow de Evolución** — proceso de 7 pasos para evolucionar un bloque funcional.

Otros sistemas de instrucciones presentes en este repositorio y su alcance (para no confundirlos
con `CLAUDE.md`, que es la fuente principal):

- `.claude/agents/`, `.claude/commands/` — agentes y skills específicos de Claude Code, alineados
  con `CLAUDE.md` (remiten a él en vez de duplicar reglas).
- `.ai_standards/`, `.cursor/rules/` — protocolo de "memoria de trabajo" para sesiones largas en
  Cursor (carpeta `.ai_work_context/` por sesión). Es un protocolo de *proceso* de trabajo, no de
  reglas de dominio o de arquitectura — no sustituye a `CLAUDE.md`.
- `.agents/skills/` — skills genéricas de Laravel (no específicas de este proyecto).
