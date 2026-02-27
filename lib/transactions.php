<?php

/**
 * Fetch transactions from Pennylane API (handles full pagination automatically)
 *
 * Available args:
 * - cursor (string)
 * - limit (int 1-100)
 * - filter (array)  Example:
 *      [
 *          ["field" => "date", "operator" => "gt", "value" => "2024-12-31"],
 *          ["field" => "date", "operator" => "lt", "value" => "2026-01-01"]
 *      ]
 * - sort (string) Example: "-id" or "id"
 *
 * @param array $args
 * @return array
 * @throws Exception
 */
function getPennylaneTransactions(array $args = []): array
{
    $baseUrl = "https://app.pennylane.com/api/external/v2/transactions";

    $allItems = [];
    $hasMore = true;
    $cursor = $args['cursor'] ?? null;

    // Ensure limit is valid
    $limit = $args['limit'] ?? 100;
    $limit = max(1, min(100, (int)$limit));

    while ($hasMore) {

        $queryParams = [
            'limit' => $limit,
        ];

        if (!empty($cursor)) {
            $queryParams['cursor'] = $cursor;
        }

        if (!empty($args['filter'])) {
            $queryParams['filter'] = json_encode($args['filter']);
        }

        if (!empty($args['sort'])) {
            $queryParams['sort'] = $args['sort'];
        }

        $url = $baseUrl . '?' . http_build_query($queryParams);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . API_KEY,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API request failed with status {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);

        if (!isset($data['items'])) {
            throw new Exception("Invalid API response structure");
        }

        $allItems = array_merge($allItems, $data['items']);

        $hasMore = $data['has_more'] ?? false;
        $cursor = $data['next_cursor'] ?? null;

        if (!$hasMore || empty($cursor)) {
            break;
        }
    }

    return $allItems;
}
