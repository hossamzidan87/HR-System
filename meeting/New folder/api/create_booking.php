<?php
header('Content-Type: application/json');

$room_id = isset($_POST['room_id']) ? $_POST['room_id'] : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';

// Validation
if (empty($room_id) || empty($name) || empty($date) || empty($start_time) || empty($end_time)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format'
    ]);
    exit;
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid time format'
    ]);
    exit;
}

// Validate end time is after start time
if ($end_time <= $start_time) {
    echo json_encode([
        'success' => false,
        'message' => 'End time must be after start time'
    ]);
    exit;
}

$data_dir = __DIR__ . '/../data/';
$file = $data_dir . $room_id . '.txt';

// Create file if it doesn't exist
if (!file_exists($file)) {
    file_put_contents($file, '');
}

// Check for booking conflicts
function timeConflict($s1, $e1, $s2, $e2) {
    return $s1 < $e2 && $e1 > $s2;
}

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $parts = explode('|', $line);
    if (count($parts) >= 4) {
        $existing_date = $parts[0];
        $existing_start = $parts[1];
        $existing_end = $parts[2];
        
        if ($existing_date === $date && timeConflict($start_time, $end_time, $existing_start, $existing_end)) {
            echo json_encode([
                'success' => false,
                'message' => 'This time slot conflicts with an existing booking'
            ]);
            exit;
        }
    }
}

// Generate unique booking ID
$booking_id = 'BK' . date('YmdHis') . substr(md5(rand()), 0, 4);

// Add new booking
$booking_line = $date . '|' . $start_time . '|' . $end_time . '|' . $name . '|' . $booking_id . "\n";
file_put_contents($file, $booking_line, FILE_APPEND);

echo json_encode([
    'success' => true,
    'booking_id' => $booking_id,
    'message' => 'Room booked successfully'
]);
?>
