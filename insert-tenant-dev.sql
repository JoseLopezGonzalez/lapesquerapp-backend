USE pesquerapp;
INSERT IGNORE INTO tenants (name, subdomain, `database`, active, created_at, updated_at)
VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 1, NOW(), NOW());
