-- Sample patient data for CarePlus Healthcare System
USE healthcare;

-- Update existing patients with height and weight
UPDATE patient SET height = 165.0, weight = 60.0 WHERE patientID = 30;
UPDATE patient SET height = 170.5, weight = 75.5 WHERE patientID = 31;
UPDATE patient SET height = 175.2, weight = 82.0 WHERE patientID = 33;

-- Add medical history for existing patients
INSERT INTO patienthistory (patientID, visitDate, diagnosis, medicalHistory) VALUES
(30, '2025-01-15', 'Hypertension', 'Patient presented with elevated blood pressure. Prescribed medication and lifestyle changes.'),
(30, '2025-03-20', 'Follow-up Hypertension', 'Blood pressure improving with medication. Continue current treatment.'),
(30, '2025-07-10', 'Annual Checkup', 'General health good. Blood pressure well controlled. Recommended regular exercise.'),
(31, '2025-02-05', 'Type 2 Diabetes', 'Newly diagnosed diabetes. Started on metformin. Dietary counseling provided.'),
(31, '2025-04-15', 'Diabetes Follow-up', 'Blood sugar levels improving. Continue medication and diet plan.'),
(31, '2025-08-01', 'Diabetes Management', 'HbA1c levels within target range. Patient compliance excellent.'),
(33, '2025-01-25', 'Back Pain', 'Lower back pain due to poor posture. Prescribed physiotherapy.'),
(33, '2025-05-12', 'Sports Injury', 'Sprained ankle during football. Rest and physiotherapy recommended.'),
(33, '2025-08-15', 'Routine Checkup', 'Overall health excellent. All vitals normal.');

-- Add appointments for patients with doctors
INSERT INTO appointment (patientID, providerID, appointmentDate, status, notes) VALUES
(30, 27, '2025-08-25 10:00:00', 'Scheduled', 'Regular checkup appointment'),
(30, 27, '2025-09-15 14:30:00', 'Scheduled', 'Follow-up for blood pressure monitoring'),
(31, 28, '2025-08-26 09:00:00', 'Scheduled', 'Diabetes management consultation'),
(31, 28, '2025-09-20 11:00:00', 'Scheduled', 'Review blood sugar levels and medication'),
(33, 27, '2025-08-27 15:00:00', 'Scheduled', 'Sports medicine consultation'),
(33, 28, '2025-09-10 16:00:00', 'Scheduled', 'General health checkup'),
-- Past appointments
(30, 27, '2025-07-10 10:00:00', 'Completed', 'Annual health screening completed'),
(31, 28, '2025-07-15 14:00:00', 'Completed', 'Diabetes follow-up completed'),
(33, 27, '2025-08-01 09:30:00', 'Completed', 'Back pain consultation completed');

-- Add prescriptions for patients
INSERT INTO prescription (appointmentID, doctorID, `medicineNames-dosages`, instructions, date) VALUES
(7, 27, 'Amlodipine 5mg - Once daily, Lisinopril 10mg - Once daily', 'Take with food. Monitor blood pressure daily.', '2025-07-10'),
(8, 28, 'Metformin 500mg - Twice daily, Glipizide 5mg - Once daily', 'Take before meals. Check blood sugar regularly.', '2025-07-15'),
(9, 27, 'Ibuprofen 400mg - As needed, Diclofenac gel - Twice daily', 'Apply gel to affected area. Take ibuprofen only when needed for pain.', '2025-08-01'),
(1, 27, 'Atorvastatin 20mg - Once daily', 'Take at bedtime. Monitor cholesterol levels.', '2025-08-20'),
(3, 28, 'Vitamin D3 1000IU - Once daily', 'Take with meals for better absorption.', '2025-08-22');

-- Add caregiver bookings for patients
INSERT INTO caregiverbooking (patientID, careGiverID, bookingType, startDate, endDate, totalAmount, status, availabilityID) VALUES
(30, 25, 'Weekly', '2025-08-20', '2025-08-27', 2500.00, 'Active', 1),
(31, 32, 'Daily', '2025-08-24', '2025-08-24', 500.00, 'Completed', 2),
(33, 25, 'Monthly', '2025-09-01', '2025-09-30', 8000.00, 'Scheduled', 3);

-- Add some caregiver availability slots
INSERT INTO caregiver_availability (careGiverID, startDate, endDate, startTime, endTime, status) VALUES
(25, '2025-08-25', '2025-08-31', '08:00:00', '16:00:00', 'Available'),
(25, '2025-09-01', '2025-09-07', '09:00:00', '17:00:00', 'Available'),
(32, '2025-08-25', '2025-08-25', '10:00:00', '18:00:00', 'Available'),
(32, '2025-08-26', '2025-08-26', '08:00:00', '16:00:00', 'Available');

-- Add notifications for patients
INSERT INTO notification (userID, type, message, sentDate, status) VALUES
(30, 'Appointment', 'Your appointment with Dr. Rahman is scheduled for August 25, 2025 at 10:00 AM', '2025-08-23', 'Unread'),
(31, 'Prescription', 'New prescription has been added to your medical history', '2025-08-22', 'Unread'),
(33, 'Caregiver', 'Your caregiver booking has been confirmed for September 2025', '2025-08-20', 'Read'),
(30, 'Health', 'Reminder: Take your blood pressure medication daily', '2025-08-24', 'Unread'),
(31, 'Health', 'Your diabetes follow-up appointment is due next week', '2025-08-23', 'Unread');

-- Add some feedback from patients
INSERT INTO feedback (patientID, providerID, rating, comments, feedbackDate) VALUES
(30, 27, 5, 'Excellent doctor! Very thorough examination and clear explanations.', '2025-07-11'),
(31, 28, 4, 'Good consultation. Helpful advice on diabetes management.', '2025-07-16'),
(33, 27, 5, 'Great experience. Doctor was very understanding about my sports injury.', '2025-08-02'),
(30, 25, 5, 'Outstanding caregiver service. Very professional and caring.', '2025-08-21');
