# Estándares de seeders para entorno de desarrollo

Este documento define las características que debe cumplir el conjunto de seeders del proyecto para ser útil en un entorno de desarrollo: datos creíbles, reproducibles y alineados con la lógica de negocio y el dominio pesquero.

---

## 1. Idempotencia

Los seeders deben poder ejecutarse **varias veces** sin errores ni duplicación de datos.

- Usar **`firstOrCreate`** o **`updateOrCreate`** con una **clave natural única** (email, vat_number, nombre de especie, etc.).
- Evitar `create()` a secas para datos que puedan volver a sembrarse.
- Si el seeder rellena “hasta N registros”, comprobar el count antes de crear (ej. `$toCreate = max(0, TARGET - Model::count())`).

---

## 2. Datos realistas y coherentes con el dominio

Los datos deben **parecer reales** y respetar la **lógica de negocio** y la **nomenclatura del sector**.

### Núcleo fijo vs datos generados

- **Núcleo fijo**: Un conjunto de registros con nombres/datos reales o muy realistas (empresas tipo Novafica, Circeo Pesca; especies con nombre científico y código FAO; productos como “Pulpo Fresco -1kg”, “Caballa fresca”). Sirve para demos y pruebas repetibles.
- **Parte generada**: Registros creados con Faker para dar volumen y variedad, siempre con **reglas que respeten el dominio** (por ejemplo, no mezclar países con formatos de dirección incoherentes).

### Criterios de coherencia

- **Fechas**: Orden lógico (p. ej. `entry_date` ≤ `load_date` en pedidos; “último acceso” posterior a “fecha de creación” en usuarios).
- **Relaciones**: Clientes con direcciones y CIF acordes al país; comerciales con usuarios de rol Comercial; pedidos repartidos entre estados (pending, finished, incident).
- **Terminología**: Nombres de productos, especies, familias y artes de pesca alineados con el glosario del sector (ver CLAUDE.md y documentación de dominio).

---

## 3. Cobertura de estados y casos útiles

El entorno de desarrollo debe permitir **probar todos los flujos** relevantes.

- **Estados de negocio**: Incluir registros en cada estado importante (p. ej. pedidos en pending, finished, incident).
- **Casos “especiales”** controlados: al menos un cliente sin alias, uno con muchos pedidos, uno con incidencias; usuarios de cada rol (Administrador, Técnico, Comercial, Operario); comercial con y sin `user_id` si la aplicación distingue por ello.
- **Escenarios de permisos**: Datos que permitan validar políticas (p. ej. un comercial que solo ve sus clientes/pedidos), asignando parte de los datos al comercial vinculado a un User.

---

## 4. Localización y formato

- **Locale de Faker**: Usar **`Faker::create('es_ES')`** para nombres, direcciones, empresas y teléfonos.
- **Formatos de dominio**: NIF/CIF españoles cuando aplique; códigos FAO en especies; nombres de productos y familias coherentes con el glosario.
- **Texto legible**: Evitar placeholders genéricos (“asdf”, “test1”); preferir nombres que permitan identificar el registro en listados y logs (ej. “Cliente Nº127”, “Laura Comercial”, “Pulpo Fresco -1kg”).

---

## 5. Dependencias y orden

- **Documentar** en el comentario del seeder de qué otros seeders depende (Countries, PaymentTerms, Salespeople, etc.).
- **Orden de ejecución**: Catálogos primero (Countries, PaymentTerms, FishingGear, Incoterms, etc.) → entidades que los referencian (Species, CaptureZones, Customers, Salespeople, Products, etc.) → datos transaccionales (Orders, PunchEvent, etc.).
- Si falta una dependencia: hacer **`$this->command->warn(...)`** y **return** sin fallar, indicando qué seeders ejecutar antes.

---

## 6. Volumen y rendimiento

- **Desarrollo**: Suficiente volumen para que listas, filtros y paginación sean creíbles (decenas de pedidos, varios clientes y usuarios), sin que el seed sea excesivamente lento.
- Opcional: parametrizar el número de registros “extra” (constante o variable de entorno).
- Para **grandes volúmenes** (miles de registros): usar transacciones y inserts por lotes (p. ej. `Model::insert()` en batches) para mantener un tiempo de seed razonable.

---

## 7. Determinismo (opcional)

- En **tests automatizados**: usar **`Faker::seed(12345)`** (o equivalente) al inicio del seeder cuando se ejecute en entorno de testing, para que los datos generados sean reproducibles.
- En desarrollo local puede no ser necesario; aplicar si los mismos seeders se usan en tests.

---

## 8. Credenciales y datos sensibles

- Usuarios de desarrollo con **emails de dominio controlado** (ej. `@pesquerapp.com`) y **roles explícitos** en el nombre o en el comentario (“José Admin”, “Laura Comercial”).
- **Nunca** incluir contraseñas reales ni datos de producción; en proyectos con magic link/OTP, no sembrar passwords.

---

## 9. Documentación en el código

Cada seeder debe incluir en cabecera o comentario:

- **Propósito**: “Clientes de desarrollo”, “Pedidos con estados pending/finished/incident”.
- **Dependencias**: Lista de seeders que deben ejecutarse antes.
- **Origen** (si aplica): “backup_reduced Brisamar”, “nomenclatura sector”, etc.

---

## Resumen

| Aspecto | Recomendación |
|--------|----------------|
| **Nombres** | Núcleo real/realista (empresas, especies, productos) + Faker para volumen; todo coherente con el dominio (español, sector pesquero). |
| **Estados** | Incluir ejemplos de cada estado relevante y al menos un caso por rol/permiso. |
| **Idempotencia** | `firstOrCreate` (o similar) por clave única en todos los seeders. |
| **Dependencias** | Orden claro y `warn` si faltan dependencias. |
| **Locale** | Faker `es_ES` para nombres, direcciones y datos localizados. |
| **Tests** | Si los tests usan seeders, valorar `Faker::seed()` fijo en ese entorno. |

Los seeders son más útiles en desarrollo cuando **parecen datos reales del sector**, **cubren estados y permisos** que se quieren probar y se pueden **ejecutar varias veces sin romper ni duplicar**.

---

## Referencias

- **CLAUDE.md**: Terminología pesquera, modelos de dominio y convenciones del proyecto.
- **docs/fundamentos/01-Arquitectura-Multi-Tenant.md**: Conexión tenant; los seeders se ejecutan en el contexto de una base de datos tenant.
- Seeders de referencia en el proyecto: `CustomerSeeder`, `UsersSeeder`, `OrderSeeder`, `ProductSeeder`, `SpeciesSeeder`.
