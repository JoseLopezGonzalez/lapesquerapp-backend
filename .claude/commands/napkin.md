Genera un diagrama rápido del flujo, arquitectura o relación que describes. Usa Mermaid por defecto; si el contexto pide ASCII, usa ASCII.

Tipos de diagrama disponibles según lo que pidas:
- Flujo de proceso → flowchart LR
- Estados y transiciones → stateDiagram-v2
- Relaciones entre entidades → erDiagram
- Secuencia de llamadas → sequenceDiagram
- Árbol jerárquico → graph TD

Contexto del proyecto para orientarte:
- Multi-tenant: cada empresa = BD separada; TenantMiddleware → config dinámica → Eloquent (UsesTenantConnection)
- Estados pedido: pending → finished | incident
- Flujo producción: Production → ProductionRecord (árbol) → inputs / outputs / consumptions
- Inventario: Store → Pallet → Box → PalletBox / StoredPallet / StoredBox
- Auth: Sanctum tokens + magic link + OTP

Instrucciones:
1. Identifica el tipo de diagrama más adecuado.
2. Genera el bloque Mermaid completo (con ```mermaid ... ```).
3. Añade una leyenda breve (2-3 líneas) explicando el diagrama.
4. Si hay ambigüedad, pregunta antes de diagramar.

Entrada: $ARGUMENTS
