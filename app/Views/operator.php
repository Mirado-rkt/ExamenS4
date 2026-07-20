<?php

/** @var array<int, array<string, mixed>> $operators */
/** @var array<int, array<string, mixed>> $prefixes */
/** @var array<int, array<string, mixed>> $types */
/** @var array<int, array<string, mixed>> $feeBands */
/** @var array<int, array<string, mixed>> $gains */
/** @var array<int, array<string, mixed>> $clients */
?>
<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="mb-4">
    <div class="mm-pill mb-3">Espace opérateur</div>
    <h1 class="h2 mb-2">Configuration et suivi des activités</h1>
    <p class="text-muted-soft mb-0">Les préfixes, les barèmes et les situations clients sont stockés dans SQLite et consultables ici.</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Ajouter un préfixe</h2>
                <form method="post" action="<?= site_url('operateur/prefix') ?>" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Opérateur</label>
                        <select name="operateur_id" class="form-select" required>
                            <?php foreach ($operators as $operator) : ?>
                                    <option value="<?= esc($operator['id']) ?>"><?= esc($operator['code']) ?> - <?= esc($operator['nom']) ?><?= ((int) $operator['est_principal'] === 1) ? ' (Notre opérateur)' : '' ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Préfixe</label>
                        <input type="text" name="prefixe" class="form-control" maxlength="3" placeholder="039" required>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-mm w-100">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Types d'opérations</h2>
                <div class="table-responsive">
                    <table class="table table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Libellé</th>
                                <th>Avec frais</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($types as $type) : ?>
                            <tr>
                                <td><?= esc($type['code']) ?></td>
                                <td><?= esc($type['libelle']) ?></td>
                                <td><?= ((int) $type['avec_frais'] === 1) ? 'Oui' : 'Non' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card surface border-0 text-white">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Barèmes modifiables</h2>
                <div class="table-responsive">
                    <table class="table table-dark align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Opérateur</th>
                                <th>Type</th>
                                <th>Tranche</th>
                                <th>Frais</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($feeBands as $band) : ?>
                            <tr>
                                <td><?= esc($band['operateur_code']) ?></td>
                                <td><?= esc($band['type_libelle']) ?></td>
                                <td><?= number_format((float) $band['montant_min'], 0, ',', ' ') ?> - <?= number_format((float) $band['montant_max'], 0, ',', ' ') ?></td>
                                <td>
                                    <form method="post" action="<?= site_url('operateur/fee') ?>" class="d-flex gap-2 align-items-center">
                                        <input type="hidden" name="band_id" value="<?= esc($band['id']) ?>">
                                        <input type="number" min="0" step="1" name="frais" class="form-control form-control-sm" style="max-width: 130px;" value="<?= esc($band['frais']) ?>">
                                </td>
                                <td class="text-end">
                                        <button class="btn btn-sm btn-mm">Mettre à jour</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Situation des gains</h2>
                <div class="table-responsive">
                    <table class="table table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Opérateur</th>
                                <th>Opération</th>
                                <th>Nb</th>
                                <th>Frais</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($gains as $gain) : ?>
                            <tr>
                                <td><?= esc($gain['operateur']) ?></td>
                                <td><?= esc($gain['type_operation']) ?></td>
                                <td><?= esc($gain['nombre_operations']) ?></td>
                                <td><?= number_format((float) $gain['total_frais'], 0, ',', ' ') ?> Ar</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
        <div class="col-lg-7">
            <div class="card surface border-0 text-white h-100">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Commissions inter-opérateurs</h2>
                    <div class="table-responsive mb-3">
                        <table class="table table-dark mb-0">
                            <thead>
                                <tr>
                                    <th>Opérateur cible</th>
                                    <th>% Commission</th>
                                    <th>Nb transferts</th>
                                    <th>Total commissions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($commissions as $c) : ?>
                                <tr>
                                    <td><?= esc($c['operateur_code']) ?></td>
                                    <td><?= number_format((float) $c['pourcentage'], 2, ',', ' ') ?> %</td>
                                    <td><!-- données dynamiques via vue_commissions_inter_operateurs --> -</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="post" action="<?= site_url('operateur/commission') ?>" class="d-inline">
                                                <input type="hidden" name="operateur_id" value="<?= esc($c['operateur_id']) ?>">
                                                <input type="number" min="0" step="0.01" name="pourcentage" class="form-control form-control-sm" style="max-width:120px; display:inline-block;" value="<?= esc($c['pourcentage']) ?>">
                                                <button class="btn btn-sm btn-mm">OK</button>
                                            </form>
                                            <form method="post" action="<?= site_url('operateur/commission/delete') ?>" class="d-inline" onsubmit="return confirm('Confirmer la suppression de la commission pour <?= esc($c['operateur_code']) ?> ?');">
                                                <input type="hidden" name="operateur_id" value="<?= esc($c['operateur_id']) ?>">
                                                <button class="btn btn-sm btn-danger">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="h6 mb-2">Configurer une commission</h3>
                    <form method="post" action="<?= site_url('operateur/commission') ?>" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Opérateur cible</label>
                            <select name="operateur_id" class="form-select" required>
                                <?php foreach ($operators as $operator) : ?>
                                    <option value="<?= esc($operator['id']) ?>"><?= esc($operator['code']) ?> - <?= esc($operator['nom']) ?><?= ((int) $operator['est_principal'] === 1) ? ' (Notre opérateur)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pourcentage</label>
                            <input type="number" min="0" step="0.01" name="pourcentage" class="form-control" placeholder="1.5" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-mm w-100">Enregistrer</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <h3 class="h6 mb-2">Montants à envoyer par opérateur</h3>
                    <div class="table-responsive">
                        <table class="table table-dark mb-0">
                            <thead>
                                <tr>
                                    <th>Opérateur</th>
                                    <th>Nb transferts</th>
                                    <th>Montant net à envoyer</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($montants_a_envoyer)) : foreach ($montants_a_envoyer as $m) : ?>
                                <tr>
                                    <td><?= esc($m['operateur_cible']) ?></td>
                                    <td><?= esc($m['nombre_transferts']) ?></td>
                                    <td><?= number_format((float) $m['montant_net_a_envoyer'], 0, ',', ' ') ?> Ar</td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="3" class="text-muted-soft">Aucun montant à envoyer.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <div class="col-lg-7">
        <div class="card surface border-0 text-white h-100">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Situation des comptes clients</h2>
                <div class="table-responsive">
                    <table class="table table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Téléphone</th>
                                <th>Opérateur</th>
                                <th>Solde</th>
                                <th>Opérations</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $client) : ?>
                            <tr>
                                <td><?= esc($client['telephone']) ?></td>
                                <td><?= esc($client['operateur']) ?></td>
                                <td><?= number_format((float) $client['solde'], 0, ',', ' ') ?> Ar</td>
                                <td><?= esc($client['nombre_operations']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card surface border-0 text-white">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Préfixes autorisés</h2>
                <div class="table-responsive">
                    <table class="table table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Opérateur</th>
                                <th>Préfixe</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prefixes as $prefix) : ?>
                            <tr>
                                <td><?= esc($prefix['operateur_code']) ?></td>
                                <td><?= esc($prefix['prefixe']) ?></td>
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