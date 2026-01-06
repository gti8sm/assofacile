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

## Stripe (mise en place plus tard)

### Variables d'environnement

Dans `licensing/.env` :

- `STRIPE_SECRET_KEY` : clé secrète Stripe (format `sk_...`)
- `STRIPE_WEBHOOK_SECRET` : secret de signature du webhook (format `whsec_...`)
- `STRIPE_PRICE_PREMIUM_ANNUAL` : Price ID Stripe pour Premium annual (format `price_...`)
- `STRIPE_PRICE_PREMIUM_LIFETIME` : Price ID Stripe pour Premium lifetime (format `price_...`)

### Webhook

- URL à configurer dans Stripe :
  - `POST https://licences.assofacile.net/api/v1/stripe/webhook`

Événements minimum à activer (pour démarrer) :

- `checkout.session.completed`
- `customer.subscription.updated`
- `customer.subscription.deleted`

### Notes

- Le webhook est idempotent (les événements sont stockés en DB) : si Stripe renvoie le même event plusieurs fois, il ne sera traité qu'une fois.
- La logique actuelle met à jour la licence quand `checkout.session.completed` est reçu et que les metadata contiennent `license_id` ou `license_key`.
- La date d'expiration (annual) est synchronisée via `customer.subscription.updated` (champ `current_period_end`).
