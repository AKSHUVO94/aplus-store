<?php
declare(strict_types=1);

class Cart
{
    public static function items()
    {
        return isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
    }

    public static function count()
    {
        $c = 0;
        foreach (self::items() as $item) {
            $c += (int)$item['qty'];
        }
        return $c;
    }

    /**
     * Server-side safe add — inspect cannot skip required size/color/stock
     * Returns true or error string
     */
    public static function add($productId, $qty = 1, $size = '', $color = '', $selectedImage = '')
    {
        $productId = (int)$productId;
        $qty = max(1, min(99, (int)$qty));
        $size = is_string($size) ? trim($size) : '';
        $color = is_string($color) ? trim($color) : '';

        $product = Database::fetch(
            "SELECT * FROM products WHERE id = ? AND status = 'active'",
            array($productId)
        );
        if (!$product) {
            return 'Product not found or unavailable.';
        }

        $stock = (int)$product['stock'];
        if ($stock < 1) {
            return 'This product is out of stock.';
        }

        $allowedSizes = array_values(array_filter(array_map('trim', explode(',', isset($product['sizes']) ? $product['sizes'] : ''))));
        $allowedColors = array_values(array_filter(array_map('trim', explode(',', isset($product['colors']) ? $product['colors'] : ''))));

        // Require size/color when product defines them (cannot bypass via inspect)
        if (count($allowedSizes) > 0) {
            if ($size === '' || !in_array($size, $allowedSizes, true)) {
                return 'Please select a valid size.';
            }
        } else {
            $size = ''; // ignore forged size
        }

        if (count($allowedColors) > 0) {
            if ($color === '' || !in_array($color, $allowedColors, true)) {
                return 'Please select a valid color.';
            }
        } else {
            $color = '';
        }

        $key = $productId . '_' . $size . '_' . $color;
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = array();
        }

        $existingQty = isset($_SESSION['cart'][$key]) ? (int)$_SESSION['cart'][$key]['qty'] : 0;
        $newQty = $existingQty + $qty;
        if ($newQty > $stock) {
            return 'Not enough stock available.';
        }

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] = $newQty;
        } else {
            $imgUrl = '';
            $selectedImage = is_string($selectedImage) ? trim($selectedImage) : '';
            if ($selectedImage !== '') {
                // Must belong to this product (security)
                $okImg = false;
                $gallery = ProductImage::forProduct($productId);
                foreach ($gallery as $g) {
                    $path = $g['image_path'];
                    $url = ProductImage::url($path);
                    if ($selectedImage === $path || $selectedImage === $url || ltrim($selectedImage, '/') === ltrim($path, '/')) {
                        $imgUrl = $url;
                        $okImg = true;
                        break;
                    }
                }
                if (!$okImg && !empty($product['image'])) {
                    $url = ProductImage::url($product['image']);
                    if ($selectedImage === $product['image'] || $selectedImage === $url) {
                        $imgUrl = $url;
                        $okImg = true;
                    }
                }
            }
            if ($imgUrl === '') {
                $imgUrl = ProductImage::thumbForVariant($product, $color, $size);
            }
            $_SESSION['cart'][$key] = array(
                'product_id' => (int)$product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'sku' => $product['sku'],
                'price' => productPrice($product),
                'original_price' => (float)$product['price'],
                'qty' => $qty,
                'size' => $size,
                'color' => $color,
                'image' => $imgUrl ? $imgUrl : '',
            );
        }
        return true;
    }

    /**
     * Re-validate entire cart before checkout (stock + options still valid)
     */
    public static function validateForCheckout()
    {
        $errors = array();
        $items = self::items();
        if (empty($items)) {
            return array('Cart is empty.');
        }
        foreach ($items as $key => $item) {
            $pid = (int)$item['product_id'];
            $product = Database::fetch(
                "SELECT * FROM products WHERE id = ? AND status = 'active'",
                array($pid)
            );
            if (!$product) {
                $errors[] = $item['name'] . ' is no longer available.';
                continue;
            }
            $stock = (int)$product['stock'];
            if ((int)$item['qty'] > $stock) {
                $errors[] = $item['name'] . ' — only ' . $stock . ' left in stock.';
            }
            $allowedSizes = array_values(array_filter(array_map('trim', explode(',', isset($product['sizes']) ? $product['sizes'] : ''))));
            $allowedColors = array_values(array_filter(array_map('trim', explode(',', isset($product['colors']) ? $product['colors'] : ''))));
            $size = isset($item['size']) ? $item['size'] : '';
            $color = isset($item['color']) ? $item['color'] : '';
            if (count($allowedSizes) > 0 && ($size === '' || !in_array($size, $allowedSizes, true))) {
                $errors[] = $item['name'] . ' — valid size required.';
            }
            if (count($allowedColors) > 0 && ($color === '' || !in_array($color, $allowedColors, true))) {
                $errors[] = $item['name'] . ' — valid color required.';
            }
            // Refresh price from DB (prevent price tampering in session)
            $_SESSION['cart'][$key]['price'] = productPrice($product);
            $_SESSION['cart'][$key]['name'] = $product['name'];
        }
        return $errors;
    }


    /**
     * Change size/color for a cart line (new key)
     */
    public static function changeOptions($oldKey, $size, $color)
    {
        if (!isset($_SESSION['cart'][$oldKey])) {
            return 'Item not found in cart.';
        }
        $item = $_SESSION['cart'][$oldKey];
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        unset($_SESSION['cart'][$oldKey]);
        $result = self::add($pid, $qty, $size, $color);
        if ($result !== true) {
            // restore old line on failure
            $_SESSION['cart'][$oldKey] = $item;
            return $result;
        }
        return true;
    }

    public static function update($key, $qty)
    {
        if (!isset($_SESSION['cart'][$key])) {
            return false;
        }
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$key]);
            return true;
        }
        $pid = (int)$_SESSION['cart'][$key]['product_id'];
        $product = Database::fetch("SELECT stock FROM products WHERE id = ? AND status = 'active'", array($pid));
        if (!$product) {
            unset($_SESSION['cart'][$key]);
            return false;
        }
        if ($qty > (int)$product['stock']) {
            return false;
        }
        $_SESSION['cart'][$key]['qty'] = $qty;
        return true;
    }

    public static function remove($key)
    {
        unset($_SESSION['cart'][$key]);
    }

    public static function clear()
    {
        $_SESSION['cart'] = array();
    }

    public static function subtotal()
    {
        $t = 0;
        foreach (self::items() as $item) {
            $t += $item['price'] * $item['qty'];
        }
        return $t;
    }

    public static function shipping()
    {
        $sub = self::subtotal();
        $freeMin = (float) setting('free_shipping_min', 3000);
        if ($sub >= $freeMin) {
            return 0;
        }
        return (float) setting('shipping_cost', 120);
    }

    public static function total()
    {
        return self::subtotal() + self::shipping();
    }
}