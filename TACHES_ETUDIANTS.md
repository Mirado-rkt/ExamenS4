##  ÉTUDIANT 1: **Mirado (ETU3924)**

###  Page: `app/Controllers/Login.php`
**Fonctions à faire:**
- [ok] `index()` - Afficher la page de connexion
- [ok] `authenticate()` - Authentifier l'utilisateur par numéro de téléphone
- [ok] `logout()` - Déconnecter l'utilisateur
- [ok] Validation du format du numéro de téléphone

###  Page: `app/Views/login.php`
**Fonctions à faire:**
- [ok] Créer le formulaire de connexion avec champ numéro de téléphone
- [ok] Ajouter les messages d'erreur/succès
- [ok] Intégrer Bootstrap pour le design
- [ok] Ajouter la validation côté client (JavaScript)

###  Page: `app/Libraries/MobileMoneyRepository.php`
**Fonctions à faire:**
- [ok] `createOrGetClientByPhone($phone)` - Créer ou récupérer un client par téléphone
- [ok] `getClientHistory($clientId)` - Récupérer l'historique des transactions d'un client
- [ok] `getFeeBands($operatorId)` - Récupérer les barèmes de frais d'un opérateur

###  Page: `base.sql`
**Fonctions à faire:**
- [ok] Créer la table `clients` (id, telephone, solde, operateur_id, created_at)
- [ok] Créer la table `operateurs` (id, code, nom, commission)
- [ok] Créer la table `types_operations` (id, nom, description)
- [ok] Ajouter les données initiales pour les opérateurs

###  Page: `system/Model.php`
**Fonctions à faire:**
- [ok] Comprendre la structure du modèle CodeIgniter
- [ok] Documenter les méthodes principales
- [ok] Lister les méthodes disponibles pour les requêtes

---

## ÉTUDIANT 2: **Jerinieina (ETU4357)**

###  Page: `app/Controllers/Dashboard.php`
**Fonctions à faire:**
- [ok] `index()` - Afficher le tableau de bord du client
- [ok] `deposit()` - Gérer les dépôts d'argent
- [ok] `withdraw()` - Gérer les retraits d'argent
- [ok] `transfer()` - Gérer les transferts d'argent
- [ok] `handleClientOperation()` - Traiter les opérations client

###  Page: `app/Views/dashboard.php`
**Fonctions à faire:**
-  Afficher le solde du client
-  Afficher l'historique des transactions
-  Créer les formulaires pour dépôt/retrait/transfert
-  Intégrer Bootstrap pour le design responsive
-  Afficher les barèmes de frais par tranche

###  Page: `app/Controllers/Operator.php`
**Fonctions à faire:**
- [ok] `index()` - Afficher l'espace opérateur
- [ok] `viewOperations()` - Voir les opérations du jour
- [ok] `calculateCommissions()` - Calculer les commissions des opérateurs
- [ok] `generateReport()` - Générer un rapport des transactions

###  Page: `app/Views/operator.php`
**Fonctions à faire:**
- [ ] Créer le tableau de bord opérateur
- [ok] Afficher la liste des opérations
- [ok] Afficher les commissions gagnées
- [ok] Ajouter un formulaire de filtre par date
- [ok] Intégrer Bootstrap pour le design

###  Page: `base.sql` (Suite)
**Fonctions à faire:**
- [ok] Créer la table `operations` (id, client_id, operateur_id, type, montant, frais, created_at)
- [ok] Créer la table `baremes` (id, operateur_id, tranche_min, tranche_max, pourcentage_frais)
- [ok] Créer la table `prefixes_operateurs` (id, operateur_id, prefix, pays)
- [ok] Ajouter les indexes de performance

---


## CRITÈRES DE VALIDATION

### Étudiant 1 (Mirado):
- [ok] Connexion automatique fonctionnelle par numéro de téléphone
- [ok] Base de données correctement structurée
- [ok] Validation des données d'entrée
- [ok] Interface Bootstrap compatible mobile

### Étudiant 2 (Jerinieina):
- [ok] Opérations complètes (dépôt, retrait, transfert)
- [ok] Calcul des frais par tranche
- [ok] Interface opérateur fonctionnelle
- [ok] Historique et rapports disponibles

---


