# Bootstrap : workflow Claude Code ↔ GitHub

> Aide-mémoire pour la mise en place. À supprimer (ou garder comme trace) une fois le ticket de bootstrap terminé.
> Plan complet : `C:\Users\cmn\.claude\plans\serene-weaving-kernighan.md`

## 1. Le cycle de travail visé

```
SESSION PLANIFICATION (sur main)
  tu décris un besoin → Claude en mode plan → tu valides
  → /plan-tickets : Claude propose la liste des tickets → tu confirmes
  → tickets créés (label task, Size, Priority), Project 3 en Backlog
                │
   tu choisis le ticket N (tu le passes en Ready)
                ▼
SESSION TICKET (/clear, ou nouvelle session claude)
  /start-task N → branche task/<10000+N>, ticket en In progress
  → mode plan sur le ticket → tu valides → implémentation en TDD
  → /finish-task → tests + Pint + PR « Closes #N », ticket en In review
                │
   tu relis et merges la PR
                ▼
  ticket fermé → workflow natif du Project → Done
  → /clear, puis ticket suivant
```

Nommage des branches : ticket #N → `task/<10000+N>`. Par exemple #4 → `task/10004`, #12 → `task/10012`.

## 2. Ce qui est déjà prêt (non commité, sur `task/10002`)

| Fichier | Rôle | Chargé |
|---|---|---|
| `CLAUDE.md` | stack, conventions, workflow tickets, liens (28 lignes) | à chaque session |
| `docs/vision.md` | vision, objectifs d'apprentissage, décisions, API, schéma, roadmap initiale | à la demande (planification) |
| `.mcp.json` | serveur MCP `github` ; le token est lu dans `${GITHUB_PAT}` | au démarrage |
| `.claude/skills/plan-tickets/` | plan validé → tickets dans le Kanban | toi seul (`/plan-tickets`) |
| `.claude/skills/start-task/` | branche, In Progress, mode plan | toi seul (`/start-task <N>`) |
| `.claude/skills/finish-task/` | tests, Pint, graphify, commit, push, PR « Closes #N » | toi seul (`/finish-task`) |
| `.claude/hooks/session-start.ps1` + `.claude/settings.json` | indique à Claude le ticket de la branche courante | à chaque session |
| Mémoire Claude (hors dépôt) | ton rôle de manager, le français, l'explication des notions | à chaque session |

## 3. Tes actions manuelles

- [ ] **Créer le ticket de bootstrap** dans le [Kanban](https://github.com/users/ChrisManin/projects/3). Titre suggéré : « Workflow Claude Code ↔ GitHub ». Note son numéro : probablement #4, car le #3 est une PR.
- [ ] **Créer un token GitHub** sur https://github.com/settings/tokens : *Generate new token (classic)*, cases `repo` et `project`.
- [ ] **Enregistrer le token** dans **ton propre terminal PowerShell** (pas avec `!` dans Claude Code, sinon il apparaîtrait dans la conversation) :
  ```powershell
  [Environment]::SetEnvironmentVariable("GITHUB_PAT", "ghp_...", "User")
  ```
- [ ] **Activer l'automatisation Done** : Project 3 → menu `⋯` → **Workflows** → *Item closed* → Set status : **Done** → Enable. Vérifier aussi *Pull request merged*.
- [ ] **Relancer Claude Code** dans un **nouveau** terminal, pour qu'il voie la variable d'environnement :
  ```powershell
  cd C:\Users\cmn\Documents\boitazik
  claude -c
  ```
  `-c` reprend la conversation précédente. Accepte le serveur `github` quand Claude Code le propose.
- [ ] **Vérifier** : tape `/mcp`. Le serveur `github` doit apparaître comme connecté.

## 4. Ce que Claude fera ensuite

Tu lui donnes le numéro du ticket de bootstrap, puis Claude :

1. crée la branche `task/<10000+N>` depuis `main`, en y emportant les changements préparés ;
2. lit le Project 3 via le MCP (champ `Status`) et passe le ticket en **In Progress** ;
3. commite le bootstrap, puis tu lances `/finish-task` pour tester le cycle réel jusqu'à la PR ;
4. après ton merge, on vérifie que le ticket passe en **Done**.

## 5. Ensuite : première session de planification

`/clear`, puis sur `main` : `/plan-tickets` transforme la roadmap de `docs/vision.md` en tickets. Le ticket #2 « Tests unitaires » sera réécrit (isolation des tests + schéma).

## En cas de souci

- `/mcp` affiche `github` en échec : vérifie la variable dans un nouveau terminal (`$env:GITHUB_PAT` ne doit pas être vide) et les scopes du token.
- Le hook ne s'affiche pas : `/hooks` liste les hooks chargés.
- Claude Code ne propose pas le serveur `github` : `claude mcp list` dans le terminal.
