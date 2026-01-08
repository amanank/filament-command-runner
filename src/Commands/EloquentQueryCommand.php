<?php

namespace EeqanLtd\FilamentCommandRunner\Commands;

use EeqanLtd\FilamentCommandRunner\Abstractions\AbstractCommandRunner;
use EeqanLtd\FilamentCommandRunner\Services\EloquentQueryRunner;
use EeqanLtd\FilamentCommandRunner\Support\OutputFormatter;
use InvalidArgumentException;

/**
 * Example command: Safe Eloquent Query Runner
 * Demonstrates how to create a custom command by extending AbstractCommandRunner
 *
 * Usage: php artisan eloquent:query --model="App\Models\User" --query="get()"
 */
class EloquentQueryCommand extends AbstractCommandRunner {
    protected string $commandName = 'eloquent:query';
    protected string $displayName = 'Eloquent Query Runner';
    protected string $description = 'Run safe Eloquent queries for data exploration and analysis. Only SELECT-type queries are allowed.';
    protected string $category = 'Data Exploration';
    protected string $dangerLevel = 'low';
    protected bool $requiresConfirmation = false;

    protected array $options = [
        'model' => [
            'type' => 'select',
            'label' => 'Model',
            'required' => true,
            'options_dynamic' => 'eloquent_models',
            'help' => 'Select the Eloquent model to query',
        ],
        'query' => [
            'type' => 'textarea',
            'label' => 'Query',
            'required' => true,
            'placeholder' => "whereDate('created_at', today())->get()->pluck('name', 'id')",
            'help' => 'Enter the Eloquent query method chain (without the model name and ::). Example: whereDate(\'created_at\', today())->get()->pluck(\'name\', \'id\')',
        ],
    ];

    /**
     * Validate options with custom logic
     */
    public function validateOptions(array $options): void {
        parent::validateOptions($options);

        $model = $options['model'] ?? null;
        $query = $options['query'] ?? null;

        // Validate model exists
        if (!class_exists($model)) {
            throw new InvalidArgumentException("Model class '{$model}' does not exist.");
        }

        // Validate query is safe
        if ($query) {
            EloquentQueryRunner::validateQuery($query);
        }
    }

    /**
     * Execute the Eloquent query
     */
    public function execute(array $options): string {
        $model = $options['model'] ?? null;
        $query = $options['query'] ?? null;

        $this->validateOptions($options);

        $output = OutputFormatter::formatExecutionMetadata(
            $this->commandName,
            ['model' => $model, 'query' => $query]
        );

        try {
            $startTime = microtime(true);

            // Show query info
            $output .= "📋 Model: {$model}\n";
            $output .= "📋 Query: {$query}\n\n";
            $output .= "Executing: {$model}::{$query}\n";
            $output .= str_repeat('─', 60) . "\n\n";

            // Execute the query
            $result = EloquentQueryRunner::executeQuery($model, $query);

            // Format results
            $formattedResult = EloquentQueryRunner::formatResults($result);

            $executionTime = round(microtime(true) - $startTime, 3);

            $output .= "✅ Query executed successfully\n\n";
            $output .= "Results:\n";
            $output .= str_repeat('─', 60) . "\n";
            $output .= $formattedResult . "\n";

            $output .= OutputFormatter::formatExecutionFooter($executionTime, 0);

            return $output;
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 3);
            $output .= OutputFormatter::formatExecutionFooter($executionTime, 1, $e->getMessage());
            return $output;
        }
    }
}
