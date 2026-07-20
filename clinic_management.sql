SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `date_registered` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patients` (`patient_id`, `fullname`, `age`, `gender`, `address`, `contact_number`, `date_registered`) VALUES
(1, 'juan dela cruz', 19, 'Male', 'bacoor', '090909090909', '2026-05-31 06:05:18');

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `specialization` varchar(50) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `max_patients` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctors` (`doctor_id`, `doctor_name`, `specialization`, `schedule`, `max_patients`) VALUES
(1, 'Dr. Maria Santos', 'General Physician', 'Monday – Wednesday 8:00 AM – 2:00 PM', 15),
(2, 'Dr. John Reyes', 'Cardiologist', 'Tuesday – Thursday 1:00 PM – 7:00 PM', 10),
(3, 'Dr. Angela Cruz', 'Radiologist', 'Friday – Saturday 9:00 AM – 5:00 PM', 12);

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('Scheduled','Completed','Cancelled','Pending') DEFAULT 'Scheduled',
  `lab_required` enum('Yes','No') DEFAULT 'No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `status`, `lab_required`, `created_at`) VALUES
(1, 1, 1, '2026-06-01 09:30:00', 'Pending', 'No', '2026-05-31 08:23:43'),
(2, 1, 3, '2026-06-05 09:03:00', 'Pending', 'No', '2026-05-31 08:24:11');

CREATE TABLE `laboratory` (
  `lab_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `laboratory_type` varchar(100) NOT NULL,
  `status` enum('Not Yet Taken','Ongoing','Completed') DEFAULT 'Not Yet Taken',
  `result` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `laboratory` (`lab_id`, `patient_id`, `laboratory_type`, `status`, `result`, `created_at`) VALUES
(1, 1, 'X-ray', 'Completed', 'goods', '2026-05-31 06:07:06'),
(2, 1, 'From Appointment', 'Not Yet Taken', NULL, '2026-05-31 08:23:43'),
(3, 1, 'From Appointment', 'Not Yet Taken', NULL, '2026-05-31 08:24:11');

CREATE TABLE `medicines` (
  `medicine_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `status` enum('Not Taken','Taken') DEFAULT 'Not Taken',
  `prescription_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 500.00,
  `laboratory_fee` decimal(10,2) DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`consultation_fee` + `laboratory_fee`) STORED,
  `payment_status` enum('Unpaid','Partial','Paid') DEFAULT 'Unpaid',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` (`payment_id`, `patient_id`, `consultation_fee`, `laboratory_fee`, `amount_paid`, `payment_status`, `payment_date`) VALUES
(1, 1, 500.00, 300.00, 0.00, 'Unpaid', '2026-05-31 06:05:18');

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('Admin','User') DEFAULT 'User',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`);

ALTER TABLE `laboratory`
  ADD PRIMARY KEY (`lab_id`),
  ADD KEY `patient_id` (`patient_id`);

ALTER TABLE `medicines`
  ADD PRIMARY KEY (`medicine_id`),
  ADD KEY `fk_medicine_patient` (`patient_id`);

ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`);

ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `patient_id` (`patient_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `laboratory`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `medicines`
  MODIFY `medicine_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`);

ALTER TABLE `laboratory`
  ADD CONSTRAINT `laboratory_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE;

ALTER TABLE `medicines`
  ADD CONSTRAINT `fk_medicine_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE;

ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE;

 -- ============================================
-- COMPLETE DATABASE MIGRATION
-- Clinic Management System
-- (No Google Calendar)
-- Run this script once to update your database
-- ============================================

-- ============================================
-- 1. PATIENTS TABLE
-- ============================================

-- Add archive and extended fields
ALTER TABLE patients 
ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS civil_status VARCHAR(30) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS citizenship VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS place_of_birth VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS first_name VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS middle_name VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS suffix VARCHAR(20) DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_patients_archived ON patients(is_archived);

ALTER TABLE appointments
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS lab_tests TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS lab_fee_total DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS med_tests TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS med_fee_total DECIMAL(10,2) DEFAULT 0.00;

CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);

ALTER TABLE doctors
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

ALTER TABLE medicines
ADD COLUMN IF NOT EXISTS quantity INT(11) DEFAULT 1,
ADD COLUMN IF NOT EXISTS unit VARCHAR(20) DEFAULT 'pcs',
ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS reorder_level INT(11) DEFAULT 5,
ADD COLUMN IF NOT EXISTS supplier VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS batch_number VARCHAR(50) DEFAULT NULL;

ALTER TABLE laboratory
ADD COLUMN IF NOT EXISTS doctor_id INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS appointment_id INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS appointment_date DATE DEFAULT NULL,
ADD COLUMN IF NOT EXISTS appointment_time TIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS procedure_name VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS procedure_fee DECIMAL(10,2) DEFAULT 0.00;

-- Add foreign key for appointment_id (if not already exists)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'laboratory' 
                  AND CONSTRAINT_NAME LIKE 'laboratory_ibfk_%' 
                  AND COLUMN_NAME = 'appointment_id');
IF @fk_exists = 0 THEN
    ALTER TABLE laboratory ADD CONSTRAINT fk_lab_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL;
END IF;

-- Indexes for patient and appointment lookups
CREATE INDEX IF NOT EXISTS idx_lab_patient ON laboratory(patient_id);
CREATE INDEX IF NOT EXISTS idx_lab_appointment ON laboratory(appointment_id);


-- ============================================
-- 6. USERS TABLE (Patient Portal)
-- ============================================

ALTER TABLE users
ADD COLUMN IF NOT EXISTS patient_id INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS security_question VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS security_answer VARCHAR(255) DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_users_patient ON users(patient_id);


CREATE TABLE IF NOT EXISTS settings (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default email settings
INSERT INTO settings (setting_key, setting_value) VALUES 
('email_username', 'your-email@gmail.com'),
('email_password', 'your-app-password'),
('email_from_name', 'Clinic Management System'),
('email_host', 'smtp.gmail.com'),
('email_port', '465'),
('email_encryption', 'ssl')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS transactions (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(50) UNIQUE NOT NULL,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) DEFAULT NULL,
    appointment_id INT(11) DEFAULT NULL,
    user_id INT(11) NOT NULL,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    lab_fee DECIMAL(10,2) DEFAULT 0.00,
    medicine_fee DECIMAL(10,2) DEFAULT 0.00,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    change_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_method VARCHAR(50) DEFAULT 'Cash',
    payment_status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Refunded') DEFAULT 'Unpaid',
    reference_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_refunded TINYINT(1) DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_transactions_patient ON transactions(patient_id);
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(payment_status);

CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    type ENUM('appointment', 'lab', 'medicine', 'payment', 'system') DEFAULT 'system',
    message TEXT NOT NULL,
    url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);

CREATE TABLE IF NOT EXISTS inventory_logs (
    log_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT(11) NOT NULL,
    action ENUM('add', 'subtract', 'adjust', 'expired') NOT NULL,
    quantity_change INT(11) NOT NULL,
    previous_quantity INT(11) NOT NULL,
    new_quantity INT(11) NOT NULL,
    reason TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_inventory_medicine ON inventory_logs(medicine_id);

UPDATE patients SET contact_number = SUBSTRING(contact_number, 2) WHERE contact_number LIKE '0%' AND LENGTH(contact_number) = 11;

UPDATE patients 
SET 
    first_name = SUBSTRING_INDEX(fullname, ' ', 1),
    last_name = SUBSTRING_INDEX(fullname, ' ', -1),
    middle_name = CASE 
        WHEN LENGTH(fullname) - LENGTH(REPLACE(fullname, ' ', '')) >= 2 
        THEN SUBSTRING_INDEX(SUBSTRING_INDEX(fullname, ' ', 2), ' ', -1)
        ELSE NULL 
    END
WHERE first_name IS NULL OR first_name = '';

COMMIT;