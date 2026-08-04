<?php
include 'config.php';
session_start();

$bookingId = intval($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    die('Invoice not found.');
}

$stmt = $pdo->prepare('SELECT b.*, c.name as car_name, c.price_per_day FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.id = ?');
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) {
    die('Invoice not found.');
}

// Show the invoice only when the booking is actually paid in the database.
// This is more reliable than the session flag (persists across refresh/new tabs).
$isPaid = (isset($booking['status']) && $booking['status'] === 'Paid')
       || !empty($_SESSION['paid_booking_' . $bookingId]);

if (!$isPaid) {
    header('Location: payment.php?booking_id=' . $bookingId);
    exit;
}

$invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($bookingId, 4, '0', STR_PAD_LEFT);
$invoiceDate = date('F j, Y');
$gstDetails = calculate_gst_details($booking['total_price']);
$emailStatus = '';

// Generate the PDF invoice file (shared by the email + download link).
$gst_number = $gst_number ?? '';
require_once __DIR__ . '/invoice_pdf.php';
$pdfPath = generate_invoice_pdf($booking, $gstDetails, $invoiceNumber);
$pdfFilename = basename($pdfPath);

if (empty($_SESSION['invoice_email_sent_' . $bookingId])) {
$emailBody = '<h1>' . htmlspecialchars($business_name) . ' Invoice</h1>' .
                 '<p>Invoice #: ' . htmlspecialchars($invoiceNumber) . '</p>' .
                 '<p>Date: ' . htmlspecialchars($invoiceDate) . '</p>' .
                 '<p>GSTIN: ' . htmlspecialchars($gst_number) . '</p>' .
                 '<h2>Booking Details</h2>' .
                 '<p>Customer: ' . htmlspecialchars($booking['customer_name']) . '</p>' .
                 '<p>Email: ' . htmlspecialchars($booking['email']) . '</p>' .
                 '<p>Phone: ' . htmlspecialchars($booking['phone']) . '</p>' .
                 '<p>Car: ' . htmlspecialchars($booking['car_name']) . '</p>' .
                 '<p>Pickup: ' . htmlspecialchars($booking['start_date']) . '</p>' .
                 '<p>Return: ' . htmlspecialchars($booking['end_date']) . '</p>' .
                 '<h2>Payment Summary</h2>' .
                 '<p>Base Amount: ' . htmlspecialchars(format_inr($gstDetails['base_price'])) . '</p>' .
                 '<p>SGST (9%): ' . htmlspecialchars(format_inr($gstDetails['sgst'])) . '</p>' .
                 '<p>CGST (9%): ' . htmlspecialchars(format_inr($gstDetails['cgst'])) . '</p>' .
                 '<p><strong>Total Paid: ' . htmlspecialchars(format_inr($gstDetails['total'])) . '</strong></p>' .
                 '<p>Your invoice is attached as a PDF to this email.</p>';
    // Attach the PDF invoice.
    if (send_invoice_email($booking['email'], 'Your CAR Rentals Invoice #' . $invoiceNumber, $emailBody, [$pdfPath])) {
        $emailStatus = '<div class="alert success">Invoice PDF has been sent to ' . htmlspecialchars($booking['email']) . '.</div>';
        $_SESSION['invoice_email_sent_' . $bookingId] = true;
    } else {
        $emailStatus = '<div class="alert error">Unable to send invoice email. You can download the PDF below.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice | CAR Rentals</title>
    <link rel="stylesheet" href="style.css">
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
        <div class="invoice-box">
            <?php echo $emailStatus; ?>
<div style="text-align:center; margin-bottom:16px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <a href="invoice_download.php?booking_id=<?php echo (int)$bookingId; ?>" class="btn">Download PDF Invoice</a>
                <button type="button" class="btn" onclick="window.print()" style="background:#1a1a1a;">Print Invoice</button>
            </div>
            <div class="invoice-header">
<div class="invoice-logo">
                    <img src="logo.svg" alt="CAR Rentals logo">
                    <div>
                        <h1>Invoice</h1>
                        <p>Invoice #: <?php echo htmlspecialchars($invoiceNumber); ?></p>
                        <p>Date: <?php echo htmlspecialchars($invoiceDate); ?></p>
                        <p>GSTIN: <?php echo htmlspecialchars($gst_number); ?></p>
                    </div>
                </div>
                <div class="invoice-stamp">PAID</div>
            </div>

            <div class="invoice-details">
                <div>
                    <h3>Customer</h3>
                    <p><?php echo htmlspecialchars($booking['customer_name']); ?></p>
                    <p><?php echo htmlspecialchars($booking['email']); ?></p>
                    <p><?php echo htmlspecialchars($booking['phone']); ?></p>
                </div>
                <div>
                    <h3>Booking</h3>
                    <p><strong>Car:</strong> <?php echo htmlspecialchars($booking['car_name']); ?></p>
                    <p><strong>Pickup Date:</strong> <?php echo htmlspecialchars($booking['start_date']); ?></p>
                    <p><strong>Return Date:</strong> <?php echo htmlspecialchars($booking['end_date']); ?></p>
                    <p><strong>Amount Before GST:</strong> <?php echo htmlspecialchars(format_inr($booking['total_price'])); ?></p>
                </div>
            </div>

            <div class="invoice-details" style="margin-top: 24px;">
                <div style="grid-column: span 2;">
                    <h3>Payment Summary</h3>
                    <p><strong>Base Amount:</strong> <?php echo htmlspecialchars(format_inr($gstDetails['base_price'])); ?></p>
                    <p><strong>SGST (9%):</strong> <?php echo htmlspecialchars(format_inr($gstDetails['sgst'])); ?></p>
                    <p><strong>CGST (9%):</strong> <?php echo htmlspecialchars(format_inr($gstDetails['cgst'])); ?></p>
                    <p><strong>Total Amount:</strong> <?php echo htmlspecialchars(format_inr($gstDetails['total'])); ?></p>
                </div>
            </div>

            <div class="invoice-footer">
                <p>Thank you for choosing CAR Rentals. Drive safe!</p>
            </div>
        </div>
    </div>
</body>
</html>
