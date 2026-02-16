#!/bin/bash
# Pasos restantes del plan de reorganización (FASE 5 y comprobaciones)
# Ejecutar desde la raíz del repo: bash docs/audits/documentation/apply-reorganization-remaining.sh

set -e
cd "$(dirname "$0")/../../.."

echo "=== FASE 5: Renombrar archivos numeración duplicada ==="
[ -f docs/20-fundamentos/02-Convencion-Tenant-Jobs.md ] && mv docs/20-fundamentos/02-Convencion-Tenant-Jobs.md docs/20-fundamentos/02b-Convencion-Tenant-Jobs.md && echo "  Renamed 02-Convencion -> 02b"
[ -f docs/23-inventario/31-Palets-Estados-Fijos.md ] && mv docs/23-inventario/31-Palets-Estados-Fijos.md docs/23-inventario/31b-Palets-Estados-Fijos.md && echo "  Renamed 31-Palets-Estados -> 31b"
[ -f docs/28-sistema/82-Roles-Pasos-2-y-3-Pendientes.md ] && mv docs/28-sistema/82-Roles-Pasos-2-y-3-Pendientes.md docs/28-sistema/82b-Roles-Pasos-Pendientes.md && echo "  Renamed 82-Roles-Pasos -> 82b"

echo "=== FASE 5: Mover documentos ==="
[ -f docs/28-sistema/86-Control-Horario-FRONTEND.md ] && mv docs/28-sistema/86-Control-Horario-FRONTEND.md docs/33-frontend/Control-Horario-FRONTEND.md && echo "  Moved 86-Control-Horario-FRONTEND -> 33-frontend"
if [ -d "docs/00_ POR IMPLEMENTAR" ]; then
  [ -f "docs/00_ POR IMPLEMENTAR/README.md" ] && mv "docs/00_ POR IMPLEMENTAR/README.md" docs/por-implementar/00-POR-IMPLEMENTAR-README.md 2>/dev/null || true
fi
if [ -f docs/PROBLEMAS-CRITICOS.md ]; then
  mv docs/PROBLEMAS-CRITICOS.md docs/audits/PROBLEMAS-CRITICOS.md && echo "  Moved PROBLEMAS-CRITICOS -> docs/audits/"
  # Actualizar referencias que apuntaban a docs/PROBLEMAS-CRITICOS.md
  sed -i 's|docs/PROBLEMAS-CRITICOS\.md|docs/audits/PROBLEMAS-CRITICOS.md|g' README.md 2>/dev/null || true
  sed -i 's|../PROBLEMAS-CRITICOS\.md|../audits/PROBLEMAS-CRITICOS.md|g' docs/34-por-hacer/ROADMAP.md docs/34-por-hacer/TECH_DEBT.md 2>/dev/null || true
fi

echo "=== Comprobación: .ai_work_context en .gitignore ==="
grep -q '\.ai_work_context' .gitignore && echo "  OK: .ai_work_context ya está en .gitignore" || echo "  Añadir .ai_work_context/ a .gitignore"

echo "=== Hecho ==="
