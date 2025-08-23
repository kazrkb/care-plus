-- Simple corrected sample data for patients
USE healthcare;

-- Add prescriptions for patients using correct appointment and doctor IDs
INSERT INTO prescription (appointmentID, doctorID, `medicineNames-dosages`, instructions, date) VALUES
(27, 27, 'Amlodipine 5mg - Once daily, Lisinopril 10mg - Once daily', 'Take with food. Monitor blood pressure daily.', '2025-07-10'),
(28, 24, 'Metformin 500mg - Twice daily, Glipizide 5mg - Once daily', 'Take before meals. Check blood sugar regularly.', '2025-07-15'),
(29, 27, 'Ibuprofen 400mg - As needed, Diclofenac gel - Twice daily', 'Apply gel to affected area. Take ibuprofen only when needed for pain.', '2025-08-01'),
(18, 27, 'Atorvastatin 20mg - Once daily', 'Take at bedtime. Monitor cholesterol levels.', '2025-07-10'),
(19, 24, 'Vitamin D3 1000IU - Once daily', 'Take with meals for better absorption.', '2025-07-15');

-- Add caregiver availability slots
INSERT INTO caregiver_availability (careGiverID, bookingType, startDate, status) VALUES
(25, 'Weekly', '2025-08-20', 'Booked'),
(32, 'Daily', '2025-08-24', 'Booked'), 
(25, 'Monthly', '2025-09-01', 'Booked'),
(25, 'Weekly', '2025-08-25', 'Available'),
(25, 'Weekly', '2025-09-08', 'Available'),
(32, 'Daily', '2025-08-25', 'Available'),
(32, 'Daily', '2025-08-26', 'Available');

-- Get availability IDs for caregiver bookings
SET @avail1 = (SELECT availabilityID FROM caregiver_availability WHERE careGiverID = 25 AND startDate = '2025-08-20' AND bookingType = 'Weekly' LIMIT 1);
SET @avail2 = (SELECT availabilityID FROM caregiver_availability WHERE careGiverID = 32 AND startDate = '2025-08-24' AND bookingType = 'Daily' LIMIT 1);
SET @avail3 = (SELECT availabilityID FROM caregiver_availability WHERE careGiverID = 25 AND startDate = '2025-09-01' AND bookingType = 'Monthly' LIMIT 1);

-- Add caregiver bookings for patients
INSERT INTO caregiverbooking (patientID, careGiverID, bookingType, startDate, endDate, totalAmount, status, availabilityID) VALUES
(30, 25, 'Weekly', '2025-08-20', '2025-08-27', 2500.00, 'Active', @avail1),
(31, 32, 'Daily', '2025-08-24', '2025-08-24', 500.00, 'Completed', @avail2),
(33, 25, 'Monthly', '2025-09-01', '2025-09-30', 8000.00, 'Scheduled', @avail3);

-- Add notifications for patients
INSERT INTO notification (userID, type, message, sentDate, status) VALUES
(30, 'Appointment', 'Your appointment with Dr. Tawfiq Dipu is scheduled for August 25, 2025 at 10:00 AM', '2025-08-23', 'Unread'),
(31, 'Prescription', 'New prescription has been added to your medical history', '2025-08-22', 'Unread'),
(33, 'Caregiver', 'Your caregiver booking has been confirmed for September 2025', '2025-08-20', 'Read'),
(30, 'Health', 'Reminder: Take your blood pressure medication daily', '2025-08-24', 'Unread'),
(31, 'Health', 'Your diabetes follow-up appointment is due next week', '2025-08-23', 'Unread');

-- Add some feedback from patients
INSERT INTO feedback (patientID, providerID, rating, comments, feedbackDate) VALUES
(30, 27, 5, 'Excellent doctor! Very thorough examination and clear explanations.', '2025-07-11'),
(31, 24, 4, 'Good consultation. Helpful advice on diabetes management.', '2025-07-16'),
(33, 27, 5, 'Great experience. Doctor was very understanding about my sports injury.', '2025-08-02'),
(30, 25, 5, 'Outstanding caregiver service. Very professional and caring.', '2025-08-21');
