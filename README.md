# update-for-namaste

CAR Rentals booking system with QR payment, invoice generation, and countdown timer.

## Project Structure

All application files are located in the `car-rental/` directory:

```
car-rental/
├── admin.php               # Admin dashboard
├── booking.php            # Booking form / reservation
├── car.php                # Single car view
├── cars.php               # Cars listing
├── config.php             # DB config, payment & GST settings, email helpers
├── database.sql           # Database schema
├── fpdf.php               # FPDF library
├── index.php              # Home page
├── invoice.php            # Invoice display page
├── invoice_download.php   # PDF invoice download
├── invoice_pdf.php        # PDF invoice generator
├── login.php              # OTP login
├── make_logo.php          # Logo generator
├── payment.php            # Payment page (QR + countdown timer)
├── script.js
├── style.css
├── font/                  # FPDF fonts
├── mailer/                # PHPMailer
├── invoices/              # Generated invoice PDFs
└── email_debug.log        # Debug email log (when SMTP not configured)
```

## Features
- OTP-based login
- Car booking with GST calculation
- UPI QR payment with a 30-second verification countdown timer
- Automatic invoice generation (PDF) + email after successful payment
- Download / print invoice
