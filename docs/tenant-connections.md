# Gestión de la conexión tenant en Laravel

En este proyecto, la arquitectura multi-tenant está gestionada de forma centralizada y automática para asegurar que cada request y modelo acceda a la base de datos del tenant correcto. Aquí tienes la guía actualizada para desarrolladores:

---

## 1. Middleware Tenant

El middleware `TenantMiddleware` es el encargado de seleccionar la base de datos tenant en cada request:
- **Obtiene el subdominio** del tenant desde la cabecera HTTP `X-Tenant`.
- **Busca el tenant** en la base de datos central (`Tenant`), asegurándose de que esté activo.
- **Configura dinámicamente** la base de datos tenant:
  ```php
  config(['database.connections.tenant.database' => $tenant->database]);
  DB::purge('tenant');
  DB::reconnect('tenant');
  ```
- **Excluye rutas públicas** como `api/v2/public/*`.
- Si no se encuentra el tenant o no está activo, retorna error 400 o 404.

> **Advertencia:**
> El middleware `tenant` está aplicado tanto en las rutas (por grupo) como a nivel global en el stack de middleware de la aplicación (`app/Http/Kernel.php`).
> Esto se debe a problemas de prioridad con Sanctum: si solo se aplica en las rutas, Sanctum puede intentar autenticar antes de que la conexión tenant esté configurada, causando errores. Por eso, el middleware se encuentra en ambos lugares para garantizar el correcto funcionamiento de la autenticación y la selección de base de datos.

**Ejemplo de cabecera requerida:**
```
X-Tenant: subdominio_del_tenant
```

---

## 2. Aplicación del Middleware en las rutas

En `routes/api.php`, el middleware se aplica a todo el grupo de rutas v2:
```php
Route::group(['prefix' => 'v2', 'as' => 'v2.', 'middleware' => ['tenant']], function () {
    // rutas aquí
});
```
De este modo, todas las rutas v2 usan la base de datos tenant correspondiente.

---

## 3. Modelos y Trait UsesTenantConnection

Los modelos que deben operar sobre la base de datos tenant usan el trait `UsesTenantConnection`:
```php
use App\Traits\UsesTenantConnection;

class Supplier extends Model {
    use UsesTenantConnection;
    // ...
}
```
El trait implementa:
```php
public function initializeUsesTenantConnection() {
    $this->setConnection('tenant');
}
```
Esto hace que Eloquent use la conexión tenant por defecto para ese modelo.

---

## 4. Validaciones con reglas `exists` y `unique`

Cuando uses reglas de validación que acceden a la base de datos, **debes especificar la conexión tenant**:
```php
// Correcto para tenant:
'supplier_id' => 'required|integer|exists:tenant.suppliers,id',
// Incorrecto (usa la default):
'supplier_id' => 'required|integer|exists:suppliers,id',

// Para unique:
'email' => 'required|unique:tenant.users,email',
```

---

## 5. Advertencias y buenas prácticas

- **No olvides la cabecera `X-Tenant`** en las peticiones protegidas por el middleware, o recibirás un error 400.
- **No omitas el prefijo `tenant.`** en las reglas de validación, o la consulta se hará sobre la base de datos default.
- **Todos los modelos que acceden a datos tenant deben usar el trait `UsesTenantConnection`.**
- **No modifiques la conexión tenant manualmente** fuera del middleware salvo casos muy excepcionales.
- **Si tienes dudas, revisa la versión v2 de los controladores** para ver ejemplos correctos.
- **Documenta cualquier excepción o caso especial en este archivo.**

---

## 6. Ejemplo de flujo completo

1. El frontend envía la cabecera `X-Tenant` con el subdominio del tenant.
2. El middleware selecciona la base de datos tenant.
3. Los modelos usan la conexión tenant automáticamente gracias al trait.
4. Las validaciones usan la conexión tenant si se especifica en la regla.

---

*Actualiza este documento si cambian las convenciones o la arquitectura multi-tenant del proyecto.* 