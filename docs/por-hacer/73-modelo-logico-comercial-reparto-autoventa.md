# Modelo lógico del flujo Comercial, Reparto y Autoventa

**Estado**: Documento de contexto funcional — revisado con auditoría de código
**Última actualización**: 2026-03-21

---

## 1. Objetivo

Dejar por escrito el problema de negocio detectado en torno al uso actual del rol `comercial` y proponer un encaje futuro más sólido desde el punto de vista de la **lógica de negocio**, los **actores**, las **responsabilidades** y los **flujos funcionales**.

Este documento parte de la lógica de negocio y los actores, y evoluciona hacia una propuesta técnica concreta para el backend. Las secciones 2–15 fijan el marco conceptual; las secciones 16–28 aterrizan la implementación técnica con entidades, migraciones, endpoints y plan de PRs; la sección 29 fija lo que no debe cambiar.

### 1.1. Resumen ejecutivo

La idea central de este documento es simple:

- hoy el espacio `comercial` mezcla **CRM relacional** y **operativa de reparto/autoventa**
- negocio ya está pidiendo distinguir dos actores distintos:
  - **comercial CRM**
  - **repartidor/autoventa**
- ambos pueden trabajar sobre el mismo cliente, pero con responsabilidades diferentes
- para que esto escale bien, hay que separar:
  - **ownership comercial**
  - **acceso operativo**
- además, la futura lógica de **rutas** debe servir para unir planificación comercial y ejecución en calle sin convertir al repartidor en un usuario administrativo

En una frase:

**el comercial gestiona la relación y planifica; el repartidor ejecuta en ruta y vende de forma oportunista; el sistema debe permitir esa colaboración sin mezclar significados.**

| Tema | Idea clave |
|------|------------|
| Actor comercial | Lleva CRM, relación, cartera, ofertas y planificación |
| Actor repartidor/autoventa | Ejecuta entregas, trabaja en ruta y abre ventas oportunistas |
| Cliente | Puede existir sin owner comercial y con acceso operativo |
| Pedido | Debe separar previsión, planificación y ejecución real |
| Ruta | Organiza la actividad de campo sin convertir al repartidor en administrativo |

### 1.2. Decisiones de negocio ya validadas en este documento

Estas son las decisiones que ya han quedado fijadas durante la discusión:

- el repartidor/autoventa es un actor distinto del comercial CRM
- un cliente puede existir **sin owner comercial**
- un cliente creado en autoventa no entra automáticamente en CRM
- el repartidor solo puede operar sobre:
  - clientes habilitados operativamente
  - clientes nuevos creados por él en autoventa
- esos clientes nuevos nacen:
  - sin owner comercial
  - con ejecutor operativo definido
- el repartidor no debe ver CRM completo ni ficha comercial rica del cliente
- un pedido prefijado puede servir como previsión, pero su contenido real puede ajustarse durante la ejecución
- las rutas deben existir como capa de planificación y ejecución
- la UI de rutas debe ser:
  - ligera para el repartidor
  - compatible con móvil
  - integrada con `shadcn/ui`
  - construida con stack sin coste

| Decisión | Estado validado |
|----------|-----------------|
| El repartidor/autoventa es un actor distinto del comercial CRM | Sí |
| Un cliente puede existir sin owner comercial | Sí |
| Un cliente creado en autoventa entra automáticamente en CRM | No |
| El repartidor puede operar sobre cualquier cliente del sistema | No |
| El repartidor puede operar sobre clientes habilitados operativamente | Sí |
| El repartidor puede crear clientes nuevos en autoventa | Sí |
| Los clientes creados por autoventa nacen con owner comercial | No |
| Los clientes creados por autoventa nacen con ejecutor operativo definido | Sí |
| El repartidor debe ver CRM completo | No |
| Deben existir rutas como capa operativa | Sí |
| La UI de rutas debe ser móvil-first en ejecución | Sí |
| El stack de rutas debe evitar coste | Sí |

### 1.3. Cómo leer este documento

| Si quieres entender... | Lee sobre todo... |
|------------------------|-------------------|
| El problema de negocio | Secciones 2, 3, 4 |
| El estado técnico real del backend | Secciones 3.1 y 3.2 |
| El modelo final de actores y entidades | Secciones 5, 6, 10 |
| Las rutas como capa operativa | Sección 8 |
| UI y frontend | Secciones 11, 12 |
| Análisis backend detallado | Secciones 13, 14 |
| Propuesta técnica de implementación | Sección 16 |
| Diseño de entidades (incluyendo `route_template_stops`) | Sección 16.3 |
| Flujo de gestión de acceso operativo a cliente | Sección 16.3.B |
| Estrategia de reporting por actor | Sección 16.11 |
| Plan de PRs y desglose | Secciones 17–26 |
| Auditoría de autorización del actor operativo (PR 2B) | Sección 17.5B |
| Ajuste fino y riesgos transversales | Secciones 27, 28 |
| Estrategia de testing con setup multi-tenant | Sección 30 |
| Lo que no cambia al implementar | Sección 31 |

---

## 2. Problema inicial

En el flujo actual, el espacio funcional del rol `comercial` agrupa realidades de trabajo distintas bajo un mismo paraguas:

- gestión comercial relacional y de cartera
- seguimiento CRM
- agenda comercial
- generación y seguimiento de ofertas
- creación de pedidos
- autoventa en ruta
- ejecución de entregas sobre pedidos ya prefijados

Esto funciona como punto de partida, pero mezcla dos naturalezas operativas que no son equivalentes.

| Hoy se mezcla en `comercial` | Pero en realidad responde a |
|------------------------------|-----------------------------|
| CRM y cartera | Comercial relacional |
| Agenda e interacciones | Comercial relacional |
| Ofertas | Comercial relacional |
| Autoventa | Repartidor / distribuidor |
| Ejecución de pedidos prefijados | Repartidor / distribuidor |

### 2.1. Actor 1: comercial relacional

Este actor trabaja la cuenta desde una lógica comercial clásica:

- crea o capta prospectos
- hace seguimiento
- registra interacciones
- mantiene agenda
- prepara ofertas
- convierte oportunidades en pedidos
- cuida la relación comercial de fondo

Su foco es la **relación**, la **conversión** y la **gestión de cartera**.

| Actor | Foco | Núcleo funcional |
|-------|------|------------------|
| Comercial CRM | Relación y conversión | Prospectos, agenda, ofertas, cartera, pedidos comerciales |

### 2.2. Actor 2: repartidor o distribuidor propio

Este actor trabaja en movilidad y en ejecución:

- puede tener pedidos ya prefijados que debe suministrar
- puede pasar por clientes y abrir una venta directa en el momento
- puede crear clientes de manera oportunista durante la autoventa
- puede operar sobre clientes que no necesariamente gestiona a nivel CRM

Su foco es la **ruta**, la **entrega**, la **lectura de cajas reales** y la **venta oportunista en campo**.

| Actor | Foco | Núcleo funcional |
|-------|------|------------------|
| Repartidor / Autoventa | Ejecución y oportunidad en calle | Rutas, entregas, autoventa, clientes operativos |

### 2.3. Tensión del modelo actual

El problema no es solo de permisos o de interfaz. Es un problema de modelo conceptual.

Si ambos actores siguen viviendo bajo el mismo rol lógico, empiezan a aparecer dudas estructurales:

- si un repartidor crea un cliente durante la autoventa, ¿ese cliente pasa a ser “suyo”?
- si un comercial lleva la cuenta, ¿cómo comparte ese cliente con un repartidor sin perder trazabilidad?
- si un pedido viene prefijado por un comercial, ¿quién es realmente el responsable del pedido: quien lo planifica o quien lo sirve?
- si una persona hace autoventa pero no lleva CRM, ¿por qué debe convivir con prospectos, agenda y ofertas como núcleo de su espacio?
- si el reporting se apoya en el mismo campo para “dueño comercial”, “creador del pedido”, “vendedor en ruta” y “ejecutor de entrega”, ¿cómo se evita confundir la realidad del negocio?

La conclusión es que el problema no se resuelve pensando en “un comercial más limitado”, sino en reconocer que existen **dos actores de negocio diferentes**.

---

## 3. Lectura del contexto actual

El contexto funcional deducido a partir del proyecto es el siguiente:

- el flujo principal del espacio comercial actual se comporta como un embudo tipo `prospecto -> interacción/agenda -> oferta -> pedido`
- la autoventa aparece como un flujo paralelo de venta rápida en campo
- el pedido es la entidad transaccional final
- el cliente es el nexo común entre CRM, oferta, pedido y autoventa

Desde esta lectura, la autoventa no es simplemente “otra pantalla del comercial”, sino un comportamiento operativo distinto que convive con el circuito comercial clásico.

| Flujo actual observado | Lectura funcional |
|------------------------|------------------|
| `prospecto -> interacción/agenda -> oferta -> pedido` | Circuito CRM/comercial clásico |
| autoventa | Venta directa en campo |
| pedido | Entidad transaccional central |
| cliente | Nexo entre CRM, pedido y autoventa |

### 3.1. El CRM ya está modelado y es maduro

Un punto importante que el análisis de código confirma: el ciclo CRM no es una aspiración futura, sino una realidad ya implementada. Las siguientes entidades existen, tienen migraciones, modelos, servicios y endpoints completos:

- `Prospect`: oportunidad comercial previa al cliente con estados `new → following → offer_sent → customer → discarded` y múltiples orígenes (conxemar, direct, referral, linkedin, google_maps, ai_sourced, etc.)
- `ProspectContact`: contactos asociados al prospecto, con soporte de contacto primario
- `CommercialInteraction`: interacción comercial sobre prospecto o cliente; tipos `call`, `email`, `whatsapp`, `visit`, `other`; con resultado y programación de próxima acción
- `AgendaAction`: acción futura programada con **encadenamiento explícito** (`source_interaction_id`, `completed_interaction_id`, `previous_action_id`); estados `pending`, `done`, `cancelled`, `reprogrammed`
- `Offer` + `OfferLine`: oferta dirigida a prospecto o cliente (mutualmente excluyentes por constraint DB), con estados `draft → sent → accepted/rejected/expired` y conversión directa a `Order` mediante `OfferService::createOrderFromAcceptedOffer()`

El flujo CRM completo es `Prospect → CommercialInteraction → AgendaAction → Offer → Order`, y la conversión `Prospect → Customer` está también implementada en `ProspectService::convertToCustomer()`.

**Esta capa es exactamente lo que debe preservarse intacta.** Las propuestas de este documento se apoyan en ella, no la reemplazan.

### 3.2. Estado técnico real del backend: puntos de atención antes de implementar

Antes de abordar cualquier implementación del modelo propuesto, conviene partir de la realidad exacta del código, no de suposiciones.

#### `customers.salesperson_id` es NOT NULL — no es opcional

La migración original `2023_12_19_152335` define `salesperson_id` como `foreignId('salesperson_id')->constrained()`, es decir, **NOT NULL con FK restrictiva**. La migración `2026_02_18_170000`, que se creó precisamente para facilitar el alta rápida de clientes en autoventa, hizo nullable vat_number, payment_term_id, country_id, billing_address, shipping_address, emails, contact_info y transport_id, **pero no `salesperson_id`**.

Consecuencia directa: hoy es imposible crear un cliente sin asignarle un comercial. Toda la propuesta de "cliente sin owner comercial" requiere como primer paso técnico una migración que lo haga nullable, más la actualización coordinada de `CustomerPolicy`, `CustomerListService` y las validaciones de los controladores que esperan ese campo.

#### La autoventa crea stock real, no solo un pedido

`AutoventaStoreService` genera en una única transacción: `Order` con `order_type='autoventa'`, `OrderPlannedProductDetail` por ítem, un `Pallet` en estado `shipped` (expedido directamente, nunca en almacén), una `Box` y `PalletBox` por cada caja, y una entrada en el timeline del palet con `fromAutoventa=true`. El servicio también valida que exista al menos un `Tax` configurado.

Separar el actor repartidor de la autoventa no es solo un cambio de permisos o de `salesperson_id`. Implica decidir si el repartidor sigue creando este mismo artefacto de stock o si hay un flujo de ejecución diferente. Tocar autoventa afecta pedidos, stock, logística física y reporting simultáneamente.

#### El comercial no puede editar ni borrar clientes

`CustomerPolicy` restringe `update` y `delete` a roles distintos de `comercial`. El comercial actual puede crear y ver sus clientes, pero **no puede modificarlos**. Esto afecta a la propuesta: la futura acción de "asignar un owner comercial a un cliente nacido en autoventa" no puede ejecutarla el propio comercial desde la interfaz actual. Requeriría un nuevo endpoint con autorización específica, o ampliar explícitamente el permiso de forma controlada.

#### `AutoventaStoreService` requiere un `Salesperson` vinculado al usuario

La línea `$salespersonId = $user->salesperson?->id; if (!$salespersonId) throw ValidationException` hace que hoy sea imposible crear una autoventa sin ser un usuario con `role='comercial'` y `Salesperson` asociado. El futuro repartidor no es un comercial, y no tendría `Salesperson`. El servicio necesita adaptarse para admitir otro tipo de actor como creador.

#### No existe ninguna entidad de ruta

Confirmado revisando todo el código: no hay modelos, migraciones, controladores ni endpoints para `Route`, `RouteTemplate`, `RouteStop`, `OrderExecution` ni nada conceptualmente equivalente. La capa de rutas parte de cero.

---

## 4. Replanteamiento conceptual

La propuesta de fondo es separar con claridad:

- **propiedad comercial de la cuenta**
- **capacidad operativa para servir o vender sobre esa cuenta**

La clave no es duplicar clientes ni fragmentar el dominio, sino distinguir mejor las relaciones entre actores y entidades.

| Concepto | No debería significar | Sí debería significar |
|----------|------------------------|-----------------------|
| Cliente “de alguien” | Propiedad exclusiva rígida | Entidad compartida con relaciones distintas |
| Autoventa | CRM simplificado | Alta/venta operativa en contexto de ruta |
| Pedido | Solo documento comercial | Unidad transaccional con planificación y ejecución |

### 4.1. El cliente no debe depender de un único significado de propiedad

No conviene seguir pensando el cliente con una lógica simple del tipo:

- “este cliente pertenece al comercial X”

Ese modelo se queda corto en cuanto existe colaboración entre comercial y reparto.

En su lugar, el cliente debe entenderse como una entidad compartida que puede tener al menos dos dimensiones distintas:

- un **responsable comercial principal**
- uno o varios **actores operativos autorizados**

Así se evita que la autoría de una autoventa o la creación puntual de un cliente en ruta altere artificialmente la propiedad comercial de la cuenta.

### 4.2. La autoventa no debe redefinir la relación comercial

Si un repartidor crea un cliente durante una venta en campo, eso no debería implicar automáticamente que:

- sea el dueño comercial del cliente
- asuma su seguimiento CRM
- pase a gestionar prospectos, agenda u ofertas sobre esa cuenta

Lo correcto es entender esa acción como una **captación operativa** o una **alta en contexto de ruta**, no necesariamente como una asignación comercial estructural.

### 4.3. El pedido necesita distinguir planificación y ejecución

Un mismo pedido puede involucrar varios tipos de responsabilidad:

- quién lo originó
- quién es el responsable comercial de la venta
- quién lo sirve físicamente
- si nace de un flujo planificado o de una venta espontánea

Si estos significados se mezclan, el sistema se vuelve frágil para reporting, trazabilidad y permisos.

---

## 5. Propuesta de encaje futuro

### 5.1. Separar dos espacios lógicos

La propuesta más sana a medio y largo plazo es distinguir entre:

#### A. Comercial CRM

Actor orientado a relación y conversión.

Responsabilidades principales:

- prospectos
- seguimiento comercial
- agenda
- ofertas
- reactivación de clientes
- cartera
- pedidos desde una óptica comercial

#### B. Reparto / Autoventa

Actor orientado a ejecución y venta en ruta.

Responsabilidades principales:

- pedidos asignados para servir
- entregas
- lectura de cajas
- autoventa
- alta rápida de clientes en contexto de ruta
- historial operativo propio

La clave no es que uno sustituya al otro, sino que ambos colaboren sobre algunas entidades compartidas.

| Espacio lógico | Actor principal | Qué hace | Qué no debería absorber |
|----------------|-----------------|----------|--------------------------|
| Comercial CRM | Comercial | Prospectos, agenda, ofertas, cartera, pedidos comerciales | Operativa de calle y ejecución de reparto |
| Reparto / Autoventa | Repartidor | Ruta, entrega, autoventa, alta operativa | CRM relacional completo |

### 5.2. Mantener el cliente como entidad compartida

No se recomienda duplicar clientes según el actor que interactúe con ellos.

El cliente debe seguir siendo único, pero con una lógica de relación más rica:

- puede tener un owner comercial principal
- puede ser accesible por uno o varios actores operativos
- puede acumular actividad comercial y operativa sobre la misma cuenta

Esto permite que:

- un comercial mantenga la cuenta
- un repartidor entregue pedidos o haga autoventa sobre ella
- el sistema conserve una visión completa del historial

| Cliente | Puede tener |
|---------|-------------|
| Relación comercial | Owner comercial principal |
| Relación operativa | Acceso operativo para uno o varios actores |
| Historial | Actividad comercial y operativa sobre la misma cuenta |

### 5.3. Acceso operativo del repartidor

El repartidor o distribuidor propio no debería poder operar sobre cualquier cliente visible del sistema.

Su universo funcional debe limitarse a:

- clientes que tenga **habilitados operativamente**
- clientes nuevos que él mismo cree en el contexto de una autoventa

Esto permite mantener un modelo acotado y escalable:

- el repartidor no invade la cartera comercial global
- el acceso operativo no depende de la propiedad comercial
- el cliente puede existir para operación aunque todavía no tenga owner comercial

| El repartidor puede operar sobre... | Sí / No |
|------------------------------------|---------|
| Clientes habilitados operativamente | Sí |
| Clientes nuevos creados por él en autoventa | Sí |
| Cualquier cliente visible del sistema | No |

### 5.4. Alta de clientes en autoventa

Cuando el repartidor crea un cliente desde una autoventa, ese cliente debe entenderse como una **alta operativa**.

Su estado lógico inicial sería:

- **sin owner comercial**
- **con ejecutor operativo definido** (el repartidor que lo ha creado)

Ese cliente:

- puede quedarse solo como cliente operativo
- puede ser visible para administración
- puede, más adelante, ser asignado a un owner comercial si negocio decide trabajarlo en CRM

No debe asumirse que toda alta desde autoventa entra automáticamente en un circuito comercial formal.

| Cliente creado en autoventa | Estado inicial recomendado |
|-----------------------------|----------------------------|
| Owner comercial | No definido |
| Ejecutor operativo | Definido |
| Uso operativo | Sí |
| Entrada automática en CRM | No |

### 5.5. Diferenciar ownership de acceso operativo

Conviene separar conceptualmente:

- **quién gestiona la relación**
- **quién puede operar en campo**

Esto resuelve varios problemas de escalabilidad:

- clientes compartidos sin conflictos
- repartidores que venden a clientes no captados por ellos
- comerciales que planifican pedidos que ejecutan otras personas
- trazabilidad de quién creó, quién planificó y quién ejecutó

---

## 6. Nuevo marco mental del dominio

La propuesta final se apoya en este marco abstracto:

### 6.1. El comercial CRM

Es responsable de:

- la cuenta
- la relación
- la oportunidad
- la negociación
- la continuidad comercial

### 6.2. El repartidor/autoventa

Es responsable de:

- la ejecución física
- la ruta
- la entrega
- la venta espontánea en campo
- la materialización real mediante cajas leídas o servidas

No es, por defecto, responsable de:

- prospectos
- agenda comercial
- seguimiento CRM
- ofertas
- ficha comercial completa del cliente

| Actor | Responsable de | No responsable de |
|-------|----------------|-------------------|
| Comercial CRM | Cuenta, relación, oportunidad, negociación, continuidad comercial | Ejecución de ruta |
| Repartidor/autoventa | Ejecución física, ruta, entrega, venta espontánea en campo | CRM, prospectos, agenda, ofertas, ficha comercial rica |

### 6.3. El cliente

Es una entidad común sobre la que pueden coexistir:

- una relación comercial principal
- una relación operativa de servicio o venta en ruta

Además, un cliente puede existir en uno de estos estados lógicos:

- cliente con owner comercial y acceso operativo para uno o varios actores permitidos
- cliente sin owner comercial pero con acceso operativo
- cliente nacido desde autoventa y pendiente de eventual asignación comercial

| Estado del cliente | Descripción |
|--------------------|-------------|
| Con owner comercial y acceso operativo | Cliente maduro compartido |
| Sin owner comercial pero con acceso operativo | Cliente operativo |
| Nacido desde autoventa | Cliente pendiente de eventual asignación comercial |

### 6.4. El pedido

Debe soportar, al menos a nivel conceptual, varias lecturas:

- pedido planificado
- pedido originado desde oferta
- pedido servido por un agente operativo
- autoventa como venta directa en campo

No se trata de multiplicar entidades necesariamente, sino de no reducir el pedido a un único significado organizativo.

| El pedido puede leerse como... |
|--------------------------------|
| pedido planificado |
| pedido originado desde oferta |
| pedido servido por un agente operativo |
| autoventa como venta directa en campo |

### 6.5. Pedido prefijado y ejecución real

En el flujo validado para el repartidor, el pedido prefijado no debe entenderse como un documento completamente cerrado e intocable.

El repartidor:

- recibe una previsión de lo que debe suministrar
- se guía por esa previsión
- pero puede crear o rehacer libremente el **contenido real** del pedido que termina sirviendo

Al mismo tiempo, su capacidad de modificación no abarca toda la capa comercial del pedido.

Si durante la ejecución aparecen diferencias que afectan a aspectos no editables por él, como por ejemplo:

- datos del cliente
- fecha
- otras condiciones no operativas

la resolución lógica no es forzar todo dentro del pedido prefijado, sino:

- comunicar la desviación al comercial responsable
- y, si aplica según el caso de uso, generar una autoventa separada

Esto no debe entenderse como una automatización rígida del sistema, sino como el marco de uso esperado para no mezclar planificación comercial y ejecución operativa.

> **Nota técnica**: la `OrderPolicy` actual impide que el rol `comercial` modifique pedidos (`update` → solo roles distintos de comercial). Al diseñar los permisos del repartidor habrá que decidir explícitamente qué partes del pedido puede ajustar durante la ejecución y con qué mecanismo se autoriza esa acción.

| Pedido prefijado | Pedido ejecutado |
|------------------|------------------|
| sirve como previsión | refleja lo servido realmente |
| lo planifica negocio/comercial | lo ajusta el ejecutor operativo |
| no debe absorber toda la casuística de campo | puede convivir con incidencias o autoventas separadas |

---

## 7. Riesgos de no separar estos conceptos

Si el sistema sigue tratando todo esto como variantes del mismo rol lógico, es previsible que aparezcan estos problemas:

- confusión entre actividad comercial y actividad operativa
- dashboards y reporting poco fiables
- ownership de clientes mal definido
- permisos sobredimensionados para perfiles de reparto
- CRM contaminado por usuarios que realmente no lo trabajan
- dificultad para compartir clientes entre actores distintos
- complejidad creciente cuando haya más de un repartidor por zona o por cartera

El mayor riesgo no es de UX, sino de **modelo de negocio mal expresado**.

| Riesgo | Efecto |
|--------|--------|
| Mezclar actividad comercial y operativa | Reportes y permisos incoherentes |
| No separar ownership y acceso operativo | Confusión sobre clientes |
| Sobredimensionar al repartidor | UX pesada y uso pobre en calle |
| No modelar bien la colaboración | Escalabilidad limitada |

---

## 8. Lógica de rutas

La evolución natural de este modelo no se entiende solo desde clientes, pedidos y autoventas. Para que la colaboración entre comercial y repartidor sea realmente operativa, conviene introducir la noción de **ruta** como pieza de planificación y ejecución.

La ruta no debe entenderse únicamente como un recorrido geográfico, sino como una **unidad operativa de trabajo** que organiza la actividad de campo del repartidor para un día o para un patrón repetible.

> **Requisito arquitectónico**: todos los modelos de ruta (`RouteTemplate`, `Route`, `RouteStop`) deben usar el trait `UsesTenantConnection` y pertenecer a la base de datos del tenant activo. Las validaciones de paradas que referencien clientes, prospectos o usuarios deben usar la notación `exists:tenant.customers,id` para garantizar el aislamiento multi-tenant.

| La ruta sirve para unir... |
|----------------------------|
| pedidos prefijados |
| clientes donde se recomienda parar |
| oportunidades de autoventa |
| zonas o puntos geográficos de interés |

### 8.1. Qué resuelve la ruta

La ruta permite unir en un mismo marco:

- pedidos prefijados que deben entregarse
- clientes donde se recomienda parar
- oportunidades de autoventa
- zonas o puntos geográficos donde conviene intentar actividad comercial ligera

Sin esta capa, la operativa de reparto y autoventa queda demasiado dispersa entre pedidos sueltos y decisiones improvisadas en calle.

### 8.2. Plantilla de ruta y ruta programada

Conviene distinguir dos niveles:

#### A. Plantilla de ruta

Representa un patrón reutilizable.

Ejemplos conceptuales:

- ruta habitual de los martes
- ruta de costa
- ruta interior
- ruta de autoventa de una zona

No representa un día real todavía. Representa una estructura base repetible o reutilizable.

#### B. Ruta programada

Representa una instancia concreta para una fecha determinada.

Puede:

- basarse en una plantilla
- crearse de forma puntual
- modificarse para adaptarse a una jornada concreta

Este nivel permite que una ruta habitual siga existiendo como patrón sin impedir que cada día real se ajuste según necesidad.

| Concepto | Qué representa |
|----------|----------------|
| Plantilla de ruta | Patrón reutilizable |
| Ruta programada | Instancia concreta para una fecha |

### 8.3. Quién crea la ruta y quién la ejecuta

La lógica validada hasta ahora encaja mejor con este reparto de responsabilidad:

- el **comercial** diseña normalmente la ruta o la deja preparada
- el **repartidor** la ejecuta en campo

Eso no implica rigidez absoluta. El repartidor puede adaptarla durante la jornada, pero no se convierte por ello en el planificador estructural del sistema.

El marco correcto es:

- el comercial **planifica**
- el repartidor **ejecuta y adapta**

### 8.4. Tipos de parada dentro de una ruta

Las paradas no deberían modelarse solo como una lista ordenada de clientes. Cada parada necesita una **intención funcional**.

Como mínimo, una parada puede ser:

- **obligatoria**
- **sugerida**
- **de oportunidad / autoventa**

Esto ayuda a separar:

- compromisos de entrega reales
- visitas recomendadas
- oportunidades abiertas que el repartidor puede aprovechar si la jornada lo permite

| Tipo de parada | Significado |
|----------------|-------------|
| Obligatoria | Compromiso claro de visita o entrega |
| Sugerida | Recomendación operativa |
| Oportunidad / autoventa | Visita abierta si la jornada lo permite |

### 8.5. Qué puede apuntar una parada

Una parada no tiene por qué referirse siempre a un cliente ya consolidado.

Una parada puede apuntar a:

- un **cliente**
- un **prospecto**
- una **zona, pueblo, calle o punto geográfico**

Esto permite que la ruta soporte:

- entregas comprometidas
- visitas a oportunidades abiertas
- recorridos de exploración o mantenimiento comercial ligero

| Una parada puede apuntar a... |
|-------------------------------|
| Cliente |
| Prospecto |
| Zona / pueblo / calle / punto geográfico |

### 8.6. Relación entre ruta y pedido

La ruta no sustituye al pedido ni el pedido sustituye a la ruta.

Sus funciones son distintas:

- el **pedido** representa una necesidad comercial o transaccional
- la **ruta** representa una organización operativa de ejecución

Una ruta puede contener:

- pedidos prefijados
- paradas sin pedido
- oportunidades de autoventa

Y un pedido puede:

- estar vinculado a una ruta
- cambiar de ruta
- quedar fuera de una ruta concreta si negocio lo necesita

| Entidad | Función principal |
|---------|-------------------|
| Pedido | Necesidad comercial o transaccional |
| Ruta | Organización operativa de ejecución |

### 8.7. Flexibilidad durante la ejecución

La ruta no debe ser completamente rígida.

El repartidor debería poder:

- ejecutar las paradas previstas
- omitir alguna si no procede
- añadir paradas nuevas
- aprovechar oportunidades de autoventa no previstas inicialmente

Esto es importante para reflejar la realidad del trabajo de calle. La planificación existe, pero no puede asfixiar la adaptación operativa.

### 8.8. Registro ligero de lo ocurrido en una parada

Aunque es valioso registrar qué ha pasado en cada parada, no conviene trasladar al repartidor una carga administrativa excesiva.

El principio recomendado es:

- registrar poco
- registrar rápido
- registrar solo lo accionable
- favorecer acciones táctiles y cierres simples, pensados para móvil y contexto de calle

La parada no debe convertirse en un mini CRM ni en un formulario de oficina.

### 8.9. Estado y resultado de parada

Para mantener trazabilidad sin fricción, conviene distinguir conceptualmente dos capas:

#### A. Estado operativo

Sirve para saber si la parada se ha trabajado.

Ejemplos:

- pendiente
- realizada
- omitida

#### B. Resultado ligero

Sirve para indicar qué ocurrió, pero de forma muy simple.

Ejemplos:

- entrega realizada
- autoventa realizada
- sin venta
- cliente no quiso mercancía
- incidencia
- visita no realizada

La idea no es pedir texto largo, sino permitir un marcado rápido.

| Capa | Ejemplos |
|------|----------|
| Estado operativo | pendiente, realizada, omitida |
| Resultado ligero | entrega realizada, autoventa realizada, sin venta, incidencia |

### 8.10. Resultado explícito e inferido

Siempre que el sistema ya pueda deducir lo ocurrido, conviene evitar pedir intervención manual.

Por ejemplo:

- si en una parada se sirve un pedido prefijado, el sistema puede inferir una entrega realizada
- si desde una parada se genera una autoventa, el sistema puede inferir una autoventa realizada

El repartidor solo debería marcar manualmente aquello que no pueda inferirse automáticamente o que necesite una señal mínima para cerrar correctamente la ruta.

### 8.11. Relación entre ruta y actor comercial

La ruta puede servir también como puente suave entre la lógica comercial y la operativa sin mezclar sus responsabilidades.

Esto significa:

- el comercial puede preparar rutas con mezcla de compromiso y oportunidad
- el repartidor puede ejecutar y detectar ventas reales
- el resultado de la ruta puede ayudar a negocio a entender:
  - qué se entregó
  - qué oportunidades se aprovecharon
  - qué zonas o paradas no funcionaron

Todo ello sin convertir al repartidor en usuario CRM relacional.

---

## 9. Principios recomendados para la evolución futura

### 9.1. No usar una sola etiqueta para responsabilidades distintas

No conviene que un mismo campo o una misma noción de “comercial” absorba a la vez:

- owner de la cuenta
- creador del cliente
- creador del pedido
- actor que realiza la autoventa
- agente que sirve la entrega

Cada uno de esos significados responde a una capa distinta del flujo.

### 9.2. No convertir al repartidor en usuario CRM por defecto

Aunque use pantallas próximas al dominio comercial, su centro de gravedad no es el CRM sino la ejecución en campo.

### 9.3. No ligar el ownership del cliente al origen de alta

Que un actor haya creado un cliente no significa necesariamente que deba ser su responsable comercial permanente.

### 9.4. Mantener colaboración sin duplicidad

El objetivo no debe ser separar por completo a los actores, sino permitir que compartan cliente y operación sin pisarse ni distorsionar la lógica del dominio.

---

## 10. Propuesta final resumida

La idea final recomendada es la siguiente:

- mantener el dominio comercial actual como base del flujo relacional
- reconocer que la autoventa y la entrega en ruta pertenecen a un actor distinto
- separar lógicamente `comercial CRM` de `reparto/autoventa`
- conservar el cliente como entidad compartida
- distinguir entre ownership comercial y acceso operativo
- limitar la autoventa del repartidor a clientes habilitados operativamente y a clientes nuevos creados por él durante esa autoventa
- tratar la creación de clientes en autoventa como alta operativa, no como apropiación comercial automática
- preservar la trazabilidad de origen, planificación y ejecución de cada venta o pedido
- permitir que un pedido prefijado tenga un ejecutor concreto y que su contenido real pueda ajustarse durante la ejecución
- introducir la ruta como unidad operativa flexible que conecte pedidos prefijados, paradas sugeridas y oportunidades de autoventa
- distinguir entre plantilla de ruta y ruta programada para un día concreto
- registrar lo ocurrido en cada parada con la mínima fricción posible para un contexto móvil y de calle

En otras palabras:

**el comercial CRM gestiona la relación y planifica la actividad; el repartidor/autoventa ejecuta y vende en ruta dentro de su universo operativo; la ruta organiza esa ejecución sin confundir responsabilidades comerciales y operativas.**

| Resumen final | Definición |
|---------------|------------|
| Comercial CRM | Lleva relación, cartera y planificación |
| Repartidor/autoventa | Ejecuta y vende en ruta |
| Cliente | Compartido, con ownership y acceso operativo separados |
| Ruta | Capa operativa flexible |
| UI | Ligera, móvil-first para ejecución, sin coste |

---

## 11. Stack UI y diseño propuesto para rutas en frontend

> **Alcance**: Esta sección y la sección 12 son territorio del frontend Next.js. No afectan al modelo de dominio backend ni a las decisiones de modelado de la sección 16. Si el objetivo es implementar backend, puede saltarse a la sección 13.

La decisión actual para rutas en frontend queda mejor expresada como una estrategia en **dos fases**:

- **Fase 1**: creación avanzada de rutas dentro de la app con **Mapbox** y navegación externa parada a parada con **Google Maps o Waze**
- **Fase 2**: evolución futura hacia una experiencia de navegación integrada y propia

Esta estrategia permite resolver primero el verdadero problema de negocio inmediato:

- planificar bien
- buscar y seleccionar puntos con una UX potente
- construir rutas cómodamente
- ejecutar en calle sin tener que desarrollar desde el primer momento un sistema de navegación completo

### 11.1. Decisión de producto para rutas en frontend

La clave no es solo “mostrar un mapa”, sino ofrecer una interfaz suficientemente buena para:

- buscar direcciones
- ubicar clientes, prospectos o zonas
- añadir y ordenar paradas
- visualizar un recorrido
- asignar la ruta a un repartidor

Por eso, aunque la navegación final del repartidor pueda delegarse fuera de la app, la **configuración de rutas** sí requiere una capa geográfica más potente y usable.

| Necesidad | Solución recomendada |
|----------|----------------------|
| Configuración avanzada de rutas | Mapbox dentro de la app |
| Navegación real del repartidor en Fase 1 | Google Maps o Waze |
| Evolución futura | Navegación propia integrada en Fase 2 |

### 11.2. Stack recomendado para Fase 1

#### A. Base de arquitectura y UI

Se mantiene el stack ya presente en el proyecto:

- **Next.js App Router**
- **layouts actuales por segmento**
- **`shadcn/ui` nativo**
- **React Query**
- **React Hook Form**

La UI de rutas no debe introducir un sistema visual nuevo. Debe construirse como una extensión coherente del producto actual.

#### B. Stack geográfico principal

Para la Fase 1, la recomendación es:

- **Mapbox** como capa principal para:
  - mapa
  - buscador geográfico
  - selección de puntos
  - visualización de trazados
  - interacción avanzada de planificación

Esta elección se justifica porque el valor inmediato está en la **UX de creación de rutas**, no en la navegación turn-by-turn dentro de la app.

#### C. Librerías y piezas UI de apoyo

Además de Mapbox, se recomienda seguir usando:

- `@dnd-kit/core`
- `@dnd-kit/sortable`
- `supercluster` si más adelante hace falta agrupar puntos

Y como base visual:

- `Card`
- `Tabs`
- `Badge`
- `ScrollArea`
- `Dialog`
- `Sheet`
- `DropdownMenu`
- `Button`
- `Input`
- `Select`
- `DatePicker`

todo ello desde el ecosistema **`shadcn/ui` nativo**.

| Capa | Herramienta |
|------|-------------|
| Mapa y edición geográfica | Mapbox |
| Reordenación manual de paradas | dnd-kit |
| Agrupación de puntos | supercluster |
| Sistema visual | shadcn/ui |
| Datos y estado | React Query + React Hook Form |

### 11.3. Flujo funcional de Fase 1

La Fase 1 debe centrarse en dos superficies distintas:

- **planificación** para comercial
- **ejecución** para repartidor

#### A. Planificación

El comercial:

- crea una plantilla o una ruta programada
- busca y añade puntos sobre mapa
- selecciona clientes, prospectos o zonas
- ordena paradas
- distingue tipo de parada
- visualiza el recorrido
- asigna la ruta al repartidor

#### B. Ejecución

El repartidor:

- abre la ruta del día en la app
- ve la siguiente parada
- pulsa “Navegar”
- se abre Google Maps o Waze con la parada actual
- vuelve a la app tras la visita
- registra el resultado mínimo
- pasa a la siguiente parada

Esto evita construir navegación propia en la primera fase y reduce muchísimo la complejidad.

| Actor | Qué hace en Fase 1 |
|-------|--------------------|
| Comercial | Configura y planifica la ruta dentro de la app |
| Repartidor | Ejecuta la ruta dentro de la app pero navega fuera con Google Maps/Waze |

### 11.4. Encaje con los layouts actuales de la app

El proyecto ya trabaja con layouts por segmentos como:

- `/admin`
- `/operator`
- `/comercial`

La propuesta de rutas debe respetar esa organización.

#### A. Planificación de rutas

Encaja naturalmente en el espacio **`/comercial`** y debe sentirse como una extensión de la actividad de planificación comercial.

#### B. Ejecución de rutas

Aunque en una primera fase pueda convivir todavía bajo el universo actual del rol comercial, la UI debe diseñarse desde ya como experiencia de actor operativo:

- móvil
- simple
- directa
- sin densidad de CRM

#### C. Continuidad visual

El uso de Mapbox no debe romper el lenguaje del producto.

El mapa será una capa especializada, pero el resto de la interfaz debe seguir apoyándose en:

- tipografías existentes
- spacing del sistema
- componentes `shadcn/ui`
- patrones ya presentes de cards, sheets, dialogs y navegación

### 11.5. Diseño UI de la vista de planificación

La vista de planificación debe pensarse como una herramienta de trabajo de escritorio o tablet para comercial.

#### A. Composición recomendada

- **lista de paradas a la izquierda**
- **mapa principal a la derecha**

Esto permite que la lista siga siendo el centro operativo y que el mapa actúe como capa de apoyo visual y espacial.

#### B. Qué debe permitir la pantalla

- crear plantilla
- crear ruta del día
- editar nombre y contexto de la ruta
- buscar y añadir puntos
- arrastrar para reordenar
- clasificar paradas
- asociar paradas a pedido si existe
- asignar repartidor
- ver el trazado resultante

#### C. Qué debe mostrar cada parada

Cada parada debería mostrar de forma compacta:

- orden
- tipo de parada
- tipo de objetivo:
  - cliente
  - prospecto
  - zona
- etiqueta principal
- contexto secundario breve
- referencia visible a pedido prefijado si existe

#### D. Componentes UI recomendados

Usando `shadcn/ui`:

- `Card` para bloques principales
- `Tabs` para alternar entre plantilla, ruta programada y detalle
- `Badge` para el tipo de parada
- `ScrollArea` para listas largas
- `Dialog` o `Sheet` para crear/editar parada
- `DropdownMenu` para acciones rápidas
- `ButtonGroup` para acciones del planificador

### 11.6. Diseño UI de la vista de ejecución

La vista de ejecución debe estar pensada desde móvil antes que desde escritorio.

#### A. Composición recomendada

- **mapa de apoyo**
- **bottom sheet** como capa principal de trabajo

#### B. Qué debe priorizar

- siguiente parada
- acción de navegar
- acceso rápido a pedido prefijado si existe
- marcar resultado mínimo
- abrir autoventa
- añadir nueva parada

#### C. Qué no debe hacer

- no debe mostrar CRM completo
- no debe abrir una ficha rica del cliente
- no debe exigir formularios largos
- no debe comportarse como una herramienta de oficina

#### D. Acción de navegación

La acción principal de navegación debe ser externa:

- `Abrir en Google Maps`
- opcionalmente `Abrir en Waze`

La recomendación para Fase 1 es navegar **parada a parada**, no exportar una navegación completa compleja desde el primer momento.

| Vista | Centro de la experiencia |
|-------|--------------------------|
| Planificación | Lista + mapa |
| Ejecución | Bottom sheet + siguiente parada + botón navegar |

### 11.7. Fase 2: evolución hacia navegación integrada

La Fase 2 no debe plantearse de entrada como “replicar Google Maps completo”, sino como una evolución progresiva.

La recomendación es pensarla como:

- navegación integrada asistida
- seguimiento propio sobre mapa
- experiencia guiada dentro del producto

Y solo más adelante valorar si compensa llegar a una navegación más completa.

#### A. Qué podría incluir una Fase 2 realista

- ver la posición actual en mapa
- ver la parada actual y la siguiente
- centrar la ruta activa
- mostrar progreso sobre la ruta
- ofrecer ayudas simples de navegación
- reducir la dependencia de apps externas

#### B. Qué no conviene prometer de inicio

- navegación turn-by-turn de nivel Google/Waze
- tráfico avanzado
- recalculado de navegación enterprise
- experiencia de GPS completo desde el primer salto

### 11.8. MVP recomendado de Fase 1

El MVP debería incluir:

- listado de rutas y rutas programadas
- creación de ruta con mapa y buscador
- alta de paradas por cliente, prospecto o zona
- reordenación manual
- tipos de parada
- visualización de trazado
- asignación de repartidor
- vista móvil de ejecución
- apertura de parada en Google Maps/Waze
- cierre ligero de parada
- alta manual de parada durante ejecución

El MVP no debería incluir todavía:

- navegación integrada completa
- optimización avanzada automática
- analítica espacial compleja
- experiencia tipo navegador profesional

### 11.9. Principios de diseño que deben gobernar esta implementación

- **Mapbox aporta la UX geográfica donde realmente hace falta: la planificación**
- **Google Maps/Waze resuelven la navegación real de calle en la Fase 1**
- **`shadcn/ui` sigue siendo el sistema visual principal**
- **la lista y las acciones pesan más que el mapa como espectáculo visual**
- **la ejecución debe ser móvil-first**
- **la experiencia del repartidor debe ser rápida, no administrativa**
- **la futura Fase 2 debe construirse como evolución progresiva, no como un gran salto de complejidad**

### 11.10. Conclusión del stack y de la UI

La recomendación final para el frontend del módulo de rutas es:

- usar **Mapbox** en la app para la creación y planificación avanzada de rutas
- mantener la UI integrada con los layouts existentes y con `shadcn/ui` nativo
- ejecutar la navegación real del repartidor en **Google Maps o Waze** durante la Fase 1
- dejar abierta una **Fase 2** para una navegación propia integrada y progresiva

| Fase | Objetivo | Herramientas principales |
|------|----------|--------------------------|
| Fase 1 | Configurar rutas bien y ejecutar sin fricción | Mapbox + shadcn/ui + dnd-kit + Google Maps/Waze |
| Fase 2 | Evolucionar a navegación propia integrada | Revisión futura basada en la experiencia real de uso |

En términos prácticos:

**la mejor estrategia actual es una UI de rutas potente para comercial dentro de la app, construida con Mapbox y `shadcn/ui`, y una ejecución ligera para repartidor que delega la navegación real a Google Maps o Waze hasta que tenga sentido evolucionar hacia un sistema propio.**

---

## 12. Revisión del contexto y de la implementación actual en frontend

### 12.1. Resumen de encaje general

Tras revisar el frontend actual, la conclusión general es que el modelo propuesto **sí encaja como evolución natural**, pero todavía no está expresado de forma limpia en la aplicación.

El frontend ya dispone de un espacio funcional propio para `comercial` y dentro de él conviven piezas valiosas del dominio:

- dashboard comercial
- CRM de prospectos, agenda, clientes y ofertas
- gestor de pedidos
- flujo de autoventa

Esto permite afirmar que la base estructural existe. Sin embargo, el espacio actual sigue presentando en la práctica un único actor funcional donde el negocio ya está pidiendo dos realidades distintas:

- comercial CRM
- repartidor/autoventa

Por tanto, el encaje es bueno a nivel de dirección, pero todavía requiere una futura reorganización funcional para no mezclar bajo la misma superficie responsabilidades relacionales y operativas.

### 12.2. Qué sí encaja bien con el modelo propuesto

Hay varios elementos del frontend actual que encajan bien con el modelo definido en este documento:

- Ya existe un **segmento propio `/comercial`** con layout y navegación dedicados, lo que facilita una futura especialización por actor sin partir de cero.
- El frontend ya distingue claramente entre **flujo CRM** y **flujo de autoventa** a nivel de pantallas y componentes, aunque ambos sigan bajo el mismo paraguas de rol.
- El CRM actual ya expresa un embudo reconocible de `prospecto -> interacción/agenda -> oferta -> pedido`, lo que encaja muy bien con la figura del comercial relacional.
- El flujo de autoventa ya está construido como un recorrido móvil, guiado y orientado a calle, lo cual encaja con la lógica del repartidor/autoventa.
- El pedido sigue actuando como entidad transaccional central, lo cual permite que CRM, autoventa y futura lógica de rutas converjan sin necesidad de cambiar la naturaleza del proceso comercial.
- El frontend ya opera con una lógica de acceso por rol y por segmento, por lo que el patrón de separación funcional ya existe en la aplicación aunque aún no haya llegado al nivel de detalle que pide este modelo.

En conjunto, el frontend no contradice la propuesta. Más bien ya contiene las piezas necesarias, pero aún no las ordena según la separación conceptual correcta.

### 12.3. Qué no encaja o qué genera tensión

El principal punto de tensión es que hoy el frontend sigue tratando `comercial` como un espacio para un único actor híbrido.

Esa mezcla se refleja en varios planos:

- El menú comercial reúne en la misma navegación **CRM, autoventa y pedidos**, lo que funcionalmente mezcla relación comercial y operativa de calle.
- El dashboard comercial combina métricas de ventas con recordatorios y actividad CRM, pero todavía no diferencia si quien entra es un comercial relacional o un perfil más operativo.
- La sección de clientes está planteada desde una óptica de cartera y seguimiento, pero el modelo de negocio validado indica que el repartidor no debería trabajar esa vista relacional del cliente.
- El gestor de pedidos comercial reutiliza lógica muy cercana al gestor general y hoy no expresa con claridad la distinción entre:
  - pedido planificado por un comercial
  - pedido ejecutado por un repartidor
  - autoventa generada en ruta
- La autoventa vive hoy bajo el mismo segmento que el CRM y usa el rol `comercial` como contenedor, lo que a futuro puede reforzar una lectura incorrecta si no se separa bien la intención del actor.
- No existe todavía en frontend una capa explícita para **rutas**, **paradas**, **ejecutor operativo** o **cliente con acceso operativo sin owner comercial**.

En resumen, el frontend sí tiene piezas válidas, pero aún no comunica bien la separación entre:

- quién gestiona la relación
- quién planifica
- quién ejecuta
- quién vende en calle de forma oportunista

### 12.4. Peculiaridades del frontend a tener en cuenta

Hay varias peculiaridades del frontend actual que condicionan cualquier evolución:

- El flujo de autoventa ya está claramente orientado a **móvil y uso en calle**, por lo que cualquier futura lógica de reparto o rutas debe respetar ese nivel de simplicidad y foco táctil.
- El repartidor, según el modelo validado, no debe comportarse como usuario de oficina. Esto significa que no conviene extender hacia él:
  - formularios largos
  - cierres complejos
  - navegación densa propia del CRM
- El frontend actual ya muestra que la autoventa funciona mejor como recorrido guiado y no como interfaz administrativa tradicional.
- La lógica de clientes y CRM en desktop se apoya en paneles laterales, tabs y vistas de detalle relativamente ricas. Eso encaja con el comercial CRM, pero no con el repartidor.
- La futura capa de rutas y paradas deberá convivir con:
  - una experiencia móvil ligera para ejecución
  - una experiencia más estructurada para planificación desde comercial
- El frontend actual no expresa todavía clientes sin owner comercial ni acceso operativo independiente, por lo que esta futura realidad deberá introducirse sin romper la comprensión del usuario.
- La aplicación ya utiliza un patrón fuerte de segmentación por rol y layout. Esa es una ventaja importante, porque permite evolucionar vistas y recorridos sin necesidad de forzar todo dentro del mismo shell conceptual.

### 12.5. Riesgos o puntos de atención al implementar en frontend

Si este modelo se lleva a implementación en frontend, conviene vigilar especialmente estos riesgos:

- no mantener indefinidamente el menú y el espacio `comercial` como contenedor indiferenciado de CRM y reparto
- no convertir al repartidor en un usuario de CRM por simple reutilización de vistas ya existentes
- no cargar la futura lógica de rutas con formularios de cierre demasiado administrativos
- no hacer que la parada de ruta se convierta en una mini ficha de cliente o en una mini interacción comercial
- no volver a introducir implícitamente la noción de “cliente propio del repartidor” por cómo se presenten los listados o selectores
- no esconder en la UI una diferencia de dominio crítica: ownership comercial frente a acceso operativo
- no asumir que el pedido comercial y la ejecución real del pedido son la misma cosa solo porque hoy compartan componentes

También conviene tener presente un riesgo específico del estado actual:

- como el frontend ya tiene autoventa, CRM y pedidos funcionando dentro del mismo segmento, puede resultar tentador ampliar ese espacio sin rediseñar la separación conceptual; eso daría velocidad inicial, pero empeoraría la escalabilidad del modelo.

### 12.6. Conclusión frontend

Desde la óptica frontend, la propuesta es **viable y compatible** con la base actual del proyecto.

No parece necesaria una ruptura total de la estructura existente, porque ya hay:

- segmentación por rol
- espacio comercial propio
- módulos CRM
- autoventa móvil
- pedidos como eje transaccional

Sin embargo, sí parece necesaria una **reorganización funcional clara** en el futuro para evitar que el frontend siga comunicando un único actor híbrido donde negocio ya distingue varios.

La recomendación final desde frontend es:

- conservar la base actual
- reutilizar las piezas ya válidas
- pero evolucionar el espacio comercial hacia una separación más explícita entre:
  - comercial CRM
  - repartidor/autoventa
  - y futura capa operativa de rutas

En términos prácticos y funcionales:

**el frontend actual soporta bien esta dirección, pero necesita ordenar mejor sus superficies para que CRM, autoventa, pedido ejecutado y rutas no queden artificialmente mezclados bajo una sola identidad de usuario.**

---

## 13. Revisión equivalente desde backend

### 13.1. Resumen de encaje general

Tras revisar la implementación actual del backend, la conclusión general es que la propuesta encaja **parcialmente bien**, pero hoy el dominio todavía está centrado en una idea fuerte: el actor principal de este espacio sigue siendo el **comercial interno** y la autoventa sigue modelada como una **variante de pedido**, no como una capacidad de un actor operativo distinto.

El backend sí contiene varias piezas valiosas que acercan mucho el modelo deseado:

- ya existe un eje claro de **ownership comercial** mediante `User(role=comercial)` enlazado a `Salesperson`
- CRM ya está modelado con entidad propia: `Prospect`, `CommercialInteraction`, `AgendaAction` y `Offer`
- los listados y permisos de comercial ya aplican, en varios puntos, **scope por `salesperson_id`**
- existe una separación real entre **actor interno** y **actor externo** mediante `User` y `ExternalUser`
- autoventa ya existe como tipo explícito de pedido mediante `orders.order_type = autoventa`

Sin embargo, el backend todavía no expresa bien tres ideas que este documento da por necesarias:

- cliente sin owner comercial
- actor operativo interno distinto del comercial CRM
- capa de rutas/paradas/ejecución como primer concepto de dominio

En otras palabras:

**la base actual del backend sirve como punto de partida razonable, pero todavía mezcla ownership comercial y ejecución operativa dentro del mismo eje `salesperson -> customer -> order`.**

### 13.2. Qué sí encaja bien con el modelo propuesto

Hay varias estructuras actuales que sí encajan bien con la dirección propuesta.

#### Separación comercial CRM ya bastante madura

El backend ya tiene una capa CRM reconocible y separada del resto del ERP:

- `Prospect` modela oportunidad comercial previa a cliente
- `CommercialInteraction` modela la interacción comercial con target prospecto o cliente
- `AgendaAction` modela la próxima acción y su cadena de reprogramaciones
- `Offer` modela la oferta como objeto propio y permite convertir a pedido

Esto encaja muy bien con la parte del documento que define al **comercial CRM** como actor de relación, seguimiento, agenda y conversión.

#### Ownership comercial ya existe como patrón transversal

El sistema ya utiliza `salesperson_id` como eje de ownership en varias entidades:

- `customers`
- `orders`
- `prospects`
- `commercial_interactions`
- `offers`

Además, parte importante del backend ya respeta ese ownership:

- `CustomerPolicy` y `OrderPolicy` limitan `view` al comercial dueño
- `CustomerListService` y `OrderListService` filtran por `salesperson_id`
- `ProspectService`, `CommercialInteractionService`, `OfferService` y `CrmAgendaService` aplican scope por comercial

Esto significa que la idea de **ownership comercial** no hay que inventarla: ya está presente y funciona como lenguaje base del dominio.

#### Autoventa ya existe como realidad funcional diferenciada

Aunque todavía no exista como actor distinto, la autoventa sí existe como flujo específico:

- `orders.order_type` distingue `standard` y `autoventa`
- `AutoventaStoreService` crea el pedido, sus líneas, un palet en estado enviado y las cajas asociadas
- la validación actual obliga a que la autoventa la cree un usuario `comercial` con `Salesperson` asociado

Esto encaja bien con una parte del problema porque confirma que negocio ya necesitaba diferenciar esta operativa. El backend ya lo ha reconocido, aunque de momento lo haya resuelto como especialización de pedido y no como actor operativo autónomo.

#### La noción de acceso operativo ya existe en otra zona del sistema

Existe una separación real entre:

- actor interno autenticado (`User`)
- actor externo autenticado (`ExternalUser`)

Y existe también una lógica de **scope operativo** por acceso a almacenes:

- `ExternalUser`
- `Store.store_type`
- `stores.external_user_id`
- `ActorScopeService`
- `StorePolicy`

Esto no resuelve el caso de reparto/autoventa, pero sí demuestra que el backend ya sabe modelar:

- acceso operativo distinto del ownership comercial
- actores con visibilidad limitada
- permisos sobre un subconjunto operativo del dominio

Conceptualmente, esta es una pista útil para el futuro modelo.

### 13.3. Qué no encaja o qué genera tensión

El principal problema es que el backend actual sigue usando `salesperson_id` como pegamento de casi todo el flujo comercial y de parte de la autoventa. Eso introduce varias rigideces.

#### El cliente sigue dependiendo estructuralmente de un comercial

`customers.salesperson_id` es una foreign key **NOT NULL** definida en la migración original `2023_12_19_152335` como `foreignId('salesperson_id')->constrained()`. La migración `2026_02_18_170000`, pensada para facilitar el alta rápida en autoventa, hizo nullable payment_term_id, country_id, billing_address y otros campos periféricos, pero **no tocó `salesperson_id`**. Hoy es literalmente imposible insertar un cliente sin asignarle un comercial.

Esto choca de frente con dos necesidades fundamentales del modelo propuesto:

- cliente sin owner comercial
- cliente creado en autoventa con acceso operativo pero sin entrada automática en CRM

Mientras `Customer` exija `salesperson_id`, el backend no puede expresar esa posibilidad. El primer paso técnico concreto del modelo propuesto es una migración que lo haga nullable, seguida de la actualización coordinada de `CustomerPolicy` (la cláusula `$customer->salesperson_id === $user->salesperson->id` rompe con null), `CustomerListService` (el scope por `salesperson_id` excluiría clientes sin dueño) y los controladores que asumen ese campo como siempre presente.

#### El pedido también sigue atado al comercial

`orders.salesperson_id` también forma parte del núcleo del modelo actual. No solo se usa para reporting o ownership blando, sino como una de las claves sobre las que se filtra visibilidad, estadísticas y acceso.

Eso genera una consecuencia importante:

- hoy el sistema entiende el pedido sobre todo como **pedido comercial**
- la autoventa no tiene un ejecutor propio; hereda el comercial del usuario creador

Por tanto, todavía no existe una separación clara entre:

- quién es dueño comercial de la cuenta
- quién ejecuta en calle
- quién registró la operación

#### Autoventa sigue siendo un caso especial de comercial, no un actor distinto

La implementación actual fuerza que solo un `comercial` pueda crear autoventas. Eso resuelve la necesidad táctica, pero semánticamente sigue mezclando dos perfiles:

- comercial CRM
- repartidor/autoventa

En el backend actual, la autoventa no introduce:

- un actor operativo nuevo
- un ejecutor operativo en cliente o pedido
- una visibilidad reducida distinta del CRM

Solo introduce un `order_type`.

#### No existe todavía una capa de rutas

No se han encontrado en backend conceptos equivalentes a:

- ruta
- plantilla de ruta
- ruta programada
- parada
- resultado de parada
- cliente habilitado operativamente
- asignación operativa cliente-actor

Eso significa que la parte más estructural del modelo futuro todavía no está empezada en backend.

#### El actor externo existente no resuelve este caso

`ExternalUser` y `Store.store_type = externo` resuelven un caso de acceso operativo externo a almacenes. No modelan:

- repartidor interno
- vendedor en movilidad
- relación ligera con cliente
- ejecución comercial en ruta

Si se reutilizara ese modelo sin cuidado, se mezclarían dos dominios que hoy son distintos: logística externa de almacén y operación de reparto/autoventa.

### 13.4. Peculiaridades del backend a tener en cuenta

Hay varios condicionantes del backend actual que deben tenerse muy presentes antes de evolucionar el modelo.

#### El rol y la identidad funcional no son lo mismo

Hoy el sistema usa:

- `User.role` para autorización general
- `Salesperson` como identidad comercial de negocio

Eso funciona bien para CRM, pero obliga a decidir con cuidado qué será en el futuro el repartidor/autoventa:

- ¿otro `role`?
- ¿otro actor enlazado a `User`?
- ¿otro perfil operativo paralelo a `Salesperson`?

Si esto no se separa bien, se acabará metiendo semántica de actor en `role` y semántica de ownership en `salesperson_id`, mezclando dos planos distintos.

#### Buena parte del scope está embebido en servicios y policies

El comportamiento actual no depende solo del esquema de base de datos. También depende mucho de:

- policies
- servicios de listado
- validaciones en controladores
- lógica de creación en servicios

Eso es positivo porque da flexibilidad, pero implica que una evolución del modelo no se resolverá solo con nuevas tablas. Habrá que revisar de forma coordinada:

- permisos
- scopes de consulta
- reglas de creación
- recursos de API

#### CRM y pedidos ya están conectados por varias conversiones implícitas

Hoy ya existen conversiones y transferencias entre capas:

- un `Prospect` puede convertirse en `Customer`
- una `Offer` puede terminar creando un `Order`
- una `AgendaAction` puede migrar de prospecto a cliente

Esto hace que cualquier cambio en clientes, ownership o ejecución tenga impacto también sobre CRM. No es un módulo aislado.

#### La autoventa ya materializa stock y expedición

La autoventa no solo crea un pedido administrativo. `AutoventaStoreService` genera en una única transacción DB:

1. `Order` con `order_type='autoventa'`, `status='pending'`, y `salesperson_id` del usuario creador
2. Un `OrderPlannedProductDetail` por cada ítem con peso, cajas, precio y tax
3. Un `Pallet` en estado `shipped` vinculado al pedido (expedido directamente, nunca pasa por almacén)
4. Una `Box` y `PalletBox` por cada caja con lot, GS1-128, peso neto y bruto
5. Una entrada en el timeline del palet con `fromAutoventa=true`

El servicio también valida que exista al menos un `Tax` configurado en el tenant, y construye las `accounting_notes` con el flag de factura y las observaciones del repartidor.

Esto significa que cualquier cambio en el actor que puede crear autoventas afecta simultáneamente a pedidos, stock físico, logística y reporting. No es una etiqueta funcional; es una transacción con impacto real en el inventario.

### 13.5. Riesgos o puntos de atención al implementar en backend

Si este modelo evoluciona en backend, conviene vigilar especialmente estos riesgos.

- no reutilizar `salesperson_id` para representar también ejecutor operativo; ese campo hoy significa ownership comercial y conviene preservar ese significado
- no resolver el repartidor/autoventa solo con un nuevo `role`; haría falta también una identidad operativa y reglas de acceso propias
- no obligar a que todo cliente operativo entre automáticamente en CRM; el backend actual tendería a hacerlo por su fuerte acoplamiento a `Customer + salesperson`
- no mezclar en una misma entidad los conceptos de creador, owner comercial y ejecutor; son responsabilidades distintas y deberían poder divergir
- no modelar rutas solo como un filtro de pedidos; si la ruta va a organizar visitas, ventas oportunistas y entregas, necesita entidad propia
- no intentar reutilizar sin más `ExternalUser` para reparto interno; el parecido es superficial, pero el dominio es diferente
- no romper el scope comercial ya existente en CRM; es una de las partes más maduras del backend y debe seguir siendo una frontera clara

Las decisiones más críticas para que el modelo escale bien parecen ser estas:

- introducir un concepto explícito de **acceso operativo a cliente**
- permitir **cliente sin owner comercial**
- introducir un concepto explícito de **ejecutor operativo**
- decidir si la ejecución en calle vive sobre `Order`, sobre `RouteStop` o sobre ambos
- mantener `ownership comercial` y `capacidad operativa` como ejes distintos

Si estas decisiones se difuminan, el sistema volverá a caer en el mismo problema actual, solo que con más tablas.

### 13.6. Conclusión backend

Desde la óptica backend, la propuesta es **viable**, pero no como simple ajuste cosmético.

La parte CRM del modelo ya está bastante bien encaminada y ofrece una base sólida para el actor **comercial CRM**. También existe ya una primera aproximación funcional a autoventa y una noción útil de acceso operativo limitado en el caso de actores externos.

Sin embargo, para llegar al modelo final que plantea este documento parece necesaria una **reorganización conceptual media-alta** en estos puntos:

- desacoplar `Customer` de la obligación de tener owner comercial
- dejar de usar autoventa solo como subtipo de pedido creado por comercial
- introducir identidad y permisos para actor operativo interno
- crear una capa propia de rutas, paradas y ejecución
- separar explícitamente ownership comercial, acceso operativo y ejecución real

La recomendación final desde backend es:

**aprovechar lo ya existente en CRM y ownership comercial como base estable, pero construir la operativa de reparto/autoventa como una nueva capa de dominio, no como una extensión informal del comercial actual.**

---

## 14. Consolidación final mediante documento unificado

### 14.1. Síntesis de coincidencias entre negocio, frontend y backend

La lectura conjunta de negocio, frontend y backend apunta a una coincidencia bastante clara.

Las tres miradas convergen en que:

- hoy `comercial` está cargando más de un significado
- CRM y autoventa no son la misma naturaleza de trabajo
- cliente, pedido y acceso deben poder soportar colaboración entre actores distintos
- la futura capa de rutas es la mejor forma de unir planificación y ejecución sin convertir al repartidor en usuario administrativo

También hay una coincidencia importante en algo más estructural:

- frontend ya deja ver superficies diferenciables
- backend ya deja ver ownership comercial y CRM maduros
- negocio ya está pidiendo separar responsabilidades

Eso hace que el problema no sea inventado ni prematuro. Es una necesidad real que ya aparece simultáneamente en las tres capas.

### 14.2. Diferencias de interpretación o tensiones abiertas

Las tensiones abiertas no parecen venir de desacuerdo sobre la dirección, sino de cómo aterrizarla sin romper lo existente.

Los puntos que siguen abiertos son sobre todo estos:

- si el repartidor/autoventa debe ser un nuevo rol, un nuevo perfil vinculado a usuario o ambas cosas
- si el cliente operativo debe vivir en `Customer` con nuevos campos o en una capa complementaria de habilitación operativa
- si la ejecución real debe anclarse primariamente en el pedido o en la parada de ruta
- qué parte de la autoventa actual debe seguir viviendo en `Order` y qué parte debe migrar a una lógica de ejecución en ruta

La tensión central, por tanto, no es estratégica sino de modelado:

**cómo separar significados sin duplicar entidades ni romper el ownership comercial ya existente.**

### 14.3. Modelo final consolidado

El modelo consolidado que mejor encaja con lo hablado y con el estado actual del sistema puede expresarse así:

#### Actor comercial CRM

Es el responsable de:

- captar
- seguir
- planificar
- ofertar
- convertir
- mantener la relación comercial

Su eje de dominio sigue siendo el **ownership comercial**.

#### Actor repartidor/autoventa

Es el responsable de:

- ejecutar en calle
- visitar en ruta
- entregar
- registrar resultado operativo
- vender de forma oportunista dentro de su ámbito permitido

Su eje de dominio no debe ser el ownership comercial, sino el **acceso operativo** y la **ejecución**.

#### Cliente

Debe pasar a entenderse como entidad compartida que puede estar en varios estados lógicos:

- cliente con owner comercial y operable
- cliente con owner comercial pero sin acceso operativo para un actor dado
- cliente sin owner comercial pero habilitado operativamente
- cliente nacido desde autoventa y pendiente de eventual incorporación plena a CRM

#### Pedido prefijado

Debe seguir siendo la expresión de una previsión o compromiso previo, pero no debe confundirse con la ejecución real. Puede alimentar una ruta, pero no sustituirla.

#### Autoventa

Debe entenderse como venta ejecutada en calle por actor operativo. Puede seguir generando pedidos, líneas y movimiento físico, pero no debería seguir implicando por defecto que quien ejecuta es el mismo actor que posee la relación comercial.

#### Ruta y parada

Deben convertirse en la capa operativa que une:

- planificación
- asignación
- secuencia de visitas
- ejecución real
- resultado en campo

La parada debe ser la unidad mínima de ejecución; el pedido puede ser uno de sus inputs o resultados, pero no su sustituto.

#### Separación clave

La separación estructural final debe quedar así:

- `ownership comercial`: quién lleva la cuenta y el CRM
- `acceso operativo`: quién puede trabajar ese cliente en ejecución
- `ejecutor`: quién realizó realmente la acción en calle

### 14.4. Riesgos conocidos y criterios de implementación

Antes de implementar, conviene dejar explícitos estos riesgos y criterios.

#### Riesgos conocidos

- volver a meter todo en `comercial` por rapidez inicial
- usar campos existentes con significados nuevos y ambiguos
- forzar que todo cliente operativo tenga owner comercial desde el minuto uno
- diseñar rutas como simple vista de pedidos en lugar de como capa operativa real
- convertir al repartidor en usuario CRM recortado en vez de en actor operativo propio

#### Principios que no deberían romperse

- `salesperson_id` debe seguir significando ownership comercial mientras exista
- CRM debe seguir teniendo scope claro por comercial
- ejecución operativa y relación comercial deben poder divergir
- la creación de un cliente en autoventa no debe obligar a su incorporación automática a CRM
- la experiencia de ejecución debe seguir siendo ligera y móvil-first

#### Simplificaciones aceptables

- en una primera fase, permitir que autoventa siga generando `Order`
- en una primera fase, usar la ruta como contenedor de ejecución sin resolver todavía optimización avanzada
- en una primera fase, inferir algunos resultados desde pedido o interacción si el modelo de parada aún es ligero

#### Simplificaciones no recomendables

- usar solo un nuevo `role` sin nuevo modelo operativo
- reutilizar `ExternalUser` como sustituto directo del repartidor interno
- seguir usando pedido como único lugar donde vive toda la verdad operativa
- resolver cliente operativo sin separar access/ownership

### 14.5. Conclusión final previa a implementación

La visión común que queda fijada es esta:

**el comercial CRM y el repartidor/autoventa son actores distintos que pueden colaborar sobre un mismo cliente, pero el sistema debe separar con claridad quién posee la relación, quién puede operar y quién ejecuta realmente.**

Desde aquí ya pueden pasar a especificación técnica, al menos, estas piezas:

- modelo de actor operativo interno
- modelo de acceso operativo a cliente
- adaptación de `Customer` para permitir ausencia de owner comercial
- modelo de rutas, rutas programadas y paradas
- reglas de creación y visibilidad para clientes nacidos en autoventa

La secuencia recomendada de implementación, con los artefactos técnicos concretos de cada paso, es:

**Paso 1 — Desacoplar el cliente del ownership obligatorio**
- Migración: hacer `customers.salesperson_id` nullable con `nullOnDelete` en la FK
- Actualizar `CustomerPolicy::view` para manejar `salesperson_id = null` (visibilidad para administrador/dirección)
- Actualizar `CustomerListService` para incluir clientes sin owner en los listados de roles no comerciales
- Decidir quién puede ver y gestionar los clientes sin owner comercial

**Paso 2 — Definir la identidad del repartidor**
- Añadir valor al enum `Role` (valor final recomendado: `repartidor_autoventa`) y crear la entidad `FieldOperator` vinculada a `User` (ver sección 16 para el modelo completo)
- Crear `FieldOperatorPolicy` y registrarla en `AuthServiceProvider`
- Añadir relación `User::fieldOperator()` análoga a `User::salesperson()`

**Paso 3 — Adaptar autoventa para el actor operativo**
- Extraer la validación de `Salesperson` de `AutoventaStoreService` y generalizarla para el repartidor
- Definir qué campo del `Order` identifica al ejecutor operativo (¿`executor_id`?) y qué campo sigue siendo `salesperson_id` para el ownership comercial
- Actualizar `OrderListService` y `OrderPolicy` para el scope del repartidor

**Paso 4 — Crear la capa de rutas**
- Migraciones: `route_templates`, `routes` (ruta programada), `route_stops`
- Modelos con `UsesTenantConnection`: `RouteTemplate`, `Route`, `RouteStop`
- Validaciones con `exists:tenant.customers,id`, `exists:tenant.prospects,id`, `exists:tenant.users,id`
- Controladores y endpoints en `api/v2/routes`
- Policy de ruta: comercial crea y planifica, repartidor solo ejecuta la suya

**Paso 5 — Reorganización de frontend**
- Solo una vez que backend expresa los conceptos correctamente, reorganizar las superficies del frontend para separar el espacio CRM del espacio de ejecución operativa

La razón de este orden es concreta: el frontend puede reorganizar menús y vistas en cualquier momento, pero mientras `customers.salesperson_id` sea NOT NULL y `AutoventaStoreService` requiera un `Salesperson`, no hay forma de separar los actores sin romper el comportamiento actual.

---

## 15. Uso recomendado de este documento

Este documento debe servir como referencia para futuras decisiones sobre:

- redefinición de roles funcionales
- rediseño de navegación por actor
- visibilidad de clientes y pedidos
- reglas de ownership y compartición
- reporting comercial frente a reporting operativo
- evolución del flujo de autoventa
- diseño lógico de rutas y paradas
- equilibrio entre trazabilidad y simplicidad móvil para el repartidor

No sustituye a una especificación detallada. Su función es fijar el marco lógico correcto antes de entrar a decisiones concretas.

### 15.1. Conclusión final del documento

La conclusión final que se desprende de todo el análisis es nítida:

**el sistema ya no debería seguir pensando `comercial` como un único actor híbrido que mezcla CRM, pedido, autoventa y ejecución de calle.**

Lo que negocio está pidiendo, lo que el frontend ya deja entrever y lo que el backend ya permite entrever parcialmente apuntan en la misma dirección:

- existe un **comercial CRM** cuyo trabajo principal es la relación, la planificación, la oferta y la conversión
- existe un **repartidor/autoventa** cuyo trabajo principal es la ejecución en ruta, la entrega y la venta oportunista
- ambos pueden colaborar sobre un mismo cliente, pero no deben compartir el mismo significado funcional

Por tanto, la evolución correcta del producto no pasa por recortar o ampliar artificialmente el rol actual de `comercial`, sino por separar explícitamente tres ejes que hoy aparecen demasiado mezclados:

- **ownership comercial**
- **acceso operativo**
- **ejecución real**

Desde esa separación, el modelo objetivo queda mucho más claro:

- el cliente deja de depender de una única lógica de propiedad
- la autoventa deja de ser un simple “modo especial” del comercial y pasa a entenderse como operativa de calle
- el pedido deja de absorber él solo toda la verdad del proceso
- la ruta aparece como la capa natural para unir planificación y ejecución

También queda clara una idea importante de implementación:

**no hace falta romper lo que ya existe, pero sí reordenarlo sobre un modelo de dominio más explícito.**

Eso significa:

- reutilizar el CRM actual como base estable del actor comercial relacional
- reutilizar la autoventa actual como punto de partida funcional, pero no como modelo conceptual definitivo
- aprovechar la madurez actual de frontend y backend para introducir progresivamente una capa operativa propia

En términos de decisión de producto y arquitectura, la recomendación final de este documento es:

1. reconocer formalmente que **comercial CRM** y **repartidor/autoventa** son actores distintos
2. permitir que exista **cliente sin owner comercial** pero con uso operativo válido
3. separar en backend y frontend la noción de **quién lleva la cuenta** de **quién puede operar** y de **quién ejecutó**
4. construir la lógica futura de calle alrededor de **rutas y paradas**
5. evitar que la evolución táctica del sistema siga reforzando un actor híbrido que ya no representa bien la realidad del negocio

En una frase final:

**el comercial CRM debe seguir gestionando la relación y la planificación; el repartidor/autoventa debe ejecutar y vender en ruta dentro de su ámbito operativo; y el sistema debe expresar esa colaboración con claridad, sin volver a mezclar significados comerciales y operativos bajo una sola identidad funcional.**

---

## 16. Estado técnico real implementado en este backend

> **Actualización importante**: esta sección ya no debe leerse como una propuesta futura. A fecha **2026-03-21**, el backend ya incorpora una implementación real de este modelo y ha sido validado en Sail con migraciones tenant y batería focalizada de tests. Las subsecciones de diseño y PRs que aparecen más abajo se conservan como histórico del razonamiento, pero el estado real implementado es el que manda.

El objetivo técnico que finalmente se ha materializado es este:

**preservar `Salesperson` como eje de ownership comercial, introducir `FieldOperator` como identidad operativa interna, separar acceso operativo en `customers`, registrar ejecución operativa en `orders` y añadir `routes` / `route_stops` como capa ligera de planificación y ejecución.**

### 16.A. Estado real implementado

El backend ya implementa las siguientes piezas:

- nuevo rol de aplicación: `repartidor_autoventa`
- nueva identidad operativa interna: `FieldOperator`
- `customers.salesperson_id` nullable
- `customers.field_operator_id` como acceso operativo único por cliente
- `customers.operational_status` para distinguir cliente normal vs. `alta_operativa`
- `orders.field_operator_id` como ejecutor operativo
- `orders.created_by_user_id` para trazabilidad de creación
- `orders.route_id` y `orders.route_stop_id` para vinculación opcional con ruta/parada
- `route_templates`, `route_template_stops`, `routes` y `route_stops`
- `route_stops.result_type` + `route_stops.result_notes` en lugar de un `result` libre
- autoventa operativa sobre el mismo `Order`, sin introducir `order_executions`

### 16.B. Entidades y campos que hoy existen realmente

#### `FieldOperator`

Existe como entidad propia y enlazada 1:1 con `User`.

Responsabilidades actuales:

- representar al actor operativo interno
- ser el owner de rutas asignadas
- ser el actor que crea autoventas operativas
- ser el actor operativo asignado a clientes y pedidos

#### `Customer`

El modelo actual ya soporta:

- `salesperson_id` nullable
- `field_operator_id` nullable
- `operational_status`

Significado vigente:

- `salesperson_id`: ownership comercial
- `field_operator_id`: único actor operativo con acceso actual
- `operational_status`: estado operativo del cliente

Estado inicial real al crear cliente desde autoventa operativa:

- `salesperson_id = null`
- `field_operator_id = FieldOperator del usuario autenticado`
- `operational_status = alta_operativa`

#### `Order`

El modelo actual ya soporta:

- `salesperson_id`
- `field_operator_id`
- `created_by_user_id`
- `route_id`
- `route_stop_id`
- `order_type = autoventa`

El pedido prefijado se sigue editando sobre el mismo `Order`. No se ha introducido `order_executions` en este MVP.

#### `Route`, `RouteTemplate`, `RouteStop`

Existen ya como entidades reales.

La implementación actual incluye:

- `route_templates`
- `route_template_stops`
- `routes`
- `route_stops`

`RouteStop` usa ya tipado explícito:

- `status`
- `result_type`
- `result_notes`

Constantes ya implementadas:

- en `DeliveryRoute`: estados válidos
- en `RouteStop`: estados, tipos de parada y tipos de resultado

### 16.C. Autorización real implementada

La separación de actores no se ha dejado solo en modelo de datos. El backend ya aplica un perímetro explícito para `repartidor_autoventa`.

#### Qué puede hacer hoy el actor operativo

- consultar `GET /api/v2/field/customers/options`
- consultar `GET /api/v2/field/products/options`
- consultar `GET /api/v2/field/orders`
- consultar `GET /api/v2/field/orders/{order}`
- actualizar operativamente `PUT /api/v2/field/orders/{order}`
- crear `POST /api/v2/field/autoventas`
- consultar `GET /api/v2/field/routes`
- consultar `GET /api/v2/field/routes/{route}`
- registrar resultado de parada en `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

#### Qué no puede hacer hoy el actor operativo

- acceder al CRUD general de `customers`
- acceder al CRM (`prospects`, `offers`, `commercial-interactions`, `crm/dashboard`)
- acceder a `stores/options`
- acceder a `products/options`
- acceder a `pallets`
- acceder a exports de pedidos
- generar PDFs de pedidos desde los endpoints generales

#### Policies ya adaptadas o endurecidas

El backend ya tiene al menos estas piezas de autorización alineadas con el modelo:

- `CustomerPolicy`
- `OrderPolicy`
- `DeliveryRoutePolicy`
- `ProductPolicy`
- `StorePolicy`
- `PalletPolicy`

Además, los controladores operativos ya protegen el caso de usuario con rol `repartidor_autoventa` pero sin `FieldOperator` vinculado, devolviendo `403` explícito en lugar de error PHP.

### 16.D. Autoventa real implementada

La autoventa operativa ya no es solo una idea de diseño; está implementada sobre el backend actual.

#### Comportamiento real

- sigue generando un `Order` con `order_type = autoventa`
- permite operar sobre cliente existente si `customers.field_operator_id = current_field_operator.id`
- permite crear cliente nuevo al vuelo si no existe
- crea el cliente nuevo dentro de la misma transacción
- asigna `field_operator_id` y `operational_status = alta_operativa` al nuevo cliente
- registra `field_operator_id` y `created_by_user_id` en el pedido
- valida el contexto de ruta/parada cuando se envía `routeId` / `routeStopId`

#### Reglas reales ya implementadas

- si el cliente existente no tiene acceso operativo para ese actor, la autoventa falla
- si la ruta no pertenece al `FieldOperator`, la autoventa falla
- si la parada no pertenece a la ruta indicada, la autoventa falla
- el `field_operator_id` no se puede inyectar desde el body: se resuelve desde el usuario autenticado

### 16.E. Integridad de datos real implementada

La parte de integridad ya no depende solo de servicios. También se ha reforzado en base de datos.

#### Migración de endurecimiento aplicada

Existe una migración correctiva específica para pedidos y rutas que:

- limpia `orders.route_id` inválidos
- limpia `orders.route_stop_id` inválidos
- limpia `route_stop_id` inconsistentes respecto a `route_id`
- añade foreign keys con `nullOnDelete()`

Esto deja la relación `orders -> routes / route_stops` endurecida sin romper datos previos.

#### Migración de tipado de resultados

Existe además una migración correctiva que:

- migra `route_stops.result` a `result_type` / `result_notes`
- conserva los datos existentes
- deja el modelo alineado con el contrato funcional validado

### 16.F. Endpoints reales implementados

#### Administración / backoffice

- `GET /api/v2/field-operators`
- `POST /api/v2/field-operators`
- `GET /api/v2/field-operators/{field_operator}`
- `PUT /api/v2/field-operators/{field_operator}`
- `DELETE /api/v2/field-operators/{field_operator}`
- `GET /api/v2/field-operators/options`
- `PUT /api/v2/customers/{customer}/assignment`
- `apiResource('route-templates', ...)`
- `apiResource('routes', ...)`

#### Actor operativo

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field/orders`
- `GET /api/v2/field/orders/{order}`
- `PUT /api/v2/field/orders/{order}`
- `POST /api/v2/field/autoventas`
- `GET /api/v2/field/routes`
- `GET /api/v2/field/routes/{route}`
- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

#### Auth / actor context

`GET /api/v2/me` ya devuelve de forma aditiva:

- `salespersonId`
- `fieldOperatorId`
- `isFieldOperator`

### 16.G. Validación real ejecutada en Sail

Esta implementación ya se ha validado sobre Sail siguiendo el flujo multi-tenant real del proyecto:

1. `./vendor/bin/sail up -d`
2. `./vendor/bin/sail artisan migrate --force`
3. `./vendor/bin/sail artisan tenants:migrate`
4. ejecución de batería focalizada de tests

Resultado real de la última validación:

- `40` tests en verde
- `132` assertions
- `1` warning
- validación real de migraciones tenant + flujos operativos + perímetro de autorización

Tests focalizados ya validados:

- `AuthBlockApiTest`
- `FieldOperatorApiTest`
- `OperationalCustomersApiTest`
- `OperationalOrdersApiTest`
- `OrdersRouteIntegrityApiTest`
- `RouteManagementApiTest`

### 16.H. Pendientes reales a fecha actual

En la parte backend y de lógica operativa, el modelo está ya implementado y validado. Lo que queda pendiente no es de dominio core, sino de refinamiento futuro:

- limpiar el warning de `fsockopen()` en el flujo `auth/request-access`
- ampliar reporting operativo si negocio necesita dashboards específicos
- decidir si más adelante compensa introducir una capa de ejecución más rica que el `Order` actual
- decidir si la vinculación `origin_route_stop_id` merece existir como trazabilidad adicional

### 16.I. Cómo leer las subsecciones siguientes

La información técnica útil de este documento termina en las subsecciones **16.A–16.H**. El diseño previo, los planes de PR y la teoría de implementación se han eliminado para que esta parte refleje solo el backend real y vigente.

#### Conclusión técnica actual

Desde backend, este modelo ya no está en fase de propuesta. A fecha **2026-03-21** existe una implementación real, validada en Sail y alineada con el dominio acordado:

- `repartidor_autoventa` es un rol real y separado del `comercial`
- `FieldOperator` es la identidad operativa real del actor de calle
- `Customer` y `Order` ya soportan ownership comercial y ejecución operativa como significados distintos
- `Route`, `RouteTemplate` y `RouteStop` existen como capa operativa real
- la autoventa operativa ya funciona sobre el mismo `Order` sin invadir CRM
- el perímetro de autorización del actor operativo está cerrado y testeado

La referencia técnica vigente para backend es esta sección 16 junto con los invariantes funcionales que siguen a continuación.

---

## 17. Guía de interacción frontend con el backend real

Esta sección no define cómo debe construirse el frontend. Su objetivo es dejar claro **qué contratos, reglas, límites y comportamientos reales expone hoy el backend** para que frontend pueda implementar la nueva lógica sin asumir cosas incorrectas.

### 17.1. Regla general de integración

Frontend debe distinguir siempre entre dos mundos:

- **endpoints generales** del producto
- **endpoints operativos** bajo `/api/v2/field/*`

Para el actor `repartidor_autoventa`, la referencia correcta no son los endpoints generales, sino el perímetro específico `field/*` más el contexto de sesión devuelto por `GET /api/v2/me`.

Consecuencia práctica:

- el frontend del actor operativo no debe intentar reutilizar el CRUD general de `customers`
- no debe intentar reutilizar `products/options`
- no debe intentar reutilizar endpoints CRM
- no debe asumir que puede abrir la ficha general de pedido o cliente aunque conozca su `id`

### 17.2. Contexto de sesión que debe usar frontend

El endpoint `GET /api/v2/me` devuelve ya contexto suficiente para resolver el actor autenticado.

Campos especialmente relevantes:

- `role`
- `actorType`
- `salespersonId`
- `fieldOperatorId`
- `isFieldOperator`
- `allowedStoreIds`
- `features`

Regla recomendada de lectura:

- si `role = repartidor_autoventa`, frontend debe tratar al usuario como actor operativo
- si además `fieldOperatorId` viene `null`, el backend devolverá `403` en los endpoints operativos; frontend debe tratarlo como usuario sin identidad operativa activa
- frontend no debe inferir el actor solo desde `role` si necesita saber si existe identidad operativa real; para eso debe mirar `fieldOperatorId`

### 17.3. Qué puede y qué no puede consumir frontend según actor

#### Resumen rápido por perímetro

| Actor | Debe usar | No debe usar |
|-------|-----------|--------------|
| Comercial / backoffice | Endpoints generales + gestión de `FieldOperator`, clientes, rutas y plantillas | `field/*` salvo que se quiera reutilizar puntualmente lectura operativa |
| Repartidor / autoventa | `GET /api/v2/me` + endpoints `field/*` | CRUD general de clientes/pedidos, CRM, catálogos generales, exports, PDFs |

#### Comercial / backoffice

Puede seguir usando:

- CRUD general de clientes
- CRUD general de pedidos
- CRM
- gestión de `FieldOperator`
- plantillas de ruta
- rutas programadas
- asignación comercial y operativa de clientes

#### Repartidor / autoventa

Debe limitarse a:

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field/orders`
- `GET /api/v2/field/orders/{order}`
- `PUT /api/v2/field/orders/{order}`
- `POST /api/v2/field/autoventas`
- `GET /api/v2/field/routes`
- `GET /api/v2/field/routes/{route}`
- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

No debe intentar usar:

- `/api/v2/customers`
- `/api/v2/products/options`
- `/api/v2/stores/options`
- `/api/v2/prospects`
- `/api/v2/offers`
- `/api/v2/commercial-interactions`
- `/api/v2/crm/dashboard`
- exports o PDFs generales

Si frontend llama esos endpoints con `repartidor_autoventa`, lo esperable es `403`.

### 17.4. Tipos de respuesta que frontend debe esperar

Hay dos patrones reales en esta parte del backend:

#### A. Listados paginados con resource collection

Los usan, entre otros:

- `GET /api/v2/field/orders`
- `GET /api/v2/field/routes`
- `GET /api/v2/field-operators`
- `GET /api/v2/routes`
- `GET /api/v2/route-templates`

Frontend debe esperar el shape típico de colección paginada Laravel:

- `data`
- `links`
- `meta`

#### B. Endpoints tipo `options`

Los usan, entre otros:

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field-operators/options`

Estos endpoints devuelven directamente un array simple, no una colección paginada.

#### Resumen de shapes

| Tipo de endpoint | Shape esperado |
|------------------|----------------|
| Listado paginado | `data` + `links` + `meta` |
| Detalle | `data` |
| Acción mutadora | `message` + `data` |
| `options` | array simple |

### 17.5. Contrato real de clientes para frontend

#### A. Cliente operativo

Para el actor operativo no existe endpoint de ficha de cliente. El backend expone clientes operativos solo como opciones mínimas.

`GET /api/v2/field/customers/options` devuelve por elemento:

- `id`
- `name`
- `operationalStatus`

Reglas reales:

- solo devuelve clientes con `customers.field_operator_id = fieldOperatorId actual`
- no devuelve historial
- no devuelve notas
- no devuelve ownership comercial
- no devuelve datos CRM

#### B. Asignación de cliente desde backoffice

`PUT /api/v2/customers/{customer}/assignment`

Campos admitidos:

- `salesperson_id`
- `field_operator_id`
- `operational_status`

Valores válidos para `operational_status`:

- `normal`
- `alta_operativa`

Esto es relevante para frontend porque la asignación operativa **no se hace desde el flujo del repartidor**, sino desde administración/comercial/backoffice.

#### Resumen de interacción de cliente

| Caso | Endpoint | Qué obtiene o envía frontend |
|------|----------|------------------------------|
| Selector operativo de clientes | `GET /api/v2/field/customers/options` | `id`, `name`, `operationalStatus` |
| Asignación desde backoffice | `PUT /api/v2/customers/{customer}/assignment` | `salesperson_id`, `field_operator_id`, `operational_status` |
| Ficha de cliente para repartidor | No existe | No debe implementarse contra backend actual |

### 17.6. Contrato real de productos para frontend

`GET /api/v2/field/products/options` devuelve por producto:

- `id`
- `name`
- `species`
  - `id`
  - `name`

Reglas reales:

- es el endpoint que debe usar el actor operativo para construir autoventa
- el endpoint general `GET /api/v2/products/options` está bloqueado para `repartidor_autoventa`
- backend no exige que frontend conozca más catálogo que ese

#### Resumen de catálogo operativo

| Endpoint | Actor | Respuesta mínima |
|----------|-------|------------------|
| `GET /api/v2/field/products/options` | Repartidor/autoventa | `id`, `name`, `species` |
| `GET /api/v2/products/options` | Repartidor/autoventa | `403` esperado |

### 17.7. Contrato real de pedidos operativos

#### A. Listado de pedidos operativos

`GET /api/v2/field/orders`

Filtros admitidos:

- `status`
  - `pending`
  - `finished`
  - `incident`
- `orderType`
  - `standard`
  - `autoventa`
- `routeId`
- `perPage`

Solo devuelve pedidos cuyo `field_operator_id` coincide con el actor operativo actual.

| Filtro | Valores |
|--------|---------|
| `status` | `pending`, `finished`, `incident` |
| `orderType` | `standard`, `autoventa` |
| `routeId` | `id` de ruta |
| `perPage` | `1..100` |

#### B. Shape real del recurso de pedido operativo

`FieldOrderResource` devuelve:

- `id`
- `orderType`
- `status`
- `entryDate`
- `loadDate`
- `buyerReference`
- `customer`
  - `id`
  - `name`
- `fieldOperatorId`
- `routeId`
- `routeStopId`
- `plannedProductDetails` — array de líneas de producto; cada elemento contiene:
  - `id`
  - `orderId`
  - `product` — objeto completo (`id`, `name`, `species`, `captureZone`, `category`, `family`, `articleGtin`, `boxGtin`, `palletGtin`, `a3erpCode`, `facilcomCode`)
  - `tax` — objeto de impuesto
  - `quantity`
  - `boxes`
  - `unitPrice`
- `totalBoxes`
- `totalNetWeight`
- `createdAt`
- `updatedAt`

Frontend no debe asumir que el pedido operativo devuelve la misma carga que el recurso general de pedido.

#### C. Detalle de pedido operativo

`GET /api/v2/field/orders/{order}`

Regla real:

- solo accesible si la policy permite `viewOperational`
- si el pedido no pertenece al actor operativo, el resultado esperado es `403`

#### D. Actualización operativa de pedido

`PUT /api/v2/field/orders/{order}`

Campos admitidos:

- `status`
- `plannedProducts`

Cada línea de `plannedProducts` admite:

- `product`
- `quantity`
- `boxes`
- `unitPrice`
- `tax`

Regla importante para frontend:

- si no se envía ni `status` ni `plannedProducts`, backend devuelve error de validación
- si se envía `plannedProducts`, frontend debe tratarlo como **nuevo conjunto operativo de líneas**, no como parche parcial por línea
- este endpoint no sirve para cambiar cliente, fechas ni condiciones comerciales del pedido

#### Resumen de interacción de pedido operativo

| Caso | Endpoint | Claves principales |
|------|----------|--------------------|
| Listado | `GET /api/v2/field/orders` | scope por `field_operator_id` |
| Detalle | `GET /api/v2/field/orders/{order}` | `403` si no pertenece al actor |
| Actualización | `PUT /api/v2/field/orders/{order}` | `status`, `plannedProducts` |

### 17.8. Contrato real de autoventa operativa

`POST /api/v2/field/autoventas`

#### A. Regla de cliente

Frontend debe enviar una de estas dos opciones:

- `customer`
- `newCustomerName`

No hace falta enviar ambas.

Si no envía ninguna, backend devuelve error de validación.

#### B. Campos admitidos

- `customer`
- `newCustomerName`
- `entryDate`
- `loadDate`
- `invoiceRequired`
- `observations`
- `items`
- `boxes`
- `routeId`
- `routeStopId`

| Campo | Obligatorio | Observaciones |
|-------|-------------|---------------|
| `customer` | no | Alternativa a `newCustomerName` |
| `newCustomerName` | no | Alternativa a `customer` |
| `entryDate` | sí | Debe ser `<= loadDate` |
| `loadDate` | sí | Debe ser `>= entryDate` |
| `invoiceRequired` | sí | boolean |
| `observations` | no | texto libre |
| `items` | sí | mínimo 1 |
| `boxes` | sí | mínimo 1 |
| `routeId` | no | debe pertenecer al actor |
| `routeStopId` | no | debe pertenecer a `routeId` y al actor |

#### C. Shape de `items`

Cada elemento admite:

- `productId`
- `boxesCount`
- `totalWeight`
- `unitPrice`
- `subtotal`
- `tax`

#### D. Shape de `boxes`

Cada elemento admite:

- `productId`
- `lot`
- `netWeight`
- `grossWeight`
- `gs1128`

#### E. Reglas de negocio que frontend debe respetar

- `entryDate` no puede ser posterior a `loadDate`
- si se envía `customer`, ese cliente debe estar operativo para el actor actual
- si se envía `newCustomerName`, backend crea el cliente en la misma transacción
- el cliente nuevo nace con:
  - `salesperson_id = null`
  - `field_operator_id = actor actual`
  - `operational_status = alta_operativa`
- si se envía `routeId`, la ruta debe pertenecer al actor operativo actual
- si se envía `routeStopId`, la parada debe pertenecer a una ruta del actor actual
- si se envían `routeId` y `routeStopId`, ambos deben ser coherentes entre sí

#### F. Qué devuelve

Respuesta `201` con:

- `message`
- `data` con `FieldOrderResource`

Es decir, la autoventa devuelve ya el pedido operativo creado, no una entidad distinta.

#### Resumen de interacción de autoventa

| Caso | Comportamiento backend |
|------|------------------------|
| Cliente existente con acceso | crea autoventa |
| Cliente existente sin acceso | `422` / rechazo funcional |
| Cliente nuevo | crea cliente + pedido en la misma transacción |
| Ruta/parada incoherente | error de validación |

### 17.9. Contrato real de rutas para frontend

#### A. Rutas del actor operativo

`GET /api/v2/field/routes`

Parámetro admitido:

- `perPage`

Regla real:

- solo devuelve rutas con `field_operator_id = actor actual`

| Endpoint | Tipo | Scope |
|----------|------|-------|
| `GET /api/v2/field/routes` | listado paginado | solo rutas del actor actual |
| `GET /api/v2/field/routes/{route}` | detalle | solo rutas asignadas |
| `PUT /api/v2/field/routes/{route}/stops/{routeStop}` | acción | solo paradas de rutas asignadas |

#### B. Detalle de ruta del actor operativo

`GET /api/v2/field/routes/{route}`

Solo accesible si la policy permite `viewAssigned`.

#### C. Shape real de `DeliveryRouteResource`

El recurso devuelve:

- `id`
- `routeTemplateId`
- `name`
- `description`
- `routeDate`
- `status`
- `salesperson`
- `fieldOperator`
- `createdByUserId`
- `stops`
- `createdAt`
- `updatedAt`

Cada parada en `stops` devuelve:

- `id`
- `position`
- `stopType`
- `targetType`
- `customerId`
- `prospectId`
- `label`
- `address`
- `notes`
- `status`
- `resultType`
- `resultNotes`
- `completedAt`

#### D. Actualización de parada

`PUT /api/v2/field/routes/{route}/stops/{routeStop}`

Campos admitidos:

- `status`
- `result_type`
- `result_notes`

Reglas reales:

- `status` es obligatorio
- `result_type` es obligatorio si `status = completed`
- `result_notes` es opcional
- si `routeStop` no pertenece a `route`, backend responde `404`
- si la ruta no está asignada al actor actual, backend responde `403`

Valores válidos de `result_type`:

- `delivery`
- `autoventa`
- `no_contact`
- `incident`
- `visit`

Frontend no debe seguir usando el concepto antiguo de `result` libre.

#### Respuesta de `updateStop`

El endpoint devuelve `message` + `data`, pero `data` es la **ruta completa** actualizada (`DeliveryRouteResource` con `stops` frescos), no solo la parada modificada. Frontend debe usar esa respuesta para refrescar toda la vista de ruta, no solo el ítem individual.

#### Resumen de payload de parada

| Campo | Obligatorio | Regla |
|-------|-------------|-------|
| `status` | sí | `pending`, `completed` o `skipped` |
| `result_type` | condicional | obligatorio si `status = completed` |
| `result_notes` | no | texto libre, máx. 1000 |

### 17.10. Contrato real de plantillas y rutas para backoffice

#### A. `FieldOperator`

`GET /api/v2/field-operators/options` devuelve una lista mínima con:

- `id`
- `name`

El CRUD completo de `FieldOperator` devuelve además:

- `emails`
- `ccEmails`
- `userId`
- `user` (**solo presente en `show`, `store` y `update`, no en `index`**)
  - `id`
  - `name`
  - `email`
  - `role`

Restricción real relevante para frontend:

- si se intenta enlazar un `user_id` que no tenga rol `repartidor_autoventa`, backend devuelve validación
- si se intenta enlazar un `user_id` que ya tiene `Salesperson`, backend devuelve validación

| Endpoint | Uso |
|----------|-----|
| `GET /api/v2/field-operators` | listado paginado |
| `POST /api/v2/field-operators` | creación |
| `GET /api/v2/field-operators/{field_operator}` | detalle |
| `PUT /api/v2/field-operators/{field_operator}` | edición |
| `DELETE /api/v2/field-operators/{field_operator}` | borrado |
| `GET /api/v2/field-operators/options` | selector simple |

#### B. `RouteTemplate`

`GET /api/v2/route-templates`

Filtros admitidos:

- `name`
- `fieldOperatorId`
- `salespersonId`
- `perPage`

Campos de creación/actualización:

- `name`
- `description`
- `salespersonId`
- `fieldOperatorId`
- `isActive`
- `stops`

Cada `stop` admite:

- `position`
- `stopType`
- `targetType`
- `customerId`
- `prospectId`
- `label`
- `address`
- `notes`

Regla importante:

- si frontend envía `stops` en update, backend reemplaza completamente el conjunto actual de paradas de la plantilla

| Campo | Observación |
|-------|-------------|
| `name` | obligatorio en creación |
| `description` | opcional |
| `salespersonId` | opcional |
| `fieldOperatorId` | opcional |
| `isActive` | opcional |
| `stops` | si se envía en update, reemplaza todo |

#### C. `Route`

`GET /api/v2/routes`

Filtros admitidos:

- `name`
- `fieldOperatorId`
- `salespersonId`
- `routeDate`
- `status`
- `perPage`

Campos de creación/actualización:

- `routeTemplateId`
- `name`
- `description`
- `routeDate`
- `status`
- `salespersonId`
- `fieldOperatorId`
- `stops`

Reglas reales importantes:

- si frontend crea o actualiza una ruta con `routeTemplateId` y sin `stops`, backend instancia las paradas desde la plantilla
- si frontend envía `stops`, backend reemplaza completamente las paradas actuales de la ruta
- al instanciar desde plantilla, backend preserva vínculo con `route_template_stop_id` para trazabilidad interna

| Campo | Observación |
|-------|-------------|
| `routeTemplateId` | opcional; si se envía sin `stops`, instancia desde plantilla |
| `name` | obligatorio en creación |
| `description` | opcional |
| `routeDate` | opcional |
| `status` | opcional |
| `salespersonId` | opcional |
| `fieldOperatorId` | opcional |
| `stops` | si se envía, reemplaza el conjunto actual |

### 17.11. Estados y enums que frontend debe tratar como contrato

#### A. Pedido operativo

- `pending`
- `finished`
- `incident`

#### B. Tipos de pedido

- `standard`
- `autoventa`

#### C. Estado operativo de cliente

- `normal`
- `alta_operativa`

#### D. Tipos de parada

Valores exactos del contrato (constantes `RouteStop::STOP_TYPE_*`):

- `obligatoria`
- `sugerida`
- `oportunidad`

Frontend debe consumir estos strings exactos y no inventar otros.

#### E. Tipos de target de parada

- `customer`
- `prospect`
- `location`

#### F. Estado de parada

Valores exactos del contrato (constantes `RouteStop::STATUS_*`):

- `pending`
- `completed`
- `skipped`

Nota: cuando el backend recibe `status = completed`, fija automáticamente `completed_at = now()`. Para cualquier otro estado, `completed_at` se pone a `null`.

#### G. Resultado de parada

- `delivery`
- `autoventa`
- `no_contact`
- `incident`
- `visit`

#### Tabla compacta de enums

| Dominio | Valores |
|---------|---------|
| Pedido operativo `status` | `pending`, `finished`, `incident` |
| Pedido `orderType` | `standard`, `autoventa` |
| Cliente `operational_status` | `normal`, `alta_operativa` |
| Stop `stopType` | `obligatoria`, `sugerida`, `oportunidad` |
| Stop `targetType` | `customer`, `prospect`, `location` |
| Stop `status` | `pending`, `completed`, `skipped` |
| Stop `resultType` | `delivery`, `autoventa`, `no_contact`, `incident`, `visit` |

### 17.12. Errores y respuestas que frontend debe considerar normales

#### `403`

Casos típicos:

- usuario con rol operativo sin `FieldOperator`
- intento de acceder a un recurso general no permitido
- pedido/ruta/cliente fuera del perímetro operativo del actor actual

#### `404`

Casos típicos:

- `routeStop` no pertenece a la `route` indicada
- recurso inexistente o fuera de route model binding

#### `422`

Casos típicos:

- autoventa sin `customer` ni `newCustomerName`
- `entryDate > loadDate`
- `result_type` ausente cuando la parada se marca como completada
- intento de enlazar `FieldOperator` a un usuario no válido
- intento de autoventa sobre cliente no accesible

Frontend debe tratar estos errores como parte normal del flujo y no como fallos inesperados del sistema.

#### Resumen rápido de errores

| Código | Lectura frontend |
|--------|------------------|
| `403` | fuera de perímetro o sin identidad operativa |
| `404` | recurso no encontrado o relación de ruta/parada incoherente |
| `422` | payload inválido o regla funcional incumplida |

### 17.13. Invariantes que frontend no debe romper conceptualmente

- `salesperson_id` sigue significando ownership comercial; frontend no debe usarlo como ejecutor
- `fieldOperatorId` es el eje operativo real para el actor de calle
- el actor operativo no tiene ficha de cliente
- la autoventa operativa devuelve y trabaja con `Order`
- la ruta es una guía operativa, no un contenedor obligatorio
- los endpoints `field/*` son el contrato principal del repartidor/autoventa
- si frontend necesita más datos para el actor operativo, debe pedir un endpoint específico; no reutilizar por defecto uno general que hoy está fuera de su perímetro

### 17.14. Resumen mínimo por flujo para frontend

#### Login / bootstrap

1. autenticar
2. llamar `GET /api/v2/me`
3. decidir actor y perímetro
4. cargar solo endpoints permitidos para ese actor

#### Autoventa operativa

1. cargar `field/customers/options`
2. cargar `field/products/options`
3. construir payload de autoventa
4. enviar `POST /api/v2/field/autoventas`
5. usar la respuesta como pedido operativo creado

#### Pedido prefijado operativo

1. listar `GET /api/v2/field/orders`
2. abrir `GET /api/v2/field/orders/{order}`
3. actualizar estado y/o líneas operativas con `PUT /api/v2/field/orders/{order}`

#### Ejecución de ruta

1. listar `GET /api/v2/field/routes`
2. abrir `GET /api/v2/field/routes/{route}`
3. cerrar parada con `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

#### Backoffice

1. gestionar `FieldOperator`
2. asignar ownership/acceso en clientes
3. crear plantillas
4. crear rutas y asignarlas

### 17.15. Criterio final para frontend

Si frontend duda entre reutilizar un endpoint general existente o consumir uno operativo específico, la regla correcta para este dominio es:

- **actor operativo**: usar siempre el endpoint específico si existe
- **backoffice/comercial**: usar el endpoint general salvo que necesite comportamiento operativo explícito

Si falta un dato para el actor operativo, el siguiente paso correcto no es abrir un endpoint general, sino pedir una ampliación o endpoint operativo adicional.

En resumen:

**frontend no debe intentar deducir esta lógica desde pantallas antiguas o endpoints heredados; debe tomar esta sección como contrato de integración real con el backend actual.**

---

## 31. Lo que no cambia

Este apartado existe para dejar claro qué parte del sistema **no debe tocarse** al implementar el modelo del repartidor. Es igual de importante que saber qué hay que construir.

### 31.1. El CRM actual se preserva íntegro

Las entidades `Prospect`, `ProspectContact`, `CommercialInteraction`, `AgendaAction`, `Offer` y `OfferLine` no necesitan modificación. El flujo CRM completo `Prospect → CommercialInteraction → AgendaAction → Offer → Order` sigue siendo el dominio exclusivo del comercial relacional. No se añaden campos al CRM para dar cabida al repartidor.

### 31.2. El scope del comercial no se toca

Los scopes por `salesperson_id` en `CustomerListService`, `OrderListService`, `ProspectService` y `OfferService` no cambian. El comercial sigue viendo solo sus clientes, sus pedidos y sus prospectos. La separación de actores no debe ampliar ni reducir lo que el comercial ve hoy.

### 31.3. `salesperson_id` sigue significando ownership comercial

En ningún caso `salesperson_id` debe reutilizarse para almacenar también el ejecutor operativo. Si se introduce un campo `executor_id` o `field_operator_id` en pedidos o clientes, son campos adicionales con significado propio. `salesperson_id` sigue siendo el eje del CRM.

### 31.4. La autoventa actual sigue funcionando

Los pedidos existentes con `order_type='autoventa'` creados por comerciales no cambian de comportamiento. El flujo legacy de autoventa debe permanecer operativo durante y después de la transición. El repartidor usará un flujo adaptado en paralelo; nunca un reemplazo que rompa lo existente.

### 31.5. La arquitectura multi-tenant no cambia

Todo lo nuevo (rutas, identidad de `FieldOperator`, acceso operativo) sigue el mismo patrón: modelos con `UsesTenantConnection`, validaciones con `exists:tenant.{tabla}`, sin queries cross-tenant. La separación de actores es una decisión de dominio, no de arquitectura de datos.

### 31.6. La ruta es una guía, nunca un contenedor obligatorio

La ruta organiza y sugiere, pero no bloquea. Un actor operativo puede crear autoventas, completar pedidos y operar clientes sin tener una ruta activa. Un pedido o autoventa no necesita estar vinculado a una parada para ser válido. La vinculación entre ruta/parada y pedido/autoventa es siempre opcional y sirve solo para trazabilidad de reporting, nunca como requisito de ejecución.

### 31.7. El acceso operativo es uno por cliente en todo momento

Un cliente tiene como máximo un actor operativo asignado simultáneamente (`customers.field_operator_id`). No se admite acceso operativo múltiple concurrente. Si cambia el actor asignado, el valor anterior se sobreescribe sin historial, por decisión de negocio.

### 31.8. El repartidor no tiene ficha de cliente

El actor operativo no tiene acceso a la ficha de detalle de un cliente, su historial, sus notas, sus precios ni ningún dato CRM. Solo puede usar el cliente como referencia en autoventa o verlo en pedidos asignados. Este límite no debe relajarse sin decisión explícita de negocio.
