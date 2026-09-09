<?php

namespace App\Console\Commands;

use App\Services\RetentionPolicyService;
use Illuminate\Console\Command;

/**
 * Run Retention Policies Command
 * 
 * Executes data retention policies (archival, anonymization, deletion).
 */
class RunRetentionPoliciesCommand extends Command
{
    protected $signature = 'retention:run 
                            {--archive : Run archival only}
                            {--anonymize : Run anonymization only}
                            {--delete : Run deletion only}';

    protected $description = 'Run data retention policies (archival, anonymization, deletion)';

    public function __construct(
        private RetentionPolicyService $retentionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Running retention policies...');

        if ($this->option('archive')) {
            $this->runArchive();
        } elseif ($this->option('anonymize')) {
            $this->runAnonymize();
        } elseif ($this->option('delete')) {
            $this->runDelete();
        } else {
            $this->runAll();
        }

        return Command::SUCCESS;
    }

    private function runArchive(): void
    {
        $this->info('Archiving old data...');
        
        $payments = $this->retentionService->archivePayments();
        $smsAttempts = $this->retentionService->archiveSmsAttempts();
        $auditLogs = $this->retentionService->archiveAuditLogs();
        
        $this->info("Archived {$payments} payments");
        $this->info("Archived {$smsAttempts} SMS attempts");
        $this->info("Archived {$auditLogs} audit logs");
    }

    private function runAnonymize(): void
    {
        $this->info('Anonymizing old payments...');
        
        $count = $this->retentionService->anonymizeOldPayments();
        
        $this->info("Anonymized {$count} payments");
    }

    private function runDelete(): void
    {
        $this->info('Deleting anonymized data...');
        
        $count = $this->retentionService->deleteAnonymizedData();
        
        $this->info("Deleted {$count} anonymized records");
    }

    private function runAll(): void
    {
        $this->info('Running all retention policies...');
        
        $results = $this->retentionService->runAllPolicies();
        
        $this->table(
            ['Policy', 'Count'],
            [
                ['Payments Archived', $results['payments_archived']],
                ['SMS Attempts Archived', $results['sms_attempts_archived']],
                ['Audit Logs Archived', $results['audit_logs_archived']],
                ['Payments Anonymized', $results['payments_anonymized']],
                ['Anonymized Data Deleted', $results['anonymized_data_deleted']],
            ]
        );
    }
}
