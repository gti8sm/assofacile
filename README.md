# AssoFacile

SaaS de gestion d'association (PHP + MySQL), modulaire (core + modules activables).

## Installation locale (rapide)

1. Copier `.env.example` vers `.env` et remplir la connexion MySQL.
2. Créer la base vide.
3. Exécuter les SQL dans l'ordre :
   - `database/migrations/001_core.sql`
   - `database/migrations/002_modules.sql`
   - `database/migrations/003_users_admin.sql`
   - `database/migrations/010_treasury.sql`
   - `database/migrations/011_treasury_categories.sql`
   - `database/migrations/012_treasury_add_category.sql`
   - `database/migrations/013_treasury_attachments.sql`
   - `database/migrations/020_google_tokens.sql`
   - `database/migrations/021_drive_config.sql`
   - `database/seed.sql`
4. Servir `public/` (Apache/Nginx).
5. Se connecter :
   - email: `admin@demo.local`
   - mdp: `admin123`

## cPanel

- DocumentRoot à pointer sur `public/`.
- Activer `mod_rewrite` (le `.htaccess` est fourni).

## Stockage fichiers (justificatifs)

- Les justificatifs sont stockés hors webroot dans `storage/private/`.
- Le dossier sera créé automatiquement au premier upload.

## Google Drive (OAuth)

1. Installer les dépendances PHP (en local ou sur le serveur) :
   - `composer install`
2. Créer un projet Google Cloud + identifiants OAuth.
3. Renseigner dans `.env` :
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI` (ex: `https://ton-domaine.tld/drive/callback`)
4. Activer le module `drive` (Admin → Modules) puis cliquer sur **Connecter Google Drive**.

## Changelog

- Consultable via navigateur : `/changelog`
- Source : `CHANGELOG.md`
