-- Database initialization for CAR Rentals
-- Run this in phpMyAdmin, MySQL CLI, or another MySQL client.
 CREATE DATABASE IF NOT EXISTS car_rental_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE car_rental_db;

CREATE TABLE IF NOT EXISTS cars (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type VARCHAR(100) NOT NULL,
  price_per_day DECIMAL(10,2) NOT NULL,
  status ENUM('Available', 'Booked', 'Unavailable') NOT NULL DEFAULT 'Available',
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_otps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  otp_code VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  customer_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  car_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Confirmed','Paid','Cancelled') NOT NULL DEFAULT 'Pending',
  payment_method VARCHAR(50) NULL,
  payment_ref VARCHAR(100) NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Sample cars to get started
INSERT INTO cars (name, type, price_per_day, status, description) VALUES
('Toyota Camry', 'Sedan', 55.00, 'Available', 'Comfortable midsize sedan with great fuel economy.'),
('Honda CR-V', 'SUV', 70.00, 'Available', 'Roomy compact SUV perfect for family trips.'),
('BMW 3 Series', 'Luxury', 120.00, 'Available', 'Premium luxury sedan with sporty handling.');

-- ============================================================================
-- Upgrade for existing databases (run these if the bookings table already exists
-- without the status / payment columns)
-- ============================================================================
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS status ENUM('Pending','Confirmed','Paid','Cancelled') NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NULL,
  ADD COLUMN IF NOT EXISTS payment_ref VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL;
