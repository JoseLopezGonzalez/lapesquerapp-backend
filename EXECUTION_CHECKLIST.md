# ✅ Execution Checklist

## PRE-EJECUCIÓN

- [ ] Estás en `/home/jose/lapesquerapp-backend`
- [ ] `npx @anthropic-ai/claude-code --version` funciona
- [ ] `npx @anthropic-ai/claude-code auth status` → autenticado
- [ ] `php artisan test` → ✅ PASS
- [ ] `./vendor/bin/pint --test` → ✅ OK
- [ ] `./vendor/bin/phpstan analyse` → ✅ OK
- [ ] CLAUDE.md existe
- [ ] QUICK_START.md existe
- [ ] `docs/prompts/01_Laravel incremental evolution prompt.md` existe
- [ ] `docs/audits/laravel-backend-global-audit.md` existe
- [ ] `docs/audits/laravel-evolution-log.md` existe

## DURANTE EJECUCIÓN

- [ ] Claude Code está procesando
- [ ] A.2 Ventas: completado (9/10)
- [ ] A.3 Stock: completado (9/10)
- [ ] A.10 Etiquetas: completado (9/10)
- [ ] A.8 Catálogos: completado (9/10)
- [ ] A.9 Proveedores: completado (9/10)

## POST-EJECUCIÓN

- [ ] `php artisan test` → 100% PASS
- [ ] `./vendor/bin/pint` → OK
- [ ] Controllers < 200 líneas
- [ ] Policies existen
- [ ] Form Requests creados
- [ ] Tests creados
- [ ] Evolution log actualizado
- [ ] Mínimo 25 commits

---

**Success = Todo ✅**
