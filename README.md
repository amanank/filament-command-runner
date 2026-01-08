# Filament Command Runner

A reusable, extensible Laravel package that provides a secure Filament UI for executing predefined Artisan commands.

## Features

✅ **Secure** - No arbitrary shell execution, validated command registry
✅ **Extensible** - Create new commands by extending `AbstractCommandRunner`
✅ **Permission-Aware** - Integrates with Filament Shield and standard policies
✅ **Risk Management** - Configurable danger levels (low/medium/high) with confirmations
✅ **Output Formatting** - ANSI color support, syntax highlighting, downloadable logs
✅ **Environment-Based** - Restrict commands per environment (production, staging, etc.)
✅ **Audit Logging** - Optional database logging of all command executions
✅ **Auto-Discovery** - Optionally discover commands from your Laravel app

## Requirements

- PHP 8.1+
- Laravel 10+
- Filament 3+

## Installation

### 1. Add Package Repository to composer.json

Since the package is in your local `packages/` directory, add it as a path repository in your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/eeqan-ltd/filament-command-runner",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

### 2. Install via Composer

```bash
composer require eeqan-ltd/filament-command-runner:@dev
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --provider="Amanank\\FilamentCommandRunner\\Providers\\FilamentCommandRunnerServiceProvider"
```

This will publish:
- `config/filament-command-runner.php` - Package configuration
- `database/migrations/` - Database migrations
- `resources/views/filament/pages/` - View templates

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Register in Filament Panel

Add the Command Runner page to your Filament panel configuration:

```php
// config/filament/admin.php (or your panel configuration)

'pages' => [
    // ... other pages
    \Amanank\FilamentCommandRunner\Filament\Pages\CommandRunnerPage::class,
],
```

### 6. Register Application Commands (Optional)

If you have app-specific commands, register them in your app service provider:

```php
// app/Providers/CommandRunnerServiceProvider.php or AppServiceProvider

use Amanank\FilamentCommandRunner\Services\CommandRegistry;
use App\Console\Commands\YourCustomCommand;

public function boot(): void {
    CommandRegistry::registerMany([
        YourCustomCommand::class,
        // ... other commands
    ]);
}

## Quick Start

Once installed, navigate to `/admin/run-commands` in your Filament panel to access the Command Runner UI.

### Built-In Commands

The package includes one example command:

#### Eloquent Query Runner
- **Name:** `eloquent:query`
- **Purpose:** Run safe read-only Eloquent queries for data exploration
- **Risk Level:** Low
- **Options:**
  - Model: Select the Eloquent model to query
  - Query: Enter the query method chain (e.g., `whereDate('created_at', today())->get()`)

## Creating Custom Commands

### Basic Example

Create a command by extending `AbstractCommandRunner`:

```php
<?php

namespace App\CommandRunner\Commands;

use Amanank\FilamentCommandRunner\Abstractions\AbstractCommandRunner;

class MyCustomCommand extends AbstractCommandRunner {
    protected string $commandName = 'custom:my-command';
    protected string $displayName = 'My Custom Command';
    protected string $description = 'A custom command that does something useful';
    protected string $category = 'Custom';
    protected string $dangerLevel = 'low'; // 'low', 'medium', or 'high'
    protected bool $requiresConfirmation = false;

    protected array $options = [
        'parameter' => [
            'type' => 'text',
            'label' => 'Parameter Name',
            'required' => true,
            'help' => 'Enter a value',
            'validation' => 'string|min:1|max:100',
        ],
    ];

    public function execute(array $options): string {
        $parameter = $options['parameter'];

        // Your logic here
        $output = "Processing {$parameter}...\n";

        return $output;
    }
}
```

### Registering Custom Commands

#### Option 1: Register in Service Provider

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Amanank\FilamentCommandRunner\Services\CommandRegistry;
use App\CommandRunner\Commands\MyCustomCommand;

class AppServiceProvider extends ServiceProvider {
    public function boot(): void {
        CommandRegistry::register(MyCustomCommand::class);
    }
}
```

#### Option 2: Register in Configuration

Add to `config/filament-command-runner.php`:

```php
'commands' => [
    \App\CommandRunner\Commands\MyCustomCommand::class => true,
],
```

### Advanced Example: Database Sync Command

```php
<?php

namespace App\CommandRunner\Commands;

use Amanank\FilamentCommandRunner\Abstractions\AbstractCommandRunner;
use Amanank\FilamentCommandRunner\Support\OutputFormatter;
use App\Models\User;
use InvalidArgumentException;

class SyncDatabaseCommand extends AbstractCommandRunner {
    protected string $commandName = 'sync:database';
    protected string $displayName = 'Sync Database';
    protected string $description = 'Synchronize critical data from external source';
    protected string $category = 'Data Synchronization';
    protected string $dangerLevel = 'medium'; // Requires confirmation
    protected bool $requiresConfirmation = true;

    protected array $options = [
        'dry_run' => [
            'type' => 'checkbox',
            'label' => 'Dry Run (Preview Only)',
            'default' => true,
            'help' => 'Show what would be updated without making changes',
        ],
        'limit' => [
            'type' => 'text',
            'label' => 'Record Limit',
            'default' => '100',
            'numeric' => true,
            'validation' => 'integer|min:1|max:10000',
            'help' => 'Maximum number of records to process',
        ],
    ];

    public function validateOptions(array $options): void {
        parent::validateOptions($options);

        // Custom validation logic
        if ($options['limit'] > 1000 && !app()->environment('local')) {
            throw new InvalidArgumentException('Limit cannot exceed 1000 in non-local environments');
        }
    }

    public function execute(array $options): string {
        $this->validateOptions($options);

        $output = OutputFormatter::formatExecutionMetadata(
            $this->commandName,
            ['dry_run' => $options['dry_run'], 'limit' => $options['limit']]
        );

        try {
            $dryRun = $options['dry_run'] ?? true;
            $limit = (int)$options['limit'];

            $users = User::limit($limit)->get();

            $output .= "📊 Processing " . $users->count() . " records\n";

            if ($dryRun) {
                $output .= "🔍 DRY RUN: No changes will be made\n";
                $output .= "Changes that would be made:\n";
            } else {
                $output .= "💾 Applying changes to database\n";
            }

            foreach ($users as $user) {
                // Simulate processing
                $output .= "  ✓ {$user->name}\n";

                if (!$dryRun) {
                    // Apply changes
                }
            }

            $output .= OutputFormatter::formatExecutionFooter(0, 0);
            return $output;
        } catch (\Exception $e) {
            return OutputFormatter::formatExecutionFooter(0, 1, $e->getMessage());
        }
    }
}
```

## Option Types

The Command Runner supports four option types:

### Text Input
```php
'field_name' => [
    'type' => 'text',
    'label' => 'Field Label',
    'required' => true,
    'placeholder' => 'Enter value',
    'numeric' => false,
    'validation' => 'string|max:100',
    'help' => 'Help text',
]
```

### Textarea
```php
'query' => [
    'type' => 'textarea',
    'label' => 'Query',
    'required' => true,
    'rows' => 6,
    'placeholder' => 'Enter query',
    'help' => 'Help text',
]
```

### Select/Dropdown
```php
'status' => [
    'type' => 'select',
    'label' => 'Status',
    'required' => true,
    'options' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'help' => 'Choose a status',
]
```

### Checkbox/Toggle
```php
'confirm' => [
    'type' => 'checkbox',
    'label' => 'Confirm Action',
    'default' => false,
    'help' => 'Check to confirm',
]
```

## Danger Levels

Commands support three risk levels:

| Level | Description | Behavior |
|-------|-------------|----------|
| **low** | Read-only or safe operations | No auto-confirmation required |
| **medium** | May modify data but reversible | Confirmation required |
| **high** | Critical operations, potential data loss | Always requires confirmation |

## Configuration

Edit `config/filament-command-runner.php` to:

- Enable/disable the Command Runner
- Configure security levels
- Set environment-based restrictions
- Customize the UI
- Configure audit logging

### Environment-Based Restrictions

```php
'environment_restrictions' => [
    'production' => [
        'allowed_danger_levels' => ['low'],
        'disable_unless_confirmed' => true,
    ],
    'staging' => [
        'allowed_danger_levels' => ['low', 'medium'],
        'disable_unless_confirmed' => false,
    ],
],
```

### Database Logging

The package automatically logs command executions to the `command_executions` table:

| Column | Description |
|--------|-------------|
| `command` | Command name |
| `options` | JSON options provided |
| `user_id` | Executing user ID |
| `user_name` | Executing user name |
| `user_email` | Executing user email |
| `exit_code` | Exit code (0 = success) |
| `output` | Full command output |
| `execution_time` | Execution time in seconds |
| `environment` | Environment (local, staging, production) |
| `ip_address` | User's IP address |
| `user_agent` | Browser user agent |
| `started_at` | Start timestamp |
| `completed_at` | Completion timestamp |

## Security Considerations

### Permissions

Add Filament Shield permissions to control access:

```php
// In your admin panel provider
use Spatie\Permission\Models\Role;

$role = Role::findByName('admin');
$role->givePermissionTo('view_command_runner_page');
$role->givePermissionTo('page_CommandRunnerPage');
```

### Preventing Command Injection

The package prevents command injection through:

1. **Command Registry** - Only pre-registered commands can be executed
2. **Parameter Validation** - All options are validated before execution
3. **No Shell Execution** - Commands are executed through Laravel's Artisan facade, not shell_exec
4. **Whitelist Validation** - Eloquent queries use method whitelisting

### Best Practices

1. **Always validate options** - Override `validateOptions()` for custom validation
2. **Use danger levels appropriately** - Mark potentially destructive commands as 'high'
3. **Require confirmation for modifications** - Set `requiresConfirmation = true` for write operations
4. **Limit availability** - Disable commands in production if unnecessary
5. **Monitor logs** - Regularly review the `command_executions` table
6. **Use Shield** - Integrate with Filament Shield for role-based access

## Support & Customization

### Extending the Page

You can extend the `CommandRunnerPage` to customize the UI:

```php
<?php

namespace App\Filament\Pages;

use Amanank\FilamentCommandRunner\Filament\Pages\CommandRunnerPage as BaseCommandRunnerPage;

class CustomCommandRunnerPage extends BaseCommandRunnerPage {
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 50;
}
```

### Custom Output Formatting

Use the `OutputFormatter` utility:

```php
use Amanank\FilamentCommandRunner\Support\OutputFormatter;

$output = OutputFormatter::formatExecutionMetadata(
    'command:name',
    ['option1' => 'value1']
);

$output .= "Processing...\n";

$output .= OutputFormatter::formatExecutionFooter(1.23, 0);
```

## Troubleshooting

### Commands Not Appearing

1. Check that the command class implements `CommandRunnerInterface`
2. Verify the command is registered in the service provider or config
3. Check `CommandRegistry::all()` in a Tinker session

### Permission Denied Errors

1. Ensure the user has permission to access the page
2. Check Filament Shield configuration
3. Verify the user's role

### Execution Errors

1. Check Laravel logs in `storage/logs/`
2. Review the command's validation logic
3. Test the command via CLI first: `php artisan your:command --help`

## Testing

```php
use Amanank\FilamentCommandRunner\Services\CommandRegistry;

// Register a test command
CommandRegistry::register(MyTestCommand::class);

// Get a command
$command = CommandRegistry::get('test:command');

// Execute
$output = $command->execute(['option' => 'value']);

// Assert results
$this->assertStringContainsString('expected output', $output);
```

## License

This package is open-sourced under the MIT license.

## Contributing

Contributions are welcome! Please submit pull requests or open issues for bugs and feature requests.
