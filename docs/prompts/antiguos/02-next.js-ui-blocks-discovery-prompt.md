# Prompt: Descubrimiento de Bloques UI en Next.js — Anexo al CORE Consolidation Plan

**Propósito:** Guiar la localización profesional de bloques funcionales en el frontend Next.js y generar un anexo (ANEXO B) que se añade al documento CORE Consolidation Plan, complementando el inventario de bloques backend (ANEXO A).

**Referencias:**
- `docs/core-consolidation-plan-erp-saas.md`
- ANEXO A (Inventario de Bloques Backend) en el mismo documento

---

## Instrucciones para el agente

Eres un arquitecto frontend senior. Tu tarea es **localizar de forma sistemática** todos los bloques funcionales (módulos, features, dominios) que existen en la UI del proyecto Next.js y documentarlos como un anexo nuevo del CORE Consolidation Plan.

### Alcance

- **Proyecto**: Frontend PesquerApp (Next.js 14+ con App Router).
- **Ruta al proyecto**: Si el frontend está en un repo separado, el usuario te indicará la ruta (ej. `../lapesquerapp-frontend` o monorepo). Si no se indica, asume que está en el mismo workspace o en una carpeta `frontend/`.
- **Objetivo**: Inventario de bloques UI que pueda alinearse con los bloques backend del ANEXO A.

---

## Metodología de descubrimiento

### 1. Rutas y páginas (App Router)

- Inspecciona `app/` (o `src/app/`).
- Identifica todas las rutas: carpetas `page.tsx`, `page.js`, `layout.tsx`.
- Agrupa por segmento de ruta: `/orders`, `/customers`, `/production`, `/settings`, etc.
- Anota rutas dinámicas (`[id]`, `[slug]`) y rutas anidadas.

### 2. Navegación y menús

- Busca menús, sidebars, breadcrumbs: componentes de navegación, constantes de rutas, configuraciones de menú.
- Patrones: `navigation.ts`, `menuConfig.ts`, `sidebarItems`, arrays de rutas con labels.
- Lista las secciones visibles para el usuario y sus rutas destino.

### 3. Componentes por feature/dominio

- Revisa `components/`, `features/`, `modules/` o carpetas análogas.
- Agrupa componentes por dominio (orders, customers, production, inventory, etc.).
- Distingue entre componentes compartidos (ui/) y componentes de dominio.

### 4. Data fetching y API

- Busca llamadas a la API v2: `fetch`, `useSWR`, `useQuery`, `apiClient`, hooks personalizados.
- Identifica endpoints consumidos por cada flujo (orders, raw-material-receptions, etc.).
- Relaciona cada bloque UI con los endpoints backend correspondientes.

### 5. Estado global

- Si hay Zustand, Redux, Context u otro store: identifica slices o módulos por dominio.
- Documenta qué bloques tienen estado global dedicado.

### 6. Cross-reference con ANEXO A

- Para cada bloque backend (A.1–A.16), verifica si existe su equivalente en la UI.
- Marca bloques backend **sin** representación UI explícita.
- Marca bloques UI que no tengan un bloque backend directo (ej. dashboards, vistas compuestas).

---

## Orden de búsqueda recomendado

1. **app/** — Estructura de rutas y páginas.
2. **Archivos de navegación** — `*navigation*`, `*menu*`, `*sidebar*`, `*routes*`.
3. **Componentes** — `components/`, `features/`, `modules/`.
4. **Hooks y servicios** — `hooks/`, `services/`, `api/`, `lib/`.
5. **Estado** — `store/`, `context/`, slices.

---

## Formato del output: ANEXO B

El resultado debe ser un bloque de Markdown listo para insertar en el documento CORE Consolidation Plan, con esta estructura:

```markdown
# ANEXO B — Inventario de Bloques UI (Next.js)

Inventario de bloques funcionales identificados en el frontend Next.js. Alineado con ANEXO A (backend).

## Bloques UI identificados

### B.1 [Nombre del bloque]
| Tipo | Detalle |
|------|---------|
| **Rutas** | /ruta/ejemplo, /ruta/[id] |
| **Páginas principales** | app/orders/page.tsx, app/orders/[id]/page.tsx |
| **Componentes clave** | OrderList, OrderForm, OrderDetail |
| **API consumida** | GET/POST /api/v2/orders, ... |
| **Bloque backend** | A.2 Ventas |

### B.2 ...
...

## Bloques backend sin UI explícita

- A.X [Nombre] — Motivo (ej. solo API interna, en desarrollo, etc.)

## Bloques UI sin bloque backend directo

- Dashboard principal
- ...

## Mapeo Bloque UI ↔ Bloque Backend

| Bloque UI | Bloque Backend |
|-----------|----------------|
| B.1 Pedidos | A.2 Ventas |
| ... | ... |

---

*Fuente: análisis de app/, components/, navegación, data fetching. Última revisión: [fecha].*
```

---

## Reglas de calidad

- **Exhaustividad**: No dejar rutas ni secciones de menú sin asignar a un bloque.
- **Consistencia**: Usar los nombres de bloques del ANEXO A cuando corresponda.
- **Trazabilidad**: Siempre indicar qué endpoints consumen y qué bloque backend mapean.
- **Objetividad**: Documentar lo que existe, no lo que debería existir.

---

## Salida esperada

1. **ANEXO B** en formato Markdown (listo para pegar en el CORE plan).
2. **Lista de rutas/páginas** agrupadas por bloque.
3. **Gaps identificados**: bloques backend sin UI; bloques UI sin backend claro.

---

## Coletilla para invocar

Puedes usar esta coletilla al invocar el prompt:

```
@docs/prompts/02_Next.js UI Blocks Discovery Prompt.md

Localiza los bloques UI en el proyecto Next.js [ruta si aplica] y genera el ANEXO B 
para el CORE Consolidation Plan. Usa el ANEXO A como referencia de bloques backend.
```
