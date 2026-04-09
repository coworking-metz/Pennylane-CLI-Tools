#!/usr/bin/env php
<?php

include '_main.php';

// ============================================================
//  BILAN ANNUEL COMPLET - Le Poulailler Coworking Metz
// ============================================================

$options = getopt("", [
    "annee::",
    "date-from::",
    "date-to::",
    "help::"
]);

$today       = new DateTime();
$currentYear = (int)$today->format('Y');
$defaultFrom = ($currentYear - 1) . "-01-01";
$defaultTo   = ($currentYear - 1) . "-12-31";

if (isset($options['help'])) {
    echo "\n";
    echo "============================================================\n";
    echo " BILAN ANNUEL COMPLET - Le Poulailler Coworking Metz\n";
    echo "============================================================\n\n";
    echo "Description:\n";
    echo "  Génère un récapitulatif financier complet pour le bilan\n";
    echo "  annuel de l'association, toutes familles confondues.\n\n";
    echo "Paramètres:\n";
    echo "  --annee=YYYY|current     (OPTIONNEL, ex: --annee=2024 ou --annee=current)\n";
    echo "  --date-from=YYYY-MM-DD   (OPTIONNEL, défaut: $defaultFrom)\n";
    echo "  --date-to=YYYY-MM-DD     (OPTIONNEL, défaut: $defaultTo)\n";
    echo "  --help                   Affiche cette aide\n\n";
    echo "Exemples:\n";
    echo "  php bilan_annuel_complet.php\n";
    echo "  php bilan_annuel_complet.php --annee=2024\n";
    echo "  php bilan_annuel_complet.php --date-from=2024-01-01 --date-to=2024-12-31\n\n";
    exit(0);
}

if (isset($options['annee'])) {
    $anneeOpt = ($options['annee'] === 'current') ? $currentYear : (int)$options['annee'];
    $dateFrom = $anneeOpt . "-01-01";
    $dateTo   = $anneeOpt . "-12-31";
} else {
    $dateFrom = $options['date-from'] ?? $defaultFrom;
    $dateTo   = $options['date-to']   ?? $defaultTo;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    echo "Format de date invalide. Utilise YYYY-MM-DD\n";
    exit(1);
}

$headers = [
    "Authorization: Bearer " . API_KEY,
    "Accept: application/json"
];

// ============================================================
// PAGINATION GÉNÉRIQUE
// ============================================================

function fetchAll($url, $headers) {
    $results = [];
    $cursor  = null;

    do {
        $separator = (parse_url($url, PHP_URL_QUERY)) ? "&" : "?";
        $fullUrl   = $url . $separator . "limit=100";

        if ($cursor) {
            $fullUrl .= "&cursor=" . urlencode($cursor);
        }

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "CURL error: " . curl_error($ch) . "\n";
            exit(1);
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['items'])) {
            echo "Réponse API invalide pour : $url\n";
            print_r($data);
            exit(1);
        }

        $results = array_merge($results, $data['items']);
        $cursor  = $data['next_cursor'] ?? null;

    } while ($cursor);

    return $results;
}

// ============================================================
// FETCH CATEGORY GROUPS
// ============================================================

$groups   = fetchAll("https://app.pennylane.com/api/external/v2/category_groups", $headers);
$groupMap = [];

foreach ($groups as $g) {
    $groupMap[$g['id']] = $g['label'];
}

// ============================================================
// FETCH CATEGORIES
// ============================================================

$categories = fetchAll("https://app.pennylane.com/api/external/v2/categories", $headers);

// keyToId global
$keyToId = [];

// Familles connues (catégories principales)
$familleIds = [
    'Famille.Charges'                  => 14023608,
    'Famille.Dépenses exceptionnelles' => 14044902,
    'Famille.Dépenses hors bilan'      => 25917535,
    'Famille.Frais généraux'           => 14025772,
    'Famille.Investissements'          => 14023626,
    'Famille.Revenu exceptionnel'      => 14024276,
    'Famille.Revenu opérationnel'      => 14024173,
    'Famille.Revenus hors bilan'       => 25917536,
    'Famille.Subventions'              => 14024791,
];

// On reconstruit keyToId depuis l'API pour toutes catégories
foreach ($categories as $cat) {
    $groupId = $cat['category_group']['id'] ?? null;
    if (!$groupId || !isset($groupMap[$groupId])) continue;
    $family   = $groupMap[$groupId];
    $label    = $cat['label'];
    $key      = $family . "." . $label;
    $keyToId[$key] = $cat['id'];
}

// ============================================================
// FETCH TRANSACTIONS
// ============================================================

echo "⏳ Chargement des transactions...\n";
// $transactions = fetchAll("https://app.pennylane.com/api/external/v2/transactions", $headers);

$transactions = getPennylaneTransactions([
    'filter' => [
        ["field" => "date", "operator" => "gteq", "value" => $dateFrom],
        ["field" => "date", "operator" => "lteq", "value" => $dateTo]
    ],
]);
echo "✅ " . count($transactions) . " transactions chargées.\n\n";

// ============================================================
// CALCUL PAR FAMILLE
// ============================================================

// ============================================================
// DÉFINITION DES FAMILLES AVEC LEUR TYPE
// ============================================================

$familles = [
    // DÉPENSES
    [
        'key'   => 'Famille.Charges',
        'id'    => 14023608,
        'label' => 'Charges',
        'type'  => 'depense',
    ],
    [
        'key'   => 'Famille.Dépenses exceptionnelles',
        'id'    => 14044902,
        'label' => 'Dépenses exceptionnelles',
        'type'  => 'depense',
    ],
    [
        'key'   => 'Famille.Dépenses hors bilan',
        'id'    => 25917535,
        'label' => 'Dépenses hors bilan',
        'type'  => 'depense',
    ],
    [
        'key'   => 'Famille.Frais généraux',
        'id'    => 14025772,
        'label' => 'Frais généraux',
        'type'  => 'depense',
    ],
    [
        'key'   => 'Famille.Investissements',
        'id'    => 14023626,
        'label' => 'Investissements',
        'type'  => 'depense',
    ],
    // RECETTES
    [
        'key'   => 'Famille.Revenu exceptionnel',
        'id'    => 14024276,
        'label' => 'Revenu exceptionnel',
        'type'  => 'recette',
    ],
    [
        'key'   => 'Famille.Revenu opérationnel',
        'id'    => 14024173,
        'label' => 'Revenu opérationnel',
        'type'  => 'recette',
    ],
    [
        'key'   => 'Famille.Revenus hors bilan',
        'id'    => 25917536,
        'label' => 'Revenus hors bilan',
        'type'  => 'recette',
    ],
    [
        'key'   => 'Famille.Subventions',
        'id'    => 14024791,
        'label' => 'Subventions',
        'type'  => 'recette',
    ],
];

// ============================================================
// CALCUL ET AFFICHAGE
// ============================================================

$resultats = [];

foreach ($familles as $famille) {
    $res = computeFamilyTotal($transactions, $famille['id']);
    $famille['total'] = $res['total'];
    $famille['count'] = $res['count'];
    $resultats[] = $famille;
}

// Totaux globaux
$totalDepenses = 0.0;
$totalRecettes = 0.0;
$nbrDepenses   = 0;
$nbrRecettes   = 0;

foreach ($resultats as $r) {
    if ($r['type'] === 'depense') {
        $totalDepenses += $r['total'];
        $nbrDepenses   += $r['count'];
    } else {
        $totalRecettes += $r['total'];
        $nbrRecettes   += $r['count'];
    }
}

$solde = $totalRecettes + $totalDepenses; // Les dépenses sont en négatif dans Pennylane

// ============================================================
// CALCUL DES CHARGES FIXES MENSUELLES (hors investissements)
// ============================================================

// Identifier l'ID des investissements
$investissementId = null;
foreach ($familles as $f) {
    if ($f['key'] === 'Famille.Investissements') {
        $investissementId = $f['id'];
        break;
    }
}

// Total des charges hors investissements
$totalChargesHorsInvestissements = 0.0;
$nbrChargesHorsInvestissements = 0;

foreach ($transactions as $tx) {

    if (!isset($tx['categories'])) continue;

    $transactionDate = $tx['date'] ?? null;
    if (!$transactionDate) continue;

    if ($transactionDate < $dateFrom || $transactionDate > $dateTo) {
        continue;
    }

    $txCategoryIds = array_column($tx['categories'], 'id');

    // Vérifie si transaction = dépense
    $isDepense = false;
    foreach ($familles as $f) {
        if ($f['type'] === 'depense' && in_array($f['id'], $txCategoryIds)) {
            $isDepense = true;
            break;
        }
    }

    if (!$isDepense) continue;

    // Exclure investissements
    if ($investissementId && in_array($investissementId, $txCategoryIds)) {
        continue;
    }

    $totalChargesHorsInvestissements += (float)$tx['amount'];
    $nbrChargesHorsInvestissements++;
}

// Calcul du nombre de mois sur la période
$start = new DateTime($dateFrom);
$end   = new DateTime($dateTo);

$interval = $start->diff($end);
$months = ($interval->y * 12) + $interval->m + 1; // inclusif

if ($months <= 0) {
    $months = 1;
}

$chargesFixesMensuelles = $totalChargesHorsInvestissements / $months;
// ============================================================
// REVENUS RÉELS PAR MOIS
// ============================================================

$revenuOperationnelId = null;
$revenuExceptionnelId = null;

foreach ($familles as $f) {
    if ($f['key'] === 'Famille.Revenu opérationnel') {
        $revenuOperationnelId = $f['id'];
    }
    if ($f['key'] === 'Famille.Revenu exceptionnel') {
        $revenuExceptionnelId = $f['id'];
    }
}

// Génération des mois de la période
$monthlyData = [];
$period = new DatePeriod(
    new DateTime($dateFrom),
    new DateInterval('P1M'),
    (new DateTime($dateTo))->modify('first day of next month')
);

foreach ($period as $dt) {
    $key = $dt->format('Y-m');
    $monthlyData[$key] = [
        'operationnel' => 0.0,
        'exceptionnel' => 0.0
    ];
}

// Agrégation
foreach ($transactions as $tx) {

    if (!isset($tx['categories'])) continue;

    $transactionDate = $tx['date'] ?? null;
    if (!$transactionDate) continue;

    if ($transactionDate < $dateFrom || $transactionDate > $dateTo) {
        continue;
    }

    $monthKey = substr($transactionDate, 0, 7);
    if (!isset($monthlyData[$monthKey])) continue;

    $txCategoryIds = array_column($tx['categories'], 'id');

    if ($revenuOperationnelId && in_array($revenuOperationnelId, $txCategoryIds)) {
        $monthlyData[$monthKey]['operationnel'] += (float)$tx['amount'];
    }

    if ($revenuExceptionnelId && in_array($revenuExceptionnelId, $txCategoryIds)) {
        $monthlyData[$monthKey]['exceptionnel'] += (float)$tx['amount'];
    }
}

// Calcul moyennes
$totalOp = 0.0;
$totalEx = 0.0;
$nbMonths = count($monthlyData);

foreach ($monthlyData as $m) {
    $totalOp += $m['operationnel'];
    $totalEx += $m['exceptionnel'];
}

$moyOp = $nbMonths ? $totalOp / $nbMonths : 0;
$moyEx = $nbMonths ? $totalEx / $nbMonths : 0;
$moyTotal = $nbMonths ? ($totalOp + $totalEx) / $nbMonths : 0;

// ============================================================
// AFFICHAGE DU BILAN
// ============================================================

$separateur  = "============================================================\n";
$separateur2 = "------------------------------------------------------------\n";

echo "\n";
echo $separateur;
echo " 📊 BILAN ANNUEL COMPLET - Le Poulailler Coworking Metz\n";
echo $separateur;
echo " Période analysée : $dateFrom → $dateTo\n";
echo $separateur;

// --- RECETTES ---
echo "\n";
echo " 💰 RECETTES\n";
echo $separateur2;

foreach ($resultats as $r) {
    if ($r['type'] !== 'recette') continue;
    $montant = number_format($r['total'], 2, '.', ' ');
    $label   = str_pad($r['label'], 30);
    echo "  ✅ $label  {$montant} EUR   ({$r['count']} transactions)\n";
}

echo $separateur2;
$totalRec = number_format($totalRecettes, 2, '.', ' ');
echo "  TOTAL RECETTES                   $totalRec EUR   ($nbrRecettes transactions)\n";
echo $separateur2;

// --- DÉPENSES ---
echo "\n";
echo " 💸 DÉPENSES\n";
echo $separateur2;

foreach ($resultats as $r) {
    if ($r['type'] !== 'depense') continue;
    $montant = number_format($r['total'], 2, '.', ' ');
    $label   = str_pad($r['label'], 30);
    echo "  ❌ $label  {$montant} EUR   ({$r['count']} transactions)\n";
}

echo $separateur2;
$totalDep = number_format($totalDepenses, 2, '.', ' ');
echo "  TOTAL DÉPENSES                   $totalDep EUR   ($nbrDepenses transactions)\n";
echo $separateur2;

// --- SOLDE ---
echo "\n";
echo $separateur;
$soldeFormate = number_format($solde, 2, '.', ' ');
$emoji = $solde >= 0 ? "🟢" : "🔴";
echo " $emoji SOLDE NET (Recettes + Dépenses) : $soldeFormate EUR\n";
echo $separateur;
echo "\n";

echo "\n";
echo $separateur2;

$chargesMensuellesFormate = number_format($chargesFixesMensuelles, 2, '.', ' ');
$totalHorsInvestFormate   = number_format($totalChargesHorsInvestissements, 2, '.', ' ');

echo " 📊 CHARGES FIXES MENSUELLES (hors investissements)\n";
echo $separateur2;
echo "  $chargesMensuellesFormate EUR / mois\n";

echo $separateur2;

echo "\n";
$monthWidth = 12;
$colWidth   = 15;

echo "\n📊 REVENUS RÉELS PAR MOIS\n";
echo str_repeat("=", $monthWidth + ($colWidth * 2) + 6) . "\n";

// Header
echo str_pad("Mois", $monthWidth);
echo " | ";
echo str_pad("Opérationnel", $colWidth);
echo " | ";
echo str_pad("Exceptionnel", $colWidth);
echo "\n";

echo str_repeat("-", $monthWidth + ($colWidth * 2) + 6) . "\n";

// Data
foreach ($monthlyData as $month => $values) {

    $op = number_format($values['operationnel'], 2, '.', ' ') . "€";
    $ex = number_format($values['exceptionnel'], 2, '.', ' ') . "€";

    echo str_pad($month, $monthWidth);
    echo " | ";
    echo str_pad($op, $colWidth, ' ', STR_PAD_LEFT);  // aligné droite (finance)
    echo " | ";
    echo str_pad($ex, $colWidth, ' ', STR_PAD_LEFT);
    echo "\n";
}

echo str_repeat("=", $monthWidth + ($colWidth * 2) + 6) . "\n";

// ============================================================
// GÉNÉRATION HTML
// ============================================================

$annee = substr($dateFrom, 0, 4);
$htmlFile = "bilan_{$annee}.html";

// Calcul taux de couverture (recettes / |dépenses|)
$tauxCouverture = ($totalDepenses != 0)
    ? round(($totalRecettes / abs($totalDepenses)) * 100, 1)
    : 0;

// Prépare les lignes du tableau mensuel
$monthlyRowsHtml = '';
foreach ($monthlyData as $month => $values) {
    $op    = number_format($values['operationnel'], 2, ',', ' ') . ' €';
    $ex    = number_format($values['exceptionnel'], 2, ',', ' ') . ' €';
    $total = number_format($values['operationnel'] + $values['exceptionnel'], 2, ',', ' ') . ' €';
    $monthlyRowsHtml .= "
                <tr>
                    <td><strong>{$month}</strong></td>
                    <td class=\"has-text-right\">{$op}</td>
                    <td class=\"has-text-right\">{$ex}</td>
                    <td class=\"has-text-right\"><strong>{$total}</strong></td>
                </tr>";
}

// Agrégation trimestrielle
$quarterlyData = [];
foreach ($monthlyData as $month => $values) {
    [$y, $m] = explode('-', $month);
    $q = 'Q' . ceil((int)$m / 3) . ' ' . $y;
    if (!isset($quarterlyData[$q])) {
        $quarterlyData[$q] = ['operationnel' => 0.0, 'exceptionnel' => 0.0];
    }
    $quarterlyData[$q]['operationnel'] += $values['operationnel'];
    $quarterlyData[$q]['exceptionnel'] += $values['exceptionnel'];
}

$quarterlyRowsHtml = '';
foreach ($quarterlyData as $quarter => $values) {
    $op    = number_format($values['operationnel'], 2, ',', ' ') . ' €';
    $ex    = number_format($values['exceptionnel'], 2, ',', ' ') . ' €';
    $total = number_format($values['operationnel'] + $values['exceptionnel'], 2, ',', ' ') . ' €';
    $quarterlyRowsHtml .= "
                <tr>
                    <td><strong>{$quarter}</strong></td>
                    <td class=\"has-text-right\">{$op}</td>
                    <td class=\"has-text-right\">{$ex}</td>
                    <td class=\"has-text-right\"><strong>{$total}</strong></td>
                </tr>";
}

// Lignes recettes
$recettesRowsHtml = '';
foreach ($resultats as $r) {
    if ($r['type'] !== 'recette') continue;
    $montant = number_format($r['total'], 2, ',', ' ') . ' €';
    $recettesRowsHtml .= "
                <tr>
                    <td>{$r['label']}</td>
                    <td class=\"has-text-right\"><span class=\"tag is-success is-light\">{$montant}</span></td>
                    <td class=\"has-text-right has-text-grey\">{$r['count']} tx</td>
                </tr>";
}

// Lignes dépenses
$depensesRowsHtml = '';
foreach ($resultats as $r) {
    if ($r['type'] !== 'depense') continue;
    $montant = number_format($r['total'], 2, ',', ' ') . ' €';
    $depensesRowsHtml .= "
                <tr>
                    <td>{$r['label']}</td>
                    <td class=\"has-text-right\"><span class=\"tag is-danger is-light\">{$montant}</span></td>
                    <td class=\"has-text-right has-text-grey\">{$r['count']} tx</td>
                </tr>";
}

// Investissements
$investTotal = 0.0;
$investCount = 0;
foreach ($resultats as $r) {
    if ($r['key'] === 'Famille.Investissements') {
        $investTotal = $r['total'];
        $investCount = $r['count'];
        break;
    }
}
$investFormate = number_format($investTotal, 2, ',', ' ') . ' €';

// Données JSON pour le graphique récap recettes/dépenses/solde
$recDepLabels     = json_encode(['Recettes', 'Dépenses', 'Solde']);
$recDepData       = json_encode([round($totalRecettes, 2), round(abs($totalDepenses), 2), round($solde, 2)]);
$recDepColors     = json_encode([
    'rgba(72, 199, 142, 0.85)',
    'rgba(241, 70, 104, 0.85)',
    $solde >= 0 ? 'rgba(72, 199, 142, 0.85)' : 'rgba(241, 70, 104, 0.85)',
]);
$recDepBorders    = json_encode([
    '#48c78e',
    '#f14668',
    $solde >= 0 ? '#48c78e' : '#f14668',
]);

// Données JSON pour Chart.js
$chartMonthLabels    = json_encode(array_keys($monthlyData));
$chartMonthOp        = json_encode(array_map(fn($v) => round($v['operationnel'], 2), array_values($monthlyData)));
$chartMonthEx        = json_encode(array_map(fn($v) => round($v['exceptionnel'], 2), array_values($monthlyData)));

$chartQuarterLabels  = json_encode(array_keys($quarterlyData));
$chartQuarterOp      = json_encode(array_map(fn($v) => round($v['operationnel'], 2), array_values($quarterlyData)));
$chartQuarterEx      = json_encode(array_map(fn($v) => round($v['exceptionnel'], 2), array_values($quarterlyData)));

$totalRecettesFormate = number_format($totalRecettes, 2, ',', ' ') . ' €';
$totalDepensesFormate = number_format($totalDepenses, 2, ',', ' ') . ' €';
$soldeFormate2        = number_format($solde, 2, ',', ' ') . ' €';
$chargesFormate       = number_format($chargesFixesMensuelles, 2, ',', ' ') . ' €';
$moyOpFormate         = number_format($moyOp, 2, ',', ' ') . ' €';
$moyExFormate         = number_format($moyEx, 2, ',', ' ') . ' €';
$moyTotalFormate      = number_format($moyTotal, 2, ',', ' ') . ' €';

$soldeBgClass   = $solde >= 0 ? 'solde-pos' : 'solde-neg';
$soldeTextClass = $solde >= 0 ? 'has-text-success' : 'has-text-danger';
$soldeIcon      = $solde >= 0 ? '▲' : '▼';
$tauxClass      = $tauxCouverture >= 100 ? 'is-success' : ($tauxCouverture >= 80 ? 'is-warning' : 'is-danger');
$tauxBarColor   = $tauxCouverture >= 100 ? '#48c78e' : ($tauxCouverture >= 80 ? '#ffe08a' : '#f14668');
$tauxBarWidth   = min($tauxCouverture, 100);
$generatedAt    = date('d/m/Y à H:i');

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilan {$annee} — Le Poulailler Coworking Metz</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        body { background: #f5f7fa; }
        .hero { background: #f2af10; }
        .kpi-card { border-top: 4px solid; }
        .kpi-card.recettes  { border-color: #48c78e; }
        .kpi-card.depenses  { border-color: #f14668; }
        .kpi-card.solde-pos { border-color: #48c78e; }
        .kpi-card.solde-neg { border-color: #f14668; }
        .kpi-card.charges        { border-color: #3e8ed0; }
        .kpi-card.investissements { border-color: #7a5af8; }
        .kpi-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; white-space: nowrap; }
        .section-title { border-left: 4px solid #3e8ed0; padding-left: 0.75rem; margin-bottom: 1rem; }
        .table th { background: #f0f4f8; }
        .footer-note { color: #888; font-size: 0.85rem; }
        .taux-bar-wrap { background: #e8e8e8; border-radius: 999px; height: 10px; margin-top: 0.5rem; }
        .taux-bar { height: 10px; border-radius: 999px; }
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
                    <p class="title is-2 is-4-mobile mb-1" style="color: #fff;">Bilan financier {$annee}</p>
                    <p class="subtitle is-6-mobile" style="color: rgba(255,255,255,0.85);">Période : {$dateFrom} → {$dateTo}</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container pt-4 pb-2">
    <nav class="breadcrumb" aria-label="breadcrumbs">
        <ul>
            <li><a href="index.php">Bilans financiers</a></li>
            <li class="is-active"><a href="#" aria-current="page">Bilan {$annee}</a></li>
        </ul>
    </nav>
</div>

<div class="container pb-6">

    <!-- KPI row -->
    <div class="columns mb-5">

        <div class="column">
            <div class="box kpi-card recettes">
                <p class="heading has-text-grey">Recettes totales</p>
                <p class="kpi-value has-text-success">{$totalRecettesFormate}</p>
                <p class="has-text-grey is-size-7 mt-1">{$nbrRecettes} transactions</p>
            </div>
        </div>

        <div class="column">
            <div class="box kpi-card depenses">
                <p class="heading has-text-grey">Dépenses totales</p>
                <p class="kpi-value has-text-danger">{$totalDepensesFormate}</p>
                <p class="has-text-grey is-size-7 mt-1">{$nbrDepenses} transactions</p>
            </div>
        </div>

        <div class="column">
            <div class="box kpi-card {$soldeBgClass}">
                <p class="heading has-text-grey">Solde net</p>
                <p class="kpi-value {$soldeTextClass}">{$soldeIcon} {$soldeFormate2}</p>
                <p class="has-text-grey is-size-7 mt-1">Recettes + Dépenses</p>
            </div>
        </div>

        <div class="column">
            <div class="box kpi-card charges">
                <p class="heading has-text-grey">Charges fixes / mois</p>
                <p class="kpi-value has-text-info">{$chargesFormate}</p>
                <p class="has-text-grey is-size-7 mt-1">Hors investissements</p>
            </div>
        </div>

        <div class="column">
            <div class="box kpi-card investissements">
                <p class="heading has-text-grey">Investissements</p>
                <p class="kpi-value" style="color: #7a5af8;">{$investFormate}</p>
                <p class="has-text-grey is-size-7 mt-1">{$investCount} transactions</p>
            </div>
        </div>

    </div>

    <!-- Graphique Recettes / Dépenses / Solde -->
    <div class="box mb-5">
        <h2 class="title is-5 section-title">Recettes · Dépenses · Solde</h2>
        <canvas id="chart-recap" height="80"></canvas>
    </div>

    <!-- Taux de couverture -->
    <div class="box mb-5">
        <p class="heading has-text-grey">Taux de couverture des dépenses par les recettes</p>
        <p class="is-size-4 has-text-weight-bold">
            <span class="tag {$tauxClass} is-medium">{$tauxCouverture} %</span>
        </p>
        <div class="taux-bar-wrap mt-2">
            <div class="taux-bar" style="width: {$tauxBarWidth}%; background: {$tauxBarColor};"></div>
        </div>
        <p class="is-size-7 has-text-grey mt-3">
            Indique dans quelle mesure les recettes suffisent à couvrir les dépenses sur la période.
            Un taux de <strong>100 %</strong> signifie l'équilibre exact. En dessous, l'association a dépensé plus qu'elle n'a encaissé ; au-dessus, elle a dégagé un excédent.
        </p>
    </div>

    <!-- Recettes et Dépenses côte à côte -->
    <div class="columns mb-5">

        <div class="column">
            <div class="box">
                <h2 class="title is-5 section-title has-text-success">Recettes</h2>
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Famille</th>
                            <th class="has-text-right">Montant</th>
                            <th class="has-text-right">Nb</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$recettesRowsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total recettes</th>
                            <th class="has-text-right has-text-success">{$totalRecettesFormate}</th>
                            <th class="has-text-right has-text-grey">{$nbrRecettes} tx</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="column">
            <div class="box">
                <h2 class="title is-5 section-title has-text-danger">Dépenses</h2>
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Famille</th>
                            <th class="has-text-right">Montant</th>
                            <th class="has-text-right">Nb</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$depensesRowsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total dépenses</th>
                            <th class="has-text-right has-text-danger">{$totalDepensesFormate}</th>
                            <th class="has-text-right has-text-grey">{$nbrDepenses} tx</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

    <!-- Revenus réels -->
    <div class="box mb-5">
        <h2 class="title is-5 section-title">Revenus réels</h2>
        <div class="columns is-multiline mb-4">
            <div class="column is-4">
                <div class="notification is-info is-light py-3">
                    <p class="heading">Moy. revenu opérationnel</p>
                    <p class="is-size-5 has-text-weight-bold">{$moyOpFormate} / mois</p>
                </div>
            </div>
            <div class="column is-4">
                <div class="notification is-warning is-light py-3">
                    <p class="heading">Moy. revenu exceptionnel</p>
                    <p class="is-size-5 has-text-weight-bold">{$moyExFormate} / mois</p>
                </div>
            </div>
            <div class="column is-4">
                <div class="notification is-success is-light py-3">
                    <p class="heading">Moy. revenu total</p>
                    <p class="is-size-5 has-text-weight-bold">{$moyTotalFormate} / mois</p>
                </div>
            </div>
        </div>

        <div class="tabs">
            <ul>
                <li class="is-active" data-tab="mois"><a>Par mois</a></li>
                <li data-tab="trimestre"><a>Par trimestre</a></li>
            </ul>
        </div>

        <div id="tab-mois">
            <canvas id="chart-mois" height="90" class="mb-4"></canvas>
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Mois</th>
                        <th class="has-text-right">Opérationnel</th>
                        <th class="has-text-right">Exceptionnel</th>
                        <th class="has-text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$monthlyRowsHtml}
                </tbody>
            </table>
        </div>

        <div id="tab-trimestre" style="display:none;">
            <canvas id="chart-trimestre" height="90" class="mb-4"></canvas>
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Trimestre</th>
                        <th class="has-text-right">Opérationnel</th>
                        <th class="has-text-right">Exceptionnel</th>
                        <th class="has-text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {$quarterlyRowsHtml}
                </tbody>
            </table>
        </div>
    </div>

    <p class="footer-note has-text-centered">
        Généré le {$generatedAt} &nbsp;·&nbsp; Source : Pennylane API &nbsp;·&nbsp; Le Poulailler Coworking Metz
    </p>

</div>

<script>
    function makeChart(id, labels, opData, exData) {
        return new Chart(document.getElementById(id), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Opérationnel',
                        data: opData,
                        backgroundColor: 'rgba(62, 142, 208, 0.75)',
                        borderColor: '#3e8ed0',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Exceptionnel',
                        data: exData,
                        backgroundColor: 'rgba(255, 224, 138, 0.85)',
                        borderColor: '#e8a400',
                        borderWidth: 1,
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.dataset.label + ' : ' + ctx.parsed.y.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                            }
                        }
                    }
                }
            }
        });
    }

    // Graphique récap recettes / dépenses / solde
    new Chart(document.getElementById('chart-recap'), {
        type: 'bar',
        data: {
            labels: {$recDepLabels},
            datasets: [{
                data: {$recDepData},
                backgroundColor: {$recDepColors},
                borderColor: {$recDepBorders},
                borderWidth: 1,
                borderRadius: 6,
                barPercentage: 0.5,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.parsed.y.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
                        }
                    }
                }
            }
        }
    });

    var chartMois = makeChart(
        'chart-mois',
        {$chartMonthLabels},
        {$chartMonthOp},
        {$chartMonthEx}
    );

    var chartTrimestre = null;

    document.querySelectorAll('.tabs li[data-tab]').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.dataset.tab;
            document.querySelectorAll('.tabs li[data-tab]').forEach(function(t) {
                t.classList.remove('is-active');
            });
            this.classList.add('is-active');
            document.getElementById('tab-mois').style.display      = (target === 'mois')      ? '' : 'none';
            document.getElementById('tab-trimestre').style.display = (target === 'trimestre') ? '' : 'none';

            // Initialise le graphique trimestriel au premier clic (canvas invisible sinon)
            if (target === 'trimestre' && !chartTrimestre) {
                chartTrimestre = makeChart(
                    'chart-trimestre',
                    {$chartQuarterLabels},
                    {$chartQuarterOp},
                    {$chartQuarterEx}
                );
            }
        });
    });
</script>

</body>
</html>
HTML;

file_put_contents($htmlFile, $html);
echo "\n✅ Fichier HTML généré : {$htmlFile}\n";
