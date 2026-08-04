<?php 
include 'config.php'; 
session_start();

// 1. Define your credentials (In a real app, these would be in the DB)
$admin_user = "car";
$admin_pass = "sohamcar1234"; 

// 2. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// 3. Handle Login Submission
$error = "";
if (isset($_POST['login'])) {
    if ($_POST['username'] === $admin_user && $_POST['password'] === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Invalid username or password!";
    }
}

// 4. Handle Export to Excel
if (isset($_GET['export'])) {
    if (!isset($_SESSION['admin_logged_in'])) {
        header('Location: admin.php');
        exit;
    }

    $query = "SELECT b.id, b.customer_name, b.email, b.phone, c.name as car_name,
                     b.start_date, b.end_date, b.total_price, b.status,
                     b.payment_method, b.payment_ref, b.paid_at
              FROM bookings b JOIN cars c ON b.car_id = c.id
              ORDER BY b.id DESC";
    $stmt = $pdo->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build the Excel-compatible CSV (UTF-8 with BOM so Excel shows ₹ correctly).
    $tsv = "\xEF\xBB\xBF"; // UTF-8 BOM
    $tsv .= "Booking ID\tCustomer Name\tEmail\tPhone\tCar\tPickup Date\tReturn Date\tBase Amount (INR)\tSGST (9%)\tCGST (9%)\tTotal (INR)\tStatus\tPayment Method\tPayment Ref\tPaid At\n";

    foreach ($rows as $r) {
        $gst = calculate_gst_details($r['total_price']);
        $tsv .= $r['id'] . "\t"
              . $r['customer_name'] . "\t"
              . $r['email'] . "\t"
              . $r['phone'] . "\t"
              . $r['car_name'] . "\t"
              . $r['start_date'] . "\t"
              . $r['end_date'] . "\t"
              . (float)$r['total_price'] . "\t"
              . $gst['sgst'] . "\t"
              . $gst['cgst'] . "\t"
              . $gst['total'] . "\t"
              . $r['status'] . "\t"
              . ($r['payment_method'] ?? '') . "\t"
              . ($r['payment_ref'] ?? '') . "\t"
              . ($r['paid_at'] ?? '') . "\n";
    }

    $filename = 'bookings_' . date('Y-m-d_H-i-s') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $tsv;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Swift Rentals</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a class="brand-logo" href="index.php"><img src="logo.svg" alt="CAR Rentals logo"></a>
        <div>
            <a href="index.php">Home</a>
            <?php if(isset($_SESSION['admin_logged_in'])): ?>
                <a href="admin.php?logout=true" style="color:#ff4d4d">Logout</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <?php if (!isset($_SESSION['admin_logged_in'])): ?>
            <div style="max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <h2 style="text-align:center">Admin Login</h2>
                <?php if($error) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>
                <form method="POST">
                    <label>Username</label>
                    <input type="text" name="username" required>
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <button type="submit" name="login" class="btn" style="width:100%; margin-top:10px;">Login</button>
                </form>
            </div>

<?php else: ?>
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom: 2px solid #ff4d4d; padding-bottom:10px;">
                <h1 style="margin:0;">Booking Dashboard</h1>
                <a href="admin.php?export=1" class="btn" style="background:#28a745; text-decoration:none;">📥 Export to Excel</a>
            </div>

            <table style="width:100%; background:white; border-collapse:collapse; margin-top:20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <thead>
                    <tr style="background:#1a1a1a; color:white;">
                        <th style="padding:15px;">ID</th>
                        <th>Customer Details</th>
                        <th>Car Booked</th>
                        <th>Rental Period</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // JOIN query to get car names instead of just IDs
                    $query = "SELECT b.*, c.name as car_name FROM bookings b 
                              JOIN cars c ON b.car_id = c.id 
                              ORDER BY b.id DESC";
                    $stmt = $pdo->query($query);
                    
                    while($row = $stmt->fetch()) {
                        echo "<tr style='border-bottom:1px solid #ddd; text-align:center;'>
                            <td style='padding:15px;'>#{$row['id']}</td>
                            <td style='text-align:left; padding:10px;'>
                                <strong>{$row['customer_name']}</strong><br>
                                <small>{$row['email']}</small><br>
                                <small>{$row['phone']}</small>
</td>
                            <td>{$row['car_name']}</td>
                            <td>" . date('M d', strtotime($row['start_date'])) . " - " . date('M d, Y', strtotime($row['end_date'])) . "</td>
                            <td style='color:green; font-weight:bold;'>" . htmlspecialchars(format_inr($row['total_price'])) . "</td>
                            <td>
                                <span style='display:inline-block; padding:4px 10px; border-radius:12px; font-size:0.85rem; font-weight:600; color:#fff; background-color:" . status_color($row['status']) . ";'>" . htmlspecialchars($row['status']) . "</span>
                            </td>
                            <td>
                                " . (!empty($row['payment_method']) ? htmlspecialchars($row['payment_method']) : '—') . "
                                " . (!empty($row['payment_ref']) ? "<br><small>" . htmlspecialchars($row['payment_ref']) . "</small>" : '') . "
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        <?php endif; ?>
</div>
</body>
</html>
