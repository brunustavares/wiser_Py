<p align="center">
  <img src="../static/img/Sentinel_F.jpg" alt="Sentinel_F Logo" width="300">
</p>

# Sentinel_F

`Sentinel_F` is a PHP script designed to monitor student behavior within the WISEflow platform. It operates by fetching participation events from the WISEflow API for ongoing exams (flows), storing them, and then analyzing them to detect anomalies or specific occurrences that require administrative or managerial attention.

The script can be executed in two primary modes: `monitor` for real-time monitoring and `report` for generating daily summaries.

It was originally developed for [Universidade Aberta (UAb)](https://portal.uab.pt/).

## Features

- **Real-time Event Monitoring**: Fetches and logs student interaction events from active WISEflow flows.
- **Authentication Management**: Automatically handles API token acquisition and renewal.
- **Dual Execution Mode**: Can be run via Command Line Interface (CLI) for scheduled tasks or through a web browser.
- **New Event Discovery**: Automatically detects and catalogues new, previously unseen event types from the API.
- **Administrative Alerts**: Sends email notifications to administrators when new event types are discovered, allowing for their review and classification.
- **Relevant Event Reporting**: Identifies and reports on specific, pre-defined "relevant" events to management, providing timely alerts on critical student activities.
- **Typing Speed Anomaly Detection**: Monitors `CHARACTERS_TYPED` events to identify sudden, unusual increases in typing speed, which could indicate suspicious activity.
- **Prolonged Inactivity Detection**: Detects and reports instances where students exhibit prolonged periods of inactivity during a flow, based on event timestamps, excluding students who have already submitted their work.
- **LMS Access Verification**: Cross-references student activity with Moodle logs to detect if students accessed course materials during an exam.
- **End-of-Session Summaries**: When the last active exam for the day concludes, a summary of all events reported during that monitoring period is automatically sent to management.
- **Daily Summaries & No-Show Reporting**: Generates and emails a daily summary report that includes a count of all relevant events detected and a list of any scheduled exams that had no student participation.
- **JSON Payload Normalization**: Includes a function to validate and standardize JSON payloads before database insertion, ensuring data consistency.
- **Database Maintenance**: Automatically purges event data older than one year to keep the database size manageable.

## Dependencies

- **`auth_lib_bdint.php`**: This file is crucial as it contains all the necessary configurations for:
  - Database connections (`connect2bdint`).
  - WISEflow API credentials and token management functions (`getwftoken`, `encrypt_token`, `decrypt_token`).
  - PHPMailer library and email server (SMTP) settings, including the sender email (`Sentinel_F@uab.pt`).
- **PHP**: A PHP environment with the `mysqli` and `curl` extensions enabled.
- **Database**: A MySQL or MariaDB database with the `wiseflow` schema as referenced in the queries. The script also creates and uses a temporary table `sentinelf_tmp` for efficient analysis during `monitor` mode execution.
  - Ensure the `wiseflow` database and its tables (`sentinelf_settings`, `flows`, `sentinelf_events`, `sentinelf_event_types`, `students`) exist and are accessible.

## Database Schema

The script relies on several tables within the `wiseflow` database schema:

- `sentinelf_settings`: Stores configuration values for the script's operation, such as administrator email, management email lists (`manageTO`, `manageCC`), and the timestamp of the last execution (`lastrun`).
- `flows`: Contains information about the WISEflow flows, including their IDs, start times (`dtfrom`), and end times (`dtto`).
- `sentinelf_events`: The main table where all fetched student participation events are logged. It includes the flow ID, student ID, timestamp, event type, the full JSON payload of the event, and a `report` column to track if an event has been reported.
- `sentinelf_event_types`: Acts as a catalogue for event types. It determines whether an event type should be included in management reports (`report` column) and whether it counts as a critical event (`red_flag` column) to trigger teacher alerts. This table is also used to store reference values for composite event detection, such as inactivity thresholds.
- `sentinelf_reported`: Stores a history of events that have been reported to management, used to generate end-of-session synthesis reports.
- `students`: A table mapping WISEflow student IDs (`stdid`) to their student numbers (`std_num`).

## Execution

The script is designed to be run from the command line, using a `-m` or `--mode` flag to specify the operation mode.

### `monitor` Mode

This is the primary monitoring mode. It should be run frequently (e.g., every 5-15 minutes) via a cron job or scheduled task while exams are active.

**Command:**

```bash
php sentinel_f.php -m monitor
```

**Workflow:**

1.  **Authentication**: It checks for a valid API token in `./auth.tkn`. If the token is missing or expiring within 3 minutes, it requests a new one using functions from `auth_lib_bdint.php`.
2.  **Fetch Running Flows**: It queries the `wiseflow.flows` table to get a list of flows that are currently active or have ended within the last 45 minutes.
3.  **Fetch Events**: For each running flow, it makes a POST request to the `/participation-events` WISEflow API endpoint.
    - It uses the `lastrun` timestamp from the settings to fetch only the events that have occurred since the script was last executed.
    - The API response is paginated, and the script iterates through all pages to retrieve all events within the time window.
4.  **Store Events**: Each event is inserted as a new row into the `wiseflow.sentinelf_events` table. The query uses `INSERT IGNORE` to prevent duplicates.
5.  **Analyze Recent Events**: It creates a temporary table, `sentinelf_tmp`, and populates it with events from the last 30 minutes that have not yet been reported. This improves the performance of subsequent analysis queries.
6.  **Detect and Notify New Event Types**:
    - The script queries `sentinelf_tmp` for events that have a `type` not present in `wiseflow.sentinelf_event_types`.
    - If new types are found, they are automatically inserted into `sentinelf_event_types` with the `report` flag set to `1` (defaulting to being reportable), ensuring they are considered for future alerts.
    - An email is sent to the administrator (defined by the `admin` setting) containing a table of these new events for review.
7.  **Detect and Notify Relevant Events**:
    - **Elementary Events**: It queries `sentinelf_tmp` for simple events that are marked as reportable (`evt_tp.report = 1`) and have not been reported yet (`evts.report IS NULL`).
    - **Typing Speed Analysis**: It also analyzes `CHARACTERS_TYPED` events to detect anomalous spikes in typing speed. It calculates the characters per second between consecutive events for a student and compares it against a threshold defined in `sentinelf_event_types`.
    - **Inactivity Detection**: The script identifies prolonged periods of student inactivity. It calculates the time difference between the current execution and the student's last event. If this period exceeds a configurable threshold (defined in `sentinelf_event_types` for the `INACTIVITY` event type), an alert is generated. This check excludes students who have already handed in their paper.
    - **LMS Access Check**: If enabled in the configuration (`PLATAFORMABERTA_ACCESS`), the script queries the Moodle web service to check if any of the monitored students accessed course pages during the exam window. These events are merged with the WISEflow events.
    - If any of these relevant events are found, it constructs a single HTML email with a table of all such events and sends it to the management mailing lists (`manageTO` and `manageCC`).
    - It then updates the `report` column for the sent events to prevent them from being reported again.
8.  **Update Last Run Time**: After processing all flows, it updates the `lastrun` setting with the current timestamp.
9.  **End-of-Session Summary**: If no flows are currently running, the script sends a summary of all events reported during the day's monitoring session, and then removes the temporary analysis table.

### `report` Mode

This mode is intended to be run once a day (typically at the end of the day) to generate daily summary reports and notify both teachers and managers of critical events.

**Command:**

```bash
php sentinel_f.php -m report
```

**Workflow:**

1.  **Teacher Notifications (Red Flags)**:
    - The script identifies critical events (marked as `red_flag = 1` in `sentinelf_event_types`) that occurred today.
    - It maps these events to the respective course teachers (using the `vw_teacher_2wiseflow` view) and student class enrollments (`lead.alunos_inscricoes`).
    - For each teacher, it generates and sends a personalized email report detailing the academic flow, student number, class, event description, and total occurrences ($N$).
    - Management emails (`manageTO` and `manageCC`) are copied (CC) on these messages.
    - Each email includes an explanatory disclaimer stating that `Sentinel_F` is an automated support tool for human supervision and does not prove academic fraud on its own.
2.  **Management Daily Summary Report**:
    - The script fetches the daily count of all reported events grouped by type.
    - It identifies scheduled exams (flows) that concluded today but had zero student participation (`SUM(dtass IS NOT NULL) = 0`).
    - If there are events or empty flows, it constructs a summary email and sends it to the management recipients defined in the `reportTO` setting.
3.  **Database Purge**:
    - Finally, the script deletes obsolete event records from `sentinelf_events` that are older than 365 days to maintain database performance.

## How It Operates

The script acts as a bridge between the WISEflow API and a local database, adding a layer of business logic for monitoring and alerting.

- **Stateless Authentication**: The API token is stored on the filesystem (`auth.tkn`), allowing different script executions to share the same session, minimizing the number of authentication requests. The token is encrypted for security.

- **Dynamic Event Analysis**: The script includes specific SQL logic to detect complex patterns like sudden typing speed increases and prolonged inactivity, which are crucial for monitoring student engagement and potential issues. It uses a temporary table for efficient analysis of recent events.

- **Incremental Fetching**: By storing a `lastrun` timestamp, the script avoids re-processing the entire event history for a flow on every run. This makes the monitoring process efficient and scalable.

- **Dynamic Event Handling**: The system is designed to adapt to new event types introduced by WISEflow. Instead of failing, it catalogues them and alerts an administrator, ensuring the system can be updated without code changes.

- **Separation of Concerns**:
  - **`monitor` mode** is for immediate operational awareness. It answers the question: "What is happening _right now_ that I need to know about?"
  - **`report` mode** is for managerial oversight. It answers the question: "What happened _today_ that was noteworthy?"

- **Configuration-driven**: The recipients of alerts and reports are managed via the `sentinelf_settings` table, allowing for easy updates without modifying the script's code.

## Helper Functions

The agent defines several helper functions to manage its operations, API calls, and email formatting:

- **`Is_cli()`**:
  - **Description**: Determines whether the script is running via the Command Line Interface (CLI) or a web browser.
  - **Returns**: `boolean` (`true` if CLI, `false` otherwise).
- **`set_curl_params($sys, $start_time = null)`**:
  - **Parameters**:
    - `$sys` (string): The target system (`"wf"` for WISEflow, `"mdl"` for Moodle/PlataformAbERTA).
    - `$start_time` (int, optional): The script execution start timestamp, passed to token validation.
  - **Description**: Configures baseline cURL options (headers, timeouts, SSL settings) appropriate for the targeted API. For WISEflow, it automatically appends the authorization token using `checkwftoken()`.
  - **Returns**: `array` (an array of cURL options).
- **`checkwftoken($start_time)`**:
  - **Parameters**:
    - `$start_time` (int): The current execution start timestamp.
  - **Description**: Manages and validates the WISEflow API access token. It reads the cached token from `./auth.tkn` and decrypts it. If the token is missing or expiring within 3 minutes of `$start_time`, it requests a new token using `getwftoken()`, encrypts it, caches it to the file, and returns the authorization header.
  - **Returns**: `string` (the authorization header, e.g., `"Bearer <token>"`).
- **`normalizeJsonString($payload)`**:
  - **Parameters**:
    - `$payload` (string): The raw JSON payload to sanitize.
  - **Description**: Validates and standardizes a JSON payload before database insertion. It trims whitespace, strips outer quotes, and decodes/re-encodes the string to ensure a valid structure, escaping backslashes and adding slashes for database compatibility.
  - **Returns**: `string` (the normalized and escaped JSON payload enclosed in quotes).
- **`build_email_wrapper($content_html)`**:
  - **Parameters**:
    - `$content_html` (string): The HTML table or content to include in the email body.
  - **Description**: Wraps the email body in a highly compatible HTML layout. It injects premium dark-themed inline styles, embeds the `Sentinel_F` avatar image, and includes fallback VML structures (Vector Markup Language) for Microsoft Outlook desktop client compatibility.
  - **Returns**: `string` (the complete HTML email body wrapper).
- **`get_email_table_styles()`**:
  - **Description**: Returns an associative array of inline CSS style strings. These styles are applied directly to HTML table elements (`table`, `th`, `td`, links, rows, payloads) to maintain a consistent dark-theme appearance across various email clients.
  - **Returns**: `array<string, string>` (associative array of element-to-CSS-style mappings).

## Licenses

**Author**: Bruno Tavares  
**Contact**: [brunustavares@gmail.com](mailto:brunustavares@gmail.com)  
**LinkedIn**: [https://www.linkedin.com/in/brunomastavares/](https://www.linkedin.com/in/brunomastavares/)  
**Copyright**: 2025-present Bruno Tavares  
**License**: GNU GPL v3 or later

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

### Assets

- **Source code**: GNU GPL v3 or later (© Bruno Tavares)
- **Images**: created using [Image Creator from ©Microsoft Designer](https://www.bing.com/images/create?FORM=IRPGEN) and [Ideogram](https://ideogram.ai/t/explore)
