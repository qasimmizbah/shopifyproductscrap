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

/*
|--------------------------------------------------------------------------
| ADD MULTIPLE COLLECTION URLS HERE
|--------------------------------------------------------------------------
*/

$collections = [
    "https://abs.com/collections/costume-jewellery-rings",
    "https://abc.com/collections/costume-jewellery-pendants",
    
];

$allProducts = [];
$alreadyAdded = [];

foreach ($collections as $collectionUrl) {

    $html = fetchUrl($collectionUrl);

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $productLinks = [];
    $nodes = $xpath->query('//a[contains(@href,"/products/")]');

    foreach ($nodes as $node) {
        $href = $node->getAttribute('href');

        if (strpos($href, '/products/') !== false) {
            $cleanUrl = "https://uk.lalique.com" . strtok($href, '?');
            $productLinks[] = $cleanUrl;
        }
    }

    $productLinks = array_unique($productLinks);
    $productLinks = array_slice($productLinks, 0, 10); // TAKE 10 PER COLLECTION

    foreach ($productLinks as $productUrl) {

        // Skip duplicate products
        if (in_array($productUrl, $alreadyAdded)) {
            continue;
        }

        $jsonUrl = $productUrl . ".json";
        $productData = json_decode(fetchUrl($jsonUrl), true);

        if (!isset($productData['product'])) continue;

        $product = $productData['product'];

        $allProducts[] = $product;
        $alreadyAdded[] = $productUrl;
    }
}

/*
|--------------------------------------------------------------------------
| GENERATE SINGLE CSV
|--------------------------------------------------------------------------
*/

header('Content-Disposition: attachment; filename="multi_collection_products.csv";');
header('Content-Type: text/csv; charset=UTF-8');

$csvFile = fopen('php://output', 'w');

fputcsv($csvFile, [
    'ID','Type','SKU','Name','Published','Is featured?',
    'Visibility in catalog','Short description','Description',
    'Tax status','In stock?','Regular price','Categories','Images'
]);

foreach ($allProducts as $product) {

    $title = $product['title'];
    $description = strip_tags($product['body_html']);
    $variant = $product['variants'][0];

    $price = $variant['price'];
    $sku = $variant['sku'];
    $stock = ($variant['inventory_quantity'] > 0) ? '1' : '10';
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
        'Imported Collection',
        $image
    ]);
}

fclose($csvFile);
exit;
?>