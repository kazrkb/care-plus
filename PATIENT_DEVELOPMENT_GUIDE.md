# Patient Module Development Guide - Care Plus

## Overview
This guide focuses on the patient-related functionality in the Care Plus healthcare management system.

## Patient Database Schema

### Core Patient Tables

#### 1. `users` table (Patient role)
- **userID**: Primary key
- **email**: Unique login identifier
- **password**: Hashed password
- **Name**: Patient full name
- **contactNo**: Phone number
- **role**: Set to 'Patient'

#### 2. `patient` table (Extended patient info)
- **patientID**: References users.userID
- **age**: Patient age
- **height**: Height in cm
- **weight**: Weight in kg
- **gender**: Male/Female

#### 3. `patienthistory` table (Medical history)
- **historyID**: Primary key
- **patientID**: References patient.patientID
- **visitDate**: Date of visit
- **diagnosis**: Medical diagnosis
- **labResults**: Laboratory test results
- **healthMetrics**: Health measurements

## Patient-Related Functionality

### 1. **Appointment Management**
- Book appointments with doctors/nutritionists
- View scheduled appointments
- Join consultation links
- Cancel/reschedule appointments

**Related Tables:**
- `appointment` (patientID, providerID, appointmentDate, status, consultation_link)
- `schedule` (provider availability)

### 2. **Caregiver Services**
- Browse available caregivers
- Book caregiver services (daily/weekly/monthly)
- Manage caregiver bookings

**Related Tables:**
- `caregiverbooking` (patientID, careGiverID, bookingType, dates, amount)
- `caregiver` (caregiver profiles and rates)

### 3. **Medical Records**
- View prescriptions from doctors
- Access diet plans from nutritionists
- Track care plans and therapy instructions
- View medical history

**Related Tables:**
- `prescription` (medicine details, dosage, instructions)
- `dietplan` (nutrition plans, calorie guidelines)
- `careplan` (exercise plans, therapy instructions)

### 4. **Feedback System**
- Rate healthcare providers
- Leave comments and reviews
- View feedback history

**Related Tables:**
- `feedback` (rating, comments, feedback date)

### 5. **Payment Management**
- View transaction history
- Payment for appointments and caregiver services

**Related Tables:**
- `transaction` (payment records for appointments and caregiver bookings)

### 6. **Notifications**
- Receive appointment reminders
- Get updates on bookings and prescriptions

**Related Tables:**
- `notification` (system notifications)

## Sample Patient Data
The database includes a sample patient:
- **Name**: John Doe
- **Email**: john.doe@email.com
- **Age**: 35, Male, 175.5cm, 80.2kg
- **Medical History**: Hypertension diagnosis
- **Active Appointment**: With Dr. Alice Smith (Cardiologist)
- **Prescription**: Aspirin 81mg daily
- **Diet Plan**: Low-carb diet from nutritionist
- **Caregiver Booking**: Weekly physiotherapy

## Development Recommendations

### Frontend Patient Features
1. **Patient Dashboard**
   - Upcoming appointments
   - Recent prescriptions
   - Health metrics overview
   - Quick actions (book appointment, view history)

2. **Appointment Booking**
   - Search doctors by specialty
   - View available time slots
   - Schedule appointments
   - Join video consultations

3. **Medical Records**
   - Prescription history
   - Diet plans
   - Lab results
   - Health progress tracking

4. **Caregiver Services**
   - Browse caregivers by type
   - Compare rates (daily/weekly/monthly)
   - Book services
   - Track service history

5. **Profile Management**
   - Update personal information
   - Health metrics tracking
   - Medical history updates

### Backend Patient APIs
1. **Authentication**
   - Patient login/register
   - Password management
   - Session handling

2. **Profile Management**
   - GET/PUT patient profile
   - Health metrics CRUD
   - Medical history management

3. **Appointments**
   - GET available doctors/slots
   - POST new appointments
   - PUT appointment updates
   - GET appointment history

4. **Medical Records**
   - GET prescriptions
   - GET diet plans
   - GET care plans
   - GET patient history

5. **Caregiver Services**
   - GET available caregivers
   - POST booking requests
   - GET booking history
   - PUT booking updates

6. **Payments**
   - GET transaction history
   - POST payment processing
   - GET payment status

## Technology Stack Suggestions
- **Frontend**: React.js/Next.js with TypeScript
- **Backend**: Node.js with Express/Fastify
- **Database**: MySQL (already defined)
- **Authentication**: JWT tokens
- **Payment**: Stripe/PayPal integration
- **Video Calls**: WebRTC/Zoom SDK

## Next Steps
1. Set up development environment
2. Create patient authentication system
3. Build patient dashboard
4. Implement appointment booking
5. Add medical records management
6. Integrate payment system
