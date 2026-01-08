<?php

namespace EeqanLtd\FilamentCommandRunner\Services;

use EeqanLtd\FilamentCommandRunner\Abstractions\CommandRunnerInterface;
use Illuminate\Support\Collection;

/**
 * Service for registering and managing command runners
 * Handles command discovery, registration, and retrieval
 */
class CommandRegistry {
    /**
     * Container for registered commands
     */
    private static array $commands = [];

    /**
     * Register a command runner
     */
    public static function register(CommandRunnerInterface|string $command): void {
        if (is_string($command)) {
            // Instantiate the command class
            $command = new $command();
        }

        if (!$command instanceof CommandRunnerInterface) {
            throw new \InvalidArgumentException(
                'Command must implement CommandRunnerInterface'
            );
        }

        self::$commands[$command->getCommandName()] = $command;
    }

    /**
     * Register multiple commands
     */
    public static function registerMany(array $commands): void {
        foreach ($commands as $command) {
            self::register($command);
        }
    }

    /**
     * Get a registered command by name
     */
    public static function get(string $commandName): ?CommandRunnerInterface {
        return self::$commands[$commandName] ?? null;
    }

    /**
     * Get all registered commands
     */
    public static function all(): array {
        return self::$commands;
    }

    /**
     * Check if a command is registered
     */
    public static function has(string $commandName): bool {
        return isset(self::$commands[$commandName]);
    }

    /**
     * Get all commands as a collection
     */
    public static function collect(): Collection {
        return collect(self::$commands);
    }

    /**
     * Get all commands grouped by category
     */
    public static function groupByCategory(): array {
        $grouped = [];

        foreach (self::$commands as $command) {
            $category = $command->getCategory();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$command->getCommandName()] = $command;
        }

        return $grouped;
    }

    /**
     * Get all commands as configuration arrays
     */
    public static function toConfig(): array {
        $config = [];

        foreach (self::$commands as $commandName => $command) {
            $config[$commandName] = $command->toConfig();
        }

        return $config;
    }

    /**
     * Reset all registered commands (useful for testing)
     */
    public static function reset(): void {
        self::$commands = [];
    }

    /**
     * Filter commands by danger level
     */
    public static function filterByDangerLevel(string $level): array {
        return array_filter(
            self::$commands,
            fn($command) => $command->getDangerLevel() === $level
        );
    }

    /**
     * Get all commands that require confirmation
     */
    public static function requiresConfirmation(): array {
        return array_filter(
            self::$commands,
            fn($command) => $command->requiresConfirmation()
        );
    }

    /**
     * Auto-discover and register commands from Laravel console
     * This discovers any commands in the app's Console\Commands directory
     */
    public static function discoverCommands(): array {
        $discovered = [];
        $commandsPath = app_path('Console/Commands');

        if (!is_dir($commandsPath)) {
            return $discovered;
        }

        $files = scandir($commandsPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                continue;
            }

            $className = 'App\\Console\\Commands\\' . substr($file, 0, -4);

            if (!class_exists($className)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($className);
                if ($reflection->hasProperty('signature') && $reflection->hasProperty('description')) {
                    $instance = new $className();
                    $signature = $reflection->getProperty('signature');
                    $signature->setAccessible(true);
                    $description = $reflection->getProperty('description');
                    $description->setAccessible(true);

                    $commandSignature = $signature->getValue($instance);
                    $commandDescription = $description->getValue($instance);

                    // Extract command name from signature
                    $commandName = trim(explode(' ', $commandSignature)[0]);

                    $discovered[$commandName] = [
                        'name' => $className,
                        'signature' => $commandSignature,
                        'description' => $commandDescription,
                        'class' => $className,
                    ];
                }
            } catch (\Exception $e) {
                // Skip invalid commands
                continue;
            }
        }

        return $discovered;
    }
}
