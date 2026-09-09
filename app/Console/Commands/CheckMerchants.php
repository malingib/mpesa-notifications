<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;

class CheckMerchants extends Command
{
    protected $signature = 'merchants:check';
    protected $description = 'Check all merchants in the database';

    public function handle()
    {
        $merchants = Merchant::withoutGlobalScopes()->get();
        
        if ($merchants->isEmpty()) {
            $this->error('No merchants found in database!');
            $this->info('Make sure you have saved your payment settings in the UI.');
            return 1;
        }

        $this->info('Found ' . $merchants->count() . ' merchant(s):');
        $this->newLine();

        $headers = ['ID', 'User ID', 'Account Type', 'Account Number', 'Account Name', 'Active', 'Created At'];
        $rows = [];

        foreach ($merchants as $merchant) {
            $rows[] = [
                $merchant->id,
                $merchant->user_id,
                $merchant->account_type,
                $merchant->account_number,
                $merchant->account_name ?? 'N/A',
                $merchant->is_active ? 'Yes' : 'No',
                $merchant->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }
}
