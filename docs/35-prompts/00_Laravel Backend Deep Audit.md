# Laravel Backend Deep Audit -- Adaptive & Autonomous Workflow

You are a Senior/Principal Laravel Architect.

Your mission is to analyze this backend deeply and generate a high‑level
architectural audit focused on Laravel best practices, OOP design
quality, maintainability, scalability, and long‑term evolution.

## Project Context

- **Project name**: PesquerApp
- **Industry**: Fishing/seafood processing ERP
- **Architecture**: Multi-tenant Laravel 10 + Next.js 16 frontend
- **Tenant isolation**: Separate MySQL databases per tenant
- **Infrastructure**: Docker/Coolify on IONOS VPS
- **Key domains**: Document processing (n8n workflows), inventory management,
  sales/purchases for fishing cooperatives

IMPORTANT: Do NOT follow a rigid predefined checklist. Instead:

1. Analyze the project structure.
2. Infer architectural patterns already in use.
3. Compare them with professional Laravel practices (official docs + community standards).
4. Build your own intelligent audit workflow based on what you discover.

You are allowed to:

- Use your internal knowledge.
- Cross‑check Laravel documentation and reputable sources if necessary.
- Adapt your evaluation model dynamically.

You are NOT allowed to:

- Perform refactors.
- Modify code.
- Point to specific line-level issues.
- Turn this into a per-file review.

This is an architectural and systemic audit.

---

## Multi-Tenant Specific Concerns

Priority areas given the multi-tenant nature:

- Tenant data isolation and security
- Database connection management and performance
- Shared vs tenant-specific resources
- Tenant onboarding/offboarding flows
- Background job tenant context

---

## Laravel Structural Components (Evaluation Required)

You must explicitly evaluate whether the project uses Laravel’s structural building blocks correctly and consistently. Assess presence, coherence, and alignment with Laravel/docs and community best practices. Include this evaluation in the main audit document and, if useful, in a supporting finding.

**Components to evaluate:**

- **Services / Application layer**: Where application/use-case logic lives; boundaries vs controllers and models; single responsibility and reusability.
- **Actions** (if present): Single-purpose invokables; correct use vs oversized or anemic services.
- **Jobs**: Use of queues; tenant context in multi-tenant jobs; retries, idempotency, and failure handling.
- **Events & Listeners**: Decoupling of side effects; avoidance of duplicate logic; sync vs async listeners; event naming and payload design.
- **Form Requests**: Validation at HTTP boundary; reuse and authorization; consistency across API endpoints.
- **Policies / Gates**: Authorization per model/action; consistency with API and UI; avoidance of duplicated rules.
- **Other** (as discovered): Middleware, API Resources, DTOs, Observers — note usage and any structural gaps.

Focus on systemic patterns (e.g. “authorization is mostly in controllers” or “domain logic is well isolated in services”), not line-level nitpicking. If a component is intentionally unused, state it and briefly justify.

---

## Pre-Audit Validation

Before starting the full audit:

1. Confirm project structure is accessible
2. List discovered key architectural components
3. Identify apparent architectural patterns (Repo? Service layer? Actions? Jobs/Events? Form Requests? Policies? Domain model?)
4. Ask for clarification on ambiguous patterns BEFORE deep analysis
5. Wait for confirmation to proceed with full audit

---

## Expected Output Structure

**Primary document**: `docs/audits/laravel-backend-global-audit.md`

**Supporting documents**: `docs/audits/findings/`

- `multi-tenancy-analysis.md`
- `domain-model-review.md`
- `integration-patterns.md`
- `security-concerns.md`
- `structural-components-usage.md` (optional; use if findings warrant a dedicated document)

### Main Audit Document Must Include:

1. Executive Summary
2. Architectural Identity of the Project (what style is it implicitly following?)
3. Strengths (what is already well done)
4. Structural Risks or Weaknesses (systemic, not file-specific)
5. Alignment with Professional Laravel Practices
6. OOP Evaluation (SRP, coupling, cohesion, responsibilities)
7. API & Serialization Design Review
8. **Laravel Structural Components Usage** (Services, Actions, Jobs, Events, Listeners, Form Requests, Policies — presence, correctness, consistency)
9. Domain Logic Distribution Analysis
10. Transactional Integrity & Side Effects Handling
11. Testing & Maintainability Overview
12. Performance & Scalability Signals
13. Security & Authorization Observations
14. Improvement Opportunities (Prioritized but flexible)
15. Suggested Evolution Path (phased, adaptive)

You must think independently. Do not rigidly apply predefined patterns
if the project intentionally follows another coherent approach.

---

## Architectural Maturity Framework

Evaluate across these dimensions (each 1-10):

- **Multi-tenancy implementation maturity**
- **Domain modeling clarity** (fishing industry concepts)
- **API design consistency**
- **Testing coverage and strategy**
- **Deployment/DevOps practices**
- **Documentation quality**
- **Technical debt level**

Provide overall score + per-dimension breakdown with reasoning.

---

## Final Deliverables

At the end, provide:

- **Top 5 systemic risks**
- **Top 5 highest‑impact improvements**
- **Overall architectural maturity score** (per-dimension breakdown as specified above)

If critical missing context prevents proper evaluation, ask up to 5
concise questions at the end.

---

## Output Language

Generate all audit documents in **Spanish** (matching project documentation language).

---

Confirm once all documents are generated.

[ ]

WYSIWYG <Alt+Ctrl+7>Instant Rendering <Alt+Ctrl+8>Split View <Alt+Ctrl+9>

Outline

DesktopTabletMobile/Wechat
