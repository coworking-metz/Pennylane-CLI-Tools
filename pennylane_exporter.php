#!/usr/bin/env php
<?php

include 'config.php';
$options = getopt("", [
    "categories:",
    "exclude-categories::",
    "date-from::",
    "date-to::",
    "help::"
]);

if (empty($options) || isset($options['help']) || !isset($options['categories'])) {

    $currentYear = (int)date('Y');
    $defaultFrom = ($currentYear - 1) . "-01-01";
    $defaultTo   = ($currentYear - 1) . "-12-31";

    echo "\n";
    echo "============================================================\n";
    echo " PENNYLANE TOTAL CALCULATOR\n";
    echo "============================================================\n\n";

    echo "Description:\n";
    echo "  Calcule le montant total des transactions Pennylane\n";
    echo "  correspondant EXACTEMENT aux catégories fournies.\n\n";

    echo "Règles de filtrage:\n";
    echo "  ✅ Une transaction doit contenir TOUTES les catégories de --categories\n";
    echo "  ❌ Elle est exclue si elle contient AU MOINS UNE catégorie de --exclude-categories\n";
    echo "  ✅ Filtre par date inclus\n\n";
    echo "Pour voir la liste des catégories, lancer le script `php pennylane_list_categories.php` sans arguments";

    echo "Paramètres:\n";
    echo "  --categories=\"Famille.Cat1,Famille.Cat2\"   (OBLIGATOIRE)\n";
    echo "  --exclude-categories=\"Famille.CatX\"       (OPTIONNEL)\n";
    echo "  --date-from=YYYY-MM-DD                     (OPTIONNEL)\n";
    echo "  --date-to=YYYY-MM-DD                       (OPTIONNEL)\n";
    echo "  --help                                     Affiche cette aide\n\n";

    echo "Dates par défaut:\n";
    echo "  Si aucune date n'est fournie:\n";
    echo "  Période analysée = année civile précédente complète\n";
    echo "  Exemple actuel: $defaultFrom → $defaultTo\n\n";

    echo "Exemples:\n\n";

    echo "  1) Année dernière automatique:\n";
    echo "     php pennylane_total.php --categories=\"Charges.Frais d'avocats\"\n\n";

    echo "  2) Plusieurs catégories (AND strict):\n";
    echo "     php pennylane_total.php \\\n";
    echo "       --categories=\"Charges.Frais d'avocats,Charges.Honoraires\"\n\n";

    echo "  3) Avec exclusion:\n";
    echo "     php pennylane_total.php \\\n";
    echo "       --categories=\"Charges.Frais d'avocats\" \\\n";
    echo "       --exclude-categories=\"Charges.Frais bancaires\"\n\n";

    echo "  4) Période personnalisée:\n";
    echo "     php pennylane_total.php \\\n";
    echo "       --categories=\"Produits.Ventes\" \\\n";
    echo "       --date-from=2024-01-01 \\\n";
    echo "       --date-to=2024-12-31\n\n";

    echo "Format des catégories:\n";
    echo "  Format strict : Famille.Categorie\n";
    echo "  Exemple : Charges.Frais d'avocats\n\n";

    echo "============================================================\n\n";

    exit(0);
}

$includeKeys = array_filter(array_map('trim', explode(',', $options['categories'])));
$excludeKeys = [];

if (isset($options['exclude-categories'])) {
    $excludeKeys = array_filter(array_map('trim', explode(',', $options['exclude-categories'])));
}


// ============================================
// DATE MANAGEMENT
// ============================================

$today = new DateTime();
$currentYear = (int)$today->format('Y');

$defaultFrom = ($currentYear - 1) . "-01-01";
$defaultTo   = ($currentYear - 1) . "-12-31";

$dateFrom = $options['date-from'] ?? $defaultFrom;
$dateTo   = $options['date-to']   ?? $defaultTo;


// Validation simple
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {

    echo "Format date invalide. Utilise YYYY-MM-DD\n";
    exit(1);
}


$headers = [
    "Authorization: Bearer " . API_KEY,
    "Accept: application/json"
];


// ============================================
// PAGINATION GENERIC
// ============================================

function fetchAll($url, $headers) {

    $results = [];
    $cursor = null;

    do {

        $separator = (parse_url($url, PHP_URL_QUERY)) ? "&" : "?";
        $fullUrl = $url . $separator . "limit=100";

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
            echo "Invalid API response\n";
            exit(1);
        }

        $results = array_merge($results, $data['items']);
        $cursor = $data['next_cursor'] ?? null;

    } while ($cursor);

    return $results;
}


// ============================================
// FETCH CATEGORY GROUPS
// ============================================

$groups = fetchAll(
    "https://app.pennylane.com/api/external/v2/category_groups",
    $headers
);

$groupMap = [];
foreach ($groups as $g) {
    $groupMap[$g['id']] = $g['label'];
}


// ============================================
// FETCH CATEGORIES
// ============================================

$categories = fetchAll(
    "https://app.pennylane.com/api/external/v2/categories",
    $headers
);

$keyToId = [];

foreach ($categories as $cat) {

    $groupId = $cat['category_group']['id'] ?? null;
    if (!$groupId || !isset($groupMap[$groupId])) continue;

    $family = $groupMap[$groupId];
    $label  = $cat['label'];

    $key = $family . "." . $label;
    $keyToId[$key] = $cat['id'];
}


// ============================================
// MAP KEYS TO IDS
// ============================================

function mapKeysToIds($keys, $keyToId) {

    $ids = [];

    foreach ($keys as $key) {
        if (isset($keyToId[$key])) {
            $ids[] = $keyToId[$key];
        } else {
            echo "⚠️ Catégorie introuvable : $key\n";
        }
    }

    return $ids;
}

$includeIds = mapKeysToIds($includeKeys, $keyToId);
$excludeIds = mapKeysToIds($excludeKeys, $keyToId);

// ============================================
// RESOLVE VALID CATEGORY NAMES
// ============================================

$validIncludeKeys = [];
foreach ($includeKeys as $key) {
    if (isset($keyToId[$key])) {
        $validIncludeKeys[] = $key;
    }
}

$validExcludeKeys = [];
foreach ($excludeKeys as $key) {
    if (isset($keyToId[$key])) {
        $validExcludeKeys[] = $key;
    }
}


if (empty($includeIds)) {
    echo "Aucune catégorie valide à inclure.\n";
    exit(1);
}


// ============================================
// FETCH TRANSACTIONS
// ============================================

$transactions = fetchAll(
    "https://app.pennylane.com/api/external/v2/transactions",
    $headers
);


// ============================================
// FILTER + TOTAL
// ============================================

$total = 0.0;
$count = 0;

foreach ($transactions as $tx) {

    if (!isset($tx['categories'])) continue;

    $transactionDate = $tx['date'] ?? null;
    if (!$transactionDate) continue;

    // ✅ filtre date
    if ($transactionDate < $dateFrom || $transactionDate > $dateTo) {
        continue;
    }

    $txCategoryIds = array_column($tx['categories'], 'id');

    // ✅ doit contenir TOUTES les catégories incluses
    $matchInclude = count(array_intersect($txCategoryIds, $includeIds)) === count($includeIds);
    if (!$matchInclude) continue;

    // ❌ ne doit contenir aucune catégorie exclue
    if (!empty($excludeIds)) {
        $matchExclude = count(array_intersect($txCategoryIds, $excludeIds)) > 0;
        if ($matchExclude) continue;
    }

    $total += (float)$tx['amount'];
    $count++;
}

// ============================================
// RESULT
// ============================================

echo "----------------------------------------\n";
echo "Période : $dateFrom → $dateTo\n";

echo "\nCatégories incluses :\n";
foreach ($validIncludeKeys as $cat) {
    echo "  ✅ $cat\n";
}

if (!empty($validExcludeKeys)) {
    echo "\nCatégories exclues :\n";
    foreach ($validExcludeKeys as $cat) {
        echo "  ❌ $cat\n";
    }
}

echo "\nTransactions retenues : $count\n";
echo "Montant total : " . number_format($total, 2, '.', ' ') . " EUR\n";
echo "----------------------------------------\n";
