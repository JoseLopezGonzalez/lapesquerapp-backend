# Postmortems

Carpeta para informes de incidentes (postmortems) del backend PesquerApp.

**Última actualización:** 2026-02-16

---

## Plantilla para un postmortem

Crear un archivo con nombre descriptivo y fecha, p. ej. `YYYY-MM-DD-descripcion-breve.md`.

```markdown
# Postmortem — [Título breve del incidente]

**Fecha del incidente:** YYYY-MM-DD
**Fecha del informe:** YYYY-MM-DD
**Autor:** nombre o equipo

## Resumen

Una o dos frases: qué falló y cuál fue el efecto visible.

## Impacto

- **Usuarios/tenants afectados:** ...
- **Duración:** desde HH:MM hasta HH:MM (UTC)
- **Servicios afectados:** API, frontend, colas, etc.

## Cronología

- HH:MM — ...
- HH:MM — ...

## Causa raíz

Explicación técnica de por qué ocurrió (configuración, código, dependencia, operación).

## Acciones correctivas

- [ ] Inmediatas (ya realizadas): ...
- [ ] Corto plazo: ...
- [ ] Largo plazo: ...

## Lecciones aprendidas

Qué cambiar en procesos, alertas o documentación para evitar o detectar antes.
```

## Véase también

- [../deployment/11e-RUNBOOK.md](../deployment/11e-RUNBOOK.md) — Runbook y respuesta a incidentes.
- [../deployment/11d-ROLLBACK-PROCEDURES.md](../deployment/11d-ROLLBACK-PROCEDURES.md) — Rollback.
