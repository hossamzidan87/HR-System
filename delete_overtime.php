<?php
include 'db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['employee_code']) && isset($_POST['overtime_date'])) {
        $employee_code = $_POST['employee_code'];
        $overtime_date = $_POST['overtime_date'];
        $selected_department = $_POST['selected_department'] ?? '';

        // Delete overtime record
        $delete_sql = "DELETE FROM overtime WHERE employee_code = '$employee_code' AND overtime_date = '$overtime_date'";
        if ($conn->query($delete_sql) === TRUE) {
            echo "Record deleted successfully";
        } else {
            echo "Error: " . $delete_sql . "<br>" . $conn->error;
        }

        // Redirect back to the overtime page with the selected date and department
        $selected_date = $_POST['overtime_date'];
        $selected_department = $_POST['selected_department'] ?? '';
   // Display message and redirect after 5 seconds 
        echo '<form id="redirectForm" method="POST" action="overtime.php">'; 
        echo '<input type="hidden" name="date" value="' . htmlspecialchars($selected_date) . '">'; 
        echo '<input type="hidden" name="department" value="' . htmlspecialchars($selected_department) . '">'; 
        echo '</form>'; 
        echo '<script> setTimeout(function() { document.getElementById("redirectForm").submit(); }, 0); 
            </script>'; 
        exit();
    } else {
        echo "Employee code or overtime date missing.";
    }
} else {
    echo "Invalid request method.";
}

$conn->close();
?>
