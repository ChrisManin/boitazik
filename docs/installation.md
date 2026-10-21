# Installer le poste (WSL)

Procédure pour installer un poste de dev Boitazik sous **WSL (Ubuntu 24.04)**. PHP, Composer et Node tournent directement dans WSL, MySQL tourne dans Docker. Toutes les commandes se tapent dans un **terminal WSL** (invite `utilisateur@machine:~$`), jamais dans PowerShell.

## 1. Prérequis

| Outil | Vérification | Remarque |
|---|---|---|
| PHP 8.3 + extensions `mbstring`, `xml`, `curl`, `zip`, `intl`, `pdo_mysql`, `pdo_sqlite` | `php -v`, `php -m` | `pdo_sqlite` sert aux tests (base `:memory:`) |
| Composer | `composer -V` | |
| Node (via fnm) + npm | `node -v` | |
| Docker Engine dans WSL | `docker info` | systemd actif (`/etc/wsl.conf`), utilisateur dans le groupe `docker` |
| uv | `uv --version` | sert à installer graphify |
| Claude Code | `claude --version` | |

## 2. Token GitHub (serveur MCP `github`)

Le fichier `.mcp.json` lit le token dans la variable `GITHUB_PAT`.

1. Sur https://github.com/settings/tokens : *Generate new token (classic)*, cases `repo` et `project`.
2. Dans un terminal **WSL**, en remplaçant `COLLE_TON_TOKEN_ICI` par la vraie valeur :
   ```bash
   echo 'export GITHUB_PAT="COLLE_TON_TOKEN_ICI"' >> ~/.bashrc
   source ~/.bashrc
   echo ${#GITHUB_PAT}   # doit afficher 40
   ```
3. **Relancer Claude Code** (`/exit`, puis `claude` ou `claude -c`). `/mcp` ne relit pas les variables d'environnement : seul un redémarrage les prend en compte.

Pièges déjà rencontrés :
- Si la commande est tapée dans PowerShell, `~/.bashrc` désigne un fichier Windows que WSL ne lit jamais.
- Ne jamais coller le token (ni la ligne qui le contient) dans une conversation Claude. S'il y apparaît, il faut le révoquer et en générer un nouveau.

## 3. graphify

```bash
uv tool install graphifyy      # installe graphify dans ~/.local/bin
graphify hook install          # hooks git + pilote de merge graphify (dans .git/config)
graphify update .              # construit graphify-out/ (non versionné)
```

## 4. Application

```bash
composer install
npm install && npm run build   # sans public/build, une partie des tests échoue
cp .env.example .env
php artisan key:generate
```

Dans `.env`, renseigner les identifiants de seed (`SEED_ADMIN_*`, `SEED_USER_*`). Les identifiants MySQL (`DB_*`) servent à la fois à Laravel et à Docker Compose.

```bash
docker compose up -d           # lance MySQL 8 (redémarre ensuite tout seul avec Docker)
php artisan migrate --seed
```

## 5. Vérifications

- `docker compose ps` : le service `mysql` est `healthy`.
- `php artisan test` : tous les tests passent.
- `php artisan serve`, puis http://localhost:8000 : l'appli s'affiche et la connexion fonctionne.
- Dans Claude Code : `/mcp` montre `github` connecté, `/hooks` liste le hook `SessionStart`. Au démarrage, Claude annonce le ticket de la branche courante.

## Bon à savoir

- Les hooks Claude Code du projet sont des scripts PHP (`.claude/hooks/*.php`) : ils fonctionnent de la même façon sous Windows et sous WSL.
- Repartir d'une base vide : `docker compose down -v` (supprime le volume), puis `docker compose up -d` et `php artisan migrate --seed`.
