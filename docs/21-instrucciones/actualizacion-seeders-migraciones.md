# Actualización de Seeders y Migraciones en Desarrollo

Guía para saber qué comando ejecutar cuando cambias seeders o migraciones en el entorno de desarrollo (Sail).

---

## Resumen ejecutivo

| ¿Qué has cambiado? | Comando recomendado | ¿Pierdes datos del tenant? |
|--------------------|---------------------|----------------------------|
| **Seeders** | `tenants:migrate --fresh --seed` | Sí |
| **Migraciones (tenants)** | `tenants:migrate` (o `--fresh --seed` si hay conflictos) | Solo con `--fresh` |
| **Migraciones (central)** | `migrate` | No |
| **Nada, solo levantar entorno** | `sail up -d` | No |

**Recomendación:** Como algunos seeders no son idempotentes, la opción más segura al cambiar seeders es siempre `tenants:migrate --fresh --seed`.

---

## Análisis de Seeders (TenantDatabaseSeeder)

### Idempotentes (seguros para ejecutar varias veces)

Usan `firstOrCreate`, `updateOrCreate` o `updateOrInsert`. Actualizan o crean sin duplicar:

| Seeder | Patrón | Notas |
|--------|--------|-------|
| UsersSeeder | `updateOrCreate` / `firstOrCreate` | Actualiza usuarios por email |
| ProductSeeder | `firstOrCreate` | Por código/identificador |
| CustomerSeeder | `firstOrCreate` | Por identificador |
| TaxSeeder | `firstOrCreate` | Por nombre |
| CountriesSeeder | `firstOrCreate` | Por nombre |
| PaymentTermsSeeder | `firstOrCreate` | Por nombre |
| IncotermsSeeder | `firstOrCreate` | Por código |
| TransportsSeeder | `firstOrCreate` | Por nombre |
| SalespeopleSeeder | `firstOrCreate` | Por identificador |
| SpeciesSeeder | `firstOrCreate` | Por nombre/código |
| FishingGearSeeder | `firstOrCreate` | Por nombre |
| CaptureZonesSeeder | `firstOrCreate` | Por nombre |
| FAOZonesSeeder | `firstOrCreate` | Por código |
| StoreOperatorUserSeeder | `firstOrCreate` | Usuario operario tienda |
| OrderPlannedProductDetailSeeder | `firstOrCreate` | Por order_id + product_id |
| OrderPalletSeeder | `firstOrCreate` | Por order_id + pallet_id |
| TenantDatabaseSeeder (settings) | `updateOrInsert` | Por clave |

### Con guardia (no duplican, pero tampoco actualizan)

Comprueban si ya existen datos y omiten la creación:

| Seeder | Guardia | Comportamiento al re-ejecutar |
|--------|---------|------------------------------|
| OrderSeeder | `Order::count() > 0` | Omite si hay pedidos |
| BoxSeeder | `Box::count() >= 30` | Omite si hay 30+ cajas |
| PalletSeeder | `Pallet::count() >= 20` | Omite si hay 20+ palés |

### No idempotentes (fallan o duplican)

Usan `create()` sin guardia. La tabla tiene índice único en `name`, por lo que **fallarán** al ejecutarse de nuevo:

| Seeder | Problema |
|--------|----------|
| **ProductCategorySeeder** | `create()` → error "Duplicate entry 'Fresco'" o 'Congelado' |
| **ProductFamilySeeder** | `create()` → error "Duplicate entry" (unique en name) |

**Conclusión:** Ejecutar `tenants:seed` sobre datos existentes **fallará** en ProductCategorySeeder (es el primero de la cadena que usa create sin guardia). Por eso la opción fiable es `tenants:migrate --fresh --seed`.

---

## Árbol de decisión

```
¿Has cambiado seeders?
├── SÍ → tenants:migrate --fresh --seed
│        (borra BD tenant, recrea tablas, ejecuta seeders)
│
└── NO → ¿Has cambiado migraciones de tenants?
         ├── SÍ → ¿Hay conflicto con datos existentes?
         │        ├── SÍ / No seguro → tenants:migrate --fresh --seed
         │        └── NO → tenants:migrate
         │                 (solo migraciones pendientes)
         │
         └── NO → ¿Has cambiado migraciones central?
                  ├── SÍ → sail artisan migrate
                  └── NO → sail up -d (solo levantar)
```

---

## Comandos detallados

### 1. Cambiaste seeders

```bash
./vendor/bin/sail artisan tenants:migrate --fresh --seed
```

- **Qué hace:** Elimina todas las tablas del tenant, las recrea con las migraciones y ejecuta los seeders actualizados.
- **Datos:** Se pierden todos los datos del tenant (pedidos, usuarios, productos, etc.).
- **Cuándo:** Cada vez que modificas cualquier seeder del `TenantDatabaseSeeder`.

### 2. Cambiaste migraciones de tenants (nuevas tablas o columnas)

**Si las migraciones son aditivas** (nuevas tablas, nuevas columnas nullable):

```bash
./vendor/bin/sail artisan tenants:migrate
```

- **Qué hace:** Ejecuta solo las migraciones pendientes.
- **Datos:** Se conservan.

**Si hay migraciones destructivas o no estás seguro:**

```bash
./vendor/bin/sail artisan tenants:migrate --fresh --seed
```

### 3. Cambiaste migraciones de la base central

```bash
./vendor/bin/sail artisan migrate
```

- **Qué hace:** Ejecuta migraciones pendientes en la BD central (`pesquerapp`).
- **Datos:** La tabla `tenants` y el resto de la BD central se actualizan sin borrar tenants (salvo que la migración lo haga explícitamente).

### 4. Solo quieres volver a ejecutar seeders (sin borrar)

⚠️ **No recomendado** con la implementación actual. ProductCategorySeeder y ProductFamilySeeder fallarán. Si en el futuro se migran a `firstOrCreate`, podrías usar:

```bash
./vendor/bin/sail artisan tenants:seed --class=TenantDatabaseSeeder
```

### 5. Ejecutar un seeder concreto

```bash
./vendor/bin/sail artisan tenants:seed --class=UsersSeeder
```

Útil para seeders idempotentes (UsersSeeder, CountriesSeeder, etc.) cuando solo has cambiado ese seeder.

---

## Dónde se guardan los datos de desarrollo

| Componente | Ubicación | Persistencia |
|------------|-----------|--------------|
| **MySQL (BD central + tenants)** | Volumen Docker `sail-mysql` | Persiste entre `sail down` y `sail up` |
| **Redis** | Volumen Docker `sail-redis` | Persiste entre `sail down` y `sail up` |

Los datos **solo se pierden** si ejecutas:

```bash
./vendor/bin/sail down -v
```

La opción `-v` elimina los volúmenes. Sin `-v`, los datos permanecen.

**`tenants:migrate --fresh`** borra y recrea las tablas del tenant, pero el volumen Docker sigue existiendo; solo se vacía el contenido de las BD de tenants.

---

## Referencias

- [Deploy en desarrollo](./deploy-desarrollo.md) — Pasos iniciales y comandos generales.
- [Deploy guiado (primera vez)](./deploy-desarrollo-guiado.md) — Guía paso a paso.
- [database/migrations/companies/README.md](../../database/migrations/companies/README.md) — Migraciones y seeders multi-tenant.
