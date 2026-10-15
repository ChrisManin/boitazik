---
name: start-task
description: Start work on a GitHub ticket - create or resume its branch, move it to In progress, then plan.
argument-hint: <issue number>
disable-model-invocation: true
---

Start ticket #$ARGUMENTS of ChrisManin/boitazik. Project IDs: `.claude/skills/project-ids.md`.

1. If `$ARGUMENTS` is not a positive integer, stop and ask for the ticket number.
2. Read issue #$ARGUMENTS with the `github` MCP server. Stop if it doesn't exist, is closed, or is a pull request.
3. Check `git status --short`. If the tree is dirty, stop and show the changes: never stash or discard without asking.
4. Branch `task/<10000 + N>` (#4 → `task/10004`, #12 → `task/10012`). Run `git fetch origin` first.
   - it already exists (locally or on `origin`): `git checkout` it. It's a resume.
   - otherwise: `git checkout --no-track -b <branch> origin/main`. `--no-track` keeps the branch from tracking `main`; the first push will set its upstream.
5. In Project 3: add the issue if it isn't in it yet, then set `Status` = `In progress`. Assign the issue to `ChrisManin` if it has no assignee.
6. Summarize in French, in a few lines: goal, acceptance criteria, dependencies (if a "Dépend de #X" ticket isn't closed yet, flag it), and the Claude Code concept the ticket exercises.
7. Enter plan mode (EnterPlanMode) to design the implementation. Don't write code before the plan is approved.
