// Main JavaScript functionality for Care Plus Patient Portal

// Global variables
let currentUser = null;
let isLoggedIn = false;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Care Plus Patient Portal initialized');
    checkAuthStatus();
});

// Modal functions
function showLogin() {
    document.getElementById('loginModal').classList.remove('hidden');
}

function showRegister() {
    document.getElementById('registerModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function switchToRegister() {
    closeModal('loginModal');
    showRegister();
}

function switchToLogin() {
    closeModal('registerModal');
    showLogin();
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const loginModal = document.getElementById('loginModal');
    const registerModal = document.getElementById('registerModal');
    
    if (event.target === loginModal) {
        closeModal('loginModal');
    }
    if (event.target === registerModal) {
        closeModal('registerModal');
    }
});

// Check authentication status
function checkAuthStatus() {
    const token = localStorage.getItem('patientToken');
    const userData = localStorage.getItem('patientData');
    
    if (token && userData) {
        currentUser = JSON.parse(userData);
        isLoggedIn = true;
        console.log('User is logged in:', currentUser);
        // Redirect to dashboard if on login page
        if (window.location.pathname.includes('index.html') || window.location.pathname === '/') {
            redirectToDashboard();
        }
    }
}

// Redirect to dashboard
function redirectToDashboard() {
    window.location.href = 'pages/dashboard.html';
}

// Logout function
function logout() {
    localStorage.removeItem('patientToken');
    localStorage.removeItem('patientData');
    currentUser = null;
    isLoggedIn = false;
    window.location.href = '../index.html';
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
    
    // Set color based on type
    switch(type) {
        case 'success':
            notification.className += ' bg-green-500 text-white';
            break;
        case 'error':
            notification.className += ' bg-red-500 text-white';
            break;
        case 'warning':
            notification.className += ' bg-yellow-500 text-white';
            break;
        default:
            notification.className += ' bg-blue-500 text-white';
    }
    
    notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Loading spinner
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    element.disabled = true;
    
    return function hideLoading() {
        element.innerHTML = originalContent;
        element.disabled = false;
    };
}

// Format date function
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Validate email format
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validate phone number
function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

// Patient data management
const PatientManager = {
    // Get patient profile
    async getProfile() {
        try {
            // This would typically make an API call
            // For now, return mock data
            return {
                success: true,
                data: currentUser
            };
        } catch (error) {
            console.error('Error fetching profile:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Update patient profile
    async updateProfile(profileData) {
        try {
            // This would typically make an API call
            // For now, update localStorage
            const updatedUser = { ...currentUser, ...profileData };
            localStorage.setItem('patientData', JSON.stringify(updatedUser));
            currentUser = updatedUser;
            
            return { success: true, data: updatedUser };
        } catch (error) {
            console.error('Error updating profile:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Get appointments
    async getAppointments() {
        try {
            // Mock data for now
            return {
                success: true,
                data: [
                    {
                        id: 1,
                        doctorName: 'Dr. Alice Smith',
                        specialty: 'Cardiology',
                        date: '2025-08-18T10:00:00',
                        status: 'Scheduled',
                        consultationLink: 'https://meet.example.com/xyz-abc-123'
                    }
                ]
            };
        } catch (error) {
            console.error('Error fetching appointments:', error);
            return { success: false, error: error.message };
        }
    },
    
    // Get medical records
    async getMedicalRecords() {
        try {
            // Mock data for now
            return {
                success: true,
                data: {
                    prescriptions: [
                        {
                            id: 1,
                            medicineName: 'Aspirin',
                            dosage: '81mg',
                            instructions: 'Take one tablet daily with food.',
                            date: '2025-08-18',
                            doctorName: 'Dr. Alice Smith'
                        }
                    ],
                    dietPlans: [
                        {
                            id: 1,
                            dietType: 'Low-Carb',
                            caloriesPerDay: 2000,
                            mealGuidelines: 'Avoid sugar and processed grains. Focus on lean protein and vegetables.',
                            startDate: '2025-08-19',
                            endDate: '2025-09-18',
                            nutritionist: 'Susan Jones'
                        }
                    ]
                }
            };
        } catch (error) {
            console.error('Error fetching medical records:', error);
            return { success: false, error: error.message };
        }
    }
};
