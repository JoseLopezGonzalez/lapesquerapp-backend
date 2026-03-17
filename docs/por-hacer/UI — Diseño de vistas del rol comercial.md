# UI — Diseño de vistas del rol comercial

> Este documento describe cómo se organiza visualmente la experiencia del comercial en PesquerApp. No es un wireframe de píxel, sino la descripción funcional de cada vista: qué ve, cómo navega y qué patrones de UI se usan.

> **Revisado contra código real del frontend — Marzo 2026.**

---

## Navegación del rol comercial

El comercial usa `ComercialLayoutClient.jsx` que monta `ResponsiveLayout`. El comportamiento real es:

* **Desktop (≥768px):** sidebar lateral fijo (18 rem, colapsable a iconos) igual que admin — no hay barra superior
* **Mobile (<768px):** sin sidebar. Usa **BottomNav fijo con 5 slots** (2 izquierda + botón central de acción + 2 derecha). Menú completo accesible vía `NavigationSheet` (drawer deslizable desde abajo)

> **Limitación real del BottomNav:** el componente fija 5 slots. Con 6 ítems CRM, los que no caben en los slots principales quedan en el sheet del menú completo.

> **Prioridad de slots (decisión de producto):** Autoventa tiene uso intensivo en mobile y acceso crítico — tiene prioridad máxima en el BottomNav. Orden: Inicio (slot 1) → Autoventa (slot 2) → botón central (slot 3, reservado) → Prospectos (slot 4) → Clientes (slot 5). Ofertas y Pedidos van en el `NavigationSheet` (menú completo deslizable). Son flujos que se usan principalmente en desktop o con tiempo, no en campo.

Se añaden estos ítems a `navgationConfig.js`:

| Ítem de menú | Ruta                      | Descripción                              |
| -------------- | ------------------------- | ----------------------------------------- |
| Inicio         | `/comercial`            | Dashboard con widgets CRM + estadísticas |
| Prospectos     | `/comercial/prospectos` | Lista de prospectos propios               |
| Mis clientes   | `/comercial/clientes`   | Lista de clientes asignados               |
| Ofertas        | `/comercial/ofertas`    | Lista de ofertas propias                  |
| Mis pedidos    | `/comercial/pedidos`    | Order Manager limitado (solo ver + crear) |
| Autoventa      | `/comercial/autoventa`  | Wizard existente, sin cambios             |

---

## Vista 1 — Dashboard (`/comercial`)

### Qué es

El punto de entrada del comercial cada mañana. Combina los widgets de estadísticas actuales con la nueva sección CRM.

### Layout real (basado en `ComercialDashboard/index.js`)

El dashboard actual usa `ScrollArea` wrapping un `flex-col` con:

1. Saludo (greeting + nombre del usuario)
2. Grid 2 columnas (`TotalQuantitySoldCard` + `TotalAmountSoldCard`)
3. `Masonry` (3 cols → 2 → 1 según breakpoints) con `OrderRankingChart`, `SalesBySalespersonPieChart`, `TransportRadarChart`

Las nuevas secciones CRM se **insertan entre el saludo y el grid de stats existente**, respetando el mismo patrón de `flex-col gap-4`:

**Bloque nuevo 1 — Agenda del día (insertar primero)**

Widget `RemindersWidget` — lista de acciones pendientes hoy y esta semana, de `next_action_at` en interacciones y prospectos.

* Cada ítem: nombre del prospecto/cliente, tipo de acción, días de vencimiento
* Vencida (fecha pasada) → badge `variant="destructive"` (rojo, componente Badge existente)
* Click en ítem → navega a la ficha del prospecto/cliente
* Sin acciones pendientes → componente `Empty` (ya existe en `src/components/ui/empty.jsx`) con texto "Todo al día"

**Bloque nuevo 2 — Alertas CRM (insertar segundo)**

Grid de 2 columnas con `gap-4` (igual que el grid de stats):

* `InactiveCustomersWidget`: clientes sin pedido en >30 días — nombre, días de inactividad
* `ProspectsWithoutActivityWidget`: prospectos sin interacción en >7 días

**Bloque existente — Stats (mantener, con un ajuste)**

`TotalQuantitySoldCard`, `TotalAmountSoldCard`, `OrderRankingChart`, `TransportRadarChart` se mantienen sin cambios.

`SalesBySalespersonPieChart` → **sustituir** por un `Card` de resumen propio del comercial: "Tus ventas este año" con importe total del comercial autenticado. Razón: el pie chart muestra todos los comerciales y para un único usuario es irrelevante.

---

## Vista 2 — Lista de prospectos (`/comercial/prospectos`)

### Qué es

Tabla principal de prospectos del comercial.

### Patrón de tabla real

El sistema tiene **dos patrones de tabla distintos**:

* `EntityClient` (en `src/components/Admin/Entity/EntityClient/`) → TanStack Table v8 + shadcn `Table`. Usado en Clientes, Proveedores, Pedidos (admin). Mobile: convierte a `Accordion` con items expandibles.
* `OrdersManager` → lista de `OrderCard` (cards clickables, no tabla). Master-detail en desktop.

Para prospectos se recomienda seguir el **patrón del `OrdersManager`** (lista de cards + master-detail), ya que las fichas son ricas y el flujo natural es click → ficha a la derecha, no click → página separada.

### Componentes

* **Cabecera:** título "Prospectos" + botón primario "Nuevo prospecto" (variant `default`).
* **Filtros:**`Tabs` (componente shadcn ya existente) con: Todos / Nuevo / En seguimiento / Oferta enviada / Descartados. Más `Input` de búsqueda por nombre de empresa (patrón `InputGroup` con `InputGroupInput`, igual que OrdersManager).
* **Lista de cards:**`ScrollArea` con `ProspectoCard` por ítem — empresa, país, badge de estado, próxima acción, días sin contacto.
* **Badge de estado:** inline-flex con fondo de color semitransparente + punto de color, igual que los status badges de pedidos (`bg-orange-500/15 text-orange-700`). Sin usar el componente `Badge` con variants estándar.
* **Ordenación por defecto:** próxima acción más cercana primero. Los sin fecha van al final.
* **Card con acción vencida:** borde o fondo diferenciado (clase `border-destructive/40` o `bg-destructive/5`).
* **Click en card (desktop):** panel de detalle se abre a la derecha (master-detail, igual que Order Manager).
* **Click en card (mobile):** navega a vista full-screen de la ficha.
* **Menú de tres puntos en cada card:** usa `DropdownMenu` + `DropdownMenuTrigger` + `DropdownMenuContent` de shadcn. Las acciones disponibles dependen del estado del prospecto:

| Estado                | Acciones disponibles                                                                 |
| --------------------- | ------------------------------------------------------------------------------------ |
| `new`/`following` | Editar · Nueva interacción · Enviar oferta · Descartar                           |
| `offer_sent`        | Ver oferta · Nueva oferta · Nueva interacción · Convertir a cliente · Descartar |
| `customer`          | Ver cliente · Nueva interacción · Ver ofertas                                     |
| `discarded`         | Reactivar (vuelve a `following`) · Ver historial                                  |

"Convertir a cliente" solo aparece en `offer_sent` porque es el momento lógico — ya hubo oferta. En `new`/`following` no tiene sentido convertir sin haber enviado nada. En `customer` y `discarded` no se muestran acciones de avance.

---

## Vista 3 — Ficha de prospecto (`/comercial/prospectos/[id]`)

### Qué es

Vista de detalle de un prospecto.

### Corrección de patrón: NO es de dos columnas

La afirmación anterior de "ficha de dos columnas igual que ficha de pedido" era incorrecta. El detalle de pedido real (`OrdersManager/Order/index.js`) **no usa dos columnas**. Usa:

* **Desktop:**`Card` wrapper + `OrderTabsDesktop` (tabs horizontales) para navegar entre secciones
* **Mobile:**`SectionList` (lista de secciones seleccionables) → sección seleccionada en full-screen

La ficha de prospecto debe seguir **el mismo patrón real del Order Manager**:

**Desktop:**

* `Card` con `CardHeader` (nombre de empresa + badge de estado + acciones) + `CardContent`
* `Tabs` (shadcn) para secciones: "Datos" / "Contactos" / "Interacciones" / "Ofertas"
* Cada tab muestra su contenido en el área derecha

**Mobile:**

* Header con nombre + badge + botón de acciones
* Lista de secciones seleccionables (igual que Order mobile)
* Tap en sección → vista full-screen de esa sección, con botón de volver
* Se usa `useHideBottomNav` para ocultar el BottomNav al entrar en sección (ya existe el hook)

### Contenido de cada tab/sección

**Tab "Datos"**

* Nombre de empresa (editable), país, origen, especie de interés, notas libres
* Próxima acción (fecha + texto)
* Botón "Convertir a cliente" (solo si `status === 'offer_sent'` — coherente con la regla funcional y el menú de la lista)
* Botón "Descartar" → abre `AlertDialog` (shadcn, ya existe) con campo de motivo
* Selector de estado: usa `Select` (shadcn) inline, no selector de chips

**Tab "Contactos"**

* Lista de contactos con nombre, cargo, teléfono, email
* Contacto primario: badge `variant="default"`
* Botón "Añadir contacto" → `Sheet` (shadcn, patrón de PositionSlideover)
* Edición de contacto → mismo `Sheet`

**Tab "Interacciones"**

* Lista cronológica (más reciente arriba). Cada ítem: tipo (icono de Lucide), fecha, resumen, badge de resultado
* Botón "Registrar interacción" → abre `QuickInteractionModal`

**Tab "Ofertas"**

* Lista de ofertas: estado (badge), fecha, importe estimado
* Botón "Nueva oferta" → navega a `/comercial/ofertas/create?prospectId=X`

---

## Vista 4 — Lista de clientes (`/comercial/clientes`)

### Qué es

Lista de clientes asignados al comercial. Reutiliza `customerService.ts` (filtra por `salesperson_id` en backend automáticamente) y `useCustomerHistory.js` (ya calcula `daysSinceLastOrder`, `generalMetrics`, tendencias por producto).

### Patrón

Sigue el mismo patrón que la lista de prospectos: lista de cards + master-detail en desktop, full-screen en mobile.

### Componentes

* **Filtros:**`Input` de búsqueda por nombre + `Select` de país (usando `useCountriesList.ts` ya existente).
* **Cards:** ClienteCard — nombre, país, días sin pedido (calculado con `useCustomerHistory`), importe último pedido.
* **Alerta visual:** card con borde o fondo `border-destructive/40` si `daysSinceLastOrder > 30`.
* **Click en card:** panel de detalle a la derecha (desktop) o full-screen (mobile).

### Ficha de cliente (extensión)

La ficha de cliente existente vive en `/admin/customers/[id]` y gestiona admin. Para el comercial se construye una **ficha propia simplificada** en `/comercial/clientes/[id]`, con el mismo patrón de `Tabs`:

* **Tab "Datos"** — datos del cliente (solo lectura para el comercial, que no puede editar según Policy)
* **Tab "Pedidos"** — historial de pedidos (usa `useCustomerHistory.js` ya existente con sus filtros de período, tendencias, métricas generales)
* **Tab "Interacciones"** — historial CRM igual que en la ficha de prospecto
* **Tab "Ofertas"** — ofertas enviadas a este cliente

---

## Vista 5 — Ofertas (`/comercial/ofertas`)

### Qué es

Lista de todas las ofertas del comercial.

### Componentes

* **Filtros:**`Tabs` por estado (Todos / Borrador / Enviada / Aceptada / Rechazada / Expirada) + `Input` de búsqueda por empresa (mismo patrón que prospectos).
* **Cards:** OfertaCard — empresa, badge de estado, fecha envío, válida hasta, importe estimado, canal de envío.
* **Botón "Nueva oferta"** en cabecera (variant `default`).
* **Click en card (desktop):** panel de detalle a la derecha. **Mobile:** full-screen.

### Ficha de oferta (`/comercial/ofertas/[id]`)

Patrón igual que ficha de prospecto: `Card` + `Tabs` en desktop / section-list en mobile.

**Tab "Oferta"**

* Empresa destinataria (prospecto o cliente) + badge de estado
* Condiciones: incoterm (usa `useIncotermsList.ts`), forma de pago (usa `usePaymentTermsList.ts`), moneda, válida hasta, notas
* Editor de líneas (solo editable en estado `draft`): tabla con columnas Producto (combobox contra catálogo, igual que en creación de pedidos), Descripción libre, Cantidad, Unidad, Precio unitario, Impuesto, Cajas (boxes — opcional en oferta, se puede dejar vacío). Usa `useOrderFormOptions.js` para opciones de productos.

> **Sobre `boxes` en la oferta:** el campo es opcional al crear la oferta draft — el comercial puede no saber aún el número exacto de cajas. Al generar el pedido desde la oferta aceptada, si `boxes` estaba vacío el comercial debe completarlo en ese momento, ya que `StoreOrderRequest` lo requiere para crear el pedido. El formulario de creación de pedido desde oferta debe marcar `boxes` como obligatorio en ese paso.

* En estado `draft`: botón "Línea de producto" para añadir
* Líneas en `sent`/`accepted`/`rejected`/`expired`: solo lectura

**Tab "Acciones"** (o botones visibles en cabecera según estado)

* `draft` → botón "Enviar oferta" → abre `Dialog` (no Sheet) con tres opciones
* `sent` → botones "Aceptar" / "Rechazar" ("Rechazar" abre `AlertDialog` con campo de motivo)
* `accepted` → botón "Crear pedido desde esta oferta"

**Tab "Historial"**

* Registro de cambios de estado con fechas y usuario

### Modal de envío (Dialog, no Sheet)

Usa `Dialog` + `DialogContent` (shadcn, ya existe) — más adecuado que `Sheet` para un modal de acción puntual:

* **Email** — `Input` de email (precargado del contacto primario), `Input` de asunto, botón enviar
* **Descargar PDF** — botón de descarga directa
* **Copiar texto WhatsApp** — `Textarea` con mensaje formateado (readonly), botón "Copiar"

Las tres opciones como tres secciones verticales separadas con `Separator` dentro del mismo Dialog.

---

## Vista 6 — Mis pedidos (`/comercial/pedidos`)

### Qué es

Order Manager existente reutilizado, restringido para el rol comercial. **No se construye una vista nueva.**

### Patrón real del Order Manager (`src/components/Admin/OrdersManager/index.js`)

* **Desktop:** layout de dos paneles: `OrdersList` (columna izquierda, min-width 360px) + detalle del pedido (columna derecha, flex-1)
* **Mobile:** o lista O detalle — nunca los dos a la vez. Toggle entre vistas.
* `OrdersList` usa `Tabs` (categorías: Todos, En producción, Terminados, Hoy, Mañana) + `InputGroup` de búsqueda + `ScrollArea` con `OrderCard`
* El `OrderCard` muestra: cliente, ID pedido, fecha de carga, nº cajas, badge de estado con dot de color
* `OrderCard` NO tiene menú de tres puntos — click en el card abre el detalle

### Diferencias para el rol comercial

* Solo muestra pedidos del comercial autenticado (backend ya filtra por `salesperson_id`)
* Botón "Nuevo pedido" visible y funcional
* En el detalle del pedido: ocultar los botones de edición y eliminación (condicional por rol usando `session.user.role`). El backend ya deniega vía Policy — es solo cuestión de no renderizar los controles.
* Si el pedido viene de una oferta aceptada: el vínculo vive en `offers.order_id` (no en `orders.offer_id` — la FK está en la tabla `offers`). Para mostrar el badge "Desde oferta" en el `OrderCard`, el frontend consulta si existe una `offer` con `order_id = this.order.id`. Link en el detalle apunta a `/comercial/ofertas/[offer.id]`.

---

## Componente transversal — QuickInteractionModal

Modal ligero accesible desde cualquier ficha (prospecto o cliente) y desde el `DropdownMenu` de las listas.

### Implementación real: `Dialog`, no `Sheet`

Usa `Dialog` + `DialogContent` de shadcn. Es un modal central (no drawer lateral), más adecuado para una acción rápida de registro.

### Campos (en orden visual)

1. **Tipo** — grupo de botones tipo toggle. Se implementa con `Button variant="outline"` con estado activo (`bg-primary text-primary-foreground`) usando un estado local. El componente `Toggle` de shadcn (en `src/components/ui/toggle.jsx`) también es válido. **No existe un componente de "chips" dedicado en el sistema** — este es el patrón más cercano.
2. **Fecha** — `DatePicker` (ya existe en `src/components/ui/datePicker.jsx`), por defecto hoy.
3. **Resumen** — `Textarea` (shadcn), 3 filas, placeholder orientativo. Sin límite estricto.
4. **Resultado** — mismo patrón de toggle buttons que el tipo: Interesado / Pendiente / Sin respuesta / No interesa.
5. **Próxima acción** — `Input` de texto libre + `DatePicker`. Ambos opcionales. Solo visibles si el resultado no es "No interesa".

**Botón de guardar** (`Button variant="default"`) al pie del `DialogFooter`. Solo Tipo y Fecha son obligatorios.

Al guardar → optimistic update en el historial de interacciones. Si `next_action_at` se introdujo, el campo del prospecto/cliente se actualiza en la respuesta del backend.

---

## Componente transversal — ProspectoForm

Formulario de creación/edición de prospecto.

### Corrección: no reutiliza `CreateCustomerQuickForm`

`CreateCustomerQuickForm` (`src/components/Comercial/Autoventa/CreateCustomerQuickForm/index.js`) es un formulario de **un solo campo** ("Nombre del cliente") en un `Sheet`. El `ProspectoForm` es un formulario complejo independiente. Comparten las mismas primitivas de UI (`Input`, `Label`, `Select`, `Textarea` de shadcn) pero no hay reutilización directa de componentes.

### Implementación: `Sheet` (panel lateral)

Sigue el patrón de `PositionSlideover` (`src/components/Admin/Stores/StoresManager/Store/PositionSlideover/index.js`):

* `Sheet` + `SheetContent` (desktop: panel lateral derecho de \~600px; mobile: drawer desde abajo)
* `SheetHeader` con título y descripción
* `ScrollArea` con el formulario dentro
* Botones de guardar/cancelar en `SheetFooter`

### Campos (en orden visual)

1. **Nombre de empresa** (`Input`, obligatorio, autofocus)
2. **País** (`Select` contra catálogo via `useCountriesList.ts` — mismo patrón que en el resto del ERP)
3. **Especie de interés** — multi-selección con toggle buttons (mismo patrón que QuickInteractionModal) o `Checkbox` por especie en lista compacta. **No hay componente de chips multi-select en el sistema**.
4. **Origen** (`Select`: Conxemar / Llamada directa / Referido / Web / Otro)
5. **Notas** (`Textarea` libre, placeholder: "formato, calibre, mercado objetivo…")
6. **Contacto principal** — subsección con `Label` "Contacto principal" y cuatro `Input` en grid 2 columnas: Nombre, Cargo, Teléfono, Email
7. **Próxima acción** — `DatePicker` + `Input` de texto. Opcionales.

Solo el nombre de empresa es obligatorio.

---

## Patrones de UI reutilizados del ERP existente

> Tabla corregida con los patrones reales cotejados contra el código.

| Patrón                                                           | Dónde existe ya (archivo real)                                                | Se reutiliza en                                                             | Notas de corrección                                                                                        |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------ | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Lista de cards + master-detail (desktop) / full-screen (mobile)   | `OrdersManager/index.js`•`OrdersList/index.js`                            | Listas de prospectos, clientes, ofertas                                     | ✅ Patrón correcto para vistas CRM ricas                                                                   |
| Tabla TanStack + shadcn `Table`con accordion en mobile          | `Entity/EntityClient/EntityTable/`                                           | No recomendado para vistas CRM con fichas ricas — usar el patrón de cards | ⚠️ Solo si la vista es puramente tabular y simple                                                         |
| Ficha con `Tabs`por secciones (desktop) + section-list (mobile) | `OrdersManager/Order/index.js`                                               | Ficha de prospecto, ficha de cliente, ficha de oferta                       | ✅ Corrige el error anterior: NO es "dos columnas"                                                          |
| Panel lateral `Sheet`(derecha desktop / bottom mobile)          | `PositionSlideover/index.js`,`CreateCustomerQuickForm`                     | ProspectoForm, ContactForm, edición inline                                 | ✅                                                                                                          |
| Badge de estado con dot de color (inline-flex manual)             | `OrderCard/index.js`                                                         | Estado de prospecto, estado de oferta                                       | ✅ Patrón real:`bg-orange-500/15 text-orange-700`, NO usa `Badge`con variants                          |
| Componente `Badge`shadcn con variants                           | `SupplierLiquidationDetail.tsx`,`PositionSlideover`                        | Tipo de interacción, etiquetas secundarias                                 | ✅ Para badges secundarios, no para estado principal                                                        |
| `Dialog`para acciones puntuales                                 | `src/components/ui/dialog.jsx`                                               | QuickInteractionModal, Modal de envío de oferta                            | ✅ Corrige el término "modal ligero": se llama `Dialog`en shadcn                                         |
| `AlertDialog`para confirmaciones destructivas                   | `src/components/ui/alert-dialog.jsx`                                         | Descartar prospecto, rechazar oferta                                        | ✅                                                                                                          |
| Toggle buttons para selección exclusiva                          | `src/components/ui/toggle.jsx`                                               | Tipo de interacción, resultado, especie de interés                        | ✅ Corrige "chips": no existe componente chip — se usan `Toggle`o `Button`con estado activo            |
| `DropdownMenu`para menú de tres puntos                         | `PositionSlideover/index.js`(en uso),`src/components/ui/dropdown-menu.jsx` | Menú de acciones en ProspectoCard                                          | ✅ El componente existe pero NO está en las filas de entity tables — hay que construirlo en ProspectoCard |
| `DatePicker`/`DateRangePicker`                                | `src/components/ui/datePicker.jsx`                                           | Próxima acción, filtros de fecha                                          | ✅                                                                                                          |
| Selector de productos con catálogo (`Command`/combobox)        | Formulario de pedidos                                                          | Editor de líneas de oferta                                                 | ✅ Usa `command.jsx`para búsqueda con autocompletado                                                     |
| `ScrollArea`para contenido largo                                | `ComercialDashboard`,`OrdersManager`,`PositionSlideover`                 | Todas las vistas CRM                                                        | ✅ Patrón universal en el proyecto                                                                         |
| Estado vacío `Empty`                                           | `src/components/ui/empty.jsx`                                                | Lista de prospectos vacía, agenda sin recordatorios                        | ✅                                                                                                          |

---

## Completar una acción pendiente — flujo de resolución

Cuando el comercial tiene una acción pendiente en la agenda (`next_action_at` definido), necesita una forma de resolverla. Hay dos casos:

**Caso A — la resuelve registrando una interacción:** al abrir el `QuickInteractionModal` desde la agenda o desde la ficha del prospecto/cliente, puede dejar el campo `next_action_at` vacío. Esto indica que no hay nueva acción pendiente. El backend limpia el `next_action_at` del prospecto/interacción correspondiente y desaparece de la agenda.

**Caso B — la descarta sin registrar interacción:** en el `RemindersWidget` del dashboard, cada ítem tiene un botón secundario pequeño: "Aplazar" (abre un `DatePicker` para reprogramar) y "Descartar" (limpia el `next_action_at` sin registrar nada). Ambas opciones inline en la propia tarjeta de la agenda, sin abrir una página nueva.

**Regla:** una acción vencida (`next_action_at < hoy`) sigue apareciendo con badge rojo hasta que se resuelva por uno de los dos caminos. No desaparece sola.

---

## Primer uso — estado vacío del dashboard

Cuando el comercial accede por primera vez (sin prospectos, sin interacciones, sin alertas), el dashboard muestra:

* `RemindersWidget`: estado vacío con componente `Empty` y texto "No tienes acciones pendientes para hoy".
* `InactiveCustomersWidget`: si tiene clientes asignados pero ninguno inactivo, estado vacío positivo: "Todos tus clientes tienen actividad reciente". Si no tiene ningún cliente asignado aún, texto: "Aún no tienes clientes asignados".
* `ProspectsWithoutActivityWidget`: si no hay prospectos en absoluto, texto: "Aún no has registrado prospectos" + botón "Nuevo prospecto" que lleva a `/comercial/prospectos/create`.

El objetivo es que el dashboard vacío no sea una pantalla muerta — orienta al comercial hacia la primera acción útil.

---

## Formulario de oferta con prospecto precargado

Cuando se navega a `/comercial/ofertas/create?prospectId=X`, el formulario de oferta precarga automáticamente:

* **Destinatario**: nombre de la empresa del prospecto (no editable, muestra el prospecto vinculado).
* **Email de envío**: email del contacto primario del prospecto (`prospect_contacts.is_primary = true`). Editable.
* **Incoterm y forma de pago**: vacíos por defecto, el comercial los rellena si los conoce.
* **Líneas**: vacías. El comercial añade los productos manualmente.

Lo que **no** se precarga: precio, calibre, origen — esos datos están en `commercial_interest_notes` como texto libre, visible en un panel lateral de contexto mientras se crea la oferta ("Interés del prospecto": muestra `species_interest` + `commercial_interest_notes` en modo solo lectura). Esto evita que el comercial tenga que abrir otra pestaña para recordar qué busca el cliente.

---

## Estados vacíos por vista

Cada vista debe tener un estado vacío útil, no una pantalla muerta.

| Vista                     | Sin datos                               | CTA                      |
| ------------------------- | --------------------------------------- | ------------------------ |
| `/comercial/prospectos` | "Aún no tienes prospectos registrados" | Botón "Nuevo prospecto" |
| `/comercial/clientes`   | "Aún no tienes clientes asignados"     | Sin CTA — informativo   |
| `/comercial/ofertas`    | "Aún no has creado ninguna oferta"     | Botón "Nueva oferta"    |
| `/comercial/pedidos`    | "Aún no tienes pedidos"                | Sin CTA — solo lectura  |

Todos usan el componente `Empty` existente en `src/components/ui/empty.jsx`.

---

## Priorización UI — MVP vs Fase 2

### MVP (implementar primero)

Lo mínimo que hace útil el CRM desde el primer día:

* Dashboard con `RemindersWidget` + `InactiveCustomersWidget` + `ProspectsWithoutActivityWidget`
* `/comercial/prospectos` — lista + crear + ficha básica (tab Datos + tab Interacciones)
* `QuickInteractionModal` — el componente más crítico para la adopción
* `/comercial/ofertas` — crear oferta con líneas + tres canales de envío
* `/comercial/pedidos` — Order Manager reutilizado, controles de edición ocultos
* Estados vacíos en todas las vistas

### Fase 2 (una vez validado el uso real)

* Tab Historial en ficha de oferta (registro de cambios de estado)
* Panel de contexto del prospecto al crear oferta (`commercial_interest_notes` visible lateralmente)
* Master-detail más fino en desktop (sincronización de scroll, sticky header)
* Refinamiento mobile: `useHideBottomNav` en todas las fichas, transiciones de sección
* `/comercial/clientes` con tab Pedidos e historial completo
* Vista Kanban de prospectos como alternativa a la lista

---

## Lo que NO se diseña en esta versión

* Sin vista Kanban de prospectos por estado (puede añadirse como vista alternativa después).
* Sin calendario propio (las fechas de acción viven en el dashboard).
* Sin notificaciones push ni emails automáticos al comercial.
* Sin diseño específico para mobile en esta fase (el sistema actual es responsive pero no mobile-first).

---

> *Documento creado en sesión con Claude — Marzo 2026. Revisado y corregido contra código real del frontend — Marzo 2026.*
>
