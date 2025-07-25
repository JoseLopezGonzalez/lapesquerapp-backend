# Gestión de la conexión tenant en Laravel

En aplicaciones multi-tenant, es fundamental asegurarse de que todas las operaciones de base de datos (consultas, validaciones, etc.) se realicen sobre la base de datos del tenant correspondiente y no sobre la conexión por defecto (`default`).

## 1. Validaciones con reglas `exists` y `unique`

Cuando uses reglas de validación como `exists` o `unique`, **debes especificar la conexión tenant** para que la validación se realice en la base de datos correcta.

**Ejemplo:**
```php
// Correcto para tenant:
'supplier_id' => 'required|integer|exists:tenant.suppliers,id',
// Incorrecto (usa la default):
'supplier_id' => 'required|integer|exists:suppliers,id',
```

Esto aplica también para la regla `unique`:
```php
'email' => 'required|unique:tenant.users,email',
```

## 2. Modelos y consultas Eloquent

Si necesitas forzar una consulta sobre la conexión tenant:
```php
// En una consulta:
User::on('tenant')->where(...)->get();

// O en el modelo:
class User extends Model {
    protected $connection = 'tenant';
}
```

## 3. Middleware de selección de tenant

Lo más habitual es usar un middleware que seleccione la base de datos tenant antes de cada request. Asegúrate de que tus rutas usan este middleware:
```php
Route::middleware(['tenant'])->group(function () {
    // rutas aquí
});
```

## 4. Resumen de buenas prácticas
- Siempre especifica la conexión en reglas de validación que acceden a la base de datos.
- Si tienes dudas, revisa la versión v2 de los controladores para ver ejemplos correctos.
- Documenta cualquier excepción o caso especial en este archivo.

---

*Actualiza este documento si cambian las convenciones o la arquitectura multi-tenant del proyecto.* 