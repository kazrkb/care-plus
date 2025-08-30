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

     