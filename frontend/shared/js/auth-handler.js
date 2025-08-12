// Authentication functions for Care Plus Patient Portal

// Login form handler
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
});

// Handle login
async function handleLogin(event) {
    event.preventDefault();
    
    const email = document.getElementById('loginEmail').value.trim();
    const password = document.getElementById('loginPassword').value;
    const submitButton = event.target.querySelector('button[type="submit"]');
    
    // Validation
    if (!email || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (!isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return;
    }
    
    const hideLoading = showLoading(submitButton);
    
    try {
        // Simulate API call - replace with actual API endpoint
        const result = await authenticateUser(email, password);
        
        if (result.success) {
            // Store auth token and user data
            localStorage.setItem('patientToken', result.token);
            localStorage.setItem('patientData', JSON.stringify(result.user));
            
            // Update global state
            currentUser = result.user;
            isLoggedIn = true;
            
            showNotification('Login successful! Redirecting...', 'success');
            
            // Redirect to dashboard
            setTimeout(() => {
                redirectToDashboard();
            }, 1500);
            
        } else {
            showNotification(result.message || 'Login failed', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showNotification('An error occurred during login. Please try again.', 'error');
    } finally {
        hideLoading();
    }
}

// Handle registration
async function handleRegister(event) {
    event.preventDefault();
    
    const name = document.getElementById('registerName').value.trim();
    const email = document.getElementById('registerEmail').value.trim();
    const phone = document.getElementById('registerPhone').value.trim();
    const password = document.getElementById('registerPassword').value;
    const submitButton = event.target.querySelector('button[type="submit"]');
    
    // Validation
    if (!name || !email || !phone || !password) {
        showNotification('Please fill in all fields', 'error');
        return;
    }
    
    if (!isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return;
    }
    
    if (!isValidPhone(phone)) {
        showNotification('Please enter a valid phone number', 'error');
        return;
    }
    
    if (password.length < 6) {
        showNotification('Password must be at least 6 characters long', 'error');
        return;
    }
    
    const hideLoading = showLoading(submitButton);
    
    try {
        // Simulate API call - replace with actual API endpoint
        const result = await registerUser(name, email, phone, password);
        
        if (result.success) {
            showNotification('Registration successful! Please login to continue.', 'success');
            
            // Clear form
            document.getElementById('registerForm').reset();
            
            // Switch to login modal
            setTimeout(() => {
                closeModal('registerModal');
                showLogin();
                // Pre-fill email in login form
                document.getElementById('loginEmail').value = email;
            }, 1500);
            
        } else {
            showNotification(result.message || 'Registration failed', 'error');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showNotification('An error occurred during registration. Please try again.', 'error');
    } finally {
        hideLoading();
    }
}

// Simulate user authentication (replace with actual API call)
async function authenticateUser(email, password) {
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Debug logging for testing
    console.log('Login attempt:', { email: email, password: password });
    
    // Mock authentication - check against sample data from database
    const sampleUsers = {
        'john.doe@email.com': {
            userID: 1,
            name: 'John Doe',
            email: 'john.doe@email.com',
            contactNo: '111-222-3333',
            role: 'Patient',
            patientInfo: {
                age: 35,
                height: 175.5,
                weight: 80.2,
                gender: 'Male'
            }
        }
    };
    
    // Debug logging
    console.log('Available users:', Object.keys(sampleUsers));
    console.log('Email match:', sampleUsers[email] ? 'Found' : 'Not found');
    console.log('Password match:', password === 'password123' ? 'Correct' : 'Incorrect');
    
    // Check if user exists and password is correct (in real app, password would be hashed)
    if (sampleUsers[email] && password === 'password123') {
        console.log('Authentication successful');
        return {
            success: true,
            token: 'mock-jwt-token-' + Date.now(),
            user: sampleUsers[email]
        };
    } else {
        console.log('Authentication failed');
        return {
            success: false,
            message: 'Invalid email or password'
        };
    }
}

// Simulate user registration (replace with actual API call)
async function registerUser(name, email, phone, password) {
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Mock registration validation
    const existingEmails = ['john.doe@email.com', 'dr.smith@email.com'];
    
    if (existingEmails.includes(email)) {
        return {
            success: false,
            message: 'Email already exists. Please use a different email address.'
        };
    }
    
    // Simulate successful registration
    return {
        success: true,
        message: 'Registration successful'
    };
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) {
        strength += 25;
    } else {
        feedback.push('Use at least 8 characters');
    }
    
    // Uppercase check
    if (/[A-Z]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('Include uppercase letters');
    }
    
    // Lowercase check
    if (/[a-z]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('Include lowercase letters');
    }
    
    // Number or symbol check
    if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) {
        strength += 25;
    } else {
        feedback.push('Include numbers or symbols');
    }
    
    return {
        strength: strength,
        feedback: feedback,
        level: strength < 50 ? 'weak' : strength < 75 ? 'medium' : 'strong'
    };
}

// Forgot password function
async function forgotPassword(email) {
    if (!isValidEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return;
    }
    
    try {
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        showNotification('Password reset instructions have been sent to your email', 'success');
    } catch (error) {
        console.error('Forgot password error:', error);
        showNotification('Failed to send reset instructions. Please try again.', 'error');
    }
}

// Session management
function refreshAuthToken() {
    const token = localStorage.getItem('patientToken');
    if (token) {
        // In a real app, you would validate the token with the server
        // and refresh it if necessary
        console.log('Token refreshed');
    }
}

// Auto-refresh token every 15 minutes
setInterval(refreshAuthToken, 15 * 60 * 1000);
