# Plan: Sistema transversal de adjuntos y archivos

> Contexto: PesquerApp necesita incorporar imagenes y documentos persistentes asociados a entidades de negocio como palets, pedidos y recepciones de materia prima. Este documento define el problema, la solucion propuesta y una hoja de ruta por fases para implementar la base tecnica y despues activar los usos de negocio por modulo.

---

## 1. Problema

Actualmente el backend tiene soporte tecnico para trabajar con archivos mediante Laravel Storage (`local`, `public`, `s3`) y tambien genera documentos PDF/Excel en flujos concretos. Sin embargo, no existe un modelo general para adjuntos persistentes subidos por usuarios.

Esto provoca que, si se quiere anadir una imagen de un palet, una foto de una etiqueta, un CMR firmado, una factura de proveedor o una evidencia de recepcion, la tentacion natural sea anadir campos aislados como `image`, `document_url`, `photo_path` o similares en cada tabla. Ese enfoque parece rapido al principio, pero escala mal.

Problemas de usar campos sueltos:

- Limita a uno o pocos archivos por entidad cuando el negocio probablemente necesitara varios.
- Mezcla la entidad principal con detalles tecnicos del almacenamiento.
- Hace dificil reutilizar validaciones, permisos, descargas, borrado y auditoria.
- No permite clasificar bien el significado operativo de cada archivo.
- Complica el futuro si se cambia de disco local a S3/R2.
- Duplica endpoints y servicios en cada modulo.
- No resuelve bien miniaturas, metadatos, orden, notas, etiquetas o trazabilidad.

El problema real no es solo "subir archivos". El problema real es definir un contrato estable para que los archivos sean parte de la trazabilidad del ERP sin contaminar los modelos principales.

---

## 2. Objetivos

### Objetivo principal

Implementar un sistema transversal de adjuntos, privado por defecto, multi-tenant y reutilizable por cualquier entidad de negocio.

### Objetivos funcionales

- Adjuntar multiples archivos a pedidos, palets y recepciones.
- Clasificar cada adjunto por coleccion o tipo logico.
- Descargar o visualizar archivos solo si el usuario tiene permiso sobre la entidad asociada.
- Listar adjuntos junto a sus metadatos: nombre original, tipo MIME, tamano, usuario que subio, fecha y notas.
- Permitir borrar adjuntos cuando las reglas de negocio lo permitan.
- Preparar el sistema para imagenes, documentos PDF, hojas escaneadas, evidencias fotograficas y archivos administrativos.

### Objetivos tecnicos

- Usar Laravel Storage como abstraccion de almacenamiento.
- Mantener los metadatos en la base de datos del tenant.
- Mantener aislamiento estricto entre tenants.
- Evitar URLs publicas permanentes para documentos sensibles.
- Centralizar validacion, autorizacion, rutas, recursos API y servicios.
- Disenar una base pequena pero extensible.

---

## 3. Estado actual relevante

El proyecto ya tiene:

- Configuracion de filesystem en `config/filesystems.php` con discos `local`, `public` y `s3`.
- Arquitectura multi-tenant database-per-tenant.
- Modelos de negocio con `UsesTenantConnection`.
- API REST v2 bajo middleware `tenant`.
- Servicios para documentos generados de pedidos (`OrderPDFService`, `OrderMailerService`).
- Exportaciones y archivos temporales o generados.

Lo que no existe todavia:

- Tabla general de adjuntos.
- Relacion polimorfica de archivos con entidades.
- Endpoints CRUD de adjuntos.
- Politicas de acceso por adjunto.
- Convencion de rutas fisicas en storage por tenant y entidad.
- Taxonomia de colecciones de adjuntos por modulo.

---

## 4. Decision propuesta

Crear un modelo propio `Attachment` en la base de datos del tenant, relacionado de forma polimorfica con entidades adjuntables.

No se recomienda, en esta primera version, anadir Spatie Media Library. Es una libreria muy solida, especialmente si se necesitan conversiones, miniaturas avanzadas, responsive images y colecciones ya resueltas. Aun asi, para PesquerApp conviene empezar con un modelo propio porque:

- El dominio exige significado operativo especifico por modulo.
- La necesidad inicial es trazabilidad documental, no gestion multimedia compleja.
- El proyecto ya tiene convenciones propias de servicios, policies y API resources.
- Se puede mantener una superficie pequena y auditable.
- Siempre sera posible migrar o integrar una libreria despues si aparecen necesidades avanzadas.

La decision clave es separar:

- El archivo fisico: donde vive en storage.
- El metadato tecnico: path, disk, mime, size.
- El significado de negocio: coleccion, tipo, notas, entidad asociada.
- El permiso de acceso: delegado a la entidad asociada.

---

## 5. Modelo conceptual

Un adjunto pertenece a una entidad de negocio mediante relacion polimorfica:

- Un `Order` puede tener muchos adjuntos.
- Un `Pallet` puede tener muchos adjuntos.
- Una `RawMaterialReception` puede tener muchos adjuntos.
- En el futuro, un `Incident`, `Supplier`, `Customer`, `ProductionRecord` o `CeboDispatch` tambien podria tener adjuntos.

La entidad principal no necesita columnas nuevas. Solo expone una relacion:

```php
public function attachments()
{
    return $this->morphMany(Attachment::class, 'attachable');
}
```

### MorphMap obligatorio

El campo `attachable_type` NO debe almacenar el FQCN completo (`App\Models\Pallet`). Usar un morphMap registrado en un ServiceProvider evita que un renombrado o movimiento de clase rompa registros historicos:

```php
Relation::morphMap([
    'pallet'     => \App\Models\Pallet::class,
    'order'      => \App\Models\Order::class,
    'reception'  => \App\Models\RawMaterialReception::class,
]);
```

Este mapa debe actualizarse cada vez que se incorpore una nueva entidad adjuntable. La clave corta (`'pallet'`) es lo que se guarda en base de datos.

---

## 6. Estructura de datos propuesta

Tabla: `attachments`

```text
id
attachable_type
attachable_id
collection
disk
path
original_name
stored_name
mime_type
extension
size
checksum
uploaded_by_user_id
notes
metadata
created_at
updated_at
deleted_at
```

### Descripcion de campos

| Campo | Proposito |
|---|---|
| `attachable_type` | Clave corta del morphMap (`'pallet'`, `'order'`, `'reception'`, etc.). Nunca el FQCN. |
| `attachable_id` | ID de la entidad asociada dentro del tenant. |
| `collection` | Significado logico del adjunto dentro del modulo. |
| `disk` | Disco de Laravel Storage usado (`local`, `s3`, `attachments`, etc.). |
| `path` | Ruta relativa completa dentro del disco, incluyendo el nombre de archivo generado. Ej: `tenants/demo/pallets/947/damage_photo/01HZ...jpg`. |
| `original_name` | Nombre original del archivo subido por el usuario. Solo para mostrar; nunca se usa para acceder al disco. |
| `stored_name` | Nombre de archivo generado en storage (UUID/ULID + extension). Equivale al ultimo segmento de `path`; se guarda separado para facilitar consultas y limpieza. |
| `mime_type` | Tipo MIME detectado por el servidor mediante `finfo` o equivalente, no el declarado por el cliente. |
| `extension` | Extension normalizada derivada del MIME detectado, no de la extension del nombre original. |
| `size` | Tamano en bytes. |
| `checksum` | SHA-256 calculado por el servidor en el momento de la subida. No es opcional: se guarda siempre para detectar duplicados y verificar integridad. |
| `uploaded_by_user_id` | Usuario tenant que subio el archivo. |
| `notes` | Nota corta visible para usuarios. Editable despues de la subida. |
| `metadata` | JSON para datos especificos por modulo sin migrar columnas nuevas. Editable despues de la subida. |
| `deleted_at` | Borrado logico del metadato. El archivo fisico se elimina de storage en el momento del soft delete; la purga del registro (hard delete) es solo limpieza administrativa. |

### Indices recomendados

```text
index attachable_type, attachable_id
index collection
index uploaded_by_user_id
index created_at
```

En MySQL, no se puede crear una foreign key real sobre una relacion polimorfica. La integridad se mantiene desde servicios, policies y tests.

---

## 7. Almacenamiento fisico

Los archivos deben ser privados por defecto. La aplicacion entrega los archivos mediante endpoints autorizados, no mediante URLs publicas permanentes.

### Disco recomendado

Crear un disco dedicado:

```php
'attachments' => [
    'driver' => env('ATTACHMENTS_DISK_DRIVER', 'local'),
    'root' => storage_path('app/attachments'),
    'throw' => true,
],
```

En produccion podria mapearse a S3/R2 sin cambiar la logica de negocio.

Tambien se puede usar `s3` directamente mediante `ATTACHMENTS_DISK=s3`, pero conviene tener una clave logica propia (`attachments`) para no mezclar exportaciones, temporales y adjuntos.

### Convencion de rutas

```text
tenants/{tenant}/orders/{order_id}/{collection}/{uuid}.{ext}
tenants/{tenant}/pallets/{pallet_id}/{collection}/{uuid}.{ext}
tenants/{tenant}/raw-material-receptions/{reception_id}/{collection}/{uuid}.{ext}
```

Ejemplos:

```text
tenants/demo/orders/128/signed_delivery_note/01HZ...pdf
tenants/demo/pallets/947/damage_photo/01HZ...jpg
tenants/demo/raw-material-receptions/63/supplier_document/01HZ...pdf
```

El identificador `{tenant}` es el slug o subdominio del tenant tal como lo resuelve el middleware (`app('currentTenant')`), nunca un campo enviado por el cliente ni el ID numerico de la fila. Esto garantiza rutas legibles y estables mientras el subdominio no cambie.

---

## 8. Seguridad y autorizacion

Los adjuntos no deben ser accesibles por path directo. La autorizacion debe responder a dos preguntas:

1. Puede el usuario ver o modificar la entidad asociada?
2. Puede el usuario realizar la accion concreta sobre el adjunto?

Ejemplos:

- Si un usuario no puede ver un pedido, no puede listar ni descargar sus adjuntos.
- Si un usuario no puede actualizar un palet, no puede subir o borrar imagenes de ese palet.
- Si una recepcion esta bloqueada por reglas de edicion, tal vez se permita ver adjuntos pero no borrarlos.

### Acciones sugeridas en policy

```text
viewAny
view
create
update
delete
download
```

La `AttachmentPolicy` puede delegar en la policy del modelo asociado:

- `download`: equivalente a `view` del `attachable`.
- `create`: equivalente a `update` o accion especifica del `attachable`.
- `update`: equivalente a `update` del `attachable`; solo permite editar `notes` y `metadata`, nunca el archivo fisico ni la coleccion.
- `delete`: equivalente a `update` o `deleteAttachment` del `attachable`.

`viewAny` aplica al listado paginado de adjuntos de una entidad; si el usuario no puede ver la entidad, no puede ver su listado.

---

## 9. Validacion

La validacion debe estar centralizada en Form Requests.

### Reglas base

```text
file: required|file
collection: required|string
notes: nullable|string|max:500
metadata: nullable|array
```

### Deteccion de MIME server-side

No confiar en el Content-Type declarado por el cliente ni en la extension del nombre original. El `AttachmentService` debe detectar el MIME real con `finfo_file()` o `mime_content_type()` despues de almacenar el archivo temporalmente, y rechazar si no coincide con los tipos permitidos para la coleccion. El campo `mime_type` en la BD siempre refleja el MIME detectado, nunca el declarado.

### Limites iniciales recomendados

| Categoria | MIME permitido | Tamano inicial |
|---|---|---|
| Imagenes | jpg, jpeg, png, webp | 10 MB |
| Documentos | pdf | 20 MB |
| Hojas de calculo | xls, xlsx, csv | 20 MB, solo si se aprueba por modulo |

No aceptar ejecutables, HTML, SVG subido por usuario, scripts ni archivos comprimidos en la primera fase.

### Validacion por modulo

Cada modulo debe declarar que colecciones acepta y que tipos MIME permite por coleccion.

Ejemplo conceptual:

```php
'pallets' => [
    'pallet_photo' => ['image/jpeg', 'image/png', 'image/webp'],
    'label_photo' => ['image/jpeg', 'image/png', 'image/webp'],
    'damage_photo' => ['image/jpeg', 'image/png', 'image/webp'],
],
```

---

## 10. API propuesta

La API debe ser generica en la implementacion interna, pero puede exponerse con rutas especificas por dominio para mantener claridad en frontend y permisos.

### Rutas para palets

```text
GET    /api/v2/pallets/{pallet}/attachments
POST   /api/v2/pallets/{pallet}/attachments
GET    /api/v2/pallets/{pallet}/attachments/{attachment}
PATCH  /api/v2/pallets/{pallet}/attachments/{attachment}
GET    /api/v2/pallets/{pallet}/attachments/{attachment}/download
DELETE /api/v2/pallets/{pallet}/attachments/{attachment}
```

### Rutas para pedidos

```text
GET    /api/v2/orders/{order}/attachments
POST   /api/v2/orders/{order}/attachments
GET    /api/v2/orders/{order}/attachments/{attachment}
PATCH  /api/v2/orders/{order}/attachments/{attachment}
GET    /api/v2/orders/{order}/attachments/{attachment}/download
DELETE /api/v2/orders/{order}/attachments/{attachment}
```

### Rutas para recepciones

```text
GET    /api/v2/raw-material-receptions/{reception}/attachments
POST   /api/v2/raw-material-receptions/{reception}/attachments
GET    /api/v2/raw-material-receptions/{reception}/attachments/{attachment}
PATCH  /api/v2/raw-material-receptions/{reception}/attachments/{attachment}
GET    /api/v2/raw-material-receptions/{reception}/attachments/{attachment}/download
DELETE /api/v2/raw-material-receptions/{reception}/attachments/{attachment}
```

### Paginacion del listado

El endpoint `GET .../attachments` debe devolver resultados paginados (parametro `per_page`, por defecto 20). Filtro opcional por `collection`. Una entidad puede acumular muchos adjuntos a lo largo del tiempo y cargarlos todos en una sola respuesta no escala.

### Respuesta JSON de adjunto

```json
{
  "id": 15,
  "collection": "damage_photo",
  "originalName": "palet-golpeado.jpg",
  "mimeType": "image/jpeg",
  "extension": "jpg",
  "size": 482193,
  "notes": "Golpe visible en esquina superior",
  "metadata": {
    "capturedAt": "2026-06-04T10:30:00+02:00"
  },
  "uploadedBy": {
    "id": 7,
    "name": "Operario"
  },
  "createdAt": "2026-06-04T10:31:18+02:00"
}
```

Para imagenes, mas adelante se puede anadir:

```json
{
  "previewUrl": "/api/v2/pallets/947/attachments/15/preview"
}
```

La URL de preview tambien debe estar protegida.

---

## 11. Servicios backend

### `AttachmentService`

Responsabilidades:

- Validar que la coleccion es aceptada por la entidad.
- Generar nombre seguro y ruta de storage.
- Guardar archivo en el disco configurado.
- Crear registro `Attachment`.
- Borrar archivo fisico y metadato cuando corresponda.
- Devolver streams de descarga.
- Evitar que un adjunto se descargue desde una entidad distinta a la asociada.

Metodos orientativos:

```php
store(Model $attachable, UploadedFile $file, string $collection, ?User $user, array $data = []): Attachment
list(Model $attachable, ?string $collection = null, int $perPage = 20): LengthAwarePaginator
update(Attachment $attachment, array $data): Attachment   // solo notes y metadata
download(Attachment $attachment): StreamedResponse
delete(Attachment $attachment): void  // elimina archivo fisico y hace soft delete del registro
```

El metodo `delete` debe eliminar primero el archivo fisico de storage y despues hacer el soft delete. Si la eliminacion fisica falla, no se hace soft delete (evitar registros huerfanos al reves). El borrado fisico debe ser robusto ante `FileNotFoundException` (el archivo ya no estaba: se loguea y se continua con el soft delete).

### `AttachmentCollectionRegistry`

Responsabilidad:

- Definir colecciones validas por entidad.
- Definir mimes permitidos por coleccion (lista blanca; el servicio valida contra MIME detectado, no el declarado).
- Definir tamanos maximos por coleccion.
- Definir numero maximo de adjuntos por coleccion y por entidad.
- Centralizar nombres y descripciones.

Esto evita que cada controller invente su propia logica.

### `AttachmentPathGenerator`

Responsabilidad:

- Construir rutas tenant-aware.
- Normalizar nombres de entidad.
- Generar UUID/ULID para archivos.

---

## 12. Integracion frontend

La experiencia de frontend debe ser consistente por modulo, pero no necesariamente identica.

### Componente base recomendado

Crear un componente reutilizable tipo `AttachmentPanel` que reciba:

- `entityType`
- `entityId`
- `allowedCollections`
- `readonly`
- callbacks de subida/borrado/descarga

El panel deberia soportar:

- Lista de adjuntos.
- Filtro por coleccion.
- Subida drag-and-drop o selector de archivo.
- Campo de notas.
- Progreso de subida.
- Estados de error de validacion.
- Acciones de descargar y borrar.
- Previsualizacion basica para imagenes.

Cada modulo decide donde mostrarlo:

- Palet: en ficha/detalle operativo del palet.
- Pedido: en tab de documentos o seccion documental.
- Recepcion: en detalle de recepcion, junto a proveedor y palets generados.

---

## 13. Fases de implementacion

## Fase 0 - Decision funcional y taxonomia inicial

### Criterio general

La taxonomia de colecciones se define modulo a modulo, justo antes de implementar cada bloque. No se cierra una taxonomia global por adelantado: la logica de negocio de cada entidad puede diferir y conviene decidirla con contexto concreto.

### Reglas transversales confirmadas

- **Borrado**: cualquier adjunto puede borrarse. El permiso de borrado se restringe a roles de rango alto (admin, tecnico). Los roles operativos (operario, comercial) no pueden borrar. Se aplica soft delete; el archivo fisico se elimina en ese momento.
- **Colecciones nuevas**: cada modulo define sus colecciones antes de implementarse. No se inventan colecciones durante la implementacion tecnica.

### Modulo Pallet — CONFIRMADO (piloto Fase 1)

| Coleccion | Tipo | Descripcion |
|---|---|---|
| `pallet_image` | Evidencia operativa | Imagen general del palet. El usuario adjunta lo que necesite: etiqueta, cajas, desperfecto, estado del producto, escarcha, etc. Sin distincion forzada por categoria. |

Reglas de visibilidad: cualquier rol con acceso al palet puede ver sus imagenes.

Reglas de subida: cualquier rol con permiso de edicion del palet puede subir imagenes.

Reglas de borrado: solo admin y tecnico.

Restriccion por estado: sin restriccion por estado en esta primera version. Se revisa si aparece necesidad operativa.

### Modulo Order — pendiente

La taxonomia de colecciones se define antes de implementar la Fase 3. Candidatos preliminares: `signed_delivery_note`, `cmr_signed`, `customer_document`, `transport_document`, `order_photo`, `incident_evidence`.

### Modulo RawMaterialReception — pendiente

La taxonomia de colecciones se define antes de implementar la Fase 4. Candidatos preliminares: `supplier_document`, `weighing_ticket`, `invoice_or_delivery_note`, `reception_photo`, `quality_control`, `damage_or_discrepancy`.

---

## Fase 1 - Base transversal de adjuntos

Objetivo: construir la infraestructura comun sin activar todavia todos los casos de uso.

Backend:

- Crear migracion tenant `attachments`.
- Crear modelo `Attachment` con `UsesTenantConnection`, casts y soft deletes.
- Registrar `morphMap` en un ServiceProvider con las claves cortas iniciales (`pallet`, `order`, `reception`).
- Crear trait opcional `HasAttachments`.
- Crear `AttachmentService`.
- Crear `AttachmentCollectionRegistry`.
- Crear `AttachmentPathGenerator`.
- Crear `AttachmentResource`.
- Crear `StoreAttachmentRequest` (subida) y `UpdateAttachmentRequest` (edicion de notes/metadata).
- Crear `AttachmentPolicy` con acciones `viewAny`, `view`, `create`, `update`, `delete`, `download`.
- Crear disco dedicado `attachments`.
- Anadir config `config/attachments.php`.
- **Entidad piloto: `Pallet`**. Crear endpoints `pallets/{pallet}/attachments` con los seis verbos (GET listado, POST, GET detalle, PATCH, GET download, DELETE).
- Crear tests feature de subida, listado paginado, detalle, actualizacion de notas, descarga, borrado y denegacion por permisos.

Reglas tecnicas:

- Los archivos se guardan privados.
- Los metadatos viven en la BD tenant.
- No se aceptan paths enviados por cliente.
- El tenant del path se obtiene del contexto actual.
- Si falla la escritura en storage, no se crea registro.
- Si falla la creacion del registro, se limpia el archivo subido.
- El borrado debe ser transaccional en metadato y robusto frente a archivo ya inexistente.

Resultado esperado:

- Base reutilizable y probada.
- Sin acoplamiento a pedidos/palets/recepciones mas alla de la entidad piloto.

---

## Fase 2 - Imagenes para palets

> Esta fase queda absorbida por la Fase 1 al ser Pallet la entidad piloto. La coleccion `pallet_image` ya se implementa y prueba en Fase 1.

Objetivo: galeria de imagenes asociadas al palet, gestionada por el usuario sin distincion forzada de categoria.

Coleccion confirmada:

| Coleccion | MIME permitidos | Tamano max |
|---|---|---|
| `pallet_image` | `image/jpeg`, `image/png`, `image/webp` | 10 MB |

Implementacion:

- La infraestructura de Pallet (rutas, endpoints, trait, policy) se construye en Fase 1.
- Registrar `pallet_image` en `config/attachments.php`.
- Sin restriccion por estado en esta version.
- Borrado: solo admin y tecnico.
- Tests: subida valida, subida con MIME invalido (pdf, exe), listado, descarga, borrado con y sin permiso.

Resultado esperado:

- Galeria de imagenes del palet accesible desde la ficha.
- Trazabilidad visual basica operativa.

---

## Fase 3 - Documentacion e imagenes para pedidos

Objetivo: permitir adjuntar documentos recibidos o subidos manualmente a pedidos, sin confundirlos con PDFs generados automaticamente por el sistema.

Colecciones candidatas:

| Coleccion | Significado pendiente de validar |
|---|---|
| `customer_document` | Documento enviado por cliente. |
| `transport_document` | Documento relacionado con transporte. |
| `signed_delivery_note` | Albaran/nota de carga firmada. |
| `cmr_signed` | CMR firmado o escaneado. |
| `invoice_support` | Soporte administrativo o factura relacionada. |
| `order_photo` | Imagen relacionada con preparacion/carga del pedido. |
| `incident_evidence` | Evidencia documental o fotografica vinculada a incidencia. |
| `general` | Adjuntos no clasificados, a minimizar. |

Decisiones funcionales pendientes:

- Diferenciar claramente documento generado vs documento adjunto.
- Definir que adjuntos se envian por email y cuales son solo internos.
- Definir si un CMR firmado debe marcar algun estado documental del pedido.
- Definir visibilidad por rol: comercial, administracion, direccion, operario.
- Definir si pedidos `finished` permiten adjuntos posteriores.
- Definir retencion minima de documentos sensibles.

Implementacion:

- Anadir `attachments()` a `Order`.
- Registrar colecciones de pedidos.
- Crear rutas `orders/{order}/attachments`.
- Integrar listado en la vista documental del pedido.
- Opcional: permitir que `OrderMailerService` adjunte archivos persistentes seleccionados, no solo PDFs generados.
- Tests de permisos y pedidos cerrados.

Resultado esperado:

- Expediente documental del pedido.
- Mejor trazabilidad post-entrega.
- Preparacion para documentos firmados y evidencias de transporte.

---

## Fase 4 - Imagenes y documentacion para recepciones

Objetivo: permitir adjuntar documentacion y evidencias a recepciones de materia prima.

Colecciones candidatas:

| Coleccion | Significado pendiente de validar |
|---|---|
| `supplier_document` | Documento aportado por proveedor. |
| `weighing_ticket` | Ticket de bascula/pesaje. |
| `invoice_or_delivery_note` | Factura, albaran o documento de entrega del proveedor. |
| `reception_photo` | Foto general de la recepcion. |
| `pallet_photo` | Imagen de palets generados desde la recepcion. |
| `quality_control` | Evidencia de control de calidad. |
| `damage_or_discrepancy` | Evidencia de diferencia de peso, dano, rechazo parcial, etc. |

Decisiones funcionales pendientes:

- Si algunas imagenes pertenecen a la recepcion o al palet generado.
- Si un documento de proveedor debe alimentar liquidaciones.
- Si un ticket de pesaje debe bloquear o justificar pesos declarados.
- Si las recepciones editables permiten borrar adjuntos y las cerradas no.
- Si adjuntos de recepcion deben aparecer tambien en la ficha del proveedor.

Implementacion:

- Anadir `attachments()` a `RawMaterialReception`.
- Registrar colecciones de recepciones.
- Crear rutas `raw-material-receptions/{reception}/attachments`.
- Integrar adjuntos en detalle de recepcion.
- Revisar interaccion con palets creados automaticamente.
- Tests sobre recepciones editables/no editables y permisos.

Resultado esperado:

- Expediente documental de recepcion.
- Evidencias de peso, calidad y proveedor.
- Mejor trazabilidad desde entrada de materia prima hasta stock.

---

## Fase 5 - Mejoras posteriores

Estas mejoras no deberian bloquear la primera version:

- Miniaturas de imagenes.
- Compresion/optimizacion de imagenes en cola.
- Previews protegidos para PDF.
- Escaneo antivirus.
- Deteccion de duplicados por checksum.
- Versionado de documentos.
- Bloqueo legal/retencion de adjuntos sensibles.
- Firma o verificacion documental.
- Subida directa a S3 mediante URLs temporales.
- Adjuntos en incidencias, proveedores, clientes y produccion.
- Busqueda global de adjuntos por nombre, coleccion o entidad.

---

## 14. Riesgos y mitigaciones

| Riesgo | Mitigacion |
|---|---|
| Mezclar documentos privados con storage publico | Usar disco privado y endpoints autorizados. |
| Duplicar logica por modulo | Centralizar `AttachmentService`, requests, resources y registry. |
| Ambiguedad funcional de colecciones | Cerrar taxonomia por fase antes de implementar UI. |
| Fugas entre tenants | Rutas con tenant contextual, BD tenant, tests multi-tenant. |
| Archivos huerfanos | Eliminar archivo fisico antes del soft delete; manejar `FileNotFoundException` en borrado (loguear y continuar). Comando de auditoria futuro para detectar archivos sin registro. |
| Acumulacion descontrolada de adjuntos | `AttachmentCollectionRegistry` define limite maximo por coleccion y por entidad; el servicio rechaza la subida si se supera. |
| Borrados indebidos | Soft delete en metadatos y autorizacion estricta. |
| Crecimiento de almacenamiento | Limites por archivo, futuras cuotas por tenant y politicas de retencion. |
| Exceso de tipos permitidos | Lista blanca de MIME y extension. |

---

## 15. Criterios de aceptacion de la base

La Fase 1 se considera lista cuando (entidad piloto: `Pallet`):

- Un usuario autorizado puede subir un archivo a un palet.
- Un usuario autorizado puede listar adjuntos de ese palet (respuesta paginada).
- Un usuario autorizado puede descargar el archivo.
- Un usuario autorizado puede editar `notes` y `metadata` de un adjunto existente.
- Un usuario sin permiso no puede listar, ver, descargar ni editar.
- Un usuario autorizado puede borrar si la regla de negocio lo permite; el archivo fisico se elimina con el soft delete.
- El campo `attachable_type` almacena la clave corta del morphMap, no el FQCN.
- El archivo queda almacenado en una ruta tenant-aware usando el slug del tenant.
- El `mime_type` registrado es el detectado por el servidor, no el declarado por el cliente.
- La respuesta API no expone paths internos de storage.
- Los tests cubren: subida valida, subida con MIME invalido, listado paginado, filtro por coleccion, detalle, actualizacion de notas, descarga, borrado y denegacion por permisos.
- El sistema no usa URLs publicas permanentes para archivos privados.

---

## 16. Recomendacion final

Implementar primero una base pequena, profesional y transversal. Despues activar casos de uso por modulo:

1. Base tecnica de adjuntos.
2. Imagenes para palets.
3. Documentacion e imagenes para pedidos.
4. Imagenes y documentacion para recepciones.

El punto mas delicado no es tecnico, sino semantico: definir que significa cada adjunto dentro del negocio. La implementacion debe dejar espacio para esa evolucion sin obligar a migraciones por cada nueva categoria menor.

