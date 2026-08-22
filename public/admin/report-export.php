<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();

$type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$from = isset($_GET['from']) ? trim($_GET['from']) : date('Y-m-01');
$to = isset($_GET['to']) ? trim($_GET['to']) : date('Y-m-d');
$payMethod = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
$fromDt = $from . ' 00:00:00';
$toDt = $to . ' 23:59:59';
$range = $from . ' to ' . $to;
$meta = array('range' => $range);

$allowedPay = array('cod', 'bkash', 'nagad', 'rocket', 'bank', 'visa', 'mastercard', 'card');
if ($payMethod !== '' && !in_array($payMethod, $allowedPay, true)) {
    $payMethod = '';
}

$headers = array();
$rows = array();
$title = 'Report';
$fileBase = 'report';

switch ($type) {
    case 'stock':
        $title = 'Stock Report';
        $fileBase = 'stock_report';
        $headers = array('ID', 'Product', 'SKU', 'Category', 'Stock', 'Price', 'Sale Price', 'Status');
        $list = Database::fetchAll(
            "SELECT p.id, p.name, p.sku, p.stock, p.price, p.sale_price, p.status, c.name AS cat_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.stock ASC, p.name ASC"
        );
        foreach ($list as $r) {
            $rows[] = array(
                $r['id'],
                $r['name'],
                $r['sku'],
                $r['cat_name'],
                (int)$r['stock'],
                $r['price'],
                $r['sale_price'],
                $r['status'],
            );
        }
        break;

    case 'products':
        $title = 'All Products';
        $fileBase = 'products';
        $headers = array('ID', 'Name', 'SKU', 'Category', 'Price', 'Sale', 'Stock', 'Featured', 'New', 'Status', 'Gender');
        $list = Database::fetchAll(
            "SELECT p.*, c.name AS cat_name FROM products p
             LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC"
        );
        foreach ($list as $r) {
            $rows[] = array(
                $r['id'],
                $r['name'],
                $r['sku'],
                $r['cat_name'],
                $r['price'],
                $r['sale_price'],
                (int)$r['stock'],
                $r['is_featured'] ? 'Yes' : 'No',
                $r['is_new'] ? 'Yes' : 'No',
                $r['status'],
                $r['gender'],
            );
        }
        break;

    case 'orders':
        $title = 'Orders Report (' . $range . ')';
        $fileBase = 'orders';
        $headers = array('Order #', 'Date', 'Customer', 'Email', 'Phone', 'City', 'Status', 'Payment Method', 'Payment Status', 'Subtotal', 'Shipping', 'Total', 'TrxID');
        $sql = "SELECT * FROM orders WHERE created_at BETWEEN ? AND ?";
        $params = array($fromDt, $toDt);
        if ($payMethod !== '') {
            $sql .= " AND payment_method = ?";
            $params[] = $payMethod;
            $title .= ' — ' . ReportExport::payLabel($payMethod);
        }
        $sql .= " ORDER BY created_at DESC";
        $list = Database::fetchAll($sql, $params);
        foreach ($list as $r) {
            $rows[] = array(
                $r['order_number'],
                $r['created_at'],
                $r['customer_name'],
                $r['customer_email'],
                $r['customer_phone'],
                $r['shipping_city'],
                $r['status'],
                ReportExport::payLabel($r['payment_method']),
                $r['payment_status'],
                $r['subtotal'],
                $r['shipping_cost'],
                $r['total'],
                isset($r['transaction_id']) ? $r['transaction_id'] : '',
            );
        }
        break;

    case 'order_items':
        $title = 'Order Items Report (' . $range . ')';
        $fileBase = 'order_items';
        $headers = array('Order #', 'Date', 'Product', 'SKU', 'Size', 'Color', 'Price', 'Qty', 'Line Total', 'Order Status', 'Payment');
        $sql = "SELECT o.order_number, o.created_at, o.status, o.payment_method, oi.*
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE o.created_at BETWEEN ? AND ?";
        $params = array($fromDt, $toDt);
        if ($payMethod !== '') {
            $sql .= " AND o.payment_method = ?";
            $params[] = $payMethod;
        }
        $sql .= " ORDER BY o.created_at DESC, oi.id ASC";
        $list = Database::fetchAll($sql, $params);
        foreach ($list as $r) {
            $rows[] = array(
                $r['order_number'],
                $r['created_at'],
                $r['product_name'],
                $r['product_sku'],
                $r['size'],
                $r['color'],
                $r['price'],
                $r['quantity'],
                $r['total'],
                $r['status'],
                ReportExport::payLabel($r['payment_method']),
            );
        }
        break;

    case 'sales':
        $title = 'Date-wise Sales (' . $range . ')';
        $fileBase = 'sales_daily';
        $headers = array('Date', 'Orders', 'Revenue', 'Delivered', 'Cancelled');
        $list = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS orders_count,
                    COALESCE(SUM(total),0) AS revenue,
                    COALESCE(SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END),0) AS delivered,
                    COALESCE(SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END),0) AS cancelled
             FROM orders WHERE created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at) ORDER BY d ASC",
            array($fromDt, $toDt)
        );
        foreach ($list as $r) {
            $rows[] = array($r['d'], $r['orders_count'], $r['revenue'], $r['delivered'], $r['cancelled']);
        }
        break;

    case 'payments':
        $title = 'Payments Report (' . $range . ')';
        $fileBase = 'payments';
        if ($payMethod !== '') {
            $title .= ' — ' . ReportExport::payLabel($payMethod);
            $fileBase .= '_' . $payMethod;
            $headers = array('Date', 'Order #', 'Customer', 'Method', 'Payment Status', 'Total', 'TrxID');
            $list = Database::fetchAll(
                "SELECT * FROM orders WHERE created_at BETWEEN ? AND ? AND payment_method = ? ORDER BY created_at DESC",
                array($fromDt, $toDt, $payMethod)
            );
            foreach ($list as $r) {
                $rows[] = array(
                    $r['created_at'],
                    $r['order_number'],
                    $r['customer_name'],
                    ReportExport::payLabel($r['payment_method']),
                    $r['payment_status'],
                    $r['total'],
                    isset($r['transaction_id']) ? $r['transaction_id'] : '',
                );
            }
        } else {
            $headers = array('Method', 'Orders', 'Total Amount', 'Paid Amount', 'Pending Orders');
            $list = Database::fetchAll(
                "SELECT payment_method, COUNT(*) AS cnt,
                        COALESCE(SUM(total),0) AS amount,
                        COALESCE(SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END),0) AS paid_amount,
                        COALESCE(SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END),0) AS pending_cnt
                 FROM orders WHERE created_at BETWEEN ? AND ?
                 GROUP BY payment_method ORDER BY amount DESC",
                array($fromDt, $toDt)
            );
            foreach ($list as $r) {
                $rows[] = array(
                    ReportExport::payLabel($r['payment_method']),
                    $r['cnt'],
                    $r['amount'],
                    $r['paid_amount'],
                    $r['pending_cnt'],
                );
            }
        }
        break;

    case 'payments_daily':
        $title = 'Date-wise Payments by Method (' . $range . ')';
        $fileBase = 'payments_daily';
        $headers = array('Date', 'Method', 'Orders', 'Amount');
        $sql = "SELECT DATE(created_at) AS d, payment_method, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
                FROM orders WHERE created_at BETWEEN ? AND ?";
        $params = array($fromDt, $toDt);
        if ($payMethod !== '') {
            $sql .= " AND payment_method = ?";
            $params[] = $payMethod;
            $title .= ' — ' . ReportExport::payLabel($payMethod);
        }
        $sql .= " GROUP BY DATE(created_at), payment_method ORDER BY d ASC, payment_method ASC";
        $list = Database::fetchAll($sql, $params);
        foreach ($list as $r) {
            $rows[] = array($r['d'], ReportExport::payLabel($r['payment_method']), $r['cnt'], $r['amount']);
        }
        break;

    case 'orders_status':
        $title = 'Orders by Status (' . $range . ')';
        $fileBase = 'orders_status';
        $headers = array('Status', 'Count', 'Amount');
        $list = Database::fetchAll(
            "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total),0) AS amount
             FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status ORDER BY cnt DESC",
            array($fromDt, $toDt)
        );
        foreach ($list as $r) {
            $rows[] = array(ucfirst($r['status']), $r['cnt'], $r['amount']);
        }
        break;

    case 'top_products':
        $title = 'Top Products (' . $range . ')';
        $fileBase = 'top_products';
        $headers = array('Product', 'Qty Sold', 'Revenue');
        $list = Database::fetchAll(
            "SELECT oi.product_name, SUM(oi.quantity) AS qty_sold, COALESCE(SUM(oi.total),0) AS revenue
             FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.created_at BETWEEN ? AND ? AND o.status != 'cancelled'
             GROUP BY oi.product_id, oi.product_name ORDER BY qty_sold DESC LIMIT 50",
            array($fromDt, $toDt)
        );
        foreach ($list as $r) {
            $rows[] = array($r['product_name'], $r['qty_sold'], $r['revenue']);
        }
        break;

    default: // overview summary lines
        $title = 'Overview Summary (' . $range . ')';
        $fileBase = 'overview';
        $headers = array('Metric', 'Value');
        $s = Database::fetch(
            "SELECT COUNT(*) AS total_orders, COALESCE(SUM(total),0) AS total_revenue,
                    COALESCE(SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END),0) AS cancelled_orders,
                    COALESCE(SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END),0) AS delivered_orders,
                    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total ELSE 0 END),0) AS paid_revenue,
                    COALESCE(SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END),0) AS pending_payments
             FROM orders WHERE created_at BETWEEN ? AND ?",
            array($fromDt, $toDt)
        );
        $rows = array(
            array('Total orders', $s['total_orders']),
            array('Total revenue', $s['total_revenue']),
            array('Paid revenue', $s['paid_revenue']),
            array('Delivered orders', $s['delivered_orders']),
            array('Cancelled orders', $s['cancelled_orders']),
            array('Pending payments', $s['pending_payments']),
        );
        break;
}

ReportExport::send($format, $fileBase, $title, $headers, $rows, $meta);
