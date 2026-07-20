<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Mobile Money') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --mm-bg: #07111f;
            --mm-surface: rgba(10, 20, 36, 0.78);
            --mm-surface-strong: #0d1a2b;
            --mm-border: rgba(255, 255, 255, 0.08);
            --mm-accent: #19b69c;
            --mm-accent-2: #f5b942;
            --mm-text: #f5f7fb;
            --mm-muted: #9aa8bd;
        }

        body {
            min-height: 100vh;
            font-family: 'Manrope', sans-serif;
            color: var(--mm-text);
            background:
                radial-gradient(circle at top left, rgba(25, 182, 156, 0.18), transparent 32%),
                radial-gradient(circle at top right, rgba(245, 185, 66, 0.14), transparent 28%),
                linear-gradient(160deg, #09111f 0%, #101b2d 50%, #07111f 100%);
        }

        .navbar, .card, .table, .modal-content {
            backdrop-filter: blur(12px);
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--mm-accent), #2fe0c4);
            color: #031019;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }

        .surface {
            background: var(--mm-surface);
            border: 1px solid var(--mm-border);
            box-shadow: 0 18px 54px rgba(0, 0, 0, 0.28);
        }

        .hero-title, h1, h2, h3, h4, .brand-text {
            font-family: 'Space Grotesk', sans-serif;
        }

        .text-muted-soft {
            color: var(--mm-muted) !important;
        }

        .mm-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--mm-text);
            font-size: .85rem;
        }

        .btn-mm {
            --bs-btn-color: #041018;
            --bs-btn-bg: var(--mm-accent);
            --bs-btn-border-color: var(--mm-accent);
            --bs-btn-hover-bg: #11a58d;
            --bs-btn-hover-border-color: #11a58d;
            font-weight: 700;
        }

        .btn-mm-ghost {
            --bs-btn-color: var(--mm-text);
            --bs-btn-bg: transparent;
            --bs-btn-border-color: rgba(255,255,255,0.16);
            --bs-btn-hover-bg: rgba(255,255,255,0.08);
            --bs-btn-hover-border-color: rgba(255,255,255,0.26);
        }

        .table-dark {
            --bs-table-bg: transparent;
            --bs-table-border-color: rgba(255,255,255,0.1);
            --bs-table-color: var(--mm-text);
        }

        .table thead th {
            color: #dce6f7;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .glass-panel {
            border-radius: 24px;
            overflow: hidden;
        }

        .page-fade {
            animation: fadeIn 0.45s ease-out both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark border-bottom" style="background: rgba(7,17,31,0.72); border-color: rgba(255,255,255,0.08) !important;">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center gap-3" href="<?= site_url('/') ?>">
            <span class="brand-mark">MM</span>
            <span>
                <span class="d-block brand-text">Mobile Money S4</span>
                <small class="text-muted-soft">Examen final - version 1</small>
            </span>
        </a>
        <div class="ms-auto d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-mm-ghost" href="<?= site_url('login') ?>">Client</a>
            <a class="btn btn-sm btn-mm-ghost" href="<?= site_url('operateur') ?>">Opérateur</a>
            <?php if (session()->get('client_id')) : ?>
                <a class="btn btn-sm btn-mm" href="<?= site_url('logout') ?>">Déconnexion</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container py-4 py-lg-5 page-fade">
    <?php if ($message = session()->getFlashdata('success')) : ?>
        <div class="alert alert-success surface text-white border-0 mb-4"><?= esc($message) ?></div>
    <?php endif; ?>

    <?php if ($message = session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger surface text-white border-0 mb-4"><?= esc($message) ?></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>