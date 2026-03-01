<?php

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// STEP 1: Fetch collection page HTML
$collectionUrl = "https://abc.com/collections/gifts-for-him";
$html = fetchUrl($collectionUrl);

$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Extract product links
$productLinks = [];
$nodes = $xpath->query('//a[contains(@href,"/products/")]');

foreach ($nodes as $node) {
    $href = $node->getAttribute('href');
    if (strpos($href, '/products/') !== false) {
        $productLinks[] = "https://uk.lalique.com" . strtok($href, '?');
    }
}

$productLinks = array_unique($productLinks);
$productLinks = array_slice($productLinks, 0, 10); // LIMIT 10

// Prepare CSV
header('Content-Disposition: attachment; filename="shopify_to_woo.csv";');
header('Content-Type: text/csv; charset=UTF-8');

$csvFile = fopen('php://output', 'w');

fputcsv($csvFile, [
    'ID','Type','SKU','Name','Published','Is featured?',
    'Visibility in catalog','Short description','Description',
    'Tax status','In stock?','Regular price','Categories','Images'
]);

foreach ($productLinks as $productUrl) {

    $jsonUrl = $productUrl . ".json";
    $productData = json_decode(fetchUrl($jsonUrl), true);

    if (!isset($productData['product'])) continue;

    $product = $productData['product'];

    $title = $product['title'];
    $description = strip_tags($product['body_html']);
    $variant = $product['variants'][0];

    $price = $variant['price'];
    $sku = $variant['sku'];
    $stock = ($variant['inventory_quantity'] > 0) ? '1' : '100';
    $image = $product['images'][0]['src'] ?? '';

    fputcsv($csvFile, [
        '',
        'simple',
        $sku,
        $title,
        '1',
        '0',
        'visible',
        substr($description,0,150),
        $description,
        'taxable',
        $stock,
        $price,
        'Gifts for Her',
        $image
    ]);
}

fclose($csvFile);
exit;
?>