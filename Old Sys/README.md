# Web Application

## Overview

This web application is designed to manage employee data, evaluations, and reports. It includes features for handling overtime, generating various reports, and managing user access.

## Features

- Employee Management
  - Add, edit, and delete employee records
  - Fetch employee data

- Overtime Management
  - Process and delete overtime records
  - Generate weekly, monthly, and quarterly overtime reports

- Evaluations
  - Conduct and manage employee evaluations
  - Generate quarterly evaluation reports

- User Access
  - Login and logout functionality
  - Page access control

## File Structure

- `check_cookies.php`: Script to check cookies for user sessions
- `cpanel.php`: Control panel for administrators
- `daily_report.php`: Generate daily reports
- `db_connection.php`: Database connection script
- `delete_overtime.php`: Script to delete overtime records
- `employee_list.php`: List of employees
- `evaluation_cpanel.php`: Control panel for evaluations
- `evaluation.php`: Manage employee evaluations
- `fetch_employees.php`: Fetch employee data
- `general_cpanel.php`: General control panel
- `index.php`: Main entry point of the application
- `login.php`: User login page
- `logout.php`: User logout script
- `monthly_report.php`: Generate monthly reports
- `overtime DEL.php`: Script to handle overtime deletions
- `overtime_cpanel.php`: Control panel for overtime management
- `overtime_home.php`: Overtime management home page
- `overtime_report.php`: Generate overtime reports
- `overtime.php`: Manage overtime records
- `page_access.php`: Manage page access control
- `process_overtime.php`: Process overtime records
- `quarter_evaluation.php`: Generate quarterly evaluation reports
- `quarter_report.php`: Generate quarterly reports
- `register.php`: User registration page
- `sadv_cpanel.php`: Control panel for SADV
- `sadv_list.php`: List of SADV records
- `test.php`: Test script
- `weekly_overtime.php`: Generate weekly overtime reports

## Installation

1. Clone the repository:
    ```sh
    git clone <repository-url>
    ```

2. Install dependencies using Composer:
    ```sh
    composer install
    ```

3. Configure the database connection in `db_connection.php`.

4. Start the web server and navigate to `index.php` in your browser.

## Usage

1. Login using your credentials.
2. Navigate through the control panels to manage employees, evaluations, and reports.
3. Use the provided scripts to generate various reports and manage overtime records.

## License

This project is licensed under the MIT License.