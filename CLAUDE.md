# Boitazik

Catalogue des albums physiques de chaque utilisateur, enrichi par des API externes (MusicBrainz, Spotify…).
Vision, décisions et schéma cible : `docs/vision.md`. À lire en session de planification, inutile en session ticket.

## Stack et commandes

- Laravel 10 (PHP 8.1+), Breeze Vue 3 + Inertia + TypeScript, Filament 3 (panel `/admin`), Pest 2, MySQL (dev)
- Tests : `php artisan test`. Format PHP : `vendor/bin/pint`. Front : `npm run build`

## Conventions

- Code, noms et commentaires en anglais ; interface et échanges en français
- TDD par tranche verticale : un test rouge, puis le code, puis le refactoring
- Les tests ne touchent jamais la base de dev ; aucun appel HTTP réel en test (`Http::fake()`)
- Secrets uniquement dans `.env` / variables d'environnement, jamais commités

## Workflow tickets (GitHub)

- Dépôt : https://github.com/ChrisManin/boitazik. Kanban : https://github.com/users/ChrisManin/projects/3
- Colonnes `Status` : Backlog (créé par Claude) → Ready (choisi par le propriétaire) → In progress (`/start-task`) → In review (`/finish-task`) → Done (merge, automatisation native)
- Accès GitHub via le serveur MCP `github` (`.mcp.json`). Le CLI `gh` n'est pas installé
- Ticket #N → branche `task/<10000+N>` (#4 → `task/10004`, #12 → `task/10012`), créée depuis `main`
- Une session Claude par ticket. Le propriétaire choisit le ticket, Claude l'exécute
  - `/plan-tickets` : transforme un plan validé en tickets (sur `main`)
  - `/start-task <N>` : branche, ticket en In Progress, puis mode plan
  - `/finish-task` : tests, Pint, PR « Closes #N », ticket en In review. Le merge ferme le ticket et l'automatisation du Project le passe en Done
- Après le merge : `/clear` avant le ticket suivant

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
