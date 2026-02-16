# 🚀 Quick Start - Ejecutar Claude Code Workflow

## ✅ Pre-requisitos (Ya completados)

- ✅ Claude Code instalado
- ✅ Autenticado
- ✅ CLAUDE.md creado
- ✅ Todos los prompts en `docs/`

## 📋 PASO 1: Verificación Previa (2 minutos)
```bash
cd /home/jose/lapesquerapp-backend
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=-1 2>&1 | head -20
```

Si todo pasa, continúa.

## 🎯 PASO 2: Ejecutar el Workflow (4-6 horas)
```bash
cd /home/jose/lapesquerapp-backend

npx @anthropic-ai/claude-code \
  --effort high \
  "LEE CLAUDE.md COMPLETAMENTE

LUEGO LEE docs/prompts/01_Laravel incremental evolution prompt.md COMPLETAMENTE

EJECUTA AUTOMATICAMENTE este workflow:

BLOQUES A EVOLUCIONAR (EN ESTE ORDEN):
1. A.2 Ventas (de 8.5/10 a 9/10)
2. A.3 Stock (de 8/10 a 9/10)
3. A.10 Etiquetas (de 8/10 a 9/10)
4. A.8 Catálogos (de 0 a 9/10)
5. A.9 Proveedores (de 0 a 9/10)

PARA CADA BLOQUE, SIGUE EXACTAMENTE estos 7 pasos:
- STEP 0a: Scope & Entity Mapping
- STEP 0: Document Business Behavior
- STEP 1: Analysis con Rating ANTES
- STEP 2: Proposed Changes
- STEP 3: Implementation
- STEP 4: Validation + Rating DESPUÉS
- STEP 5: Log en docs/audits/laravel-evolution-log.md

IMPORTANTE:
- NO pidas confirmación, ejecuta automáticamente
- Cada cambio = 1 commit
- Ejecuta php artisan test después de cada cambio
- NO cambiar de bloque hasta tener 9/10"
```

## 📊 PASO 3: Monitorear (mientras se ejecuta)

En otra terminal:
```bash
cd /home/jose/lapesquerapp-backend
watch -n 5 'git log --oneline -15'
```

## ✅ PASO 4: Validación (después)
```bash
php artisan test
./vendor/bin/pint
git log --oneline --since="6 hours ago" | wc -l
tail -50 docs/audits/laravel-evolution-log.md
```
