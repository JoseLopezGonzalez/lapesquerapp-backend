# Patrones de Integración — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-15

---

## 1. Alcance

Se entiende por "integraciones" tanto las **salidas** del backend (envío de correo, generación de PDFs, posibles llamadas a servicios externos) como la **exposición** de la API hacia el frontend Next.js y cualquier otro consumidor.

---

## 2. API REST (consumida por el frontend)

- **Contrato**: API v2 bajo prefijo `api/v2`, autenticación con Sanctum (Bearer token), identificación de tenant vía header `X-Tenant`.
- **Patrones observados**:
  - Recursos REST estándar: `apiResource` para la mayoría de entidades (orders, products, customers, productions, punches, etc.).
  - Endpoints adicionales para opciones (desplegables), reportes (Excel/PDF), acciones específicas (cambiar estado, enviar documentos, bulk).
  - Respuestas JSON homogéneas: recursos con API Resources, errores con `message`, `userMessage` y opcionalmente `errors`.
- **Validación**: Form Requests en prácticamente toda la API (137 clases).
- **Autorización**: Policies aplicadas en la mayoría de recursos críticos.
- **Documentación**: No hay OpenAPI/Swagger en el repo. La documentación de arquitectura describe el flujo multi-tenant y la necesidad de `X-Tenant`, pero no un catálogo formal de endpoints y contratos.
- **Recomendación**: Documentar convenciones (paginación, filtros, códigos de error) y, si es posible, generar o mantener OpenAPI para v2.

---

## 3. Correo (Mail)

- **Patrón**: Laravel Mail con configuración dinámica por tenant.
- **TenantMailConfigService**: Ajusta la configuración de correo (host, puerto, usuario, contraseña) a partir de la tabla `settings` del tenant antes de enviar. Permite que cada empresa tenga su propio SMTP.
- **SettingService**: Encapsula acceso a settings; ofusca contraseña en respuestas GET.
- **OrderMailerService**: Envío de documentos (PDFs) asociados a pedidos; usa OrderPDFService y TenantMailConfigService. Invocado desde controladores (OrderDocumentController).
- **Riesgo**: Si en el futuro el envío se mueve a una cola, el job debe ejecutarse con el contexto del tenant (conexión y configuración de correo) correctamente establecido.

---

## 4. Generación de PDFs y exportaciones

- **PDF**: OrderPDFService y controladores (PDFController) generan hojas de pedido, albaranes, CMR, etc., usando vistas Blade y librerías (dompdf, snappy, etc.). Se generan bajo demanda en el request; no hay cola para generación asíncrona.
- **Excel**: Exportaciones vía Maatwebsite/Excel (A3ERP, Facilcom, listados de cajas, etc.) desde ExcelController y controladores de recursos. Patrón consistente: endpoint GET que devuelve el archivo.
- **Integridad**: Las exportaciones leen de la conexión tenant implícita (modelos con UsesTenantConnection); no se ha detectado riesgo de mezcla de datos entre tenants.

---

## 5. Integraciones externas (n8n, Document AI)

- En el código PHP del backend **no** se han encontrado referencias directas a n8n, webhooks salientes ni al cliente de Google Document AI en uso. Es posible que Document AI o n8n se usen desde otro servicio o desde jobs que aún no existen en el proyecto.
- **Recomendación**: Si el backend debe llamar a n8n o a Document AI en el futuro, encapsular las llamadas en servicios/clientes que reciban el contexto tenant si afecta a la configuración o a los datos, y considerar colas con payload que incluya el tenant.

---

## 6. Autenticación y sesión

- **Sanctum**: Tokens de API para el frontend; no hay sesión web con cookies en el flujo revisado.
- **Magic link y OTP**: Flujos de acceso sin contraseña (MagicLinkService, endpoints de auth). Tokens de magic link con limpieza programada (`auth:cleanup-magic-tokens`).
- **Roles**: El middleware `role:tecnico,administrador,...` restringe por rol a nivel de ruta; las Policies complementan con autorización por recurso.

---

## 7. Resumen

| Integración | Patrón | Observaciones |
|-------------|--------|---------------|
| API v2 (frontend) | REST + Sanctum + X-Tenant + Form Requests + Policies | Consistente; falta documentación formal (OpenAPI). |
| Correo | Mail + TenantMailConfigService + SettingService | Configuración por tenant correcta; definir contexto tenant si se pasan envíos a cola. |
| PDF / Excel | Síncrono en request | Adecuado para uso actual; si crece carga, valorar cola con tenant en payload. |
| n8n / Document AI | No localizado en backend | Aclarar dónde se usan; si entran al backend, definir servicios y posible cola con tenant. |

Las integraciones actuales son coherentes con el modelo request/response y multi-tenant. La principal mejora pendiente es documentación formal de la API (OpenAPI) y preparar convención de tenant para cualquier trabajo asíncrono futuro.
