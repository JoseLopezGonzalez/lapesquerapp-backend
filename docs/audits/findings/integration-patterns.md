# Patrones de Integración — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-14

---

## 1. Alcance

Se entiende por “integraciones” tanto las **salidas** del backend (envío de correo, generación de PDFs, posibles llamadas a servicios externos) como la **exposición** de la API hacia el frontend Next.js y cualquier otro consumidor. No se ha revisado en el código backend la existencia de clientes HTTP hacia n8n o Google Document AI; se asume que el procesamiento de documentos (n8n) puede estar en otro servicio o en el frontend.

---

## 2. API REST (consumida por el frontend)

- **Contrato**: API v2 bajo prefijo `api/v2`, autenticación con Sanctum (Bearer token), identificación de tenant vía header `X-Tenant`.
- **Patrones observados**:
  - Recursos REST estándar: `apiResource` para la mayoría de entidades (orders, products, customers, productions, etc.).
  - Endpoints adicionales para opciones (desplegables), reportes (Excel/PDF), acciones específicas (cambiar estado, enviar documentos, bulk).
  - Respuestas JSON homogéneas: recursos con API Resources, errores con `message`, `userMessage` y opcionalmente `errors`.
- **Documentación**: No hay OpenAPI/Swagger en el repo; la documentación de arquitectura describe el flujo multi-tenant y la necesidad de `X-Tenant`, pero no un catálogo de endpoints y contratos.
- **Recomendación**: Documentar convenciones (paginación, filtros, códigos de error) y, si es posible, generar o mantener un OpenAPI para v2 para facilitar evolución del frontend y de posibles integraciones externas.

---

## 3. Correo (Mail)

- **Patrón**: Uso de Laravel Mail y `Mail::` con configuración dinámica por tenant.
- **TenantMailConfigService**: Ajusta la configuración de correo (host, puerto, usuario, contraseña, etc.) a partir de la tabla `settings` del tenant antes de enviar. Permite que cada empresa tenga su propio SMTP.
- **OrderMailerService**: Envío de documentos (PDFs) asociados a pedidos; usa OrderPDFService y TenantMailConfigService. Invocado desde controladores (OrderDocumentController).
- **Riesgo**: Si en el futuro el envío se mueve a una cola, el job debe ejecutarse con el contexto del tenant (conexión y configuración de correo) correctamente establecido; si no, podría usarse la configuración por defecto o de otro tenant.

---

## 4. Generación de PDFs y exportaciones

- **PDF**: OrderPDFService y controladores (PDFController) generan hojas de pedido, albaranes, CMR, etc., usando vistas Blade y librerías (dompdf, snappy, etc.). Se generan bajo demanda en el request; no se ha visto cola para generación asíncrona.
- **Excel**: Exportaciones vía Maatwebsite/Excel (A3ERP, Facilcom, listados de cajas, etc.) desde ExcelController y controladores de recursos. Patrón consistente: endpoint GET que devuelve el archivo.
- **Integridad**: Las exportaciones leen de la conexión tenant implícita (modelos con UsesTenantConnection); no se ha detectado riesgo de mezcla de datos entre tenants en estos flujos.

---

## 5. Integraciones externas (n8n, Document AI)

- En el código PHP del backend **no** se han encontrado referencias a n8n, webhooks salientes ni al cliente de Google Document AI (aunque `google/cloud-document-ai` está en composer.json). Es posible que:
  - Document AI se use desde otro servicio o desde jobs no presentes en el árbol revisado, o
  - n8n orqueste flujos que llamen al backend (webhooks entrantes) en lugar de ser llamados por el backend.
- **Recomendación**: Si el backend debe llamar a n8n o a Document AI en el futuro, encapsular las llamadas en servicios/clientes que reciban el contexto tenant si afecta a la configuración o a los datos, y considerar colas con payload que incluya el tenant para no depender del request.

---

## 6. Autenticación y sesión

- **Sanctum**: Tokens de API para el frontend; no hay sesión web con cookies en el flujo revisado.
- **Magic link y OTP**: Flujos de acceso sin contraseña (MagicLinkService, endpoints de auth). Tokens de magic link con limpieza programada (`auth:cleanup-magic-tokens`).
- **Roles**: El middleware `role:tecnico,administrador,...` restringe por rol a nivel de ruta; no hay integración con sistemas externos de identidad (SSO/LDAP) en el código revisado.

---

## 7. Resumen

| Integración | Patrón | Observaciones |
|-------------|--------|----------------|
| API v2 (frontend) | REST + Sanctum + X-Tenant | Consistente; falta documentación formal de contratos. |
| Correo | Mail + TenantMailConfigService | Configuración por tenant correcta; definir contexto tenant si se pasan envíos a cola. |
| PDF / Excel | Síncrono en request | Adecuado para uso actual; si crece carga, valorar cola con tenant en payload. |
| n8n / Document AI | No localizado en backend | Aclarar dónde se usan; si entran al backend, definir servicios y posible cola con tenant. |

Las integraciones actuales son coherentes con el modelo request/response y multi-tenant; la principal mejora es documentar la API y preparar convención de tenant para cualquier trabajo asíncrono o llamada saliente que dependa del tenant.
