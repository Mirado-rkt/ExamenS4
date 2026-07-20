<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<div class="row align-items-center g-4 g-lg-5">
    <div class="col-lg-6">
        <div class="mm-pill mb-3">Connexion automatique par numéro</div>
        <h1 class="display-5 fw-bold mb-3">Un numéro, un compte, sans inscription préalable.</h1>
        <p class="lead text-muted-soft mb-4">
            Le client se connecte avec son numéro de téléphone. Si le numéro correspond à un préfixe autorisé,
            le compte est créé automatiquement et le tableau de bord s’ouvre immédiatement.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <span class="mm-pill">Dépôt automatique</span>
            <span class="mm-pill">Retrait avec frais</span>
            <span class="mm-pill">Transfert inter-opérateurs</span>
        </div>
    </div>
    <div class="col-lg-5 offset-lg-1">
        <div class="card surface text-white glass-panel border-0">
            <div class="card-body p-4 p-lg-5">
                <h2 class="h3 mb-3">Connexion client</h2>
                <p class="text-muted-soft mb-4">Entrez votre numéro de téléphone pour ouvrir ou créer votre compte.</p>
                <form method="post" action="<?= site_url('login') ?>">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Numéro de téléphone</label>
                        <input type="text" class="form-control form-control-lg" id="phone" name="phone" value="<?= esc(old('phone')) ?>" placeholder="033 12 345 67" required>
                    </div>
                    <button type="submit" class="btn btn-mm btn-lg w-100">Accéder au compte</button>
                </form>
                <div class="mt-4 small text-muted-soft">
                    Préfixes déjà configurés dans la base: 032, 033, 034, 035, 037 et 038.
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>