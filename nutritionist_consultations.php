<?php
session_start();
$conn = require_once 'config.php';
date_default_timezone_set('Asia/Dhaka');

// Protect this page: allow only logged-in Nutritionists
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist') {
    header("Location: login.php");
    exit();
}

$nutritionistID = $_SESSION['userID'];
$userName = $_SESSION['Name'];
$userAvatar = strtoupper(substr($userName, 0, 2));

// Fetch Scheduled Consultations for this Nutritionist
$query = "
    SELECT 
        a.appointmentID, a.patientID, u.Name as patientName,
        p.age as patientAge, a.appointmentDate, a.consultation_link
    FROM appointment a
    JOIN users u ON a.patientID = u.userID
    LEFT JOIN patient p ON u.userID = p.patientID
    WHERE a.providerID = ? AND a.status = 'Scheduled'
    ORDER BY a.appointmentDate ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $nutritionistID);
$stmt->execute();
$consultations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Group consultations by date
$today = [];
$tomorrow = [];
$upcoming = [];
$now = new DateTime();
$tomorrowDate = (new DateTime())->modify('+1 day');

foreach ($consultations as $consult) {
    $appointmentDate = new DateTime($consult['appointmentDate']);
    if ($appointmentDate->format('Y-m-d') === $now->format('Y-m-d')) {
        $today[] = $consult;
    } elseif ($appointmentDate->format('Y-m-d') === $tomorrowDate->format('Y-m-d')) {
        $tomorrow[] = $consult;
    } elseif ($appointmentDate > $now) {
        $upcoming[] = $consult;
    }
}

// Helper functions
function formatDate($datetime) {
    return (new DateTime($datetime))->format('D, M j, Y');
}
function formatTime($datetime) {
    return (new DateTime($datetime))->format('g:i A');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Consultations - CarePlus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dark-orchid { background-color: #9932CC; }
        .text-dark-orchid { color: #9932CC; }
        .consultation-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .consultation-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(153, 50, 204, 0.15);
        }
    </style>
</head>
<body class="bg-purple-50">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white border-r">
            <div class="p-6">
                <a href="#" class="text-2xl font-bold text-dark-orchid">CarePlus</a>
            </div>
            <nav class="px-4">
                <a href="nutritionistDashboard.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-table-columns w-5"></i><span>Dashboard</span></a>
                <a href="nutritionist_schedule.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-calendar-days w-5"></i><span>Provide Schedule</span></a>
                <a href="nutritionist_consultations.php" class="flex items-center space-x-3 px-4 py-3 bg-purple-100 text-dark-orchid rounded-lg"><i class="fa-solid fa-laptop-medical w-5"></i><span>Consultations</span></a>
                <a href="#" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg"><i class="fa-solid fa-utensils w-5"></i><span>Diet Plan</span></a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-gray-600 hover:bg-slate-100 rounded-lg mt-8"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i><span>Logout</span></a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">My Consultations</h1>
                    <p class="text-gray-600 mt-1">View your upcoming scheduled appointments.</p>
                </div>
                <div class="flex items-center space-x-4">
                     <div class="text-right">
                        <p class="font-semibold text-slate-700"><?php echo htmlspecialchars($userName); ?></p>
                        <p class="text-sm text-gray-500">Nutritionist</p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-dark-orchid text-white flex items-center justify-center font-bold text-lg"><?php echo htmlspecialchars($userAvatar); ?></div>
                </div>
            </header>

            <div class="space-y-10">
                <?php function render_consultation_card($consult) { ?>
                    <div class="consultation-card bg-white p-6 rounded-lg shadow-lg">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-800 mb-3"><?php echo htmlspecialchars($consult['patientName']); ?></h3>
                            <div class="space-y-2 text-sm text-gray-600 mb-4">
                                <div class="flex items-center"><i class="fa-solid fa-hashtag w-5 text-gray-400"></i> Patient ID: <span class="font-medium text-gray-800 ml-1"><?php echo $consult['patientID']; ?></span></div>
                                <div class="flex items-center"><i class="fa-solid fa-cake-candles w-5 text-gray-400"></i> Age: <span class="font-medium text-gray-800 ml-1"><?php echo htmlspecialchars($consult['patientAge'] ?? 'N/A'); ?></span></div>
                                <div class="flex items-center"><i class="fa-solid fa-calendar-day w-5 text-gray-400"></i> Date: <span class="font-medium text-gray-800 ml-1"><?php echo formatDate($consult['appointmentDate']); ?></span></div>
                                <div class="flex items-center"><i class="fa-solid fa-clock w-5 text-gray-400"></i> Time: <span class="font-medium text-gray-800 ml-1"><?php echo formatTime($consult['appointmentDate']); ?></span></div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-2 mt-4">
                            <a href="<?php echo htmlspecialchars($consult['consultation_link']); ?>" target="_blank" class="w-full text-center font-medium text-white bg-green-500 hover:bg-green-600 px-4 py-2 rounded-lg transition">
                                <i class="fa-solid fa-video mr-2"></i>Join Meet
                            </a>
                            <a href="create_diet_plan.php?appointment_id=<?php echo $consult['appointmentID']; ?>" class="w-full text-center font-medium text-dark-orchid bg-purple-100 hover:bg-purple-200 px-4 py-2 rounded-lg transition">
                                <i class="fa-solid fa-utensils mr-2"></i>Create Diet Plan
                            </a>
                        </div>
                    </div>
                <?php } ?>
                
                <?php if (!empty($today)): ?>
                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4 border-b pb-2">Today's Consultations</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($today as $consult) { render_consultation_card($consult); } ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($tomorrow)): ?>
                <section>
                    
                </section>
                <?php endif; ?>

                <?php if (!empty($upcoming)): ?>
                <section>
                    <h2 class="text-2xl font-bold text-slate-800 mb-4 border-b pb-2">Upcoming Consultations</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($upcoming as $consult) { render_consultation_card($consult); } ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (empty($today) && empty($tomorrow) && empty($upcoming)): ?>
                <div class="text-center py-20 bg-white rounded-lg shadow-lg">
                    <i class="fa-solid fa-calendar-xmark fa-4x text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-1">No Consultations Scheduled</h3>
                    <p class="text-gray-500">You do not have any upcoming appointments.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>