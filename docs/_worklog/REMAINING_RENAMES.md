# Renombrados pendientes (Fase 1 — batch)

Archivos que aún requieren renombrar a formato `NN-topic[-scope].md` (kebab-case, sin mayúsculas).

**Regla:** Convertir a minúsculas el topic; preservar NN si ya existe.

## Por carpeta

### catalogos/
- 40-Productos.md → 40-productos.md
- 41-Categorias-Familias-Productos.md → 41-categorias-familias-productos.md
- 42-Especies.md → 42-especies.md
- (y resto 43–54)

### fundamentos/
- 00-Introduccion.md → 00-introduccion.md
- 01-Arquitectura-Multi-Tenant.md → 01-arquitectura-multi-tenant.md
- 02-Autenticacion-Autorizacion.md → 02-autenticacion-autorizacion.md
- 02b-Convencion-Tenant-Jobs.md → 02b-convencion-tenant-jobs.md
- 03-Configuracion-Entorno.md → 03-configuracion-entorno.md

### instrucciones/, ejemplos/, inventario/, pedidos/, etc.
Aplicar mismo criterio: topic en kebab-case lowercase.

### audits/documentation/
Muchos con MAYÚSCULAS — convertir a kebab-case.

---

**Nota:** Se han procesado y renombrado los archivos raíz, _archivo/*, deployment/, y corregido enlaces críticos. El resto puede ejecutarse en batch en una siguiente iteración.
