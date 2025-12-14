# AssoFacile Licensing (serveur central)

Mini application PHP + MySQL (compatible cPanel) pour gérer les licences et exposer une API de validation.

## Installation

1. Copier `licensing/.env.example` vers `licensing/.env` (ou lancer directement `/install`).
2. Pointer le DocumentRoot vers `licensing/public/`.
3. Accéder à `/install` pour :
   - configurer la DB
   - exécuter les migrations
   - créer l'admin
   - générer la paire de clés Ed25519
4. Se connecter sur `/login` puis gérer les licences sur `/licenses`.

## API

- `POST /api/v1/licenses/validate`
  - body JSON: `{ "license_key": "...", "tenant_id": 1, "app_url": "...", "app_version": "..." }`
  - réponse JSON: `status, plan_type, valid_until, signed_token, token_valid_until, public_key_b64`

## Côté AssoFacile

- Copier `public_key_b64` dans `.env` : `LICENSE_PUBLIC_KEY=...`
- Configurer `LICENSE_SERVER_URL=https://licences.assofacile.fr`
