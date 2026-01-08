<?php

namespace EeqanLtd\FilamentCommandRunner\Support;

use Illuminate\Support\HtmlString;

/**
 * Utility class for formatting command output
 * Handles ANSI color conversion and special formatting
 */
class OutputFormatter {
    /**
     * Format command output for display in the UI
     * Converts ANSI colors to HTML and applies syntax highlighting
     */
    public static function format(?string $output, array $options = []): HtmlString {
        if (!$output) {
            return new HtmlString('');
        }

        $output = self::convertAnsiToHtml($output);
        $output = self::highlightSpecialPatterns($output);

        $maxHeight = $options['max_height'] ?? '400px';
        $showCopyButton = $options['show_copy_button'] ?? true;

        $html = "<div class='relative'>
                    <pre class='bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-xs overflow-x-auto whitespace-pre-wrap max-h-96 overflow-y-auto border border-gray-700'>{$output}</pre>";

        if ($showCopyButton) {
            $html .= "
                    <div class='absolute top-2 right-2'>
                        <button onclick='navigator.clipboard.writeText(this.parentElement.previousElementSibling.textContent)' 
                                class='text-gray-400 hover:text-white text-xs bg-gray-800 px-2 py-1 rounded'>
                            Copy
                        </button>
                    </div>";
        }

        $html .= "</div>";

        return new HtmlString($html);
    }

    /**
     * Convert ANSI color codes to HTML span tags
     */
    private static function convertAnsiToHtml(string $output): string {
        // Escape HTML first
        $output = htmlspecialchars($output);

        // ANSI color code patterns and their HTML equivalents
        $ansiColors = [
            '/\033\[0;32m(.*?)\033\[0m/' => '<span class="text-green-400">$1</span>',          // Green
            '/\033\[1;32m(.*?)\033\[0m/' => '<span class="text-green-300 font-bold">$1</span>', // Bold green
            '/\033\[0;31m(.*?)\033\[0m/' => '<span class="text-red-400">$1</span>',            // Red
            '/\033\[1;31m(.*?)\033\[0m/' => '<span class="text-red-300 font-bold">$1</span>',  // Bold red
            '/\033\[0;33m(.*?)\033\[0m/' => '<span class="text-yellow-400">$1</span>',         // Yellow
            '/\033\[1;33m(.*?)\033\[0m/' => '<span class="text-yellow-300 font-bold">$1</span>', // Bold yellow
            '/\033\[0;36m(.*?)\033\[0m/' => '<span class="text-cyan-400">$1</span>',           // Cyan
            '/\033\[1;36m(.*?)\033\[0m/' => '<span class="text-cyan-300 font-bold">$1</span>', // Bold cyan
            '/\033\[0;34m(.*?)\033\[0m/' => '<span class="text-blue-400">$1</span>',           // Blue
            '/\033\[1;34m(.*?)\033\[0m/' => '<span class="text-blue-300 font-bold">$1</span>', // Bold blue
            '/\033\[0;37m(.*?)\033\[0m/' => '<span class="text-gray-300">$1</span>',           // White
            '/\033\[1;37m(.*?)\033\[0m/' => '<span class="text-white font-bold">$1</span>',   // Bold white
        ];

        foreach ($ansiColors as $pattern => $replacement) {
            $output = preg_replace($pattern, $replacement, $output);
        }

        // Remove any remaining ANSI codes
        $output = preg_replace('/\033\[[0-9;]*m/', '', $output);

        return $output;
    }

    /**
     * Highlight special patterns in output
     */
    private static function highlightSpecialPatterns(string $output): string {
        // Highlight success/failure/warning indicators
        $output = preg_replace('/✅([^\n]*)/', '<span class="text-green-400 font-medium">✅$1</span>', $output);
        $output = preg_replace('/❌([^\n]*)/', '<span class="text-red-400 font-medium">❌$1</span>', $output);
        $output = preg_replace('/⚠️([^\n]*)/', '<span class="text-yellow-400 font-medium">⚠️$1</span>', $output);
        $output = preg_replace('/ℹ️([^\n]*)/', '<span class="text-blue-400 font-medium">ℹ️$1</span>', $output);

        return $output;
    }

    /**
     * Strip all formatting and return plain text
     */
    public static function stripFormatting(string $output): string {
        // Remove HTML tags
        $output = strip_tags($output);

        // Remove ANSI codes
        $output = preg_replace('/\033\[[0-9;]*m/', '', $output);

        // Remove emoji indicators
        $output = preg_replace('/[✅❌⚠️ℹ️]/', '', $output);

        return trim($output);
    }

    /**
     * Format execution metadata (header and footer)
     */
    public static function formatExecutionMetadata(
        string $commandName,
        array $options = [],
        ?float $executionTime = null,
        ?int $exitCode = null,
        bool $success = true
    ): string {
        $user = auth()->user();
        $userName = $user ? ($user->name ?? 'Unknown') : 'CLI';

        $output = "╭─────────────────────────────────────────────────────╮\n";
        $output .= "│                  COMMAND EXECUTION                  │\n";
        $output .= "╰─────────────────────────────────────────────────────╯\n\n";
        $output .= "Command: php artisan {$commandName}\n";
        $output .= "User: {$userName}\n";
        $output .= "Started: " . now()->format('Y-m-d H:i:s T') . "\n";
        $output .= "Environment: " . app()->environment() . "\n";

        if (!empty($options)) {
            $output .= "Options:\n";
            foreach ($options as $key => $value) {
                $output .= "  - {$key}: {$value}\n";
            }
        }

        $output .= str_repeat('═', 60) . "\n\n";

        return $output;
    }

    /**
     * Format execution footer
     */
    public static function formatExecutionFooter(
        float $executionTime = 0,
        int $exitCode = 0,
        ?string $error = null
    ): string {
        $output = "\n" . str_repeat('═', 60) . "\n";

        if ($error) {
            $output .= "❌ EXECUTION ERROR\n";
            $output .= "Error: {$error}\n";
        } else {
            $output .= "Completed: " . now()->format('Y-m-d H:i:s T') . "\n";
            $output .= "Duration: {$executionTime}s\n";
            $output .= "Exit Code: {$exitCode}\n";
            $output .= "Status: " . ($exitCode === 0 ? '✅ SUCCESS' : '❌ FAILED') . "\n";
        }

        return $output;
    }

    /**
     * Create a styled section header
     */
    public static function createSectionHeader(string $title): string {
        $padding = (60 - strlen($title)) / 2;
        $left = str_repeat(' ', (int)floor($padding));
        $right = str_repeat(' ', (int)ceil($padding));

        return "╭" . str_repeat('─', 58) . "╮\n" .
            "│{$left}{$title}{$right}│\n" .
            "╰" . str_repeat('─', 58) . "╯\n";
    }

    /**
     * Create a styled table
     */
    public static function createTable(array $headers, array $rows): string {
        $columnWidths = [];
        foreach ($headers as $header) {
            $columnWidths[] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $columnWidths[$index] = max($columnWidths[$index] ?? 0, strlen($cell));
            }
        }

        $separatorLine = '┌' . implode('┬', array_map(fn($w) => str_repeat('─', $w + 2), $columnWidths)) . '┐';
        $output = $separatorLine . "\n";

        // Header row
        $headerLine = '│';
        foreach ($headers as $index => $header) {
            $headerLine .= ' ' . str_pad($header, $columnWidths[$index]) . ' │';
        }
        $output .= $headerLine . "\n";

        $output .= '├' . implode('┼', array_map(fn($w) => str_repeat('─', $w + 2), $columnWidths)) . '┤' . "\n";

        // Data rows
        foreach ($rows as $row) {
            $rowLine = '│';
            foreach ($row as $index => $cell) {
                $rowLine .= ' ' . str_pad((string)$cell, $columnWidths[$index]) . ' │';
            }
            $output .= $rowLine . "\n";
        }

        $output .= '└' . implode('┴', array_map(fn($w) => str_repeat('─', $w + 2), $columnWidths)) . '┘';

        return $output;
    }
}
