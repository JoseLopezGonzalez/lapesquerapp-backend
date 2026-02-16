# 📋 Laravel Backend Documentation Audit & Refactor Prompt

## 🎯 Objetivo General

Realizar un análisis exhaustivo de toda la documentación (archivos `.md`, `.txt`, `.rst`, etc.) en el proyecto Laravel backend, identificar documentos obsoletos/deprecados, validar estructura, reorganizar según Laravel best practices, y generar un repositorio de documentación profesional y mantenible.

---

## 📊 FASE 1: DESCUBRIMIENTO Y ANÁLISIS

### 1.1 Escaneo Completo de Documentación

Realiza un escaneo recursivo del proyecto para encontrar **todos** los archivos de documentación:

```bash
# Busca estos tipos de archivos:
- *.md (Markdown)
- *.txt (Texto plano)
- *.rst (ReStructuredText)
- *.adoc (AsciiDoc)
- README.* (en cualquier directorio)
- CONTRIBUTING.*
- CHANGELOG.*
- TODO.*
```

**Información a extraer de cada archivo encontrado:**

* Ruta completa relativa a la raíz del proyecto
* Nombre del archivo
* Tamaño (bytes)
* Fecha de última modificación (si está disponible en metadatos)
* Primeras 100 caracteres de contenido
* Cantidad de líneas
* Lenguaje detectado (MD, TXT, RST, etc.)

### 1.2 Análisis de Contenido

Para **cada documento encontrado**, realiza el siguiente análisis:

#### A. Detección de Estado de Actualización

Busca indicadores que sugieran si el documento está:

* ✅ **Actualizado**: Hace referencia a Laravel 10+, patrones modernos, fechas recientes
* ⚠️ **Parcialmente Desactualizado**: Mezclado entre versiones, algún contenido antiguo
* ❌ **Deprecado**: Referencias explícitas a Laravel <10, patrones obsoletos
* 🤔 **Ambiguo**: Difícil determinar estado de actualización

### 1.3 Análisis de Relevancia Comercial

Para PesquerApp (ERP pesquero), determina si el documento es:

* 🎯 **Crítico**: Esencial para el funcionamiento del ERP
* 📌 **Importante**: Documentación de arquitectura o procesos clave
* 📚 **Referencial**: Guías y mejores prácticas
* 🗑️ **Innecesario**: Duplicado o genérico

---

## 📐 FASE 2: ESTRUCTURA IDEAL SEGÚN LARAVEL BEST PRACTICES

```
proyecto-root/
├── docs/
│   ├── README.md
│   ├── MANIFEST.md
│   ├── getting-started/
│   ├── architecture/
│   ├── modules/
│   │   ├── sales/
│   │   ├── stock/
│   │   ├── suppliers/
│   │   ├── labels/
│   │   └── catalogs/
│   ├── api/
│   ├── database/
│   ├── development/
│   ├── deployment/
│   ├── troubleshooting/
│   └── migration/
```

---

## 🔍 FASE 3: EVALUACIÓN DETALLADA DE CADA DOCUMENTO

Para cada archivo, genera un reporte individual con:

* Estado de actualización
* Relevancia para PesquerApp
* Detección de duplicación
* Validación de referencias internas
* Recomendación final (actualizar, eliminar, reubicar, consolidar)

---

## 📋 FASE 4: VALIDACIÓN DE ESTRUCTURA DE DIRECTORIOS

Analiza estructura actual vs. ideal:

* Problemas detectados
* Cumplimiento con Laravel best practices
* Documentos en ubicaciones incorrectas
* Plan de reorganización

---

## 📄 FASE 5: CREACIÓN DE ÍNDICES Y MANIFESTS

### 5.1 MANIFEST.md

Inventario central con:

* Total de documentos
* Estado de actualización
* Índice por categoría
* Mapa de interdependencias

### 5.2 README.md maestro

Índice navegable en docs/

---

## 🎯 FASE 6: VALIDACIONES FINALES

Checklist de calidad:

* [ ] Todos los archivos tienen encabezado H1
* [ ] Sección de última actualización
* [ ] No hay referencias rotas
* [ ] Nomenclatura consistente
* [ ] Estructura sigue recomendaciones
* [ ] MANIFEST actualizado
* [ ] Ejemplos usan Laravel 10+ conventions

---

## 📋 SALIDA ESPERADA

Proporciona los siguientes archivos (todos en Markdown descargable):

1. **AUDIT\_REPORT.md**
   * Resumen ejecutivo
   * Estadísticas globales
   * Ficha detallada de cada documento
   * Recomendaciones por documento
2. **MANIFEST.md**
   * Listado de todos los documentos
   * Estado de cada uno
   * Interdependencias
   * Estadísticas
3. **REORGANIZATION\_PLAN.md**
   * Cambios de ubicación archivo por archivo
   * Consolidaciones necesarias
   * Eliminaciones recomendadas
   * Nuevos documentos a crear
4. **STRUCTURE\_DIAGRAM.md**
   * Diagrama visual de la nueva estructura
5. **VALIDATION\_CHECKLIST.md**
   * Checklist post-reorganización

---

## ⚙️ INSTRUCCIONES PARA CLAUDE CODE

### Cuando ejecutes este análisis:

1. **Lee PRIMERO el proyecto**:
   ```bash
   ls -la
   find . -name "*.md" -o -name "*.txt" -o -name "*.rst" 2>/dev/null
   ```
2. **Mapea la estructura actual**:
   ```bash
   tree docs/ -L 3 2>/dev/null || find docs/ -type f | sort
   ```
3. **Obtén metadatos**:
   ```bash
   find docs/ -type f -name "*.md" -exec sh -c 'echo "=== {} ===" && wc -l "{}"' \;
   ```
4. **Busca documentación dispersa**:
   ```bash
   find . -maxdepth 3 -name "*.md" | grep -v node_modules | grep -v vendor
   ```
5. **Analiza contenido crítico**:
   * Lee archivos importantes completamente
   * Busca versiones de Laravel, patrones, referencias
   * Documenta hallazgos específicos
6. **Genera reportes Markdown**:
   * Todos descargables como `.md`
   * Incluir tablas, listas, enlaces internos
   * Ser específico, no genérico

---

## 📌 NOTAS IMPORTANTES

* Este análisis es **NON-BREAKING**: No modificar archivos, solo analizar
* Enfoque en **profesionalismo**: Reportes claros, estructurados
* Considerar contexto de **PesquerApp**: Sales, Stock, Suppliers, Labels, Catalogs
* Pensar en **escalabilidad**: Estructura debe crecer con el proyecto
* Documentación debe reflejar **estado ACTUAL**: Laravel 10+, CORE v1.0

---

**Versión**: 1.0
**Proyecto**: PesquerApp - ERP Pesquero
**Stack**: Laravel 10, Database-per-tenant, Docker/Coolify, IONOS VPS
**Fecha**: Febrero 2026
