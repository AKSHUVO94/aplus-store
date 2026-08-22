<?php
/**
 * Order mail — PHP mail() or SMTP (Gmail / hosting)
 */
class Mailer
{
    public static function send($to, $subject, $htmlBody, $textBody = '')
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromEmail = setting('site_email', 'noreply@ak-store.local');
        $fromName = setting('site_name', 'AK');
        if ($textBody === '') {
            $textBody = strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), "\n", $htmlBody));
        }

        $useSmtp = setting('smtp_enabled', '0') === '1';
        if ($useSmtp) {
            return self::sendSmtp($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName);
        }
        return self::sendPhpMail($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName);
    }

    private static function sendPhpMail($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName)
    {
        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $boundary = 'bnd_' . md5(uniqid((string) mt_rand(), true));
        $headers = array();
        $headers[] = 'From: ' . self::encodeAddress($fromName, $fromEmail);
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'X-Mailer: AK-Store';

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--";

        try {
            return @mail($to, $subjectEnc, $body, implode("\r\n", $headers));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function sendSmtp($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName)
    {
        $host = setting('smtp_host', 'smtp.gmail.com');
        $port = (int) setting('smtp_port', '587');
        $user = setting('smtp_user', '');
        $pass = setting('smtp_pass', '');
        $secure = strtolower(setting('smtp_secure', 'tls')); // tls | ssl | none

        if ($user === '' || $pass === '') {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
        $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            return false;
        }
        stream_set_timeout($fp, 30);

        try {
            self::smtpRead($fp);
            self::smtpCmd($fp, 'EHLO localhost');
            if ($secure === 'tls') {
                self::smtpCmd($fp, 'STARTTLS');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($fp);
                    return false;
                }
                self::smtpCmd($fp, 'EHLO localhost');
            }
            self::smtpCmd($fp, 'AUTH LOGIN');
            self::smtpCmd($fp, base64_encode($user));
            self::smtpCmd($fp, base64_encode($pass));
            self::smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>');
            self::smtpCmd($fp, 'RCPT TO:<' . $to . '>');
            self::smtpCmd($fp, 'DATA');

            $boundary = 'bnd_' . md5(uniqid((string) mt_rand(), true));
            $msg = 'From: ' . self::encodeAddress($fromName, $fromEmail) . "\r\n";
            $msg .= 'To: <' . $to . ">\r\n";
            $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
            $msg .= "\r\n";
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode($textBody)) . "\r\n";
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $msg .= "--{$boundary}--\r\n";
            $msg .= ".\r\n";

            fwrite($fp, $msg);
            self::smtpRead($fp);
            self::smtpCmd($fp, 'QUIT');
            fclose($fp);
            return true;
        } catch (Exception $e) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            return false;
        }
    }

    private static function smtpRead($fp)
    {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    private static function smtpCmd($fp, $cmd)
    {
        fwrite($fp, $cmd . "\r\n");
        $resp = self::smtpRead($fp);
        $code = (int) substr($resp, 0, 3);
        if ($code >= 400) {
            throw new Exception('SMTP error: ' . trim($resp));
        }
        return $resp;
    }

    private static function encodeAddress($name, $email)
    {
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    public static function sendOrderConfirmation($order, $items)
    {
        if (setting('mail_order_confirmation', '1') !== '1') {
            return false;
        }

        $site = setting('site_name', 'AK');
        $email = $order['customer_email'];
        $subject = 'Order Confirmed — ' . $order['order_number'] . ' | ' . $site;

        $payLabels = array(
            'cod' => 'Cash on Delivery',
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank Transfer',
            'visa' => 'Visa Card',
            'mastercard' => 'Master Card',
            'card' => 'Card',
        );
        $pm = strtolower($order['payment_method']);
        $payLabel = isset($payLabels[$pm]) ? $payLabels[$pm] : strtoupper($order['payment_method']);

        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #e5e5e5">' . htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8')
                . (!empty($it['size']) ? ' <span style="color:#71717a">(' . htmlspecialchars($it['size'], ENT_QUOTES, 'UTF-8') . ')</span>' : '')
                . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #e5e5e5;text-align:center">' . (int) $it['quantity'] . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #e5e5e5;text-align:right">' . htmlspecialchars(money($it['total']), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        $baseUrl = self::baseUrl();
        $trackUrl = $baseUrl . '/track-order.php?order=' . urlencode($order['order_number']);
        $invoiceUrl = $baseUrl . '/invoice.php?order=' . urlencode($order['order_number'])
            . '&email=' . urlencode($order['customer_email']);

        $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f4f5;font-family:system-ui,Segoe UI,Roboto,sans-serif">'
            . '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e5e5">'
            . '<div style="background:#18181b;color:#fff;padding:24px 28px">'
            . '<div style="font-size:22px;font-weight:800">' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="opacity:.8;font-size:13px;margin-top:4px">Order Confirmation</div></div>'
            . '<div style="padding:28px">'
            . '<p style="margin:0 0 12px;font-size:16px">Hi <strong>' . htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p style="margin:0 0 20px;color:#52525b;font-size:14px;line-height:1.6">Thank you for your order. We have received it and will process it shortly.</p>'
            . '<div style="background:#fafafa;border:1px solid #e5e5e5;border-radius:10px;padding:16px;margin-bottom:20px">'
            . '<div style="font-size:12px;color:#71717a;text-transform:uppercase">Order Number</div>'
            . '<div style="font-size:20px;font-weight:800;color:#e11d48;margin-top:4px">' . htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') . '</div></div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:16px">'
            . '<thead><tr style="background:#fafafa"><th style="text-align:left;padding:10px 8px">Product</th><th style="text-align:center;padding:10px 8px">Qty</th><th style="text-align:right;padding:10px 8px">Total</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<div style="text-align:right;font-size:14px;margin-bottom:8px;color:#52525b">Subtotal: ' . htmlspecialchars(money($order['subtotal']), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="text-align:right;font-size:14px;margin-bottom:8px;color:#52525b">Shipping: ' . htmlspecialchars(money($order['shipping_cost']), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="text-align:right;font-size:18px;font-weight:800;margin-bottom:20px">Total: ' . htmlspecialchars(money($order['total']), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="border:2px solid #e11d48;border-radius:10px;padding:14px 16px;margin-bottom:24px;background:#fff1f2">'
            . '<div style="font-size:11px;font-weight:700;color:#9f1239;text-transform:uppercase">Payment Method</div>'
            . '<div style="font-size:16px;font-weight:800;color:#e11d48;margin-top:4px">' . htmlspecialchars($payLabel, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="font-size:13px;margin-top:6px;color:#52525b">Status: <strong>' . htmlspecialchars(ucfirst($order['payment_status']), ENT_QUOTES, 'UTF-8') . '</strong></div>'
            . (!empty($order['transaction_id']) ? '<div style="font-size:13px;margin-top:4px;color:#52525b">TrxID: <strong>' . htmlspecialchars($order['transaction_id'], ENT_QUOTES, 'UTF-8') . '</strong></div>' : '')
            . '</div>'
            . '<div style="margin-bottom:20px;font-size:13px;color:#52525b;line-height:1.5"><strong>Ship to:</strong><br>'
            . htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') . '<br>'
            . nl2br(htmlspecialchars($order['shipping_address'], ENT_QUOTES, 'UTF-8')) . '<br>'
            . htmlspecialchars($order['shipping_city'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#e11d48;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;margin:4px">View Invoice</a> '
            . '<a href="' . htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#fff;color:#18181b;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;margin:4px;border:1px solid #d4d4d8">Track Order</a>'
            . '</div>'
            . '<p style="margin:0;font-size:12px;color:#a1a1aa;text-align:center">You received this because you ordered at ' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '</div></div></body></html>';

        return self::send($email, $subject, $html);
    }

    private static function baseUrl()
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host;
    }
}