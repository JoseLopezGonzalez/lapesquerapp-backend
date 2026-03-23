# Matriz de Prioridad por Bloques — Auditoría 2026-03-23

| Bloque | Nota actual | Prioridad | Motivo principal |
|--------|-------------|-----------|------------------|
| A.22 Superadmin SaaS | 8.5/10 | P0 | Bloque estratégico ya activo, con jobs y mucha superficie operativa. |
| A.6 Producción | 9/10 | P1 | Complejidad funcional y técnica más alta del core. |
| A.11 Fichajes | 9/10 | P1 | Hotspot claro de controlador y carga operativa. |
| A.12 Estadísticas e informes | 9/10 | P1 | Riesgo de índices, tiempos y contrato desigual. |
| A.15 Documentos | 9/10 | P1 | Exportes/PDFs y side effects síncronos. |
| A.19 CRM | 8.5/10 | P1 | Necesita cierre fino de estados y side effects comerciales. |
| A.20 Canal operativo / Autoventa | 8.5/10 | P1 | Reglas de acceso y sincronización entre rutas/pedidos. |
| A.21 Usuarios externos | 8.5/10 | P1 | Endurecer onboarding y seguridad actor externo. |
| A.2 Ventas | 9/10 | P2 | Reporting/export y variantes residuales. |
| A.4 Recepciones | 9/10 | P2 | Bulk y estrés de exportación. |
| A.5 Despachos | 9/10 | P2 | Variantes de export y carga. |
| A.7 Productos | 9/10 | P2 | Cobertura complementaria y filtros/opciones. |
| A.9 Proveedores + Liquidaciones | 9/10 | P2 | Casos complejos de liquidación. |
| A.13 Settings | 9/10 | P2 | Mantener estándar y evitar regresiones. |
| A.16 Tenants públicos | 9/10 | P2 | Mantener simple y seguro. |
| A.1, A.3, A.8, A.10, A.14, A.17, A.18 | 9-10/10 | P3 | Mantenimiento y prevención de regresiones. |

## Recomendación de ejecución

1. Arreglar el circuito de tests/operabilidad.
2. Consolidar A.22.
3. Atacar hotspots P1 de controller/performance.
4. Cerrar P2.
5. Mantener P3 como guardrail.
