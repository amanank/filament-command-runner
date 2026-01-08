<?php

namespace EeqanLtd\FilamentCommandRunner\Abstractions;

/**
 * Interface for executable commands in the Command Runner
 * All commands must implement this interface to be executable via the UI
 */
interface CommandRunnerInterface {
    /**
     * Get the command name/identifier (e.g., 'eloquent:query', 'members:find-direct-debit')
     */
    public function getCommandName(): string;

    /**
     * Get the display name for the command (e.g., 'Find Direct Debit Members')
     */
    public function getDisplayName(): string;

    /**
     * Get the command description
     */
    public function getDescription(): string;

    /**
     * Get the category (e.g., 'Data Exploration', 'Member Analysis')
     */
    public function getCategory(): string;

    /**
     * Get the danger level: 'low', 'medium', or 'high'
     * - low: Read-only or safe operations
     * - medium: May modify data but potentially reversible
     * - high: Critical operations that could cause data loss
     */
    public function getDangerLevel(): string;

    /**
     * Get all available options/parameters for this command
     * Returns array of option definitions with name, type, label, validation, etc.
     */
    public function getOptions(): array;

    /**
     * Validate the provided options before execution
     * Should throw InvalidArgumentException if validation fails
     */
    public function validateOptions(array $options): void;

    /**
     * Execute the command with the given options
     * Returns the output as a string
     */
    public function execute(array $options): string;

    /**
     * Whether this command requires explicit user confirmation before execution
     */
    public function requiresConfirmation(): bool;
}
