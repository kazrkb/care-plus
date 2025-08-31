<?php
// caregiver_add_progress.php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');

$conn = require_once 'config.php';

if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'CareGiver') {
    http_response_code(403);
    echo "Access denied"; exit;
}

$careGiverID = (int)$_SESSION['userID'];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$patients = [];
$listSql = "
    SELECT
        p.patientID,
        u.`Name` AS patientName,
        GROUP_CONCAT(DISTINCT cb.status ORDER BY cb.status SEPARATOR ', ') AS statuses
    FROM caregiverbooking cb
    JOIN patient p ON p.patientID = cb.patientID
    JOIN users u   ON u.userID    = p.patientID
    WHERE cb.careGiverID = ?
    GROUP BY p.patientID, u.`Name`
    ORDER BY u.`Name` ASC
";
$stmt = $conn->prepare($listSql);
$stmt->bind_param('i', $careGiverID);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $patients[] = $row;
$stmt->close();
?>