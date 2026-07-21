<?php

namespace App\Libraries;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use Throwable;

class MobileMoneyRepository
{
    private SQLite3 $db;

    public function __construct(?string $databasePath = null)
    {
        $databasePath ??= WRITEPATH . 'database.db';

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->db = new SQLite3($databasePath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db->busyTimeout(5000);
        $this->db->exec('PRAGMA foreign_keys = ON;');

        if (!$this->tableExists('promotions')) {
            $this->db->exec('BEGIN IMEDIATE TRANSACTION');
            try {
                $this->exec(
                    'CREATE TABLE promotions (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            type_operation_id INTEGER NOT NULL UNIQUE,
                            pourcenttage_reduction DECIMAL(5,2) NOT NULL DEFAULT 0,
                            actif INTEGER NOT NULL DEFAULT 1,
                            FOREIGN KEY (type_operation_id) REFERENCES type_operation(id)
                        )'
                );

                $types = $this ->fetchAll('SELECT FROM types_operation');
                foreach ($types as $type){
                    $this->execute(
                        'INSERT OR IGNORE INTO promotions (type_operation_id)'
                    )
                }
        }
    }

    $clientsCols = $this->fetchAll<("PRAGMA table_info('clients')");
    $hasEpargne = false;
    $hasPourcentageEpagrne = false ;
    foreach ($clientsCols as $col){
        if (issset($col['name']) && $col['name']==='epargne'){
            $hasEpargne=true;
        }
    }

    if (!$hasEpargne || !$hasPourcentageEpagrne){
        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
        try{
            if(!$hasEpargne && !$this->db->exec('ALTER TABLE clients ADD COLUMN epargne DECIMAL(15,2) NOT NULL DEFAULT 0')){
                throw new RuntimeException('impossible d ajouter epargne');
            }
            if(!$hasPourcentageEpagrne && !this->db->exec('ALTER TABLE clients ADD COLUMN pourcentage_epargne DECIMAL(5,2) NOT NULL DEFAULT 0')){
                throw new RuntimeException('impossible d ajouter pourcentage');
            }
            $this->db->exec('COMMIT');
        } catch (/Throwable $e){
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    $opCols = $this->fetchAll("PRAGMA table_info")


        $this->initializeSchema();
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }

    public function createOrGetClientByPhone(string $phone): array
    {
        $telephone = $this->normalizePhone($phone);

        if ($telephone === '' || strlen($telephone) < 6) {
            throw new RuntimeException('Numéro de téléphone invalide.');
        }

        $existing = $this->findClientByPhone($telephone);
        if ($existing !== null) {
            return $existing;
        }

        $operator = $this->findOperatorByPhone($telephone);
        if ($operator === null) {
            throw new RuntimeException('Aucun opérateur ne correspond à ce préfixe.');
        }

        $this->execute(
            'INSERT INTO clients (operateur_id, telephone, solde) VALUES (:operateur_id, :telephone, 0)',
            [
                'operateur_id' => (int) $operator['operateur_id'],
                'telephone' => $telephone,
            ]
        );

        return $this->findClientByPhone($telephone) ?? throw new RuntimeException('Impossible de créer le compte client.');
    }

    public function getClientSummary(int $clientId): ?array
    {
        return $this->fetchOne(
            'SELECT c.id, c.operateur_id, c.telephone, c.solde, c.date_creation, op.code AS operateur_code, op.nom AS operateur_nom
             FROM clients c
             JOIN operateurs op ON op.id = c.operateur_id
             WHERE c.id = :id',
            ['id' => $clientId]
        );
    }

    public function getClientHistory(int $clientId): array
    {
        return $this->fetchAll(
            'SELECT o.id, o.montant, o.frais, o.solde_avant, o.solde_apres, o.date_operation,
                    t.code AS type_code, t.libelle AS type_libelle,
                    c1.telephone AS emetteur,
                    c2.telephone AS destinataire,
                    CASE
                        WHEN o.client_id = :client_id THEN "emission"
                        ELSE "reception"
                    END AS sens,
                    CASE
                        WHEN o.client_id = :client_id THEN o.solde_apres
                        ELSE c3.solde
                    END AS solde_compte,
                    -- Commission inter-opérateurs : applicable quand c1 (expéditeur) est chez le principal
                    -- et que le destinataire appartient à un opérateur tiers
                    COALESCE(
                      CASE
                        WHEN t.code = "TRANSFERT" AND op_src.est_principal = 1 AND op_dest.est_principal = 0
                        THEN o.montant * COALESCE(ci.pourcentage, 0) / 100.0
                        ELSE 0
                      END, 0
                    ) AS commission
             FROM operations o
             JOIN types_operation t ON t.id = o.type_operation_id
             JOIN clients c1 ON c1.id = o.client_id
             LEFT JOIN clients c2 ON c2.id = o.client_destinataire_id
             LEFT JOIN clients c3 ON c3.id = :client_id
             LEFT JOIN operateurs op_src ON op_src.id = c1.operateur_id
             LEFT JOIN operateurs op_dest ON op_dest.id = c2.operateur_id
             LEFT JOIN commissions_inter_operateurs ci ON ci.operateur_id = op_dest.id
             WHERE o.client_id = :client_id OR o.client_destinataire_id = :client_id
             ORDER BY o.date_operation DESC, o.id DESC',
            ['client_id' => $clientId]
        );
    }

    public function deposit(int $clientId, float $amount): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Le montant du dépôt doit être supérieur à zéro.');
        }

        $client = $this->requireClient($clientId);
        $typeId = $this->requireOperationTypeId('DEPOT');
        $balanceBefore = (float) $client['solde'];
        $balanceAfter = $balanceBefore + $amount;

        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                'solde' => $balanceAfter,
                'id' => $clientId,
            ]);

            $this->execute(
                'INSERT INTO operations (type_operation_id, client_id, montant, frais, solde_avant, solde_apres)
                 VALUES (:type_operation_id, :client_id, :montant, 0, :solde_avant, :solde_apres)',
                [
                    'type_operation_id' => $typeId,
                    'client_id' => $clientId,
                    'montant' => $amount,
                    'solde_avant' => $balanceBefore,
                    'solde_apres' => $balanceAfter,
                ]
            );

            $this->db->exec('COMMIT');
        } catch (\Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }

        return [
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'fee' => 0.0,
        ];
    }

    public function withdraw(int $clientId, float $amount): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Le montant du retrait doit être supérieur à zéro.');
        }

        $client = $this->requireClient($clientId);
        $typeId = $this->requireOperationTypeId('RETRAIT');
        $fee = $this->calculateFee((int) $client['operateur_id'], 'RETRAIT', $amount);
        $totalDebit = $amount + $fee;
        $balanceBefore = (float) $client['solde'];

        if ($balanceBefore < $totalDebit) {
            throw new RuntimeException('Solde insuffisant pour ce retrait.');
        }

        $balanceAfter = $balanceBefore - $totalDebit;

        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                'solde' => $balanceAfter,
                'id' => $clientId,
            ]);

            $this->execute(
                'INSERT INTO operations (type_operation_id, client_id, montant, frais, solde_avant, solde_apres)
                 VALUES (:type_operation_id, :client_id, :montant, :frais, :solde_avant, :solde_apres)',
                [
                    'type_operation_id' => $typeId,
                    'client_id' => $clientId,
                    'montant' => $amount,
                    'frais' => $fee,
                    'solde_avant' => $balanceBefore,
                    'solde_apres' => $balanceAfter,
                ]
            );

            $this->db->exec('COMMIT');
        } catch (\Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }

        return [
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'fee' => $fee,
        ];
    }

    public function transfer(int $clientId, string $recipientPhone, float $amount, bool $includeWithdrawalFees = false): array
    {
        if ($amount <= 0) {
            throw new RuntimeException('Le montant du transfert doit être supérieur à zéro.');
        }

        $sender = $this->requireClient($clientId);
        $recipient = $this->createOrGetClientByPhone($recipientPhone);

        if ((int) $recipient['id'] === $clientId) {
            throw new RuntimeException('Un transfert vers le même compte est interdit.');
        }

        $typeId = $this->requireOperationTypeId('TRANSFERT');
        $fee = $this->calculateFee((int) $sender['operateur_id'], 'TRANSFERT', $amount);

        // Determine commission if sender is principal and recipient belongs to other operator
        $senderIsPrincipal = (int) ($this->fetchOne('SELECT est_principal FROM operateurs WHERE id = :id', ['id' => (int) $sender['operateur_id']])['est_principal']) === 1;
        $recipientOperateurId = (int) $recipient['operateur_id'];
        $recipientIsPrincipal = (int) ($this->fetchOne('SELECT est_principal FROM operateurs WHERE id = :id', ['id' => $recipientOperateurId])['est_principal']) === 1;

        $commissionPercentage = 0.0;
        $commissionSupplement = 0.0;
        $commissionAmount = 0.0;
        if ($senderIsPrincipal && !$recipientIsPrincipal) {
            // Read both base commission and optional supplementary percent
            $row = $this->fetchOne('SELECT pourcentage, pourcentage_supplementaire FROM commissions_inter_operateurs WHERE operateur_id = :operateur_id', ['operateur_id' => $recipientOperateurId]);
            if ($row !== null) {
                $commissionPercentage = (float) ($row['pourcentage'] ?? 0.0);
                $commissionSupplement = (float) ($row['pourcentage_supplementaire'] ?? 0.0);
            }

            $totalPercent = $commissionPercentage + $commissionSupplement;
            $commissionAmount = $amount * $totalPercent / 100.0;
        }

        // If includeWithdrawalFees requested, only allowed when recipient is at principal
        $withdrawalFee = 0.0;
        if ($includeWithdrawalFees) {
            if (!$recipientIsPrincipal) {
                throw new RuntimeException('L\'option "Inclure les frais de retrait" n\'est pas disponible pour un destinataire d\'un autre opérateur.');
            }
            $withdrawalFee = $this->calculateFee($recipientOperateurId, 'RETRAIT', $amount);
        }

        // Total debit from sender: amount + transfer fee + optional anticipated withdrawal fee
        $totalDebit = $amount + $fee + $withdrawalFee;
        $balanceBefore = (float) $sender['solde'];

        if ($balanceBefore < $totalDebit) {
            throw new RuntimeException('Solde insuffisant pour ce transfert.');
        }

        $senderBalanceAfter = $balanceBefore - $totalDebit;
        $recipientBalanceBefore = (float) $recipient['solde'];
        // Recipient receives the amount minus commission (if any) and minus anticipated withdrawal fee (if included)
        $recipientNet = $amount - $commissionAmount - $withdrawalFee;
        $recipientBalanceAfter = $recipientBalanceBefore + $recipientNet;

        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                'solde' => $senderBalanceAfter,
                'id' => $clientId,
            ]);

            $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                'solde' => $recipientBalanceAfter,
                'id' => (int) $recipient['id'],
            ]);

            $this->execute(
                'INSERT INTO operations (type_operation_id, client_id, client_destinataire_id, montant, frais, solde_avant, solde_apres)
                 VALUES (:type_operation_id, :client_id, :client_destinataire_id, :montant, :frais, :solde_avant, :solde_apres)',
                [
                    'type_operation_id' => $typeId,
                    'client_id' => $clientId,
                    'client_destinataire_id' => (int) $recipient['id'],
                    'montant' => $amount,
                    'frais' => $fee + $withdrawalFee,
                    'solde_avant' => $balanceBefore,
                    'solde_apres' => $senderBalanceAfter,
                ]
            );

            $this->db->exec('COMMIT');
        } catch (\Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }

        return [
            'balance_before' => $balanceBefore,
            'balance_after' => $senderBalanceAfter,
            'fee' => $fee,
            'commission' => $commissionAmount,
            'recipient_phone' => $recipient['telephone'],
            'recipient_net' => $recipientNet,
        ];
    }

    public function transferMultiple(int $clientId, array $phones, float $totalAmount): array
    {
        if (empty($phones)) {
            throw new RuntimeException('Aucun destinataire fourni pour le transfert multiple.');
        }

        $sender = $this->requireClient($clientId);
        $senderIsPrincipal = (int) ($this->fetchOne('SELECT est_principal FROM operateurs WHERE id = :id', ['id' => (int) $sender['operateur_id']])['est_principal']) === 1;

        $count = count($phones);
        $share = floor($totalAmount / $count);
        $remainder = $totalAmount - ($share * $count);

        $results = [];

        // All recipients must exist/be created and be at principal operator
        $recipients = [];
        foreach ($phones as $phone) {
            $r = $this->createOrGetClientByPhone($phone);
            $recipients[] = $r;
            $op = $this->fetchOne('SELECT est_principal FROM operateurs WHERE id = :id', ['id' => (int) $r['operateur_id']]);
            if ((int) $op['est_principal'] !== 1) {
                throw new RuntimeException('Le transfert multiple n\'est autorisé que vers des destinataires chez l\'opérateur principal.');
            }
        }

        // Start transaction and perform individual transfers without the withdrawal-included option
        $this->db->exec('BEGIN IMMEDIATE TRANSACTION');

        try {
            // Check total available balance including fees per individual
            $balanceBefore = (float) $sender['solde'];
            $expectedTotalDebit = 0.0;
            $individualAmounts = [];

            for ($i = 0; $i < $count; $i++) {
                $amt = $share + ($i === 0 ? $remainder : 0);
                $fee = $this->calculateFee((int) $sender['operateur_id'], 'TRANSFERT', $amt);
                $expectedTotalDebit += $amt + $fee;
                $individualAmounts[] = ['amount' => $amt, 'fee' => $fee];
            }

            if ($balanceBefore < $expectedTotalDebit) {
                throw new RuntimeException('Solde insuffisant pour le transfert multiple.');
            }

            $senderBalanceAfter = $balanceBefore - $expectedTotalDebit;

            // Update sender balance
            $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                'solde' => $senderBalanceAfter,
                'id' => $clientId,
            ]);

            // Process each recipient
            $cumulativeDebit = 0.0;
            foreach ($recipients as $idx => $recipient) {
                $amt = $individualAmounts[$idx]['amount'];
                $fee = $individualAmounts[$idx]['fee'];
                $recipientBalanceBefore = (float) $recipient['solde'];
                $recipientBalanceAfter = $recipientBalanceBefore + $amt;

                $this->execute('UPDATE clients SET solde = :solde WHERE id = :id', [
                    'solde' => $recipientBalanceAfter,
                    'id' => (int) $recipient['id'],
                ]);

                $cumulativeDebit += $amt + $fee;
                $opSoldeAvant = $balanceBefore;
                $opSoldeApres = $balanceBefore - $cumulativeDebit;

                $this->execute(
                    'INSERT INTO operations (type_operation_id, client_id, client_destinataire_id, montant, frais, solde_avant, solde_apres)
                     VALUES (:type_operation_id, :client_id, :client_destinataire_id, :montant, :frais, :solde_avant, :solde_apres)',
                    [
                        'type_operation_id' => $this->requireOperationTypeId('TRANSFERT'),
                        'client_id' => $clientId,
                        'client_destinataire_id' => (int) $recipient['id'],
                        'montant' => $amt,
                        'frais' => $fee,
                        'solde_avant' => $opSoldeAvant,
                        'solde_apres' => $opSoldeApres,
                    ]
                );

                $results[] = ['recipient_phone' => $recipient['telephone'], 'amount' => $amt, 'fee' => $fee];
            }

            // Fix solde_apres values for operations if necessary
            $this->db->exec('COMMIT');
        } catch (\Throwable $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }

        return ['balance_before' => $balanceBefore, 'balance_after' => $senderBalanceAfter, 'details' => $results];
    }

    public function getOperatorPrefixes(): array
    {
        return $this->fetchAll(
            'SELECT p.id, p.prefixe, op.code AS operateur_code, op.nom AS operateur_nom, p.operateur_id
             FROM prefixes p
             JOIN operateurs op ON op.id = p.operateur_id
             ORDER BY op.code, p.prefixe'
        );
    }

    public function getOperators(): array
    {
        return $this->fetchAll('SELECT id, code, nom, est_principal FROM operateurs ORDER BY code');
    }

    public function getCommissionsInterOperators(): array
    {
        return $this->fetchAll(
            'SELECT ci.id, ci.operateur_id, ci.pourcentage, ci.pourcentage_supplementaire, op.code AS operateur_code, op.nom AS operateur_nom
             FROM commissions_inter_operateurs ci
             JOIN operateurs op ON op.id = ci.operateur_id
             ORDER BY op.code'
        );
    }

    public function setCommissionInterOperateur(int $operateurId, float $pourcentage): void
    {
        if ($operateurId <= 0) {
            throw new RuntimeException('Opérateur invalide.');
        }

        // Either update existing or insert
        $exists = $this->fetchOne('SELECT id FROM commissions_inter_operateurs WHERE operateur_id = :operateur_id', ['operateur_id' => $operateurId]);
        if ($exists === null) {
            $this->execute('INSERT INTO commissions_inter_operateurs (operateur_id, pourcentage) VALUES (:operateur_id, :pourcentage)', [
                'operateur_id' => $operateurId,
                'pourcentage' => $pourcentage,
            ]);
        } else {
            $this->execute('UPDATE commissions_inter_operateurs SET pourcentage = :pourcentage WHERE operateur_id = :operateur_id', [
                'pourcentage' => $pourcentage,
                'operateur_id' => $operateurId,
            ]);
        }
    }

    public function deleteCommissionInterOperateur(int $operateurId): void
    {
        if ($operateurId <= 0) {
            throw new RuntimeException('Opérateur invalide.');
        }

        $this->execute('DELETE FROM commissions_inter_operateurs WHERE operateur_id = :operateur_id', [
            'operateur_id' => $operateurId,
        ]);
    }

    public function getMontantsAEnvoyer(): array
    {
        return $this->fetchAll('SELECT operateur_cible, nombre_transferts, montant_net_a_envoyer FROM vue_montants_a_envoyer ORDER BY operateur_cible');
    }

    public function getTypesOperation(): array
    {
        return $this->fetchAll('SELECT id, code, libelle, avec_frais FROM types_operation ORDER BY id');
    }

    public function getFeeBands(?int $operateurId = null): array
    {
        $sql = 'SELECT b.id, b.operateur_id, b.type_operation_id, b.montant_min, b.montant_max, b.frais,
                       op.code AS operateur_code, t.code AS type_code, t.libelle AS type_libelle
                FROM baremes_frais b
                JOIN operateurs op ON op.id = b.operateur_id
                JOIN types_operation t ON t.id = b.type_operation_id';

        $params = [];
        if ($operateurId !== null) {
            $sql .= ' WHERE b.operateur_id = :operateur_id';
            $params['operateur_id'] = $operateurId;
        }

        $sql .= ' ORDER BY op.code, t.code, b.montant_min';

        return $this->fetchAll($sql, $params);
    }

    public function updateFeeBand(int $bandId, float $frais): void
    {
        if ($bandId <= 0) {
            throw new RuntimeException('Barème invalide.');
        }

        $this->execute('UPDATE baremes_frais SET frais = :frais WHERE id = :id', [
            'frais' => $frais,
            'id' => $bandId,
        ]);
    }

    public function addPrefix(int $operateurId, string $prefix): void
    {
        if ($operateurId <= 0) {
            throw new RuntimeException('Opérateur invalide.');
        }

        $prefix = trim($prefix);
        if (!preg_match('/^\d{3}$/', $prefix)) {
            throw new RuntimeException('Le préfixe doit contenir exactement 3 chiffres.');
        }

        $exists = $this->fetchOne('SELECT id FROM prefixes WHERE prefixe = :prefixe', ['prefixe' => $prefix]);
        if ($exists !== null) {
            throw new RuntimeException('Ce préfixe existe déjà.');
        }

        $this->execute('INSERT INTO prefixes (operateur_id, prefixe) VALUES (:operateur_id, :prefixe)', [
            'operateur_id' => $operateurId,
            'prefixe' => $prefix,
        ]);
    }

    public function getOperatorGains(): array
    {
        return $this->fetchAll('SELECT operateur, type_operation, nombre_operations, total_frais FROM vue_gains_operateur ORDER BY operateur, type_operation');
    }

    public function getClientSituation(): array
    {
        return $this->fetchAll('SELECT id, operateur, telephone, solde, date_creation, nombre_operations FROM vue_situation_comptes ORDER BY solde DESC, id ASC');
    }

    private function initializeSchema(): void
    {
        $schemaFile = ROOTPATH . 'base.sql';
        if (!is_file($schemaFile)) {
            throw new RuntimeException('Le fichier base.sql est introuvable.');
        }

        // If operateurs table does not exist, initialize from base.sql (fresh DB)
        if (!$this->tableExists('operateurs')) {
            $sql = file_get_contents($schemaFile);
            if ($sql === false) {
                throw new RuntimeException('Impossible de lire base.sql.');
            }

            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
            $statements = preg_split('/;\s*(?:\n|$)/', trim($sql)) ?: [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }

                if (!$this->db->exec($statement)) {
                    throw new RuntimeException('Erreur SQL: ' . $this->db->lastErrorMsg());
                }
            }

            return;
        }

        // If table exists, apply lightweight migrations for V2 if needed
        // 1) Ensure operateurs.est_principal exists
        $columns = $this->fetchAll("PRAGMA table_info('operateurs')");
        $hasEstPrincipal = false;
        foreach ($columns as $col) {
            if (isset($col['name']) && $col['name'] === 'est_principal') {
                $hasEstPrincipal = true;
                break;
            }
        }

        if (!$hasEstPrincipal) {
            // Add column with default 0 and set ORANGE to 1
            $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
            try {
                if (!$this->db->exec('ALTER TABLE operateurs ADD COLUMN est_principal INTEGER NOT NULL DEFAULT 0')) {
                    throw new RuntimeException('Impossible d\'ajouter la colonne est_principal: ' . $this->db->lastErrorMsg());
                }
                $this->execute("UPDATE operateurs SET est_principal = 1 WHERE code = 'ORANGE'");
                $this->db->exec('COMMIT');
            } catch (\Throwable $e) {
                $this->db->exec('ROLLBACK');
                throw $e;
            }
        }

        // 2) Ensure commissions_inter_operateurs table exists
        if (!$this->tableExists('commissions_inter_operateurs')) {
            $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
            try {
                $this->db->exec(
                    'CREATE TABLE IF NOT EXISTS commissions_inter_operateurs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        operateur_id INTEGER NOT NULL,
                        pourcentage DECIMAL(5,2) NOT NULL DEFAULT 0,
                        pourcentage_supplementaire DECIMAL(5,2) NOT NULL DEFAULT 0,
                        FOREIGN KEY (operateur_id) REFERENCES operateurs(id)
                    )'
                );

                // Insert defaults for all existing operators (including ORANGE)
                $ops = $this->fetchAll('SELECT id FROM operateurs');
                foreach ($ops as $op) {
                    $this->execute('INSERT OR IGNORE INTO commissions_inter_operateurs (operateur_id, pourcentage, pourcentage_supplementaire) VALUES (:operateur_id, 0.00, 0.00)', ['operateur_id' => $op['id']]);
                }

                // Create or update V2 views if missing
                $this->db->exec(
                    "CREATE VIEW IF NOT EXISTS vue_commissions_inter_operateurs AS
                    SELECT
                        op.code AS operateur_cible,
                        ci.pourcentage AS pourcentage,
                        COUNT(o.id) AS nombre_transferts,
                        SUM(o.montant * ci.pourcentage / 100.0) AS total_commissions
                    FROM operations o
                    JOIN types_operation t ON t.id = o.type_operation_id AND t.code = 'TRANSFERT'
                    JOIN clients c ON c.id = o.client_id
                    JOIN operateurs op_src ON op_src.id = c.operateur_id
                    JOIN clients cd ON cd.id = o.client_destinataire_id
                    JOIN operateurs op ON op.id = cd.operateur_id
                    JOIN commissions_inter_operateurs ci ON ci.operateur_id = op.id
                    WHERE op_src.est_principal = 1 AND op.est_principal = 0
                    GROUP BY op.code, ci.pourcentage"
                );

                $this->db->exec(
                    "CREATE VIEW IF NOT EXISTS vue_montants_a_envoyer AS
                    SELECT
                        op.code AS operateur_cible,
                        COUNT(o.id) AS nombre_transferts,
                        SUM(o.montant - (o.montant * COALESCE(ci.pourcentage,0) / 100.0)) AS montant_net_a_envoyer
                    FROM operations o
                    JOIN types_operation t ON t.id = o.type_operation_id AND t.code = 'TRANSFERT'
                    JOIN clients c ON c.id = o.client_id
                    JOIN operateurs op_src ON op_src.id = c.operateur_id
                    JOIN clients cd ON cd.id = o.client_destinataire_id
                    JOIN operateurs op ON op.id = cd.operateur_id
                    LEFT JOIN commissions_inter_operateurs ci ON ci.operateur_id = op.id
                    WHERE op_src.est_principal = 1 AND op.est_principal = 0
                    GROUP BY op.code"
                );

                $this->db->exec('COMMIT');
            } catch (\Throwable $e) {
                $this->db->exec('ROLLBACK');
                throw $e;
            }
        }

        // Ensure every operator has an entry in commissions_inter_operateurs
        $missing = $this->fetchAll('SELECT id FROM operateurs WHERE id NOT IN (SELECT operateur_id FROM commissions_inter_operateurs)');
        foreach ($missing as $op) {
            $this->execute('INSERT OR IGNORE INTO commissions_inter_operateurs (operateur_id, pourcentage, pourcentage_supplementaire) VALUES (:operateur_id, 0.00, 0.00)', ['operateur_id' => $op['id']]);
        }

        // If existing table lacks the new column, attempt lightweight migration (add column)
        $cols = $this->fetchAll("PRAGMA table_info('commissions_inter_operateurs')");
        $hasSupplement = false;
        foreach ($cols as $c) {
            if (isset($c['name']) && $c['name'] === 'pourcentage_supplementaire') {
                $hasSupplement = true;
                break;
            }
        }

        if (!$hasSupplement) {
            // add column with default 0
            $this->db->exec('BEGIN IMMEDIATE TRANSACTION');
            try {
                if (!$this->db->exec('ALTER TABLE commissions_inter_operateurs ADD COLUMN pourcentage_supplementaire DECIMAL(5,2) NOT NULL DEFAULT 0')) {
                    throw new RuntimeException('Impossible d\'ajouter la colonne pourcentage_supplementaire: ' . $this->db->lastErrorMsg());
                }
                $this->db->exec('COMMIT');
            } catch (\Throwable $e) {
                $this->db->exec('ROLLBACK');
                // Non-fatal: just continue with default behavior
            }
        }
    }

    private function findClientByPhone(string $telephone): ?array
    {
        return $this->fetchOne(
            'SELECT c.id, c.operateur_id, c.telephone, c.solde, c.date_creation, op.code AS operateur_code, op.nom AS operateur_nom
             FROM clients c
             JOIN operateurs op ON op.id = c.operateur_id
             WHERE c.telephone = :telephone',
            ['telephone' => $telephone]
        );
    }

    private function findOperatorByPhone(string $telephone): ?array
    {
        $prefix = substr($telephone, 0, 3);

        return $this->fetchOne(
            'SELECT op.id AS operateur_id, op.code AS operateur_code, op.nom AS operateur_nom, p.prefixe
             FROM prefixes p
             JOIN operateurs op ON op.id = p.operateur_id
             WHERE p.prefixe = :prefixe
             LIMIT 1',
            ['prefixe' => $prefix]
        );
    }

    private function requireClient(int $clientId): array
    {
        $client = $this->getClientSummary($clientId);
        if ($client === null) {
            throw new RuntimeException('Compte client introuvable.');
        }

        return $client;
    }

    private function requireOperationTypeId(string $code): int
    {
        $row = $this->fetchOne('SELECT id FROM types_operation WHERE code = :code', ['code' => $code]);
        if ($row === null) {
            throw new RuntimeException('Type d\'opération introuvable: ' . $code);
        }

        return (int) $row['id'];
    }

    private function calculateFee(int $operateurId, string $operationCode, float $amount): float
    {
        $row = $this->fetchOne(
            'SELECT frais
             FROM baremes_frais b
             JOIN types_operation t ON t.id = b.type_operation_id
             WHERE b.operateur_id = :operateur_id
               AND t.code = :type_code
               AND :amount BETWEEN b.montant_min AND b.montant_max
             ORDER BY b.montant_min
             LIMIT 1',
            [
                'operateur_id' => $operateurId,
                'type_code' => $operationCode,
                'amount' => $amount,
            ]
        );

        return $row === null ? 0.0 : (float) $row['frais'];
    }

    private function tableExists(string $tableName): bool
    {
        $row = $this->fetchOne(
            'SELECT name FROM sqlite_master WHERE type = "table" AND name = :name',
            ['name' => $tableName]
        );

        return $row !== null;
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return $rows[0] ?? null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        if (!$statement instanceof \SQLite3Stmt) {
            throw new RuntimeException('Impossible de préparer la requête SQL.');
        }

        $this->bindParameters($statement, $params);

        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException($this->db->lastErrorMsg());
        }

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        $result->finalize();
        $statement->close();

        return $rows;
    }

    private function execute(string $sql, array $params = []): void
    {
        $statement = $this->db->prepare($sql);
        if (!$statement instanceof \SQLite3Stmt) {
            throw new RuntimeException('Impossible de préparer la requête SQL.');
        }

        $this->bindParameters($statement, $params);

        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException($this->db->lastErrorMsg());
        }

        $result->finalize();
        $statement->close();
    }

    private function bindParameters(\SQLite3Stmt $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = SQLITE3_TEXT;
            if (is_int($value)) {
                $type = SQLITE3_INTEGER;
            } elseif (is_float($value)) {
                $type = SQLITE3_FLOAT;
            } elseif ($value === null) {
                $type = SQLITE3_NULL;
            }

            $statement->bindValue(':' . $key, $value, $type);
        }
    }

}
