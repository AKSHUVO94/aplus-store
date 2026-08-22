<?php
declare(strict_types=1);

class ProductImage
{
    const UPLOAD_DIR = 'uploads/products';
    const MAX_SIZE = 5242880; // 5MB
    const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function uploadDir()
    {
        $root = dirname(__DIR__, 2) . '/public/' . self::UPLOAD_DIR;
        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
        }
        return $root;
    }

    public static function forProduct($productId)
    {
        try {
            return Database::fetchAll(
                "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC",
                [(int) $productId]
            );
        } catch (Exception $e) {
            return [];
        }
    }

    public static function primary($productId)
    {
        try {
            $img = Database::fetch(
                "SELECT * FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1",
                [(int) $productId]
            );
            if (!$img) {
                $img = Database::fetch(
                    "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1",
                    [(int) $productId]
                );
            }
            return $img;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function url($path)
    {
        if (!$path) {
            return null;
        }
        $path = str_replace('\\', '/', $path);
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        // Always web path from document root (public/)
        return '/' . ltrim($path, '/');
    }

    /**
     * Get displayable thumbnail URL for a product row
     */
    public static function productThumb($product)
    {
        if (!is_array($product)) {
            return null;
        }

        // 1) products.image column
        if (!empty($product['image'])) {
            $u = self::url($product['image']);
            if ($u) {
                return $u;
            }
        }

        // 2) subquery alias from admin list
        if (!empty($product['thumb'])) {
            return self::url($product['thumb']);
        }

        // 3) product_images table
        if (!empty($product['id'])) {
            $primary = self::primary($product['id']);
            if ($primary && !empty($primary['image_path'])) {
                return self::url($primary['image_path']);
            }
        }

        return null;
    }


    /**
     * Image for selected color (and optional size) — cart / variants
     */
    public static function thumbForVariant($product, $color = '', $size = '')
    {
        if (!is_array($product) || empty($product['id'])) {
            return self::productThumb($product);
        }
        $productId = (int)$product['id'];
        $color = is_string($color) ? trim($color) : '';
        $size = is_string($size) ? trim($size) : '';
        $images = self::forProduct($productId);

        if ($color !== '' && $images) {
            $colorLower = strtolower($color);
            // 1) Explicit color column if present
            foreach ($images as $img) {
                if (!empty($img['color']) && strtolower(trim($img['color'])) === $colorLower) {
                    return self::url($img['image_path']);
                }
            }
            // 2) Match color name inside file path / alt
            foreach ($images as $img) {
                $hay = strtolower(($img['image_path'] ?? '') . ' ' . ($img['alt_text'] ?? ''));
                if ($hay !== '' && strpos($hay, $colorLower) !== false) {
                    return self::url($img['image_path']);
                }
            }
        }

        if ($size !== '' && $images) {
            $sizeLower = strtolower($size);
            foreach ($images as $img) {
                $hay = strtolower(($img['image_path'] ?? '') . ' ' . ($img['alt_text'] ?? ''));
                if (strpos($hay, $sizeLower) !== false) {
                    return self::url($img['image_path']);
                }
            }
        }

        return self::productThumb($product);
    }

    public static function upload($productId, $files)
    {
        $uploaded = [];
        $errors = [];
        $dir = self::uploadDir();
        $productId = (int) $productId;

        if ($productId < 1) {
            return ['ids' => [], 'errors' => ['Invalid product ID']];
        }

        // Normalize to array of files
        if (!isset($files['name'])) {
            return ['ids' => [], 'errors' => ['No files received']];
        }

        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']],
            ];
        }

        $existing = self::forProduct($productId);
        $hasPrimary = false;
        foreach ($existing as $e) {
            if (!empty($e['is_primary'])) {
                $hasPrimary = true;
                break;
            }
        }

        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $err = isset($files['error'][$i]) ? (int) $files['error'][$i] : UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = self::uploadErrorMessage($err);
                continue;
            }

            $size = (int) $files['size'][$i];
            if ($size <= 0 || $size > self::MAX_SIZE) {
                $errors[] = 'File too large (max 5MB): ' . $files['name'][$i];
                continue;
            }

            $origName = $files['name'][$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            // Normalize jpeg
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                $errors[] = 'Invalid type: ' . $origName . ' (allowed: jpg, png, webp, gif)';
                continue;
            }

            // Extra safety: check real image
            $tmp = $files['tmp_name'][$i];
            if (!is_uploaded_file($tmp)) {
                $errors[] = 'Invalid upload: ' . $origName;
                continue;
            }
            $imgInfo = @getimagesize($tmp);
            if ($imgInfo === false) {
                $errors[] = 'Not a valid image: ' . $origName;
                continue;
            }

            $filename = 'p' . $productId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = 'Failed to save: ' . $origName;
                continue;
            }
            @chmod($dest, 0644);

            $relPath = self::UPLOAD_DIR . '/' . $filename;
            $isPrimary = (!$hasPrimary && empty($uploaded)) ? 1 : 0;
            if ($isPrimary) {
                $hasPrimary = true;
            }

            try {
                $id = Database::insert('product_images', [
                    'product_id' => $productId,
                    'image_path' => $relPath,
                    'is_primary' => $isPrimary,
                    'sort_order' => count($existing) + count($uploaded),
                    'alt_text' => null,
                ]);
                $uploaded[] = $id;

                if ($isPrimary) {
                    Database::update('products', ['image' => $relPath], 'id = ?', [$productId]);
                }
            } catch (Exception $e) {
                @unlink($dest);
                $errors[] = 'DB error (did you run migration_product_images.sql?): ' . $e->getMessage();
            }
        }

        return ['ids' => $uploaded, 'errors' => $errors];
    }

    public static function setPrimary($imageId, $productId)
    {
        $imageId = (int) $imageId;
        $productId = (int) $productId;
        Database::query("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$productId]);
        Database::update('product_images', ['is_primary' => 1], 'id = ? AND product_id = ?', [$imageId, $productId]);
        $img = Database::fetch("SELECT image_path FROM product_images WHERE id = ?", [$imageId]);
        if ($img) {
            Database::update('products', ['image' => $img['image_path']], 'id = ?', [$productId]);
        }
        return true;
    }

    public static function delete($imageId, $productId)
    {
        $imageId = (int) $imageId;
        $productId = (int) $productId;
        $img = Database::fetch("SELECT * FROM product_images WHERE id = ? AND product_id = ?", [$imageId, $productId]);
        if (!$img) {
            return false;
        }

        $full = dirname(__DIR__, 2) . '/public/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $img['image_path']);
        if (is_file($full)) {
            @unlink($full);
        }

        Database::delete('product_images', 'id = ?', [$imageId]);

        if (!empty($img['is_primary'])) {
            $next = Database::fetch(
                "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1",
                [$productId]
            );
            if ($next) {
                self::setPrimary($next['id'], $productId);
            } else {
                Database::update('products', ['image' => null], 'id = ?', [$productId]);
            }
        }
        return true;
    }

    private static function uploadErrorMessage($code)
    {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Blocked by extension',
        ];
        return isset($map[$code]) ? $map[$code] : 'Upload error code ' . $code;
    }
}
