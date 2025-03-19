<?php
include 'db_connection.php';

if (isset($_GET['date'])) {
    $selected_date = $_GET['date'];

    $employees_sql = "SELECT employee_code, employee_name, bus_line_name, department, job, processed_by, processed_at FROM overtime WHERE overtime_date = '$selected_date'";
    $employees_result = $conn->query($employees_sql);

    if ($employees_result->num_rows > 0) {
        echo "<table border='1'>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee Name</th>
                        <th>Bus Line</th>
                        <th>Department</th>
                        <th>Job</th>
                        <th>Processed By</th>
                        <th>Processed At</th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>";
        while ($row = $employees_result->fetch_assoc()) {
            echo "<tr>
                    <td>" . $row['employee_code'] . "</td>
                    <td>" . $row['employee_name'] . "</td>
                    <td>" . $row['bus_line_name'] . "</td>
                    <td>" . $row['department'] . "</td>
                    <td>" . $row['job'] . "</td>
                    <td>" . $row['processed_by'] . "</td>
                    <td>" . $row['processed_at'] . "</td>
                    <td><input type='checkbox' name='employees_to_delete[]' value='" . $row['employee_code'] . "'></td>
                  </tr>";
        }
        echo "</br><input type='checkbox' onclick='toggle(this);'' />Select all ?<br/><br/></tbody>
              </table>";

    } else {
        echo "No employees found for the selected date.";
    }
}
?>
