---
name: plan-tickets
description: Turn an approved plan into GitHub tickets added to the Backlog of the Kanban.
argument-hint: [optional subject]
disable-model-invocation: true
---

Turn the plan approved in this session (or the subject: $ARGUMENTS) into tickets for ChrisManin/boitazik. Project IDs: `.claude/skills/project-ids.md`.

1. Read `docs/vision.md` for the vision and the decisions already made. If no approved plan exists in the session yet, build one first in plan mode.
2. With the `github` MCP server, list the open issues and the items in Project 3 so you don't create duplicates. An existing ticket can be rewritten instead (say so).
3. Split the work into tickets, **one vertical slice each**, small enough for a single session. Each ticket is **self-contained**: a fresh session must be able to handle it with nothing but the ticket. Body, in French:

   ```
   ## Contexte
   ## Critères d'acceptation
   - [ ] ...
   ## Pistes techniques
   ## Notion Claude Code exercée
   ## Dépendances
   Dépend de #X (or "Aucune")
   ```

4. **Show the list and wait for explicit confirmation** before creating or changing anything on GitHub. For each ticket: title, one-line summary, dependencies, suggested `Size` (XS–XL) and `Priority` (P0–P2).
5. After confirmation: create the issues (label `task`) in dependency order. Add each one to Project 3 with `Status` = `Backlog`, plus the approved `Size` and `Priority`. Then fill in the real `#X` numbers in the dependencies.
6. If the plan settles a new decision (API, schema, convention), update the "Décisions actées" section of `docs/vision.md`.
7. Finish with the list of created tickets (number + title) and recommend which one to tackle first. The user decides: they move the chosen one to `Ready`, then launch `/start-task <N>`.
