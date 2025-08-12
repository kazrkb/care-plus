# Care Plus - Healthcare Management System

## 🏥 Project Overview
Care Plus is a comprehensive healthcare management platform that connects patients, doctors, nutritionists, caregivers, and administrators in one seamless system. The platform enables appointment booking, medical record management, caregiver services, and complete healthcare workflow automation.

## 👥 Team Structure (6 Developers)
1. **Patient Module Developer** - Patient portal and features
2. **Doctor Module Developer** - Doctor portal and medical features  
3. **Nutritionist Module Developer** - Nutrition and diet plan features
4. **Caregiver Module Developer** - Caregiver services and booking
5. **Admin Module Developer** - System administration and analytics
6. **System Integration Developer** - Backend, database, and deployment

## 📁 Project Structure
```
care-plus/
├── README.md                           # This file
├── TEAM_COLLABORATION_GUIDE.md         # Team guidelines and conventions
├── PATIENT_DEVELOPMENT_GUIDE.md        # Patient module documentation
├── PROJECT_STRUCTURE.md               # Detailed project structure
├── healthcare.sql                      # Database schema with sample data
│
├── frontend/
│   ├── index.html                      # Main landing page (all portals)
│   ├── shared/                         # Shared resources
│   │   ├── css/global-styles.css       # Global styling
│   │   └── js/
│   │       ├── shared-utilities.js     # Common functions
│   │       └── auth-handler.js         # Authentication logic
│   │
│   ├── patient/                        # 👤 Patient Module
│   │   ├── pages/
│   │   │   ├── patient-login.html      ✅ Complete
│   │   │   ├── patient-dashboard.html  ✅ Complete
│   │   │   └── patient-appointments.html ✅ Complete
│   │   ├── js/
│   │   │   ├── patient-dashboard.js    ✅ Complete
│   │   │   └── patient-booking.js      ✅ Complete
│   │   ├── css/patient-styles.css      ✅ Complete
│   │   └── README.md                   # Patient dev guide
│   │
│   ├── doctor/                         # 👨‍⚕️ Doctor Module
│   │   ├── pages/ (TO BE DEVELOPED)
│   │   ├── js/ (TO BE DEVELOPED)
│   │   ├── css/ (TO BE DEVELOPED)
│   │   └── README.md                   # Doctor dev guide
│   │
│   ├── nutritionist/                   # 🍎 Nutritionist Module
│   │   ├── pages/ (TO BE DEVELOPED)
│   │   ├── js/ (TO BE DEVELOPED)
│   │   ├── css/ (TO BE DEVELOPED)
│   │   └── README.md                   # Nutritionist dev guide
│   │
│   ├── caregiver/                      # 👩‍⚕️ Caregiver Module
│   │   ├── pages/ (TO BE DEVELOPED)
│   │   ├── js/ (TO BE DEVELOPED)
│   │   ├── css/ (TO BE DEVELOPED)
│   │   └── README.md                   # Caregiver dev guide
│   │
│   └── admin/                          # ⚙️ Admin Module
│       ├── pages/ (TO BE DEVELOPED)
│       ├── js/ (TO BE DEVELOPED)
│       ├── css/ (TO BE DEVELOPED)
│       └── README.md                   # Admin dev guide
│
└── backend/ (TO BE DEVELOPED)
    ├── shared/                         # Shared backend resources
    ├── patient/                        # Patient API endpoints
    ├── doctor/                         # Doctor API endpoints
    ├── nutritionist/                   # Nutritionist API endpoints
    ├── caregiver/                      # Caregiver API endpoints
    └── admin/                          # Admin API endpoints
```

## 🎯 Completed Features (Patient Module)
✅ **Landing Page** - Multi-portal entry point  
✅ **Patient Authentication** - Login/Register system  
✅ **Patient Dashboard** - Health overview and quick actions  
✅ **Appointment Booking** - 4-step booking process  
✅ **Responsive Design** - Mobile-first with Tailwind CSS  
✅ **File Organization** - Collaborative structure  

## 🔄 Development Status by Module

### Patient Module (✅ 80% Complete)
- ✅ Login/Register system
- ✅ Dashboard with health overview
- ✅ Appointment booking system
- 🔲 Profile management
- 🔲 Medical records viewing
- 🔲 Caregiver booking interface

### Doctor Module (🔲 0% Complete)
- 🔲 Doctor authentication
- 🔲 Patient management dashboard
- 🔲 Appointment scheduling
- 🔲 Prescription creation
- 🔲 Medical record management

### Nutritionist Module (🔲 0% Complete)
- 🔲 Nutritionist authentication
- 🔲 Diet plan creation
- 🔲 Client appointment management
- 🔲 Nutrition tracking tools

### Caregiver Module (🔲 0% Complete)
- 🔲 Caregiver authentication
- 🔲 Service booking management
- 🔲 Schedule and availability
- 🔲 Rate management system

### Admin Module (🔲 0% Complete)
- 🔲 System administration
- 🔲 User management
- 🔲 Analytics and reporting
- 🔲 Platform configuration

## 🚀 Getting Started

### For All Team Members:
1. **Clone the repository**
2. **Read the collaboration guide**: `TEAM_COLLABORATION_GUIDE.md`
3. **Navigate to your module**: `frontend/[your-module]/`
4. **Read your module's README**: `frontend/[your-module]/README.md`
5. **Follow the established naming conventions**

### For Testing the Current System:
1. Open `frontend/index.html` in a web browser
2. Click "Access Patient Portal"
3. Login with: `john.doe@email.com` / `password123`
4. Explore the patient dashboard and appointment booking

## 📋 File Naming Convention
- **Pages**: `[role]-[feature].html` (e.g., `patient-dashboard.html`)
- **JavaScript**: `[role]-[feature].js` (e.g., `doctor-appointments.js`)
- **CSS**: `[role]-styles.css` (e.g., `nutritionist-styles.css`)
- **Shared Files**: `[purpose]-[type].js` (e.g., `auth-handler.js`)

## 🎨 Color Schemes by Module
- **Patient**: Blue theme (#3B82F6)
- **Doctor**: Green theme (#10B981)
- **Nutritionist**: Orange theme (#F59E0B)
- **Caregiver**: Purple theme (#8B5CF6)
- **Admin**: Red theme (#EF4444)

## 🗄️ Database Schema
The `healthcare.sql` file contains:
- Complete database structure (14 tables)
- Sample data for all user types
- Foreign key relationships
- Proper indexing and constraints

### Key Tables:
- `users` - All system users
- `patient`, `doctor`, `nutritionist`, `caregiver`, `admin` - Role-specific data
- `appointment` - Appointment management
- `prescription` - Medical prescriptions
- `dietplan` - Nutrition plans
- `caregiverbooking` - Caregiver services
- `transaction` - Payment processing

## 🔧 Technology Stack
- **Frontend**: HTML5, Tailwind CSS, Vanilla JavaScript
- **Backend**: (To be implemented - Node.js recommended)
- **Database**: MySQL/MariaDB
- **Icons**: Font Awesome 6.0
- **Design**: Mobile-first, responsive design

## 👥 Team Collaboration

### Git Workflow:
1. `main` branch - Production ready
2. `develop` branch - Integration branch
3. `feature/[module]-[feature]` - Feature branches
4. Pull requests required for merging

### Communication:
- Use descriptive commit messages with module prefix
- Example: `[PATIENT] Add appointment booking functionality`
- Regular code reviews between team members
- Follow established coding standards

## 📞 Support
For questions about your specific module, refer to your module's README file. For general questions about the project structure or collaboration, refer to the `TEAM_COLLABORATION_GUIDE.md`.

---

**Last Updated**: August 12, 2025  
**Project Lead**: Team Lead  
**Repository**: care-plus  
**License**: Internal Project
