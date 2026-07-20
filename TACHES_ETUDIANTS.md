## MISE À JOUR - SUITE DES MODIFICATIONS

### V2 déjà intégrée dans le projet
- [ok] Ajout de `operateurs.est_principal` pour distinguer l'opérateur principal
- [ok] Ajout de `commissions_inter_operateurs` pour paramétrer les commissions inter-opérateurs
- [ok] Ajout des vues `vue_commissions_inter_operateurs` et `vue_montants_a_envoyer`
- [ok] Intégration de la logique de commission dans `MobileMoneyRepository`
- [ok] Intégration de l'option "Inclure les frais de retrait" côté client
- [ok] Ajout du transfert multiple
- [ok] Ajout de la colonne Commission dans l'historique des opérations



---


## VERSION 2

### Objectifs V2
- [ok] Distinguer opérateur principal vs autres opérateurs (`operateurs.est_principal`)
- [ok] Ajouter table `commissions_inter_operateurs` et vues `vue_commissions_inter_operateurs`, `vue_montants_a_envoyer`
- [ok] Interface opérateur: config des commissions inter-opérateurs, affichage montants à envoyer
- [ok] Interface client: option "Inclure les frais de retrait" et transfert multiple
- [ok] Adapter `MobileMoneyRepository` pour appliquer commission inter-opérateur, gérer option frais inclus, et `transferMultiple`

### Répartition du travail

- **Mirado (ETU3924)**
	- [ok] Mettre à jour `base.sql` : ajouter `est_principal`, créer `commissions_inter_operateurs`, ajouter vues V2
	- [ok] Modifier `app/Libraries/MobileMoneyRepository.php` : logique commission, `transferMultiple`, option frais inclus
	- [ok] Tests manuels: transfert vers autre opérateur, frais inclus, transfert multiple

- **Jerinieina (ETU4357)**
	- [ok] Mettre à jour `app/Controllers/Operator.php` et `app/Views/operator.php` : UI commissions, affichage montants à envoyer
	- [ok] Mettre à jour `app/Controllers/Dashboard.php` et `app/Views/dashboard.php` : checkbox frais inclus, formulaire transfert multiple
	- [ok] Ajouter routes et valider intégration front/back

### Critères de validation V2
- [ok] Les opérations financières restent dans des transactions (BEGIN/COMMIT/ROLLBACK)
- [ok] Commission inter-opérateur appliquée (conforme à la règle métier choisie)
- [ok] Option "Inclure les frais de retrait" fonctionne uniquement pour destinataires chez l'opérateur principal
- [ok] Transfert multiple autorisé uniquement si tous les destinataires sont chez l'opérateur principal
- [ok] Ne pas casser les fonctionnalités V1 existantes



