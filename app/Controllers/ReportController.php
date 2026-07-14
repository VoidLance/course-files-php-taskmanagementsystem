<?php

declare(strict_types=1);

namespace App\Controllers;

class ReportController extends BaseController
{
    public function dashboard(): void
    {
        // Aggregate dashboard metrics + completion trend for charting.
        $user = $this->requireUser();

        $stats = [
            'projects' => 0,
            'tasks_open' => 0,
            'tasks_overdue' => 0,
            'tasks_done' => 0,
        ];

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT pm.project_id) AS total
               FROM tms_project_members pm
             WHERE pm.user_id = :user_id'
        );
        $stmt->execute(['user_id' => (int) $user['sub']]);
        $stats['projects'] = (int) ($stmt->fetch()['total'] ?? 0);

        // Count open, overdue, and done tasks in one query.
        $taskStmt = $this->db->prepare(
            'SELECT
                SUM(CASE WHEN t.status != "completed" THEN 1 ELSE 0 END) AS tasks_open,
                SUM(CASE WHEN t.status != "completed" AND t.due_date IS NOT NULL AND t.due_date < NOW() THEN 1 ELSE 0 END) AS tasks_overdue,
                SUM(CASE WHEN t.status = "completed" THEN 1 ELSE 0 END) AS tasks_done
               FROM tms_tasks t
               JOIN tms_project_members pm ON pm.project_id = t.project_id
             WHERE pm.user_id = :user_id'
        );
        $taskStmt->execute(['user_id' => (int) $user['sub']]);
        $row = $taskStmt->fetch() ?: [];

        $stats['tasks_open'] = (int) ($row['tasks_open'] ?? 0);
        $stats['tasks_overdue'] = (int) ($row['tasks_overdue'] ?? 0);
        $stats['tasks_done'] = (int) ($row['tasks_done'] ?? 0);

        // Trend line: completed tasks grouped by day.
        $trendStmt = $this->db->prepare(
            'SELECT DATE(updated_at) AS day, COUNT(*) AS completed
             FROM tms_tasks t
             JOIN tms_project_members pm ON pm.project_id = t.project_id
             WHERE pm.user_id = :user_id AND t.status = "completed"
             GROUP BY DATE(updated_at)
             ORDER BY DATE(updated_at) ASC
             LIMIT 30'
        );
        $trendStmt->execute(['user_id' => (int) $user['sub']]);

        $this->json([
            'stats' => $stats,
            'completion_trend' => $trendStmt->fetchAll(),
        ]);
    }

    public function overdueCsv(): void
    {
        // Export overdue tasks as CSV for spreadsheet-loving humans.
        $user = $this->requireUser();

        $stmt = $this->db->prepare(
            'SELECT t.id, t.title, t.priority, t.due_date, p.name AS project_name
                         FROM tms_tasks t
                         JOIN tms_projects p ON p.id = t.project_id
                         JOIN tms_project_members pm ON pm.project_id = t.project_id
             WHERE pm.user_id = :user_id
               AND t.status != "completed"
               AND t.due_date IS NOT NULL
               AND t.due_date < NOW()
             ORDER BY t.due_date ASC'
        );

        $stmt->execute(['user_id' => (int) $user['sub']]);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="overdue_tasks.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['ID', 'Title', 'Priority', 'Due Date', 'Project']);

        foreach ($rows as $row) {
            fputcsv($out, [$row['id'], $row['title'], $row['priority'], $row['due_date'], $row['project_name']]);
        }

        fclose($out);
    }
}
