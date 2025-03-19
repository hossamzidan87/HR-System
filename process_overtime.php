<?php
include 'db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['select_employee']) && isset($_POST['selected_date'])) {
        $selected_date = $_POST['selected_date'];  // Use the selected date
        $selected_department = $_POST['selected_department'];  // Fetch the selected department from the hidden field

        if (isset($_SESSION['username'])) {
            $processed_by = $_SESSION['username'];  // Get the username from the session
        } else {
            $processed_by = 'unknown';  // Handle case when username is not set
        }
        $processed_at = date('Y-m-d H:i:s');

 foreach ($_POST['select_employee'] as $employee_code) {
  if (isset($_POST['bus_line_name'][$employee_code])) {
   $bus_line_name = $_POST['bus_line_name'][$employee_code]; // Fetch the bus line name using employee code 
            
            // Fetch employee details
            $employee_sql = "SELECT * FROM employees WHERE employee_code = '$employee_code'";
            $employee_result = $conn->query($employee_sql);
            if ($employee_result->num_rows > 0) {
                $employee = $employee_result->fetch_assoc();
                $employee_name = $employee['first_name'];
                $department = $employee['department'];
                $job = $employee['job'];

                // Insert overtime record
                $sql = "INSERT INTO overtime (employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at, overtime_date)
                        VALUES ('$employee_code', '$employee_name', '$bus_line_name', '$department', '$job', '$processed_by', '$processed_at', '$selected_date')";
                
                if ($conn->query($sql) === TRUE) {
                    echo "Overtime record added successfully for employee code " . $employee_code;
                } else {
                    echo "Error: " . $sql . "<br>" . $conn->error;
                }
            } else {
                echo "No employee found with code " . $employee_code;
            }
        }
    }  

        //Redirect back to the overtime page with the selected date
         echo '<form id="redirectForm" method="POST" action="overtime.php">'; 
         echo '<input type="hidden" name="date" value="' . htmlspecialchars($selected_date) . '">'; 
         echo '<input type="hidden" name="department" value="' . htmlspecialchars($selected_department) . '">'; 
         echo '</form>'; 
         echo '<script>document.getElementById("redirectForm").submit();</script>'; 
        exit();
    } else {
        $selected_date = $_POST['selected_date'] ?? $today;
        $selected_department = $_POST['selected_department'] ?? '';
   // Display message and redirect after 5 seconds 
        echo '<p>No employees selected or selected date missing.</p>'; 
        echo '<form id="redirectForm" method="POST" action="overtime.php">'; 
        echo '<input type="hidden" name="date" value="' . htmlspecialchars($selected_date) . '">'; 
        echo '<input type="hidden" name="department" value="' . htmlspecialchars($selected_department) . '">'; 
        echo '</form>'; 
        echo '<script> setTimeout(function() { document.getElementById("redirectForm").submit(); }, 1000); 
            </script>'; 
    exit(); 
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
