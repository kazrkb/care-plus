<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid caregiver ID']);
    exit;
}

$caregiverId = (int)$_GET['id'];

try {
    // Get caregiver profile
    $profileQuery = "SELECT u.Name, u.profilePhoto, c.careGiverType, c.certifications, c.dailyRate, c.weeklyRate, c.monthlyRate 
                    FROM users u 
                    JOIN caregiver c ON u.userID = c.careGiverID 
                    WHERE u.userID = ? AND u.role = 'CareGiver'";
    $stmt = $conn->prepare($profileQuery);
    $stmt->bind_param("i", $caregiverId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        echo json_encode(['error' => 'Caregiver not found']);
        exit;
    }

    // Get available slots
    $availabilityQuery = "SELECT availabilityID, bookingType, startDate 
                         FROM caregiver_availability 
                         WHERE careGiverID = ? AND status = 'Available' AND startDate >= CURDATE() 
                         ORDER BY startDate ASC";
    $stmt = $conn->prepare($availabilityQuery);
    $stmt->bind_param("i", $caregiverId);
    $stmt->execute();
    $availability = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'profile' => $profile,
        'availability' => $availability
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error occurred']);
}

$conn->close();
?>
