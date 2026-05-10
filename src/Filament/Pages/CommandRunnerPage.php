<?php

namespace Amanank\FilamentCommandRunner\Filament\Pages;

use Amanank\FilamentCommandRunner\Services\CommandRegistry;
use Amanank\FilamentCommandRunner\Services\EloquentQueryRunner;
use Amanank\FilamentCommandRunner\Support\OutputFormatter;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action as PageAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\Artisan;

/**
 * Filament Page for Command Runner
 *
 * Provides a UI for executing registered commands with:
 * - Command selection and filtering
 * - Dynamic form generation based on command options
 * - Real-time execution output
 * - Security checks and confirmation dialogs
 */
class CommandRunnerPage extends Page implements HasForms, HasActions {
    use InteractsWithForms, InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';
    protected static string $view = 'filament-command-runner::pages.command-runner-page';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationGroup = null;
    protected static ?string $title = 'Run Commands';
    protected static ?string $navigationLabel = null;
    protected static ?int $navigationSort = 100;
    protected static ?string $slug = 'run-commands';

    public ?array $data = [];
    public ?string $selectedCommand = null;
    public ?string $commandOutput = null;
    public bool $isRunning = false;

    public function mount(): void {
        $this->data = [
            'selectedCommand' => null,
        ];
        $this->form->fill($this->data);
    }

    public static function getNavigationGroup(): ?string {
        return config('filament-command-runner.ui.navigation_group', 'System Admin');
    }

    public static function getNavigationLabel(): string {
        return config('filament-command-runner.ui.navigation_label', 'Run Commands');
    }

    public function form(Form $form): Form {
        return $form
            ->schema([
                Section::make('Command Selection')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Select::make('selectedCommand')
                                    ->label('Available Commands')
                                    ->options($this->getCommandOptions())
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->selectedCommand = $state;
                                        $this->data['selectedCommand'] = $state;
                                        $this->resetCommandForm();
                                    })
                                    ->placeholder('Select a command to run'),
                            ]),

                        Placeholder::make('command_info')
                            ->label('')
                            ->content(fn() => $this->getCommandInfo())
                            ->visible(fn() => $this->hasSelectedCommand()),
                    ]),

                Section::make('Command Options')
                    ->schema(fn() => $this->getCommandOptionsSchema())
                    ->visible(fn() => $this->hasSelectedCommand() && $this->hasCommandOptions())
                    ->collapsible(),

                Section::make('Command Output')
                    ->schema([
                        Placeholder::make('output')
                            ->label('')
                            ->content(fn() => OutputFormatter::format($this->commandOutput)),
                    ])
                    ->visible(fn() => $this->commandOutput !== null)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    protected function getActions(): array {
        return [
            PageAction::make('runCommand')
                ->label('Run Command')
                ->icon('heroicon-o-play')
                ->color('success')
                ->disabled(fn() => !$this->hasSelectedCommand() || $this->isRunning)
                ->requiresConfirmation(fn() => $this->requiresConfirmation())
                ->modalHeading(fn() => 'Run ' . ($this->getSelectedCommand()?->getDisplayName() ?? 'Command'))
                ->modalDescription(fn() => $this->getConfirmationMessage())
                ->modalSubmitActionLabel('Run Command')
                ->action('executeCommand'),

            PageAction::make('clearOutput')
                ->label('Clear Output')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->visible(fn() => $this->commandOutput !== null)
                ->action(fn() => $this->commandOutput = null),

            PageAction::make('downloadOutput')
                ->label('Download Output')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn() => $this->commandOutput !== null)
                ->action('downloadOutput'),
        ];
    }

    /**
     * Execute the selected command
     */
    public function executeCommand(): void {
        $selectedCommandName = $this->data['selectedCommand'] ?? null;

        if (!$selectedCommandName) {
            Notification::make()
                ->title('No Command Selected')
                ->danger()
                ->send();
            return;
        }

        $command = CommandRegistry::get($selectedCommandName);

        if (!$command) {
            Notification::make()
                ->title('Command Not Found')
                ->danger()
                ->send();
            return;
        }

        $this->isRunning = true;
        $startTime = microtime(true);

        try {
            // Collect options from form data
            $options = [];
            foreach ($command->getOptions() as $key => $option) {
                if (isset($this->data[$key])) {
                    $options[$key] = $this->data[$key];
                }
            }

            // Validate options
            $command->validateOptions($options);

            // Execute the command
            $this->commandOutput = $command->execute($options);

            $executionTime = round(microtime(true) - $startTime, 2);

            Notification::make()
                ->title('Command Completed Successfully')
                ->body("Execution time: {$executionTime}s")
                ->success()
                ->duration(5000)
                ->send();
        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            $this->commandOutput = OutputFormatter::formatExecutionFooter($executionTime, 1, $e->getMessage());

            Notification::make()
                ->title('Command Execution Error')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }

    /**
     * Download command output as a text file
     */
    public function downloadOutput(): void {
        if (!$this->commandOutput) {
            return;
        }

        $filename = 'command-output-' . date('Y-m-d-H-i-s') . '.txt';
        $content = OutputFormatter::stripFormatting($this->commandOutput);

        Notification::make()
            ->title('Download Initiated')
            ->body("File: {$filename}")
            ->info()
            ->send();
    }

    /**
     * Get available commands as options for the select
     */
    private function getCommandOptions(): array {
        $options = [];

        foreach (CommandRegistry::all() as $commandName => $command) {
            $dangerIcon = match ($command->getDangerLevel()) {
                'high' => '🔴',
                'medium' => '🟡',
                default => '🟢',
            };

            $options[$commandName] = "{$dangerIcon} {$command->getDisplayName()} ({$command->getCategory()})";
        }

        return $options;
    }

    /**
     * Get the currently selected command
     */
    private function getSelectedCommand() {
        $commandName = $this->data['selectedCommand'] ?? $this->selectedCommand ?? null;
        return $commandName ? CommandRegistry::get($commandName) : null;
    }

    /**
     * Get command info as formatted text
     */
    private function getCommandInfo(): string {
        $command = $this->getSelectedCommand();

        if (!$command) {
            return '';
        }

        $dangerText = match ($command->getDangerLevel()) {
            'high' => '⚠️ High Risk - May modify critical data or cause system downtime',
            'medium' => '⚠️ Medium Risk - May modify data',
            default => '🟢 Low Risk - Read-only or safe operations',
        };

        return "📋 {$command->getDescription()}\n" .
            "{$dangerText}\n" .
            "📂 Category: {$command->getCategory()}\n" .
            "🔧 Command: {$command->getCommandName()}";
    }

    /**
     * Get form schema for command options
     */
    private function getCommandOptionsSchema(): array {
        $command = $this->getSelectedCommand();

        if (!$command) {
            return [];
        }

        $options = $command->getOptions();

        if (empty($options)) {
            return [
                Placeholder::make('no_options')
                    ->label('')
                    ->content('<p class="text-sm text-gray-500 italic">This command has no configurable options.</p>')
            ];
        }

        $schema = [];

        foreach ($options as $key => $option) {
            $defaultValue = $option['default'] ?? '';

            // Handle dynamic options
            $optionsList = $option['options'] ?? [];
            if ($option['type'] === 'select' && ($option['options_dynamic'] ?? false) === 'eloquent_models') {
                $models = EloquentQueryRunner::getAvailableModels();
                $optionsList = array_combine(array_keys($models), array_values($models));
            }

            $component = match ($option['type']) {
                'select' => Select::make($key)
                    ->options($optionsList)
                    ->default($defaultValue)
                    ->searchable()
                    ->live(),
                'checkbox' => Checkbox::make($key)
                    ->default($option['default'] ?? false)
                    ->live(),
                'textarea' => Textarea::make($key)
                    ->default($defaultValue)
                    ->placeholder($option['placeholder'] ?? '')
                    ->rows(6)
                    ->live(),
                default => TextInput::make($key)
                    ->numeric($option['numeric'] ?? false)
                    ->default($defaultValue)
                    ->placeholder($option['placeholder'] ?? '')
                    ->live(),
            };

            $component = $component
                ->label($option['label'])
                ->required($option['required'] ?? false);

            if (!empty($option['help'])) {
                $component = $component->helperText($option['help']);
            }

            if ($this->selectedCommand === 'eloquent:query') {
                if ($key === 'model') {
                    $component = $component->helperText(fn() => $this->getEloquentModelSelectorHelp($option));
                }

                if ($key === 'query') {
                    $component = $component->helperText(fn() => $this->getEloquentQueryHelp($option));
                }
            }

            $schema[] = $component;
        }

        return [Grid::make(2)->schema($schema)];
    }

    private function getEloquentModelSelectorHelp(array $option): HtmlString|string {
        $selectedModel = $this->data['model'] ?? null;
        $baseHelp = $option['help'] ?? 'Select the Eloquent model to query';

        if (!$selectedModel) {
            return $baseHelp;
        }

        $fields = EloquentQueryRunner::getModelFields($selectedModel);

        if (empty($fields)) {
            return $baseHelp;
        }

        $formattedFields = implode(', ', $fields);

        return new HtmlString(e($baseHelp) . '<br><span class="text-xs text-gray-500">Fields: ' . e($formattedFields) . '</span>');
    }

    private function getEloquentQueryHelp(array $option): HtmlString|string {
        $baseHelp = $option['help']
            ?? 'Enter the Eloquent query method chain (without the model name and ::).';
        $selectedModel = $this->data['model'] ?? null;

        if (!$selectedModel) {
            return $baseHelp;
        }

        $fields = EloquentQueryRunner::getModelFields($selectedModel);
        $examples = array_values(array_unique([
            ...$this->getConfiguredEloquentExamples($selectedModel),
            ...$this->getGeneratedEloquentExamples($selectedModel, $fields),
        ]));

        $parts = ['<div>' . e($baseHelp) . '</div>'];

        if (!empty($examples)) {
            $exampleItems = collect($examples)
                ->map(fn(string $example) => '<div><code>' . e($example) . '</code></div>')
                ->implode('');

            $parts[] = '<div class="text-xs text-gray-500 mt-1"><strong>Examples for ' . e(class_basename($selectedModel)) . ':</strong></div>';
            $parts[] = '<div class="text-xs text-gray-500 space-y-1 mt-1">' . $exampleItems . '</div>';
        }

        return new HtmlString(implode('', $parts));
    }

    private function getConfiguredEloquentExamples(string $modelClass): array {
        $config = config('filament-command-runner.eloquent_query.model_examples', []);

        if (!is_array($config)) {
            return [];
        }

        $examples = $config[$modelClass] ?? $config[class_basename($modelClass)] ?? [];

        if (is_string($examples)) {
            return [$examples];
        }

        if (!is_array($examples)) {
            return [];
        }

        return array_values(array_filter($examples, fn($example) => is_string($example) && Str::of($example)->trim()->isNotEmpty()));
    }

    private function getGeneratedEloquentExamples(string $modelClass, array $fields): array {
        $examples = [];
        $fieldSet = array_flip($fields);

        if (isset($fieldSet['last_status_confirmed_date'], $fieldSet['aims_id'], $fieldSet['name'])) {
            $examples[] = "whereDate('last_status_confirmed_date', today())->get()->pluck('aims_id', 'name')";
        }

        if (isset($fieldSet['status'], $fieldSet['last_status_confirmed_date'], $fieldSet['aims_id'], $fieldSet['name'])) {
            $examples[] = "where('status', 'ACTIVE')->whereNull('last_status_confirmed_date')->get(['aims_id', 'name', 'status'])";
            $examples[] = "where('status', 'ACTIVE')->whereDate('last_status_confirmed_date', '<', today())->get(['aims_id', 'name', 'last_status_confirmed_date', 'status'])";
        }

        if (empty($examples) && isset($fieldSet['created_at'], $fieldSet['name'], $fieldSet['id'])) {
            $examples[] = "whereDate('created_at', today())->get()->pluck('name', 'id')";
        }

        if (empty($examples) && isset($fieldSet['id'])) {
            $examples[] = "limit(10)->get()";
        }

        return $examples;
    }

    /**
     * Check if a command is selected
     */
    private function hasSelectedCommand(): bool {
        return !empty($this->data['selectedCommand'] ?? $this->selectedCommand);
    }

    /**
     * Check if selected command has options
     */
    private function hasCommandOptions(): bool {
        $command = $this->getSelectedCommand();
        return $command && !empty($command->getOptions());
    }

    /**
     * Check if confirmation is required
     */
    private function requiresConfirmation(): bool {
        $command = $this->getSelectedCommand();

        if (!$command) {
            return true;
        }

        return $command->requiresConfirmation();
    }

    /**
     * Get confirmation message
     */
    private function getConfirmationMessage(): string {
        $command = $this->getSelectedCommand();

        if (!$command) {
            return 'Are you sure?';
        }

        $message = "Are you sure you want to run this command?";

        if ($command->getDangerLevel() !== 'low') {
            $message .= "\n\n⚠️ This command may modify data in your system.";
        }

        if (app()->environment('production')) {
            $message .= "\n\n🔥 WARNING: You are in PRODUCTION environment!";
        }

        $message .= "\n\nCommand: {$command->getCommandName()}";

        return $message;
    }

    /**
     * Reset command form
     */
    private function resetCommandForm(): void {
        $this->commandOutput = null;

        $selectedCommand = $this->data['selectedCommand'] ?? null;

        $this->data = [
            'selectedCommand' => $selectedCommand,
        ];

        if ($selectedCommand) {
            $command = CommandRegistry::get($selectedCommand);
            if ($command) {
                foreach ($command->getOptions() as $key => $option) {
                    $this->data[$key] = $option['default'] ?? null;
                }
            }
        }
    }
}
