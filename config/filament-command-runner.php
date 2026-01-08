<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Command Runner Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Filament Command Runner package.
    | Controls which commands are available, security levels, and features.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    */

    'enabled' => env('FILAMENT_COMMAND_RUNNER_ENABLED', true),

    'max_execution_time' => env('FILAMENT_COMMAND_RUNNER_MAX_EXECUTION_TIME', 300),

    'log_executions' => env('FILAMENT_COMMAND_RUNNER_LOG_EXECUTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'require_confirmation_for_production' => env('FILAMENT_COMMAND_RUNNER_REQUIRE_CONFIRMATION_PROD', true),

    'require_confirmation_for_medium_risk' => true,

    'require_confirmation_for_high_risk' => true,

    /*
    |--------------------------------------------------------------------------
    | Command Registration
    |--------------------------------------------------------------------------
    |
    | Register custom commands by specifying their class names.
    | Set to false or remove to disable a command.
    |
    | Example:
    | 'commands' => [
    |     \App\Commands\MyCommand::class => true,
    |     \App\Commands\DangerousCommand::class => false,
    | ]
    |
    */

    'commands' => [
        // Register your commands here
        // Built-in EloquentQueryCommand is registered automatically
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Settings
    |--------------------------------------------------------------------------
    |
    | Auto-discovery finds commands in your app's Console/Commands directory.
    |
    */

    'auto_discovery' => [
        'enabled' => false,
        'default_security_level' => 'medium',
        'default_enabled' => false, // Auto-discovered commands disabled by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-based Restrictions
    |--------------------------------------------------------------------------
    |
    | Restrict commands based on the current environment.
    |
    */

    'environment_restrictions' => [
        'production' => [
            // List danger levels allowed in production
            'allowed_danger_levels' => ['low'],
            'disable_unless_confirmed' => true,
        ],
        'staging' => [
            'allowed_danger_levels' => ['low', 'medium'],
            'disable_unless_confirmed' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'page_title' => 'Run Commands',
        'page_description' => 'Execute predefined Artisan commands safely',
        'navigation_group' => 'System Admin',
        'navigation_label' => 'Run Commands',
        'navigation_icon' => 'heroicon-o-command-line',
        'show_execution_time' => true,
        'show_exit_code' => true,
        'max_output_height' => '400px',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    */

    'output' => [
        'enable_ansi_colors' => true,
        'show_copy_button' => true,
        'enable_download' => true,
        'format_timestamps' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Logging
    |--------------------------------------------------------------------------
    |
    | Log command executions to the database for audit purposes.
    |
    */

    'database_logging' => [
        'enabled' => env('FILAMENT_COMMAND_RUNNER_DB_LOGGING', true),
        'table' => 'command_executions',
        'keep_logs_for_days' => 90,
    ],
];
