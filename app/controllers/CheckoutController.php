<?php
/**
 * AK Checkout — create order + items
 */
if (empty(Cart::items())) {
    flash('error', 'Your cart is empty.');
    redirect('/cart.php');
}

// CSRF — cannot place order via forged request without token
$token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
if (!csrf_verify($token)) {
    flash('error', 'Security check failed. Please refresh and try again.');
    redirect('/checkout.php');
}

// Re-validate cart lines (size/color/stock) — inspect cannot skip
$cartErrors = Cart::validateForCheckout();
if (!empty($cartErrors)) {
    flash('error', implode(' ', $cartErrors));
    redirect('/cart.php');
}

$name    = trim(isset($_POST['name']) ? $_POST['name'] : '');
$email   = trim(isset($_POST['email']) ? $_POST['email'] : '');
$phone   = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
$address = trim(isset($_POST['address']) ? $_POST['address'] : '');
$city    = trim(isset($_POST['city']) ? $_POST['city'] : 'Dhaka');
$payment = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cod';
$notes   = trim(isset($_POST['notes']) ? $_POST['notes'] : '');
$trxId   = trim(isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '');

if ($trxId !== '') {
    $notes = trim($notes . "\nTrxID: " . $trxId);
}

$_SESSION['old'] = array(
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'city' => $city,
    'payment' => $payment,
    'notes' => $notes,
);

if ($name === '' || $email === '' || $phone === '' || $address === '') {
    flash('error', 'Please fill all required fields.');
    redirect('/checkout.php');
}

// Only methods enabled in Admin Settings (cannot force disabled method via inspect)
$enabledPay = array();
if (setting('pay_cod_enabled', '1') === '1') $enabledPay[] = 'cod';
if (setting('pay_bkash_enabled', '0') === '1') $enabledPay[] = 'bkash';
if (setting('pay_nagad_enabled', '0') === '1') $enabledPay[] = 'nagad';
if (setting('pay_rocket_enabled', '0') === '1') $enabledPay[] = 'rocket';
if (setting('pay_bank_enabled', '0') === '1') $enabledPay[] = 'bank';
if (setting('pay_card_enabled', '0') === '1') $enabledPay[] = 'card';
if (setting('pay_visa_enabled', '0') === '1') $enabledPay[] = 'visa';
if (setting('pay_mastercard_enabled', '0') === '1') $enabledPay[] = 'mastercard';
if (empty($enabledPay)) {
    $enabledPay = array('cod');
}
if (!in_array($payment, $enabledPay, true)) {
    flash('error', 'Invalid or disabled payment method.');
    redirect('/checkout.php');
}


// Online payments require TrxID + screenshot
$onlinePay = array('bkash', 'nagad', 'rocket', 'bank', 'visa', 'mastercard');
$transactionId = trim(isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '');
$paymentProof = null;

if (in_array($payment, $onlinePay, true)) {
    if ($transactionId === '') {
        flash('error', 'Transaction ID is required for ' . strtoupper($payment) . ' payment.');
        redirect('/checkout.php');
    }
    if (empty($_FILES['payment_proof']['name']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Please upload payment screenshot for ' . strtoupper($payment) . '.');
        redirect('/checkout.php');
    }
    $file = $_FILES['payment_proof'];
    if ($file['size'] > 3 * 1024 * 1024) {
        flash('error', 'Screenshot must be under 3 MB.');
        redirect('/checkout.php');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMime = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/jpg' => 'jpg',
    );
    if (!isset($allowedMime[$mime])) {
        flash('error', 'Invalid screenshot type. Use JPG, PNG or WebP.');
        redirect('/checkout.php');
    }
    $dir = dirname(dirname(__DIR__)) . '/public/uploads/payments';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fname = 'pay_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fname)) {
        flash('error', 'Could not save payment screenshot.');
        redirect('/checkout.php');
    }
    $paymentProof = 'uploads/payments/' . $fname;
}


$orderNumber = 'AK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
$subtotal = Cart::subtotal();
$shipping = Cart::shipping($city);
$discount = class_exists('Coupon') ? Coupon::discount() : 0;
$couponCur = class_exists('Coupon') ? Coupon::current() : null;
$couponCode = $couponCur ? $couponCur['code'] : null;
$total = max(0, $subtotal + $shipping - $discount);

// Customer profile
$customerId = null;
try {
    $customerId = Customer::upsert(array(
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'city' => $city,
    ), Auth::check() ? Auth::id() : null);
} catch (Exception $e) {
    $customerId = null;
}

// Build order row
$orderData = array(
    'order_number'     => $orderNumber,
    'user_id'          => Auth::check() ? Auth::id() : null,
    'customer_name'    => $name,
    'customer_email'   => $email,
    'customer_phone'   => $phone,
    'shipping_address' => $address,
    'shipping_city'    => $city,
    'shipping_country' => 'Bangladesh',
    'subtotal'         => $subtotal,
    'shipping_cost'    => $shipping,
    'discount'         => $discount,
    'total'            => $total,
    'payment_method'   => $payment,
    'payment_status'   => 'pending',
    'status'           => 'pending',
    'notes'            => ($notes !== '' ? $notes : null),
);

if ($transactionId !== '') {
    $orderData['transaction_id'] = $transactionId;
}
if ($paymentProof) {
    $orderData['payment_proof'] = $paymentProof;
}

// customer_id only if column exists / value present
if ($customerId) {
    $orderData['customer_id'] = $customerId;
}

try {
    $orderId = Database::insert('orders', $orderData);
} catch (Exception $e) {
    // Fallback: missing columns or enum value — still create order
    $fallback = $orderData;
    unset($fallback['transaction_id'], $fallback['payment_proof'], $fallback['customer_id']);
    if (!in_array($payment, array('cod', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'visa', 'mastercard'), true)) {
        $fallback['payment_method'] = 'bank';
    }
    $extraNotes = array();
    if (!empty($transactionId)) {
        $extraNotes[] = 'TrxID: ' . $transactionId;
    }
    if ($paymentProof) {
        $extraNotes[] = 'Payment proof file: ' . $paymentProof;
    }
    if (!empty($extraNotes)) {
        $fallback['notes'] = trim(
            (isset($fallback['notes']) && $fallback['notes'] ? $fallback['notes'] . "
" : '')
            . implode(' | ', $extraNotes)
        );
    }
    try {
        $orderId = Database::insert('orders', $fallback);
    } catch (Exception $e2) {
        flash('error', 'Order failed: ' . $e2->getMessage());
        redirect('/checkout.php');
    }
}

// Fallback if lastInsertId is 0
if (empty($orderId)) {
    $row = Database::fetch("SELECT id FROM orders WHERE order_number = ?", array($orderNumber));
    $orderId = $row ? (int)$row['id'] : 0;
}

if (empty($orderId)) {
    flash('error', 'Could not create order. Please try again.');
    redirect('/checkout.php');
}

// Always try to attach TrxID + payment proof after insert (survives missing-column fallback)
if ($transactionId !== '' || $paymentProof) {
    try {
        $upd = array();
        if ($transactionId !== '') {
            $upd['transaction_id'] = $transactionId;
        }
        if ($paymentProof) {
            $upd['payment_proof'] = $paymentProof;
        }
        if (!empty($upd)) {
            Database::update('orders', $upd, 'id=?', array($orderId));
        }
    } catch (Exception $e) {
        // Columns may not exist — keep proof path in notes
        if ($paymentProof) {
            try {
                $row = Database::fetch("SELECT notes FROM orders WHERE id=?", array($orderId));
                $notesNow = ($row && !empty($row['notes'])) ? $row['notes'] . "
" : '';
                Database::update(
                    'orders',
                    array('notes' => $notesNow . 'Payment proof: ' . $paymentProof . ($transactionId ? ' | TrxID: ' . $transactionId : '')),
                    'id=?',
                    array($orderId)
                );
            } catch (Exception $e3) {
            }
        }
    }
}

// Order items
foreach (Cart::items() as $item) {
    $imgPath = null;
    // 1) Image chosen at add-to-cart (color variant)
    if (!empty($item['image'])) {
        $imgPath = $item['image'];
        if (strpos($imgPath, 'http://') === 0 || strpos($imgPath, 'https://') === 0) {
            // keep full URL or strip host later
            $imgPath = preg_replace('#^https?://[^/]+/#', '', $imgPath);
        }
        $imgPath = ltrim($imgPath, '/');
    }
    // 2) Match by color/size
    if (($imgPath === null || $imgPath === '') && !empty($item['product_id'])) {
        try {
            $pRow = Database::fetch("SELECT * FROM products WHERE id = ?", array($item['product_id']));
            if ($pRow) {
                $color = isset($item['color']) ? $item['color'] : '';
                $size = isset($item['size']) ? $item['size'] : '';
                $imgPath = ProductImage::thumbForVariant($pRow, $color, $size);
                if ($imgPath && strpos($imgPath, '/') === 0) {
                    $imgPath = ltrim($imgPath, '/');
                }
            }
        } catch (Exception $e) {}
    }
    $itemData = array(
        'order_id'     => (int)$orderId,
        'product_id'   => isset($item['product_id']) ? $item['product_id'] : null,
        'product_name' => $item['name'],
        'product_sku'  => isset($item['sku']) ? $item['sku'] : null,
        'size'         => !empty($item['size']) ? $item['size'] : null,
        'color'        => !empty($item['color']) ? $item['color'] : null,
        'price'        => $item['price'],
        'quantity'     => $item['qty'],
        'total'        => $item['price'] * $item['qty'],
    );
    if ($imgPath) {
        $itemData['product_image'] = $imgPath;
    }
    try {
        Database::insert('order_items', $itemData);
    } catch (Exception $e) {
        // column may not exist yet — insert without image
        unset($itemData['product_image']);
        Database::insert('order_items', $itemData);
    }

    if (!empty($item['product_id'])) {
        Database::query(
            "UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?",
            array($item['qty'], $item['product_id'])
        );
    }
}

if ($couponCode) {
    try {
        Coupon::markUsed($couponCode);
        // store code on order if column exists
        try {
            Database::update('orders', array('coupon_code' => $couponCode), 'id=?', array($orderId));
        } catch (Exception $e) {}
    } catch (Exception $e) {}
    Coupon::remove();
}
Cart::clear();

if ($customerId) {
    try {
        Customer::refreshStats($customerId);
    } catch (Exception $e) {
    }
}

// Email confirmation to customer
try {
    $mailOrder = Database::fetch("SELECT * FROM orders WHERE id = ?", array($orderId));
    $mailItems = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", array($orderId));
    if ($mailOrder && $mailItems) {
        Mailer::sendOrderConfirmation($mailOrder, $mailItems);
    }
} catch (Exception $e) {
    // never block order success if mail fails
}

unset($_SESSION['old']);
$_SESSION['last_order'] = $orderNumber;
redirect('/order-success.php');