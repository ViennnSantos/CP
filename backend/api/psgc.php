<?php
/**
 * PSGC (Philippine Standard Geographic Code) API
 * Returns provinces, cities, and barangays for NCR and CALABARZON
 */

header('Content-Type: application/json; charset=utf-8');

// Start output buffering to catch any accidental output
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get endpoint from URL path
$endpoint = $_GET['endpoint'] ?? '';

try {
    // Route requests
    if ($endpoint === 'provinces') {
        echo json_encode(get_provinces());
    } elseif (preg_match('/^provinces\/(\d+)\/cities$/', $endpoint, $matches)) {
        echo json_encode(get_cities($matches[1]));
    } elseif (preg_match('/^cities\/(\d+)\/barangays$/', $endpoint, $matches)) {
        echo json_encode(get_barangays($matches[1]));
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function get_provinces() {
    // NCR and CALABARZON provinces
    return [
        ['code' => '133900000', 'name' => 'Metropolitan Manila'],
        ['code' => '034100000', 'name' => 'Cavite'],
        ['code' => '040200000', 'name' => 'Laguna'],
        ['code' => '041000000', 'name' => 'Batangas'],
        ['code' => '045800000', 'name' => 'Rizal'],
        ['code' => '045600000', 'name' => 'Quezon']
    ];
}

function get_cities($province_code) {
    // Sample cities per province
    $cities_map = [
        '133900000' => [ // NCR
            ['code' => '137404000', 'name' => 'Caloocan'],
            ['code' => '137601000', 'name' => 'Las Piñas'],
            ['code' => '137602000', 'name' => 'Makati'],
            ['code' => '137603000', 'name' => 'Malabon'],
            ['code' => '137604000', 'name' => 'Mandaluyong'],
            ['code' => '133900000', 'name' => 'Manila'],
            ['code' => '137605000', 'name' => 'Marikina'],
            ['code' => '137606000', 'name' => 'Muntinlupa'],
            ['code' => '137607000', 'name' => 'Navotas'],
            ['code' => '137608000', 'name' => 'Parañaque'],
            ['code' => '137609000', 'name' => 'Pasay'],
            ['code' => '137610000', 'name' => 'Pasig'],
            ['code' => '137611000', 'name' => 'Pateros'],
            ['code' => '137404000', 'name' => 'Quezon City'],
            ['code' => '137612000', 'name' => 'San Juan'],
            ['code' => '137613000', 'name' => 'Taguig'],
            ['code' => '137614000', 'name' => 'Valenzuela']
        ],
        '034100000' => [ // Cavite
            ['code' => '034101000', 'name' => 'Alfonso'],
            ['code' => '034102000', 'name' => 'Amadeo'],
            ['code' => '034103000', 'name' => 'Bacoor'],
            ['code' => '034104000', 'name' => 'Carmona'],
            ['code' => '034105000', 'name' => 'Cavite City'],
            ['code' => '034106000', 'name' => 'Dasmariñas'],
            ['code' => '034107000', 'name' => 'General Mariano Alvarez'],
            ['code' => '034108000', 'name' => 'General Trias'],
            ['code' => '034109000', 'name' => 'Imus'],
            ['code' => '034110000', 'name' => 'Indang'],
            ['code' => '034111000', 'name' => 'Kawit'],
            ['code' => '034112000', 'name' => 'Magallanes'],
            ['code' => '034113000', 'name' => 'Maragondon'],
            ['code' => '034114000', 'name' => 'Mendez'],
            ['code' => '034115000', 'name' => 'Naic'],
            ['code' => '034116000', 'name' => 'Noveleta'],
            ['code' => '034117000', 'name' => 'Rosario'],
            ['code' => '034118000', 'name' => 'Silang'],
            ['code' => '034119000', 'name' => 'Tagaytay'],
            ['code' => '034120000', 'name' => 'Tanza'],
            ['code' => '034121000', 'name' => 'Ternate'],
            ['code' => '034122000', 'name' => 'Trece Martires']
        ]
    ];

    return $cities_map[$province_code] ?? [];
}

function get_barangays($city_code) {
    // Sample barangays - in production this should come from database
    // For now, return generic barangays
    $sample_barangays = [];
    for ($i = 1; $i <= 20; $i++) {
        $sample_barangays[] = [
            'code' => $city_code . str_pad($i, 3, '0', STR_PAD_LEFT),
            'name' => 'Barangay ' . $i
        ];
    }
    return $sample_barangays;
}
