<?php
include 'config.php';
session_start();

$imageUrls = [
    'Sedan' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=800&q=80',
    'SUV' => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=800&q=80',
    'Luxury' => 'https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=800&q=80',
    'Hatchback' => 'https://images.unsplash.com/photo-1483721310020-03333e577078?auto=format&fit=crop&w=800&q=80',
    'Van' => 'https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=800&q=80',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cars | CAR Rentals</title>
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
            <?php if (!empty($_SESSION['customer_logged_in'])): ?>
                <a href="login.php?logout=1">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <h2>Available Cars</h2>
        <div class="car-grid">
            <?php
            $stmt = $pdo->query("SELECT * FROM cars WHERE status='Available'");
            while ($row = $stmt->fetch()) {
                $image = $imageUrls[$row['type']] ?? 'https://images.unsplash.com/photo-1549921296-3b0a71b3f2ec?auto=format&fit=crop&w=800&q=80';
                $button = !empty($_SESSION['customer_logged_in'])
                    ? "<a href='booking.php?car_id={$row['id']}' class='btn'>Book Now</a>"
                    : "<a href='login.php?redirect=" . urlencode('booking.php?car_id=' . $row['id']) . "' class='btn'>Login to Book</a>";

                echo "<div class='car-card'>
                        <img src='{$image}' alt='Car Image'>
                        <div class='car-card-body'>
                            <h3>" . htmlspecialchars($row['name']) . "</h3>
                            <p>Type: " . htmlspecialchars($row['type']) . "</p>
                            <p><strong>" . htmlspecialchars(format_inr($row['price_per_day'])) . " / Day</strong></p>
                            <p>Status: " . htmlspecialchars($row['status']) . "</p>
                            {$button}
                        </div>
                    </div>";
            }
            ?>
        </div>
    </div>
</body>
</html>
