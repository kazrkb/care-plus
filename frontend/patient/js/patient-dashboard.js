// Dashboard specific JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    // Check if user is authenticated
    if (!isLoggedIn) {
        window.location.href = '../../index.html';
        return;
    }
    
    initializeDashboard();
    loadDashboardData();
    
    // Setup event listeners
    setupEventListeners();
});

function initializeDashboard() {
    // Display user name
    if (currentUser) {
        document.getElementById('userName').textContent = currentUser.name;
        document.getElementById('welcomeName').textContent = currentUser.name;
    }
    
    // Setup profile dropdown toggle
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        profileDropdown.classList.add('hidden');
    });
}

function setupEventListeners() {
    // Notification button
    const notificationBtn = document.getElementById('notificationBtn');
    notificationBtn.addEventListener('click', function() {
        // Toggle notifications panel or navigate to notifications page
        console.log('Notifications clicked');
    });
}

async function loadDashboardData() {
    try {
        // Load appointments
        await loadUpcomingAppointments();
        
        // Load medical records
        await loadRecentMedicalRecords();
        
        // Load health metrics
        await loadHealthMetrics();
        
        // Update stats
        updateDashboardStats();
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showNotification('Error loading dashboard data', 'error');
    }
}

async function loadUpcomingAppointments() {
    const appointmentsList = document.getElementById('appointmentsList');
    
    try {
        const response = await PatientManager.getAppointments();
        
        if (response.success && response.data.length > 0) {
            appointmentsList.innerHTML = '';
            
            response.data.forEach(appointment => {
                const appointmentCard = createAppointmentCard(appointment);
                appointmentsList.appendChild(appointmentCard);
            });
        } else {
            appointmentsList.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600">No upcoming appointments</p>
                    <button onclick="window.location.href='book-appointment.html'" class="mt-4 text-primary hover:text-secondary">
                        Book your first appointment
                    </button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        appointmentsList.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                <p class="text-red-600">Error loading appointments</p>
            </div>
        `;
    }
}

async function loadRecentMedicalRecords() {
    const medicalRecordsList = document.getElementById('medicalRecordsList');
    
    try {
        const response = await PatientManager.getMedicalRecords();
        
        if (response.success) {
            medicalRecordsList.innerHTML = '';
            
            // Display recent prescriptions
            if (response.data.prescriptions && response.data.prescriptions.length > 0) {
                response.data.prescriptions.slice(0, 3).forEach(prescription => {
                    const prescriptionCard = createPrescriptionCard(prescription);
                    medicalRecordsList.appendChild(prescriptionCard);
                });
            }
            
            // Display recent diet plans
            if (response.data.dietPlans && response.data.dietPlans.length > 0) {
                response.data.dietPlans.slice(0, 2).forEach(dietPlan => {
                    const dietPlanCard = createDietPlanCard(dietPlan);
                    medicalRecordsList.appendChild(dietPlanCard);
                });
            }
            
            // If no records found
            if ((!response.data.prescriptions || response.data.prescriptions.length === 0) &&
                (!response.data.dietPlans || response.data.dietPlans.length === 0)) {
                medicalRecordsList.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-file-medical text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">No medical records found</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading medical records:', error);
        medicalRecordsList.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                <p class="text-red-600">Error loading medical records</p>
            </div>
        `;
    }
}

async function loadHealthMetrics() {
    const healthMetrics = document.getElementById('healthMetrics');
    
    try {
        // Get patient health data
        if (currentUser && currentUser.patientInfo) {
            const patientInfo = currentUser.patientInfo;
            
            healthMetrics.innerHTML = `
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-600">Age</span>
                    <span class="font-semibold">${patientInfo.age} years</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-600">Height</span>
                    <span class="font-semibold">${patientInfo.height} cm</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                    <span class="text-gray-600">Weight</span>
                    <span class="font-semibold">${patientInfo.weight} kg</span>
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="text-gray-600">BMI</span>
                    <span class="font-semibold">${calculateBMI(patientInfo.weight, patientInfo.height)}</span>
                </div>
            `;
        } else {
            healthMetrics.innerHTML = `
                <div class="text-center py-4">
                    <p class="text-gray-600">Health metrics not available</p>
                    <button onclick="window.location.href='profile.html'" class="mt-2 text-primary hover:text-secondary text-sm">
                        Update your profile
                    </button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading health metrics:', error);
        healthMetrics.innerHTML = `
            <div class="text-center py-4">
                <p class="text-red-600">Error loading health metrics</p>
            </div>
        `;
    }
}

function updateDashboardStats() {
    // These would typically come from API calls
    // For now, using mock data
    
    // Update stats based on loaded data
    document.getElementById('upcomingAppointments').textContent = '1';
    document.getElementById('activePrescriptions').textContent = '1';
    document.getElementById('activeCaregiver').textContent = '1';
    document.getElementById('healthScore').textContent = '85';
}

function createAppointmentCard(appointment) {
    const card = document.createElement('div');
    card.className = 'border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-300';
    
    const appointmentDate = new Date(appointment.date);
    const formattedDate = appointmentDate.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    const formattedTime = appointmentDate.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    card.innerHTML = `
        <div class="flex justify-between items-start">
            <div class="flex-1">
                <h4 class="font-semibold text-gray-900">${appointment.doctorName}</h4>
                <p class="text-sm text-gray-600">${appointment.specialty}</p>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-calendar mr-1"></i>${formattedDate}
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-clock mr-1"></i>${formattedTime}
                </p>
            </div>
            <div class="text-right">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                    ${appointment.status}
                </span>
                ${appointment.consultationLink ? `
                    <button onclick="window.open('${appointment.consultationLink}', '_blank')" 
                            class="block mt-2 text-primary hover:text-secondary text-sm">
                        <i class="fas fa-video mr-1"></i>Join Call
                    </button>
                ` : ''}
            </div>
        </div>
    `;
    
    return card;
}

function createPrescriptionCard(prescription) {
    const card = document.createElement('div');
    card.className = 'border border-gray-200 rounded-lg p-4 mb-3';
    
    card.innerHTML = `
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <i class="fas fa-pills text-accent mr-2"></i>
                    <h4 class="font-semibold text-gray-900">${prescription.medicineName}</h4>
                </div>
                <p class="text-sm text-gray-600 mt-1">${prescription.dosage}</p>
                <p class="text-xs text-gray-500 mt-1">${prescription.instructions}</p>
                <p class="text-xs text-gray-500 mt-1">Prescribed by: ${prescription.doctorName}</p>
            </div>
            <span class="text-xs text-gray-500">${formatDate(prescription.date)}</span>
        </div>
    `;
    
    return card;
}

function createDietPlanCard(dietPlan) {
    const card = document.createElement('div');
    card.className = 'border border-gray-200 rounded-lg p-4 mb-3';
    
    card.innerHTML = `
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center">
                    <i class="fas fa-utensils text-warning mr-2"></i>
                    <h4 class="font-semibold text-gray-900">${dietPlan.dietType} Diet Plan</h4>
                </div>
                <p class="text-sm text-gray-600 mt-1">${dietPlan.caloriesPerDay} calories/day</p>
                <p class="text-xs text-gray-500 mt-1">${dietPlan.mealGuidelines}</p>
                <p class="text-xs text-gray-500 mt-1">By: ${dietPlan.nutritionist}</p>
            </div>
            <span class="text-xs text-gray-500">${formatDate(dietPlan.startDate)}</span>
        </div>
    `;
    
    return card;
}

function calculateBMI(weight, height) {
    const heightInMeters = height / 100;
    const bmi = weight / (heightInMeters * heightInMeters);
    return bmi.toFixed(1);
}

// Refresh dashboard data
function refreshDashboard() {
    loadDashboardData();
    showNotification('Dashboard updated', 'success');
}

// Auto-refresh dashboard every 5 minutes
setInterval(refreshDashboard, 5 * 60 * 1000);
