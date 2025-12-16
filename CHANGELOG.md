# Changelog

## 0.5.0 - 2025-12-16
- Mise à jour DB forcée : page /admin/update + blocage de l'application tant que les migrations ne sont pas appliquées
- Trésorerie : filtres (période, recherche, type, catégorie) + totaux (dépenses/recettes/solde)
- Trésorerie : actions dupliquer une transaction + pointage (toggle "pointée")
- Licensing : génération automatique de clé + envoi optionnel par email
- Changelog : accès réservé aux utilisateurs connectés

## 0.4.0 - 2025-12-12
- Google Drive : OAuth (connexion/déconnexion) par association
- Justificatifs : option de stockage sur Google Drive (si module drive activé)

## 0.3.0 - 2025-12-12
- Trésorerie v0.3 : justificatifs multi-fichiers (stockage privé)
- Préparation du stockage Google Drive (module 'drive' activable, OAuth à venir)

## 0.2.0 - 2025-12-12
- Trésorerie v0.2 : catégories (création + association aux transactions)
- Export CSV des transactions

## 0.1.0 - 2025-12-12
- Initialisation du squelette AssoFacile (multi-association via tenant_id)
- Authentification basique (login/logout)
- Dashboard
- Module Trésorerie (liste + création transaction)
- Page changelog consultable via navigateur (/changelog)
