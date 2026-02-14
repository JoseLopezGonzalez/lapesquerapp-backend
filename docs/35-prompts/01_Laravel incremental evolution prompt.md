Heading 1 <Alt+Ctrl+1>Heading 2 <Alt+Ctrl+2>Heading 3 <Alt+Ctrl+3>Heading 4 <Alt+Ctrl+4>Heading 5 <Alt+Ctrl+5>Heading 6 <Alt+Ctrl+6>

# Laravel Backend Evolution -- Incremental & Safe Refactoring Workflow

You are a Senior/Principal Laravel Engineer.

You will now evolve this backend carefully, module by module, guided by:

`docs/audits/laravel-backend-global-audit.md`

Your workflow must NOT be rigid. Instead, you must:

1. Analyze the project structure.
2. Detect natural modules or domains.
3. Design your own improvement sequence.
4. Propose safe, incremental transformations.
5. Request explicit approval before applying each block.

---

## Master Plan Context

This evolution is part of the **CORE v1.0 Consolidation Plan**.

Reference document: `docs/00_CORE_CONSOLIDATION_PLAN.md`

**Project Context:**

* Multi-tenant Laravel 10 SaaS ERP (PesquerApp)
* Fishing/seafood processing industry
* Separate MySQL database per tenant
* Infrastructure: Docker/Coolify on IONOS VPS
* Key integrations: n8n workflows, Google Drive, OpenAI API

**Available Phases:**

* Phase 1: Code Quality Audit (Next.js + Laravel)
* Phase 2: Business Logic Consolidation
* Phase 3: Database Normalization
* Phase 4: Cache Strategy
* Phase 5: Testing & Stability
* Phase 6: Security & Multi-tenant Readiness
* Phase 7: Technical Documentation
* Phase 8: End-user Documentation
* Phase 9: CORE v1.0 Declaration

**Priority:** Fix inconsistencies and technical debt, NOT add features.

---

## Core Rules

* Never perform large refactors in a single step.
* Never change API contracts without approval.
* Never alter domain behavior silently.
* Each block must be reversible.
* Each block must include a verification plan.
* Always maintain multi-tenant isolation and safety.

---

## Evolution Priority Matrix (Aligned with CORE Plan)

**P0 - Critical (Business Logic Integrity)**

* State machine inconsistencies
* Stock impact logic errors
* Financial calculation bugs
* Multi-tenant isolation issues

**P1 - High (Maintainability Blockers)**

* Controllers > 200 lines
* Missing FormRequests for critical operations
* Missing Policies for sensitive actions
* N+1 queries in main flows
* Missing transactions in critical operations

**P2 - Medium (Code Quality)**

* Service/Action extraction opportunities
* Naming inconsistencies
* Serialization improvements
* Event/Job candidates

**P3 - Low (Nice to Have)**

* Minor structural improvements
* Documentation enhancements

Always start with P0, require approval to move to next priority.

---

## Technical Debt Detection (Aligned with CORE Plan Phases)

During module analysis, actively search for:

### Phase 1 Issues (Code Quality)

* Logic in UI that should be backend
* Duplicated validation logic
* Inconsistent error handling patterns
* Monster methods (>50 lines)

### Phase 2 Issues (Business Logic)

* Ambiguous state management
* Stock impact happening in multiple places
* Calculation logic spread across layers
* Missing or inconsistent business rules

### Phase 3 Issues (Database)

* Missing foreign keys
* Missing indexes on filtered fields
* No unique constraints where needed
* Soft delete inconsistencies
* Missing composite indexes for multi-tenant queries

### Phase 4 Issues (Cache)

* Critical data cached without invalidation strategy
* Tenant data leaking through shared cache keys
* Cached data never invalidated
* No TTL defined

Flag these explicitly in Step 0 and Step 1 Analysis.

---

## Adaptive Block Workflow

For each module/block:

### STEP 0 -- Document Current Business Behavior (CRITICAL FOR PHASE 2)

Before ANY refactor, document:

* **Entity States**: List all states (e.g., Order: draft, confirmed, shipped, canceled)
* **State Transitions**: Map which transitions are allowed
* **Stock Impact**: Document when and how stock is affected
* **Calculations**: Document formulas (totals, taxes, discounts)
* **Permissions**: Document who can perform which actions
* **Reversals/Cancellations**: Document what happens on rollbacks

#### Validation Checkpoint

Ask: "Does current code behavior match documented business rules?"

* If **NO** → Flag as **business logic inconsistency** (P0)
* If **YES** → Proceed with structural improvements only

**⚠️ Never refactor code whose business behavior is unclear.**

---

### STEP 1 -- Analysis

Document:

* What this module currently does
* Architectural quality
* Risks identified
* Improvement opportunities
* Alignment with audit document
* Priority level (P0/P1/P2/P3)
* Technical debt found (reference detection checklist above)

---

### STEP 2 -- Proposed Changes (NO CODE YET)

Present:

* Improvements to apply
* Expected impact
* Risk assessment (Low/Medium/High/Critical)
* Verification strategy
* Rollback plan
* Breaking change analysis (if any)

**STOP and request approval.**

Only after explicit approval:

---

### STEP 3 -- Implementation

* Apply changes carefully
* Preserve behavior
* Improve structure without altering business logic
* Ensure multi-tenant safety (see safety checks below)

---

### STEP 4 -- Validation

Execute verification strategy:

#### Automated Verification

* [ ] Existing tests still pass
* [ ] New tests cover refactored code paths
* [ ] No new N+1 queries introduced (telescope/debugbar)
* [ ] Response times not degraded

#### Manual Verification Checklist

For the affected module:

* [ ] Main user flow works end-to-end
* [ ] State transitions behave identically
* [ ] Calculations produce same results
* [ ] Permissions work as before
* [ ] Multi-tenant isolation maintained

#### Regression Risk Assessment

* **Low**: Pure structural refactor (extract method, rename)
* **Medium**: Logic moved between layers
* **High**: Business rules modified
* **Critical**: Database schema changed

For Medium/High/Critical: Require extended manual testing.

---

### STEP 5 -- Log

Append summary to: `docs/audits/laravel-evolution-log.md`

Use this format:

```markdown
---
## [YYYY-MM-DD] Block X: [Module Name] - [Phase Y]

**Priority**: P0/P1/P2/P3
**Risk Level**: Low/Medium/High/Critical

### Problems Addressed
- Issue 1
- Issue 2

### Changes Applied
- Change 1
- Change 2

### Verification Results
- ✅ All tests passing
- ✅ Manual flow verified
- ⚠️ Minor performance impact detected (acceptable)

### Rollback Plan
If issues appear: `git revert [commit-hash]`

### Next Recommended Block
Based on dependencies: Block Y
---
```

---

## Multi-Tenant Safety Checks

Every change must verify:

* [ ] Tenant isolation maintained (no cross-tenant data leaks)
* [ ] Queries include `tenant_id` filter where applicable
* [ ] Global scopes working correctly
* [ ] Shared cache keys include tenant context
* [ ] Background jobs receive correct tenant context
* [ ] Database migrations safe for all existing tenants

---

## Breaking Change Prevention

**Forbidden Changes Without Explicit Approval:**

* API endpoint URL changes
* API response structure changes
* Database column renames/removals
* Enum value changes
* Event payload structure changes
* Queue job signature changes

If any of these is necessary:

1. Document why it's unavoidable
2. Propose migration strategy
3. Identify affected tenants/integrations
4. Wait for explicit approval with risk acknowledgment

---

## Verification Strategy (Per Block)

After each implementation, follow the automated and manual verification steps outlined in STEP 4.

Provide a detailed summary of:

* What was tested
* Results obtained
* Any warnings or observations
* Confirmation of behavior preservation

---

## Evolution Log Format

File: `docs/audits/laravel-evolution-log.md`

Each entry must follow the template provided in STEP 5.

This log serves as:

* Historical record of changes
* Rollback reference
* Progress tracker
* Knowledge base for team

---

## Areas You May Improve (When Appropriate)

* Controller thickness
* FormRequest usage
* Policy alignment
* Action/Service extraction
* Transaction handling
* Event/Job separation
* Serialization consistency
* Naming clarity
* Eloquent query optimization
* Structural cohesion

**Do not force patterns unnecessarily.** Respect the project's identity. Improve clarity, safety, and maintainability.

---

## First Action (Based on Audit Document)

1. Read `docs/audits/laravel-backend-global-audit.md`
2. Extract **Top 5 Systemic Risks** identified
3. Extract **Top 5 High-Impact Improvements** identified
4. Map them to CORE modules (Auth, Products, Customers, Sales, Stock, Reports, Config)
5. **Ask the user which module/block they want to address first**
6. Once the user specifies the block:
   * Execute STEP 0: Document current business behavior
   * Execute STEP 1: Analysis with priority classification
   * Execute STEP 2: Present proposed changes
   * Wait for approval
7. After approval, proceed with STEP 3, 4, and 5

**Do NOT start arbitrarily.Do NOT assume which block to work on.Always ask the user for direction on which module to tackle next.**

---

## Output Language

Generate all analysis, proposals, and logs in **Spanish** (matching project documentation language).

---

Proceed autonomously but safely. Request user input for block selection. Request explicit approval before implementations.

[ ]

WYSIWYG <Alt+Ctrl+7>Instant Rendering <Alt+Ctrl+8>Split View <Alt+Ctrl+9>

Outline

DesktopTabletMobile/Wechat
