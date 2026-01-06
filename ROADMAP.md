# Roadmap

## En cours
- Trésorerie: export cabinet (ZIP CSV + justificatifs) + pré-FEC + manifest + mapping comptes par catégorie
- Trésorerie: budgets (création/archivage/transfert) + ventilation multi-budgets (montant/%)
- Projets: finaliser ergonomie (badges/compteurs, modales, cards) + docs/liens
- Projets: documents (Drive/local) + corbeille/audit (tests + UX)
- Docs: mise à jour CDC + ROADMAP + CHANGELOG

## À venir (proche)
- Drive: audit OAuth (token/refresh) + endpoints download/upload + statut “connecté” + gestion erreurs/UX
- Multi-tenant: tenant en chemin `/t/{tenant}/...` partout + anti cross-tenant (y compris OAuth state)
- Helper URLs tenantées + migration liens navbar/actions principales vers `/t/{slug}/...`
- Onboarding/licensing: création d'association (nom, SIRET, RNA, adresse...), provisioning tenant + admin user
- HelloAsso: finaliser tests sandbox + ajuster parsing webhook (statuts réels, payload)
- Paramétrages généraux: menu configurable (ordre/masquage) + icônes
- Paramétrages généraux: thème (logo, couleurs, police)
- Navigation: menus principaux + sous-menus (UI)
- Module "Site public": pages par blocs (1/2/3 colonnes), menu public, zones connectées (adhérents/bénévoles)
- Adhérents: fiche adhérent en onglets + documents (Drive si actif sinon local)
- Tiers: fiche tiers en onglets + documents (Drive si actif sinon local)

## À venir
- HelloAsso: mode OAuth (mire d'autorisation) (Mode B)
- Trésorerie: analytique (axes) (optionnel)
- Trésorerie: projets (liaison budgets ↔ projets si module projets actif)

## Terminé
- Mode famille (foyers, enfants)
- UI cotisations (catalogue + souscriptions)
