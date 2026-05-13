<?php
header('Content-Type: application/json');

$correct_password = 'Fetih@book';

$booking_id = isset($_POST['booking_id']) ? $_POST['booking_id'] : '';
$room_id = isset($_POST['room_id']) ? $_POST['room_id'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation
if (empty($booking_id) || empty($room_id) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Verify password
if ($password !== $correct_password) {
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect password'
    ]);
    exit;
}

$data_dir = __DIR__ . '/../data/';
$file = $data_dir . $room_id . '.txt';

if (!file_exists($file)) {
    echo json_encode([
        'success' => false,
        'message' => 'Room file not found'
    ]);
    exit;
}

// Read all lines and find the one to delete
$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$foundBooking = false;
$newLines = [];

foreach ($lines as $line) {
    $parts = explode('|', $line);
    if (count($parts) >= 5 && $parts[4] === $booking_id) {
        $foundBooking = true;
        // Skip this line (delete it)
        continue;
    }
    $newLines[] = $line;
}

if (!$foundBooking) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
    exit;
}

// Write back the updated lines
file_put_contents($file, implode("\n", $newLines) . "\n");

echo json_encode([
    'success' => true,
    'message' => 'Booking deleted successfully'
]);
?>
