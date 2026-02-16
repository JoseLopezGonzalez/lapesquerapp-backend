# Frontend Settings - Resumen: Configuración de Email

## 🆕 Nuevos Campos

Añadir sección **"Configuración de Email"** en Settings con estos campos:

| Campo | Clave API | Tipo | Requerido |
|-------|-----------|------|-----------|
| Servidor SMTP | `company.mail.host` | string | Sí* |
| Puerto | `company.mail.port` | string | Sí (default: `'587'`) |
| Encriptación | `company.mail.encryption` | select | Sí (opciones: `'tls'`, `'ssl'`) |
| Usuario | `company.mail.username` | email | Sí* |
| Contraseña | `company.mail.password` | password | Sí* |
| Email Remitente | `company.mail.from_address` | email | Sí* |
| Nombre Remitente | `company.mail.from_name` | string | No |

\* *Requerido solo si se activa "configuración personalizada"*

## 📡 API

**GET** `/api/v2/settings` → Retorna objeto con todas las configuraciones
- Filtrar claves que empiezan con `company.mail.`

**PUT** `/api/v2/settings` → Enviar objeto con las claves a actualizar
```json
{
  "company.mail.host": "smtp.gmail.com",
  "company.mail.port": "587",
  "company.mail.encryption": "tls",
  "company.mail.username": "noreply@empresa.com",
  "company.mail.password": "contraseña",
  "company.mail.from_address": "noreply@empresa.com",
  "company.mail.from_name": "Mi Empresa"
}
```

## 🎨 UI Recomendada

1. **Toggle**: "Usar configuración de email personalizada"
2. **Si activado**: Mostrar todos los campos
3. **Si desactivado**: Mensaje "Usando configuración global del sistema"
4. **Campo contraseña**: Tipo password, no mostrar valor actual, solo permitir cambiarlo

## ⚠️ Validaciones Frontend

- **Host**: Hostname válido (ej: `smtp.gmail.com`)
- **Puerto**: Número 1-65535
- **Encriptación**: Solo `'tls'` o `'ssl'`
- **Username/From Address**: Formato email válido
- **Password**: No enviar si está vacío (no cambiar)

## 💡 Lógica

```typescript
// Extraer campos de email
const emailSettings = {
  host: settings['company.mail.host'] || '',
  port: settings['company.mail.port'] || '587',
  encryption: settings['company.mail.encryption'] || 'tls',
  username: settings['company.mail.username'] || '',
  password: '', // ⚠️ No mostrar valor actual
  from_address: settings['company.mail.from_address'] || '',
  from_name: settings['company.mail.from_name'] || '',
};

// Verificar si hay config personalizada
const hasCustomConfig = emailSettings.host && emailSettings.username;

// Preparar payload (solo enviar password si se cambió)
const payload = {
  'company.mail.host': emailSettings.host,
  'company.mail.port': emailSettings.port,
  // ... otros campos
  // Solo incluir password si tiene valor nuevo
  ...(emailSettings.password && { 'company.mail.password': emailSettings.password })
};
```

## 📋 Valores por Defecto

Si campos vacíos → Sistema usa configuración global (`config/mail.php`)

- `mailer`: `'smtp'`
- `port`: `'587'`
- `encryption`: `'tls'`

---

**Documentación completa**: Ver `SETTINGS-EMAIL-CONFIGURATION.md`

