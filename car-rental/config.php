<?php
$host = "127.0.0.1";
$user = "root"; // Default XAMPP/WAMP user
$pass = "";     // Default XAMPP/WAMP password
$dbname = "car_rental_db";

// UPI payment settings for dynamic QR code payments.
// Replace these with your own UPI details to enable UPI QR payments.
$upi_id = "9081020835-2@ybl";      // Your UPI ID, e.g. "yourname@upi"
$upi_payee_name = "CAR Rentals";   // Merchant / payee name shown on the QR code

// Business / GST details shown on invoices.
// Replace with your own GSTIN (GST number) so it appears on PDF invoices and emails.
$business_name   = 'CAR Rentals';
$business_addr   = '123 Drive Lane, Auto City, AC 54321';
$business_email  = 'support@carrentals.com';
$business_phone  = '+81 9081020835';
$gst_number      = '27QAAAA000A12Q'; // Your registered GSTIN (15 characters)

// ---------------------------------------------------------------------------
// Mail settings
// ---------------------------------------------------------------------------
// EMAIL_MODE:
//   'smtp'  -> real emails are sent via SMTP. Fill in SMTP_* below.
//   'debug' -> No real emails. Everything is written to email_debug.log
//              (only used as a fallback if SMTP fails or is not configured).
$email_mode = 'smtp'; // real email sending enabled

// SMTP configuration (used when EMAIL_MODE === 'smtp')
// Using a brand email account for CAR Rentals.
// To use a Gmail brand account (e.g. bookings@carrentals.com):
//   1. Create the Gmail account with your brand name.
//   2. Enable 2-Step Verification, then generate an App Password
//      (Google Account -> Security -> App passwords).
//   3. Paste that app password into $smtp_password below.
$smtp_host     = 'smtp.gmail.com';              // e.g. smtp.gmail.com
$smtp_port     = 587;                           // 587 (TLS) or 465 (SSL)
$smtp_secure   = 'tls';                         // 'tls' or 'ssl'
$smtp_username = 'bookings@carrentals.com';     // your brand email account
$smtp_password = '';                            // your SMTP / app password
$mail_from     = 'bookings@carrentals.com';     // sender address shown to users
$mail_from_name = 'CAR Rentals';

// Whether real SMTP credentials have been provided.
// When this is false (password left blank), emails are logged to
// email_debug.log instead and the OTP is shown on screen so the
// site still works while you set up your brand email account.
function is_smtp_configured() {
    global $smtp_username, $smtp_password, $smtp_host;
    return !empty($smtp_username)
        && !empty($smtp_password)
        && !empty($smtp_host)
        && strpos($smtp_password, 'YOUR_') !== 0
        && $smtp_password !== 'your_app_password';
}

// Global email test-mode flag (used by is_email_test_mode())
$email_test_mode = ($email_mode === 'debug');

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $db_error = $e->getMessage();
}

function is_database_ready() {
    global $pdo;
    return $pdo instanceof PDO;
}

function format_inr($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function calculate_gst_details($amount) {
    $base = (float)$amount;
    $gst = round($base * 0.18, 2);
    $cgst = round($gst / 2, 2);
    $sgst = round($gst / 2, 2);
    return [
        'base_price' => $base,
        'gst' => $gst,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'total' => round($base + $gst, 2),
    ];
}

function is_email_test_mode() {
    global $email_test_mode;
    return !empty($email_test_mode);
}

function send_email($to, $subject, $message, $attachments = []) {
    global $email_mode, $smtp_host, $smtp_port, $smtp_secure, $smtp_username,
           $smtp_password, $mail_from, $mail_from_name;

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

// If SMTP credentials are not configured yet, log instead of sending.
    if (!is_smtp_configured()) {
        $debugPath = __DIR__ . '/email_debug.log';
        $logMessage = sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, strip_tags($message));
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                $logMessage .= '  [Attachment: ' . basename($att) . ']' . "\n";
            }
            $logMessage .= "\n";
        }
@file_put_contents($debugPath, $logMessage, FILE_APPEND | LOCK_EX);
        return true;
    }

// Debug mode: log the email (and any attachments) instead of sending.
    if (is_email_test_mode()) {
        $debugPath = __DIR__ . '/email_debug.log';
        $logMessage = sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, strip_tags($message));
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                $logMessage .= '  [Attachment: ' . basename($att) . ']' . "\n";
            }
            $logMessage .= "\n";
        }
@file_put_contents($debugPath, $logMessage, FILE_APPEND | LOCK_EX);
        return true;
    }

    // SMTP mode: send a real email via PHPMailer.
    if ($email_mode === 'smtp') {
        require_once __DIR__ . '/mailer/Exception.php';
        require_once __DIR__ . '/mailer/PHPMailer.php';
        require_once __DIR__ . '/mailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = $smtp_secure;
            $mail->Port       = $smtp_port;

            $mail->setFrom($mail_from, $mail_from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);

            foreach ($attachments as $att) {
                if (is_file($att)) {
                    $mail->addAttachment($att);
                }
            }

            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }

    // Fallback: try PHP mail() if mode is neither 'debug' nor 'smtp'.
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $mail_from_name . ' <' . $mail_from . '>';

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function send_invoice_email($to, $subject, $message, $attachments = []) {
    return send_email($to, $subject, $message, $attachments);
}

function set_booking_status($bookingId, $status, $paymentMethod = null, $paymentRef = null) {
    global $pdo;
    $allowed = ['Pending', 'Confirmed', 'Paid', 'Cancelled'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $sql = 'UPDATE bookings SET status = ?';
    $params = [$status];

    if ($status === 'Paid') {
        $sql .= ', paid_at = NOW()';
        if ($paymentMethod !== null) {
            $sql .= ', payment_method = ?';
            $params[] = $paymentMethod;
        }
        if ($paymentRef !== null) {
            $sql .= ', payment_ref = ?';
            $params[] = $paymentRef;
        }
    }

    $sql .= ' WHERE id = ?';
    $params[] = (int)$bookingId;

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function get_booking_status($bookingId) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT status FROM bookings WHERE id = ?');
    $stmt->execute([(int)$bookingId]);
    return $stmt->fetchColumn() ?: 'Pending';
}

function status_color($status) {
    switch ($status) {
        case 'Paid':
            return '#28a745';
        case 'Confirmed':
            return '#007bff';
        case 'Cancelled':
            return '#dc3545';
        default:
            return '#ffc107';
    }
}

function get_user_by_email($email) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function create_user($email, $name) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO users (email, name) VALUES (?, ?)');
    $stmt->execute([$email, $name]);
    return $pdo->lastInsertId();
}

function save_login_otp($userId, $otp) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO login_otps (user_id, otp_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    $stmt->execute([$userId, $otp]);
    return $pdo->lastInsertId();
}

function verify_login_otp($userId, $otp) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id FROM login_otps WHERE user_id = ? AND otp_code = ? AND expires_at >= NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$userId, $otp]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = $pdo->prepare('UPDATE login_otps SET used = 1 WHERE id = ?');
        $stmt->execute([$row['id']]);
        return true;
    }
    return false;
}

function send_otp_email($to, $otp, $name = 'Customer') {
    $subject = 'Your CAR Rentals login code';
    $message = '<html><body>' .
               '<h2>Hello ' . htmlspecialchars($name) . ',</h2>' .
               '<p>Your login OTP for CAR Rentals is:</p>' .
               '<p style="font-size:22px; font-weight:700; color:#ff6b36;">' . htmlspecialchars($otp) . '</p>' .
               '<p>This code is valid for 10 minutes.</p>' .
               '<p>If you did not request this, please ignore this email.</p>' .
               '</body></html>';
    return send_email($to, $subject, $message) ? $otp : false;
}

function send_booking_confirmation_email($to, $customerName, $bookingId, $carName, $startDate, $endDate, $baseTotal, $gstDetails, $paymentLink) {
    $subject = 'Booking Confirmed - CAR Rentals #' . $bookingId;
    $message = '<html><body>' .
               '<h2>Hello ' . htmlspecialchars($customerName) . ',</h2>' .
               '<p>Your booking has been received successfully.</p>' .
               '<h3>Booking Summary</h3>' .
               '<p><strong>Booking ID:</strong> ' . htmlspecialchars($bookingId) . '</p>' .
               '<p><strong>Car:</strong> ' . htmlspecialchars($carName) . '</p>' .
               '<p><strong>Pickup:</strong> ' . htmlspecialchars($startDate) . '</p>' .
               '<p><strong>Return:</strong> ' . htmlspecialchars($endDate) . '</p>' .
               '<h3>Payment</h3>' .
               '<p><strong>Amount Before GST:</strong> ' . htmlspecialchars(format_inr($baseTotal)) . '</p>' .
               '<p><strong>SGST (9%):</strong> ' . htmlspecialchars(format_inr($gstDetails['sgst'])) . '</p>' .
               '<p><strong>CGST (9%):</strong> ' . htmlspecialchars(format_inr($gstDetails['cgst'])) . '</p>' .
               '<p><strong>Total Payable:</strong> ' . htmlspecialchars(format_inr($gstDetails['total'])) . '</p>' .
               '<p>You can complete your payment here: <a href="' . htmlspecialchars($paymentLink) . '">' . htmlspecialchars($paymentLink) . '</a></p>' .
               '<p>Thank you for choosing CAR Rentals.</p>' .
               '</body></html>';
    return send_email($to, $subject, $message);
}

function generate_otp() {
    try {
        return random_int(100000, 999999);
    } catch (Exception $e) {
        return rand(100000, 999999);
    }
}

function normalize_indian_phone($phone) {
    $clean = preg_replace('/[^0-9+]/', '', trim($phone));
    if (strpos($clean, '+') === 0) {
        $digits = preg_replace('/[^0-9]/', '', $clean);
        if (preg_match('/^91[6-9][0-9]{9}$/', $digits)) {
            return '+' . $digits;
        }
        return '+' . $digits;
    }
    if (preg_match('/^0?([6-9][0-9]{9})$/', $clean, $matches)) {
        return '+91' . $matches[1];
    }
    if (preg_match('/^91([6-9][0-9]{9})$/', $clean, $matches)) {
        return '+91' . $matches[1];
    }
    if (preg_match('/^([6-9][0-9]{9})$/', $clean, $matches)) {
        return '+91' . $matches[1];
    }
    return $clean;
}

function paypal_get_access_token() {
    global $paypal_client_id, $paypal_secret, $paypal_api_url;

    if (empty($paypal_client_id) || strpos($paypal_client_id, 'YOUR_') === 0) {
        return null;
    }

    $url = rtrim($paypal_api_url, '/') . '/v1/oauth2/token';
    $credentials = base64_encode($paypal_client_id . ':' . $paypal_secret);
    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];
    $body = 'grant_type=client_credentials';

    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
    } else {
        return null;
    }

    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? null;
}

function paypal_api_request($method, $endpoint, $payload = [], $includeAuth = true) {
    global $paypal_api_url;

    $url = rtrim($paypal_api_url, '/') . '/' . ltrim($endpoint, '/');
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($includeAuth) {
        $token = paypal_get_access_token();
        if (!$token) {
            return [
                'status' => 0,
                'body' => ['errors' => [['message' => 'PayPal is not configured.']]],
            ];
        }
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $body = $payload ? json_encode($payload) : '';

    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : (isset($GLOBALS['http_response_header']) ? $GLOBALS['http_response_header'] : []);
        if (is_array($responseHeaders)) {
            foreach ($responseHeaders as $header) {
                if (stripos($header, 'HTTP/') === 0 && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }
    } else {
        return [
            'status' => 0,
            'body' => ['errors' => [['message' => 'No HTTP transport available for PayPal requests']]],
        ];
    }

    $decoded = json_decode($response, true);
    return [
        'status' => $status,
        'body' => $decoded !== null ? $decoded : ['raw' => $response],
    ];
}

function paypal_create_order($amount, $description = 'CAR Rentals booking') {
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($amount, 2, '.', ''),
            ],
            'description' => $description,
        ]],
    ];

    return paypal_api_request('POST', 'v2/checkout/orders', $payload);
}

function paypal_capture_order($orderId) {
    return paypal_api_request('POST', 'v2/checkout/orders/' . urlencode($orderId) . '/capture', []);
}
?>

