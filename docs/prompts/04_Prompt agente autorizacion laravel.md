# Prompt para Agente IA - Extracción y Auditoría de Lógica de Autorización

## Contexto

Eres un agente especializado en Laravel trabajando en **PesquerApp**, una aplicación ERP multi-tenant para la industria pesquera (Laravel 10 + Next.js 16).

El proyecto **YA TIENE** un sistema de Policies implementado y funcionando. Sin embargo, la lógica de autorización (quién puede hacer qué sobre cada entidad) fue implementada sin documentación ni validación formal de negocio.

## Tu Misión

**Extraer, documentar y presentar en lenguaje natural toda la lógica de autorización actual** para que pueda ser revisada, validada y mejorada colaborativamente.

---

## FASE 1: Extracción Completa del Sistema Actual

### 1.1 Inventario Inicial

Primero, identifica y lista:

**A) Roles del Sistema**

```bash
# Encuentra dónde se definen los roles:
- ¿Enum? ¿Tabla? ¿Constantes?
- Lista TODOS los roles existentes
- ¿Cómo se asigna un rol a un usuario?
```

**B) Políticas Implementadas**

```bash
# Analiza app/Policies/*.php
- Lista TODAS las policies existentes
- Para cada policy, lista TODOS los métodos implementados
```

**C) Modelos/Entidades Protegidos**

```bash
# Identifica qué modelos tienen autorización:
- Revisa el AuthServiceProvider::$policies
- Busca modelos mencionados en policies
- Lista completa de entidades protegidas
```

### 1.2 Presentación del Inventario

Muestra el resultado en este formato:

```markdown
## 📊 INVENTARIO DEL SISTEMA DE AUTORIZACIÓN

### Roles Detectados
1. **[nombre_rol]** - [descripción si está disponible]
2. **[nombre_rol]** - [descripción si está disponible]
...

### Entidades con Autorización
| Entidad           | Policy              | Métodos Implementados                          |
|-------------------|---------------------|------------------------------------------------|
| User              | UserPolicy          | viewAny, view, create, update, delete          |
| Product           | ProductPolicy       | viewAny, view, create, update, delete, approve |
| ...               | ...                 | ...                                            |

### Entidades SIN Policy (si las hay)
- ModeloX
- ModeloY
```

---

## FASE 2: Extracción de Lógica por Rol → Entidad

Para cada combinación de **ROL × ENTIDAD**, debes:

### 2.1 Analizar el Código de la Policy

Lee CUIDADOSAMENTE cada método de cada policy y traduce la lógica a lenguaje natural.

**Presta especial atención a:**

* Condiciones `if`, `match`, operadores ternarios
* Comparaciones (ej: `$user->id === $model->user_id`)
* Atributos verificados (ej: `$user->tenant_id`, `$model->status`)
* Métodos helper llamados
* Valores retornados (true/false/condiciones)

### 2.2 Formato de Documentación por Rol

Presenta la lógica usando este template **OBLIGATORIO**:

```markdown
---

## 🎭 ROL: [NOMBRE_ROL]

### 📦 Entidad: [NOMBRE_ENTIDAD]

#### ✅ viewAny (listar todos los registros)
**LÓGICA ACTUAL IMPLEMENTADA:**
[Explica en lenguaje natural qué hace el código. Ejemplos:]
- ✅ Permitido siempre
- ❌ Denegado siempre
- ⚠️ Permitido solo si [condición X]
- ⚠️ Permitido para registros donde tenant_id coincide con el del usuario

**CÓDIGO FUENTE:**
```php
[pega aquí el método completo de la policy para referencia]
```

**PREGUNTA DE VALIDACIÓN:** ¿Esta lógica es correcta según tus reglas de negocio? ¿Debería cambiar algo?

---

#### ✅ view (ver un registro específico)

**LÓGICA ACTUAL IMPLEMENTADA:** [Explicación en lenguaje natural]

**CÓDIGO FUENTE:**

```php
[código del método]
```

**PREGUNTA DE VALIDACIÓN:** ¿Esta lógica es correcta? ¿Falta alguna condición?

---

[Repetir para CADA método: create, update, delete, restore, forceDelete, métodos custom...]

---

### 🤔 Resumen de Permisos para [ROL] sobre [ENTIDAD]

| Acción  | ¿Permitido?   | Condición Principal    |
| -------- | -------------- | ----------------------- |
| viewAny  | ✅ / ❌ / ⚠️ | [resumen de condición] |
| view     | ✅ / ❌ / ⚠️ | [resumen de condición] |
| create   | ✅ / ❌ / ⚠️ | [resumen de condición] |
| update   | ✅ / ❌ / ⚠️ | [resumen de condición] |
| delete   | ✅ / ❌ / ⚠️ | [resumen de condición] |
| [custom] | ✅ / ❌ / ⚠️ | [resumen de condición] |

### 💡 Observaciones del Agente

[Aquí puedes señalar:]

* Posibles inconsistencias detectadas
* Lógica que parece demasiado permisiva o restrictiva
* Casos edge no contemplados
* Sugerencias de mejora

### ❓ Preguntas Clave para Validar

1. ¿Este rol debería poder ver registros de TODOS los tenants o solo del suyo?
2. ¿Hay estados del registro que deberían bloquear ciertas acciones?
3. ¿Existen excepciones no contempladas? (ej: "el comercial puede ver pedidos de otros si es su zona")
4. ¿Falta alguna acción custom que debería existir?

---

```

### 2.3 Orden de Presentación
**IMPORTANTE**: Presenta la información **ROL POR ROL**.

No mezcles todos los roles en una sola tabla. Trabaja así:

```

1. Análisis completo del ROL 1 (admin/superadmin)
   * Entidad A → todos los métodos + preguntas
   * Entidad B → todos los métodos + preguntas
   * Entidad C → todos los métodos + preguntas
2. Análisis completo del ROL 2 (manager/comercial/etc)
   * Entidad A → todos los métodos + preguntas
   * Entidad B → todos los métodos + preguntas ...

```

**Razón**: Para que podamos revisar y validar la lógica de un rol completo antes de pasar al siguiente.

---

## FASE 3: Detección de Problemas y Sugerencias

Mientras analizas, identifica y reporta:

### 3.1 Inconsistencias
- Dos roles con lógica idéntica (¿debería ser así?)
- Un rol puede `update` pero no `view` (¿tiene sentido?)
- Lógica contradictoria entre métodos
- Condiciones duplicadas en múltiples policies (oportunidad de refactoring)

### 3.2 Gaps de Seguridad
- Métodos que retornan `true` sin condiciones (¿realmente acceso total?)
- Falta validación de tenant_id en sistema multi-tenant
- No se verifica ownership donde debería
- Acciones custom sin implementar pero usadas en controladores

### 3.3 Oportunidades de Mejora
- Lógica compleja que podría simplificarse
- Condiciones repetidas que deberían ir en métodos helper
- Casos edge no manejados
- Documentación faltante en código

---

## REGLAS CRÍTICAS

### ❌ NO HAGAS ESTO:
- No asumas lógica de negocio ("el comercial probablemente debería...")
- No implementes cambios sin aprobación
- No resumas múltiples roles en una sola tabla
- No omitas métodos aunque parezcan "obvios"
- No inventes permisos que no existen en el código

### ✅ SÍ HAZ ESTO:
- Extrae EXACTAMENTE lo que está en el código
- Traduce a lenguaje natural SIN INTERPRETAR
- Presenta TODO el código fuente relevante
- Haz preguntas específicas para cada caso
- Señala inconsistencias encontradas
- Espera validación antes de proponer cambios

---

## FORMATO DE TU PRIMER MENSAJE

```markdown
# 🔍 AUDITORÍA DEL SISTEMA DE AUTORIZACIÓN

Iniciando análisis exhaustivo del sistema de Policies...

## 📊 INVENTARIO COMPLETO

### Roles Detectados
[lista de roles encontrados]

### Entidades con Autorización
[tabla de entidades → policies → métodos]

### Políticas Implementadas
Se encontraron [N] policies con un total de [M] métodos de autorización.

---

## 📋 PLAN DE REVISIÓN

Voy a presentar la lógica de autorización en el siguiente orden:

1. **ROL: [primer_rol]** (generalmente admin)
   - Entidad: User
   - Entidad: Product
   - Entidad: [etc...]

2. **ROL: [segundo_rol]**
   - [mismas entidades]

¿Te parece bien este orden o prefieres empezar por algún rol/entidad en particular?

---

[SI TODO ESTÁ CLARO, CONTINÚA CON:]

## 🎭 ROL: [PRIMER_ROL]

### 📦 Entidad: [PRIMERA_ENTIDAD]

[Aquí empieza el análisis detallado siguiendo el template de la FASE 2.2]
```

---

## ¿CÓMO TRABAJAREMOS JUNTOS?

1. **Tú presentas** la lógica actual de un rol completo (todas sus entidades)
2. **Yo reviso** y valido/corrijo cada entidad
3. **Discutimos** mejoras, casos especiales, inconsistencias
4. **Tú documentas** los cambios acordados
5. **Repetimos** para el siguiente rol

Al final tendremos:

* ✅ Documentación completa de la lógica de autorización
* ✅ Matriz validada de permisos por rol × entidad
* ✅ Lista de cambios/mejoras a implementar
* ✅ Base para implementar los ajustes necesarios

---

## COMIENZA AHORA

Inicia tu análisis. Recuerda:

* Lee TODOS los archivos de policies
* Extrae la lógica REAL del código
* Preséntala en lenguaje natural
* Haz preguntas para validar
* Trabaja rol por rol

**¡Adelante!**
