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
