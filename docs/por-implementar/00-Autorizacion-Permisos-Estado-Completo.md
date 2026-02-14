# Autorización y Permisos — Estado Completo y Por Implementar

**Última actualización**: 2026-02-14  
**Estado**: Documentación completada. Pendiente de implementación.

Este documento consolida el trabajo realizado en la auditoría del sistema de autorización y sirve como guía para retomar la implementación en otro momento.

---

## 1. Resumen ejecutivo

### 1.1 Situación actual

- **13 Policies** implementadas; todas usan la **misma lógica**: permiten los 6 roles en todas las acciones.
- **No hay diferenciación** por rol, tenant, almacén ni ownership.
- Cualquier usuario autenticado puede hacer todo en todo el sistema.

### 1.2 Objetivo

Implementar restricciones de permisos por rol según la matriz de negocio documentada, empezando por la vinculación User–Salesperson (base para el comercial).

---

## 2. Roles del sistema

| Rol | Descripción | Acceso objetivo |
|-----|-------------|-----------------|
| **tecnico** | Técnico del software (soporte IT) | Acceso total. Por encima de todo. |
| **administrador** | Admin del tenant | Acceso total. |
| **direccion** | Dirección/gerencia | Acceso total. |
| **administracion** | Administrativo | Restricciones por definir (TBD). |
| **comercial** | Ventas | Solo pedidos propios; crear pedidos; ciertos PDFs. |
| **operario** | Personal planta/almacén | Stock, recepciones, despachos, fichajes (con restricciones). |

**Nota**: `Store` = almacén (en código se usa Store; en negocio son almacenes, no tiendas).

---

## 3. Matriz de permisos acordada

### Leyenda

- **✅** = Permitido
- **✅R** = Permitido con restricción
- **📋** = Solo listar (viewAny), sin detalle; datos restringidos
- **📄** = Solo acciones específicas (ej. ciertos PDFs)
- **👁️** = Solo lectura (viewAny, view)
- **❌** = Sin acceso

### Por entidad

| Entidad | operario | tecnico | comercial | administracion | administrador | direccion |
|---------|----------|---------|-----------|----------------|---------------|----------|
| User | ❌ | ✅ | ❌ | 👁️ | ✅ | ✅ |
| Store (almacenes) | ✅* | ✅ | ❌ | TBD | ✅ | ✅ |
| Customer | ❌ | ✅ | ❌ | TBD | ✅ | ✅ |
| Salesperson | ❌ | ✅ | ❌ | TBD | ✅ | ✅ |
| Order | ❌ | ✅ | ✅R+📄 | TBD | ✅ | ✅ |
| Product* | 👁️? | ✅ | ❌ | TBD | ✅ | ✅ |
| Label | TBD | ✅ | ❌ | TBD | ✅ | ✅ |
| RawMaterialReception | 📋+create | ✅ | ❌ | TBD | ✅ | ✅ |
| CeboDispatch | 📋+create | ✅ | ❌ | TBD | ✅ | ✅ |
| Pallet | ✅ | ✅ | ❌ | TBD | ✅ | ✅ |
| Box | ✅ | ✅ | ❌ | TBD | ✅ | ✅ |
| PunchEvent | create only | ✅ | ❌ | TBD | ✅ | ✅ |

*Operario Store: viewAny, view, update (stock). Comercial Order: solo pedidos propios + PDFs hoja de pedido, nota de carga, nota de carga valorada.

### Reglas específicas por rol

**Operario**
- Store: todos los almacenes; agregar stocks, pasar palets, crear pallets. No crear/eliminar almacenes.
- RawMaterialReception / CeboDispatch: crear y listar (solo cantidades; sin importes ni precios). No detalle, no editar, no borrar.
- PunchEvent: solo crear (entrada/salida). No listar ni modificar.
- Sin acceso a User, Customer, Salesperson, Order.

**Comercial**
- Order: crear; ver solo sus pedidos (salesperson_id = su Salesperson); no editar; no vincular palets.
- PDFs permitidos: hoja de pedido, nota de carga, nota de carga valorada.
- Sin acceso a Customer, Salesperson, Store, Product, etc.
- **Dependencia**: vincular User ↔ Salesperson.

---

## 4. User ↔ Salesperson (decisión tomada)

### 4.1 Opción elegida: B (`salespeople.user_id`)

- Salesperson es la entidad maestra; el User es la cuenta de acceso.
- `salespeople.user_id` (nullable, unique, FK a users).

### 4.2 Implementación pendiente

**Migración**:
```php
Schema::table('salespeople', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->after('emails')
        ->constrained('users')->nullOnDelete();
    $table->unique('user_id');
});
```

**Salesperson.php**:
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

**User.php**:
```php
public function salesperson()
{
    return $this->hasOne(Salesperson::class);
}
```

**Uso en scoping**:
```php
$salesperson = Salesperson::where('user_id', $user->id)->first();
if ($salesperson) {
    $query->where('orders.salesperson_id', $salesperson->id);
}
```

---

## 5. Pendientes de decisión

| # | Tema | Qué falta |
|---|------|-----------|
| 1 | Administración | Definir restricciones del rol administrativo |
| 2 | Operario + Product | Confirmar si necesita lectura para operaciones de stock |
| 3 | Operario + Label | Confirmar si usa etiquetas en planta/almacén |
| 4 | Operario + delete Pallet/Box | Definir reglas de borrado |
| 5 | Employee | ¿Operario necesita ver empleados para fichajes manuales o solo NFC? |

---

## 6. Plan de implementación (orden sugerido)

### Fase 1: Base (ya decidido)

1. **Migración User–Salesperson**: `salespeople.user_id`
2. **Relaciones** en User y Salesperson

### Fase 2: Policies por bloque

3. **Policies para PunchEvent y Employee** (no existen actualmente)
4. **Operario**: StorePolicy, PalletPolicy, BoxPolicy, RawMaterialReceptionPolicy, CeboDispatchPolicy, PunchEvent (create only)
5. **Comercial**: OrderPolicy (scoping por salesperson_id)
6. **Resto de entidades**: User, Customer, Salesperson, Product, etc.

### Fase 3: Restricciones adicionales

7. **PDFs de pedidos**: Restringir comercial a hoja de pedido, nota de carga, nota de carga valorada
8. **Vistas/serialización**: Ocultar importes y precios al operario en listados de RawMaterialReception y CeboDispatch

---

## 7. Archivos de referencia

| Documento | Ubicación |
|-----------|-----------|
| Inventario original | `.ai_work_context/20260214_2129/01_analysis/inventario_autorizacion.md` |
| Matriz detallada | `.ai_work_context/20260214_2129/02_planning/propuesta_matriz_permisos.md` |
| Propuesta User–Salesperson | `.ai_work_context/20260214_2129/02_planning/propuesta_user_salesperson.md` |
| Prompt auditoría | `docs/35-prompts/04_Prompt agente autorizacion laravel.md` |

---

## 8. Entidades y Policies actuales

| Entidad | Policy | Estado actual |
|---------|--------|---------------|
| User | UserPolicy | Todos los roles permitidos |
| Order | OrderPolicy | Todos los roles permitidos |
| Customer | CustomerPolicy | Todos los roles permitidos |
| Salesperson | SalespersonPolicy | Todos los roles permitidos |
| Product | ProductPolicy | Todos los roles permitidos |
| ProductCategory | ProductCategoryPolicy | Todos los roles permitidos |
| ProductFamily | ProductFamilyPolicy | Todos los roles permitidos |
| Label | LabelPolicy | Todos los roles permitidos |
| RawMaterialReception | RawMaterialReceptionPolicy | Todos los roles permitidos |
| Pallet | PalletPolicy | Todos los roles permitidos |
| Box | BoxPolicy | Todos los roles permitidos |
| CeboDispatch | CeboDispatchPolicy | Todos los roles permitidos |
| Store | StorePolicy | Todos los roles permitidos |
| PunchEvent | — | Sin Policy |
| Employee | — | Sin Policy |

---

**Para retomar**: empezar por Fase 1 (User–Salesperson) y, en paralelo, cerrar las decisiones pendientes de la sección 5.
