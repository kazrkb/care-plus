<?php
session_start();
header('Content-Type: application/json');
$conn = require_once 'config.php';

// Ensure a nutritionist is logged in and a patient ID is provided
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Nutritionist' || !isset($_GET['patient_id'])) {
    echo json_encode([]);
    exit();
}

$nutritionistID = $_SESSION['userID'];
$patientID = (int)$_GET['patient_id'];

$stmt = $conn->prepare("SELECT appointmentID, appointmentDate FROM appointment WHERE providerID = ? AND patientID = ? ORDER BY appointmentDate DESC");
$stmt->bind_param("ii", $nutritionistID, $patientID);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($appointments);
?>