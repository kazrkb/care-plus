# Caregiver Module - Frontend

## Overview
This directory is for the Caregiver portal frontend development.

## Assigned Developer
**Caregiver Module Developer** - Responsible for all caregiver-related frontend functionality.

## Files to Create
```
caregiver/
├── pages/
│   ├── caregiver-login.html        # Caregiver login/register
│   ├── caregiver-dashboard.html    # Caregiver main dashboard
│   ├── caregiver-profile.html      # Profile and certification management
│   ├── caregiver-bookings.html     # Booking management
│   └── caregiver-schedule.html     # Schedule and availability
├── js/
│   ├── caregiver-dashboard.js      # Dashboard functionality
│   ├── caregiver-auth.js          # Authentication
│   ├── caregiver-bookings.js      # Booking management
│   └── caregiver-schedule.js      # Schedule management
└── css/
    └── caregiver-styles.css        # Caregiver-specific styles
```

## Features to Implement
🔲 Caregiver authentication and registration  
🔲 Dashboard with booking overview  
🔲 Service booking management  
🔲 Schedule and availability setting  
🔲 Rate management (daily/weekly/monthly)  
🔲 Client communication interface  
🔲 Service history tracking  
🔲 Certification upload system  

## Dependencies
- Use shared files from `frontend/shared/`
- Follow patient module structure as reference
- Integrate with existing database schema (caregiver, caregiverbooking tables)

## Color Scheme
- Primary: #8B5CF6 (Purple) - Care theme
- Secondary: #7C3AED (Dark Purple)
- Accent: #10B981 (Green)
- Background: #F5F3FF (Light Purple)

## Database Tables
- `caregiver` - Caregiver profiles and rates
- `caregiverbooking` - Service bookings
- `users` - User authentication

## Special Features
- Calendar integration for availability
- Rate calculator (daily/weekly/monthly)
- Certification document upload
- Service type management
- Client feedback viewing
- Distance/location-based matching

## Service Types
- Physiotherapist
- Nurse
- Home health aide
- Companion care
- Specialized care (post-surgery, elderly, etc.)

## Getting Started
1. Create `caregiver-login.html` as entry point
2. Focus on booking management and scheduling
3. Implement rate calculation features
4. Create intuitive calendar interface for availability
