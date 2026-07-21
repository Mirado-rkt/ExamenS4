STRUCTURE MobileMoneyRepository

    PROPRIÉTÉ db : ConnexionBaseDeDonnees

    // ==========================================
    // CONSTRUCTEUR ET INITIALISATION
    // ==========================================
    CONSTRUCTEUR(cheminDatabase Optionnel)
        SI cheminDatabase EST NUL ALORS
            cheminDatabase = CHEMIN_PAR_DEFAUT + "database.db"
        FIN SI

        SI LeDossierDe(cheminDatabase) N'EXISTE PAS ALORS
            CREER_DOSSIER(LeDossierDe(cheminDatabase))
        FIN SI

        db = OUVRIR_SQLITE(cheminDatabase, LECTURE_ECRITURE | CREATION)
        db.DEFINIR_TIMEOUT(5000 millisecondes)
        db.EXECUTER("PRAGMA foreign_keys = ON")

        APPELER initializeSchema()
    FIN CONSTRUCTEUR

    // ==========================================
    // UTILITAIRES ET CLIENTS
    // ==========================================
    FONCTION normalizePhone(telephone: Chaine) : Chaine
        RETOURNER SupprimerTousLesCaracteresNonNumeriques(telephone)
    FIN FONCTION

    FONCTION createOrGetClientByPhone(telephoneEntrant: Chaine) : StructureClient
        telephone = APPELER normalizePhone(telephoneEntrant)

        SI telephone EST VIDE OU Longueur(telephone) < 6 ALORS
            LEVER_ERREUR "Numéro de téléphone invalide."
        FIN SI

        clientExistant = APPELER findClientByPhone(telephone)
        SI clientExistant N'EST PAS NUL ALORS
            RETOURNER clientExistant
        FIN SI

        operateur = APPELER findOperatorByPhone(telephone)
        SI operateur EST NUL ALORS
            LEVER_ERREUR "Aucun opérateur ne correspond à ce préfixe."
        FIN SI

        EXECUTER_SQL("INSERT INTO clients (operateur_id, telephone, solde) VALUES (?, ?, 0)", [operateur.id, telephone])

        clientCree = APPELER findClientByPhone(telephone)
        SI clientCree EST NUL ALORS
            LEVER_ERREUR "Impossible de créer le compte client."
        FIN SI

        RETOURNER clientCree
    FIN FONCTION

    // ==========================================
    // OPERATIONS FINANCIERES
    // ==========================================

    // 1. DEPOT
    FONCTION deposit(clientId: Entier, montant: Reel) : StructureResultat
        SI montant <= 0 ALORS
            LEVER_ERREUR "Le montant du dépôt doit être supérieur à zéro."
        FIN SI

        client = APPELER requireClient(clientId)
        typeId = APPELER requireOperationTypeId("DEPOT")
        soldeAvant = client.solde
        soldeApres = soldeAvant + montant

        DEMARRER_TRANSACTION_IMMÉDIATE()
        ESSAYER
            EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApres, clientId])
            EXECUTER_SQL("INSERT INTO operations (type, client, montant, frais, solde_avant, solde_apres) VALUES (?, ?, ?, 0, ?, ?)",
                         [typeId, clientId, montant, soldeAvant, soldeApres])
            VALIDER_TRANSACTION()
        ATTRAPER Erreur
            ANNULER_TRANSACTION() // ROLLBACK
            RE-LANCER Erreur
        FIN ESSAYER

        RETOURNER { solde_avant: soldeAvant, solde_apres: soldeApres, frais: 0.0 }
    FIN FONCTION

    // 2. RETRAIT
    FONCTION withdraw(clientId: Entier, montant: Reel) : StructureResultat
        SI montant <= 0 ALORS
            LEVER_ERREUR "Le montant du retrait doit être supérieur à zéro."
        FIN SI

        client = APPELER requireClient(clientId)
        typeId = APPELER requireOperationTypeId("RETRAIT")
        frais = APPELER calculateFee(client.operateur_id, "RETRAIT", montant)
        debitTotal = montant + frais
        soldeAvant = client.solde

        SI soldeAvant < debitTotal ALORS
            LEVER_ERREUR "Solde insuffisant pour ce retrait."
        FIN SI

        soldeApres = soldeAvant - debitTotal

        DEMARRER_TRANSACTION_IMMÉDIATE()
        ESSAYER
            EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApres, clientId])
            EXECUTER_SQL("INSERT INTO operations (type, client, montant, frais, solde_avant, solde_apres) VALUES (?, ?, ?, ?, ?, ?)",
                         [typeId, clientId, montant, frais, soldeAvant, soldeApres])
            VALIDER_TRANSACTION()
        ATTRAPER Erreur
            ANNULER_TRANSACTION()
            RE-LANCER Erreur
        FIN ESSAYER

        RETOURNER { solde_avant: soldeAvant, solde_apres: soldeApres, frais: frais }
    FIN FONCTION

    // 3. TRANSFERT SIMPLE
    FONCTION transfer(clientId: Entier, telephoneDestinataire: Chaine, montant: Reel, inclureFraisRetrait: Booleen) : StructureResultat
        SI montant <= 0 ALORS
            LEVER_ERREUR "Le montant du transfert doit être supérieur à zéro."
        FIN SI

        expediteur = APPELER requireClient(clientId)
        destinataire = APPELER createOrGetClientByPhone(telephoneDestinataire)

        SI destinataire.id == clientId ALORS
            LEVER_ERREUR "Un transfert vers le même compte est interdit."
        FIN SI

        typeId = APPELER requireOperationTypeId("TRANSFERT")
        fraisTransfert = APPELER calculateFee(expediteur.operateur_id, "TRANSFERT", montant)

        // Calcul des commissions inter-opérateurs
        expediteurEstPrincipal = VÉRIFIER_SI_OPERATEUR_EST_PRINCIPAL(expediteur.operateur_id)
        destinataireEstPrincipal = VÉRIFIER_SI_OPERATEUR_EST_PRINCIPAL(destinataire.operateur_id)

        montantCommission = 0.0
        SI expediteurEstPrincipal ET NON destinataireEstPrincipal ALORS
            taux = OBTENIR_TAUX_COMMISSION(destinataire.operateur_id) // pourcentage + pourcentage_supplementaire
            montantCommission = montant * (taux / 100.0)
        FIN SI

        // Gestion de l'option frais de retrait anticipés
        fraisRetraitAnticipes = 0.0
        SI inclureFraisRetrait ALORS
            SI NON destinataireEstPrincipal ALORS
                LEVER_ERREUR "Option disponible uniquement vers l'opérateur principal."
            FIN SI
            fraisRetraitAnticipes = APPELER calculateFee(destinataire.operateur_id, "RETRAIT", montant)
        FIN SI

        debitTotalExpediteur = montant + fraisTransfert + fraisRetraitAnticipes
        soldeAvantExpediteur = expediteur.solde

        SI soldeAvantExpediteur < debitTotalExpediteur ALORS
            LEVER_ERREUR "Solde insuffisant pour ce transfert."
        FIN SI

        soldeApresExpediteur = soldeAvantExpediteur - debitTotalExpediteur
        soldeAvantDestinataire = destinataire.solde
        montantNetRecu = montant - montantCommission - fraisRetraitAnticipes
        soldeApresDestinataire = soldeAvantDestinataire + montantNetRecu

        DEMARRER_TRANSACTION_IMMÉDIATE()
        ESSAYER
            EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApresExpediteur, clientId])
            EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApresDestinataire, destinataire.id])
            
            EXECUTER_SQL("INSERT INTO operations (type, client, destinataire, montant, frais, solde_avant, solde_apres) VALUES (?, ?, ?, ?, ?, ?, ?)",
                         [typeId, clientId, destinataire.id, montant, (fraisTransfert + fraisRetraitAnticipes), soldeAvantExpediteur, soldeApresExpediteur])
            
            VALIDER_TRANSACTION()
        ATTRAPER Erreur
            ANNULER_TRANSACTION()
            RE-LANCER Erreur
        FIN ESSAYER

        RETOURNER {
            solde_avant: soldeAvantExpediteur,
            solde_apres: soldeApresExpediteur,
            frais: fraisTransfert,
            commission: montantCommission,
            destinataire_telephone: destinataire.telephone,
            destinataire_net: montantNetRecu
        }
    FIN FONCTION

    // 4. TRANSFERT MULTIPLE
    FONCTION transferMultiple(clientId: Entier, listeTelephones: Liste, montantTotal: Reel) : StructureResultat
        SI listeTelephones EST VIDE ALORS
            LEVER_ERREUR "Aucun destinataire fourni."
        FIN SI

        expediteur = APPELER requireClient(clientId)
        nombreDestinataires = Longueur(listeTelephones)
        
        partEgale = Div_Entiere(montantTotal, nombreDestinataires)
        reste = montantTotal - (partEgale * nombreDestinataires)

        listeDestinataires = []
        POUR CHAQUE telephone DANS listeTelephones FAIRE
            r = APPELER createOrGetClientByPhone(telephone)
            SI NON VÉRIFIER_SI_OPERATEUR_EST_PRINCIPAL(r.operateur_id) ALORS
                LEVER_ERREUR "Transfert multiple autorisé uniquement vers l'opérateur principal."
            FIN SI
            Ajouter r À listeDestinataires
        FIN POUR

        DEMARRER_TRANSACTION_IMMÉDIATE()
        ESSAYER
            soldeAvantExpediteur = expediteur.solde
            debitTotalAttendu = 0.0
            repartitionAmounts = []

            POUR i DE 0 À (nombreDestinataires - 1) FAIRE
                montantIndividuel = partEgale + (SI i == 0 ALORS reste SINON 0)
                fraisIndividuels = APPELER calculateFee(expediteur.operateur_id, "TRANSFERT", montantIndividuel)
                debitTotalAttendu = debitTotalAttendu + montantIndividuel + fraisIndividuels
                Ajouter {montant: montantIndividuel, frais: fraisIndividuels} À repartitionAmounts
            FIN POUR

            SI soldeAvantExpediteur < debitTotalAttendu ALORS
                LEVER_ERREUR "Solde insuffisant pour le transfert multiple."
            FIN SI

            soldeApresExpediteur = soldeAvantExpediteur - debitTotalAttendu
            EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApresExpediteur, clientId])

            cumulDebit = 0.0
            POUR CHAQUE index, destinataire DANS listeDestinataires FAIRE
                amt = repartitionAmounts[index].montant
                fee = repartitionAmounts[index].frais

                soldeApresDest = destinataire.solde + amt
                EXECUTER_SQL("UPDATE clients SET solde = ? WHERE id = ?", [soldeApresDest, destinataire.id])

                cumulDebit = cumulDebit + amt + fee
                soldeApresOperationTemp = soldeAvantExpediteur - cumulDebit

                EXECUTER_SQL("INSERT INTO operations ... VALUES ...", [..., amt, fee, soldeAvantExpediteur, soldeApresOperationTemp])
            FIN POUR

            VALIDER_TRANSACTION()
        ATTRAPER Erreur
            ANNULER_TRANSACTION()
            RE-LANCER Erreur
        FIN ESSAYER

        RETOURNER { solde_avant: soldeAvantExpediteur, solde_apres: soldeApresExpediteur }
    FIN FONCTION

    // ==========================================
    // METHODES PRIVEES DE SUPPORT
    // ==========================================
    FONCTION PRIVE calculateFee(operateurId: Entier, codeOperation: Chaine, montant: Reel) : Reel
        ligne = REQUETE_SQL("SELECT frais FROM baremes_frais WHERE operateur_id = ? AND code = ? AND ? BETWEEN min AND max", [operateurId, codeOperation, montant])
        SI ligne N'EST PAS NULLE ALORS
            RETOURNER ligne.frais
        SINON
            RETOURNER 0.0
        FIN SI
    FIN FONCTION

    FONCTION PRIVE requireClient(clientId: Entier) : StructureClient
        client = APPELER getClientSummary(clientId)
        SI client EST NUL ALORS
            LEVER_ERREUR "Compte client introuvable."
        FIN SI
        RETOURNER client
    FIN FONCTION

FIN STRUCTUREE..............   