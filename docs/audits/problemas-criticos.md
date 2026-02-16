# Problemas Críticos Pendientes - Resumen Ejecutivo

## ⚠️ Estado de la API

- **v1**: Eliminada (2025-01-27) - Ya no existe en el código base
- **v2**: Versión activa (este documento) - Única versión disponible

---

## 📋 Visión General

Este documento resume los **problemas más críticos pendientes** identificados en el código del sistema v2, organizados por prioridad. Para información detallada de todos los problemas, consultar [`referencia/98-Errores-Comunes.md`](referencia/98-Errores-Comunes.md).

**Nota Importante**: Este documento **NO propone soluciones**, solo documenta los problemas tal como están en el código actual.

---


---

## 📝 Resumen de Problemas Pendientes

### Ninguno pendiente

✅ **Todos los problemas críticos han sido resueltos**

---

## 📚 Referencias

Para información detallada de cada problema:

- **Documentación completa**: [`referencia/98-Errores-Comunes.md`](referencia/98-Errores-Comunes.md) - 59 problemas documentados
- **Documentación por módulo**: Cada archivo tiene sección "Observaciones Críticas y Mejoras Recomendadas"

---

**Última actualización**: 2026-01-16
**Total de problemas identificados**: 59 (ver `referencia/98-Errores-Comunes.md`)
**Problemas críticos pendientes en este resumen**: 0

---

## ✅ Problemas Resueltos

### 15. Rutas Hardcoded de Chromium ✅ RESUELTO (2026-01-16)

**Solución implementada**: Se creó una configuración centralizada en `config/pdf.php` que permite configurar la ruta de Chromium mediante variable de entorno `CHROMIUM_PATH`. Se creó el trait `HandlesChromiumConfig` para reutilizar la configuración en controllers, y métodos privados en services y mailables.

**Archivos creados/modificados**: 
- Creado: `config/pdf.php` - Configuración centralizada de Chromium
- Creado: `app/Http/Controllers/v2/Traits/HandlesChromiumConfig.php` - Trait para controllers
- Actualizado: `app/Http/Controllers/v2/PDFController.php` - Usa trait para configuración
- Actualizado: `app/Services/OrderPDFService.php` - Método privado para configuración
- Actualizado: `app/Http/Controllers/v2/SupplierLiquidationController.php` - Usa trait
- Actualizado: `app/Mail/OrderShipped.php` - Método privado para configuración
- Actualizado: `app/Mail/TransportShipmentDetails.php` - Método privado para configuración

**Beneficios**:
- ✅ Configuración centralizada en un solo lugar
- ✅ Permite usar variable de entorno `CHROMIUM_PATH` para diferentes entornos
- ✅ Elimina código duplicado
- ✅ Fácil mantenimiento y extensión

---

### 23. Relación Product-Article No Obvia ✅ RESUELTO (2026-01-16)

**Solución implementada**: Se eliminó la entidad `Article` y se consolidó todo en `Product`. El campo `name` ahora es un campo directo en la tabla `products`.

**Archivos modificados**: 
- Eliminado: `app/Models/Article.php`, `app/Models/ArticleCategory.php`
- Actualizado: `app/Models/Product.php`, `app/Http/Controllers/v2/ProductController.php`, y múltiples exports
- Ver: `docs/referencia/PLAN-ELIMINACION-ARTICLE.md` para detalles completos
