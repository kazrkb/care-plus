-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2025 at 10:15 AM
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
(26);

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
  `scheduleID` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appointmentID`, `patientID`, `providerID`, `appointmentDate`, `status`, `consultation_link`, `scheduleID`, `notes`) VALUES
(3, 30, 24, '2025-08-10 11:00:00', 'Completed', NULL, NULL, 'Consultation for minor skin rash. Prescription was provided.'),
(4, 31, 27, '2025-08-18 17:30:00', 'Completed', 'https://meet.google.com/xyz-abc-pqr', 24, 'Scheduled video consultation for blood pressure follow-up.'),
(5, 31, 24, '2025-08-25 09:00:00', 'Scheduled', 'https://meet.google.com/mhq-dkxf-ejh', NULL, 'Requesting a general check-up. Available any morning next week.'),
(6, 30, 27, '2025-08-21 16:00:00', 'Canceled', NULL, 25, 'Patient canceled due to a personal emergency.');

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
(25, 'Nurse', 'register RN', 1000.00, 6000.00, 30000.00, '2131231321', NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `careplan`
--

CREATE TABLE `careplan` (
  `planID` int(11) NOT NULL,
  `bookingID` int(11) DEFAULT NULL,
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
(24, 'Cardiology', '123-W2', 5, 500.00, '12123131312', '14324234', '2036-01-12', 'Exim Bank hospital', 'cardiology deartment', 'Dhaka Medical', NULL, NULL, NULL),
(27, 'General Medicine', '3421342', 15, 800.00, '2323123', '3423423', '2025-08-13', 'Islamic Hospital', 'Medicine', 'Dhaka Medical', NULL, NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `medicaldocuments`
--

CREATE TABLE `medicaldocuments` (
  `documentID` int(11) NOT NULL,
  `historyID` int(11) NOT NULL,
  `documentURL` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(23, 'Public Health Nutrition', 7, 700.00, '13442323', '0', NULL);

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
(30, 24, 160.00, 65.00, 'Female'),
(31, 58, 180.00, 70.00, 'Male');

-- --------------------------------------------------------

--
-- Table structure for table `patienthistory`
--

CREATE TABLE `patienthistory` (
  `historyID` int(11) NOT NULL,
  `patientID` int(11) DEFAULT NULL,
  `visitDate` date DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `labResultsFile` varchar(255) DEFAULT NULL,
  `medicalHistory` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription`
--

CREATE TABLE `prescription` (
  `prescriptionID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `doctorID` int(11) DEFAULT NULL,
  `medicineNames-dosages` varchar(255) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescription`
--

INSERT INTO `prescription` (`prescriptionID`, `appointmentID`, `doctorID`, `medicineNames-dosages`, `instructions`, `date`) VALUES
(2, 4, 27, 'Feza', 'Aa', '2025-08-16');

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
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`scheduleID`, `providerID`, `availableDate`, `startTime`, `endTime`, `status`) VALUES
(4, 24, '2025-08-28', '03:57:00', '04:58:00', 'Available'),
(5, 24, '2025-08-28', '03:57:00', '04:58:00', 'Rescheduled'),
(18, 27, '2025-08-21', '06:42:00', '02:46:00', 'Rescheduled'),
(24, 27, '2025-08-18', '17:00:00', '20:00:00', 'Available'),
(25, 27, '2025-08-21', '04:00:00', '19:00:00', 'Available'),
(26, 24, '2025-08-24', '07:00:00', '21:00:00', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `transactionID` int(11) NOT NULL,
  `appointmentID` int(11) DEFAULT NULL,
  `careProviderBookingID` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transactionType` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL
) ;

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
  `role` enum('Patient','Doctor','Nutritionist','CareGiver','Admin') NOT NULL,
  `profilePhoto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `email`, `password`, `Name`, `contactNo`, `role`, `profilePhoto`) VALUES
(23, 'n@gmail.com', '$2y$10$gx.JzXM2U1l.PSDImfxUQu./i56vUIKX4CWO8WKv5p6rQ6Z7oi/da', 'Sadia Ahmed', '01222222', 'Nutritionist', 'uploads/1755017853_474554607_2669300239933381_4912127688215262215_n.jpg'),
(24, 'd@gmail.com', '$2y$10$en0WF2jyoScW2QrGzwuDyOTra8LLfbq98ptnmi2Rht2ttqS9ZG6Bi', 'Maliha Epsy', '08991822112', 'Doctor', 'uploads/1755018046_IMG_20210929_133222.jpg'),
(25, 'c@gmail.com', '$2y$10$u0cydwtMJwG/M6i4/lTdwuevrKUu5zvafVW9gw.fCTUYWMjtvkSNe', 'Tasdik Ahmed', '134124324', 'CareGiver', 'uploads/1755018590_man.webp'),
(26, 'a@gmail.com', '$2y$10$9WnNhjzz9y7jerTdcNiCme9vPjOX3ooDpWhcvA8ZMBe/un2.oKB.C', 'Jon Snow', '0131412341', 'Admin', 'uploads/1755018679_BMDC.png'),
(27, 'dipu@gmail.com', '$2y$10$41l7O3zjGo0FeP84WVSZFu9zscb5Hd.aexzi4/d2vxYOBOC3.q8tC', 'Tawfiq Dipu', '01222222', 'Doctor', 'uploads/profile_27_1755297453.webp'),
(28, 'ra@gmail.com', '$2y$10$13Y0yWZ0no2rpIHNArpSqunErvniOppn4qHleSQU1OaeFOGoiF.vC', 'Rakib', '23123423', 'Doctor', NULL),
(30, 'p@gmail.com', '$2y$10$XeLwnSVMB5m27Fiu2l0h5urNC.OzlgkrT5k1yQA3IeG4P8/E1c5Pi', 'Parizad Sifa', '01231234231', 'Patient', NULL),
(31, 'jo@gmail.com', '$2y$10$9njCOMXXwcHmZ11ff3LLyeeP4ECCT2XEJXyWTdBvD4gwmp0DQ5YVe', 'Jolil Mia', '0189288922', 'Patient', 'uploads/1755293488_man.webp');

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
  ADD KEY `careID` (`careID`),
  ADD KEY `careplan_ibfk_1` (`bookingID`);

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
-- Indexes for table `medicaldocuments`
--
ALTER TABLE `medicaldocuments`
  ADD PRIMARY KEY (`documentID`),
  ADD KEY `historyID` (`historyID`);

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
  ADD KEY `careGiverBookingID` (`careProviderBookingID`);

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
  MODIFY `appointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT for table `medicaldocuments`
--
ALTER TABLE `medicaldocuments`
  MODIFY `documentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `prescriptionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `scheduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `transactionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
  ADD CONSTRAINT `careplan_ibfk_1` FOREIGN KEY (`bookingID`) REFERENCES `caregiverbooking` (`bookingID`) ON DELETE CASCADE,
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
-- Constraints for table `medicaldocuments`
--
ALTER TABLE `medicaldocuments`
  ADD CONSTRAINT `medicaldocuments_ibfk_1` FOREIGN KEY (`historyID`) REFERENCES `patienthistory` (`historyID`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`careProviderBookingID`) REFERENCES `caregiverbooking` (`bookingID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
