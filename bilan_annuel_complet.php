#!/usr/bin/env php
<?php

include '_main.php';

// ============================================================
//  BILAN ANNUEL COMPLET - Le Poulailler Coworking Metz
// ============================================================

$options = getopt("", [
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
    echo "  --date-from=YYYY-MM-DD   (OPTIONNEL, défaut: $defaultFrom)\n";
    echo "  --date-to=YYYY-MM-DD     (OPTIONNEL, défaut: $defaultTo)\n";
    echo "  --help                   Affiche cette aide\n\n";
    echo "Exemple:\n";
    echo "  php bilan_annuel_complet.php\n";
    echo "  php bilan_annuel_complet.php --date-from=2024-01-01 --date-to=2024-12-31\n\n";
    exit(0);
}

$dateFrom = $options['date-from'] ?? $defaultFrom;
$dateTo   = $options['date-to']   ?? $defaultTo;

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
