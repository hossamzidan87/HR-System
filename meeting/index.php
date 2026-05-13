<?php
// Meeting Room Booking System
error_reporting(E_ALL);
ini_set('display_errors', 1);

$rooms = [
    'room1' => 'Meeting Room 1',
    'room2' => 'Meeting Room 2',
    'room3' => 'Meeting Room 3',
    'room4' => 'Meeting Room 4',
    'showroom1' => 'Showroom 1',
    'showroom2' => 'Showroom 2'
];

$data_dir = __DIR__ . '/data/';

// Create data files if they don't exist
foreach ($rooms as $room_id => $room_name) {
    $file = $data_dir . $room_id . '.txt';
    if (!file_exists($file)) {
        file_put_contents($file, '');
    }
}

$operating_hours_start = '08:00';
$operating_hours_end = '18:00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Room Booking System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Meeting Room Booking System</h1>
            <p>Operating Hours: <?php echo date('H:i', strtotime($operating_hours_start . ' -0 minutes')); ?> - <?php echo date('H:i', strtotime($operating_hours_end . ' -30 minutes')); ?></p>
        </header>

        <div class="content">
            <div class="left-panel">
                <h2>Select a Meeting Room</h2>
                <div class="room-list">
                    <?php foreach ($rooms as $room_id => $room_name): ?>
                        <button class="room-btn" data-room-id="<?php echo $room_id; ?>">
                            <?php echo $room_name; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="right-panel">
                <div id="room-info" class="hidden">
                    <h2>Room Details</h2>
                    <p>Selected Room: <strong id="selected-room-name"></strong></p>
                    
                    <div class="booking-form">
                        <h3>Book This Room</h3>
                        
                        <div class="form-group">
                            <label for="booking-date">Select Date:</label>
                            <input type="date" id="booking-date" required>
                        </div>

                        <div class="form-group">
                            <label for="recurrence-type">Recurrence Type:</label>
                            <select id="recurrence-type" required>
                                <option value="once">One-time booking</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <div id="daily-options" class="hidden">
                            <div class="form-group">
                                <label for="daily-end-date">Repeat until: (1 week limit)</label>
                                <input type="date" id="daily-end-date" readonly>
                            </div>
                        </div>

                        <div id="weekly-options" class="hidden">
                            <div class="form-group">
                                <label>Select days of the week:</label>
                                <div class="days-checkbox">
                                    <label><input type="checkbox" name="day" value="1"> Monday</label>
                                    <label><input type="checkbox" name="day" value="2"> Tuesday</label>
                                    <label><input type="checkbox" name="day" value="3"> Wednesday</label>
                                    <label><input type="checkbox" name="day" value="4"> Thursday</label>
                                    <label><input type="checkbox" name="day" value="5"> Friday</label>
                                    <label><input type="checkbox" name="day" value="6"> Saturday</label>
                                    <label><input type="checkbox" name="day" value="0"> Sunday</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="weekly-end-date">Repeat until: (1 month limit)</label>
                                <input type="date" id="weekly-end-date" readonly>
                            </div>
                        </div>

                        <div id="monthly-options" class="hidden">
                            <div class="form-group">
                                <label for="monthly-end-date">Repeat until: (1 year limit)</label>
                                <input type="date" id="monthly-end-date" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="booking-start-time">Start in:</label>
                                <select id="booking-start-time" required>
                                    <option value="">-- Select start time --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="booking-end-time">End in:</label>
                                <select id="booking-end-time" required>
                                    <option value="">-- Select end time --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="booking-name">Your Name:</label>
                            <input type="text" id="booking-name" placeholder="Enter your name" required>
                        </div>

                        <button id="submit-booking" class="btn-submit">Book Now</button>
                        <div id="booking-message" class="message hidden"></div>
                    </div>

                    <h3>Availability for <span id="availability-date"></span></h3>
                    <div id="availability-list" class="availability-list">
                        <!-- Availability will be loaded here -->
                    </div>
                </div>

                <!-- Delete Modal -->
                <div id="delete-modal" class="modal hidden">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <h3>Delete Booking</h3>
                        <p>Enter password to delete this booking:</p>
                        <input type="password" id="delete-password" placeholder="Enter password" />
                        <div class="modal-buttons">
                            <button id="confirm-delete" class="btn-delete">Delete</button>
                            <button id="cancel-delete" class="btn-cancel">Cancel</button>
                        </div>
                        <div id="delete-message" class="message hidden"></div>
                    </div>
                </div>

                <div id="no-room-selected" class="info-message">
                    <p>Please select a meeting room to view availability and make a booking.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const rooms = <?php echo json_encode($rooms); ?>;
        const dataDir = 'api/';
        const operatingStart = '<?php echo $operating_hours_start; ?>';
        const operatingEnd = '<?php echo $operating_hours_end; ?>';
        let selectedRoomId = null;
    </script>
    <script src="js/app.js"></script>
</body>
</html>
