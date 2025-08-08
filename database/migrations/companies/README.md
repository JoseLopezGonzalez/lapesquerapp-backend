# Migraciones y Seeders Multi-Tenant

## 📋 Descripción

Este directorio contiene las migraciones específicas para las bases de datos de los tenants (empresas). Cada tenant tiene su propia base de datos, por lo que las migraciones deben ejecutarse en todas las bases de datos de tenants.

## 🏗️ Arquitectura Multi-Tenant

- **Base Central**: Contiene la tabla `tenants` con información de todas las empresas
- **Bases de Tenants**: Cada empresa tiene su propia base de datos (`db_empresa1`, `db_empresa2`, etc.)
- **Conexión Dinámica**: El middleware `TenantMiddleware` cambia la conexión según el subdominio

## 🚀 Comandos para Migraciones

### Ejecutar Migraciones en Todos los Tenants

```bash
# Ejecutar migraciones pendientes
php artisan tenants:migrate

# Ejecutar migraciones desde cero (fresh)
php artisan tenants:migrate --fresh

# Ejecutar migraciones y seeders
php artisan tenants:migrate --fresh --seed
```

### Ejecutar Migraciones en un Tenant Específico

```bash
# Configurar conexión manualmente
php artisan tinker
>>> config(['database.connections.tenant.database' => 'nombre_tenant_db']);
>>> DB::purge('tenant');
>>> DB::reconnect('tenant');
>>> exit

# Ejecutar migración
php artisan migrate --path=database/migrations/companies --database=tenant
```

## 🌱 Comandos para Seeders

### Ejecutar Seeders en Todos los Tenants

```bash
# Ejecutar todos los seeders
php artisan tenants:seed

# Ejecutar un seeder específico
php artisan tenants:seed --class=ProductCategorySeeder

# Ejecutar múltiples seeders
php artisan tenants:seed --class=ProductCategorySeeder,ProductFamilySeeder
```

### Ejecutar Seeders en un Tenant Específico

```bash
# Configurar conexión manualmente (como en migraciones)
# Luego ejecutar:
php artisan db:seed --database=tenant --class=ProductCategorySeeder
```

## 📁 Estructura de Archivos

```
database/
├── migrations/
│   ├── companies/           # Migraciones específicas de tenants
│   │   ├── README.md       # Este archivo
│   │   ├── 2023_08_09_*.php
│   │   └── 2025_08_08_*.php
│   └── 2024_*.php          # Migraciones de la base central
├── seeders/
│   ├── ProductCategorySeeder.php
│   ├── ProductFamilySeeder.php
│   └── TenantDatabaseSeeder.php
└── factories/
    ├── ProductCategoryFactory.php
    └── ProductFamilyFactory.php
```

## 🔧 Crear Nuevas Migraciones

### 1. Crear Migración para Tenants

```bash
php artisan make:migration create_nueva_tabla_table --path=database/migrations/companies
```

### 2. Crear Migración para Base Central

```bash
php artisan make:migration create_nueva_tabla_table
```

### 3. Ejecutar Migraciones

```bash
# Para tenants
php artisan tenants:migrate

# Para base central
php artisan migrate
```

## 🌱 Crear Nuevos Seeders

### 1. Crear Seeder

```bash
php artisan make:seeder NuevoSeeder
```

### 2. Implementar Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NuevoModelo;

class NuevoSeeder extends Seeder
{
    public function run(): void
    {
        $datos = [
            ['nombre' => 'Dato 1'],
            ['nombre' => 'Dato 2'],
        ];

        foreach ($datos as $dato) {
            NuevoModelo::create($dato);
        }
    }
}
```

### 3. Ejecutar Seeder

```bash
# En todos los tenants
php artisan tenants:seed --class=NuevoSeeder

# En un tenant específico
php artisan db:seed --database=tenant --class=NuevoSeeder
```

## ⚠️ Consideraciones Importantes

### 1. Protección de Migraciones

Para migraciones que solo deben ejecutarse en tenants, agregar este control:

```php
public function up(): void
{
    if (config('database.default') !== 'tenant') {
        return;
    }
    
    Schema::create('nueva_tabla', function (Blueprint $table) {
        // ...
    });
}
```

### 2. Uso del Trait UsesTenantConnection

Todos los modelos que pertenecen a tenants deben usar:

```php
use App\Traits\UsesTenantConnection;

class MiModelo extends Model
{
    use UsesTenantConnection;
    // ...
}
```

### 3. Validaciones de Existencia

En seeders, verificar que las tablas existen:

```php
public function run(): void
{
    if (!Schema::hasTable('product_categories')) {
        return;
    }
    
    // Continuar con el seeding...
}
```

## 🔍 Troubleshooting

### Error: "No database selected"

**Causa**: La conexión tenant no está configurada correctamente.

**Solución**:
```bash
# Verificar configuración
php artisan config:show database.connections.tenant

# Configurar manualmente
php artisan tinker
>>> config(['database.connections.tenant.database' => 'nombre_db']);
>>> DB::reconnect('tenant');
```

### Error: "Table already exists"

**Causa**: La migración ya se ejecutó en ese tenant.

**Solución**:
```bash
# Verificar estado de migraciones
php artisan migrate:status --database=tenant

# Revertir migración específica
php artisan migrate:rollback --step=1 --database=tenant
```

### Error: "Tenant not found"

**Causa**: El tenant no existe en la base central o está inactivo.

**Solución**:
```bash
# Verificar tenants
php artisan tinker
>>> App\Models\Tenant::where('active', true)->get(['subdomain', 'database']);
```

## 📚 Referencias

- [Laravel Migrations](https://laravel.com/docs/migrations)
- [Laravel Seeders](https://laravel.com/docs/seeders)
- [Multi-Tenant Architecture](https://laravel.com/docs/multi-tenancy)

## 🆘 Comandos de Emergencia

### Resetear Todo

```bash
# ⚠️ PELIGROSO: Borra todas las bases de datos de tenants
php artisan tenants:migrate --fresh --seed
```

### Verificar Estado

```bash
# Estado de migraciones en todos los tenants
php artisan tenants:migrate:status

# Verificar conexiones
php artisan config:show database.connections
```

---

**Última actualización**: Agosto 2025  
**Versión**: 1.0  
**Autor**: Sistema de Migraciones Multi-Tenant
