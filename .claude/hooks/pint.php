<?php

// PostToolUse hook (Edit|Write): formats the edited PHP file with Pint.
// Written in PHP so it runs the same way on Windows and WSL.
// Silent and never blocking: Pint output is discarded and the exit code is always 0.
$input = json_decode((string) file_get_contents('php://stdin'), true);
$file = $input['tool_input']['file_path'] ?? '';

if (! is_string($file) || ! str_ends_with($file, '.php') || ! is_file($file)) {
    exit(0);
}

$pint = dirname(__DIR__, 2).'/vendor/bin/pint';
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($pint).' '.escapeshellarg($file).' 2>&1');

exit(0);
