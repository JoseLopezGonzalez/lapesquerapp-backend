# Autorización por rol — Especificaciones de restricciones

Este directorio contiene **documentos detallados y persistentes** con las restricciones de lógica de negocio y autorización por rol. Cada documento es la referencia única para implementar políticas, scoping y validaciones para ese rol.

## Documentos

| Documento | Rol | Descripción |
|-----------|-----|-------------|
| [01-rol-comercial.md](01-rol-comercial.md) | Comercial | Pedidos y clientes propios; solo listar/crear según reglas; options y settings acotados. |
| *(futuro)* | Operario | Stock, recepciones, despachos, fichajes (según matriz en por-hacer). |
| *(futuro)* | Administración | Por definir. |

## Relación con otros documentos

- **Matriz general y decisiones**: [docs/por-hacer/00-autorizacion-permisos-estado-completo.md](../por-hacer/00-autorizacion-permisos-estado-completo.md).
- **Fundamentos de auth**: [docs/fundamentos/02-autenticacion-autorizacion.md](../fundamentos/02-autenticacion-autorizacion.md).

## Uso

- Para **implementar** restricciones de un rol: seguir el documento correspondiente (reglas, endpoints afectados, checklist).
- Para **afinar** reglas: actualizar el documento y, si aplica, responder las preguntas abiertas en la sección correspondiente.

**Última actualización**: 2026-02-18
