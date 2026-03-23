# Prompt: Extractor Inteligente de Backups SQL → JSON Reducido

## Objetivo

Extraer **10 registros representativos por tabla** de un backup SQL, manteniendo:

* ✅ Integridad referencial (relaciones FK)
* ✅ Variantes y atributos diferentes en cada registro
* ✅ Trazabilidad de instancias completas (casos de uso end-to-end)
* ✅ Formato JSON limpio y estructurado

---

## Instrucciones Generales

### Fase 1: Análisis de la Estructura

**Entrada:** Archivo SQL del backup
**Acción:**

1. Lee el archivo completo
2. Identifica **TODAS las tablas** (CREATE TABLE)
3. Para cada tabla, mapea:
   * Nombre de tabla
   * Columnas (nombre, tipo, constraints)
   * Claves primarias (PRIMARY KEY)
   * Claves foráneas (FOREIGN KEY) → destino
   * Índices importantes
4. **Crea un diagrama mental** de relaciones: `tabla_a (FK) → tabla_b`

**Entrega antes de continuar:**

```
## Estructura Identificada

### Tabla: [nombre_tabla]
- **PK:** [columnas]
- **FKs:** [relaciones con otras tablas]
- **Atributos clave:** [columnas representativas]
- **Caso de uso:** [qué representa]

[Repetir para cada tabla]

## Árbol de Dependencias
[mostrar relaciones jerárquicas]
```

---

### Fase 2: Identificar Casuísticas y Variantes

**Acción:** Para cada tabla, identifica **qué variantes** existen en los datos:

* Estados/estados (ej: `pending`, `completed`, `cancelled`)
* Tipos o categorías
* Rangos de valores significativos
* Combinaciones de atributos que crean casos distintos

**Entrega:**

```
## Variantes por Tabla

### [tabla]
- Variante 1: [descripción + valores clave]
- Variante 2: [descripción + valores clave]
- Variante 3: [...]
- Total variantes: N

[Repetir para cada tabla]

## Casos de Uso End-to-End
1. [Describir un flujo completo: tabla_a → tabla_b → tabla_c]
2. [Describir otro flujo diferente]
```

---

### Fase 3: Seleccionar 10 Registros Representativos por Tabla

**Regla:** Para cada tabla con N registros totales:

1. **Si N ≤ 10:** Tomar todos
2. **Si N > 10:** Seleccionar estratégicamente:
   * 1 registro por cada variante identificada
   * Completar hasta 10 con registros que "cierren" casos de uso
   * Mantener registros con IDs bajos (generalmente más antiguos/estables)
   * Evitar registros "rotos" (FKs inválidas, datos null inesperados)

**Acción:**

```
Iterar tabla por tabla:

SELECT * FROM [tabla] LIMIT 100;  # Para validar totales
# Analizar qué registros cubren variantes
# Seleccionar los 10 mejores
# Documentar por qué cada uno
```

**Entrega:**

```
## Selección de Registros: [tabla]
**Total en backup:** N registros
**Seleccionados:** 10 registros
**Criterio:** [describir estrategia]

| Variante | ID(s) Seleccionados | Razón |
|----------|-------------------|-------|
| [var1]   | 5, 23, 45         | Cubre caso X, Y, Z |
| [var2]   | 12                | Único ejemplo de... |
```

---

### Fase 4: Validar Integridad Referencial

**Acción:** Antes de exportar, verificar:

1. **Para cada registro seleccionado de [tabla\_B]:**
   * ¿Existe su FK referenciada en [tabla\_A]?
   * Si NO: Incluir ese registro padre en la selección
   * Si el padre fue descartado: Ajustar selección
2. **Crear tabla de "Registros Impactados":**
   ```
   Si incluyo registro_B (id=100) que referencia a padre_A (id=5):
   → Necesito asegurar que padre_A (id=5) está en la selección
   ```
3. **Iterar hasta consistencia:**
   ```
   Mientras haya FKs no satisfechas:
     - Agregar registros padres necesarios
     - Esto puede desplazar otros registros (máx 10 por tabla)
     - Priorizar: casos de uso > variantes > registros antiguos
   ```

**Entrega:**

```
## Validación de Integridad Referencial

### Tabla [tabla_B] → Tabla [tabla_A]
- FK: [columna_fk]
- Registros seleccionados de [tabla_B]: [lista IDs]
- Registros requeridos de [tabla_A]: [lista IDs]
- ✅ Todos los padres están incluidos / ⚠️ Ajustes realizados

[Repetir para cada relación FK]

## Resumen de Ajustes
[Listar cambios hechos para mantener integridad]
```

---

### Fase 5: Exportar a JSON

**Formato:** Objeto con una clave por tabla, conteniendo array de registros

```json
{
  "tabla_1": [
    {
      "id": 1,
      "columna_a": "valor",
      "columna_b": 123,
      "created_at": "2024-01-15T10:30:00Z",
      "_variante": "case_a",
      "_razon_seleccion": "Representa el flujo principal de compra"
    },
    {
      "id": 2,
      "columna_a": "valor_diferente",
      "columna_b": 456,
      "created_at": "2024-02-20T14:15:00Z",
      "_variante": "case_b",
      "_razon_seleccion": "Cubre estado pendiente + retraso"
    }
  ],
  "tabla_2": [
    {
      "id": 5,
      "fk_tabla_1": 1,
      "columna_x": "dato",
      "_razon_seleccion": "Hijo directo de tabla_1 id=1"
    }
  ]
}
```

**Notas:**

* Incluir campos `_variante` y `_razon_seleccion` como comentarios contextuales
* Mantener tipos de datos correctos (strings con `""`, números sin comillas, booleanos `true/false`)
* Incluir timestamps reales si existen
* Omitir columnas con valores NULL a menos que sean semánticamente importantes

---

## Checklist Final

Antes de entregar el JSON:

* [ ] **Fase 1:** Estructura identificada y diagramada
* [ ] **Fase 2:** Variantes documentadas por tabla
* [ ] **Fase 3:** 10 registros seleccionados y justificados por tabla
* [ ] **Fase 4:** Integridad referencial validada sin orfandades
* [ ] **Fase 5:** JSON generado con metadata contextual
* [ ] **Bonus:** Archivo `SEEDERS_README.md` con:
  * Descripción de cada tabla
  * Qué casos de uso cubre cada registro
  * Recomendaciones para generar seeders con Cursor
  * Orden de ejecución para FK dependencies

---

## Recomendaciones para Uso con Cursor

Una vez tengas el JSON:

1. **Copia el JSON en un archivo** `backup_reduced.json`
2. **Pasa a Cursor el siguiente prompt:**
   ```
   Tengo este JSON (backup_reduced.json) que contiene 10 registros
   representativos por tabla de mi BD de pescadería.

   Cada registro tiene _variante y _razon_seleccion que explican
   por qué fue seleccionado.

   Genera seeders Laravel completos y realistas que:
   - Creen estos datos en el mismo orden (respetando FKs)
   - Mantengan las variantes y casos de uso documentados
   - Incluyan comentarios explicando cada bloque
   - Sean reutilizables y fáciles de debuguear
   ```
3. **Cursor debería generar** un seeder factory completo con todos los casos cubiertos

---

## Ejemplo de Flujo Completo

```
INPUT: backup.sql (1000 registros en 15 tablas)
  ↓
FASE 1: Mapear estructura
  - Identificadas 15 tablas
  - 23 relaciones FK detectadas
  - Árbol de dependencias creado
  ↓
FASE 2: Variantes
  - Tabla "orders": 3 variantes (pending, processing, completed)
  - Tabla "boxes": 5 variantes (talla pequeña, mediana, grande, x-large + damaged)
  - Total: 47 variantes a cubrir en ~150 registros seleccionados
  ↓
FASE 3: Seleccionar 10 cada tabla
  - orders: IDs 1, 5, 12, 24, 33, 45, 67, 88, 99, 102
  - boxes: IDs 2, 7, 15, 28, 41, 53, 71, 84, 96, 110
  - etc.
  ↓
FASE 4: Validar FKs
  - order id=1 → supplier id=3 ✅
  - box id=2 → product id=7 ✅
  - [sin orfandades]
  ↓
FASE 5: JSON
  {
    "orders": [...],
    "boxes": [...],
    "suppliers": [...],
    ...
  }
  ↓
OUTPUT: backup_reduced.json + SEEDERS_README.md
```

---

## Preguntas que Deberías Hacerte

Si algo se siente incompleto:

1. ¿Cubren los 10 registros TODAS las variantes identificadas?
2. ¿Hay registros huérfanos (FKs que apuntan a nada)?
3. ¿Podría un desarrollador usar estos datos para entender todos los casos de uso?
4. ¿Los timestamps son coherentes (creados antes de modificados)?
5. ¿Las secuencias numéricas tienen sentido?

---

**Listo. Ejecuta este prompt paso a paso con Cursor y tendrás un JSON perfecto para seeders.**
