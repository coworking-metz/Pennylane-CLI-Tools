<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilans financiers — Le Poulailler Coworking Metz</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">
    <style>
        body { background: #f5f7fa; min-height: 100vh; }
        .hero { background: #f2af10; }
        .bilan-card { transition: transform 0.15s, box-shadow 0.15s; border-top: 4px solid #f2af10; }
        .bilan-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
        .empty-state { color: #aaa; }
    </style>
</head>
<body>

<section class="hero mb-6">
    <div class="hero-body">
        <div class="container">
            <div class="is-flex is-align-items-center is-flex-wrap-wrap" style="gap: 1.25rem;">
                <img src="https://www.coworking-metz.fr/wp-content/uploads/2020/05/logo-Le-Poulailler-vecto-blanc-inverse%CC%81-horizontal-300.png"
                     alt="Le Poulailler Coworking Metz"
                     style="height: 44px; width: auto; display: block; flex-shrink: 0; max-width: 100%;">
                <div>
                    <p class="title is-2 is-4-mobile mb-1" style="color: #fff;">Bilans financiers</p>
                    <p class="subtitle is-6-mobile" style="color: rgba(255,255,255,0.85);">Le Poulailler — Coworking Metz</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container pb-6">

<?php
$files = glob('bilan_*.html');

// Tri décroissant (année la plus récente en premier)
rsort($files);

if (empty($files)):
?>
    <div class="has-text-centered empty-state py-6">
        <p class="is-size-4">Aucun bilan disponible.</p>
        <p class="is-size-6 mt-2">Lancez <code>php bilan_annuel_complet.php</code> pour en générer un.</p>
    </div>
<?php else: ?>

    <div class="columns is-multiline">
    <?php foreach ($files as $file):
        preg_match('/bilan_(\d{4})\.html/', $file, $m);
        $annee = $m[1] ?? basename($file, '.html');
        $mtime = filemtime($file);
        $genere = $mtime ? date('d/m/Y à H:i', $mtime) : '—';
        $size   = round(filesize($file) / 1024, 1) . ' Ko';
    ?>
        <div class="column is-4">
            <a href="<?= htmlspecialchars($file) ?>" class="box bilan-card is-block">
                <div class="is-flex is-justify-content-space-between is-align-items-flex-start mb-3">
                    <span class="tag is-warning is-medium has-text-weight-bold">Bilan <?= htmlspecialchars($annee) ?></span>
                    <span class="tag is-light"><?= $size ?></span>
                </div>
                <p class="is-size-5 has-text-weight-bold has-text-dark mb-1">Exercice <?= htmlspecialchars($annee) ?></p>
                <p class="is-size-7 has-text-grey">Généré le <?= $genere ?></p>
                <p class="mt-3 has-text-info is-size-7">Voir le bilan →</p>
            </a>
        </div>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

    <p class="has-text-centered has-text-grey is-size-7 mt-6">
        Le Poulailler Coworking Metz &nbsp;·&nbsp; Données issues de Pennylane
    </p>

</div>
</body>
</html>
