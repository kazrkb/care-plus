# Patient Module - Project Structure

## Suggested Directory Structure

```
care-plus/
├── backend/
│   ├── src/
│   │   ├── controllers/
│   │   │   ├── auth.controller.js
│   │   │   ├── patient.controller.js
│   │   │   ├── appointment.controller.js
│   │   │   ├── caregiver.controller.js
│   │   │   └── medical-records.controller.js
│   │   ├── models/
│   │   │   ├── Patient.js
│   │   │   ├── Appointment.js
│   │   │   ├── Prescription.js
│   │   │   └── DietPlan.js
│   │   ├── routes/
│   │   │   ├── auth.routes.js
│   │   │   ├── patient.routes.js
│   │   │   ├── appointment.routes.js
│   │   │   └── caregiver.routes.js
│   │   ├── middleware/
│   │   │   ├── auth.middleware.js
│   │   │   └── validation.middleware.js
│   │   ├── utils/
│   │   │   ├── database.js
│   │   │   └── helpers.js
│   │   └── app.js
│   ├── package.json
│   └── .env
│
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   │   ├── patient/
│   │   │   │   ├── Dashboard.jsx
│   │   │   │   ├── Profile.jsx
│   │   │   │   ├── MedicalHistory.jsx
│   │   │   │   └── AppointmentBooking.jsx
│   │   │   ├── common/
│   │   │   │   ├── Header.jsx
│   │   │   │   ├── Sidebar.jsx
│   │   │   │   └── Layout.jsx
│   │   │   └── auth/
│   │   │       ├── Login.jsx
│   │   │       └── Register.jsx
│   │   ├── pages/
│   │   │   ├── patient/
│   │   │   │   ├── dashboard.jsx
│   │   │   │   ├── appointments.jsx
│   │   │   │   ├── medical-records.jsx
│   │   │   │   ├── caregivers.jsx
│   │   │   │   └── profile.jsx
│   │   │   ├── auth/
│   │   │   │   ├── login.jsx
│   │   │   │   └── register.jsx
│   │   │   └── index.jsx
│   │   ├── services/
│   │   │   ├── api.js
│   │   │   ├── auth.service.js
│   │   │   ├── patient.service.js
│   │   │   └── appointment.service.js
│   │   ├── context/
│   │   │   ├── AuthContext.js
│   │   │   └── PatientContext.js
│   │   ├── hooks/
│   │   │   ├── useAuth.js
│   │   │   └── usePatient.js
│   │   └── utils/
│   │       ├── constants.js
│   │       └── helpers.js
│   ├── public/
│   ├── package.json
│   └── .env.local
│
├── healthcare.sql
├── PATIENT_DEVELOPMENT_GUIDE.md
└── README.md
```

## Key Patient Features to Implement

### 1. Authentication & Registration
- Patient login/register
- Profile setup with medical info
- Password reset functionality

### 2. Patient Dashboard
- Overview of health status
- Upcoming appointments
- Recent prescriptions
- Quick action buttons

### 3. Appointment Management
- Search doctors by specialty
- View available time slots
- Book appointments
- Join video consultations
- View appointment history

### 4. Medical Records
- View prescriptions
- Diet plans from nutritionists
- Lab results and health metrics
- Medical history timeline

### 5. Caregiver Services
- Browse available caregivers
- Compare rates and services
- Book caregiver services
- Manage bookings

### 6. Profile & Health Tracking
- Update personal information
- Track health metrics (weight, height, etc.)
- Upload medical documents
- Emergency contact management

## API Endpoints for Patient Module

### Authentication
- POST /api/auth/login
- POST /api/auth/register
- POST /api/auth/logout
- POST /api/auth/reset-password

### Patient Profile
- GET /api/patient/profile
- PUT /api/patient/profile
- GET /api/patient/health-metrics
- PUT /api/patient/health-metrics

### Appointments
- GET /api/appointments/available-doctors
- GET /api/appointments/available-slots/:doctorId
- POST /api/appointments/book
- GET /api/appointments/patient/:patientId
- PUT /api/appointments/:appointmentId
- DELETE /api/appointments/:appointmentId

### Medical Records
- GET /api/medical-records/prescriptions/:patientId
- GET /api/medical-records/diet-plans/:patientId
- GET /api/medical-records/history/:patientId
- GET /api/medical-records/care-plans/:patientId

### Caregiver Services
- GET /api/caregivers/available
- POST /api/caregivers/book
- GET /api/caregivers/bookings/:patientId
- PUT /api/caregivers/bookings/:bookingId

### Feedback
- POST /api/feedback
- GET /api/feedback/patient/:patientId

Would you like me to help you set up any specific part of this structure?
