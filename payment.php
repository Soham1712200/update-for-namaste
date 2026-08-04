<?php
include 'config.php';
session_start();

if (empty($_SESSION['customer_logged_in'])) {
    header('Location: login.php?redirect=payment.php');
    exit;
}

$bookingId = intval($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$message = '';

if ($bookingId <= 0) {
    die('Booking not found.');
}

$stmt = $pdo->prepare('SELECT b.*, c.name as car_name, c.price_per_day FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.id = ?');
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) {
    die('Booking not found.');
}

$paymentResult = '';
$paymentMethod = $_POST['payment_method'] ?? 'upi';
$gstDetails = calculate_gst_details($booking['total_price']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($paymentMethod === 'upi') {
        // Mark as paid + record payment method/ref in the database.
        $ref = 'UPI' . time();
        set_booking_status($bookingId, 'Paid', 'upi', $ref);
        $_SESSION['paid_booking_' . $bookingId] = true;
        $_SESSION['payment_notes_' . $bookingId] = 'UPI payment completed. Reference: ' . $ref;
        header('Location: invoice.php?booking_id=' . $bookingId);
        exit;
    } elseif ($paymentMethod === 'bank') {
        // Mark as paid + record payment method/ref in the database.
        $ref = 'BT' . time();
        set_booking_status($bookingId, 'Paid', 'bank', $ref);
        $_SESSION['paid_booking_' . $bookingId] = true;
        $_SESSION['payment_notes_' . $bookingId] = 'Bank transfer selected. Reference: ' . $ref;
        header('Location: invoice.php?booking_id=' . $bookingId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment | CAR Rentals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .qr-box { text-align: center; padding: 20px; background: #f9f9f9; border-radius: 12px; margin-bottom: 20px; }
        .qr-box canvas, .qr-box img { max-width: 220px; width: 100%; height: auto; }
.upi-note { font-size: 0.95rem; color: #555; margin-top: 10px; }
        .upi-id { font-weight: 700; color: #111827; }

        /* Countdown timer overlay */
        .timer-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; align-items: center; justify-content: center; }
        .timer-overlay.active { display: flex; }
        .timer-card { background: #fff; border-radius: 16px; padding: 40px 46px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.3); max-width: 340px; width: 90%; }
        .timer-card h3 { margin: 0 0 6px; font-size: 1.3rem; color: #111827; }
        .timer-card p { margin: 0 0 20px; color: #6b7280; font-size: 0.95rem; }
        .timer-ring { position: relative; width: 150px; height: 150px; margin: 0 auto 20px; }
        .timer-ring svg { width: 150px; height: 150px; transform: rotate(-90deg); }
        .timer-ring .track { fill: none; stroke: #e5e7eb; stroke-width: 8; }
        .timer-ring .bar { fill: none; stroke: #ff4d4d; stroke-width: 8; stroke-linecap: round; stroke-dasharray: 414; stroke-dashoffset: 0; transition: stroke-dashoffset 1s linear; }
        .timer-ring .num { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 2.6rem; font-weight: 800; color: #111827; }
        .timer-spinner { display: inline-block; width: 16px; height: 16px; border: 3px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .timer-status { font-size: 0.95rem; color: #4b5563; margin-top: 6px; }
        .timer-status.green { color: #16a34a; font-weight: 700; }
    </style>
</head>
<body>
    <nav>
        <a class="brand-logo" href="index.php"><img src="logo.svg" alt="CAR Rentals logo"></a>
        <div>
            <a href="index.php">Home</a>
            <a href="cars.php">Cars</a>
            <a href="booking.php">Book Now</a>
            <a href="index.php#contact">Contact Us</a>
            <a href="admin.php">Admin</a>
            <a href="login.php?logout=1">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div style="background:white; padding:30px; border-radius:12px; box-shadow:0 5px 15px rgba(0,0,0,0.1); max-width:700px; margin:0 auto;">
            <h2 style="text-align:center;">Payment</h2>
            <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['id']); ?></p>
            <p><strong>Car:</strong> <?php echo htmlspecialchars($booking['car_name']); ?></p>
            <p><strong>Pickup:</strong> <?php echo htmlspecialchars($booking['start_date']); ?></p>
            <p><strong>Return:</strong> <?php echo htmlspecialchars($booking['end_date']); ?></p>
            <p><strong>Base Amount:</strong> <?php echo htmlspecialchars(format_inr($gstDetails['base_price'])); ?></p>
            <p><strong>SGST (9%):</strong> <?php echo htmlspecialchars(format_inr($gstDetails['sgst'])); ?></p>
            <p><strong>CGST (9%):</strong> <?php echo htmlspecialchars(format_inr($gstDetails['cgst'])); ?></p>
            <p><strong>Total with GST (18%):</strong> <?php echo htmlspecialchars(format_inr($gstDetails['total'])); ?></p>

            <?php echo $paymentResult; ?>

            <form method="POST" style="margin-top:20px;">
                <div style="margin-bottom:20px;">
                    <label><input type="radio" name="payment_method" value="upi" <?php echo $paymentMethod === 'upi' ? 'checked' : ''; ?>> UPI (Scan QR Code)</label><br>
                    <label><input type="radio" name="payment_method" value="bank" <?php echo $paymentMethod === 'bank' ? 'checked' : ''; ?>> Bank Transfer</label>
                </div>

                <div id="upi-box" style="display: <?php echo $paymentMethod === 'upi' ? 'block' : 'none'; ?>;">
                    <div class="qr-box">
                        <div id="qrcode"></div>
                        <p class="upi-note">Scan with any UPI app (GPay, PhonePe, Paytm, BHIM) to pay <strong><?php echo htmlspecialchars(format_inr($gstDetails['total'])); ?></strong>.</p>
                        <p class="upi-note">UPI ID: <span class="upi-id"><?php echo htmlspecialchars($upi_id); ?></span></p>
                    </div>
                </div>

                <div id="bank-note" style="display: <?php echo $paymentMethod === 'bank' ? 'block' : 'none'; ?>; background: #f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <p>Bank transfer instructions will be sent to your email after booking.</p>
                    <p style="font-size:0.95rem; color:#555;">This option is accepted immediately for demo purposes.</p>
                </div>

<input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($bookingId); ?>">
                <p style="text-align:center; color:#6b7280; font-size:0.9rem; margin:14px 0 0;">Scan the QR and complete the payment. Your invoice will be generated automatically after the timer.</p>
                <button type="submit" id="payBtn" class="btn" style="width:100%; margin-top:12px;">Pay <?php echo htmlspecialchars(format_inr($gstDetails['total'])); ?></button>
            </form>
        </div>
    </div>

    <!-- Payment verification countdown overlay -->
    <div id="timerOverlay" class="timer-overlay">
        <div class="timer-card">
            <h3>Verifying Payment</h3>
            <p>Please wait while we confirm your payment...</p>
            <div class="timer-ring">
                <svg viewBox="0 0 150 150">
                    <circle class="track" cx="75" cy="75" r="66"></circle>
                    <circle class="bar" id="timerBar" cx="75" cy="75" r="66"></circle>
                </svg>
                <div class="num" id="timerNum">30</div>
            </div>
            <div class="timer-status" id="timerStatus"><span class="timer-spinner"></span>Processing payment, please do not refresh...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        const radios = document.querySelectorAll('input[name="payment_method"]');
        const upiBox = document.getElementById('upi-box');
        const bankNote = document.getElementById('bank-note');

        // Build the UPI URI for the dynamic QR code.
        const upiId = <?php echo json_encode($upi_id); ?>;
        const payee = <?php echo json_encode($upi_payee_name); ?>;
        const amount = <?php echo json_encode(number_format($gstDetails['total'], 2, '.', '')); ?>;
        const note = 'Booking #<?php echo (int)$bookingId; ?> CAR Rentals';
        const upiUri = 'upi://pay?pa=' + encodeURIComponent(upiId) +
            '&pn=' + encodeURIComponent(payee) +
            '&am=' + encodeURIComponent(amount) +
            '&cu=INR' +
            '&tn=' + encodeURIComponent(note);

        // Generate the dynamic QR code.
        new QRCode(document.getElementById('qrcode'), {
            text: upiUri,
            width: 220,
            height: 220,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });

radios.forEach(radio => {
            radio.addEventListener('change', () => {
                upiBox.style.display = radio.value === 'upi' ? 'block' : 'none';
                bankNote.style.display = radio.value === 'bank' ? 'block' : 'none';
            });
        });

        // ---- Countdown timer before invoice generation ----
        const form = document.querySelector('form');
        const overlay = document.getElementById('timerOverlay');
        const timerNum = document.getElementById('timerNum');
        const timerBar = document.getElementById('timerBar');
        const timerStatus = document.getElementById('timerStatus');
        const payBtn = document.getElementById('payBtn');

        const TOTAL_TIME = 30; // seconds
        const CIRCUMFERENCE = 414; // 2 * PI * r (r=66)

        form.addEventListener('submit', function (e) {
            e.preventDefault(); // stop immediate submit

            // Show the verification overlay
            overlay.classList.add('active');
            payBtn.disabled = true;
            payBtn.style.opacity = '0.6';

            let remaining = TOTAL_TIME;
            timerNum.textContent = remaining;
            timerBar.style.strokeDashoffset = 0;

            const interval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(interval);
                    // Indicate completion, then submit the form for real
                    timerStatus.textContent = 'Payment verified! Generating your invoice...';
                    timerStatus.classList.add('green');
                    timerNum.textContent = '✓';
                    timerBar.style.stroke = '#16a34a';
                    setTimeout(() => {
                        form.submit();
                    }, 800);
                    return;
                }
                timerNum.textContent = remaining;
                const progress = (TOTAL_TIME - remaining) / TOTAL_TIME;
                timerBar.style.strokeDashoffset = CIRCUMFERENCE * progress;
            }, 1000);
        });
    </script>
</body>
</html>
