SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


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

INSERT INTO `users` (`user_id`, `username`, `password`, `fullname`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$SsDy4qm6BU9iWULXzq4ysOtu01ku5voUIdBJCx8dPlmuUnb2nqCzi', 'luigi andrei gordevilla', 'Admin', '2026-05-31 06:03:04'),
(2, 'customer', '$2y$10$qZAJ7MVvVSdgqyA0ok/k1.ypbSkAYSYtg2peCL/.y7AI.KJyKP4wW', 'juan dela cruz', 'User', '2026-05-31 06:03:41');

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
COMMIT;