<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>car rentals | Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a class="brand-logo" href="index.php"><img src="logo.svg" alt="CAR Rentals logo"></a>
        <div>
            <a href="index.php">Home</a>
            <a href="cars.php">Cars</a>
            <a href="booking.php">Book Now</a>
            <a href="login.php">Login</a>
            <a href="index.php#contact">Contact Us</a>
            <a href="admin.php">Admin</a>
        </div>
    </nav>

    <div class="hero">
        <div>
            <h1>Premium Cars. Affordable Prices.</h1>
            <p>Experience the road like never before.</p>
            <a href="cars.php" class="btn">View Our Fleet</a>
        </div>
    </div>

    <div class="container">
        <h2>Featured Cars</h2>
        <div class="car-grid">
            <?php
            $stmt = $pdo->query("SELECT * FROM cars LIMIT 3");
            while($row = $stmt->fetch()) {
                echo "
                <div class='car-card'>
                    <div class='car-card-body'>
                        <h3>{$row['name']}</h3>
                        <p>Type: {$row['type']}</p>
                        <p><strong>" . htmlspecialchars(format_inr($row['price_per_day'])) . " / Day</strong></p>
                        <a href='booking.php?car_id={$row['id']}' class='btn'>Book Now</a>
                    </div>
                </div>";
            }
            ?>
        </div>
    </div>

    <div id="contact" class="container" style="margin-top:40px;">
        <h2>Contact Us</h2>
        <div class="contact-info-boxes" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:20px; margin-bottom:40px;">
            <div class="info-box" style="background:#fff; padding:20px; text-align:center; border-radius:8px; border-top:4px solid var(--accent);">
                <h3>📍 Address</h3>
                <p>123 Drive Lane, Auto City, AC 54321</p>
            </div>
            <div class="info-box" style="background:#fff; padding:20px; text-align:center; border-radius:8px; border-top:4px solid var(--accent);">
                <h3>📞 Phone</h3>
                <p>+91 9081020835</p>
            </div>
            <div class="info-box" style="background:#fff; padding:20px; text-align:center; border-radius:8px; border-top:4px solid var(--accent);">
                <h3>✉️ Email</h3>
                <p>support@carrentals.com</p>
            </div>
        </div>

        <div class="contact-form" style="background:white; padding:25px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
            <h2 style="text-align:center;">Send us a Message</h2>
            <form action="#" method="POST">
                <input type="text" name="msg_name" placeholder="Your Name" required style="width:100%; padding:12px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                <input type="email" name="msg_email" placeholder="Your Email" required style="width:100%; padding:12px; margin-bottom:10px; border:1px solid #ddd; border-radius:5px;">
                <textarea name="message" placeholder="How can we help you?" style="width:100%; padding:12px; height:120px; border:1px solid #ddd; border-radius:5px; margin-bottom:10px; font-family:inherit;"></textarea>
                <button type="submit" class="btn" style="width:100%;">Send Message</button>
            </form>
        </div>
    </div>
</body>
</html>