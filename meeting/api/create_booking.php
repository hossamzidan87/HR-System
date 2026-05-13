<?php
header('Content-Type: application/json');

$room_id = isset($_POST['room_id']) ? $_POST['room_id'] : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';

// Handle both single date and multiple dates
$dates = [];
if (isset($_POST['dates'])) {
    // Multiple dates for recurring bookings
    $dates_json = $_POST['dates'];
    $dates = json_decode($dates_json, true);
    if (!is_array($dates) || empty($dates)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid dates format'
        ]);
        exit;
    }
} elseif (isset($_POST['date'])) {
    // Single date for backward compatibility
    $dates = [$_POST['date']];
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Validation
if (empty($room_id) || empty($name) || empty($start_time) || empty($end_time)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
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

// Validate all dates and check for conflicts
$valid_dates = [];
$conflict_dates = [];
$booking_ids = [];

foreach ($dates as $date) {
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        continue;
    }
    
    // Read existing bookings for this date
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $has_conflict = false;
    
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $existing_date = $parts[0];
                $existing_start = $parts[1];
                $existing_end = $parts[2];
                
                if ($existing_date === $date && timeConflict($start_time, $end_time, $existing_start, $existing_end)) {
                    $has_conflict = true;
                    break;
                }
            }
        }
    }
    
    if ($has_conflict) {
        $conflict_dates[] = $date;
    } else {
        $valid_dates[] = $date;
    }
}

// Book valid dates
$booking_details = [];
foreach ($valid_dates as $date) {
    // Generate unique booking ID
    $booking_id = 'BK' . date('YmdHis') . substr(md5(rand()), 0, 4);
    
    // Add new booking
    $booking_line = $date . '|' . $start_time . '|' . $end_time . '|' . $name . '|' . $booking_id . "\n";
    file_put_contents($file, $booking_line, FILE_APPEND);
    
    $booking_details[] = [
        'date' => $date,
        'booking_id' => $booking_id
    ];
    $booking_ids[] = $booking_id;
}

echo json_encode([
    'success' => true,
    'booked_count' => count($valid_dates),
    'conflict_count' => count($conflict_dates),
    'booking_ids' => $booking_ids,
    'booking_details' => $booking_details,
    'message' => 'Bookings processed'
]);
?>
