# SessionStart hook: tells Claude which ticket the current branch belongs to.
# Branch naming: ticket #N -> task/<10000 + N> (#4 -> task/10004).
$branch = git branch --show-current
if ($branch -match '^task/(\d{5,})$' -and [int]$Matches[1] -gt 10000) {
    $ticket = [int]$Matches[1] - 10000
    "Current branch: $branch -> GitHub ticket #$ticket. Wrap up with /finish-task."
} else {
    "Current branch: $branch (no ticket). Planning session: /plan-tickets. Start a ticket: /start-task <N>."
}
