# Prompt: Análisis backend — controlador y endpoints de cebo alineados con recepciones (solo líneas)

## Objetivo

Analizar la implementación actual del **controlador y los endpoints de salidas de cebo** en el backend y proponer o aplicar cambios para que queden **alineados con el controlador y endpoints de recepciones de materia prima**, con la restricción de que las salidas de cebo **solo se crean y gestionan por líneas** (no por palets).

Es decir:

- Misma estructura de rutas, respuestas y códigos HTTP que en recepciones.
- Mismo contrato de creación (body) que en recepciones cuando se usa **solo el modo líneas** (sin palets).
- Sin soporte de creación/edición por palets en cebo.

---

## Referencias en el backend

1. **Controlador de recepciones de materia prima**  
   - Identificar el controlador (y si existe, el recurso API) de `raw-material-receptions` o equivalente.
   - Revisar:
     - Listado (filtros, paginación, relaciones).
     - Obtención por ID.
     - **Create**: body con `supplier`, `date`, `notes`, `details` (array de líneas: product, netWeight, boxes, etc.). Si existe create por palets, ignorar esa rama para cebo.
     - Update (por ID).
     - Delete (por ID y/o múltiple).
   - Anotar nombres de métodos, rutas, formato de request/response y validaciones.

2. **Controlador actual de cebo**  
   - Localizar el controlador (y rutas) de `cebo-dispatches` o equivalente.
   - Documentar:
     - Rutas existentes (index, show, store, update, destroy, etc.).
     - Estructura del body en create/update (qué campos se esperan, si hay `details` y/o `pallets`).
     - Validaciones actuales (request).
     - Formato de respuesta (JSON: data, meta, etc.).
     - Uso de relaciones (supplier, details, products, etc.).

3. **Modelos y DTOs**  
   - Comparar modelo (o entidad) de recepción con el de cebo (campos, relaciones).
   - Comprobar si existen DTOs o Form Requests de recepciones y replicar estructura para cebo (solo líneas).

---

## Tareas de análisis (checklist)

- [ ] **Rutas**  
  - Listar rutas de recepciones (ej. `GET/POST /api/.../raw-material-receptions`, `GET/PUT/DELETE .../:id`).  
  - Listar rutas actuales de cebo.  
  - Indicar qué rutas de cebo faltan o difieren respecto a recepciones.

- [ ] **Create (store)**  
  - Body de recepciones en modo líneas: `supplier` (id), `date`, `notes`, `details` (array de `product.id`, `netWeight`, `boxes`, opcionalmente `tare`, `lot`, `price` según negocio).  
  - Body actual de cebo en create: documentar campos.  
  - Ajustar/definir el body de cebo para que sea **igual** al de recepciones en modo líneas (mismos nombres y estructura), salvo campos específicos de cebo si los hubiera (ej. tipo de salida).  
  - No incluir en el body de cebo: `pallets`, `creationMode` (o equivalente) ni lógica de palets.

- [ ] **Validación**  
  - Reglas de validación del request de create en recepciones (requeridos, numéricos, existencia de supplier/product, etc.).  
  - Replicar en cebo las mismas reglas para `supplier`, `date`, `notes`, `details` y cada elemento de `details`.  
  - Asegurar que no se acepte `pallets` ni modo palets en cebo.

- [ ] **Respuestas**  
  - Formato de respuesta de create/update/get en recepciones (ej. `{ data: { id, supplier, date, notes, details, ... } }`).  
  - Alinear respuestas de cebo con ese formato (misma estructura anidada, mismos nombres de clave).

- [ ] **Update y Delete**  
  - Comportamiento de update en recepciones (por ID, body parcial o completo, solo líneas o también palets).  
  - Definir update de cebo **solo por líneas** (mismo contrato que recepciones en modo líneas).  
  - Delete simple y delete múltiple: mismo comportamiento que recepciones si aplica.

- [ ] **Listado y show**  
  - Filtros y paginación en list de recepciones.  
  - Incluir en cebo los mismos filtros útiles (fechas, proveedor, etc.) y misma paginación.  
  - Show: mismo nivel de detalle (supplier, details con producto, pesos, etc.) sin palets.

- [ ] **Relaciones y recursos anidados**  
  - Qué relaciones carga el backend en recepciones (supplier, details.product, etc.).  
  - Cargar en cebo las mismas relaciones necesarias para el listado y el detalle (solo líneas).

- [ ] **Documentación**  
  - Si existe documentación (OpenAPI, Postman, etc.) de recepciones, actualizar o crear la de cebo para que refleje el contrato "solo líneas" e idéntico a recepciones en ese modo.

---

## Restricción única: solo líneas

En todo el análisis e implementación:

- **No** aceptar en create/update de cebo: `pallets`, `creationMode` (o equivalente a "palets"), ni ningún body que defina palets.
- **Sí** aceptar y validar: `supplier`, `date`, `notes`, `details` (array de líneas con product, netWeight, boxes, y los campos extra que comparta con recepciones por líneas).
- El controlador de cebo debe poder reutilizar la misma lógica de validación y persistencia de **líneas** que use el controlador de recepciones (o un servicio compartido), sin ramas de palets.

---

## Entregables

1. **Documento de análisis** (en repo o en docs del backend) que incluya:
   - Descripción del controlador y rutas actuales de cebo.
   - Comparativa con recepciones (tabla o lista de diferencias).
   - Lista de cambios a aplicar (rutas, body, validación, respuestas).

2. **Cambios en código** (o PR con ellos):
   - Ajuste del controlador de cebo para que el create (y update si aplica) use el mismo contrato que recepciones en modo líneas.
   - Validación explícita: rechazar `pallets` y cualquier modo palets.
   - Respuestas alineadas con recepciones (estructura JSON).

3. **Especificación del contrato API** para el frontend:
   - Ejemplo de body `POST .../cebo-dispatches`: `{ supplier: { id }, date, notes, details: [ { product: { id }, netWeight, boxes } ] }`.
   - Ejemplo de respuesta de create/show/list para que el frontend pueda consumirlos igual que en recepciones (solo líneas).

Con esto, el frontend podrá usar el mismo patrón que en recepciones (operario y admin solo líneas) y el backend de cebo quedará alineado con recepciones y restringido a solo líneas.
