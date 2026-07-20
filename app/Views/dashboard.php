<?php

/** @var array<string, mixed> $client */
/** @var array<int, array<string, mixed>> $history */
/** @var array<int, array<string, mixed>> $feeBands */
?>
<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <div class="mm-pill mb-3">Espace client</div>
        <h1 class="h2 mb-2">Bonjour <?= esc($client['telephone']) ?></h1>
        <div class="text-muted-soft">Opérateur: <?= esc($client['operateur_nom']) ?> (<?= esc($client['operateur_code']) ?>)</div>
    </div>
    <div class="text-lg-end">
        <div class="small text-muted-soft">Solde disponible</div>
        <div class="display-6 fw-bold"><?= number_format((float) $client['solde'], 0, ',', ' ') ?> Ar</div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body">
                <div class="text-muted-soft small">Compte créé le</div>
                <div class="fs-5 fw-bold"><?= esc($client['date_creation']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body">
                <div class="text-muted-soft small">Numéro connecté</div>
                <div class="fs-5 fw-bold"><?= esc($client['telephone']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body">
                <div class="text-muted-soft small">Opérations enregistrées</div>
                <div class="fs-5 fw-bold"><?= count($history) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Faire un dépôt</h2>
                <form method="post" action="<?= site_url('dashboard/deposit') ?>">
                    <div class="mb-3">
                        <label class="form-label">Montant</label>
                        <input type="number" min="1" step="1" name="amount" class="form-control form-control-lg" value="<?= esc(old('amount')) ?>" placeholder="10000" required>
                    </div>
                    <button class="btn btn-mm w-100">Valider le dépôt</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Faire un retrait</h2>
                <form method="post" action="<?= site_url('dashboard/withdraw') ?>">
                    <div class="mb-3">
                        <label class="form-label">Montant</label>
                        <input type="number" min="1" step="1" name="amount" class="form-control form-control-lg" value="<?= esc(old('amount')) ?>" placeholder="5000" required>
                    </div>
                    <button class="btn btn-warning w-100 fw-bold">Valider le retrait</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Faire un transfert</h2>
                <form method="post" action="<?= site_url('dashboard/transfer') ?>">
                    <div class="mb-3">
                        <label class="form-label">Téléphone destinataire</label>
                        <input type="text" name="recipient_phone" class="form-control form-control-lg" value="<?= esc(old('recipient_phone')) ?>" placeholder="0371234567" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Montant</label>
                        <input type="number" min="1" step="1" name="amount" class="form-control form-control-lg" value="<?= esc(old('amount')) ?>" placeholder="15000" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="include_withdrawal_fees" class="form-check-input" id="include_withdrawal_fees" value="1">
                        <label class="form-check-label" for="include_withdrawal_fees">Inclure les frais de retrait dans l'envoi (si destinataire chez nous)</label>
                    </div>
                    <button class="btn btn-mm w-100">Envoyer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Transfert multiple (mêmes opérateurs seulement)</h2>
                <form method="post" action="<?= site_url('dashboard/transfer-multiple') ?>">
                    <div class="mb-3">
                        <label class="form-label">Numéros destinataires (séparés par virgule ou nouvelle ligne)</label>
                        <textarea name="recipient_phones" class="form-control" rows="3" placeholder="0371234567, 0372345678" required><?= esc(old('recipient_phones')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Montant total à répartir</label>
                        <input type="number" min="1" step="1" name="total_amount" class="form-control" value="<?= esc(old('total_amount')) ?>" placeholder="30000" required>
                    </div>
                    <button class="btn btn-mm">Envoyer en plusieurs</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Historique des opérations</h2>
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sens</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Frais</th>
                                <th>Commission</th>
                                <th>Solde du compte</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($history)) : ?>
                            <tr><td colspan="7" class="text-muted-soft py-4">Aucune opération enregistrée.</td></tr>
                        <?php else : ?>
                            <?php foreach ($history as $row) : ?>
                                <tr>
                                    <td><?= esc($row['date_operation']) ?></td>
                                    <td><?= esc($row['sens'] === 'emission' ? 'Émission' : 'Réception') ?></td>
                                    <td>
                                        <?= esc($row['type_libelle']) ?>
                                        <?php if (!empty($row['destinataire'])) : ?>
                                            <div class="small text-muted-soft">Vers <?= esc($row['destinataire']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format((float) $row['montant'], 0, ',', ' ') ?> Ar</td>
                                    <td><?= number_format((float) $row['frais'], 0, ',', ' ') ?> Ar</td>
                                    <td><?= number_format((float) ($row['commission'] ?? 0.0), 0, ',', ' ') ?> Ar</td>
                                    <td><?= number_format((float) $row['solde_compte'], 0, ',', ' ') ?> Ar</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Barèmes appliqués à votre opérateur</h2>
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Tranche</th>
                                <th>Frais</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($feeBands as $band) : ?>
                            <tr>
                                <td><?= esc($band['type_libelle']) ?></td>
                                <td><?= number_format((float) $band['montant_min'], 0, ',', ' ') ?> - <?= number_format((float) $band['montant_max'], 0, ',', ' ') ?></td>
                                <td><?= number_format((float) $band['frais'], 0, ',', ' ') ?> Ar</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>