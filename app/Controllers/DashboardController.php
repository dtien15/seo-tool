<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Project;
use App\Usage;

class DashboardController
{
    private const SELECT = "SELECT a.*, p.name AS project_name FROM articles a JOIN projects p ON p.id = a.project_id";

    public function index(): void
    {
        $user = Auth::requireLogin();
        $uid = (int)$user['id'];
        $projects = Project::forUser($user);
        $ids = array_map(fn($p) => (int)$p['id'], $projects) ?: [0];
        $in = implode(',', $ids);
        $role = $user['role'];
        $sections = [];

        if ($role === 'content' || $role === 'admin') {
            $sections[] = ['Bài cần viết / sửa', 'bi-pencil', db()->fetchAll(
                self::SELECT . " WHERE a.project_id IN ($in) AND a.status IN ('writing','revise') AND (a.writer_id = ? OR ? = 'admin') ORDER BY a.status = 'revise' DESC, a.planned_date IS NULL, a.planned_date LIMIT 30",
                [$uid, $role]
            )];
        }
        if ($role === 'design' || $role === 'admin') {
            $sections[] = ['Bài cần làm hình', 'bi-palette', db()->fetchAll(
                self::SELECT . " WHERE a.project_id IN ($in) AND a.status IN ('design','image_revise') AND (a.designer_id = ? OR ? = 'admin') ORDER BY a.status = 'image_revise' DESC, a.planned_date IS NULL, a.planned_date LIMIT 30",
                [$uid, $role]
            )];
        }
        if (in_array($role, ['leader', 'admin'], true)) {
            $sections[] = ['Chờ Trưởng phòng duyệt', 'bi-check2-square', $this->waitingApproval($in, 'leader', ['outline_review', 'content_review', 'image_review'])];
        }
        if (in_array($role, ['seo', 'admin'], true)) {
            $sections[] = ['Chờ SEO duyệt', 'bi-check2-square', $this->waitingApproval($in, 'seo', ['content_review', 'image_review'], $role === 'seo' ? $uid : null)];
            $sections[] = ['Việc SEO: outline & đăng bài', 'bi-person-gear', db()->fetchAll(
                self::SELECT . " WHERE a.project_id IN ($in) AND a.status IN ('plan','outline','ready') AND (a.assigned_to = ? OR ? = 'admin')
                 ORDER BY FIELD(a.status, 'ready', 'outline', 'plan'), a.planned_date IS NULL, a.planned_date LIMIT 30",
                [$uid, $role]
            )];
        }
        if (in_array($role, ['seo', 'leader', 'admin'], true)) {
            $due = [];
            foreach ($projects as $p) {
                foreach (db()->fetchAll(self::SELECT . ' WHERE ' . \App\Workflow::dueForReview((int)$p['id'], (int)$p['reoptimize_days']) . ' LIMIT 10') as $a) {
                    $due[] = $a;
                }
            }
            $sections[] = ['Bài cũ đến hạn tối ưu lại', 'bi-arrow-repeat', array_slice($due, 0, 30)];
        }

        $statusCounts = [];
        [$scope, $scopeParams] = Project::articleScope($user);
        foreach (db()->fetchAll("SELECT status, COUNT(*) c FROM articles a WHERE a.project_id IN ($in) AND $scope GROUP BY status", $scopeParams) as $r) {
            $statusCounts[$r['status']] = (int)$r['c'];
        }
        $publishedThisMonth = (int)db()->value(
            "SELECT COUNT(*) FROM articles WHERE project_id IN ($in) AND status = 'published' AND published_at >= ?",
            [date('Y-m-01')]
        );

        view('dashboard', [
            'pageTitle' => 'Tổng quan',
            'projects' => $projects,
            'sections' => $sections,
            'statusCounts' => $statusCounts,
            'publishedThisMonth' => $publishedThisMonth,
            'spent' => Usage::monthSpent($uid),
            'budget' => Usage::budgetFor($user),
        ]);
    }

    /** Bài đang chờ một phía (SEO / TP) duyệt mà phía đó chưa duyệt. */
    private function waitingApproval(string $in, string $side, array $statuses, ?int $seoUserId = null): array
    {
        $st = implode(',', array_map(fn($s) => "'$s'", $statuses));
        $sql = self::SELECT . " WHERE a.project_id IN ($in) AND a.status IN ($st)
            AND NOT EXISTS (SELECT 1 FROM article_approvals ap WHERE ap.article_id = a.id AND ap.side = ?
                AND ap.stage = CASE a.status WHEN 'outline_review' THEN 'outline' WHEN 'content_review' THEN 'content' ELSE 'image' END)";
        $params = [$side];
        if ($seoUserId) {
            $sql .= ' AND a.assigned_to = ?';
            $params[] = $seoUserId;
        }
        return db()->fetchAll($sql . ' ORDER BY a.submitted_at LIMIT 30', $params);
    }
}
