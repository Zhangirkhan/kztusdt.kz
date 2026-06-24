<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ExchangeSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'super_admin', 'name' => 'Суперадмин'],
            ['code' => 'security_officer', 'name' => 'СБ'],
            ['code' => 'super_admin_manager', 'name' => 'Менеджер суперадминки'],
            ['code' => 'exchange_admin', 'name' => 'Админ обменника'],
            ['code' => 'exchange_client', 'name' => 'Клиент обменника'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['code' => $role['code']], $role);
        }

        $superAdminRole = Role::query()->where('code', 'super_admin')->firstOrFail();
        $securityRole = Role::query()->where('code', 'security_officer')->firstOrFail();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@exchange.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('ChangeMeNow!2026'),
                'phone' => '+70000000001',
                'phone_verified' => true,
                'kyc_status' => 'approved',
            ],
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $admin->id, 'role_id' => $superAdminRole->id, 'tenant_id' => null, 'exchange_point_id' => null],
            [],
        );

        $security = User::query()->updateOrCreate(
            ['email' => 'security@exchange.local'],
            [
                'name' => 'Security Officer',
                'password' => Hash::make('ChangeMeNow!2026'),
                'phone' => '+70000000002',
                'phone_verified' => true,
                'kyc_status' => 'approved',
            ],
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $security->id, 'role_id' => $securityRole->id, 'tenant_id' => null, 'exchange_point_id' => null],
            [],
        );
    }
}
