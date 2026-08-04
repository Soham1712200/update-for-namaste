<?php
include 'config.php';
session_start();

$redirect = isset($_REQUEST['redirect']) ? urldecode($_REQUEST['redirect']) : 'cars.php';
$message = '';
$stage = $_POST['stage'] ?? $_GET['stage'] ?? 'request';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage === 'request') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert error">Please enter your name and a valid email address.</div>';
        } else {
            $user = get_user_by_email($email);
            if (!$user) {
                $userId = create_user($email, $name);
                $user = ['id' => $userId, 'name' => $name, 'email' => $email];
            }

            $otp = generate_otp();
            save_login_otp($user['id'], $otp);
            $sentOtp = send_otp_email($email, $otp, $name);

            if ($sentOtp !== false) {
                $message = '<div class="alert success">We sent a 6-digit OTP to ' . htmlspecialchars($email) . '. Please enter it below.</div>';
                // Show the OTP on screen when real email sending is not configured yet,
                // so you can still log in while setting up your brand email account.
                if ((function_exists('is_email_test_mode') && is_email_test_mode())
                    || (function_exists('is_smtp_configured') && !is_smtp_configured())) {
                    $message .= '<div class="alert success">SMTP email not configured yet — local fallback. Your OTP is: <strong>' . htmlspecialchars($sentOtp) . '</strong></div>';
                }
                $_SESSION['pending_login'] = [
                    'user_id' => $user['id'],
                    'name' => $name,
                    'email' => $email,
                ];
                $stage = 'verify';
            } else {
                $message = '<div class="alert error">Unable to send OTP email. Please check your email address or server email settings.</div>';
            }
        }
    } elseif ($stage === 'verify') {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $pending = $_SESSION['pending_login'] ?? null;

        if (!$pending) {
            $message = '<div class="alert error">Please request a new OTP first.</div>';
            $stage = 'request';
        } elseif (!verify_login_otp($pending['user_id'], $enteredOtp)) {
            $message = '<div class="alert error">Invalid OTP code. Please try again.</div>';
            $stage = 'verify';
        } else {
            $_SESSION['customer_user_id'] = $pending['user_id'];
            $_SESSION['customer_name'] = $pending['name'];
            $_SESSION['customer_email'] = $pending['email'];
            $_SESSION['customer_logged_in'] = true;
            unset($_SESSION['pending_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$loggedIn = !empty($_SESSION['customer_logged_in']);
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login | CAR Rentals</title>
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
            <?php if ($loggedIn): ?>
                <a href="login.php?logout=1">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <?php if ($loggedIn): ?>
            <div style="background:white; padding:30px; border-radius:12px; text-align:center; box-shadow:0 5px 15px rgba(0,0,0,0.1);">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</h2>
                <p>You are logged in with <?php echo htmlspecialchars($_SESSION['customer_email']); ?>.</p>
                <a href="cars.php" class="btn">Browse Cars</a>
            </div>
        <?php else: ?>
<form method="POST">
                <h2 style="text-align:center">Customer Login</h2>
                <p style="font-size:0.85rem; color:#666; text-align:center; margin-bottom:14px;">
                    Need help with email OTP login? Watch the guide:
                    <a href="https://youtu.be/k1B89c9LYE0?si=Y8ukwh-JjZIrrdTH" target="_blank" rel="noopener" style="color:#ff6b36;">Email OTP &amp; Invoice Setup Video</a>
                </p>
                <?php echo $message; ?>

                <?php if ($stage === 'verify'): ?>
                    <label>Enter the 6-digit OTP</label>
                    <input type="text" name="otp" placeholder="123456" maxlength="6" required>
                    <input type="hidden" name="stage" value="verify">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    <button type="submit" class="btn" style="width:100%;">Verify OTP</button>
                    <p style="margin-top: 18px; color:#555; text-align:center;">Didn't get the email? Refresh the page and request again.</p>
                <?php else: ?>
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="John Doe" value="<?php echo htmlspecialchars($_SESSION['customer_name'] ?? ''); ?>" required>

                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_SESSION['customer_email'] ?? ''); ?>" required>

                    <input type="hidden" name="stage" value="request">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    <button type="submit" class="btn" style="width:100%;">Send OTP</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
