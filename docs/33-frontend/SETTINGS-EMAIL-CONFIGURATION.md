# Frontend - Configuración de Email en Settings

## 📋 Resumen

Se ha añadido la configuración completa de email por tenant en el módulo de Settings. Ahora cada tenant puede configurar su propio servidor SMTP, credenciales y remitente de emails.

---

## 🆕 Nuevos Campos de Configuración

### Campos Añadidos

El frontend debe contemplar los siguientes nuevos campos en la sección de configuración de email:

| Campo | Clave en API | Tipo | Requerido | Descripción |
|-------|--------------|------|-----------|-------------|
| **Mailer** | `company.mail.mailer` | `string` | Sí | Tipo de mailer (por defecto: `'smtp'`) |
| **Host SMTP** | `company.mail.host` | `string` | Sí* | Servidor SMTP (ej: `smtp.gmail.com`, `smtp.mailgun.org`) |
| **Puerto** | `company.mail.port` | `string` | Sí | Puerto SMTP (por defecto: `'587'`) |
| **Encriptación** | `company.mail.encryption` | `string` | Sí | Tipo de encriptación: `'tls'` o `'ssl'` (por defecto: `'tls'`) |
| **Usuario** | `company.mail.username` | `string` | Sí* | Usuario/email para autenticación SMTP |
| **Contraseña** | `company.mail.password` | `string` | Sí* | Contraseña SMTP (campo sensible) |
| **Email Remitente** | `company.mail.from_address` | `string` | Sí* | Dirección de email desde la que se envían los correos |
| **Nombre Remitente** | `company.mail.from_name` | `string` | No | Nombre que aparece como remitente (por defecto: nombre de la empresa) |

\* *Requerido solo si se configura email personalizado. Si está vacío, se usará la configuración global del sistema.*

---

## 📡 API Endpoints

### Obtener Configuración

**GET** `/api/v2/settings`

**Respuesta**:
```json
{
  "company.name": "Congelados Brisamar S.L.",
  "company.cif": "B21573282",
  "company.mail.mailer": "smtp",
  "company.mail.host": "",
  "company.mail.port": "587",
  "company.mail.encryption": "tls",
  "company.mail.username": "",
  "company.mail.password": "",
  "company.mail.from_address": "",
  "company.mail.from_name": "",
  ...
}
```

**Nota**: La API retorna todas las configuraciones en un solo objeto. El frontend debe filtrar las que empiezan con `company.mail.` para mostrar la sección de email.

### Actualizar Configuración

**PUT** `/api/v2/settings`

**Request Body**:
```json
{
  "company.mail.mailer": "smtp",
  "company.mail.host": "smtp.gmail.com",
  "company.mail.port": "587",
  "company.mail.encryption": "tls",
  "company.mail.username": "noreply@empresa.com",
  "company.mail.password": "contraseña_segura",
  "company.mail.from_address": "noreply@empresa.com",
  "company.mail.from_name": "Mi Empresa S.L."
}
```

**Respuesta**:
```json
{
  "message": "Settings updated"
}
```

---

## 🎨 Recomendaciones de UI/UX

### Estructura de Formulario

Se recomienda crear una sección dedicada **"Configuración de Email"** dentro de Settings con:

1. **Toggle o Checkbox**: "Usar configuración de email personalizada"
   - Si está desactivado: mostrar mensaje informativo indicando que se usa la configuración global
   - Si está activado: mostrar todos los campos de configuración

2. **Agrupación de campos**:
   - **Servidor SMTP**: Host, Puerto, Encriptación
   - **Credenciales**: Usuario, Contraseña
   - **Remitente**: Email remitente, Nombre remitente

3. **Campos con validación**:
   - **Host**: Validar formato de hostname o IP
   - **Puerto**: Número entre 1-65535
   - **Encriptación**: Select con opciones `tls` y `ssl`
   - **Username**: Validar formato de email
   - **Password**: Campo tipo password con opción de mostrar/ocultar
   - **From Address**: Validar formato de email
   - **From Name**: Texto libre

### Validaciones Recomendadas

```javascript
// Ejemplo de validaciones (pseudocódigo)
const validations = {
  'company.mail.host': {
    required: true, // Si se activa configuración personalizada
    pattern: /^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/, // Hostname válido
    message: 'Debe ser un hostname válido (ej: smtp.gmail.com)'
  },
  'company.mail.port': {
    required: true,
    type: 'number',
    min: 1,
    max: 65535,
    message: 'El puerto debe ser un número entre 1 y 65535'
  },
  'company.mail.encryption': {
    required: true,
    enum: ['tls', 'ssl'],
    message: 'Debe ser TLS o SSL'
  },
  'company.mail.username': {
    required: true, // Si se activa configuración personalizada
    type: 'email',
    message: 'Debe ser un email válido'
  },
  'company.mail.password': {
    required: true, // Si se activa configuración personalizada
    minLength: 1,
    message: 'La contraseña es requerida'
  },
  'company.mail.from_address': {
    required: true, // Si se activa configuración personalizada
    type: 'email',
    message: 'Debe ser un email válido'
  },
  'company.mail.from_name': {
    required: false,
    maxLength: 255,
    message: 'Máximo 255 caracteres'
  }
};
```

### Consideraciones de Seguridad

1. **Campo de Contraseña**:
   - Siempre usar input tipo `password` por defecto
   - Opción de "mostrar/ocultar" contraseña
   - **No mostrar la contraseña actual** (solo permitir cambiarla)
   - Si el usuario no cambia la contraseña, no enviar el campo en el PUT (o enviar string vacío si se quiere limpiar)

2. **Mensajes Informativos**:
   - Explicar que si los campos están vacíos, se usará la configuración global del sistema
   - Advertir sobre la seguridad de las credenciales
   - Sugerir usar contraseñas de aplicación si es Gmail/Outlook

3. **Test de Conexión** (Opcional pero recomendado):
   - Botón "Probar configuración" que envíe un email de prueba
   - Mostrar resultado (éxito/error) antes de guardar

---

## 💻 Ejemplo de Implementación

### Estructura de Datos en Frontend

```typescript
interface EmailSettings {
  mailer: string;           // 'smtp'
  host: string;             // 'smtp.gmail.com'
  port: string;             // '587'
  encryption: 'tls' | 'ssl'; // 'tls'
  username: string;         // 'noreply@empresa.com'
  password: string;         // '***' (no mostrar completo)
  from_address: string;     // 'noreply@empresa.com'
  from_name: string;       // 'Mi Empresa S.L.'
}

interface SettingsResponse {
  [key: string]: string; // Todas las configuraciones
}
```

### Función para Extraer Email Settings

```typescript
function extractEmailSettings(settings: SettingsResponse): EmailSettings {
  return {
    mailer: settings['company.mail.mailer'] || 'smtp',
    host: settings['company.mail.host'] || '',
    port: settings['company.mail.port'] || '587',
    encryption: (settings['company.mail.encryption'] || 'tls') as 'tls' | 'ssl',
    username: settings['company.mail.username'] || '',
    password: settings['company.mail.password'] || '', // ⚠️ Puede estar vacío si no se ha configurado
    from_address: settings['company.mail.from_address'] || '',
    from_name: settings['company.mail.from_name'] || '',
  };
}
```

### Función para Preparar Payload de Actualización

```typescript
function prepareEmailSettingsPayload(
  emailSettings: EmailSettings,
  isCustomEnabled: boolean
): Partial<SettingsResponse> {
  const payload: Partial<SettingsResponse> = {};
  
  // Solo enviar campos si la configuración personalizada está activada
  if (isCustomEnabled) {
    payload['company.mail.mailer'] = emailSettings.mailer;
    payload['company.mail.host'] = emailSettings.host;
    payload['company.mail.port'] = emailSettings.port;
    payload['company.mail.encryption'] = emailSettings.encryption;
    payload['company.mail.username'] = emailSettings.username;
    
    // Solo enviar password si se ha cambiado (no está vacío)
    if (emailSettings.password) {
      payload['company.mail.password'] = emailSettings.password;
    }
    
    payload['company.mail.from_address'] = emailSettings.from_address;
    payload['company.mail.from_name'] = emailSettings.from_name;
  } else {
    // Si se desactiva, limpiar todos los campos
    payload['company.mail.host'] = '';
    payload['company.mail.username'] = '';
    payload['company.mail.password'] = '';
    payload['company.mail.from_address'] = '';
    payload['company.mail.from_name'] = '';
  }
  
  return payload;
}
```

### Ejemplo de Componente React/Vue

```tsx
// Pseudocódigo - Adaptar según framework
function EmailSettingsForm() {
  const [settings, setSettings] = useState<EmailSettings>({...});
  const [isCustomEnabled, setIsCustomEnabled] = useState(false);
  
  // Verificar si hay configuración personalizada
  useEffect(() => {
    const hasCustomConfig = settings.host && settings.username && settings.from_address;
    setIsCustomEnabled(!!hasCustomConfig);
  }, [settings]);
  
  const handleSubmit = async () => {
    const payload = prepareEmailSettingsPayload(settings, isCustomEnabled);
    
    // Incluir otros campos de settings si es necesario
    const fullPayload = {
      ...otherSettings,
      ...payload
    };
    
    await fetch('/api/v2/settings', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'X-Tenant': tenantSubdomain
      },
      body: JSON.stringify(fullPayload)
    });
  };
  
  return (
    <form>
      <Toggle
        label="Usar configuración de email personalizada"
        checked={isCustomEnabled}
        onChange={setIsCustomEnabled}
      />
      
      {isCustomEnabled && (
        <>
          <Input
            label="Servidor SMTP"
            value={settings.host}
            onChange={(v) => setSettings({...settings, host: v})}
            placeholder="smtp.gmail.com"
            required
          />
          
          <Input
            label="Puerto"
            type="number"
            value={settings.port}
            onChange={(v) => setSettings({...settings, port: v})}
            min={1}
            max={65535}
            required
          />
          
          <Select
            label="Encriptación"
            value={settings.encryption}
            onChange={(v) => setSettings({...settings, encryption: v})}
            options={[
              { value: 'tls', label: 'TLS' },
              { value: 'ssl', label: 'SSL' }
            ]}
            required
          />
          
          <Input
            label="Usuario/Email"
            type="email"
            value={settings.username}
            onChange={(v) => setSettings({...settings, username: v})}
            placeholder="noreply@empresa.com"
            required
          />
          
          <Input
            label="Contraseña"
            type="password"
            value={settings.password}
            onChange={(v) => setSettings({...settings, password: v})}
            placeholder="••••••••"
            required
            showPasswordToggle
          />
          
          <Input
            label="Email Remitente"
            type="email"
            value={settings.from_address}
            onChange={(v) => setSettings({...settings, from_address: v})}
            placeholder="noreply@empresa.com"
            required
          />
          
          <Input
            label="Nombre Remitente"
            value={settings.from_name}
            onChange={(v) => setSettings({...settings, from_name: v})}
            placeholder={companyName} // Usar nombre de empresa como placeholder
          />
        </>
      )}
      
      {!isCustomEnabled && (
        <InfoMessage>
          Se está usando la configuración global del sistema.
          Activa la opción para configurar un servidor SMTP personalizado.
        </InfoMessage>
      )}
      
      <Button onClick={handleSubmit}>Guardar Configuración</Button>
    </form>
  );
}
```

---

## 🔍 Valores por Defecto

Si los campos están vacíos o no se han configurado, el sistema usará los valores por defecto:

- `company.mail.mailer`: `'smtp'`
- `company.mail.port`: `'587'`
- `company.mail.encryption`: `'tls'`
- `company.mail.host`: `''` (vacío - usa configuración global)
- `company.mail.username`: `''` (vacío - usa configuración global)
- `company.mail.password`: `''` (vacío - usa configuración global)
- `company.mail.from_address`: `''` (vacío - usa configuración global)
- `company.mail.from_name`: `''` (vacío - usa nombre de empresa desde `company.name`)

---

## 📝 Notas Importantes

1. **Actualización Parcial**: La API acepta actualizaciones parciales. Puedes enviar solo los campos que quieras actualizar.

2. **Contraseña**: Si el usuario no cambia la contraseña, puedes:
   - No incluir el campo en el payload (recomendado)
   - O enviar string vacío si quieres limpiarla

3. **Validación en Backend**: Actualmente el backend **no valida** los campos. El frontend debe hacer todas las validaciones necesarias antes de enviar.

4. **Compatibilidad**: Los campos ya están seedeados en todos los tenants existentes. Si un tenant no tiene estos campos, se usarán los valores por defecto.

5. **Fallback**: Si un campo no está configurado, el sistema usará la configuración global de `config/mail.php` y `.env`.

---

## 🧪 Testing

### Casos de Prueba Recomendados

1. **Configuración vacía**: Verificar que se muestre mensaje de uso de configuración global
2. **Configuración completa**: Verificar que todos los campos se guarden correctamente
3. **Validación de email**: Verificar que se rechacen emails inválidos
4. **Validación de puerto**: Verificar que solo acepte números 1-65535
5. **Campo contraseña**: Verificar que no se muestre la contraseña actual, solo permitir cambiarla
6. **Toggle on/off**: Verificar que al desactivar se limpien los campos
7. **Actualización parcial**: Verificar que se puedan actualizar solo algunos campos

---

## 📚 Referencias

- **API Endpoint**: `GET /api/v2/settings` y `PUT /api/v2/settings`
- **Documentación Backend**: `docs/28-sistema/84-Configuracion.md`
- **Controlador**: `app/Http/Controllers/v2/SettingController.php`
- **Configuración por defecto**: `config/company.php`

