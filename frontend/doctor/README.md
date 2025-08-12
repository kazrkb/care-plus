# Doctor Module - Frontend

## Overview
This directory is for the Doctor portal frontend development.

## Assigned Developer
**Doctor Module Developer** - Responsible for all doctor-related frontend functionality.

## Files to Create
```
doctor/
├── pages/
│   ├── doctor-login.html           # Doctor login/register
│   ├── doctor-dashboard.html       # Doctor main dashboard
│   ├── doctor-profile.html         # Doctor profile management
│   ├── doctor-appointments.html    # Appointment management
│   ├── doctor-patients.html        # Patient list and details
│   └── doctor-prescriptions.html   # Prescription creation/management
├── js/
│   ├── doctor-dashboard.js         # Dashboard functionality
│   ├── doctor-auth.js             # Doctor authentication
│   ├── doctor-appointments.js      # Appointment management
│   ├── doctor-patients.js         # Patient management
│   └── doctor-prescriptions.js    # Prescription logic
└── css/
    └── doctor-styles.css           # Doctor-specific styles
```

## Features to Implement
🔲 Doctor authentication system  
🔲 Doctor dashboard with patient overview  
🔲 Appointment scheduling and management  
🔲 Patient list with medical history  
🔲 Prescription creation and management  
🔲 Video consultation interface  
🔲 Medical record creation  
🔲 Schedule management  

## Dependencies
- Use shared files from `frontend/shared/`
- Follow patient module structure as reference
- Integrate with existing database schema (doctor table)

## Color Scheme
- Primary: #10B981 (Green) - Medical theme
- Secondary: #059669 (Dark Green)
- Accent: #3B82F6 (Blue)
- Background: #F0FDF4 (Light Green)

## Database Tables
- `doctor` - Doctor profiles and credentials
- `appointment` - Appointments with patients
- `prescription` - Doctor prescriptions
- `schedule` - Doctor availability

## Getting Started
1. Create `doctor-login.html` as entry point
2. Reference patient module for structure
3. Use shared utilities and auth handler
4. Follow established naming conventions
