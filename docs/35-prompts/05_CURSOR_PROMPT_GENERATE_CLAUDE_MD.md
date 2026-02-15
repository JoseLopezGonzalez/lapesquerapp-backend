# Prompt para Cursor: Generar CLAUDE.md Profesional

Usa este prompt EN CURSOR para generar un CLAUDE.md completo y detallado.

## COPIAR Y PEGAR EN CURSOR:
```
TAREA: Generar un CLAUDE.md detallado para PesquerApp

Este archivo será leído automáticamente por Claude Code (agente de IA) 
ANTES de cada sesión. Debe describir COMPLETAMENTE el proyecto.

Lee PRIMERO estos documentos:
1. docs/00_CORE CONSOLIDATION PLAN — ERP SaaS (Next.js + Laravel).md
2. docs/35-prompts/01_Laravel incremental evolution prompt.md
3. docs/audits/laravel-backend-global-audit.md
4. docs/audits/laravel-evolution-log.md
5. docs/audits/00_Laravel Backend Deep Audit.md

GENERA un CLAUDE.md profesional que incluya:

1. IDENTIDAD DEL PROYECTO
   - Nombre, descripción, industria, stack
   - Objetivo general

2. ARQUITECTURA MULTI-TENANT
   - Cómo funciona la separación de datos
   - DB separada por tenant
   - Trait UsesTenantConnection
   - Validaciones tenant
   - Seguridad contra cross-tenant queries

3. MODELOS DE DOMINIO
   - TODAS las entidades principales (Order, Product, Pallet, etc.)
   - Relaciones entre ellas
   - Propósito de cada una

4. TERMINOLOGÍA PESQUERA
   - Glosario completo
   - Caladero, FAO Zone, Calibre, etc.
   - Estados de pedidos y transiciones

5. ESTRUCTURA DE CARPETAS
   - Mapeo de app/
   - Qué va en cada carpeta
   - Patrones usados

6. CONVENCIONES DE CÓDIGO
   - Nomenclatura
   - Controllers thin (< 200 líneas)
   - Form Requests: validación + autorización
   - Policies: autorización por modelo
   - Services: lógica de negocio
   - Tests: Feature tests
   - Transacciones

7. REGLAS DE NEGOCIO CRÍTICAS
   - Estados de pedidos y transiciones
   - Stock: cálculo y validación
   - Trazabilidad
   - Barcode GS1-128
   - Permisos por rol
   - Multi-tenant rules

8. ESTADO ACTUAL DEL CORE v1.0
   - Tabla de bloques (A.1 a A.14)
   - Rating actual
   - Estado (✅/🔄/⏳)
   - Issues conocidos

9. STACK TECNOLÓGICO
   - Laravel 10, PHP 8.2+, MySQL 8.0
   - Next.js 16, Node.js
   - Docker, Coolify
   - Pint, PHPStan, Pest/PHPUnit

10. TESTING STRATEGY
    - Framework usado
    - Feature tests para endpoints
    - ConfiguresTenantConnection trait
    - Cobertura: >= 80%

11. DEPLOYMENT
    - IONOS VPS
    - Docker Compose
    - Coolify
    - Backup strategy

12. API REST v2 DESIGN
    - Base path: /api/v2/
    - Headers requeridos (X-Tenant, Authorization)
    - Paginación, filtrado, búsqueda
    - Error handling
    - Rate limiting

13. WORKFLOWS PRINCIPALES
    - Flujo de pedido
    - Recepción de materia prima
    - Producción
    - Despacho
    - Etiquetas
    - Fichajes

14. INTEGRACIONES EXTERNAS
    - n8n para documentos
    - GPT para clasificación
    - Webhooks

15. PROBLEMAS CONOCIDOS & DECISIONES ARQUITECTÓNICAS
    - Por qué multi-tenant con DB separada
    - Trade-offs conocidos
    - Deuda técnica
    - Decisiones importantes

16. PERFORMANCE & SCALABILITY
    - Bottlenecks conocidos
    - Índices importantes
    - N+1 prevention

17. SEGURIDAD
    - Autenticación (Sanctum)
    - Autorización (Policies)
    - Validación
    - CORS, CSRF
    - Audit logging

18. WORKFLOW DE EVOLUCIÓN (PARA CLAUDE CODE)
    - Los 7 pasos del workflow
    - Escala de Rating (1-10)
    - Criterios de completitud
    - Cómo documentar cambios

19. REFERENCIAS IMPORTANTES
    - Rutas a documentos clave
    - Links a decisiones
    - Links a prompts

---

REQUISITOS:
✅ 2000-3000 palabras
✅ Profesional y detallado
✅ Markdown bien formateado
✅ ESPECÍFICO para PesquerApp (no genérico)
✅ Incluye ejemplos reales
✅ Estado actual del CORE v1.0
✅ Útil para un agente de IA
✅ Todas las reglas críticas

NO genérico, SÉ específico sobre convenciones y por qué existen.
EXTRAE info de los documentos de auditoría.
DESCRIBE flujos reales, no teóricos.

Genera el archivo completo.
```

---

## Cómo usarlo:

1. Abre Cursor en tu proyecto
2. Copia TODO el contenido entre los tres backticks (```), menos estos mismos backticks
3. Pégalo en el chat de Cursor
4. Espera a que genere
5. Copia el resultado
6. Guárdalo como `CLAUDE.md` en la raíz del proyecto
7. Reemplaza el archivo anterior (que era muy escueto)
8. Commit: `git add CLAUDE.md && git commit -m "chore: Update CLAUDE.md with comprehensive project context"`

---

Este será tu VERDADERO CLAUDE.md que Claude Code leerá en cada sesión.
