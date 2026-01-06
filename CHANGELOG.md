# Changelog

## Unreleased
- Cotisations: modes de paiement (HelloAsso, espèces, chèque, virement) + gestion des statuts (en attente / payée)
- Cotisations: intégration HelloAsso (checkout + webhook) + création d'écriture en Trésorerie (si activé)
- Cotisations: action admin pour supprimer une cotisation de test (uniquement si non payée et sans écriture Trésorerie)
- Admin: page /admin/update affiche l'historique des migrations + option de backup MySQL (mysqldump)
- Admin: paramètres généraux du site (/admin/site-settings) pour configurer le menu (ordre/masquage) + icônes
- Site: page /roadmap
- Dashboard: KPI + activité récente (adhérents, trésorerie, cotisations) + raccourcis d'actions (selon modules/permissions)
- Cotisations: génération d'une carte d'adhérent imprimable pour une adhésion payée
- Trésorerie v2: rapprochement/pointage (écran "À rapprocher", filtres "rapprochées/non rapprochées")
- Trésorerie v2: champs de paiement (moyen, tiers, référence) + édition d'une transaction (verrouillage partiel si rapprochée)
- Trésorerie: clôtures (admin) pour verrouiller une période
- Trésorerie: proposition de clôture automatique (mois/année/exercice révolu) sur la page Trésorerie (admin)
- Trésorerie: paramètre “début d'exercice” (mois) par association
- Trésorerie: catégories: ajout du code comptable (account_code) + édition
- Trésorerie: export cabinet en ZIP (CSV complet + pré-FEC + manifest + pièces jointes)
- Trésorerie: budgets (création/archivage/transfert) + ventilation multi-budgets (montant/%) dès la création et l'édition
- Multi-tenant: corrections de liens/redirects tenant-aware sur l'admin et la trésorerie
- Site public: builder de sections/templates + set minimal de blocs (texte, image, bouton/CTA, contact, réseaux sociaux)
- Projets: fiche projet ergonomique en onglets (récap/actions/tâches/budget/docs/liens) + recherche rapide
- Projets: documents avec upload (Drive si actif, sinon stockage local) + téléchargement
- Projets: suppression de documents en corbeille (déplacement fichier) + traçabilité (qui/quand/pourquoi)

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
