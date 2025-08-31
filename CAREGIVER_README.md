# CarePlus - Caregiver User Guide

## Overview
The CarePlus Caregiver Dashboard provides a comprehensive interface for caregivers to manage their patients, availability, bookings, and care plans. This document outlines all caregiver-specific features and functionality.

## Table of Contents
- [Getting Started](#getting-started)
- [Dashboard Features](#dashboard-features)
- [Navigation Menu](#navigation-menu)
- [Key Functionalities](#key-functionalities)
- [Database Requirements](#database-requirements)
- [File Structure](#file-structure)
- [Troubleshooting](#troubleshooting)

## Getting Started

### Prerequisites
- XAMPP with Apache and MySQL running
- PHP 7.4 or higher
- MySQL database with CarePlus schema
- Web browser (Chrome, Firefox, Safari, Edge)

### Access
1. Navigate to: `http://localhost/care-plus/`
2. Login with caregiver credentials
3. Role must be set to 'CareGiver' in the database

## Dashboard Features

### Main Dashboard (`careGiverDashboard.php`)
- **Personalized Welcome**: Displays caregiver name and avatar
- **Real-time Notifications**: Shows unread notifications with dismiss functionality
- **Quick Access Cards**: Direct links to key features
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### Navigation Sidebar
The left sidebar provides access to all caregiver features:

## Navigation Menu

| Menu Item | File | Description |
|-----------|------|-------------|
| 🏠 Dashboard | `careGiverDashboard.php` | Main dashboard overview |
| 👤 My Profile | `caregiverProfile.php` | Edit personal information |
| 📋 Care Plans | `caregiver_careplan.php` | Manage patient care plans |
| 📅 Provide Availability | `caregiver_availability.php` | Set available time slots |
| ✅ My Bookings | `my_bookings.php` | View and manage bookings |
| 📖 View Patient Details | `caregiver_view_patients.php` | Comprehensive patient information |
| 📊 Progress Analytics | `#` | Track patient progress (Coming Soon) |
| 💰 Transactions | `my_transactions.php` | Financial records |
| 🚪 Logout | `logout.php` | End session securely |

## Key Functionalities

### 1. Patient Management
**File**: `caregiver_view_patients.php`

**Features**:
- **Patient List Panel**: View all assigned patients
- **Detailed Patient View**: Comprehensive patient information
- **Contact Information**: Phone, email, address details
- **Medical Information**: Health conditions, medications, allergies
- **Booking History**: Complete record of past and upcoming appointments
- **Status Tracking**: Real-time booking status updates

**Database Tables Used**:
- `caregiverbooking` - Links caregivers to patients
- `users` - Basic user information
- `patient` - Medical details (optional)

### 2. Availability Management
**File**: `caregiver_availability.php`

**Features**:
- Set available time slots
- Manage recurring availability
- Block unavailable dates
- View existing schedule

### 3. Booking Management
**File**: `my_bookings.php`

**Features**:
- View all bookings (past, current, upcoming)
- Update booking status
- Cancel appointments
- Communicate with patients

### 4. Care Plan Management
**File**: `caregiver_careplan.php`

**Features**:
- Create personalized care plans
- Update existing plans
- Track patient progress
- Set care goals and milestones

### 5. Profile Management
**File**: `caregiverProfile.php`

**Features**:
- Update personal information
- Upload profile photo
- Manage qualifications
- Set specializations

### 6. Transaction History
**File**: `my_transactions.php`

**Features**:
- View payment history
- Track earnings
- Filter by date range
- Export transaction records

## Database Requirements

### Essential Tables
```sql
-- Caregiver bookings
caregiverbooking (
    bookingID, careGiverID, patientID, 
    bookingDate, startTime, endTime, 
    status, notes, createdAt
)

-- User information
users (
    userID, Name, email, phone, 
    role, address, profilePhoto
)

-- Patient details (optional)
patient (
    patientID, userID, medicalHistory,
    currentMedication, allergies, 
    emergencyContact
)

-- Notifications
notification (
    notificationID, userID, message,
    status, timestamp
)
```

### Required Columns
- `users.role` must include 'CareGiver' value
- `caregiverbooking` table must exist for patient assignments
- Session variables: `userID`, `role`, `Name`

## File Structure

```
care-plus/
├── careGiverDashboard.php          # Main dashboard
├── caregiver_view_patients.php     # Patient management
├── caregiver_availability.php      # Availability management
├── my_bookings.php                 # Booking management
├── caregiver_careplan.php          # Care plan management
├── caregiverProfile.php            # Profile management
├── my_transactions.php             # Financial records
├── config.php                      # Database configuration
├── login.php                       # Authentication
├── logout.php                      # Session termination
└── uploads/                        # File storage
    └── profile photos
    └── patient documents
```

## Session Management

### Required Session Variables
```php
$_SESSION['userID']     // Caregiver's user ID
$_SESSION['role']       // Must be 'CareGiver'
$_SESSION['Name']       // Caregiver's display name
```

### Security Features
- Role-based access control
- Session validation on each page
- Automatic redirect to login if unauthorized
- SQL injection protection with prepared statements
- XSS protection with htmlspecialchars()

## Styling and UI

### Design Framework
- **CSS Framework**: Tailwind CSS
- **Icons**: Font Awesome 6.5.2
- **Fonts**: Inter (Google Fonts)
- **Color Scheme**: Purple theme with orchid accents
- **Layout**: Responsive sidebar navigation

### Custom CSS Classes
```css
.bg-dark-orchid { background-color: #9932CC; }
.text-dark-orchid { color: #9932CC; }
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Errors
**Solution**: Check `config.php` database credentials
```php
// Ensure correct database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "healthcare";
```

#### 2. Missing Patient Data
**Symptoms**: Empty patient list in "View Patient Details"
**Causes**:
- No entries in `caregiverbooking` table
- Incorrect `careGiverID` in bookings
- Missing `users` table records

**Solution**: Verify database relationships
```sql
-- Check caregiver assignments
SELECT * FROM caregiverbooking WHERE careGiverID = [your_caregiver_id];

-- Verify user records exist
SELECT * FROM users WHERE userID IN (
    SELECT DISTINCT patientID FROM caregiverbooking 
    WHERE careGiverID = [your_caregiver_id]
);
```

#### 3. Role Access Denied
**Symptoms**: Redirected to login page
**Solution**: Verify user role in database
```sql
UPDATE users SET role = 'CareGiver' WHERE userID = [your_user_id];
```

#### 4. Navigation Issues
**Symptoms**: Broken links or missing pages
**Solution**: Ensure all referenced files exist in the correct directory

#### 5. Transaction Errors
**Symptoms**: Database errors in transactions page
**Solution**: Check if `transaction` table exists and has required columns

### Debug Mode
Enable error reporting for development:
```php
// Add to top of PHP files for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Best Practices

### For Caregivers
1. **Regular Updates**: Keep patient information current
2. **Availability Management**: Update schedule regularly
3. **Communication**: Use notification system effectively
4. **Documentation**: Maintain detailed care plans

### For Developers
1. **Security**: Always validate user input
2. **Error Handling**: Implement graceful error handling
3. **Database**: Use prepared statements
4. **UI/UX**: Maintain responsive design principles

## Support

### Getting Help
1. Check this README for common solutions
2. Verify database schema matches requirements
3. Review browser console for JavaScript errors
4. Check server logs for PHP errors

### Feature Requests
The caregiver system is designed to be extensible. Common enhancement areas:
- Advanced analytics dashboard
- Mobile app integration
- Real-time chat system
- Automated scheduling
- Report generation

## Version Information
- **Version**: 1.0
- **Last Updated**: August 2025
- **Compatibility**: PHP 7.4+, MySQL 5.7+
- **Browser Support**: Modern browsers (Chrome 60+, Firefox 55+, Safari 12+)

---

*This README covers the caregiver functionality in the CarePlus healthcare management system. For technical support or feature requests, please refer to the main project documentation.*
