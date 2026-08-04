<?php
// invoice_download.php
// Streams the generated PDF invoice for download.

include 'config.php';
session_start();

$bookingId = intval($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    die('Invoice not found.');
}

$stmt = $pdo->prepare('SELECT b.*, c.name as car_name FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.id = ?');
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) {
    die('Invoice not found.');
}

// Allow download only when the booking is actually paid in the database.
$isPaid = (isset($booking['status']) && $booking['status'] === 'Paid')
       || !empty($_SESSION['paid_booking_' . $bookingId]);

if (!$isPaid) {
    header('Location: payment.php?booking_id=' . $bookingId);
    exit;
}

$gstDetails = calculate_gst_details($booking['total_price']);
$invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($bookingId, 4, '0', STR_PAD_LEFT);

require_once __DIR__ . '/invoice_pdf.php';
$pdfPath = generate_invoice_pdf($booking, $gstDetails, $invoiceNumber);

if (!is_file($pdfPath)) {
    die('Invoice PDF could not be generated.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
exit;
