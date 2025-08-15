<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarePlus - Healthcare Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center">
                    <h1 class="text-3xl font-bold text-dark-orchid">CarePlus</h1>
                    <span class="ml-2 text-gray-600">Healthcare Management</span>
                </div>
                <div class="space-x-4">
                    <a href="login.php" class="bg-dark-orchid text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">Login</a>
                    <a href="register.php" class="border border-purple-600 text-purple-600 px-6 py-2 rounded-lg hover:bg-purple-50 transition">Register</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-gradient text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-5xl font-bold mb-6">Your Health, Our Priority</h2>
            <p class="text-xl mb-8 max-w-3xl mx-auto">Connect with healthcare professionals, manage appointments, and take control of your health journey with CarePlus.</p>
            <div class="space-x-4">
                <a href="register.php" class="bg-white text-purple-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">Get Started</a>
                <a href="login.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-purple-600 transition">Sign In</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h3 class="text-3xl font-bold text-center text-gray-800 mb-12">Our Services</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fa-solid fa-user-doctor fa-3x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">Doctor Consultations</h4>
                    <p class="text-gray-600">Book appointments with qualified doctors across various specialties.</p>
                </div>
                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fa-solid fa-utensils fa-3x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">Nutrition Guidance</h4>
                    <p class="text-gray-600">Get personalized diet plans from certified nutritionists.</p>
                </div>
                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fa-solid fa-user-nurse fa-3x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">Home Care Services</h4>
                    <p class="text-gray-600">Professional caregivers for in-home medical assistance.</p>
                </div>
                <div class="text-center p-6 rounded-lg shadow-lg hover:shadow-xl transition">
                    <i class="fa-solid fa-file-medical fa-3x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">Health Records</h4>
                    <p class="text-gray-600">Secure digital storage of your medical history and documents.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access Section -->
    <section class="py-16 bg-purple-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h3 class="text-3xl font-bold text-center text-gray-800 mb-12">Quick Access</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <a href="login.php" class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition text-center">
                    <i class="fa-solid fa-user fa-2x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">For Patients</h4>
                    <p class="text-gray-600">Access your health dashboard and manage appointments</p>
                </a>
                <a href="login.php" class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition text-center">
                    <i class="fa-solid fa-stethoscope fa-2x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">For Healthcare Providers</h4>
                    <p class="text-gray-600">Manage schedules, patients, and consultations</p>
                </a>
                <a href="login.php" class="bg-white p-8 rounded-lg shadow-lg hover:shadow-xl transition text-center">
                    <i class="fa-solid fa-hands-helping fa-2x text-dark-orchid mb-4"></i>
                    <h4 class="text-xl font-semibold mb-2">For Caregivers</h4>
                    <p class="text-gray-600">Connect with families needing care services</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h5 class="text-lg font-semibold mb-4">CarePlus</h5>
                    <p class="text-gray-400">Your comprehensive healthcare management platform.</p>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-4">Services</h5>
                    <ul class="space-y-2 text-gray-400">
                        <li>Doctor Consultations</li>
                        <li>Nutrition Plans</li>
                        <li>Home Care</li>
                        <li>Health Records</li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-4">Quick Links</h5>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="login.php" class="hover:text-white">Login</a></li>
                        <li><a href="register.php" class="hover:text-white">Register</a></li>
                        <li><a href="#" class="hover:text-white">About Us</a></li>
                        <li><a href="#" class="hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-semibold mb-4">Contact</h5>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fa-solid fa-phone mr-2"></i>+880 123 456 789</li>
                        <li><i class="fa-solid fa-envelope mr-2"></i>info@careplus.com</li>
                        <li><i class="fa-solid fa-location-dot mr-2"></i>Dhaka, Bangladesh</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 CarePlus. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
