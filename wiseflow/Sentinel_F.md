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
- **Daily Summaries**: Generates and emails a daily summary report of all relevant events detected.
- **Database Maintenance**: Automatically purges event data older than one year to keep the database size manageable.

## Dependencies

- **`auth_lib_bdint.php`**: This file is crucial as it contains all the necessary configurations for:
    - Database connections (`connect2bdint`).
    - WISEflow API credentials and token management functions (`getwftoken`, `encrypt_token`, `decrypt_token`).
    - PHPMailer library and email server (SMTP) settings.
- **PHP**: A PHP environment with the `mysqli` and `curl` extensions enabled.
- **Database**: A MySQL or MariaDB database with the `wiseflow` schema as referenced in the queries.

## Database Schema

The script relies on several tables within the `wiseflow` database schema:

- `sentinelf_settings`: Stores configuration values for the script's operation, such as administrator email, management email lists (`manageTO`, `manageCC`), and the timestamp of the last execution (`lastrun`).
- `flows`: Contains information about the WISEflow flows, including their IDs, start times (`dtfrom`), and end times (`dtto`).
- `sentinelf_events`: The main table where all fetched student participation events are logged. It includes the flow ID, student ID, timestamp, event type, and the full JSON payload of the event.
- `sentinelf_event_types`: Acts as a catalogue for event types. It determines whether an event type should be included in management reports (`report` column).
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
2.  **Fetch Running Flows**: It queries the `wiseflow.flows` table to get a list of flows that are currently active or have ended within the last 30 minutes.
3.  **Fetch Events**: For each running flow, it makes a POST request to the `/participation-events` WISEflow API endpoint.
    - It uses the `lastrun` timestamp from the settings to fetch only the events that have occurred since the script was last executed.
    - The API response is paginated, and the script iterates through all pages to retrieve all events within the time window.
4.  **Store Events**: Each event is inserted as a new row into the `wiseflow.sentinelf_events` table. The query uses `INSERT IGNORE` to prevent duplicates.
5.  **Detect and Notify New Event Types**:
    - The script queries for events that have a `type` not present in `wiseflow.sentinelf_event_types`.
    - If new types are found, they are automatically inserted into `sentinelf_event_types` with the `report` flag set to `1` (defaulting to being reportable).
    - An email is sent to the administrator (defined by the `admin` setting) containing a table of these new events for review.
6.  **Detect and Notify Relevant Events**:
    - It queries for events that are marked as reportable (`evt_tp.report = 1`), match a specific `payload` stored in `sentinelf_event_types`, and have not been reported yet (`evts.report IS NULL`).
    - **Typing Speed Analysis**: It also analyzes `CHARACTERS_TYPED` events to detect anomalous spikes in typing speed. It calculates the characters per second between consecutive events for a student and compares it against a threshold defined in `sentinelf_event_types`.
    - **Inactivity Detection**: The script identifies prolonged periods of student inactivity. It calculates the time difference between the current execution and the student's last event. If this period exceeds a configurable threshold (defined in `sentinelf_event_types` for the `INACTIVITY` event type), an alert is generated.
    - If any of these relevant events are found, it constructs a single HTML email with a table of all such events and sends it to the management mailing lists (`manageTO` and `manageCC`).
    - It then updates the `report` column for the sent events to prevent them from being reported again.
7.  **Update Last Run Time**: After processing all flows, it updates the `lastrun` setting with the current timestamp.

At the end of the `monitor` mode execution, if there are no active flows, it cleans up any temporary tables used for analysis.

### `report` Mode

This mode is intended to be run once a day to generate a summary of the day's relevant activities.

**Command:**
```bash
php sentinel_f.php -m report
```

**Workflow:**

1.  **Fetch Daily Reported Events**: The script queries the `wiseflow.sentinelf_events` table for all events that were marked for reporting on the current date.
2.  **Generate Summary**: It groups the events by `type` and counts the occurrences of each.
3.  **Send Report**: If any events were found, it constructs an HTML email with a summary table (Event Type and Count) and sends it to the report recipients defined in the `reportTO` setting.
4.  **Purge Old Data**: Finally, it deletes all records from `sentinelf_events` that are older than 365 days.
 
## How It Operates

The script acts as a bridge between the WISEflow API and a local database, adding a layer of business logic for monitoring and alerting.

- **Stateless Authentication**: The API token is stored on the filesystem (`auth.tkn`), allowing different script executions to share the same session, minimizing the number of authentication requests. The token is encrypted for security.

- **Incremental Fetching**: By storing a `lastrun` timestamp, the script avoids re-processing the entire event history for a flow on every run. This makes the monitoring process efficient and scalable.

- **Dynamic Event Handling**: The system is designed to adapt to new event types introduced by WISEflow. Instead of failing, it catalogues them and alerts an administrator, ensuring the system can be updated without code changes.

- **Separation of Concerns**:
    - **`monitor` mode** is for immediate operational awareness. It answers the question: "What is happening *right now* that I need to know about?"
    - **`report` mode** is for managerial oversight. It answers the question: "What happened *today* that was noteworthy?"

- **Configuration-driven**: The recipients of alerts and reports are managed via the `sentinelf_settings` table, allowing for easy updates without modifying the script's code.

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
