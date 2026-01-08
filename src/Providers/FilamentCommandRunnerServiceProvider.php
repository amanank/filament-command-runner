<?php

namespace EeqanLtd\FilamentCommandRunner\Providers;

use EeqanLtd\FilamentCommandRunner\Commands\EloquentQueryCommand;
use EeqanLtd\FilamentCommandRunner\Services\CommandRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service Provider for Filament Command Runner Package
 *
 * This provider handles:
 * - Package registration and configuration
 * - Service container bindings
 * - Command registration
 * - View and migration publishing
 */
class FilamentCommandRunnerServiceProvider extends PackageServiceProvider {
    public function configurePackage(Package $package): void {
        $package
            ->name('filament-command-runner')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations(['2024_01_01_000000_create_command_executions_table']);
    }

    public function packageRegistered(): void {
        parent::packageRegistered();

        // Register the command registry as a singleton
        $this->app->singleton(CommandRegistry::class, function () {
            return new CommandRegistry();
        });
    }

    public function packageBooted(): void {
        parent::packageBooted();

        // Register default commands
        $this->registerDefaultCommands();

        // Register user-defined commands from config
        $this->registerConfiguredCommands();
    }

    /**
     * Register the default included commands
     */
    protected function registerDefaultCommands(): void {
        $registry = $this->app->make(CommandRegistry::class);

        // Register the Eloquent Query command
        $registry->register(EloquentQueryCommand::class);
    }

    /**
     * Register commands defined in the package config
     */
    protected function registerConfiguredCommands(): void {
        $config = config('filament-command-runner.commands');

        if (!$config) {
            return;
        }

        $registry = $this->app->make(CommandRegistry::class);

        foreach ($config as $commandClass => $enabled) {
            // Skip disabled commands
            if (!$enabled) {
                continue;
            }

            // Skip if already registered
            if (is_array($enabled)) {
                $enabled = $enabled['enabled'] ?? true;
            }

            if (!$enabled) {
                continue;
            }

            // Register the command
            try {
                $registry->register($commandClass);
            } catch (\Exception $e) {
                \Log::warning("Failed to register command: {$commandClass}", ['error' => $e->getMessage()]);
            }
        }
    }
}
