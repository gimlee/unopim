<?php

namespace Webkul\AdminApi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Webkul\AdminApi\Models\Apikey;
use Webkul\AdminApi\Models\Client;
use Webkul\User\Models\Admin;

class PimIntegrationProvisionCommand extends Command
{
    protected $signature = 'unopim:integration:pim-provision';

    protected $description = 'Idempotently provision the Product Info Management REST API client';

    public function handle(): int
    {
        $config = (array) config('services.product_info_management.unopim_client', []);
        $clientId = trim((string) ($config['id'] ?? ''));
        $clientSecret = trim((string) ($config['secret'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');

        if ($clientId === '' || $clientSecret === '' || $username === '' || $password === '') {
            throw new RuntimeException('PIM integration credentials are incomplete in UnoPIM .env.');
        }

        $admin = Admin::query()->firstOrNew(['email' => $username]);
        $admin->forceFill([
            'name'     => 'Product Info Management (API)',
            'password' => Hash::make($password),
            'status'   => 1,
            'type'     => 'api',
            'role_id'  => null,
        ])->save();

        $client = Client::query()->find($clientId) ?: new Client(['id' => $clientId]);
        $client->forceFill([
            'id'                     => $clientId,
            'name'                   => 'Product Info Management Local',
            'secret'                 => $clientSecret,
            'provider'               => 'admins',
            'redirect'               => '',
            'redirect_uris'          => [],
            'grant_types'            => ['password', 'refresh_token'],
            'personal_access_client' => false,
            'password_client'        => true,
            'revoked'                => false,
            'user_id'                => $admin->id,
            'owner_type'             => Admin::class,
            'owner_id'               => $admin->id,
        ])->save();

        Apikey::query()->updateOrCreate(
            ['name' => 'Product Info Management Local'],
            [
                'admin_id'        => $admin->id,
                'oauth_client_id' => $client->getKey(),
                'permission_type' => 'all',
                'permissions'     => [],
                'revoked'         => false,
            ],
        );

        $this->components->info('Product Info Management REST API integration is ready.');

        return self::SUCCESS;
    }
}
