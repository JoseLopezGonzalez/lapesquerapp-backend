# CORE CONSOLIDATION PLAN — ERP SaaS (Next.js + Laravel)

**Objetivo:**  ** **Consolidar el Core existente del ERP para declararlo estable (v1.0), eliminando inconsistencias, deuda técnica y riesgos, garantizando:

* **Funcionalidad completa y coherente**
* **Lógica de negocio sólida**
* **Base de datos consistente**
* **Buenas prácticas reales (Next.js + Laravel)**
* **Rendimiento y caché controlados**
* **Seguridad y permisos correctos**
* **Documentación técnica y para usuario final**
* **Preparación para escalar a nuevos tenants**

**Este documento NO es para construir desde cero.**  ** **Es para AUDITAR, MEJORAR y CONSOLIDAR lo que ya existe.

---

# FASE 0 — Definir qué es el CORE v1.0

**Antes de mejorar nada, debemos definir qué bloques forman el Core comercial estable.**

**Ejemplo típico:**

* **Auth + Roles/Permisos**
* **Productos (y anidados)**
* **Clientes**
* **Ventas (Orders + líneas + estados)**
* **Stock / Movimientos**
* **Informes básicos**
* **Configuración por tenant**

**⚠️ Todo lo que quede fuera es “roadmap”, no core.**

---

# FASE 1 — Auditoría Global de Calidad de Código

## 1.1 Auditoría Next.js

**Revisar:**

* **Separación correcta entre Server Components y Client Components**
* **Fetching consistente (sin duplicidades innecesarias)**
* **Manejo homogéneo de errores**
* **Estado global controlado (evitar renders innecesarios)**
* **Componentes reutilizables limpios**
* **Eliminación de lógica de negocio en UI**

**Verificar:**

* **No hay lógica crítica en el frontend que deba estar en backend**
* **Formularios correctamente validados**
* **Estructura coherente por features**

**Resultado esperado:**

* **Código legible**
* **Estructura consistente**
* **Sin patrones improvisados**

---

## 1.2 Auditoría Laravel

**Revisar:**

* **Controllers finos**
* **Validaciones en FormRequest**
* **Lógica de negocio en Services / Actions**
* **Uso correcto de Policies**
* **Eager loading correcto (sin N+1)**
* **Transacciones en operaciones críticas**
* **Uso consistente de Resources / DTOs**

**Eliminar:**

* **Lógica duplicada**
* **Métodos gigantes**
* **Consultas repetidas**

**Resultado esperado:**

* **Backend limpio**
* **Separación clara de responsabilidades**
* **Código escalable**

---

# FASE 2 — Auditoría y Consolidación de Lógica de Negocio

**Este es el punto más importante.**

**Para cada bloque del Core:**

## 2.1 Inventario funcional real

**Ejemplo: Ventas**

**Entidades involucradas:**

* **Productos**
* **Clientes**
* **Salesperson**
* **Orders**
* **OrderLines**
* **StockMovements**

**Estados existentes:**

* **draft**
* **confirmed**
* **shipped**
* **canceled**
* **etc.**

**Documentar cómo funciona HOY.**

---

## 2.2 Verificación de reglas

**Comprobar:**

* **¿Cuándo se impacta el stock?**
* **¿Se permite stock negativo?**
* **¿Qué campos se bloquean según estado?**
* **¿Los totales son 100% consistentes?**
* **¿Las anulaciones revierten correctamente?**

**Detectar incoherencias.**

---

## 2.3 Consolidar reglas

**Definir una única verdad:**

* **Regla clara de estados**
* **Regla clara de stock**
* **Regla clara de permisos**
* **Regla clara de numeración**

**Eliminar ambigüedades.**

**Resultado:** **Lógica predecible y consistente.**

---

# FASE 3 — Base de Datos: Normalización y Consistencia

## 3.1 Revisar integridad

* **Claves foráneas correctas**
* **Índices en campos críticos**
* **Unique constraints necesarias**
* **Eliminaciones coherentes (soft delete o no)**

## 3.2 Normalización

**Verificar:**

* **No hay duplicación innecesaria**
* **Relaciones bien definidas**
* **Estructura preparada para multi-tenant**

## 3.3 Rendimiento

**Añadir:**

* **Índices en filtros habituales**
* **Índices compuestos (tenant + fecha + estado)**
* **Optimización de queries pesadas**

**Resultado:** **BD sólida y eficiente.**

---

# FASE 4 — Estrategia de Caché Profesional

## 4.1 Caché en Next.js

**Definir:**

* **Qué se cachea**
* **Qué no se cachea**
* **TTL**
* **Estrategia de invalidación**

**Evitar:**

* **Datos cacheados inconsistentes**
* **Datos sensibles compartidos**

---

## 4.2 Caché en Laravel

**Aplicar cache solo en:**

* **Catálogos**
* **Configuraciones**
* **Lecturas pesadas**

**Implementar:**

* **Invalidación por evento**
* **TTL razonable**
* **No cachear datos transaccionales críticos sin control**

**Resultado:** **Mejor rendimiento sin incoherencias.**

---

# FASE 5 — Tests y Estabilidad

**No buscamos 100% coverage.**

**Buscamos estabilidad real del Core.**

## Backend

* **Tests de estados**
* **Tests de totales**
* **Tests de permisos**
* **Tests de stock**

## Frontend

* **Checklist reproducible por bloque**
* **Flujos críticos probados manualmente**

**Resultado:** **No se rompe al añadir nuevos tenants.**

---

# FASE 6 — Seguridad y Multi-Tenant Readiness

* **Aislamiento correcto por tenant**
* **Permisos revisados**
* **Auditoría mínima activada**
* **Logs con contexto (tenantId, userId)**
* **Backups probados**

---

# FASE 7 — Documentación Técnica Interna

**Generar documentación técnica viva (idealmente web):**

**Debe incluir:**

* **Arquitectura general**
* **Flujo de datos**
* **Estados por bloque**
* **Reglas de negocio consolidadas**
* **Estrategia de caché**
* **Modelo de BD simplificado**
* **Guía de despliegue**

**Objetivo:** **Cualquier desarrollador nuevo puede entender el sistema.**

---

# FASE 8 — Documentación Web para Usuario Final

**Fundamental antes de escalar.**

**Crear documentación tipo:**

* **Manual por bloques**
* **Cómo crear un pedido**
* **Cómo gestionar stock**
* **Estados y su significado**
* **FAQ**
* **Buenas prácticas de uso**

**Formato recomendado:**

* **Web tipo documentation site (Docusaurus / Nextra / similar)**
* **Buscable**
* **Con capturas reales**
* **Actualizable por versión**

**Esto reduce soporte y aumenta profesionalidad.**

---

# FASE 9 — Declaración de CORE v1.0

**Se puede declarar Core estable cuando:**

* **Lógica coherente y documentada**
* **BD consistente**
* **Sin incoherencias graves**
* **Caché controlada**
* **Tests básicos superados**
* **Documentación publicada**
* **No hay cambios estructurales constantes**

**A partir de aquí:**

* **Nuevas funcionalidades → roadmap**
* **Cambios estructurales → versionados**
* **Foco en estabilidad y nuevos tenants**

---

# RESULTADO FINAL

**Un ERP:**

* **Sólido**
* **Predecible**
* **Escalable**
* **Profesional**
* **Documentado**
* **Listo para vender e implantar**
