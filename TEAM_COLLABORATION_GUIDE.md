# Care Plus - Collaborative Project Structure

## Team Organization & File Naming Convention

### Team Roles (6 Members)
1. **Patient Module Developer** - Frontend & Backend for Patient features
2. **Doctor Module Developer** - Frontend & Backend for Doctor features  
3. **Nutritionist Module Developer** - Frontend & Backend for Nutritionist features
4. **Caregiver Module Developer** - Frontend & Backend for Caregiver features
5. **Admin Module Developer** - Frontend & Backend for Admin features
6. **System Integration Developer** - Database, API integration, deployment

## Project Directory Structure

```
care-plus/
├── README.md
├── healthcare.sql
├── docs/
│   ├── PROJECT_OVERVIEW.md
│   ├── DATABASE_SCHEMA.md
│   ├── API_DOCUMENTATION.md
│   └── DEVELOPMENT_GUIDELINES.md
│
├── frontend/
│   ├── shared/
│   │   ├── css/
│   │   │   ├── global-styles.css
│   │   │   └── component-styles.css
│   │   ├── js/
│   │   │   ├── shared-utilities.js
│   │   │   ├── api-client.js
│   │   │   └── auth-handler.js
│   │   └── components/
│   │       ├── header-navigation.html
│   │       ├── footer.html
│   │       └── notification-system.html
│   │
│   ├── patient/
│   │   ├── pages/
│   │   │   ├── patient-dashboard.html
│   │   │   ├── patient-login.html
│   │   │   ├── patient-register.html
│   │   │   ├── patient-profile.html
│   │   │   ├── patient-appointments.html
│   │   │   ├── patient-medical-records.html
│   │   │   └── patient-caregiver-booking.html
│   │   ├── js/
│   │   │   ├── patient-dashboard.js
│   │   │   ├── patient-auth.js
│   │   │   ├── patient-appointments.js
│   │   │   └── patient-booking.js
│   │   └── css/
│   │       └── patient-styles.css
│   │
│   ├── doctor/
│   │   ├── pages/
│   │   │   ├── doctor-dashboard.html
│   │   │   ├── doctor-login.html
│   │   │   ├── doctor-profile.html
│   │   │   ├── doctor-appointments.html
│   │   │   ├── doctor-patients.html
│   │   │   └── doctor-prescriptions.html
│   │   ├── js/
│   │   │   ├── doctor-dashboard.js
│   │   │   ├── doctor-auth.js
│   │   │   └── doctor-appointments.js
│   │   └── css/
│   │       └── doctor-styles.css
│   │
│   ├── nutritionist/
│   │   ├── pages/
│   │   │   ├── nutritionist-dashboard.html
│   │   │   ├── nutritionist-login.html
│   │   │   ├── nutritionist-profile.html
│   │   │   ├── nutritionist-appointments.html
│   │   │   └── nutritionist-diet-plans.html
│   │   ├── js/
│   │   │   ├── nutritionist-dashboard.js
│   │   │   ├── nutritionist-auth.js
│   │   │   └── nutritionist-diet-plans.js
│   │   └── css/
│   │       └── nutritionist-styles.css
│   │
│   ├── caregiver/
│   │   ├── pages/
│   │   │   ├── caregiver-dashboard.html
│   │   │   ├── caregiver-login.html
│   │   │   ├── caregiver-profile.html
│   │   │   ├── caregiver-bookings.html
│   │   │   └── caregiver-schedule.html
│   │   ├── js/
│   │   │   ├── caregiver-dashboard.js
│   │   │   ├── caregiver-auth.js
│   │   │   └── caregiver-bookings.js
│   │   └── css/
│   │       └── caregiver-styles.css
│   │
│   ├── admin/
│   │   ├── pages/
│   │   │   ├── admin-dashboard.html
│   │   │   ├── admin-login.html
│   │   │   ├── admin-users.html
│   │   │   ├── admin-appointments.html
│   │   │   ├── admin-reports.html
│   │   │   └── admin-system-settings.html
│   │   ├── js/
│   │   │   ├── admin-dashboard.js
│   │   │   ├── admin-auth.js
│   │   │   ├── admin-users.js
│   │   │   └── admin-reports.js
│   │   └── css/
│   │       └── admin-styles.css
│   │
│   └── assets/
│       ├── images/
│       ├── icons/
│       └── fonts/
│
├── backend/
│   ├── shared/
│   │   ├── config/
│   │   │   ├── database-config.js
│   │   │   ├── auth-config.js
│   │   │   └── server-config.js
│   │   ├── middleware/
│   │   │   ├── auth-middleware.js
│   │   │   ├── validation-middleware.js
│   │   │   └── error-handler.js
│   │   ├── utils/
│   │   │   ├── database-helper.js
│   │   │   ├── email-service.js
│   │   │   └── file-upload.js
│   │   └── models/
│   │       ├── base-model.js
│   │       └── user-model.js
│   │
│   ├── patient/
│   │   ├── controllers/
│   │   │   ├── patient-auth-controller.js
│   │   │   ├── patient-profile-controller.js
│   │   │   ├── patient-appointment-controller.js
│   │   │   └── patient-medical-records-controller.js
│   │   ├── routes/
│   │   │   ├── patient-auth-routes.js
│   │   │   ├── patient-profile-routes.js
│   │   │   ├── patient-appointment-routes.js
│   │   │   └── patient-medical-records-routes.js
│   │   ├── models/
│   │   │   ├── patient-model.js
│   │   │   ├── appointment-model.js
│   │   │   └── medical-record-model.js
│   │   └── services/
│   │       ├── patient-service.js
│   │       └── appointment-service.js
│   │
│   ├── doctor/
│   │   ├── controllers/
│   │   │   ├── doctor-auth-controller.js
│   │   │   ├── doctor-profile-controller.js
│   │   │   ├── doctor-appointment-controller.js
│   │   │   └── doctor-prescription-controller.js
│   │   ├── routes/
│   │   │   ├── doctor-auth-routes.js
│   │   │   ├── doctor-profile-routes.js
│   │   │   ├── doctor-appointment-routes.js
│   │   │   └── doctor-prescription-routes.js
│   │   ├── models/
│   │   │   ├── doctor-model.js
│   │   │   └── prescription-model.js
│   │   └── services/
│   │       ├── doctor-service.js
│   │       └── prescription-service.js
│   │
│   ├── nutritionist/
│   │   ├── controllers/
│   │   │   ├── nutritionist-auth-controller.js
│   │   │   ├── nutritionist-profile-controller.js
│   │   │   ├── nutritionist-appointment-controller.js
│   │   │   └── nutritionist-diet-plan-controller.js
│   │   ├── routes/
│   │   │   ├── nutritionist-auth-routes.js
│   │   │   ├── nutritionist-profile-routes.js
│   │   │   ├── nutritionist-appointment-routes.js
│   │   │   └── nutritionist-diet-plan-routes.js
│   │   ├── models/
│   │   │   ├── nutritionist-model.js
│   │   │   └── diet-plan-model.js
│   │   └── services/
│   │       ├── nutritionist-service.js
│   │       └── diet-plan-service.js
│   │
│   ├── caregiver/
│   │   ├── controllers/
│   │   │   ├── caregiver-auth-controller.js
│   │   │   ├── caregiver-profile-controller.js
│   │   │   ├── caregiver-booking-controller.js
│   │   │   └── caregiver-schedule-controller.js
│   │   ├── routes/
│   │   │   ├── caregiver-auth-routes.js
│   │   │   ├── caregiver-profile-routes.js
│   │   │   ├── caregiver-booking-routes.js
│   │   │   └── caregiver-schedule-routes.js
│   │   ├── models/
│   │   │   ├── caregiver-model.js
│   │   │   └── caregiver-booking-model.js
│   │   └── services/
│   │       ├── caregiver-service.js
│   │       └── booking-service.js
│   │
│   ├── admin/
│   │   ├── controllers/
│   │   │   ├── admin-auth-controller.js
│   │   │   ├── admin-user-management-controller.js
│   │   │   ├── admin-reports-controller.js
│   │   │   └── admin-system-controller.js
│   │   ├── routes/
│   │   │   ├── admin-auth-routes.js
│   │   │   ├── admin-user-management-routes.js
│   │   │   ├── admin-reports-routes.js
│   │   │   └── admin-system-routes.js
│   │   ├── models/
│   │   │   └── admin-model.js
│   │   └── services/
│   │       ├── admin-service.js
│   │       ├── report-service.js
│   │       └── system-service.js
│   │
│   ├── integration/
│   │   ├── payment-gateway/
│   │   │   ├── stripe-integration.js
│   │   │   └── paypal-integration.js
│   │   ├── notification/
│   │   │   ├── email-service.js
│   │   │   ├── sms-service.js
│   │   │   └── push-notification.js
│   │   ├── video-call/
│   │   │   ├── zoom-integration.js
│   │   │   └── webrtc-service.js
│   │   └── file-storage/
│   │       ├── aws-s3.js
│   │       └── local-storage.js
│   │
│   ├── tests/
│   │   ├── patient/
│   │   ├── doctor/
│   │   ├── nutritionist/
│   │   ├── caregiver/
│   │   ├── admin/
│   │   └── integration/
│   │
│   ├── app.js
│   ├── server.js
│   ├── package.json
│   └── .env.example
│
├── database/
│   ├── migrations/
│   │   ├── 001_create_users_table.sql
│   │   ├── 002_create_patient_table.sql
│   │   ├── 003_create_doctor_table.sql
│   │   ├── 004_create_nutritionist_table.sql
│   │   ├── 005_create_caregiver_table.sql
│   │   └── 006_create_admin_table.sql
│   ├── seeds/
│   │   ├── sample_users.sql
│   │   ├── sample_patients.sql
│   │   ├── sample_doctors.sql
│   │   └── sample_appointments.sql
│   └── healthcare.sql
│
├── deployment/
│   ├── docker/
│   │   ├── Dockerfile
│   │   └── docker-compose.yml
│   ├── nginx/
│   │   └── nginx.conf
│   └── scripts/
│       ├── deploy.sh
│       └── backup.sh
│
└── .gitignore
```

## File Naming Convention

### General Rules:
1. **Role-based prefixes**: `patient-`, `doctor-`, `nutritionist-`, `caregiver-`, `admin-`
2. **Function-based suffixes**: `-controller`, `-model`, `-service`, `-routes`
3. **Feature-based names**: `dashboard`, `profile`, `appointments`, `auth`
4. **Kebab-case**: Use hyphens for multiple words (e.g., `medical-records`)
5. **Descriptive names**: Clear purpose indication

### Examples:
- **Frontend Pages**: `patient-dashboard.html`, `doctor-appointments.html`
- **JavaScript Files**: `patient-auth.js`, `doctor-prescription.js`
- **Backend Controllers**: `patient-auth-controller.js`, `admin-user-management-controller.js`
- **Database Files**: `create_users_table.sql`, `sample_patients.sql`

## Team Collaboration Guidelines

### Git Branch Strategy:
- `main` - Production ready code
- `develop` - Development integration branch
- `feature/patient-dashboard` - Feature branches
- `feature/doctor-appointments` - Role-specific features
- `bugfix/patient-login-issue` - Bug fixes

### Commit Message Format:
```
[ROLE] Brief description

Examples:
[PATIENT] Add appointment booking functionality
[DOCTOR] Fix prescription creation bug
[ADMIN] Implement user management dashboard
[SHARED] Update database connection utility
```

### Code Review Process:
1. Create feature branch from `develop`
2. Implement feature in your module
3. Test thoroughly
4. Create pull request to `develop`
5. Get review from team lead + one other member
6. Merge after approval

Would you like me to reorganize the existing files according to this new structure?
