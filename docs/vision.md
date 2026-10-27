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
- **Suppression dans le catalogue** : aucun utilisateur ne perd un exemplaire. Un album possédé ne se supprime pas : l'admin le fusionne dans le bon album (exemplaires et éditions déplacés). Un artiste supprimé est seulement retiré de ses albums, sauf s'il est fusionné dans le bon artiste (ticket #38).
- **Tests** : TDD par tranche verticale. Les tests ne touchent jamais la base de dev (sqlite `:memory:`), et aucun appel HTTP réel en test (`Http::fake()`).

## API externes (proposition, à confirmer ticket par ticket)

| Besoin | API | Notes |
|---|---|---|
| Catalogue, éditions, code-barres, discographie | MusicBrainz | Gratuite, sans clé. Release-group = œuvre, release = édition. 1 req/s, User-Agent obligatoire. |
| Pochettes | Cover Art Archive | Indexée par MBID. |
| Écoute | Spotify : Web Playback SDK avec notre propre interface de lecteur, OAuth par utilisateur | App privée, 5 utilisateurs Premium maximum (mode développement). Voir « Investigation Spotify et Last.fm ». |
| Similaires | Last.fm (`artist.getSimilar`) | Spotify ne fournit plus de similaires aux nouvelles apps. Usage non commercial uniquement. Voir « Investigation Spotify et Last.fm ». |
| Complément pressages (optionnel) | Discogs | Riche sur les vinyles. |

## Investigation Spotify et Last.fm (octobre 2026, ticket #18)

Sources officielles consultées le 2026-10-27, listées en fin de section. Les points non confirmés par une source officielle sont signalés « non confirmé ».

### Spotify

**Mode d'une app neuve.** Une app créée aujourd'hui reste en mode développement :

- le propriétaire de l'app doit avoir un compte Premium, sinon l'app cesse de fonctionner ;
- l'app a au maximum 5 utilisateurs authentifiés, ajoutés à la main (nom et e-mail Spotify) dans le Dashboard. Un utilisateur absent de cette liste reçoit une erreur 403 ;
- chaque développeur dispose d'un seul Client ID en mode développement. Le changelog de juillet 2026 en annonce jusqu'à 25, avec des quotas comptés par compte développeur.

Le mode étendu (extended quota) est réservé depuis le 15 mai 2025 aux organisations : entreprise immatriculée, service lancé, au moins 250 000 utilisateurs actifs par mois (MAU), présence sur les marchés clés. Un projet personnel ne peut pas l'obtenir.

**Endpoints restreints.** Pour les apps créées depuis le 27 novembre 2024 :

- plus d'accès à *Recommendations*, *Related Artists*, *Audio Features*, *Audio Analysis*, *Featured Playlists*, *Category Playlists*, ni aux playlists algorithmiques ou éditoriales ;
- `preview_url` disparaît des réponses qui renvoient plusieurs éléments.

Depuis février 2026, le mode développement retire aussi :

- les endpoints *Get Several Albums*, *Get Several Artists*, *Get Several Tracks* (et les autres « Several »), ainsi que *Get Artist's Top Tracks*, *New Releases* et *Browse Categories* ;
- les champs `popularity`, `label`, `available_markets` et `followers`.

`external_ids`, qui contient l'UPC (le code-barres) et l'ISRC (l'identifiant d'un enregistrement), est conservé depuis le changelog de mars 2026.

**Endpoints toujours utiles.**

- *Get Album*, *Get Artist* et *Get Artist's Albums* : maximum 10 résultats par page, avec `offset` pour paginer.
- *Search* :
  - filtres `upc:`, `album:`, `artist:` et `year:` toujours documentés ;
  - `limit` maximum 10 (5 par défaut), `offset` maximum 1000 ;
  - la recherche `upc:` est documentée, mais je ne l'ai pas testée en réel.

Ces endpoints ne touchent pas aux données d'un utilisateur, donc le flux **Client Credentials** suffit : l'app s'authentifie seule, sans OAuth utilisateur ni refresh token, avec un nouveau jeton environ toutes les heures. Non confirmé : que ce flux échappe aux contraintes du mode développement (Premium du propriétaire, quotas).

**Recherche d'un album pour remplir `spotify_id`.** Ordre proposé :

1. `q=upc:<barcode>&type=album` à partir du code-barres d'une édition possédée ;
2. sinon `q=album:<titre> artist:<artiste>&type=album`, en vérifiant le titre, l'artiste et l'année ;
3. sinon, laisser `spotify_id` vide (correction manuelle par un admin).

Le `spotify_id` d'un artiste s'obtient avec `type=artist`, ou par l'artiste de l'album trouvé.

Piste non confirmée : MusicBrainz relie parfois les éditions numériques à leur page Spotify (relations URL), ce qui fournirait des liens sans appel à Spotify.

**OAuth utilisateur (Authorization Code + PKCE).**

- Scopes :
  - `streaming` pour le Web Playback SDK, qui exige Premium ;
  - `user-modify-playback-state` et `user-read-playback-state` pour piloter Spotify Connect ;
  - `user-read-email` et `user-read-private` pour le profil, mais en mode développement `/me` ne renvoie plus `email` ni `product`.
- Le refresh token peut ne pas être renvoyé lors d'un rafraîchissement : il faut alors garder l'ancien.
- L'adresse de retour doit être en HTTPS ou en loopback (`http://127.0.0.1:PORT`). `localhost` est refusé.

Avec 5 utilisateurs maximum, cette option ne convient qu'à un usage privé.

**Options d'écoute.**

| Option | Prérequis | Limites |
|---|---|---|
| Lien `open.spotify.com/album/<id>` | `spotify_id` seulement | Quitte Boitazik pour l'app ou le site Spotify. |
| Embed iframe `open.spotify.com/embed/album/<id>` | `spotify_id`, attribut `allow="encrypted-media"` | Aucun OAuth ni Premium requis. Morceau complet si l'utilisateur est connecté à Spotify dans son navigateur, sinon extrait de 30 s (non confirmé : seul un post communautaire le dit ; la doc officielle parle d'extraits « parfois »). Sur mobile, probablement extraits seulement (non confirmé). |
| Web Playback SDK | OAuth par utilisateur, scope `streaming`, Premium (hors offres mobiles) | 5 utilisateurs maximum en mode développement. Interdit en projet commercial sans accord écrit. |

**Conditions (Developer Terms v10 et Developer Policy, 15 mai 2025).**

- **Stockage** : interdiction de constituer une base de contenus Spotify ; seul le cache temporaire des métadonnées et des pochettes est permis. Conserver un `spotify_id` (le lien nécessaire au fonctionnement) paraît défendable ; c'est notre lecture, les textes ne parlent pas des identifiants. En revanche, il ne faut pas copier durablement les pochettes ni les métadonnées Spotify : les pochettes viennent déjà de Cover Art Archive.
- **Attribution** : citer Spotify comme source, utiliser son logo selon la charte, et renvoyer vers l'album sur Spotify.
- **Interdits** : usage commercial et monétisation, entraînement de modèles d'IA.
- **Déconnexion** : les données d'un utilisateur doivent être supprimées dans les 5 jours.

### Last.fm

**Clé.**

- Gratuite, avec un compte Last.fm, via le formulaire `last.fm/api/account/create`. On obtient une API key et un shared secret.
- Pour nos appels, la clé seule suffit : `artist.getSimilar` et `album.getInfo` ne demandent pas d'authentification.
- Non confirmé : la délivrance immédiate de la clé et le détail du formulaire (nécessite une connexion).

**`artist.getSimilar`.**

- Paramètres : `artist` ou `mbid`, `autocorrect`, `limit`.
- Réponse : `name`, `mbid`, `match` (score de 0 à 1), `url`, `image`.

**`album.getInfo`.**

- Paramètres : `artist` + `album`, ou `mbid`, plus `autocorrect`, `lang` et `username`.
- Réponse : `listeners`, `playcount`, `toptags`, `tracks` et `url`.

**Albums similaires.** Il n'existe pas de `album.getSimilar` (page 404). On les déduit, au choix :

- avec `artist.getSimilar` puis `artist.getTopAlbums` ;
- avec `album.getTopTags` puis `tag.getTopAlbums`.

**MBID.** Ils sont souvent absents ou périmés : la doc les dit « may be empty ». Il faut rapprocher par `mbid` quand il est présent, sinon par le nom, et vérifier dans MusicBrainz.

**Formats et erreurs.** Réponse en XML par défaut, en JSON avec `format=json`. Codes d'erreur : 6 (paramètres invalides), 10 (clé invalide), 26 (clé suspendue), 29 (rate limit dépassé).

**Conditions (API Terms of Service).**

- **§3.1** : usage non commercial uniquement. Le terme « commercial » n'est pas défini ; en cas de doute, il faut contacter Last.fm.
- **§2.7** :
  - les pages publiques qui utilisent l'API doivent être approuvées par écrit par Last.fm ;
  - il faut afficher un bouton « powered by AudioScrobbler » ;
  - il faut des liens vers les pages Last.fm des artistes et des albums.

  À traiter avant toute mise en ligne publique.
- **§4.3.4** : cache obligatoire selon les en-têtes HTTP, et 100 Mo maximum de données Last.fm stockées.
- **§4.4** : aucune limite chiffrée dans les conditions actuelles (le « 5 requêtes/s » souvent cité vient d'anciennes conditions). La page d'introduction de l'API prévient qu'un compte qui fait plusieurs appels par seconde en continu peut être suspendu. Elle demande aussi de ne pas appeler l'API au chargement d'une page et d'envoyer un User-Agent identifiable.
- **§5.1.8** : images et pochettes exclues de la licence. On ne les utilise jamais, les pochettes viennent de Cover Art Archive.

Piste non confirmée si Last.fm pose problème (approbation écrite, usage commercial) : ListenBrainz (MetaBrainz) propose des artistes similaires par MBID, mais sa documentation officielle reste à trouver.

### Recommandation

**Décision du propriétaire (2026-10-27).** Boitazik reste privé, avec moins de 5 utilisateurs, et le propriétaire a un compte Premium. Il ne veut pas de l'embed Spotify, mais un lecteur audio propre à Boitazik.

- **Écoute : Web Playback SDK et OAuth par utilisateur.**
  - Il est impossible de récupérer le fichier audio : il est protégé par DRM et son extraction est interdite par les Terms. Le SDK transforme le navigateur en appareil Spotify Connect qui décode le son.
  - L'interface du lecteur est entièrement la nôtre (Vue) : lecture, pause, piste suivante et précédente, barre de progression, volume, liste des pistes. On la pilote par le SDK (`togglePlay`, `seek`, `nextTrack`, `setVolume`, événement `player_state_changed`) et par la Web API (`PUT /me/player/play` avec `context_uri` de l'album).
  - Chaque utilisateur qui écoute doit avoir Premium (hors offres mobiles), être inscrit dans la liste des utilisateurs du Dashboard (5 maximum) et connecter son compte Spotify une fois.
  - Les Terms imposent d'afficher la pochette et les métadonnées pendant la lecture, avec l'attribution Spotify (logo) et un lien vers l'album sur Spotify.
  - Sur iOS, la lecture ne démarre pas automatiquement après un transfert : il faut un clic de l'utilisateur (`activateElement()`).
  - Le `spotify_id` des albums est rempli par la Web API en Client Credentials, indépendamment des comptes des utilisateurs.
  - `spotify_accounts` (tokens chiffrés) reste dans le schéma cible.
- **Similaires : Last.fm.**
  - Artistes similaires via `artist.getSimilar` par MBID.
  - Albums suggérés via les meilleurs albums des artistes similaires (`artist.getTopAlbums`).
  - Chaque résultat est rapproché de MusicBrainz, les pochettes viennent de Cover Art Archive.
  - Résultats en cache, attribution et liens Last.fm.
  - Demande d'approbation écrite à Last.fm avant une mise en ligne publique.

### Tickets proposés (à créer avec `/plan-tickets`)

1. **Client Spotify et rapprochement des albums.** Le propriétaire crée l'app Spotify (Premium requis) et renseigne ses clés dans `.env`. Le client Spotify utilise Client Credentials et suit le skill « nouveau client d'API ». Un job remplit `albums.spotify_id` : recherche par UPC, puis par titre et artiste, puis vide si rien n'est trouvé. Le rapprochement est déclenché après l'import et à la demande depuis Filament. Vérifier d'abord la piste des relations URL MusicBrainz.
2. **Connexion du compte Spotify.** OAuth Authorization Code + PKCE (scopes `streaming`, `user-read-email`, `user-read-private`, `user-modify-playback-state`, `user-read-playback-state`). Table `spotify_accounts` avec tokens chiffrés et rafraîchissement. Bouton « Connecter / déconnecter Spotify » dans le profil. Suppression des données à la déconnexion.
3. **Lecteur Boitazik sur la fiche album.** Chargement du Web Playback SDK, lecture de l'album (`context_uri`). Interface Vue maison : pochette, piste en cours, lecture/pause, piste précédente/suivante, barre de progression, volume, liste des pistes cliquables. Attribution Spotify. Messages clairs si le compte n'est pas connecté ou pas Premium, ou si l'album n'a pas de `spotify_id`.
4. **Lecteur persistant et discographie.** Lecteur fixé en bas de page qui continue entre les pages (layout Inertia persistant). Rapprochement en lot des albums de la discographie (un appel par album, car *Get Several* est indisponible), en respectant le rate limit, et bouton « Écouter » sur chaque album.
5. **Client Last.fm et artistes similaires.** Client avec cache, `artist.getSimilar` par MBID avec repli sur le nom, et bloc « Artistes similaires » sur la fiche artiste. Seuls les artistes connus de MusicBrainz sont liés. Lien Last.fm et attribution.
6. **Albums suggérés.** Meilleurs albums des artistes similaires, rapprochés de MusicBrainz, sur la fiche album ou la fiche artiste, avec un bouton pour les ajouter à ma collection.

### Sources

Spotify :

- https://developer.spotify.com/blog/2024-11-27-changes-to-the-web-api (27 novembre 2024)
- https://developer.spotify.com/blog/2025-04-15-updating-the-criteria-for-web-api-extended-access (15 avril 2025)
- https://developer.spotify.com/blog/2026-02-06-update-on-developer-access-and-platform-security (6 février 2026, mis à jour le 9 mars 2026)
- https://developer.spotify.com/documentation/web-api/references/changes/february-2026 et https://developer.spotify.com/documentation/web-api/tutorials/february-2026-migration-guide
- https://developer.spotify.com/documentation/web-api/references/changes/march-2026 (`external_ids` conservé)
- https://developer.spotify.com/documentation/web-api/references/changes/july-2026 (Client IDs et quotas)
- https://developer.spotify.com/documentation/web-api/concepts/quota-modes
- https://developer.spotify.com/documentation/web-api/reference/search
- https://developer.spotify.com/documentation/web-api/tutorials/client-credentials-flow
- https://developer.spotify.com/documentation/web-api/tutorials/code-pkce-flow
- https://developer.spotify.com/documentation/web-api/concepts/redirect_uri
- https://developer.spotify.com/documentation/web-api/concepts/scopes
- https://developer.spotify.com/documentation/web-playback-sdk
- https://developer.spotify.com/documentation/embeds et https://developer.spotify.com/documentation/embeds/tutorials/troubleshooting
- https://developer.spotify.com/terms et https://developer.spotify.com/policy (15 mai 2025)

Last.fm :

- https://www.last.fm/api, https://www.last.fm/api/intro et https://www.last.fm/api/rest
- https://www.last.fm/api/tos
- https://www.last.fm/api/show/artist.getSimilar, https://www.last.fm/api/show/album.getInfo, https://www.last.fm/api/show/artist.getTopAlbums et https://www.last.fm/api/show/tag.getTopAlbums

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
6. **Spotify** : rapprochement `spotify_id`, OAuth par utilisateur, lecteur Boitazik maison via le Web Playback SDK pour l'album et la discographie (voir ticket #18).
7. **Similaires** : Last.fm, artistes similaires et albums suggérés.

Notions Claude Code à placer au fil des tickets : subagents d'exploration, `/code-review` avant PR, skill projet « nouveau client d'API » (étape 4), MCP supplémentaires si le besoin apparaît (doc Laravel, base de données).
