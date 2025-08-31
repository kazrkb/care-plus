<?php
session_start();
header('Content-Type: application/json');
$conn = require_once 'config.php';

// Protect this endpoint
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Patient') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No provider ID specified.']);
    exit();
}
$providerID = (int)$_GET['id'];

// Fetch Provider Profile Details
$query = "
    SELECT u.userID, u.Name, u.role, u.profilePhoto, u.contactNo,
           COALESCE(d.specialty, n.specialty) as specialty,
           COALESCE(d.yearsOfExp, n.yearsOfExp) as experience,
           COALESCE(d.hospital, 'Online Clinic') as hospital,
           COALESCE(d.consultationFees, n.consultationFees) as fees
    FROM users u
    LEFT JOIN doctor d ON u.userID = d.doctorID
    LEFT JOIN nutritionist n ON u.userID = n.nutritionistID
    WHERE u.userID = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $providerID);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
     echo json_encode(['error' => 'Provider not found.']);
     exit();
}

// --- FIXED: Updated query to fetch only future schedule slots ---
$schedule = [];
$scheduleQuery = "
    SELECT scheduleID, availableDate, startTime, endTime 
    FROM schedule 
    WHERE providerID = ? AND status = 'Available'
      AND (availableDate > CURDATE() OR (availableDate = CURDATE() AND startTime > CURTIME()))
    ORDER BY availableDate ASC, startTime ASC
";
$stmt = $conn->prepare($scheduleQuery);
$stmt->bind_param("i", $providerID);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['profile' => $profile, 'schedule' => $schedule]);
?>