<?php

namespace App\Libraries;

use RuntimeException;
use SQLite3;
use SQLite3Result;

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
                    END AS solde_compte
             FROM operations o
             JOIN types_operation t ON t.id = o.type_operation_id
             JOIN clients c1 ON c1.id = o.client_id
             LEFT JOIN clients c2 ON c2.id = o.client_destinataire_id
             LEFT JOIN clients c3 ON c3.id = :client_id
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

    public function transfer(int $clientId, string $recipientPhone, float $amount): array
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
        $totalDebit = $amount + $fee;
        $balanceBefore = (float) $sender['solde'];

        if ($balanceBefore < $totalDebit) {
            throw new RuntimeException('Solde insuffisant pour ce transfert.');
        }

        $senderBalanceAfter = $balanceBefore - $totalDebit;
        $recipientBalanceBefore = (float) $recipient['solde'];
        $recipientBalanceAfter = $recipientBalanceBefore + $amount;

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
                    'frais' => $fee,
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
            'recipient_phone' => $recipient['telephone'],
        ];
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
        return $this->fetchAll('SELECT id, code, nom FROM operateurs ORDER BY code');
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
        if ($this->tableExists('operateurs')) {
            return;
        }

        $schemaFile = ROOTPATH . 'base.sql';
        if (!is_file($schemaFile)) {
            throw new RuntimeException('Le fichier base.sql est introuvable.');
        }

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