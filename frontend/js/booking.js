// Booking functionality for appointment scheduling

let currentStep = 1;
let selectedSpecialty = '';
let selectedDoctor = null;
let selectedDate = '';
let selectedTime = '';
let appointmentData = {};

document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    if (!isLoggedIn) {
        window.location.href = '../index.html';
        return;
    }
    
    initializeBooking();
    setupEventListeners();
});

function initializeBooking() {
    // Setup specialty card selection
    const specialtyCards = document.querySelectorAll('.specialty-card');
    specialtyCards.forEach(card => {
        card.addEventListener('click', function() {
            selectSpecialty(this.dataset.specialty, this);
        });
    });
    
    // Setup navigation buttons
    setupNavigationButtons();
    
    // Generate calendar
    generateCalendar();
}

function setupEventListeners() {
    // Form submission
    const bookingForm = document.getElementById('bookingForm');
    bookingForm.addEventListener('submit', handleBookingSubmission);
    
    // Navigation buttons
    document.getElementById('nextBtn').addEventListener('click', nextStep);
    document.getElementById('prevBtn').addEventListener('click', prevStep);
}

function setupNavigationButtons() {
    updateNavigationButtons();
}

function selectSpecialty(specialty, cardElement) {
    selectedSpecialty = specialty;
    
    // Update UI
    document.querySelectorAll('.specialty-card').forEach(card => {
        card.classList.remove('border-primary', 'bg-blue-50');
        card.classList.add('border-gray-200');
    });
    
    cardElement.classList.remove('border-gray-200');
    cardElement.classList.add('border-primary', 'bg-blue-50');
    
    // Enable next button
    document.getElementById('nextBtn').disabled = false;
    
    console.log('Selected specialty:', specialty);
}

function nextStep() {
    if (currentStep < 4) {
        // Hide current step
        document.getElementById(`step${currentStep}`).classList.add('hidden');
        
        currentStep++;
        
        // Show next step
        document.getElementById(`step${currentStep}`).classList.remove('hidden');
        
        // Load data for the new step
        loadStepData();
        
        // Update navigation
        updateNavigationButtons();
    }
}

function prevStep() {
    if (currentStep > 1) {
        // Hide current step
        document.getElementById(`step${currentStep}`).classList.add('hidden');
        
        currentStep--;
        
        // Show previous step
        document.getElementById(`step${currentStep}`).classList.remove('hidden');
        
        // Update navigation
        updateNavigationButtons();
    }
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    // Show/hide previous button
    if (currentStep === 1) {
        prevBtn.classList.add('hidden');
    } else {
        prevBtn.classList.remove('hidden');
    }
    
    // Show/hide next/submit buttons
    if (currentStep === 4) {
        nextBtn.classList.add('hidden');
        submitBtn.classList.remove('hidden');
    } else {
        nextBtn.classList.remove('hidden');
        submitBtn.classList.add('hidden');
    }
    
    // Enable/disable next button based on selections
    let canProceed = false;
    switch (currentStep) {
        case 1:
            canProceed = selectedSpecialty !== '';
            break;
        case 2:
            canProceed = selectedDoctor !== null;
            break;
        case 3:
            canProceed = selectedDate !== '' && selectedTime !== '';
            break;
        case 4:
            canProceed = true;
            break;
    }
    
    if (currentStep < 4) {
        nextBtn.disabled = !canProceed;
    }
}

function loadStepData() {
    switch (currentStep) {
        case 2:
            loadDoctors();
            break;
        case 3:
            loadAvailableSlots();
            break;
        case 4:
            displayAppointmentSummary();
            break;
    }
}

async function loadDoctors() {
    const doctorsList = document.getElementById('doctorsList');
    doctorsList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
    
    try {
        // Mock data for doctors based on specialty
        const doctors = getDoctorsBySpecialty(selectedSpecialty);
        
        doctorsList.innerHTML = '';
        
        doctors.forEach(doctor => {
            const doctorCard = createDoctorCard(doctor);
            doctorsList.appendChild(doctorCard);
        });
        
    } catch (error) {
        console.error('Error loading doctors:', error);
        doctorsList.innerHTML = '<div class="text-center py-4 text-red-600">Error loading doctors</div>';
    }
}

function getDoctorsBySpecialty(specialty) {
    // Mock data - replace with actual API call
    const allDoctors = {
        cardiology: [
            {
                id: 2,
                name: 'Dr. Alice Smith',
                specialty: 'Cardiology',
                experience: '10 years',
                rating: 4.8,
                fees: 150,
                hospital: 'General Hospital',
                education: 'MD, Cardiology',
                nextAvailable: '2025-08-18'
            }
        ],
        dermatology: [
            {
                id: 3,
                name: 'Dr. Sarah Johnson',
                specialty: 'Dermatology',
                experience: '8 years',
                rating: 4.7,
                fees: 120,
                hospital: 'Skin Care Center',
                education: 'MD, Dermatology',
                nextAvailable: '2025-08-19'
            }
        ],
        nutrition: [
            {
                id: 4,
                name: 'Susan Jones',
                specialty: 'Nutrition',
                experience: '5 years',
                rating: 4.6,
                fees: 80,
                hospital: 'Wellness Center',
                education: 'MPH in Community Nutrition',
                nextAvailable: '2025-08-18'
            }
        ]
    };
    
    return allDoctors[specialty] || [];
}

function createDoctorCard(doctor) {
    const card = document.createElement('div');
    card.className = 'doctor-card border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-primary transition duration-300';
    card.dataset.doctorId = doctor.id;
    
    card.innerHTML = `
        <div class="flex items-start space-x-4">
            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center">
                <i class="fas fa-user-md text-2xl text-gray-600"></i>
            </div>
            <div class="flex-1">
                <h4 class="font-semibold text-lg text-gray-900">${doctor.name}</h4>
                <p class="text-primary font-medium">${doctor.specialty}</p>
                <p class="text-sm text-gray-600">${doctor.experience} experience</p>
                <p class="text-sm text-gray-600">${doctor.hospital}</p>
                <div class="flex items-center mt-2">
                    <div class="flex text-yellow-400">
                        ${generateStarRating(doctor.rating)}
                    </div>
                    <span class="ml-2 text-sm text-gray-600">${doctor.rating}</span>
                </div>
            </div>
            <div class="text-right">
                <p class="text-lg font-bold text-gray-900">$${doctor.fees}</p>
                <p class="text-sm text-gray-600">Consultation Fee</p>
                <p class="text-xs text-green-600 mt-1">Available: ${formatDate(doctor.nextAvailable)}</p>
            </div>
        </div>
    `;
    
    card.addEventListener('click', function() {
        selectDoctor(doctor, this);
    });
    
    return card;
}

function selectDoctor(doctor, cardElement) {
    selectedDoctor = doctor;
    
    // Update UI
    document.querySelectorAll('.doctor-card').forEach(card => {
        card.classList.remove('border-primary', 'bg-blue-50');
        card.classList.add('border-gray-200');
    });
    
    cardElement.classList.remove('border-gray-200');
    cardElement.classList.add('border-primary', 'bg-blue-50');
    
    // Update navigation
    updateNavigationButtons();
    
    console.log('Selected doctor:', doctor);
}

function generateStarRating(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    let stars = '';
    
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star"></i>';
    }
    
    if (hasHalfStar) {
        stars += '<i class="fas fa-star-half-alt"></i>';
    }
    
    const emptyStars = 5 - Math.ceil(rating);
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star"></i>';
    }
    
    return stars;
}

function generateCalendar() {
    const calendar = document.getElementById('calendar');
    const currentDate = new Date();
    const currentMonth = currentDate.getMonth();
    const currentYear = currentDate.getFullYear();
    
    // Generate calendar for current month
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    let calendarHTML = `
        <div class="text-center mb-4">
            <h3 class="text-lg font-semibold">${monthNames[currentMonth]} ${currentYear}</h3>
        </div>
        <div class="grid grid-cols-7 gap-1 mb-2">
            <div class="text-center text-sm font-medium text-gray-600 py-2">Sun</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Mon</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Tue</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Wed</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Thu</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Fri</div>
            <div class="text-center text-sm font-medium text-gray-600 py-2">Sat</div>
        </div>
        <div class="grid grid-cols-7 gap-1">
    `;
    
    for (let i = 0; i < 42; i++) {
        const date = new Date(startDate);
        date.setDate(date.getDate() + i);
        
        const isCurrentMonth = date.getMonth() === currentMonth;
        const isToday = date.toDateString() === currentDate.toDateString();
        const isPast = date < currentDate;
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        
        let dayClass = 'calendar-day text-center py-2 text-sm cursor-pointer rounded';
        
        if (!isCurrentMonth) {
            dayClass += ' text-gray-300 cursor-not-allowed';
        } else if (isPast) {
            dayClass += ' text-gray-400 cursor-not-allowed';
        } else if (isWeekend) {
            dayClass += ' text-gray-500 cursor-not-allowed';
        } else {
            dayClass += ' text-gray-900 hover:bg-blue-100';
        }
        
        if (isToday) {
            dayClass += ' bg-blue-500 text-white';
        }
        
        const dateString = date.toISOString().split('T')[0];
        const isSelectable = isCurrentMonth && !isPast && !isWeekend;
        
        calendarHTML += `
            <div class="${dayClass}" 
                 data-date="${dateString}" 
                 ${isSelectable ? 'onclick="selectDate(this)"' : ''}>
                ${date.getDate()}
            </div>
        `;
    }
    
    calendarHTML += '</div>';
    calendar.innerHTML = calendarHTML;
}

function selectDate(element) {
    selectedDate = element.dataset.date;
    
    // Update UI
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('bg-primary', 'text-white');
    });
    
    element.classList.add('bg-primary', 'text-white');
    
    // Load time slots for selected date
    loadTimeSlots();
    
    // Update navigation
    updateNavigationButtons();
    
    console.log('Selected date:', selectedDate);
}

function loadTimeSlots() {
    const timeSlots = document.getElementById('timeSlots');
    
    // Mock time slots - replace with actual availability check
    const availableSlots = [
        '09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM',
        '11:00 AM', '11:30 AM', '02:00 PM', '02:30 PM',
        '03:00 PM', '03:30 PM', '04:00 PM', '04:30 PM'
    ];
    
    timeSlots.innerHTML = '';
    
    availableSlots.forEach(slot => {
        const slotButton = document.createElement('button');
        slotButton.type = 'button';
        slotButton.className = 'time-slot w-full text-left px-4 py-2 border border-gray-300 rounded-lg hover:border-primary hover:bg-blue-50 transition duration-300';
        slotButton.textContent = slot;
        slotButton.dataset.time = slot;
        
        slotButton.addEventListener('click', function() {
            selectTimeSlot(slot, this);
        });
        
        timeSlots.appendChild(slotButton);
    });
}

function selectTimeSlot(time, element) {
    selectedTime = time;
    
    // Update UI
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('border-primary', 'bg-blue-50');
        slot.classList.add('border-gray-300');
    });
    
    element.classList.remove('border-gray-300');
    element.classList.add('border-primary', 'bg-blue-50');
    
    // Update navigation
    updateNavigationButtons();
    
    console.log('Selected time:', time);
}

function displayAppointmentSummary() {
    const summary = document.getElementById('appointmentSummary');
    
    const appointmentDate = new Date(selectedDate);
    const formattedDate = appointmentDate.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    summary.innerHTML = `
        <div class="space-y-4">
            <div class="flex justify-between">
                <span class="font-medium text-gray-600">Doctor:</span>
                <span class="text-gray-900">${selectedDoctor.name}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-medium text-gray-600">Specialty:</span>
                <span class="text-gray-900">${selectedDoctor.specialty}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-medium text-gray-600">Date:</span>
                <span class="text-gray-900">${formattedDate}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-medium text-gray-600">Time:</span>
                <span class="text-gray-900">${selectedTime}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-medium text-gray-600">Hospital:</span>
                <span class="text-gray-900">${selectedDoctor.hospital}</span>
            </div>
            <div class="flex justify-between border-t pt-4">
                <span class="font-semibold text-gray-900">Consultation Fee:</span>
                <span class="font-semibold text-primary text-lg">$${selectedDoctor.fees}</span>
            </div>
        </div>
    `;
}

async function handleBookingSubmission(event) {
    event.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const hideLoading = showLoading(submitBtn);
    
    try {
        // Prepare appointment data
        appointmentData = {
            patientId: currentUser.userID,
            doctorId: selectedDoctor.id,
            specialty: selectedSpecialty,
            date: selectedDate,
            time: selectedTime,
            notes: document.getElementById('appointmentNotes').value,
            fees: selectedDoctor.fees
        };
        
        // Submit appointment booking
        const result = await bookAppointment(appointmentData);
        
        if (result.success) {
            showSuccessModal();
        } else {
            showNotification(result.message || 'Booking failed', 'error');
        }
        
    } catch (error) {
        console.error('Booking submission error:', error);
        showNotification('An error occurred while booking the appointment', 'error');
    } finally {
        hideLoading();
    }
}

async function bookAppointment(appointmentData) {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Mock successful booking
    return {
        success: true,
        appointmentId: Date.now(),
        message: 'Appointment booked successfully'
    };
}

function showSuccessModal() {
    document.getElementById('successModal').classList.remove('hidden');
}

function loadAvailableSlots() {
    // This function is called when moving to step 3
    // The calendar is already generated, so we just need to reset selections
    selectedDate = '';
    selectedTime = '';
    
    // Reset time slots
    const timeSlots = document.getElementById('timeSlots');
    timeSlots.innerHTML = '<p class="text-gray-500">Please select a date first</p>';
    
    // Reset calendar selections
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('bg-primary', 'text-white');
    });
}
