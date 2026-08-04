<?php 
include 'config.php'; 
$message = "";

// PHP Logic to handle the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Ensure all required fields are present
        if(!empty($_POST['name']) && !empty($_POST['car_id']) && !empty($_POST['total_price'])) {
            
            $sql = "INSERT INTO bookings (customer_name, email, phone, car_id, start_date, end_date, total_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['name'], 
                $_POST['email'], 
                $_POST['phone'], 
                $_POST['car_id'], 
                $_POST['start'], 
                $_POST['end'], 
                $_POST['total_price']
            ]);
            
            $message = "<div class='alert success'>Booking confirmed successfully!</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book a Car | CAR Rentals</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .price-summary { margin: 20px 0; padding: 15px; background: #f0f7ff; border-left: 5px solid #ff4d4d; }
    </style>
</head>
<body>
    <nav>
        <a class="brand-logo" href="index.php"><img src="logo.svg" alt="CAR Rentals logo"></a>
        <div>
            <a href="index.php">Home</a>
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
            <input type="text" name="name" placeholder="John Doe" required>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@example.com" required>

            <label>Phone Number</label>
            <input type="text" name="phone" placeholder="123-456-7890" required>

            <div class="form-group">
                <label>Select Car</label>
                <select name="car_id" id="car_id" required>
                    <option value="" data-price="0">-- Choose a Car --</option>
                    <?php
                    $cars = $pdo->query("SELECT * FROM cars WHERE status='Available'");
                    while($c = $cars->fetch()) {
                        $selected = (isset($_GET['car_id']) && $_GET['car_id'] == $c['id']) ? "selected" : "";
                        echo "<option value='{$c['id']}' data-price='{$c['price_per_day']}' $selected>{$c['name']} (" . htmlspecialchars(format_inr($c['price_per_day'])) . "/day)</option>";
                    }
                    ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label>Pickup Date</label>
                    <input type="date" name="start" id="start_date" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div style="flex: 1;">
                    <label>Return Date</label>
                    <input type="date" name="end" id="end_date" required>
                </div>
            </div>

            <div class="price-summary">
                <h3 id="total_display" style="margin:0;">Total: ₹0.00</h3>
                <small id="day_count">Select dates to calculate price</small>
            </div>

            <input type="hidden" name="total_price" id="total_price_val">
            
            <button type="submit" class="btn" style="width:100%">Confirm Booking</button>
        </form>
    </div>

    <script>
        const form = document.getElementById('bookingForm');
        
        function calculatePrice() {
            const car = document.getElementById('car_id');
            const startStr = document.getElementById('start_date').value;
            const endStr = document.getElementById('end_date').value;
            
            const start = new Date(startStr);
            const end = new Date(endStr);
            
            if(car.value && startStr && endStr) {
                if(end > start) {
                    const pricePerDay = car.options[car.selectedIndex].getAttribute('data-price');
                    
                    // Math: (End Time - Start Time) / (ms in a day)
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                    
                    const total = diffDays * pricePerDay;
                    
                    document.getElementById('total_display').innerText = "Total: $" + total.toFixed(2);
                    document.getElementById('day_count').innerText = "Duration: " + diffDays + " day(s)";
                    document.getElementById('total_price_val').value = total.toFixed(2);
                } else {
                    document.getElementById('total_display').innerText = "Invalid Dates";
                    document.getElementById('day_count').innerText = "Return date must be after pickup";
                    document.getElementById('total_price_val').value = "";
                }
            }
        }

        // Listen for any changes in the form
        form.addEventListener('change', calculatePrice);
    </script>
</body>
</html>