-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2025 at 06:47 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `healthcare`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `adminID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`adminID`) VALUES
(5);

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appointmentID` int(11) NOT NULL,
  `patientID` int(11) DEFAULT NULL,
  `providerID` int(11) DEFAULT NULL,
  `appointmentDate` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `consultation_link` varchar(255) DEFAULT NULL,
  `scheduleID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appointmentID`, `patientID`, `providerID`, `appointmentDate`, `status`, `consultation_link`, `scheduleID`) VALUES
(1, 1, 2, '2025-08-18 10:00:00', 'Scheduled', 'https://meet.example.com/xyz-abc-123', 1);

-- --------------------------------------------------------

--
-- Table structure for table `caregiver`
--

CREATE TABLE `caregiver` (
  `careGiverID` int(11) NOT NULL,
  `careGiverType` varchar(50) DEFAULT NULL,
  `certifications` varchar(255) DEFAULT NULL,
  `dailyRate` decimal(10,2) DEFAULT NULL,
  `weeklyRate` decimal(10,2) DEFAULT NULL,
  `monthlyRate` decimal(10,2) DEFAULT NULL,
  `nidNumber` varchar(20) DEFAULT NULL,
  `nidCopyURL` varchar(255) DEFAULT NULL,
  `certificationURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `caregiver`
--

INSERT INTO `caregiver` (`careGiverID`, `careGiverType`, `certifications`, `dailyRate`, `weeklyRate`, `monthlyRate`, `nidNumber`, `nidCopyURL`, `certificationURL`) VALUES
(4, 'Physiotherapist', 'Certified Physiotherapist', 100.00, 600.00, 2200.00, '1985123456789', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `caregiverbooking`
--

CREATE TABLE `caregiverbooking` (
  `bookingID` int(11) NOT NULL,
  `patientID` int(11) NOT NULL,
  `careGiverID` int(11) NOT NULL,
  `bookingType` enum('Daily','Weekly','Monthly') NOT NULL,
  `startDate` date NOT NULL,
  `endDate` date NOT NULL,
  `totalAmount` decimal(10,2) NOT NULL,
  `status` enum('Scheduled','Active','Completed','Canceled') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `caregiverbooking`
--

INSERT INTO `caregiverbooking` (`bookingID`, `patientID`, `careGiverID`, `bookingType`, `startDate`, `endDate`, `totalAmount`, `status`) VALUES
(1, 1, 4, 'Weekly', '2025-08-18', '2025-08-24', 600.00, 'Scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `careplan`
--

CREATE TABLE `careplan` (
  `planID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `careID` int(11) DEFAULT NULL,
  `exercisePlan` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `therapyInstructions` text DEFAULT NULL,
  `progressNotes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dietplan`
--

CREATE TABLE `dietplan` (
  `planID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `nutritionistID` int(11) DEFAULT NULL,
  `patientID` int(11) DEFAULT NULL,
  `dietType` varchar(50) DEFAULT NULL,
  `caloriesPerDay` decimal(6,2) DEFAULT NULL,
  `mealGuidelines` text DEFAULT NULL,
  `exerciseGuidelines` text DEFAULT NULL,
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dietplan`
--

INSERT INTO `dietplan` (`planID`, `appointmentID`, `nutritionistID`, `patientID`, `dietType`, `caloriesPerDay`, `mealGuidelines`, `exerciseGuidelines`, `startDate`, `endDate`) VALUES
(1, 1, 3, 1, 'Low-Carb', 2000.00, 'Avoid sugar and processed grains. Focus on lean protein and vegetables.', NULL, '2025-08-19', '2025-09-18');

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `doctorID` int(11) NOT NULL,
  `specialty` varchar(50) DEFAULT NULL,
  `licNo` varchar(50) DEFAULT NULL,
  `yearsOfExp` int(11) DEFAULT NULL,
  `consultationFees` decimal(10,2) DEFAULT NULL,
  `nidNumber` varchar(20) DEFAULT NULL,
  `bmdcRegistrationNumber` varchar(50) DEFAULT NULL,
  `licenseExpiryDate` date DEFAULT NULL,
  `hospital` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `medicalSchool` varchar(100) DEFAULT NULL,
  `nidCopyURL` varchar(255) DEFAULT NULL,
  `bmdcCertURL` varchar(255) DEFAULT NULL,
  `medicalLicenseURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`doctorID`, `specialty`, `licNo`, `yearsOfExp`, `consultationFees`, `nidNumber`, `bmdcRegistrationNumber`, `licenseExpiryDate`, `hospital`, `department`, `medicalSchool`, `nidCopyURL`, `bmdcCertURL`, `medicalLicenseURL`) VALUES
(2, 'Cardiology', 'DOC12345', 10, 150.00, '1990123456789', 'BMDC-9876', '2028-12-31', 'General Hospital', 'Cardiology', 'Dhaka Medical College', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedbackID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `patientID` int(11) DEFAULT NULL,
  `providerID` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `feedbackDate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedbackID`, `appointmentID`, `patientID`, `providerID`, `rating`, `comments`, `feedbackDate`) VALUES
(1, 1, 1, 2, 5, 'Dr. Smith was very thorough and explained everything clearly.', '2025-08-18');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notificationID` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sentDate` date DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nutritionist`
--

CREATE TABLE `nutritionist` (
  `nutritionistID` int(11) NOT NULL,
  `specialty` varchar(50) DEFAULT NULL,
  `yearsOfExp` int(11) DEFAULT NULL,
  `consultationFees` decimal(10,2) DEFAULT NULL,
  `nidNumber` varchar(20) DEFAULT NULL,
  `degree` varchar(100) DEFAULT NULL,
  `nidCopyURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nutritionist`
--

INSERT INTO `nutritionist` (`nutritionistID`, `specialty`, `yearsOfExp`, `consultationFees`, `nidNumber`, `degree`, `nidCopyURL`) VALUES
(3, 'Weight Management', 5, 80.00, '1992123456789', 'MPH in Community Nutrition', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `patientID` int(11) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`patientID`, `age`, `height`, `weight`, `gender`) VALUES
(1, 35, 175.50, 80.20, 'Male');

-- --------------------------------------------------------

--
-- Table structure for table `patienthistory`
--

CREATE TABLE `patienthistory` (
  `historyID` int(11) NOT NULL,
  `patientID` int(11) DEFAULT NULL,
  `visitDate` date DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `labResults` text DEFAULT NULL,
  `healthMetrics` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patienthistory`
--

INSERT INTO `patienthistory` (`historyID`, `patientID`, `visitDate`, `diagnosis`, `labResults`, `healthMetrics`) VALUES
(1, 1, '2025-08-18', 'Hypertension', 'Blood pressure: 140/90 mmHg', 'Weight: 80.2kg');

-- --------------------------------------------------------

--
-- Table structure for table `prescription`
--

CREATE TABLE `prescription` (
  `prescriptionID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `doctorID` int(11) DEFAULT NULL,
  `medicineName` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription`
--

INSERT INTO `prescription` (`prescriptionID`, `appointmentID`, `doctorID`, `medicineName`, `dosage`, `instructions`, `date`) VALUES
(1, 1, 2, 'Aspirin', '81mg', 'Take one tablet daily with food.', '2025-08-18');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `scheduleID` int(11) NOT NULL,
  `providerID` int(11) DEFAULT NULL,
  `availableDate` date DEFAULT NULL,
  `startTime` time DEFAULT NULL,
  `endTime` time DEFAULT NULL,
  `slotDuration` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`scheduleID`, `providerID`, `availableDate`, `startTime`, `endTime`, `slotDuration`, `status`) VALUES
(1, 2, '2025-08-18', '09:00:00', '17:00:00', 30, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `transactionID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `careGiverBookingID` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transactionType` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `transaction`
--

INSERT INTO `transaction` (`transactionID`, `appointmentID`, `careGiverBookingID`, `amount`, `transactionType`, `status`, `timestamp`) VALUES
(1, 1, NULL, 150.00, 'Card', 'Paid', '2025-08-11 22:10:00'),
(2, NULL, 1, 600.00, 'Mobile Banking', 'Paid', '2025-08-11 22:11:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `contactNo` varchar(20) DEFAULT NULL,
  `role` enum('Patient','Doctor','Nutritionist','CareGiver','Admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `email`, `password`, `Name`, `contactNo`, `role`) VALUES
(1, 'john.doe@email.com', 'hashed_password_1', 'John Doe', '111-222-3333', 'Patient'),
(2, 'dr.smith@email.com', 'hashed_password_2', 'Alice Smith', '222-333-4444', 'Doctor'),
(3, 'susan.j@email.com', 'hashed_password_3', 'Susan Jones', '333-444-5555', 'Nutritionist'),
(4, 'bob.p@email.com', 'hashed_password_4', 'Bob Parker', '444-555-6666', 'CareGiver'),
(5, 'admin@system.com', 'hashed_password_5', 'Admin User', '555-666-7777', 'Admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`adminID`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appointmentID`),
  ADD KEY `patientID` (`patientID`),
  ADD KEY `providerID` (`providerID`),
  ADD KEY `scheduleID` (`scheduleID`);

--
-- Indexes for table `caregiver`
--
ALTER TABLE `caregiver`
  ADD PRIMARY KEY (`careGiverID`);

--
-- Indexes for table `caregiverbooking`
--
ALTER TABLE `caregiverbooking`
  ADD PRIMARY KEY (`bookingID`),
  ADD KEY `patientID` (`patientID`),
  ADD KEY `careGiverID` (`careGiverID`);

--
-- Indexes for table `careplan`
--
ALTER TABLE `careplan`
  ADD PRIMARY KEY (`planID`),
  ADD KEY `appointmentID` (`appointmentID`),
  ADD KEY `careID` (`careID`);

--
-- Indexes for table `dietplan`
--
ALTER TABLE `dietplan`
  ADD PRIMARY KEY (`planID`),
  ADD KEY `appointmentID` (`appointmentID`),
  ADD KEY `nutritionistID` (`nutritionistID`),
  ADD KEY `patientID` (`patientID`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`doctorID`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedbackID`),
  ADD KEY `appointmentID` (`appointmentID`),
  ADD KEY `patientID` (`patientID`),
  ADD KEY `providerID` (`providerID`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `nutritionist`
--
ALTER TABLE `nutritionist`
  ADD PRIMARY KEY (`nutritionistID`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`patientID`);

--
-- Indexes for table `patienthistory`
--
ALTER TABLE `patienthistory`
  ADD PRIMARY KEY (`historyID`),
  ADD KEY `patientID` (`patientID`);

--
-- Indexes for table `prescription`
--
ALTER TABLE `prescription`
  ADD PRIMARY KEY (`prescriptionID`),
  ADD KEY `appointmentID` (`appointmentID`),
  ADD KEY `doctorID` (`doctorID`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`scheduleID`),
  ADD KEY `providerID` (`providerID`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`transactionID`),
  ADD KEY `appointmentID` (`appointmentID`),
  ADD KEY `careGiverBookingID` (`careGiverBookingID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `caregiverbooking`
--
ALTER TABLE `caregiverbooking`
  MODIFY `bookingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `careplan`
--
ALTER TABLE `careplan`
  MODIFY `planID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dietplan`
--
ALTER TABLE `dietplan`
  MODIFY `planID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notificationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patienthistory`
--
ALTER TABLE `patienthistory`
  MODIFY `historyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `prescription`
--
ALTER TABLE `prescription`
  MODIFY `prescriptionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `scheduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `transactionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`adminID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `appointment_ibfk_1` FOREIGN KEY (`patientID`) REFERENCES `patient` (`patientID`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_ibfk_2` FOREIGN KEY (`providerID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_ibfk_3` FOREIGN KEY (`scheduleID`) REFERENCES `schedule` (`scheduleID`) ON DELETE SET NULL;

--
-- Constraints for table `caregiver`
--
ALTER TABLE `caregiver`
  ADD CONSTRAINT `caregiver_ibfk_1` FOREIGN KEY (`careGiverID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `caregiverbooking`
--
ALTER TABLE `caregiverbooking`
  ADD CONSTRAINT `caregiverbooking_ibfk_1` FOREIGN KEY (`patientID`) REFERENCES `patient` (`patientID`) ON DELETE CASCADE,
  ADD CONSTRAINT `caregiverbooking_ibfk_2` FOREIGN KEY (`careGiverID`) REFERENCES `caregiver` (`careGiverID`) ON DELETE CASCADE;

--
-- Constraints for table `careplan`
--
ALTER TABLE `careplan`
  ADD CONSTRAINT `careplan_ibfk_1` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `careplan_ibfk_2` FOREIGN KEY (`careID`) REFERENCES `caregiver` (`careGiverID`) ON DELETE SET NULL;

--
-- Constraints for table `dietplan`
--
ALTER TABLE `dietplan`
  ADD CONSTRAINT `dietplan_ibfk_1` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `dietplan_ibfk_2` FOREIGN KEY (`nutritionistID`) REFERENCES `nutritionist` (`nutritionistID`) ON DELETE SET NULL,
  ADD CONSTRAINT `dietplan_ibfk_3` FOREIGN KEY (`patientID`) REFERENCES `patient` (`patientID`) ON DELETE CASCADE;

--
-- Constraints for table `doctor`
--
ALTER TABLE `doctor`
  ADD CONSTRAINT `doctor_ibfk_1` FOREIGN KEY (`doctorID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`patientID`) REFERENCES `patient` (`patientID`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`providerID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `nutritionist`
--
ALTER TABLE `nutritionist`
  ADD CONSTRAINT `nutritionist_ibfk_1` FOREIGN KEY (`nutritionistID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `patient_ibfk_1` FOREIGN KEY (`patientID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `patienthistory`
--
ALTER TABLE `patienthistory`
  ADD CONSTRAINT `patienthistory_ibfk_1` FOREIGN KEY (`patientID`) REFERENCES `patient` (`patientID`) ON DELETE CASCADE;

--
-- Constraints for table `prescription`
--
ALTER TABLE `prescription`
  ADD CONSTRAINT `prescription_ibfk_1` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_ibfk_2` FOREIGN KEY (`doctorID`) REFERENCES `doctor` (`doctorID`) ON DELETE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`providerID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `transaction`
--
ALTER TABLE `transaction`
  ADD CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`careGiverBookingID`) REFERENCES `caregiverbooking` (`bookingID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
