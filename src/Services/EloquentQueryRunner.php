<?php

namespace Amanank\FilamentCommandRunner\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Exception;

/**
 * Service for safely executing Eloquent queries
 * Provides validation and execution of read-only query chains
 */
class EloquentQueryRunner {
    /**
     * Allowed query methods that are read-only
     */
    private static array $allowedMethods = [
        'where',
        'orWhere',
        'whereIn',
        'whereNotIn',
        'whereBetween',
        'whereNotBetween',
        'whereNull',
        'whereNotNull',
        'whereDate',
        'whereMonth',
        'whereYear',
        'whereTime',
        'whereColumn',
        'whereRaw',
        'orWhereRaw',
        'orderBy',
        'orderByDesc',
        'latest',
        'oldest',
        'limit',
        'offset',
        'skip',
        'take',
        'select',
        'addSelect',
        'distinct',
        'groupBy',
        'having',
        'havingRaw',
        'with',
        'load',
        'loadCount',
        'get',
        'first',
        'find',
        'findOrFail',
        'sole',
        'pluck',
        'value',
        'count',
        'max',
        'min',
        'avg',
        'sum',
        'exists',
        'doesntExist',
        'toArray',
        'toJson',
        'paginate',
        'simplePaginate',
        'chunk',
        'chunkMap',
        'each',
        'eachById',
        'lazy',
    ];

    /**
     * Get all available Eloquent models in the application
     */
    public static function getAvailableModels(): array {
        $models = [];
        $modelPath = app_path('Models');

        if (!File::isDirectory($modelPath)) {
            return $models;
        }

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilenameWithoutExtension();
            $namespace = 'App\\Models\\' . $filename;

            // Only include actual Model classes
            if (class_exists($namespace) && is_subclass_of($namespace, Model::class)) {
                $models[$namespace] = $filename;
            }
        }

        return $models;
    }

    /**
     * Validate that a query string is safe to execute
     * Only allows SELECT-type queries (->get(), ->first(), ->pluck(), etc.)
     */
    public static function validateQuery(string $query): void {
        // Check for dangerous keywords that indicate write/delete operations
        $dangerousPatterns = [
            '/save\s*\(/i',
            '/create\s*\(/i',
            '/update\s*\(/i',
            '/delete\s*\(/i',
            '/destroy\s*\(/i',
            '/forceDelete\s*\(/i',
            '/insert\s*\(/i',
            '/truncate\s*\(/i',
            '/exec\s*\(/i',
            '/query\s*\(/i',
            '/statement\s*\(/i',
            '/dropIfExists\s*\(/i',
            '/drop\s*\(/i',
            '/alter\s*\(/i',
            '/schema\s*::/i',
            '/DB\s*::/i',
            '/\$_/i',
            '/eval\s*\(/i',
            '/assert\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/proc_open\s*\(/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                throw new InvalidArgumentException(
                    'Query contains disallowed operations. Only SELECT-type queries are allowed.'
                );
            }
        }

        // Verify query uses only allowed methods
        preg_match_all('/->\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $query, $allMethods);
        if (!empty($allMethods[1])) {
            foreach (array_unique(array_map('strtolower', $allMethods[1])) as $method) {
                if (!in_array($method, array_map('strtolower', self::$allowedMethods))) {
                    throw new InvalidArgumentException(
                        "Method '{$method}' is not allowed. Only read-only query methods are permitted."
                    );
                }
            }
        }
    }

    /**
     * Execute an Eloquent query safely
     */
    public static function executeQuery(string $modelClass, string $query): mixed {
        // Validate model class exists and extends Model
        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException("Model class '{$modelClass}' does not exist.");
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Class '{$modelClass}' is not an Eloquent Model.");
        }

        // Validate the query
        self::validateQuery($query);

        try {
            // Execute the query using eval in a controlled manner
            // We use a closure to prevent access to $this and other variables
            $executeQuery = static function () use ($modelClass, $query) {
                return eval('return ' . $modelClass . '::' . $query . ';');
            };

            $result = $executeQuery();

            return $result;
        } catch (Exception $e) {
            throw new Exception('Query execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Format query results for display
     */
    public static function formatResults(mixed $results): string {
        if ($results === null) {
            return 'NULL';
        }

        if (is_bool($results)) {
            return $results ? 'true' : 'false';
        }

        if (is_numeric($results)) {
            return (string)$results;
        }

        if (is_string($results)) {
            return $results;
        }

        if (is_array($results)) {
            return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if (is_object($results)) {
            // For Eloquent models and collections
            if (method_exists($results, 'toArray')) {
                return json_encode($results->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return (string)$results;
    }

    /**
     * Get the available fields/columns for a model
     */
    public static function getModelFields(string $modelClass): array {
        try {
            if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
                return [];
            }

            /** @var Model $instance */
            $instance = new $modelClass();

            // Get table name
            $table = $instance->getTable();

            // Get columns from the database
            $connection = $instance->getConnection();
            $columns = $connection->getSchemaBuilder()->getColumnListing($table);

            return $columns;
        } catch (Exception $e) {
            return [];
        }
    }
}
