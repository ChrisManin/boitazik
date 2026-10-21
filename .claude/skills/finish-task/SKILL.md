---
name: finish-task
description: Wrap up the current ticket - tests, Pint, graph, commit, push and PR "Closes #N".
disable-model-invocation: true
---

Wrap up the ticket for the current branch.

1. Read `git branch --show-current`. It must match `task/<number>` with number > 10000; otherwise stop. Ticket N = number − 10000 (`task/10004` → #4).
2. Run `vendor/bin/pint`, then `php artisan test`. **If a test fails, stop**: show the failure and don't open the PR.
3. Run `graphify update .`.
4. Show `git status --short` and commit the work tied to the ticket. Concise message in French, following the repository's style. Don't commit `.env` or any secret.
5. `git push -u origin <branch>`.
6. With the `github` MCP server, open (or update, if one already exists) the PR `<branch>` → `main`:
   - title: the issue title
   - body: summary of changes, how to verify, then the line `Closes #<N>`
7. In Project 3, set the ticket's `Status` to `In review` (IDs: `.claude/skills/project-ids.md`), then check it stuck. Project automations react to the PR's `Closes #N` and can overwrite the status a few seconds later.
   - Start this step only once step 6 has returned. Never call it in the same turn as the PR creation or update.
   - Update: `projects_write` → `update_project_item` (`owner: ChrisManin`, `project_number: 3`, `item_owner: ChrisManin`, `item_repo: boitazik`, `issue_number: N`, `updated_field: {"id": 416060108, "value": "df73e18b"}`). Keep the returned item `id`.
   - Read it back in a separate call: `projects_get` → `get_project_item` (`owner: ChrisManin`, `project_number: 3`, `item_id`, `field_names: ["Status"]`).
   - If it isn't `In review`, update it once more, then read it back again. One retry only.
8. Reply in French with the PR link and the ticket status **as read back in step 7**. Say "In review" only if the read-back confirms it. Otherwise give the actual status and point to the issue timeline (`github-project-automation` events, https://github.com/ChrisManin/boitazik/issues/N): a Project 3 workflow is likely misconfigured, and the MCP server can't read workflow settings. Then remind the user:
   - reviewing and merging is their job (on GitHub, or by asking Claude). The merge closes #N and the Project automation moves it to Done;
   - after the merge: `/clear`, then `/start-task <next>` or a planning session.

Never delete the `task/*` branch, locally or on `origin`, even after the merge (no `git branch -d/-D`, no `git push --delete`, no branch deletion when merging).
