<?php
include 'config.php';
session_start();

if (empty($_SESSION['customer_logged_in'])) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    header('Location: login.php?redirect=' . urlencode($currentUrl));
    exit;
}

$message = "";
$booking = [
    'name' => $_SESSION['customer_name'] ?? '',
    'email' => $_SESSION['customer_email'] ?? '',
    'phone' => '',
    'car_id' => '',
    'start' => '',
    'end' => '',
    'total_price' => '',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($booking as $key => $value) {
        if (isset($_POST[$key])) {
            $booking[$key] = trim($_POST[$key]);
        }
    }

    if (empty($booking['name']) || empty($booking['email']) || empty($booking['phone']) || empty($booking['car_id']) || empty($booking['start']) || empty($booking['end']) || empty($booking['total_price'])) {
        $message = "<div class='alert error'>Please fill in all required booking fields.</div>";
    } else {
        try {
            $sql = "INSERT INTO bookings (customer_name, email, phone, car_id, start_date, end_date, total_price)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $booking['name'],
                $booking['email'],
                $booking['phone'],
                $booking['car_id'],
                $booking['start'],
                $booking['end'],
                $booking['total_price'],
            ]);

            $bookingId = $pdo->lastInsertId();

            // Track status in the database (booking confirmed, awaiting payment).
            set_booking_status($bookingId, 'Confirmed');

            $gstDetails = calculate_gst_details($booking['total_price']);
            $carName = $pdo->prepare('SELECT name FROM cars WHERE id = ?');
            $carName->execute([$booking['car_id']]);
            $carNameValue = $carName->fetchColumn() ?: 'Car booking';
            $paymentLink = sprintf('http%s://%s%s/payment.php?booking_id=%s',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 's' : '',
                $_SERVER['HTTP_HOST'],
                dirname($_SERVER['REQUEST_URI']) === '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/',
                urlencode($bookingId)
            );

            send_booking_confirmation_email(
                $booking['email'],
                $booking['name'],
                $bookingId,
                $carNameValue,
                $booking['start'],
                $booking['end'],
                $booking['total_price'],
                $gstDetails,
                $paymentLink
            );

            header('Location: payment.php?booking_id=' . $bookingId);
            exit;
        } catch (PDOException $e) {
            $message = "<div class='alert error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

if (empty($booking['car_id']) && !empty($_GET['car_id'])) {
    $booking['car_id'] = trim($_GET['car_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Car | CAR Rentals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .price-summary { margin: 20px 0; padding: 15px; background: #f0f7ff; border-left: 5px solid #ff4d4d; }
        .summary-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav>
        <a class="brand-logo" href="index.php"><img src="logo.svg" alt="CAR Rentals logo"></a>
            <a href="cars.php">Cars</a>
            <a href="booking.php">Book Now</a>
            <a href="admin.php">Admin</a>
        </div>
    </nav>

    <div class="container">
        <form method="POST" id="bookingForm">
            <h2 style="text-align:center">Reserve Your Ride</h2>
            <?php echo $message; ?>

            <label>Full Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($booking['name']); ?>" readonly>

            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($booking['email']); ?>" readonly>

            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="123-456-7890" value="<?php echo htmlspecialchars($booking['phone']); ?>" required>

            <div class="form-group">
                <label>Select Car</label>
                <select name="car_id" id="car_id" required>
                    <option value="" data-price="0">-- Choose a Car --</option>
                    <?php
                    $cars = $pdo->query("SELECT * FROM cars WHERE status='Available'");
                    while ($c = $cars->fetch()) {
                        $selected = ($booking['car_id'] == $c['id']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($c['id']) . "' data-price='" . htmlspecialchars($c['price_per_day']) . "' $selected>" . htmlspecialchars($c['name']) . " (" . htmlspecialchars(format_inr($c['price_per_day'])) . "/day)</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-grid">
                <div>
                    <label>Pickup Date</label>
                    <input type="date" name="start" id="start_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($booking['start']); ?>" required>
                </div>
                <div>
                    <label>Return Date</label>
                    <input type="date" name="end" id="end_date" value="<?php echo htmlspecialchars($booking['end']); ?>" required>
                </div>
            </div>

            <div class="price-summary">
                <h3 id="total_display" style="margin:0;">Total: <?php echo htmlspecialchars(format_inr($booking['total_price'] ?: '0.00')); ?></h3>
                <small id="day_count">Select dates to calculate price</small>
            </div>

            <input type="hidden" name="total_price" id="total_price_val" value="<?php echo htmlspecialchars($booking['total_price']); ?>">
            <button type="submit" class="btn" style="width:100%">Confirm Booking</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('bookingForm');

        function calculatePrice() {
            const car = document.getElementById('car_id');
            const startStr = document.getElementById('start_date')?.value;
            const endStr = document.getElementById('end_date')?.value;
            const totalPriceField = document.getElementById('total_price_val');
            if (!car || !startStr || !endStr || !totalPriceField) return;

            const start = new Date(startStr);
            const end = new Date(endStr);

            if (car.value && startStr && endStr) {
                if (end > start) {
                    const pricePerDay = parseFloat(car.options[car.selectedIndex].getAttribute('data-price')) || 0;
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    const total = diffDays * pricePerDay;
                    document.getElementById('total_display').innerText = "Total: ₹" + total.toFixed(2);
                    document.getElementById('day_count').innerText = "Duration: " + diffDays + " day(s)";
                    totalPriceField.value = total.toFixed(2);
                } else {
                    document.getElementById('total_display').innerText = "Invalid Dates";
                    document.getElementById('day_count').innerText = "Return date must be after pickup";
                    totalPriceField.value = "";
                }
            }
        }

        form.addEventListener('change', calculatePrice);
        calculatePrice();
    </script>
</body>
</html>