# 📘 CAHIER DES CHARGES – Plateforme de Gestion pour Associations

Version : Fonctionnelle (non technique)

Principe général : une base (squelette) + modules activables

## 1️⃣ OBJECTIF GLOBAL

Créer une plateforme simple, modulaire et accessible depuis ordinateur et téléphone permettant à une association de gérer :

- ses salariés et bénévoles,
- son activité quotidienne,
- sa trésorerie,
- son inventaire matériel,
- ses achats alimentaires / ateliers,
- ses documents,
- ses projets et planifications.

L’outil doit pouvoir être commercialisé à d'autres associations : chaque structure dispose de son espace, active uniquement les modules dont elle a besoin, et garde la propriété de ses données.

## 2️⃣ PHILOSOPHIE DU PRODUIT

- ✔️ Une base fonctionnelle unique

La plateforme inclut un squelette commun que toute association utilise, même sans module activé.

- ✔️ Des modules optionnels, totalement indépendants

L’administrateur de l’association choisit :

- d’activer/désactiver un module,
- de définir les utilisateurs qui ont accès à chaque module.

- ✔️ Mobile-first

Chaque module doit être utilisable sur smartphone.

- ✔️ Intégration Google (Drive & Calendar) native

La plateforme ne remplace pas les outils existants mais s’y connecte.

## 3️⃣ SQUELETTE (CORE) – FONCTIONNALITÉS DE BASE

Ces fonctionnalités sont présentes même si aucun module n'est activé :

### 🔹 3.1 Authentification & Gestion utilisateurs

- Connexion sécurisée.
- Rôles : Administrateur / Membre / Salarié / Bénévole.
- Accès conditionné selon modules activés.

### 🔹 3.2 Tableau de bord simplifié

Vue globale des informations clés :

- modules actifs,
- notifications,
- prochaines tâches,
- événements du calendrier.

### 🔹 3.3 Gestion des projets (version minimale)

- Création de projets.
- Attribution de membres.
- Suivi rapide d’avancement.

### 🔹 3.4 Connecteurs Google

- Connexion Google OAuth.
- Accès au calendrier Google (lecture).
- Accès au Drive (lecture des dossiers autorisés).

### 🔹 3.5 Stockage interne minimum

Possibilité d’héberger de petits documents internes (si Drive non activé).

## 4️⃣ MODULES ACTIVABLES

### 🟦 Module 1 – Gestion RH (Salariés & Bénévoles)

- ✔️ Fonctionnalités salariés

- Feuilles de présence quotidiennes (horaires, pauses, validations).
- Récapitulatif journalier / hebdomadaire / mensuel.
- Notes de frais :
  - création,
  - ajout de pièce jointe (photo facture),
  - validation hiérarchique.
- Suivi des temps passés par projet.

- ✔️ Fonctionnalités bénévoles

- Inscription aux événements.
- Suivi des présences.
- Historique des engagements.

- ✔️ Export

- Format tableur (CSV/Excel).
- Export mensuel pour comptabilité ou gestion paie.

### 🟦 Module 2 – Gestion des plannings & tâches

- ✔️ Intégration Google Calendar

- Synchronisation bidirectionnelle (optionnelle).
- Import automatique des événements Google dans le planning interne.

- ✔️ Gestion interne des plannings

- Planning par salarié / bénévole.
- Planning par projet.
- Assignation de tâches avec :
  - deadline,
  - priorité,
  - charge estimée.

- ✔️ Notifications

- Rappels (email / mobile).

### 🟦 Module 3 – Gestion de trésorerie & comptabilité légère

- ✔️ Trésorerie simple

- Saisie de dépenses et recettes.
- Catégorisation automatique ou manuelle.
- Catégories enrichies : code comptable (account_code) pour export cabinet.
- Ventilation par budgets (enveloppes) :
  - multi-budgets par écriture,
  - montant fixe ou pourcentage,
  - création/archivage/transfert de budgets.
- Ventilation analytique par axes/valeurs (optionnel) :
  - multi-axes,
  - montant fixe ou pourcentage.
- Ventilation par projet : (prévu) (liaison possible via budgets si module projets actif).
- Ajout de justificatifs (scan / photo).

- ✔️ Rapprochement / pointage

- Pointage des opérations (rapprochées / non rapprochées).

- ✔️ Clôtures

- Clôture d'une période (admin) : verrouillage de saisie/modification sur la période.
- Suggestions de clôture (mois/année/exercice révolu) pour aider l'administrateur.

- ✔️ Gestion avancée des documents comptables

- Rattachement automatique dans Drive (si module Drive activé).
- Classement automatique dans dossiers thématiques.

- ✔️ Import / Export

- Import documents (photo/pdf) → OCR → préremplissage.
- Export cabinet en ZIP :
  - CSV complet,
  - pré-FEC (écritures double-ligne),
  - pièces jointes,
  - manifest (mapping transactions ↔ fichiers).
- Export compatible outils comptables (mapping par catégorie).

- ✔️ Suivi

- Graphiques recettes/dépenses.
- Vue par projet / par mois / par activité.

### 🟦 Module 4 – Gestion des adhérents et bénévoles

- ✔️ Adhérents

- Base de données simple.
- Cotisations, statuts, adhésions.
- Contacts (mail, téléphone).
- Fiche adhérent ergonomique en onglets (récap, cotisations, documents, etc.).
- Documents adhérent : stockage Google Drive si activé, sinon stockage interne (avec corbeille + traçabilité des suppressions).

- ✔️ Bénévoles

- Inscription aux créneaux.
- Liaison au module RH optionnelle.

- ✔️ Connecteurs (option)

- Intégration HelloAsso (pour adhésions et dons).

### 🟦 Module 5 – Gestion documentaire (Drive intégré)

- ✔️ Integration Google Drive

- Connexion au compte Google.
- Sélection d’un dossier racine.
- Navigation Drive depuis la plateforme.
- Import automatique de documents de la plateforme → vers Drive.
- Prévisualisation des fichiers Drive.

- ✔️ Fonctionnalités internes

- Catégorisation (contrats, factures, statuts, projets…).
- Liens avec tous les autres modules.
- Corbeille par module : les suppressions déplacent les fichiers dans un dossier corbeille + traçabilité (qui/quand/pourquoi).

### 🟦 Module 6 – Inventaire matériel

- ✔️ Inventaire complet

- Fiches articles (matériel, outils, véhicules, équipements).
- Quantité, emplacement, état, valeur.
- Entrées / sorties de stock.
- Alertes de maintenance ou péremption.

- ✔️ Rattachement

- Associé à un projet ou un atelier.
- Lié à des achats (module trésorerie).

### 🟦 Module 7 – Gestion des achats alimentaires / ateliers

- ✔️ Suivi des achats

- Liste des produits réguliers.
- Saisie rapide via mobile.
- Historique des achats par atelier.

- ✔️ Stock alimentaire minimal

- Niveau de stock.
- Alertes quantités faibles.
- Utilisation dans les ateliers (décrémentation).

- ✔️ Documents associés

- Factures, notes de frais, devis → liés au module trésorerie et Drive.

## 5️⃣ MODULES OPTIONNELS FUTURS (Roadmap potentielle)

- Module communication (newsletter, SMS, email).
- Module CRM donateurs / partenaires.
- Module statistiques avancées (PowerBI/Looker Studio).
- Module gestion d’événements (inscriptions, billetterie).
- Module boutique en ligne pour associations.
- Module GMAO pour maintenance de matériel.
- Module trésorerie v2 : rapprochement bancaire + import relevé bancaire (CSV) avec matching simple (reporté).

## 6️⃣ ADMINISTRATION

- ✔️ Gestion des modules

- Liste des modules disponibles.
- Activation / désactivation.
- Paramétrage individuel.

- ✔️ Gestion des accès

- Par utilisateur, par module.
- Permissions détaillées (lecture / écriture / admin).

## 7️⃣ ACCESSIBILITÉ & SUPPORT

- Interface responsive (ordinateur, tablette, smartphone).
- Mode hors-ligne minimal (saisie stock / note de frais → synchronisation).
- Aide intégrée (FAQ, tutoriels).

---

# 🧩 AJOUTS AU CDC – Paramétrages du site, navigation, et module “Site public”

## A) Paramétrages généraux du site (cosmétique)

Objectif : permettre à un administrateur de personnaliser l’apparence de l’instance (par association / tenant).

- Logo et nom affiché (branding)
- Couleurs (primaire/secondaire, accents)
- Police (font)
- Icônes (affichage dans les menus)

## B) Navigation interne (application AssoFacile)

Objectif : rendre la navigation plus claire pour les utilisateurs, en regroupant par modules.

- Menus principaux + sous-menus
- Regroupement par modules (ex : Adhérents, Trésorerie, Admin, Infos)
- Visibilité conditionnée :
  - modules activés
  - droits utilisateur

## C) Module “Site public” (mini CMS)

Objectif : fournir à l’association un site web public directement depuis AssoFacile, capable d’évoluer.

### C.1 Pages et blocs (builder)

- Création d’une ou plusieurs pages
- Pages composées de sections et de blocs
- Blocs de tailles différentes (1, 2, 3 colonnes ; layout responsive)
- Éditeur type “builder” :
  - drag & drop
  - gestion de largeur (colonne)
  - duplication / suppression
  - aperçu (preview)
  - brouillon / publication

Exemples de blocs (MVP) :
- Texte (titre/contenu)
- Image
- Bouton / CTA
- Contact
- Réseaux sociaux
- Adhésion / Cotisation (si module actif)

### C.2 Menu public

- Menu indépendant du menu interne
- Items : pages, ancres, liens externes
- Ordre et visibilité configurables

### C.3 Espaces connectés

- Possibilité d’avoir des pages/blocs accessibles uniquement :
  - aux utilisateurs connectés
  - selon un rôle (adhérent / bénévole / salarié / administrateur)

### C.4 Dépendances aux modules AssoFacile

Certaines fonctions du site public doivent s’afficher/être disponibles uniquement si le module correspondant est actif.

Exemples :
- Bloc “Adhérer” (cotisations) uniquement si le module adhérents/cotisations est actif
- Accès documents uniquement si module documentaire actif

## D) Domaines personnalisés (pour le site public)

Objectif : permettre au client d’utiliser son propre domaine.

- 1 seul domaine personnalisé par association
- Sans `www.` (le client gère éventuellement le `www` via sa zone DNS)
- Support :
  - domaine racine (ex : `client.fr`)
  - sous-domaine (ex : `asso.client.fr`)

Vérification de propriété recommandée :
- DNS TXT : `assofacile-verification=<token>`

