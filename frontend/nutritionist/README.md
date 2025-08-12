# Nutritionist Module - Frontend

## Overview
This directory is for the Nutritionist portal frontend development.

## Assigned Developer
**Nutritionist Module Developer** - Responsible for all nutritionist-related frontend functionality.

## Files to Create
```
nutritionist/
├── pages/
│   ├── nutritionist-login.html         # Nutritionist login/register
│   ├── nutritionist-dashboard.html     # Nutritionist main dashboard
│   ├── nutritionist-profile.html       # Profile management
│   ├── nutritionist-appointments.html  # Appointment management
│   └── nutritionist-diet-plans.html    # Diet plan creation/management
├── js/
│   ├── nutritionist-dashboard.js       # Dashboard functionality
│   ├── nutritionist-auth.js           # Authentication
│   ├── nutritionist-appointments.js    # Appointment management
│   └── nutritionist-diet-plans.js     # Diet plan logic
└── css/
    └── nutritionist-styles.css         # Nutritionist-specific styles
```

## Features to Implement
🔲 Nutritionist authentication system  
🔲 Dashboard with client overview  
🔲 Diet plan creation and management  
🔲 Calorie calculation tools  
🔲 Meal planning interface  
🔲 Client appointment scheduling  
🔲 Progress tracking  
🔲 Nutrition guidelines library  

## Dependencies
- Use shared files from `frontend/shared/`
- Follow patient module structure as reference
- Integrate with existing database schema (nutritionist, dietplan tables)

## Color Scheme
- Primary: #F59E0B (Orange/Yellow) - Nutrition theme
- Secondary: #D97706 (Dark Orange)
- Accent: #10B981 (Green)
- Background: #FFFBEB (Light Yellow)

## Database Tables
- `nutritionist` - Nutritionist profiles
- `dietplan` - Diet plans and guidelines
- `appointment` - Appointments with patients

## Special Features
- Diet plan builder with drag-drop interface
- Calorie calculator
- Meal planning calendar
- Progress charts and analytics
- Recipe database integration

## Getting Started
1. Create `nutritionist-login.html` as entry point
2. Focus on diet plan creation tools
3. Use food/nutrition icons and imagery
4. Implement interactive meal planning features
