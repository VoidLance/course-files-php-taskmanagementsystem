# Task Management System

A lightweight PHP task and project manager with a Vue-powered Kanban board. Organize work into projects, track task status and due dates, collaborate with comments, and monitor progress from a dashboard.

## Features

- Register and log in with email verification and password reset flows
- Create and manage projects, invite team members, and assign project roles
- Organize tasks on a drag-and-drop board, with priorities, statuses, due dates, labels, and time tracking
- Add task comments and view task activity
- Search and filter tasks, review dashboard statistics, and export overdue tasks as CSV
- Use a responsive interface backed by a PHP JSON API

This is a starter project, not a production-ready hosted service. Verification and password-reset messages are written to `storage/logs/mail.log` rather than sent by email. The frontend loads Vue, SortableJS, and Chart.js from CDNs.

## Requirements

- PHP 8.0 or later with PDO and the PDO MySQL driver enabled
- MySQL or MariaDB
- A modern browser with internet access to load the frontend libraries from their CDNs

No Composer packages or automated test commands are configured in this repository.

## Get started

1. Clone the repository and enter its directory:

   ```bash
   git clone https://github.com/VoidLance/course-files-php-taskmanagementsystem.git
   cd course-files-php-taskmanagementsystem
   ```

2. Create a database and import the schema:

   ```bash
   mysql -u YOUR_DB_USER -p -e "CREATE DATABASE task_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u YOUR_DB_USER -p task_management < database/schema.sql
   ```

   The schema creates tables with the `tms_` prefix.

3. Edit [`config/app.php`](config/app.php) with your database name, user, password, and host. Change the JWT secret to a unique value. The checked-in `.env.example` is a reference only; the application currently reads settings from `config/app.php`, not from environment variables.

4. Ensure the PHP process can write to `storage/logs/`, then start the local server from the repository root:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

5. Open [http://127.0.0.1:8000/](http://127.0.0.1:8000/). Register a user with the `project_manager` role, then copy the verification token from `storage/logs/mail.log` and enter it in the Verify Email form. After verifying and logging in, create a project and add tasks to its board.

## Project structure

| Path | Purpose |
| --- | --- |
| [`app/Controllers/`](app/Controllers/) | API request handlers |
| [`app/Models/`](app/Models/) | Database access |
| [`app/Services/`](app/Services/) | JWT, local mail logging, and activity logging |
| [`app/Core/`](app/Core/) | Routing and database connection |
| [`public/`](public/) | Browser interface, assets, and API entry point |
| [`database/schema.sql`](database/schema.sql) | MySQL/MariaDB table definitions |
| [`config/app.php`](config/app.php) | Application and database settings |

For API routes and request handling, see [`public/api.php`](public/api.php).

## Help and contributions

For questions or bug reports, [open an issue](https://github.com/VoidLance/course-files-php-taskmanagementsystem/issues). Contributions are welcome: please discuss larger changes in an issue first, then submit a focused pull request with a description of what you tested. There is no separate `CONTRIBUTING.md` at this time.

The repository is maintained by [@VoidLance](https://github.com/VoidLance). No `LICENSE` file is currently included; check with the maintainer before redistributing or reusing the project.
