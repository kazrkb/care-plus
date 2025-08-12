# Patient Module - Frontend

## Overview
This directory contains all frontend files for the Patient portal functionality.

## Files Structure
```
patient/
├── pages/
│   ├── patient-login.html          # Login/Register page for patients
│   ├── patient-dashboard.html      # Main dashboard
│   ├── patient-appointments.html   # Appointment booking
│   ├── patient-profile.html        # Profile management (TO BE CREATED)
│   ├── patient-medical-records.html # Medical records view (TO BE CREATED)
│   └── patient-caregiver-booking.html # Caregiver booking (TO BE CREATED)
├── js/
│   ├── patient-dashboard.js        # Dashboard functionality
│   ├── patient-booking.js          # Appointment booking logic
│   ├── patient-auth.js            # Patient-specific auth (TO BE CREATED)
│   └── patient-profile.js         # Profile management (TO BE CREATED)
└── css/
    └── patient-styles.css          # Patient-specific styles
```

## Completed Features
✅ Patient login/register page  
✅ Patient dashboard with health overview  
✅ Appointment booking system  
✅ Basic patient authentication  
✅ Responsive design with Tailwind CSS  

## Features to Implement
🔲 Patient profile management  
🔲 Medical records viewing  
🔲 Caregiver booking interface  
🔲 Prescription management  
🔲 Video consultation integration  
🔲 Payment processing  

## Dependencies
- **Shared Files**: Located in `frontend/shared/`
  - `shared-utilities.js` - Common functions
  - `auth-handler.js` - Authentication logic
  - `global-styles.css` - Global styling

## Development Guidelines
1. Use descriptive file names with `patient-` prefix
2. Follow the existing code structure and styling
3. Utilize shared utilities when possible
4. Test on multiple screen sizes (mobile-first approach)
5. Maintain consistent color scheme (blue/primary theme)

## Getting Started
1. Open `patient-login.html` in a browser
2. Use test credentials: `john.doe@email.com` / `password123`
3. Navigate through the patient dashboard and booking flow

## Color Scheme
- Primary: #3B82F6 (Blue)
- Secondary: #1E40AF (Dark Blue)
- Accent: #10B981 (Green)
- Warning: #F59E0B (Yellow)
- Danger: #EF4444 (Red)

## Notes for Team
- All patient pages should link to `patient-dashboard.html` as the main hub
- Use relative paths: `../../shared/` for shared resources
- Follow the established naming convention
- Patient module focuses on appointment booking, medical records, and caregiver services
