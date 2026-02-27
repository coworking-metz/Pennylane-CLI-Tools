<?php


/**
 * Calcule le total et le nombre de transactions
 * pour une liste d'IDs de catégories (au moins 1 match = inclus dans la famille).
 */
function computeFamilyTotal($transactions, $familyId) {
    $total = 0.0;
    $count = 0;

    $selection = []; 
    foreach ($transactions as $tx) {
        if (!isset($tx['categories'])) continue;


        $txCategoryIds = array_column($tx['categories'], 'id');

        // La transaction appartient à cette famille si elle contient l'ID de famille
        if (!in_array($familyId, $txCategoryIds)) continue;

        $selection[] = implode(' / ',[$tx['id'], $tx['date'], $tx['amount'], $tx['label'], implode(', ', array_column($tx['categories'], 'label'))]);

        // echo $total;
        $total += (float)$tx['amount'];
        // echo  " + " . $tx['amount'] .  " = " . $total . "\n";
        $count++;
    }

    return ['total' => $total, 'count' => $count, 'transactions' => $selection];
}
