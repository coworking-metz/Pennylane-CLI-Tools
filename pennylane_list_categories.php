#!/usr/bin/env php
<?php

include 'config.php';

$headers = [
    "Authorization: Bearer " . API_KEY,
    "Accept: application/json"
];

function fetchAll($url, $headers) {

    $results = [];
    $cursor = null;

    do {

        $fullUrl = $url . "?limit=100";
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


// ================================
// FETCH CATEGORY GROUPS
// ================================

$groups = fetchAll(
    "https://app.pennylane.com/api/external/v2/category_groups",
    $headers
);

$groupMap = [];

foreach ($groups as $group) {
    $groupMap[$group['id']] = $group['label'];
}


// ================================
// FETCH CATEGORIES
// ================================

$categories = fetchAll(
    "https://app.pennylane.com/api/external/v2/categories",
    $headers
);

$list = [];

foreach ($categories as $cat) {

    $groupId = $cat['category_group']['id'] ?? null;
    $family  = $groupMap[$groupId] ?? "Unknown";
    $label   = $cat['label'] ?? null;
    $id      = $cat['id'] ?? null;

    if (!$label || !$id) continue;

    $list[] = [
        'family'   => $family,
        'category' => $label,
        'id'       => $id
    ];
}


// ================================
// SORT
// ================================

usort($list, function ($a, $b) {

    $cmp = strcasecmp($a['family'], $b['family']);
    if ($cmp !== 0) return $cmp;

    return strcasecmp($a['category'], $b['category']);
});


// ================================
// OUTPUT
// ================================

foreach ($list as $item) {
    echo $item['family'] . "." . $item['category'] . " (" . $item['id'] . ")\n";
}
