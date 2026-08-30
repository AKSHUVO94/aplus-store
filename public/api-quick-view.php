<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid product'));
    exit;
}

$p = Database::fetch(
    "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id=? AND p.status='active'",
    array($id)
);
if (!$p) {
    echo json_encode(array('ok' => false, 'error' => 'Product not found'));
    exit;
}

$gallery = array();
try {
    $rows = ProductImage::forProduct($id);
    foreach ($rows as $g) {
        $url = ProductImage::url($g['image_path']);
        if ($url) $gallery[] = $url;
    }
} catch (Exception $e) {}

$thumb = ProductImage::productThumb($p);
if ($thumb && !in_array($thumb, $gallery, true)) {
    array_unshift($gallery, $thumb);
}
if (empty($gallery) && $thumb) {
    $gallery[] = $thumb;
}

$sizes = array_values(array_filter(array_map('trim', explode(',', isset($p['sizes']) ? $p['sizes'] : ''))));
$colors = array_values(array_filter(array_map('trim', explode(',', isset($p['colors']) ? $p['colors'] : ''))));

$avg = 0;
$count = 0;
try {
    $rev = Database::fetch(
        "SELECT COUNT(*) AS c, COALESCE(AVG(rating),0) AS a
         FROM product_reviews WHERE product_id=? AND status='approved'",
        array($id)
    );
    if ($rev) {
        $count = (int)$rev['c'];
        $avg = $count > 0 ? round((float)$rev['a'], 1) : 0;
    }
} catch (Exception $e) {}

$price = productPrice($p);
$old = hasSale($p) ? (float)$p['price'] : null;

echo json_encode(array(
    'ok' => true,
    'product' => array(
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'slug' => $p['slug'],
        'sku' => isset($p['sku']) ? $p['sku'] : '',
        'cat' => isset($p['cat_name']) ? $p['cat_name'] : '',
        'short' => isset($p['short_description']) ? $p['short_description'] : '',
        'description' => isset($p['description']) ? $p['description'] : '',
        'material' => isset($p['material']) ? $p['material'] : '',
        'price' => $price,
        'price_fmt' => money($price),
        'old_price' => $old,
        'old_price_fmt' => $old !== null ? money($old) : null,
        'stock' => (int)$p['stock'],
        'sizes' => $sizes,
        'colors' => $colors,
        'images' => $gallery,
        'url' => '/index.php?route=product&slug=' . rawurlencode($p['slug']),
        'rating' => $avg,
        'reviews' => $count,
        'is_new' => !empty($p['is_new']),
        'on_sale' => hasSale($p),
    ),
));
