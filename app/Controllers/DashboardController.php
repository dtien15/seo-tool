<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Project;
use App\Usage;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::requireLogin();
        $projects = Project::forUser($user);
        $ids = array_map(fn($p) => (int)$p['id'], $projects) ?: [0];
        $in = implode(',', $ids);

        $statusCounts = [];
        foreach (db()->fetchAll("SELECT status, COUNT(*) c FROM articles WHERE project_id IN ($in) GROUP BY status") as $r) {
            $statusCounts[$r['status']] = (int)$r['c'];
        }
        $myArticles = db()->fetchAll(
            "SELECT a.*, p.name AS project_name FROM articles a JOIN projects p ON p.id = a.project_id
             WHERE a.project_id IN ($in) AND (a.assigned_to = ? OR ? = 1) AND a.status NOT IN ('published')
             ORDER BY a.updated_at DESC LIMIT 12",
            [$user['id'], Auth::isAdmin() ? 1 : 0]
        );
        $publishedThisMonth = (int)db()->value(
            "SELECT COUNT(*) FROM articles WHERE project_id IN ($in) AND status = 'published' AND published_at >= ?",
            [date('Y-m-01')]
        );

        view('dashboard', [
            'pageTitle' => 'Tổng quan',
            'projects' => $projects,
            'statusCounts' => $statusCounts,
            'myArticles' => $myArticles,
            'publishedThisMonth' => $publishedThisMonth,
            'spent' => Usage::monthSpent((int)$user['id']),
            'budget' => Usage::budgetFor($user),
        ]);
    }
}
