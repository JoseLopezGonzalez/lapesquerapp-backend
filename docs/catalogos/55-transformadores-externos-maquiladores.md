# Catálogos - Transformadores Externos / Maquiladores

**Fecha:** 2026-06-25  
**Estado:** Maestro base implementado; integraciones futuras pendientes  
**Ámbito:** Nuevo maestro operativo para empresas externas que transforman producto del tenant

---

## 1. Resumen ejecutivo

Se ha creado una nueva entidad de catálogo llamada **Transformador Externo** o **Maquilador** para representar empresas externas que reciben producto del tenant, realizan una transformación/proceso y devuelven producto transformado, palets, lotes o evidencias operativas.

La entidad nace como un maestro práctico, no como un módulo infinito. En esta primera fase resuelve tres necesidades:

1. Identificar legal y operativamente a la empresa externa.
2. Guardar datos mínimos de contacto, dirección y registro sanitario.
3. Preparar una base limpia para futuras vinculaciones con pedidos, palets, almacenes externos, producciones y trazabilidad.

No se recomienda modelarlo como un simple `Supplier`, porque el maquilador no solo vende o suministra: **opera sobre producto del tenant** y puede tener implicación directa en inventario, producción, trazabilidad y responsabilidad sanitaria.

---

## 2. Nombre de dominio propuesto

### Nombre funcional

- Español UI: `Transformador externo`
- Alias habitual de negocio: `Maquilador`
- Plural UI: `Transformadores externos` o `Maquiladores`

### Nombre técnico recomendado

- Modelo: `ExternalProcessor`
- Tabla: `external_processors`
- Controller: `ExternalProcessorController`
- Resource: `ExternalProcessorResource`
- Requests: `StoreExternalProcessorRequest`, `UpdateExternalProcessorRequest`, `IndexExternalProcessorRequest`

Se recomienda usar `ExternalProcessor` en código porque es más claro y extensible que `Maquilador`, pero mostrar “Maquilador” o “Transformador externo” en frontend según el lenguaje de negocio.

---

## 3. Relación con entidades existentes

### No confundir con `ExternalUser`

Actualmente existe `ExternalUser` con `type = maquilador`. Esa entidad representa **un actor que puede iniciar sesión** y operar con acceso limitado.

El nuevo `ExternalProcessor` debe representar **la empresa externa**.

Relación conceptual:

```text
ExternalProcessor
  └── puede tener 0..N ExternalUser
```

Ejemplo:

- `ExternalProcessor`: "Congelados Atlántico S.L."
- `ExternalUser`: "operario.maquila@congelados-atlantico.es"

Esta separación evita mezclar datos fiscales, sanitarios y direcciones de empresa con credenciales o permisos de usuario.

### No sustituye todavía a `Supplier`

Un proveedor entrega o vende mercancía/servicio. Un maquilador transforma producto del tenant. Puede que una misma empresa sea ambas cosas en la realidad, pero en el ERP conviene mantener roles separados hasta que exista una necesidad clara de unificar terceros.

En una fase posterior se podría estudiar un modelo común de “terceros” (`business_partners`) con roles, pero para ahora sería más costoso y arriesgado que útil.

---

## 4. Campos propuestos para la primera versión

La primera versión debe ser completa para trabajar, pero sin intentar cubrir certificaciones, auditorías, contratos, tarifas o procesos complejos desde el inicio.

### Campos obligatorios

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre comercial o razón social visible en la aplicación. |
| `vat_number` | string | CIF/NIF/VAT de la empresa. |
| `is_active` | boolean | Permite desactivar sin borrar histórico. Default `true`. |

### Campos recomendados opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `legal_name` | string nullable | Razón social si difiere del nombre visible. |
| `sanitary_registration_number` | string nullable | Registro sanitario/RGSEAA u otro identificador equivalente. |
| `contact_person` | string nullable | Persona principal de contacto. |
| `phone` | string nullable | Teléfono principal. |
| `emails` | text/json nullable | Emails de contacto. Idealmente array JSON; si se prioriza consistencia con `Customer`/`Supplier`, texto con helper existente. |
| `address` | text nullable | Dirección principal de la planta/oficina. |
| `city` | string nullable | Ciudad. |
| `postal_code` | string nullable | Código postal. |
| `province` | string nullable | Provincia/estado. |
| `country_id` | foreignId nullable | País, si se quiere aprovechar catálogo `countries`. |
| `notes` | text nullable | Notas internas operativas. |

### Campos técnicos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `created_at` | timestamp | Creación. |
| `updated_at` | timestamp | Última actualización. |

### Campos que conviene posponer

No añadir en la primera versión salvo necesidad inmediata:

- Tarifas por proceso, especie, calibre o formato.
- Contratos y vigencias.
- Certificaciones múltiples con caducidad.
- Homologación/auditorías.
- Condiciones logísticas por ruta.
- Relación directa con cada tipo de proceso productivo.
- Cuentas contables o integración A3ERP/Facilcom.

Estos puntos pueden convertirse en tablas hijas cuando haya casos reales.

---

## 5. Estructura de base de datos recomendada

### Tabla: `external_processors`

```php
Schema::create('external_processors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('legal_name')->nullable();
    $table->string('vat_number', 32);
    $table->string('sanitary_registration_number', 64)->nullable();
    $table->string('contact_person')->nullable();
    $table->string('phone', 50)->nullable();
    $table->text('emails')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('postal_code', 20)->nullable();
    $table->string('province')->nullable();
    $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique('vat_number');
    $table->index(['is_active', 'name']);
    $table->index('sanitary_registration_number');
});
```

### Decisiones de diseño

- `vat_number` único por tenant: evita duplicados evidentes.
- `is_active`: preferible a borrar registros usados en histórico.
- `country_id` nullable: útil si ya se trabaja con catálogo de países, pero no bloquea altas rápidas.
- `emails`: por consistencia inmediata puede seguir el patrón actual de `Customer`, `Supplier` y `Transport`; a medio plazo sería mejor normalizarlo como JSON o tabla de contactos.

---

## 6. Modelo Eloquent

### Modelo

```php
class ExternalProcessor extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'name',
        'legal_name',
        'vat_number',
        'sanitary_registration_number',
        'contact_person',
        'phone',
        'emails',
        'address',
        'city',
        'postal_code',
        'province',
        'country_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
```

### Relaciones iniciales

```php
public function country()
{
    return $this->belongsTo(Country::class);
}

public function externalUsers()
{
    return $this->hasMany(ExternalUser::class);
}
```

La relación con `ExternalUser` requerirá añadir `external_processor_id` nullable en `external_users`. No es obligatorio para crear el maestro, pero sí recomendable para cerrar el modelo funcional.

---

## 7. API v2 propuesta

Rutas bajo API v2 y autenticación existente.

```text
GET    /api/v2/external-processors
POST   /api/v2/external-processors
GET    /api/v2/external-processors/{id}
PUT    /api/v2/external-processors/{id}
DELETE /api/v2/external-processors/{id}
GET    /api/v2/external-processors/options
```

### Listado

Filtros recomendados:

| Query param | Descripción |
|-------------|-------------|
| `id` | Filtrar por ID. |
| `ids[]` | Filtrar por varios IDs. |
| `name` | Búsqueda por nombre o razón social. |
| `vatNumber` | Búsqueda exacta o parcial por CIF/NIF/VAT. |
| `sanitaryRegistrationNumber` | Búsqueda por registro sanitario. |
| `isActive` | `true` / `false`. |
| `countryId` | Filtrar por país. |
| `perPage` | Paginación, default 12. |

Orden por defecto: `name ASC`.

### Resource de respuesta

```json
{
  "id": 1,
  "name": "Congelados Atlántico S.L.",
  "legalName": "Congelados Atlántico Sociedad Limitada",
  "vatNumber": "B12345678",
  "sanitaryRegistrationNumber": "12.34567/PO",
  "contactPerson": "María García",
  "phone": "+34 986 000 000",
  "emails": ["produccion@congelados-atlantico.es"],
  "ccEmails": ["administracion@congelados-atlantico.es"],
  "address": "Polígono Industrial, nave 4",
  "city": "Vigo",
  "postalCode": "36201",
  "province": "Pontevedra",
  "country": {
    "id": 1,
    "name": "España"
  },
  "isActive": true,
  "notes": "Transformador externo principal para cefalópodo.",
  "createdAt": "2026-06-25T10:00:00+02:00",
  "updatedAt": "2026-06-25T10:00:00+02:00"
}
```

### Validación recomendada

```php
[
    'name' => ['required', 'string', 'max:255'],
    'legalName' => ['nullable', 'string', 'max:255'],
    'vatNumber' => ['required', 'string', 'max:32', Rule::unique(ExternalProcessor::class, 'vat_number')->ignore($id ?? null)],
    'sanitaryRegistrationNumber' => ['nullable', 'string', 'max:64'],
    'contactPerson' => ['nullable', 'string', 'max:255'],
    'phone' => ['nullable', 'string', 'max:50'],
    'emails' => ['nullable', 'array'],
    'emails.*' => ['string', 'email:rfc,dns', 'distinct'],
    'ccEmails' => ['nullable', 'array'],
    'ccEmails.*' => ['string', 'email:rfc,dns', 'distinct'],
    'address' => ['nullable', 'string', 'max:1000'],
    'city' => ['nullable', 'string', 'max:255'],
    'postalCode' => ['nullable', 'string', 'max:20'],
    'province' => ['nullable', 'string', 'max:255'],
    'countryId' => ['nullable', 'integer', 'exists:tenant.countries,id'],
    'isActive' => ['sometimes', 'boolean'],
    'notes' => ['nullable', 'string', 'max:2000'],
]
```

---

## 8. Permisos y seguridad

### Roles iniciales propuestos

| Acción | Roles internos recomendados |
|--------|-----------------------------|
| Listar/ver | `superuser`, `manager`, `admin`, `store_operator`, `tecnico`, `direccion`, `administracion` |
| Crear/editar | `superuser`, `manager`, `admin`, `tecnico`, `direccion`, `administracion` |
| Desactivar | `superuser`, `manager`, `admin`, `direccion` |
| Eliminar | Evitar borrado físico si tiene relaciones. Mejor desactivar. |

Los `ExternalUser` no deberían poder gestionar este catálogo.

### Borrado

Recomendación fuerte: no exponer eliminación física como operación principal. Si se implementa `DELETE`, debería:

- bloquear si el transformador tiene relaciones operativas;
- o convertirlo en desactivación lógica (`is_active = false`);
- devolver un mensaje claro si está en uso.

---

## 9. Integraciones futuras previstas

Esta primera entidad debe quedar preparada para futuras relaciones, pero sin implementarlas todas ya.

### Fase futura A: almacenes externos

Vincular `stores.external_processor_id` para indicar que un almacén externo pertenece a una empresa maquiladora concreta.

Esto complementaría el actual `stores.external_user_id`, que hoy sirve para permisos de acceso.

### Fase futura B: usuarios externos

Añadir `external_users.external_processor_id`.

Permite que varios usuarios externos pertenezcan a la misma empresa y que el tenant pueda auditar quién opera en nombre de qué maquilador.

### Fase futura C: pedidos / órdenes de transformación

Cuando el negocio lo pida, añadir una relación desde pedidos o subpedidos:

- `orders.external_processor_id` si el pedido completo depende de un maquilador.
- Una tabla intermedia si solo parte del pedido/líneas se transforman fuera.
- Una entidad específica `external_processing_orders` si se necesita controlar envío, recepción, mermas, outputs y costes.

No conviene añadir este vínculo sin definir antes el flujo real.

### Fase futura D: palets y trazabilidad

Posibles relaciones:

- `pallets.external_processor_id`: palet generado por transformador externo.
- movimientos de almacén con origen/destino externo;
- historial de transformación vinculado a lotes/productos.

Debe implementarse cuando se diseñe el flujo de producción externa completo.

---

## 10. UI recomendada para primera versión

Ubicación: `Catálogos > Transformadores externos`.

### Listado

Columnas prácticas:

- Nombre
- CIF/NIF/VAT
- Registro sanitario
- Contacto
- Teléfono
- Email principal
- País / provincia
- Estado

Filtros:

- Búsqueda por nombre
- CIF/NIF/VAT
- Registro sanitario
- Activo/inactivo

### Formulario

Secciones sencillas:

1. **Datos de empresa**
   - Nombre
   - Razón social
   - CIF/NIF/VAT
   - Registro sanitario
   - Estado activo

2. **Contacto**
   - Persona de contacto
   - Teléfono
   - Emails
   - Emails CC

3. **Dirección**
   - Dirección
   - Ciudad
   - Código postal
   - Provincia
   - País

4. **Notas internas**
   - Notas

No añadir pestañas de pedidos, palets o producción en la primera versión salvo como espacio preparado visualmente.

---

## 11. Plan de implementación sugerido

### Paso 1 - Maestro base

- Crear migración `external_processors`.
- Crear modelo `ExternalProcessor`.
- Crear factory básica.
- Crear resource v2.
- Crear Form Requests.
- Crear controller CRUD + `options`.
- Registrar rutas.
- Añadir tests feature de CRUD, filtros, validaciones y multi-tenant.

### Paso 2 - Relación con `ExternalUser`

- Añadir `external_processor_id` nullable en `external_users`.
- Exponerlo en `ExternalUserResource`.
- Permitir asignarlo desde gestión de usuarios externos.
- Mantener compatibilidad con usuarios externos existentes sin transformador asignado.

### Paso 3 - Relación con almacenes externos

- Añadir `external_processor_id` nullable en `stores`.
- Permitir filtrar almacenes por transformador.
- Mantener `external_user_id` para permisos de acceso operativo.

### Paso 4 - Diseño de flujo operativo

Antes de vincularlo con pedidos/palets, documentar el flujo exacto:

- qué se envía al maquilador;
- cómo se registra la salida;
- cómo se registra la devolución;
- quién crea los palets;
- cómo se calculan mermas;
- qué costes se imputan;
- qué trazabilidad sanitaria se exige.

---

## 12. Decisiones recomendadas para aprobar ahora

1. Crear entidad propia `ExternalProcessor`.
2. Mantener `ExternalUser` como actor/login, no como ficha empresarial.
3. Usar campos mínimos pero serios: nombre, razón social, CIF, registro sanitario, contacto, emails, dirección, país, estado y notas.
4. No borrar físicamente transformadores usados; usar desactivación.
5. No enlazar todavía con pedidos/palets hasta diseñar el flujo de transformación externa.
6. Preparar desde ya la relación opcional con `ExternalUser` y `Store`.

---

## 13. Propuesta de alcance para primera entrega

La primera entrega debería considerarse terminada cuando exista:

- CRUD API v2 funcional.
- Validación y resource consistente con el resto de catálogos.
- Endpoint `options` para selects.
- Filtros básicos en listado.
- Estado activo/inactivo.
- Tests de creación, edición, listado, detalle, validación, desactivación y aislamiento tenant.
- Documento frontend de integración si se va a construir pantalla inmediatamente.

Quedaría fuera de esta entrega:

- Procesos de maquila completos.
- Costes de transformación.
- Contratos/tarifas.
- Documentación adjunta.
- Auditorías sanitarias.
- Integración contable.
- Trazabilidad avanzada por lote/palet.

Esta separación permite incorporar el maestro pronto y usarlo como base estable para las siguientes fases sin hipotecar el diseño.
