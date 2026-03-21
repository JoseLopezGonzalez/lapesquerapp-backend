# Validación de preguntas críticas — Comercial, Reparto y Autoventa

**Estado**: Pendiente de respuesta  
**Objetivo**: Validar decisiones críticas antes de implementar el modelo descrito en `73-modelo-logico-comercial-reparto-autoventa.md`

---

## Instrucciones

Responde debajo de cada pregunta con una línea tipo:

`Respuesta: ...`

Si una pregunta no está cerrada aún, puedes indicar:

`Respuesta: pendiente`

o

`Respuesta: depende de ...`

---

## 1. Actor operativo

### 1.1

¿El repartidor/autoventa es siempre un actor distinto del comercial CRM?

Respuesta: Si

### 1.2

Si una misma persona puede hacer ambos papeles, ¿quieres que el sistema lo modele como dos identidades funcionales sobre el mismo usuario?

Respuesta: No, en ese caso tan raro debera crearse un usuario por cada rol por lo pronto

### 1.3

¿Quieres crear un rol nuevo de aplicación para este actor operativo o prefieres que la identidad operativa viva separada del `role`?

Respuesta: clarodeberia ser un rol nuevo

### 1.4

¿El actor operativo interno debe poder existir aunque todavía no tenga rutas asignadas?

Respuesta: no te entiendo bien

---

## 2. Cliente sin owner comercial

### 2.1

¿Un cliente sin owner comercial puede quedarse indefinidamente así?

Respuesta: podria, siempre va a pertenecer a al la empresa en ge

### 2.2

¿Quién puede ver el listado de clientes sin owner comercial?

Respuesta: todos los roles que ya pueden ver todos los clientes

### 2.3

¿Quién puede asignar posteriormente un owner comercial a ese cliente?

Respuesta: esos roles que ya pueden manipular todos los clientes

### 2.4

¿Quieres una bandeja explícita de “clientes operativos pendientes de asignación comercial”?

Respuesta: por lo pronto no,pero si implementar los filtros para poder filtrar por ello en el crud

### 2.5

¿Un cliente sin owner comercial debe poder usarse en pedidos normales además de autoventa?

Respuesta: si

---

## 3. Alta de cliente en autoventa

### 3.1

Cuando el repartidor crea un cliente nuevo en ruta, ¿ese cliente debe quedar operativo de inmediato sin revisión previa?

Respuesta: si

### 3.2

¿Ese cliente nuevo lo puede usar solo el repartidor que lo creó o cualquier actor operativo autorizado después?

Respuesta: lo puede usar el autor operativo que lo creo en primera instancia porque se registraria con el por defecto pero si eso se cambiase podria usarlo otro y si en un pedido en concreto se asigna a otro pues tambien podria usarlo otro.

### 3.3

¿Debe crearse automáticamente el acceso operativo del creador?

Respuesta: claro, al crearlo el acesso operativo va para el creador por defecto a menos que se cambie a posteriori

### 3.4

¿Debe quedar marcado con un estado especial tipo `alta_operativa` o `pendiente_revision`?

Respuesta: si, no me importaria

### 3.5

¿Qué datos mínimos son obligatorios para crear ese cliente en calle?

Respuesta: Solo el nombre (como ya lo tenemos pensado ) para que el repartidor no deba enrollarse con formularios complejos o quedar bloqueado por no tener algun dato. Ya la administracion se encargara de rellenar esos datos faltantes a posterior al ver el pedido registrado 

### 3.6

¿Debe quedar visible para administración desde el primer momento?

Respuesta: claro y para todos los roles que ya pueden ver todos los clientes

---

## 4. Ownership vs acceso operativo

### 4.1

¿Un cliente puede tener varios actores operativos simultáneamente?

Respuesta: no

### 4.2

¿Quién concede el acceso operativo a un cliente?

Respuesta: los roles que ya pueden ver y manipular los clientes, y el comercial propietario solo a la hora de crear el pedido y vincularlo a ese repartidor

### 4.3

¿Quién revoca el acceso operativo a un cliente?

Respuesta: los roles que ya hemos mencionado antes 

### 4.4

¿El comercial owner puede conceder acceso operativo o eso debe quedar en manos de administración/logística?

Respuesta: puede solo cuando cree el pedido prefijado.

### 4.5

¿Quieres mantener historial de quién concedió y revocó el acceso operativo?

Respuesta: no es necesario.

---

## 5. Pedido prefijado

### 5.1

Cuando el repartidor ajusta lo servido realmente, ¿quieres que modifique el mismo pedido o que genere una ejecución separada vinculada al pedido original?

Respuesta: el mismo pedido

### 5.2

¿Qué partes del pedido prefijado sí puede tocar el repartidor?

Respuesta: nada, solo el contenido de productos cajas palets. (Que lo hara solo como se hacen las autoventas actualemnte , nada de vincular palets desde stock ni nada.

### 5.3

¿Qué partes del pedido no puede tocar nunca el repartidor?

Respuesta: el resto

### 5.4

Si cambia cantidades, cajas o productos servidos, ¿quieres trazabilidad explícita entre previsión y ejecución real?

Respuesta: no 

### 5.5

Si la desviación es grande, ¿quieres tratarla como incidencia, como ajuste del pedido o como autoventa separada?

Respuesta: no

---

## 6. Autoventa

### 6.1

¿La autoventa debe seguir generando siempre un `Order`?

Respuesta: si

### 6.2

¿Una autoventa puede hacerse sobre un cliente con owner comercial?

Respuesta: si, pero debe tener prefijado a ese repartidor en su configuracion, si no no lo podrá ver

### 6.3

¿Una autoventa puede hacerse sobre un cliente sin owner comercial?

Respuesta: si, siempre y cuando cumpla con el punto anterior.

### 6.4

¿Una autoventa puede hacerse sobre un cliente creado en el mismo momento?

Respuesta: si

### 6.5

Si el cliente tiene owner comercial, ¿la autoventa debe quedar atribuida comercialmente a ese owner, al ejecutor operativo o a ambos con significados distintos?

Respuesta: a ambos con los significados distintos que ya definimos

### 6.6

¿Quieres distinguir en reporting entre venta del owner comercial y venta ejecutada por actor operativo?

Respuesta: si, no está mal (sobretodo debemos valorar los pedidos que haya hecho en autoventa un repartidor a un cliente sin owner comercial) porque significaran que son ventas directamente atribuibles al repartidor directamente.

---

## 7. Rutas

### 7.1

¿Quién puede crear rutas?

Respuesta: los comerciales

### 7.2

¿Quién puede modificar una ruta ya asignada?

Respuesta: el comercial que las creo

### 7.3

¿El repartidor puede solo adaptarla durante ejecución o también reordenarla ampliamente?

Respuesta: no deberia poder manipularlas en demasia, podemos limitar a que no pueda modificarla por lo pronto.

### 7.4

¿Una ruta pertenece principalmente al comercial que la planifica o al operador que la ejecuta?

Respuesta: a ambos pero el principal es el comercial

### 7.5

¿Quieres plantillas reutilizables desde el inicio o primero solo rutas programadas?

Respuesta: plantillas reutilizables desde el inicio

---

## 8. Paradas

### 8.1

¿Una parada debe poder existir sin cliente desde el MVP?

Respuesta: si, no es obligatorio que la parada sea un cliente, puede ser un prospecto o una direccion interesante sin mas .

### 8.2

¿Quieres que una parada pueda apuntar a prospecto real desde el principio?

Respuesta: si

### 8.3

¿La parada es solo una unidad operativa o también quieres usarla como unidad principal de reporting?

Respuesta: tambien se puede usar como reporte claro. para obtener datos de esa parada si es que los hay

### 8.4

¿Qué resultados de parada son obligatorios registrar manualmente y cuáles quieres inferir automáticamente?

Respuesta: no estoy muy seguro

### 8.5

¿Quieres permitir varias acciones en una misma parada, por ejemplo entrega más autoventa?

Respuesta: no creo que sea necesario de primeras. Esa parada sera para llevar un pedido prefijado, para intentar hacer autoventa o para prospección como punto de interes

---

## 9. Visibilidad del actor operativo

### 9.1

¿El repartidor debe ver solo ficha ligera del cliente o también contexto operativo ampliado?

Respuesta: no debe ver nada sobre el cliente solo usarlo como options para una autoventa o verlo en pedidos prefijados que deba rellenar.

### 9.2

¿Debe ver historial de pedidos previos del cliente?

Respuesta: no

### 9.3

¿Debe ver precios históricos o solo precios vigentes?

Respuesta: no

### 9.4

¿Debe ver notas logísticas, notas comerciales, ambas o ninguna?

Respuesta: no

### 9.5

¿Debe poder buscar clientes fuera de su ruta si tiene acceso operativo?

Respuesta: si claro

---

## 10. CRM

### 10.1

¿Confirmas que el actor operativo no debe tocar nunca prospectos?

Respuesta: claro , no tiene prospectos, los prospectos son del comercial y no tienen nada que ver con el repartidor.

### 10.2

¿Confirmas que el actor operativo no debe tocar nunca interacciones comerciales?

Respuesta: claro que no

### 10.3

¿Confirmas que el actor operativo no debe tocar nunca agenda CRM?

Respuesta: claro

### 10.4

¿Confirmas que el actor operativo no debe tocar nunca ofertas?

Respuesta: claro

### 10.5

¿Quieres alguna excepción futura, por ejemplo una nota operativa no CRM visible para el comercial?

Respuesta: no, la unica interaccion sera mediante el reporte de la parada

### 10.6

¿Quieres que el comercial reciba alguna alerta cuando un operador cree un cliente o haga una autoventa sobre un cliente suyo?

Respuesta:  no es necesario , lo vera en sus pedidos ya que están asignados a el.

---

## 11. Reporting

### 11.1

¿Qué quieres medir por separado desde el principio?

Respuesta: nose bien

### 11.2

¿Quieres separar en informes owner comercial, creador del pedido, ejecutor operativo y creador del cliente?

Respuesta: si

### 11.3

¿Qué dimensión debe mandar en ventas: comercial, operador o ambas según informe?

Respuesta: ambos segun el informe

### 11.4

¿Quieres reporting por ruta y por parada desde el MVP o más adelante?

Respuesta: si

---

## 12. Permisos y transición

### 12.1

¿Prefieres una transición larga con convivencia del flujo actual y el nuevo?

Respuesta: no te entiendo

### 12.2

¿Qué cosas no estás dispuesto a que cambien en la UX del comercial actual?

Respuesta: no se, deberia valorarla una por una

### 12.3

¿Qué cosas no estás dispuesto a que vea el repartidor bajo ningún concepto?

Respuesta: el repartidor solo podra ver el apartado para crear una autoventa unido con crear un cliente online y otro apartado con los pedidos prefijados para rellenarlos además de un apartado para las rutas.

### 12.4

¿Qué partes del sistema aceptarías dejar temporalmente legacy mientras entra el nuevo modelo?

Respuesta: no te entiendo.

---

## 13. Decisiones que cambian mucho la implementación

### 13.1

¿El actor operativo puede trabajar sin ruta o la ruta debe ser el contenedor obligatorio de su trabajo?

Respuesta:  puede trabajar sin ruta, y aunque tenga ruta es una guia no algo forzado.

### 13.2

¿El acceso operativo al cliente puede concederse sin ruta?

Respuesta:  si claro, la ruta solo es una guia

### 13.3

¿Una autoventa nacida en ruta debe quedar ligada obligatoriamente a una parada?

Respuesta: no, la ruta es una guia , eso tenemos que dejarlo claro, puede dejarse constancia de que se rellizo el pedido prefijado o la autoventa para esa parada pero no debe estar tan fueremente vinculada.

### 13.4

¿Un cliente creado en autoventa debe entrar en una cola de revisión antes de reutilizarse fuera de esa ruta?

Respuesta: no, además no se crea el cliente en una ruta, se crea el cliente en general.

---

## 14. Preguntas de cierre duro

### 14.1

¿Cuál es la diferencia funcional mínima que quieres que exista entre comercial CRM y repartidor/autoventa el día 1?

Respuesta: ya esta más que detallado eso

### 14.2

¿Qué error sería peor para negocio: dar demasiado acceso al repartidor o dejarle demasiado limitado para operar?

Respuesta: dar demasiado acceso, solo debe poder hacer autoventas, completar pedidos y ver rutas o añadir info en las paradas.

### 14.3

Si hubiera que simplificar mucho el MVP, ¿qué tres capacidades son irrenunciables?

Respuesta: -

### 14.4

Si hubiera que retrasar algo para no equivocarnos, ¿qué preferirías retrasar?

Respuesta: Rutas (pero no lo veo evitable, porque si lo tratamos simplemente como una guia sin vinculaciones fuertes con la operativa no debe suponer problemas)