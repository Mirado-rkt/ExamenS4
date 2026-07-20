-- =========================================================
-- base.sql - Simulateur Mobile Money (multi-opérateurs)
-- Version 1
-- SGBD : SQLite
-- Opérateurs pris en charge : Orange, Airtel, Yas (Telma)
-- =========================================================

PRAGMA foreign_keys = ON;

-- =========================================================
-- TABLES
-- =========================================================

-- Opérateurs de mobile money pris en charge par le système
CREATE TABLE operateurs (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    code   VARCHAR(20) NOT NULL UNIQUE,   -- ORANGE, AIRTEL, YAS
    nom    VARCHAR(50) NOT NULL           -- Orange Money, Airtel Money, Yas (MVola)
);

-- Préfixes valables, rattachés à un opérateur (ex: 032/037 = Orange)
CREATE TABLE prefixes (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    operateur_id  INTEGER NOT NULL,
    prefixe       VARCHAR(3) NOT NULL UNIQUE,
    FOREIGN KEY (operateur_id) REFERENCES operateurs(id)
);

-- Types d'opérations : DEPOT, RETRAIT, TRANSFERT
CREATE TABLE types_operation (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        VARCHAR(20) NOT NULL UNIQUE,
    libelle     VARCHAR(50) NOT NULL,
    avec_frais  INTEGER NOT NULL DEFAULT 0   -- 0 = gratuit, 1 = avec frais
);

-- Barème de frais par tranche de montant, propre à chaque opérateur
-- et à chaque type d'opération (modifiable indépendamment)
CREATE TABLE baremes_frais (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    operateur_id        INTEGER NOT NULL,
    type_operation_id   INTEGER NOT NULL,
    montant_min         DECIMAL(15,2) NOT NULL,
    montant_max         DECIMAL(15,2) NOT NULL,
    frais               DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (operateur_id) REFERENCES operateurs(id),
    FOREIGN KEY (type_operation_id) REFERENCES types_operation(id)
);

-- Comptes clients (login = numéro de téléphone, pas d'inscription)
-- L'opérateur est déduit automatiquement du préfixe du numéro à la création
CREATE TABLE clients (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    operateur_id   INTEGER NOT NULL,
    telephone      VARCHAR(15) NOT NULL UNIQUE,
    solde          DECIMAL(15,2) NOT NULL DEFAULT 0,
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operateur_id) REFERENCES operateurs(id)
);

-- Historique des opérations (dépôt, retrait, transfert)
-- Un transfert peut être inter-opérateurs : client_id et
-- client_destinataire_id peuvent appartenir à 2 opérateurs différents
CREATE TABLE operations (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    type_operation_id        INTEGER NOT NULL,
    client_id                INTEGER NOT NULL,  -- client qui initie l'opération
    client_destinataire_id   INTEGER,            -- utilisé uniquement pour un transfert
    montant                  DECIMAL(15,2) NOT NULL,
    frais                    DECIMAL(15,2) NOT NULL DEFAULT 0,
    solde_avant              DECIMAL(15,2) NOT NULL,
    solde_apres              DECIMAL(15,2) NOT NULL,
    date_operation           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_operation_id) REFERENCES types_operation(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (client_destinataire_id) REFERENCES clients(id)
);

-- =========================================================
-- VUES
-- =========================================================

-- Situation des gains via les frais (retrait et transfert), par opérateur
CREATE VIEW vue_gains_operateur AS
SELECT
    op.code                AS operateur,
    t.code                  AS type_operation,
    COUNT(o.id)              AS nombre_operations,
    SUM(o.frais)              AS total_frais
FROM operations o
JOIN types_operation t ON t.id = o.type_operation_id
JOIN clients c          ON c.id = o.client_id
JOIN operateurs op      ON op.id = c.operateur_id
WHERE t.avec_frais = 1
GROUP BY op.code, t.code;

-- Situation des comptes clients, avec leur opérateur
CREATE VIEW vue_situation_comptes AS
SELECT
    c.id,
    op.code AS operateur,
    c.telephone,
    c.solde,
    c.date_creation,
    (SELECT COUNT(*) FROM operations o WHERE o.client_id = c.id) AS nombre_operations
FROM clients c
JOIN operateurs op ON op.id = c.operateur_id;

-- Alias demandé dans l'énoncé de livraison
CREATE VIEW vue_situation_compte AS
SELECT * FROM vue_situation_comptes;

-- =========================================================
-- DONNEES DE BASE
-- =========================================================

-- Opérateurs
INSERT INTO operateurs (code, nom) VALUES
    ('ORANGE', 'Orange Money'),
    ('AIRTEL', 'Airtel Money'),
    ('YAS',    'Yas (ex-MVola/Telma)');

-- Préfixes valables par opérateur
-- Orange  : 032, 037
-- Airtel  : 033, 035
-- Yas     : 034, 038
INSERT INTO prefixes (operateur_id, prefixe) VALUES
    (1, '032'), (1, '037'),
    (2, '033'), (2, '035'),
    (3, '034'), (3, '038');

-- Types d'opérations
INSERT INTO types_operation (code, libelle, avec_frais) VALUES
    ('DEPOT',     'Dépôt',     0),
    ('RETRAIT',   'Retrait',   1),
    ('TRANSFERT', 'Transfert', 1);

-- Barème de frais RETRAIT et TRANSFERT pour chaque opérateur
-- (même grille de départ pour les 3, modifiable indépendamment via l'admin)
INSERT INTO baremes_frais (operateur_id, type_operation_id, montant_min, montant_max, frais)
SELECT o.id, t.id, tranche.montant_min, tranche.montant_max, tranche.frais
FROM operateurs o
JOIN types_operation t ON t.code IN ('RETRAIT', 'TRANSFERT')
JOIN (
    SELECT 100     AS montant_min, 1000    AS montant_max, 50   AS frais UNION ALL
    SELECT 1001,            5000,          50            UNION ALL
    SELECT 5001,             10000,        100           UNION ALL
    SELECT 10001,            25000,        200           UNION ALL
    SELECT 25001,            50000,        400           UNION ALL
    SELECT 50001,            100000,       800           UNION ALL
    SELECT 100001,           250000,       1500          UNION ALL
    SELECT 250001,           500000,       1500          UNION ALL
    SELECT 500001,           1000000,      2500          UNION ALL
    SELECT 1000001,          2000000,      3000
) AS tranche;

-- Clients de démonstration
INSERT INTO clients (operateur_id, telephone, solde) VALUES
    (1, '0321234567', 250000),
    (1, '0372345678', 85000),
    (2, '0333456789', 120000),
    (2, '0354567890', 45000),
    (3, '0345678901', 300000),
    (3, '0386789012', 60000);
