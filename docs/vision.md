# Boitazik : vision et décisions

> Lu à la demande (planification, `/plan-tickets`). Pas importé dans `CLAUDE.md` pour ne pas consommer de tokens à chaque session.
> La roadmap vivante est le Kanban : https://github.com/users/ChrisManin/projects/3

## Double objectif

1. **Apprentissage de Claude Code.** Le propriétaire du projet n'écrit aucune ligne de code : il décrit, valide les plans et choisit les tickets, et Claude réalise. Le projet sert à pratiquer le mode plan, les skills, les hooks, les MCP, les subagents, la gestion du contexte et la revue de code. Chaque ticket indique la notion Claude Code qu'il exerce.
2. **Fonctionnel.** Lister et présenter les albums que chaque utilisateur possède physiquement.

## Fonctionnalités visées

- Saisir un album possédé avec un minimum d'effort ; le reste est complété automatiquement via des API externes.
- Pour chaque album : fiche de l'artiste et discographie complète.
- Écoute via le compte Spotify de l'utilisateur : l'album possédé et tout album de la discographie.
- Suggestions d'albums et d'artistes similaires.
- D'autres idées viendront au fil du projet.

## Décisions actées

- **Multi-utilisateur** : chaque compte a sa propre collection. Le catalogue (artistes, albums, éditions) est partagé.
- **Exemplaire précis** : on enregistre l'édition (pressage) : format, label, n° de catalogue, pays, année, code-barres. L'édition peut rester inconnue (on ne connaît alors que l'album).
- **Front et back-office séparés** : toutes les pages et tous les formulaires des utilisateurs sont en Vue 3 + Inertia. Filament `/admin` est réservé aux administrateurs, qui y gèrent le catalogue et les collections de tous les utilisateurs. Aucune fonctionnalité utilisateur dans Filament.
- **Tests** : TDD par tranche verticale. Les tests ne touchent jamais la base de dev (sqlite `:memory:`), et aucun appel HTTP réel en test (`Http::fake()`).

## API externes (proposition, à confirmer ticket par ticket)

| Besoin | API | Notes |
|---|---|---|
| Catalogue, éditions, code-barres, discographie | MusicBrainz | Gratuite, sans clé. Release-group = œuvre, release = édition. 1 req/s, User-Agent obligatoire. |
| Pochettes | Cover Art Archive | Indexée par MBID. |
| Écoute | Spotify Web API (OAuth par utilisateur) | Liens et embeds pour tous ; le Web Playback SDK exige Premium. À vérifier : *recommendations* et *related-artists* restreints pour les nouvelles apps depuis fin 2024. |
| Similaires | Last.fm (`artist.getSimilar`) | Alternative si Spotify ne le permet plus. |
| Complément pressages (optionnel) | Discogs | Riche sur les vinyles. |

## Schéma de données cible

- `artists` : name, sort_name, mbid (unique), spotify_id, country, image_url, enriched_at
- `albums` (l'œuvre, release-group MusicBrainz) : title, type (album/EP/live/compil…), first_release_year, mbid (unique), spotify_id, cover_url, enriched_at
- `album_artist` (pivot, collaborations) : album_id, artist_id, position
- `releases` (l'édition) : album_id, format (enum `ReleaseFormat` : CD, Vinyl, Cassette…), label, catalog_number, country, release_year, barcode (indexé), mbid (unique)
- `collection_items` (l'exemplaire possédé) : user_id, album_id, release_id (nullable), condition (enum `ItemCondition`, échelle Goldmine), acquired_at, notes
- Plus tard : `spotify_accounts` (tokens chiffrés), `tracks` si besoin

## Roadmap initiale (à transformer en tickets)

1. **Qualité** : hook PostToolUse Pint, permissions. L'isolation des tests (sqlite `:memory:`) est faite dans #4. Réécrit le ticket #2.
2. **Schéma** : migrations, modèles et relations, factories, enum, ressources Filament, tests Pest (relations, unicité des MBID, isolation des collections). Suite du ticket #2.
3. **Première tranche** : ajouter un album à ma collection et voir ma collection, en saisie manuelle, avec une policy par utilisateur.
4. **Client MusicBrainz et Cover Art Archive** : recherche et import par titre, puis par code-barres.
5. **Artiste et discographie complète** : job en file d'attente qui respecte le rate limit.
6. **Spotify** : OAuth, écoute de l'album et de la discographie.
7. **Similaires** : Last.fm.

Notions Claude Code à placer au fil des tickets : subagents d'exploration, `/code-review` avant PR, skill projet « nouveau client d'API » (étape 4), MCP supplémentaires si le besoin apparaît (doc Laravel, base de données).
