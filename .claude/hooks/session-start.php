<?php

// SessionStart hook: tells Claude which ticket the current branch belongs to.
// Written in PHP so it runs the same way on Windows and WSL.
// Branch naming: ticket #N -> task/<10000 + N> (#4 -> task/10004).
$branch = trim((string) shell_exec('git branch --show-current'));

if (preg_match('/^task\/(\d{5,})$/', $branch, $matches) && (int) $matches[1] > 10000) {
    $ticket = (int) $matches[1] - 10000;
    echo "Current branch: {$branch} -> GitHub ticket #{$ticket}. Wrap up with /finish-task.".PHP_EOL;
} else {
    echo "Current branch: {$branch} (no ticket). Planning session: /plan-tickets. Start a ticket: /start-task <N>.".PHP_EOL;
}
