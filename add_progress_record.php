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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Progress Record</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<style>
  body { font-family: 'Inter', sans-serif; background: #f3f4f6; }
  .card { background: #fff; border-radius: 1rem; padding: 2rem; box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
  .msg { display: none; margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-weight: 500; }
  .msg.ok { background: #d1fae5; color: #065f46; }
  .msg.err { background: #fee2e2; color: #991b1b; }
</style>
</head>