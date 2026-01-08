<?php

namespace EeqanLtd\FilamentCommandRunner\Abstractions;

use InvalidArgumentException;

/**
 * Base abstract class for implementing reusable commands
 * Provides common functionality and validation patterns
 */
abstract class AbstractCommandRunner implements CommandRunnerInterface {
    /**
     * The command name/identifier (e.g., 'eloquent:query')
     * Must be overridden in concrete implementations
     */
    protected string $commandName = '';

    /**
     * The display name of the command (e.g., 'Eloquent Query Runner')
     */
    protected string $displayName = '';

    /**
     * The command description
     */
    protected string $description = '';

    /**
     * The category (e.g., 'Data Exploration')
     */
    protected string $category = 'Utility';

    /**
     * The danger level: 'low', 'medium', or 'high'
     */
    protected string $dangerLevel = 'medium';

    /**
     * Whether confirmation is required
     */
    protected bool $requiresConfirmation = false;

    /**
     * Option definitions
     * Format: [
     *     'option_name' => [
     *         'type' => 'text|select|checkbox|textarea',
     *         'label' => 'Display Label',
     *         'required' => false,
     *         'default' => null,
     *         'validation' => 'integer|min:1|max:100',
     *         'help' => 'Help text',
     *         'placeholder' => 'Placeholder text',
     *         'options' => ['key' => 'label'], // for select type
     *     ],
     * ]
     */
    protected array $options = [];

    /**
     * Get the command name/identifier
     */
    public function getCommandName(): string {
        return $this->commandName;
    }

    /**
     * Get the display name
     */
    public function getDisplayName(): string {
        return $this->displayName;
    }

    /**
     * Get the command description
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Get the category
     */
    public function getCategory(): string {
        return $this->category;
    }

    /**
     * Get the danger level
     */
    public function getDangerLevel(): string {
        return $this->dangerLevel;
    }

    /**
     * Get all options
     */
    public function getOptions(): array {
        return $this->options;
    }

    /**
     * Whether confirmation is required
     */
    public function requiresConfirmation(): bool {
        // Medium/high risk commands always require confirmation
        if (in_array($this->dangerLevel, ['medium', 'high'])) {
            return true;
        }

        return $this->requiresConfirmation;
    }

    /**
     * Validate options - can be overridden in subclasses for custom validation
     */
    public function validateOptions(array $options): void {
        foreach ($this->options as $key => $option) {
            $value = $options[$key] ?? null;

            // Check required fields
            if (($option['required'] ?? false) && empty($value)) {
                throw new InvalidArgumentException("Option '{$option['label']}' is required");
            }

            // Check validation rules if provided
            if (!empty($value) && !empty($option['validation'])) {
                $this->validateOption($key, $value, $option['validation']);
            }
        }
    }

    /**
     * Validate a single option based on validation rules
     */
    protected function validateOption(string $key, mixed $value, string $rules): void {
        $rulesList = explode('|', $rules);

        foreach ($rulesList as $rule) {
            if (strpos($rule, ':') !== false) {
                [$ruleName, $ruleValue] = explode(':', $rule, 2);
            } else {
                $ruleName = $rule;
                $ruleValue = null;
            }

            match ($ruleName) {
                'integer' => $this->validateInteger($value, $key),
                'numeric' => $this->validateNumeric($value, $key),
                'min' => $this->validateMin($value, (int)$ruleValue, $key),
                'max' => $this->validateMax($value, (int)$ruleValue, $key),
                default => null,
            };
        }
    }

    /**
     * Validate integer rule
     */
    protected function validateInteger(mixed $value, string $key): void {
        if (!is_numeric($value) || intval($value) != $value) {
            throw new InvalidArgumentException("Option '{$key}' must be an integer");
        }
    }

    /**
     * Validate numeric rule
     */
    protected function validateNumeric(mixed $value, string $key): void {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Option '{$key}' must be numeric");
        }
    }

    /**
     * Validate min rule
     */
    protected function validateMin(mixed $value, int $min, string $key): void {
        if (is_numeric($value) && $value < $min) {
            throw new InvalidArgumentException("Option '{$key}' must be at least {$min}");
        }
    }

    /**
     * Validate max rule
     */
    protected function validateMax(mixed $value, int $max, string $key): void {
        if (is_numeric($value) && $value > $max) {
            throw new InvalidArgumentException("Option '{$key}' must not exceed {$max}");
        }
    }

    /**
     * Execute the command - must be implemented in concrete classes
     */
    abstract public function execute(array $options): string;

    /**
     * Get the configuration array for this command
     * Used by the Filament UI and registry
     */
    public function toConfig(): array {
        return [
            'name' => $this->getDisplayName(),
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'danger_level' => $this->getDangerLevel(),
            'enabled' => true,
            'requires_confirmation' => $this->requiresConfirmation(),
            'options' => $this->getOptions(),
            'class' => static::class,
        ];
    }
}
