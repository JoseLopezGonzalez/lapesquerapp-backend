USE pesquerapp;
-- Crear tenant dev si no existe
INSERT IGNORE INTO tenants (name, subdomain, `database`, active, created_at, updated_at)
VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 1, NOW(), NOW());
-- Asegurar que dev esté siempre activo (GET /api/v2/public/tenant/dev devuelve active: true)
UPDATE tenants SET active = 1, updated_at = NOW() WHERE subdomain = 'dev';
