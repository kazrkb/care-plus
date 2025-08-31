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
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-xl">
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-gray-800">Add Progress Record</h1>
        <p class="text-gray-500 mt-1">Record patient health measurements quickly</p>
    </div>

    <div class="card">
        <form id="progressForm" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">

            <label class="block mt-4 font-semibold text-gray-700" for="patientID">Patient</label>
            <select id="patientID" name="patientID" class="w-full mt-1 p-2 border rounded-lg" required>
                <option value="" disabled selected>— Select Patient —</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?php echo (int)$p['patientID']; ?>">
                        <?php
                        $statuses = $p['statuses'] ? ' — '.$p['statuses'] : '';
                        echo htmlspecialchars($p['patientName'].$statuses, ENT_QUOTES);
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block font-semibold text-gray-700" for="dataType">Data Type</label>
                    <select id="dataType" name="dataType" class="w-full mt-1 p-2 border rounded-lg" required>
                        <option value="" disabled selected>— Select —</option>
                        <option value="blood_pressure">Blood Pressure</option>
                        <option value="weight">Weight</option>
                        <option value="blood_sugar">Blood Sugar</option>
                        <option value="heart_rate">Heart Rate</option>
                        <option value="temperature">Temperature</option>
                        <option value="oxygen_level">Oxygen Level</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700" for="value">Value</label>
                    <input id="value" name="value" type="number" step="0.01" class="w-full mt-1 p-2 border rounded-lg" required>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700" for="measurementUnit">Unit</label>
                    <select id="measurementUnit" name="measurementUnit" class="w-full mt-1 p-2 border rounded-lg" required>
                        <option value="" disabled selected>— Select —</option>
                        <option value="mmHg">mmHg (BP)</option>
                        <option value="kg">kg (Weight)</option>
                        <option value="mmol/L">mmol/L (Blood Sugar)</option>
                        <option value="bpm">bpm (Heart Rate)</option>
                        <option value="°C">°C (Temperature)</option>
                        <option value="%">%(Oxygen Level)</option>
                    </select>
                </div>
            </div>

            <label class="block mt-4 font-semibold text-gray-700" for="notes">Notes (optional)</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Any note…" class="w-full mt-1 p-2 border rounded-lg"></textarea>

            <button type="submit" id="saveBtn" class="mt-6 w-full bg-purple-600 text-white font-semibold py-2 rounded-lg hover:bg-purple-700 transition">Save Record</button>

            <div id="msg" class="msg"></div>
        </form>
    </div>
</div>

<script>
const f = document.getElementById('progressForm');
const msg = document.getElementById('msg');

f.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.style.display = 'none';
    msg.className = 'msg';

    const pid = f.patientID.value;
    const dt  = f.dataType.value;
    const val = f.value.value;
    const mu  = f.measurementUnit.value;

    if (!pid || !dt || !val || !mu || isNaN(val)) {
        msg.textContent = 'Please complete all fields correctly.';
        msg.classList.add('err'); msg.style.display = 'block';
        return;
    }

    const formData = new FormData(f);
    try {
        const res = await fetch('caregiver_add_progress.php', { method:'POST', body: formData, credentials:'same-origin', headers:{'Accept':'application/json'} });
        const data = await res.json();
        if (data.success) {
            msg.textContent = 'Saved successfully!';
            msg.classList.add('ok'); msg.style.display = 'block';
            f.reset();
            f.patientID.selectedIndex = 0;
            f.dataType.selectedIndex = 0;
            f.measurementUnit.selectedIndex = 0;
        } else {
            msg.textContent = data.message || 'Failed to save';
            msg.classList.add('err'); msg.style.display = 'block';
        }
    } catch (err) {
        msg.textContent = 'Network or server error.';
        msg.classList.add('err'); msg.style.display = 'block';
    }
});
</script>

</body>
</html>
