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
   - `database/seed.sql`
4. Servir `public/` (Apache/Nginx).
5. Se connecter :
   - email: `admin@demo.local`
   - mdp: `admin123`

## cPanel

- DocumentRoot à pointer sur `public/`.
- Activer `mod_rewrite` (le `.htaccess` est fourni).

## Changelog

- Consultable via navigateur : `/changelog`
- Source : `CHANGELOG.md`
