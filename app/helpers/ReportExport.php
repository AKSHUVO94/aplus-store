<?php
/**
 * Report export — CSV (Excel), XLS (HTML), PDF (print-ready HTML)
 */
class ReportExport
{
    public static function payLabels()
    {
        return array(
            'cod' => 'Cash on Delivery',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank Transfer',
            'visa' => 'Visa',
            'mastercard' => 'Master Card',
            'card' => 'Card',
        );
    }

    public static function payLabel($method)
    {
        $m = strtolower((string) $method);
        $labels = self::payLabels();
        return isset($labels[$m]) ? $labels[$m] : strtoupper($method);
    }

    public static function filename($prefix, $ext)
    {
        return $prefix . '_' . date('Y-m-d_His') . '.' . $ext;
    }

    /** CSV — opens in Excel */
    public static function csv($filename, $headers, $rows)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    /** Excel-compatible .xls (HTML table) */
    public static function excel($filename, $title, $headers, $rows)
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        echo '<table border="1" cellpadding="6" cellspacing="0">';
        echo '<tr>';
        foreach ($headers as $h) {
            echo '<th style="background:#111;color:#fff">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    /** Print-ready HTML → user saves as PDF (or browser Print to PDF) */
    public static function pdfHtml($title, $headers, $rows, $meta = array())
    {
        // Rich daily detail PDF with product images + payment proof
        if (!empty($meta['daily_detail']) && !empty($meta['pdf_orders'])) {
            self::pdfDailyDetail($title, $meta);
            return;
        }

        $site = setting('site_name', 'AK Store');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:24px;color:#111;font-size:13px}
h1{font-size:20px;margin:0 0 6px}
.meta{color:#666;font-size:12px;margin-bottom:18px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ddd;padding:8px 10px;text-align:left}
th{background:#18181b;color:#fff;font-size:11px;text-transform:uppercase}
tr:nth-child(even){background:#fafafa}
.actions{margin-bottom:16px}
@media print{.actions{display:none} body{margin:0}}
</style></head><body>';
        echo '<div class="actions"><button onclick="window.print()" style="padding:10px 18px;background:#e11d48;color:#fff;border:0;border-radius:8px;font-weight:700;cursor:pointer">Print / Save as PDF</button> ';
        echo '<button onclick="window.close()" style="padding:10px 18px;border:1px solid #ccc;border-radius:8px;cursor:pointer">Close</button></div>';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<div class="meta">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
        if (!empty($meta['range'])) {
            echo ' · ' . htmlspecialchars($meta['range'], ENT_QUOTES, 'UTF-8');
        }
        echo ' · Generated ' . date('Y-m-d H:i') . '</div>';
        echo '<table><thead><tr>';
        foreach ($headers as $h) {
            echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        if (empty($rows)) {
            echo '<tr><td colspan="' . count($headers) . '" style="text-align:center;padding:20px">No data</td></tr>';
        }
        echo '</tbody></table>';
        echo '<script>setTimeout(function(){/* optional auto print */},300);</script>';
        echo '</body></html>';
        exit;
    }

    /**
     * Resolve a web path or relative upload path to a file:// or data URI for PDF embedding.
     * Falls back to absolute web path (works when printing from same origin).
     */
    private static function imageSrcForPdf($webPath)
    {
        if (!$webPath) {
            return '';
        }
        $webPath = str_replace('\\', '/', $webPath);
        $webPath = '/' . ltrim($webPath, '/');
        $publicRoot = dirname(__DIR__, 2) . '/public';
        $file = $publicRoot . $webPath;
        if (is_file($file)) {
            $mime = 'image/jpeg';
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'png') $mime = 'image/png';
            elseif ($ext === 'webp') $mime = 'image/webp';
            elseif ($ext === 'gif') $mime = 'image/gif';
            $data = @file_get_contents($file);
            if ($data !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
        // Fallback: absolute URL if app_url is set
        $base = rtrim((string) config('app_url', ''), '/');
        if ($base !== '') {
            return $base . $webPath;
        }
        return $webPath;
    }

    /** Rich daily sales detail PDF with product images and payment proofs */
    public static function pdfDailyDetail($title, $meta)
    {
        $site = setting('site_name', setting('app_name', 'AK Store'));
        $orders = isset($meta['pdf_orders']) ? $meta['pdf_orders'] : array();
        $sym = setting('currency_symbol', '৳');

        // Group by date
        $byDate = array();
        foreach ($orders as $block) {
            $o = $block['order'];
            $d = date('Y-m-d', strtotime($o['created_at']));
            if (!isset($byDate[$d])) $byDate[$d] = array();
            $byDate[$d][] = $block;
        }
        krsort($byDate);

        $totalOrders = count($orders);
        $totalRev = 0;
        foreach ($orders as $b) {
            $totalRev += (float)$b['order']['total'];
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>
body{font-family:system-ui,Segoe UI,Roboto,sans-serif;margin:20px;color:#111;font-size:12px;line-height:1.4}
h1{font-size:20px;margin:0 0 4px}
h2{font-size:15px;margin:0;color:#18181b}
.meta{color:#666;font-size:11px;margin-bottom:16px}
.summary{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.summary .box{border:1px solid #ddd;border-radius:8px;padding:10px 14px;min-width:100px}
.summary .box .l{font-size:10px;text-transform:uppercase;color:#666;font-weight:700}
.summary .box .v{font-size:16px;font-weight:800;margin-top:2px}
.day{margin-bottom:22px;border:1px solid #ddd;border-radius:10px;overflow:hidden;page-break-inside:avoid}
.day-h{background:#18181b;color:#fff;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.day-h h2{color:#fff;font-size:14px}
.day-h .stats{font-size:11px;opacity:.9}
.order{padding:12px 14px;border-bottom:1px solid #eee;page-break-inside:avoid}
.order:last-child{border-bottom:0}
.order-top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px}
.order-top strong{font-size:13px}
.badge{display:inline-block;padding:2px 7px;border-radius:999px;font-size:9px;font-weight:700;text-transform:uppercase;margin-left:4px}
.b-pending{background:#fef3c7;color:#b45309}
.b-confirmed,.b-processing,.b-shipped{background:#dbeafe;color:#1d4ed8}
.b-delivered{background:#dcfce7;color:#15803d}
.b-cancelled{background:#fee2e2;color:#b91c1c}
.b-paid{background:#dcfce7;color:#15803d}
.b-pay-pending{background:#fef3c7;color:#b45309}
.muted{color:#666;font-size:11px}
.items{width:100%;border-collapse:collapse;margin-top:6px}
.items th{background:#f4f4f5;font-size:10px;text-transform:uppercase;padding:5px 6px;text-align:left;border:1px solid #e4e4e7}
.items td{padding:6px;border:1px solid #e4e4e7;vertical-align:middle}
.items img{width:40px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #ddd}
.proof{max-width:90px;max-height:90px;object-fit:contain;border:1px solid #ddd;border-radius:6px;margin-top:6px}
.actions{margin-bottom:14px}
@media print{.actions{display:none} body{margin:8px} .day{page-break-inside:avoid}}
</style></head><body>';

        echo '<div class="actions"><button onclick="window.print()" style="padding:10px 18px;background:#e11d48;color:#fff;border:0;border-radius:8px;font-weight:700;cursor:pointer">Print / Save as PDF</button> ';
        echo '<button onclick="window.close()" style="padding:10px 18px;border:1px solid #ccc;border-radius:8px;cursor:pointer">Close</button></div>';

        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<div class="meta">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
        if (!empty($meta['range'])) {
            echo ' · ' . htmlspecialchars($meta['range'], ENT_QUOTES, 'UTF-8');
        }
        echo ' · Generated ' . date('Y-m-d H:i') . '</div>';

        echo '<div class="summary">';
        echo '<div class="box"><div class="l">Orders</div><div class="v">' . (int)$totalOrders . '</div></div>';
        echo '<div class="box"><div class="l">Revenue</div><div class="v">' . htmlspecialchars($sym . number_format($totalRev, 0), ENT_QUOTES, 'UTF-8') . '</div></div>';
        echo '<div class="box"><div class="l">Days</div><div class="v">' . count($byDate) . '</div></div>';
        echo '</div>';

        if (empty($byDate)) {
            echo '<p style="text-align:center;padding:30px;color:#666">No orders in this range.</p>';
        }

        foreach ($byDate as $day => $blocks) {
            $dayRev = 0;
            foreach ($blocks as $b) $dayRev += (float)$b['order']['total'];
            echo '<div class="day">';
            echo '<div class="day-h"><h2>' . htmlspecialchars(date('D, M j, Y', strtotime($day)), ENT_QUOTES, 'UTF-8') . '</h2>';
            echo '<div class="stats">' . count($blocks) . ' orders · ' . htmlspecialchars($sym . number_format($dayRev, 0), ENT_QUOTES, 'UTF-8') . '</div></div>';

            foreach ($blocks as $block) {
                $o = $block['order'];
                $trx = $block['trx'];
                $proof = $block['proof'];
                $stClass = 'b-' . preg_replace('/[^a-z]/', '', strtolower($o['status']));
                $psClass = $o['payment_status'] === 'paid' ? 'b-paid' : 'b-pay-pending';

                echo '<div class="order">';
                echo '<div class="order-top">';
                echo '<div><strong>#' . htmlspecialchars($o['order_number'], ENT_QUOTES, 'UTF-8') . '</strong>';
                echo '<span class="badge ' . $stClass . '">' . htmlspecialchars(ucfirst($o['status']), ENT_QUOTES, 'UTF-8') . '</span>';
                echo '<span class="badge ' . $psClass . '">' . htmlspecialchars(ucfirst($o['payment_status']), ENT_QUOTES, 'UTF-8') . '</span>';
                echo '<div class="muted" style="margin-top:3px">' . htmlspecialchars(date('h:i A', strtotime($o['created_at'])), ENT_QUOTES, 'UTF-8');
                echo ' · ' . htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8');
                echo ' · ' . htmlspecialchars($o['customer_phone'] ?: $o['customer_email'], ENT_QUOTES, 'UTF-8');
                echo ' · ' . htmlspecialchars(self::payLabel($o['payment_method']), ENT_QUOTES, 'UTF-8');
                if ($trx !== '') {
                    echo ' · TrxID: <code>' . htmlspecialchars($trx, ENT_QUOTES, 'UTF-8') . '</code>';
                }
                echo '</div>';
                echo '<div class="muted">' . htmlspecialchars($o['shipping_address'], ENT_QUOTES, 'UTF-8');
                if (!empty($o['shipping_city'])) {
                    echo ', ' . htmlspecialchars($o['shipping_city'], ENT_QUOTES, 'UTF-8');
                }
                echo '</div></div>';
                echo '<div style="text-align:right;font-weight:800;font-size:14px">' . htmlspecialchars($sym . number_format((float)$o['total'], 0), ENT_QUOTES, 'UTF-8');
                echo '<div class="muted" style="font-weight:400">Sub ' . htmlspecialchars($sym . number_format((float)$o['subtotal'], 0), ENT_QUOTES, 'UTF-8');
                echo ' + Ship ' . htmlspecialchars($sym . number_format((float)$o['shipping_cost'], 0), ENT_QUOTES, 'UTF-8') . '</div></div>';
                echo '</div>';

                // Items table
                if (!empty($block['items'])) {
                    echo '<table class="items"><thead><tr><th></th><th>Product</th><th>Size</th><th>Color</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead><tbody>';
                    foreach ($block['items'] as $it) {
                        $imgUrl = '';
                        if (!empty($it['product_image'])) {
                            $imgUrl = self::imageSrcForPdf($it['product_image']);
                        } else {
                            // Fallback: look up product primary image
                            $pid = !empty($it['item_product_id']) ? (int)$it['item_product_id'] : (!empty($it['product_id']) ? (int)$it['product_id'] : 0);
                            if ($pid > 0) {
                                try {
                                    $pi = Database::fetch(
                                        "SELECT image_path FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1",
                                        array($pid)
                                    );
                                    if ($pi && !empty($pi['image_path'])) {
                                        $imgUrl = self::imageSrcForPdf($pi['image_path']);
                                    }
                                } catch (Exception $e) {}
                            }
                        }
                        echo '<tr>';
                        echo '<td style="width:48px">';
                        if ($imgUrl) {
                            echo '<img src="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '" alt="">';
                        } else {
                            echo '—';
                        }
                        echo '</td>';
                        echo '<td><strong>' . htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') . '</strong>';
                        if (!empty($it['product_sku'])) {
                            echo '<br><span class="muted">' . htmlspecialchars($it['product_sku'], ENT_QUOTES, 'UTF-8') . '</span>';
                        }
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($it['size'] ?: '—', ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($it['color'] ?: '—', ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . htmlspecialchars($sym . number_format((float)$it['item_price'], 0), ENT_QUOTES, 'UTF-8') . '</td>';
                        echo '<td>' . (int)$it['quantity'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($sym . number_format((float)$it['line_total'], 0), ENT_QUOTES, 'UTF-8') . '</strong></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }

                if ($proof !== '') {
                    $proofSrc = self::imageSrcForPdf($proof);
                    if ($proofSrc) {
                        echo '<div class="muted" style="margin-top:8px;font-weight:700">Payment proof</div>';
                        echo '<img class="proof" src="' . htmlspecialchars($proofSrc, ENT_QUOTES, 'UTF-8') . '" alt="Payment proof">';
                    }
                }

                echo '</div>'; // order
            }
            echo '</div>'; // day
        }

        echo '</body></html>';
        exit;
    }

    public static function send($format, $filenameBase, $title, $headers, $rows, $meta = array())
    {
        $format = strtolower($format);
        if ($format === 'csv') {
            self::csv(self::filename($filenameBase, 'csv'), $headers, $rows);
        } elseif ($format === 'excel' || $format === 'xls') {
            self::excel(self::filename($filenameBase, 'xls'), $title, $headers, $rows);
        } else {
            self::pdfHtml($title, $headers, $rows, $meta);
        }
    }
}