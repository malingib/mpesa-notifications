<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\PaymentAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create sample tenant
        $tenant = Tenant::create([
            'name' => 'Sample Client',
            'code' => 'SAMPLE001',
            'is_active' => true,
            'settings' => [
                'timezone' => 'Africa/Nairobi',
            ],
        ]);

        // Create sample payment accounts
        PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'account_type' => 'paybill',
            'account_number' => '123456',
            'account_name' => 'Sample Paybill',
            'is_active' => true,
            'sms_template' => [
                'message' => 'Payment of KES {amount} received. Receipt: {receipt}. Thank you!',
            ],
        ]);

        PaymentAccount::create([
            'tenant_id' => $tenant->id,
            'account_type' => 'till',
            'account_number' => '987654',
            'account_name' => 'Sample Till',
            'is_active' => true,
        ]);
    }
}
