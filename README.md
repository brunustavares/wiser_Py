<p align="center">
  <img src="static/img/logo.jpg" alt="val.Py Logo" width="300">
</p>

# wiser.Py
`wiser.Py` is a Python-based web application that provides integrated management of data and indicators related to student assessments conducted on WISEflow and Moodle (PlataformAbERTA).

It serves as a central hub for administrators and managers to monitor, manage, and synchronize academic data between various university systems, including the SiGES academic management system, a central integration database (BDInt), WISEflow, and Moodle.

It was originally developed for [Universidade Aberta (UAb)](https://portal.uab.pt/).

## 1. Core Features

`wiser.Py` offers a rich set of features through a user-friendly web interface, protected by a role-based access control system.

### Data Reporting and Visualization
*   **Comprehensive Reports**: Generate detailed reports on student participation, grades, and biometric data from both WISEflow and Moodle.
    *   **Actionable Insights**: Directly manage assessment statuses (e.g., annulment, warnings) and trigger automated email notifications to students from the report view.
    *   **Data Export**: Export report data and statistical indicators to CSV format for external analysis.
*   **Student-Specific History**: View a complete academic history for any student, including a graphical representation of their biometric verification scores across assessments.
    *   **Submission Export**: Download a student's submission for a specific assessment as a single merged PDF file. The system automatically generates and prepends a cover sheet containing the assessment details.
    *   **Event Log Export**: Download the detailed event log for a specific student's assessment attempt as a CSV file.
*   **Statistical Dashboards**: Access aggregated statistics and performance indicators, filterable by academic year, for both WISEflow and Moodle assessments.
*   **AIDA Indicators**: Interface with the AIDA web service to retrieve specialized academic indicators.

### System Administration and Management
*   **User Management**: Administrators can create, delete, and manage application users and their specific permissions (e.g., `admin`, `flowman`, `bioman`).
*   **Password Control**: Secure user authentication with password hashing, mandatory password changes on first login, and password reset functionality.
*   **Activity Logging**: All significant user actions are logged for auditing and monitoring purposes. Logs are automatically purged after one year.

### Data Synchronization and Orchestration
*   **Endpoint Execution**: Manually trigger synchronization scripts that handle data flows between systems. This includes:
    *   **WISEflow Sync**: Synchronize students (`sync_users`), flows (`sync_flows`), participants (`sync_parts`), and activity statistics (`sync_stats`). These scripts are orchestrated by the Python application but are implemented in PHP.
    *   **Moodle Sync**: Collect submissions (`sync_subs`) and grades (`sync_grades`) from PlataformAbERTA (currently marked as discontinued in the UI).
*   **Calendar Management**: Upload a CSV file to bulk-create assessment schedules (flows) in the system. Integrates with Moodle Web Service to automatically determine flow types.

### WISEflow API Integration Details
The application orchestrates several PHP scripts to perform detailed synchronization tasks with the WISEflow API. The logic is primarily driven by views in the integration database (`BDInt`), which determine what data needs to be created, updated, or removed in WISEflow.

*   **`sync_users.php` (Student Synchronization)**: Manages the lifecycle of student accounts in WISEflow based on data from the `BDInt` database.
    *   **User Creation**:
        *   Identifies new students from the `wiseflow.vw_newstdts_2wiseflow` view.
        *   Creates new users via a `POST` request to `/license/user`.
        *   The request payload includes `firstName`, `lastName`, `email`, a default language (`pt`), a predefined role ID, and `externalIds` to map the WISEflow user to internal university identifiers (e.g., student number).
    *   **User Deactivation**:
        *   Identifies students to be deactivated from the `wiseflow.vw_takestdts_fromwiseflow` view.
        *   Sends a `PUT` request to `/users/{userId}` with the `loginDeactivated` flag set to `true`.
    *   **User Reactivation/Update**:
        *   Identifies students to be reactivated or whose names have changed from the `wiseflow.vw_renewstdts_atwiseflow` view.
        *   Sends a `PUT` request to `/users/{userId}` with `loginDeactivated` set to `false` and updated `firstName` and `lastName` fields.

*   **`sync_flows.php` (Flow Synchronization)**: Manages the creation and updating of assessment schedules (flows) in WISEflow.
    *   **Flow Creation**: Creates new assessment flows by sending a `POST` request to `/flows`. The payload includes the flow title, subtitle (used for course codes), start/end dates, and other configuration details sourced from the integration database.
    *   **Flow Updates**: Modifies existing flows (e.g., changing dates or titles) using a `PUT` request to `/flows/{flowId}`.

*   **`sync_parts.php` (Participant Synchronization)**: Manages student enrollment in specific assessment flows.
    *   **Enrollment**: Adds participants to a flow using `POST /flows/{flowId}/participants/add`, identifying students by their external ID.
    *   **Removal**: Removes participants from a flow via `DELETE /flows/{flowId}/participants/{partId}`.
    *   **Special Accommodations (NEEs)**: Adjusts exam times for students with special needs. It fetches global flow dates (`GET /flows/{flowId}/dates`) and participant-specific dates (`GET /flows/{flowId}/participants/{partId}/dates`), then applies custom end times with a `PATCH` request to the participant's date endpoint.
    *   **Grade Migration**: Fetches assessment results from WISEflow using `GET /flows/{flowId}/assessments` and stores them in the integration database for later synchronization with the main academic system.

*   **`sync_stats.php` (Statistics Synchronization)**: Gathers activity data from WISEflow for reporting.
    *   **Data Collection**: Iterates through active or recently concluded flows, fetching participant lists (`GET /flows/{flowId}/participants`) and their submission statuses.
    *   **Aggregation**: The script processes this data and calls a stored procedure (`update_Stats`) in the `BDInt` database to aggregate daily submission counts and other key performance indicators for the statistical dashboards.

### Specialized Tools and Utilities
*   **Flow Management (SOS Module)**:
    *   Extend the duration of active WISEflow exams for all participants who have not yet submitted.
    *   **Data Export**: Export the list of students who received extra time in a flow to a CSV file.
    *   Purge all participants from a specific flow, for example, in case of a cancellation or major error.
*   **Grade Sync Reset (SOS Module)**: Manually reset the synchronization status of grades for specific courses and students, forcing a new sync attempt to the SiGES academic system.
*   **Biometric Data Management**: View and manage student reference photos used for biometric identity verification in WISEflow.
*   **Event Monitoring (Sentinel_F Module)**:
    *   Configure and manage specific WISEflow participation event types to monitor (e.g., `FACIAL_RECOGNITION`, `PAPER_HANDED_IN`).
    *   View logs of monitored events for specific dates, showing the flow, student, and event details.
    *   **Student Analysis**: Analyze reported events per student with interactive graphical visualizations to identify patterns.
    *   Manage settings for the Sentinel_F module, including recipient mailboxes for notifications.
    *   This feature appears to be for administrators to closely track critical events within assessment flows.
*   **Email Notifications**:
    *   Configure SMTP server settings for sending automated emails.
    *   Manage email templates for various notifications (e.g., assessment cancellations, warnings).
    *   Send bulk `BCC` emails to students for important announcements. Includes a summary report sent to the manager with the list of recipients.
*   **WISEflow File Checker**: Upload and analyze `.wf` (WISEflow exam) files to identify the student, flow, flow type, and submission timestamp.

## 2. Architecture

`wiser.Py` is built as a monolith Flask application that acts as both a user-facing frontend and an orchestration layer for various backend processes.

### Components
*   **Web Application (`wiser.Py`)**: The core Flask application that provides the web interface, manages user sessions, and handles all business logic.
*   **Synchronization Scripts (`endpoints.py`)**: A Python module that calls external scripts to perform data synchronization. These scripts are a mix of PHP (for WISEflow integration) and Python (for Moodle integration).
*   **Database**: The application relies heavily on a MySQL database (referred to as `BDInt` or `wiseflow`) which serves as an integration layer. It stores data from WISEflow, Moodle, and configuration for the `wiser.Py` app itself (users, logs, etc.). It also connects to another database (`plataformaberta`) for Moodle-related data.
*   **External APIs**:
    *   **WISEflow API**: Used for managing flows, participants, and biometric data. Requires OAuth2 authentication.
    *   **AIDA (Moodle) Web Service**: A custom web service on the Moodle platform to fetch specific academic data.
*   **Templates**: Standard Jinja2 templates are used to render the HTML pages.

### Technical Stack
*   **Backend**: Python 3, Flask
*   **Frontend**: HTML, CSS, JavaScript
*   **Database**: MySQL (via `mysql-connector-python`)
*   **Web Server**: Can be run with Waitress for production or the Flask development server.
*   **Dependencies**: See `requirements.txt` for a full list of Python packages. Key libraries include `Flask`, `Flask-Session`, `requests`, `plotly`, `mysql-connector-python`, `reportlab`, `pypdf`, `waitress` and `wfastcgi`.

### Data Flow
The application orchestrates complex data flows:
1.  **WISEflow -> BDInt**: PHP scripts (`sync_stdts.php`, `sync_flows.php`, etc.) are executed to pull data from the WISEflow API and store it in the central MySQL database.
2.  **Moodle -> BDInt**: Python scripts (`sync_subs.py`, `sync_grades.py`) pull data from the `plataformaberta` database and potentially Moodle web services into the central database.
3.  **BDInt -> SiGES**: As suggested by related project documentation (`_DBsync_`), there is an implicit data flow from the integration database to the university's main academic records system (SiGES), which `wiser.Py` helps manage (e.g., via the "Reset Sync" feature).
4.  **User -> `wiser.Py` -> APIs**: Users interact with the Flask application, which in turn makes real-time calls to the WISEflow and AIDA APIs for management tasks.

## 3. Setup and Configuration

### 3.1. Prerequisites
*   Python 3.9+
*   MySQL Server
*   PHP environment with access to the required databases (for WISEflow sync scripts).
*   Access credentials for all databases and external APIs.

### 3.2. Configuration
The main configuration is parsed from a PHP file located at `wiseflow/auth_lib_bdint.php`. This file must contain the connection details for the databases and API credentials. A template for this file should be created and populated with the correct values.

**`wiseflow/auth_lib_bdint.php` example:**
```php
<?php
// Database (BDInt)
$host = 'your_db_host';
$port = 'your_db_port';
$usr  = 'your_db_user';
$pwd  = 'your_db_password';
$db   = 'your_db';

// WISEflow API
$base_url      = 'your_wiseflow_base_url';
$client_id     = 'your_wiseflow_client_id';
$client_secret = 'your_wiseflow_client_secret';
$grant_type    = 'your_wiseflow_grant_type';

// Moodle Web Service
$mdl_wsURL  = 'your_moodle_webservice_url';
$aida_token = 'your_moodle_webservice_token';
$aida_base  = 'your_moodle_webservice_endpoints_prefix';
?>
```

### 3.3. Initial Database Setup
    The application includes a first-time setup routine. When you first run the application and navigate to the root URL, you will be redirected to `/setup`. This process will:
    *   Create the necessary `users` table in the database.
    *   Create a default `admin` user with a default password (`12345678`).

    The admin user will be required to change this password upon their first login.

### 3.4. Running the Application

*   **For Development:**
    You can use the built-in Flask development server.
    ```bash
    flask --app wiser.Py run --host=0.0.0.0 --port=5000
    ```

*   **For Production:**
    The application is configured to be served with `waitress`.
    ```bash
    waitress-serve --host=0.0.0.0 --port=5000 wiser:app
    ```
    It is recommended to run this behind a reverse proxy like Nginx or Apache.

## 4. User Roles and Permissions

Access to features is restricted based on user roles, which can be managed by an administrator.

*   **`admin`**: Full access to all features, including user management and application logs.
*   **`flowman`**: Can manage flows and related data, including access to some SOS and Tools features.
*   **`bioman`**: Can view and manage student biometric data.
*   **`statsman`**: Can access the statistical dashboards.
*   **`toolsusr`**: Access to general utilities like the calendar uploader and WISEflow file checker.
*   **`sosusr`**: Access to emergency tools in the "SOS" module, like extending flow times and resetting grade syncs.

## 5. Licenses

**Author**: Bruno Tavares  
**Contact**: [brunustavares@gmail.com](mailto:brunustavares@gmail.com)  
**LinkedIn**: [https://www.linkedin.com/in/brunomastavares/](https://www.linkedin.com/in/brunomastavares/)  
**Copyright**: 2024-present Bruno Tavares  
**License**: GNU GPL v3 or later  

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

### Assets

- **Source code**: GNU GPL v3 or later (© Bruno Tavares)  
- **Images**: created using [Image Creator from ©Microsoft Designer](https://www.bing.com/images/create?FORM=IRPGEN) and [Ideogram](https://ideogram.ai/t/explore), except Universidade Aberta's logo, with all rights reserved and usage subject to the institution's policy.
