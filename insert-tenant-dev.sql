USE pesquerapp;
-- Crear tenant dev si no existe (esquema actual: status, sin active)
INSERT INTO tenants (name, subdomain, `database`, status, created_at, updated_at)
VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE status = 'active', updated_at = NOW();
-- Asegurar que dev esté siempre activo
UPDATE tenants SET status = 'active', updated_at = NOW() WHERE subdomain = 'dev';
