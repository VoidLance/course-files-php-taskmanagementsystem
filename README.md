# Task Management System (Project 3)

A beginner-friendly MVC + REST task manager in PHP, inspired by Trello/Asana-lite.

## Included Features

- User registration with email verification token flow
- Login with JWT authentication
- Password reset with token flow
- Profile endpoint (name/timezone/avatar URL)
- Roles: `admin`, `project_manager`, `team_member`
- Project CRUD basics (create, update, archive)
- Invite users into projects with project role
- Task CRUD + move between columns (drag and drop)
- Task statuses, priorities, due dates, labels, estimate/tracked minutes
- Comments + `@mentions` parsing
- Task activity log
- Search/filter tasks
- Dashboard stats + completion chart (Chart.js)
- CSV export for overdue tasks
- Responsive UI using Vue 3 + SortableJS

## What Is Stubbed / Simplified

- Email is simulated by writing tokens to `storage/logs/mail.log`
- Attachments, WebSocket real-time, external calendar sync, PDF export, offline sync are planned but not fully implemented in this starter
- This starter uses a hardcoded config array. If you want `.env` loading, add a config loader as a follow-up

## Folder Structure

- `app/Controllers`: API controllers
- `app/Models`: DB data access
- `app/Services`: JWT, mail logging, activity helper
- `app/Core`: Router + DB connection
- `public/index.php`: Vue frontend shell
- `public/api.php`: API gateway + routes
- `database/schema.sql`: MySQL schema

## Setup

1. Create tables in the existing `ecommerce` database (prefixed with `tms_`):

```bash
mariadb -h localhost -u ecom_user -p'EcomPass2024' -D ecommerce < database/schema.sql
```

2. Adjust DB credentials in `config/app.php` if your local setup differs.

3. Run local PHP server from workspace root:

```bash
php -S localhost:3000 -t .
```

4. Open:

- UI: `http://localhost:3000/TaskManagementSystem/public/index.php`
- API base: `http://localhost:3000/TaskManagementSystem/public/api.php/api/v1`

## Test Flow

1. Register a user.
2. Open `storage/logs/mail.log` and copy verification token.
3. Verify email.
4. Login.
5. Create project.
6. Create/move tasks.
7. Open task details and add comments.
8. Search tasks and view dashboard.

## Security Notes

- Passwords are hashed with `password_hash`.
- SQL uses prepared statements.
- JWT signature uses HMAC SHA-256.
- Basic secure headers are returned on JSON responses.

## Beginner Notes

Code intentionally favors readability over advanced abstraction.
"Enterprise architecture" can wait until we survive week 2.
