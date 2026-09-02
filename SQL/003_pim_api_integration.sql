-- Product Info Management integration verification.
-- Provisioning writes these rows idempotently through:
--   php artisan unopim:integration:pim-provision

SELECT id, email, status, type
FROM admins
WHERE email = 'pim-api@local.test';

SELECT id, name, provider, password_client, revoked, owner_type, owner_id
FROM oauth_clients
WHERE id = '16881688-0000-4000-8000-000000000001';

SELECT id, name, admin_id, oauth_client_id, permission_type, revoked
FROM api_keys
WHERE name = 'Product Info Management Local';
