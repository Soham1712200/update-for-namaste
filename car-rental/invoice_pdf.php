<?php
// invoice_pdf.php
// Generates a printable PDF invoice using FPDF for a booking record.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fpdf.php';

/**
 * Generate a PDF invoice for a booking and return the file path.
 *
 * @param array  $booking    Booking row (joined with cars for car_name).
 * @param array  $gstDetails Output of calculate_gst_details().
 * @param string $invoiceNo  Optional invoice number (auto-generated if empty).
 * @return string Absolute path to the generated PDF file.
 */
function generate_invoice_pdf($booking, $gstDetails, $invoiceNo = '') {
    if (empty($invoiceNo)) {
        $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad((int)$booking['id'], 4, '0', STR_PAD_LEFT);
    }

$pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

// ---- PAID stamp (editable, semi-transparent look) ----
    $pdf->SetFont('Helvetica', 'B', 60);
    $pdf->SetTextColor(235, 60, 60);
    $pdf->SetDrawColor(235, 60, 60);
    $pdf->SetLineWidth(3);
    $pdf->SetXY(55, 120);
    $pdf->Cell(100, 30, 'PAID', 1, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);

// ---- Header band ----
    global $business_name, $business_addr, $business_email, $business_phone, $gst_number;
    $pdf->SetFillColor(22, 22, 22);
    $pdf->Rect(0, 0, 210, 40, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetXY(10, 8);
    $pdf->Cell(120, 10, strtoupper($business_name), 0, 1);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetX(10);
    $pdf->Cell(120, 5, $business_addr, 0, 1);
    $pdf->SetX(10);
    $pdf->Cell(120, 5, $business_email . '  |  ' . $business_phone, 0, 1);
    $pdf->SetX(10);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(120, 5, 'GSTIN: ' . $gst_number, 0, 1);

    // Logo image on the right side of the header bar
    $logoPath = __DIR__ . '/logo.png';
    if (is_file($logoPath)) {
        $pdf->Image($logoPath, 130, 4, 60, 0);
    }

    // Invoice title on the right side of the header
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetXY(130, 10);
    $pdf->Cell(70, 8, 'INVOICE', 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetX(130);
    $pdf->Cell(70, 5, 'Invoice #: ' . $invoiceNo, 0, 1, 'R');
    $pdf->SetX(130);
    $pdf->Cell(70, 5, 'Date: ' . date('F j, Y'), 0, 1, 'R');
    $pdf->SetX(130);
    $pdf->Cell(70, 5, 'Status: PAID', 0, 1, 'R');

    // ---- Bill To / Booking details ----
    $pdf->SetY(44);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(95, 8, 'BILL TO', 0, 0);
    $pdf->Cell(95, 8, 'BOOKING', 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(95, 6, htmlspecialchars($booking['customer_name']), 0, 0);
    $pdf->Cell(95, 6, 'Booking ID: #' . htmlspecialchars($booking['id']), 0, 1);
    $pdf->Cell(95, 6, htmlspecialchars($booking['email']), 0, 0);
    $pdf->Cell(95, 6, 'Car: ' . htmlspecialchars($booking['car_name']), 0, 1);
    $pdf->Cell(95, 6, htmlspecialchars($booking['phone']), 0, 0);
    $pdf->Cell(95, 6, 'Pickup: ' . htmlspecialchars($booking['start_date']), 0, 1);
$pdf->Cell(95, 6, '', 0, 0);
    $pdf->Cell(95, 6, 'Return: ' . htmlspecialchars($booking['end_date']), 0, 1);

    // GSTIN line under bill to
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(95, 6, 'GSTIN: ' . $gst_number, 0, 1);
    $pdf->SetFont('Helvetica', '', 10);

    // ---- Amounts table ----
    $pdf->Ln(8);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('Helvetica', 'B', 10);

    $pdf->Cell(120, 9, 'Description', 1, 0, 'L', true);
    $pdf->Cell(70, 9, 'Amount (INR)', 1, 1, 'R', true);

    $pdf->SetFont('Helvetica', '', 10);
    $rows = [
        ['Base rental amount (before GST)', $gstDetails['base_price']],
        ['SGST @ 9%', $gstDetails['sgst']],
        ['CGST @ 9%', $gstDetails['cgst']],
    ];
    foreach ($rows as $row) {
        $pdf->Cell(120, 9, $row[0], 1, 0, 'L');
        $pdf->Cell(70, 9, 'Rs. ' . number_format((float)$row[1], 2), 1, 1, 'R');
    }

    // Total row
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetFillColor(255, 214, 0);
    $pdf->Cell(120, 11, 'TOTAL PAYABLE', 1, 0, 'L', true);
    $pdf->Cell(70, 11, 'Rs. ' . number_format((float)$gstDetails['total'], 2), 1, 1, 'R', true);

    // ---- Footer ----
    $pdf->Ln(12);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->MultiCell(0, 5, 'Thank you for choosing CAR Rentals. Drive safe!', 0, 'C');
    $pdf->Ln(3);
    $pdf->MultiCell(0, 5, 'This is a computer-generated invoice and does not require a signature.', 0, 'C');

// ---- Save to file ----
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @chmod($dir, 0777);
    if (!is_writable($dir)) {
        // Fall back to the system temp directory if the invoices folder is not writable.
        $dir = sys_get_temp_dir();
        $filename = $dir . '/invoice_' . $invoiceNo . '.pdf';
        $pdf->Output('F', $filename);
        return $filename;
    }
    $filename = $dir . '/invoice_' . $invoiceNo . '.pdf';
    $pdf->Output('F', $filename);

    return $filename;
}

