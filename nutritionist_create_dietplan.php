<?php
session_start();

// Database connection (adjust these values to match your setup)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "healthcare";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ========= AUTHENTICATION & INITIALIZATION =========
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$nutritionistID = $_SESSION['userID'];
$appointmentID = null;
$patientInfo = null;
$errorMsg = "";
$successMsg = "";

// ========= VALIDATE APPOINTMENT SELECTION =========
if (isset($_GET['appointment_id']) && is_numeric($_GET['appointment_id'])) {
    $appointmentID = (int)$_GET['appointment_id'];
    
    // Fetch patient info for this appointment (ensure it belongs to this nutritionist)
    $patientQuery = "SELECT a.appointmentID, a.appointmentDate, a.notes,
                            p.patientID, u.Name as patientName, u.userID,
                            pt.age, pt.gender, pt.height, pt.weight
                     FROM appointment a 
                     JOIN patient p ON a.patientID = p.patientID 
                     JOIN users u ON p.patientID = u.userID 
                     LEFT JOIN patient pt ON p.patientID = pt.patientID
                     WHERE a.appointmentID = ? AND a.providerID = ? AND a.status = 'Scheduled'";
    
    $stmt = $conn->prepare($patientQuery);
    $stmt->bind_param("ii", $appointmentID, $nutritionistID);
    $stmt->execute();
    $patientInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$patientInfo) {
        $errorMsg = "Invalid appointment selected or you do not have permission to access it.";
        $appointmentID = null;
    }
} else if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $errorMsg = "No appointment selected. Please create a diet plan from the 'My Consultations' page.";
}

// ========= HANDLE FORM SUBMISSION =========
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'])) {
    $appointmentID = (int)$_POST['appointment_id'];
    $dietType = trim($_POST['diet_type']);
    $caloriesPerDay = (float)$_POST['calories_per_day'];
    $mealGuidelines = trim($_POST['meal_guidelines']);
    $exerciseGuidelines = trim($_POST['exercise_guidelines']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $patientNameForMessage = $_POST['patient_name']; // Get patient name from hidden input
    $patientID = (int)$_POST['patient_id'];
    
    // Basic validation
    if (empty($dietType) || empty($caloriesPerDay) || empty($mealGuidelines)) {
        $errorMsg = "Diet Type, Calories Per Day, and Meal Guidelines are required fields.";
    } else {
        // Insert diet plan into database
        $sql = "INSERT INTO dietplan (appointmentID, nutritionistID, patientID, dietType, caloriesPerDay, mealGuidelines, exerciseGuidelines, startDate, endDate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        $stmt->bind_param("iiisdssss", $appointmentID, $nutritionistID, $patientID, $dietType, $caloriesPerDay, $mealGuidelines, $exerciseGuidelines, $startDate, $endDate);
        
        if ($stmt->execute()) {
            // Update appointment status to 'Completed'
            $updateSql = "UPDATE appointment SET status = 'Completed' WHERE appointmentID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $appointmentID);
            $updateStmt->execute();
            $updateStmt->close();
            
            $successMsg = "Diet plan created for " . htmlspecialchars($patientNameForMessage) . " successfully!";
        } else {
            $errorMsg = "Failed to save diet plan.";
        }
        $stmt->close();
    }
}

// Fetch Nutritionist's Info for the header
$nutritionistQuery = "SELECT u.Name, n.specialty FROM users u JOIN nutritionist n ON u.userID = n.nutritionistID WHERE u.userID = ?";
$nutStmt = $conn->prepare($nutritionistQuery);
$nutStmt->bind_param("i", $nutritionistID);
$nutStmt->execute();
$nutritionistInfo = $nutStmt->get_result()->fetch_assoc();
$nutStmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Diet Plan - Healthcare System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 5px;
            font-weight: 300;
        }
        
        .header .specialty {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .patient-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        
        .patient-details {
            flex: 1;
            min-width: 250px;
        }
        
        .patient-details h3 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .patient-details p {
            color: #666;
            margin: 2px 0;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 1.3em;
            color: #333;
        }
        
        .diet-icon {
            font-size: 2em;
            color: #28a745;
            margin-right: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"], 
        input[type="number"], 
        input[type="date"], 
        select, 
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #28a745;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .example-text {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
            font-style: italic;
        }
        
        .save-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1em;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        
        .save-btn:hover {
            transform: translateY(-2px);
        }
        
        .save-btn:before {
            content: "💾";
            margin-right: 10px;
        }
        
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d1eddd;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 0;
            }
            
            .patient-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>
                <?php echo htmlspecialchars($nutritionistInfo['Name'] ?? 'Nutritionist'); ?>
            </h1>
            <div class="specialty">
                <?php echo htmlspecialchars($nutritionistInfo['specialty'] ?? 'Nutrition Specialist'); ?>
            </div>
        </div>
        
        <div class="content">
            <!-- Display Messages -->
            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success"><?php echo $successMsg; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php endif; ?>
            
            <?php if ($patientInfo): ?>
                <!-- Patient Information -->
                <div class="patient-info">
                    <div class="patient-details">
                        <h3>Patient Name: <?php echo htmlspecialchars($patientInfo['patientName']); ?></h3>
                        <p><strong>Patient ID:</strong> <?php echo $patientInfo['patientID']; ?></p>
                        <p><strong>Consultation Notes:</strong> <?php echo htmlspecialchars($patientInfo['notes'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="patient-details">
                        <p><strong>Age:</strong> <?php echo $patientInfo['age'] ?? 'N/A'; ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patientInfo['gender'] ?? 'N/A'); ?></p>
                        <p><strong>Height:</strong> <?php echo $patientInfo['height'] ? $patientInfo['height'] . ' cm' : 'N/A'; ?></p>
                        <p><strong>Weight:</strong> <?php echo $patientInfo['weight'] ? $patientInfo['weight'] . ' kg' : 'N/A'; ?></p>
                        <p><strong>Date:</strong> <?php echo date('m/d/Y'); ?></p>
                    </div>
                </div>
                
                <!-- Diet Plan Form -->
                <form method="POST" action="">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointmentID; ?>">
                    <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($patientInfo['patientName']); ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $patientInfo['patientID']; ?>">
                    
                    <!-- Diet Plan Details Section -->
                    <div class="form-section">
                        <div class="section-title">
                            <span class="diet-icon">🥗</span>
                            Diet Plan Details
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="diet_type">Diet Type *</label>
                                <select name="diet_type" id="diet_type" required>
                                    <option value="">Select Diet Type</option>
                                    <option value="Weight Loss">Weight Loss</option>
                                    <option value="Weight Gain">Weight Gain</option>
                                    <option value="Diabetes Management">Diabetes Management</option>
                                    <option value="Heart Healthy">Heart Healthy</option>
                                    <option value="Low Sodium">Low Sodium</option>
                                    <option value="High Protein">High Protein</option>
                                    <option value="Vegetarian">Vegetarian</option>
                                    <option value="Vegan">Vegan</option>
                                    <option value="Keto">Keto</option>
                                    <option value="Mediterranean">Mediterranean</option>
                                    <option value="General Nutrition">General Nutrition</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="calories_per_day">Daily Calorie Target *</label>
                                <input type="number" name="calories_per_day" id="calories_per_day" 
                                       step="0.01" min="800" max="5000" required
                                       placeholder="e.g., 2000">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Plan Start Date</label>
                                <input type="date" name="start_date" id="start_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">Plan End Date</label>
                                <input type="date" name="end_date" id="end_date" 
                                       value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="meal_guidelines">Meal Guidelines & Food Recommendations *</label>
                            <textarea name="meal_guidelines" id="meal_guidelines" required
                                      placeholder="Provide detailed meal guidelines, food recommendations, portion sizes, meal timing, etc."></textarea>
                            <div class="example-text">
                                e.g., Breakfast: 1 cup oatmeal with berries (300 cal)<br>
                                Mid-morning: 1 apple with almond butter (200 cal)<br>
                                Lunch: Grilled chicken salad with olive oil dressing (450 cal)
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exercise & Additional Instructions -->
                    <div class="form-section">
                        <div class="section-title">
                            <span class="diet-icon">🏃‍♀️</span>
                            Exercise Guidelines & Additional Instructions
                        </div>
                        
                        <div class="form-group">
                            <label for="exercise_guidelines">Exercise Recommendations</label>
                            <textarea name="exercise_guidelines" id="exercise_guidelines"
                                      placeholder="Provide exercise recommendations, activity guidelines, and lifestyle suggestions"></textarea>
                            <div class="example-text">
                                e.g., 30 minutes brisk walking daily<br>
                                Strength training 2-3 times per week<br>
                                Stay hydrated - drink 8-10 glasses of water daily
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="save-btn">Save Diet Plan</button>
                </form>
                
            <?php else: ?>
                <!-- No Appointment Selected -->
                <div class="alert alert-danger">
                    <h3>No Consultation Selected</h3>
                    <p>Please select a consultation from your 'My Consultations' page to create a diet plan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Set minimum date for end date based on start date
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('end_date');
            endDateInput.min = startDate;
            
            // If end date is before start date, update it
            if (endDateInput.value && endDateInput.value < startDate) {
                const newEndDate = new Date(startDate);
                newEndDate.setDate(newEndDate.getDate() + 30);
                endDateInput.value = newEndDate.toISOString().split('T')[0];
            }
        });
        
        // Auto-calculate BMI if height and weight are available
        <?php if ($patientInfo && $patientInfo['height'] && $patientInfo['weight']): ?>
        const height = <?php echo $patientInfo['height']; ?> / 100; // Convert to meters
        const weight = <?php echo $patientInfo['weight']; ?>;
        const bmi = (weight / (height * height)).toFixed(1);
        console.log('Patient BMI:', bmi);
        <?php endif; ?>
    </script>
</body>
</html>
