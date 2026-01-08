<?php

namespace Amanank\FilamentCommandRunner\Commands;

use Amanank\FilamentCommandRunner\Abstractions\AbstractCommandRunner;
use Amanank\FilamentCommandRunner\Support\OutputFormatter;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Example command: Database Cleanup
 *
 * Demonstrates a maintenance command with dry-run capability
 */
class DatabaseCleanupCommand extends AbstractCommandRunner {
    protected string $commandName = 'db:cleanup';
    protected string $displayName = 'Database Cleanup';
    protected string $description = 'Remove stale records from the database';
    protected string $category = 'Database Maintenance';
    protected string $dangerLevel = 'medium';
    protected bool $requiresConfirmation = true;

    protected array $options = [
        'tables' => [
            'type' => 'select',
            'label' => 'Tables to Clean',
            'required' => true,
            'options' => [
                'sessions' => 'Sessions',
                'jobs' => 'Failed Jobs',
                'cache' => 'Cache',
            ],
            'help' => 'Select which tables to clean up',
        ],
        'days' => [
            'type' => 'text',
            'label' => 'Keep Records Newer Than (Days)',
            'required' => false,
            'default' => '30',
            'numeric' => true,
            'validation' => 'integer|min:1|max:365',
            'help' => 'Records older than this will be deleted',
        ],
        'dry_run' => [
            'type' => 'checkbox',
            'label' => 'Dry Run (Preview Only)',
            'default' => true,
            'help' => 'Show what would be deleted without making changes',
        ],
    ];

    public function execute(array $options): string {
        $this->validateOptions($options);

        $output = OutputFormatter::formatExecutionMetadata(
            $this->commandName,
            [
                'tables' => $options['tables'],
                'days' => $options['days'] ?? 30,
                'dry_run' => $options['dry_run'] ? 'Yes' : 'No',
            ]
        );

        try {
            $table = $options['tables'];
            $days = (int)($options['days'] ?? 30);
            $dryRun = $options['dry_run'] ?? true;

            $output .= "📊 Database Cleanup: {$table}\n";

            if ($dryRun) {
                $output .= "🔍 DRY RUN - No records will be deleted\n\n";
            }

            // Simulate cleanup
            $recordsFound = 0;
            $output .= "Scanning {$table} for records older than {$days} days...\n";

            // This would be the actual cleanup logic
            $output .= "Found 1,234 records matching criteria\n";
            $recordsFound = 1234;

            if ($dryRun) {
                $output .= "Would delete: {$recordsFound} records\n";
            } else {
                $output .= "Deleted: {$recordsFound} records\n";
            }

            $output .= OutputFormatter::formatExecutionFooter(2.5, 0);
            return $output;
        } catch (\Exception $e) {
            return OutputFormatter::formatExecutionFooter(0, 1, $e->getMessage());
        }
    }
}
