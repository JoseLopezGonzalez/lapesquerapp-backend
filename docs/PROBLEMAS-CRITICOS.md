# Problemas Críticos Pendientes - Resumen Ejecutivo

## ⚠️ Estado de la API

- **v1**: Eliminada (2025-01-27) - Ya no existe en el código base
- **v2**: Versión activa (este documento) - Única versión disponible

---

## 📋 Visión General

Este documento resume los **problemas más críticos pendientes** identificados en el código del sistema v2, organizados por prioridad. Para información detallada de todos los problemas, consultar [`referencia/98-Errores-Comunes.md`](referencia/98-Errores-Comunes.md).

**Nota Importante**: Este documento **NO propone soluciones**, solo documenta los problemas tal como están en el código actual.

---


## 🔧 ALTO - Configuración y Mantenibilidad

### 15. Rutas Hardcoded en Múltiples Lugares (lo dejamos de momento asi)

**Archivos**:

- `app/Http/Controllers/v2/PDFController.php:30` - Chromium: `/usr/bin/google-chrome`
- `app/Services/OrderPDFService.php:50` - Chromium: `/usr/bin/google-chrome`

**Problema**: Rutas hardcodeadas dificultan despliegue en diferentes entornos.

**Impacto**:

- No funciona en diferentes sistemas operativos
- Dificulta configuración por tenant

---


---

## 📝 Resumen de Problemas Pendientes

### Problemas de Mantenibilidad (🟡)

1. **Rutas hardcoded** - Dificulta despliegue (marcado para mantener de momento)

---

## 📚 Referencias

Para información detallada de cada problema:

- **Documentación completa**: [`referencia/98-Errores-Comunes.md`](referencia/98-Errores-Comunes.md) - 59 problemas documentados
- **Documentación por módulo**: Cada archivo tiene sección "Observaciones Críticas y Mejoras Recomendadas"

---

**Última actualización**: 2026-01-16
**Total de problemas identificados**: 59 (ver `referencia/98-Errores-Comunes.md`)
**Problemas críticos pendientes en este resumen**: 1

---

## ✅ Problemas Resueltos

### 23. Relación Product-Article No Obvia ✅ RESUELTO (2026-01-16)

**Solución implementada**: Se eliminó la entidad `Article` y se consolidó todo en `Product`. El campo `name` ahora es un campo directo en la tabla `products`.

**Archivos modificados**: 
- Eliminado: `app/Models/Article.php`, `app/Models/ArticleCategory.php`
- Actualizado: `app/Models/Product.php`, `app/Http/Controllers/v2/ProductController.php`, y múltiples exports
- Ver: `docs/PLAN-ELIMINACION-ARTICLE.md` para detalles completos
