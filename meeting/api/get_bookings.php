<?php
header('Content-Type: application/json');

$room_id = isset($_GET['room_id']) ? $_GET['room_id'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($room_id) || empty($date)) {
    echo json_encode([]);
    exit;
}

$data_dir = __DIR__ . '/../data/';
$file = $data_dir . $room_id . '.txt';

$bookings = [];

if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) >= 5) {
            $booking_date = $parts[0];
            $booking_start = $parts[1];
            $booking_end = $parts[2];
            $booking_name = $parts[3];
            $booking_id = isset($parts[4]) ? $parts[4] : '';
            
            if ($booking_date === $date) {
                $bookings[] = [
                    'date' => $booking_date,
                    'start_time' => $booking_start,
                    'end_time' => $booking_end,
                    'name' => $booking_name,
                    'booking_id' => $booking_id
                ];
            }
        }
    }
}

echo json_encode($bookings);
?>
