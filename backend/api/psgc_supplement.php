<?php
/**
 * PSGC Data Supplement
 *
 * This file supplements the PSGC cloud API with missing or incomplete data.
 * Used when the official PSGC API is incomplete or has encoding issues.
 */

// Cavite Cities/Municipalities (complete list as of 2024)
// Province code for Cavite: 043400000
$CAVITE_CITIES = [
    ['code' => '043401000', 'name' => 'Alfonso'],
    ['code' => '043402000', 'name' => 'Amadeo'],
    ['code' => '043403000', 'name' => 'Bacoor'],
    ['code' => '043404000', 'name' => 'Carmona'],
    ['code' => '043405000', 'name' => 'Cavite City'],
    ['code' => '043406000', 'name' => 'Dasmariñas'],  // Proper UTF-8 encoding
    ['code' => '043407000', 'name' => 'General Emilio Aguinaldo'],
    ['code' => '043408000', 'name' => 'General Mariano Alvarez'],  // GMA - ADDED!
    ['code' => '043409000', 'name' => 'General Trias'],
    ['code' => '043410000', 'name' => 'Imus'],
    ['code' => '043411000', 'name' => 'Indang'],
    ['code' => '043412000', 'name' => 'Kawit'],
    ['code' => '043413000', 'name' => 'Magallanes'],
    ['code' => '043414000', 'name' => 'Maragondon'],
    ['code' => '043415000', 'name' => 'Mendez'],
    ['code' => '043416000', 'name' => 'Naic'],
    ['code' => '043417000', 'name' => 'Noveleta'],
    ['code' => '043418000', 'name' => 'Rosario'],
    ['code' => '043419000', 'name' => 'Silang'],
    ['code' => '043420000', 'name' => 'Tagaytay'],
    ['code' => '043421000', 'name' => 'Tanza'],
    ['code' => '043422000', 'name' => 'Ternate'],
    ['code' => '043423000', 'name' => 'Trece Martires'],
];

/**
 * Get supplemental data for a given endpoint
 *
 * @param string $endpoint The PSGC endpoint (e.g., "provinces/042100000/cities")
 * @return array|null Supplemental data or null if no supplement exists
 */
function get_supplement($endpoint) {
    global $CAVITE_CITIES;

    // Match Cavite cities endpoint (flexible pattern for different PSGC versions)
    // Cavite province codes: 042100000 (v1), 043400000 (v2), or similar variations
    // Supports both /cities and /cities-municipalities endpoints
    if (preg_match('#^provinces/(042[0-9]{6}|043[0-9]{6})/(cities|cities-municipalities)$#', $endpoint)) {
        return $CAVITE_CITIES;
    }

    return null;
}

/**
 * Merge API response with supplemental data
 *
 * @param array $api_data Data from PSGC cloud API
 * @param array $supplement Supplemental data
 * @return array Merged and deduplicated data
 */
function merge_psgc_data($api_data, $supplement) {
    if (!$supplement) {
        return $api_data;
    }

    // Create a map of existing codes to avoid duplicates
    $existing_codes = [];
    foreach ($api_data as $item) {
        if (isset($item['code'])) {
            $existing_codes[$item['code']] = true;
        }
    }

    // Add supplement items that don't exist in API data
    $merged = $api_data;
    foreach ($supplement as $item) {
        if (!isset($existing_codes[$item['code']])) {
            $merged[] = $item;
        }
    }

    // Sort by name
    usort($merged, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $merged;
}
