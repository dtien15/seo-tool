<?php
declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Crypto;
use App\Services\WordPressService;

class Project
{
    public static function find(int $id): ?array
    {
        return db()->fetch('SELECT * FROM projects WHERE id = ?', [$id]);
    }

    public static function canAccess(array $project, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin' || (int)$project['owner_id'] === (int)$user['id']) {
            return true;
        }
        return (bool)db()->value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$project['id'], $user['id']]);
    }

    /** Lấy dự án và kiểm tra quyền, nếu không có thì 404/403. */
    public static function findOrFail(int $id): array
    {
        $project = self::find($id);
        if (!$project) {
            abort(404, 'Dự án không tồn tại.');
        }
        if (!self::canAccess($project)) {
            abort(403, 'Bạn không có quyền với dự án này.');
        }
        return $project;
    }

    public static function forUser(array $user): array
    {
        $sql = 'SELECT p.*, u.name AS owner_name,
                  (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id) AS article_count,
                  (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id AND a.status = \'published\') AS published_count,
                  (SELECT COUNT(*) FROM articles a WHERE a.project_id = p.id AND a.status = \'review\') AS review_count,
                  (SELECT COUNT(*) FROM internal_links l WHERE l.project_id = p.id) AS link_count
                FROM projects p JOIN users u ON u.id = p.owner_id';
        if ($user['role'] === 'admin') {
            return db()->fetchAll($sql . ' ORDER BY p.updated_at DESC');
        }
        return db()->fetchAll(
            $sql . ' WHERE p.owner_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) ORDER BY p.updated_at DESC',
            [$user['id'], $user['id']]
        );
    }

    public static function members(int $projectId): array
    {
        return db()->fetchAll(
            'SELECT u.id, u.name, u.email FROM project_members m JOIN users u ON u.id = m.user_id WHERE m.project_id = ? ORDER BY u.name',
            [$projectId]
        );
    }

    /** Người có thể được giao bài: chủ dự án + thành viên. */
    public static function assignableUsers(array $project): array
    {
        return db()->fetchAll(
            'SELECT id, name FROM users WHERE is_active = 1 AND (id = ? OR id IN (SELECT user_id FROM project_members WHERE project_id = ?)) ORDER BY name',
            [$project['owner_id'], $project['id']]
        );
    }

    public static function wordpress(array $project): WordPressService
    {
        if (empty($project['wp_url']) || empty($project['wp_username']) || empty($project['wp_app_password_enc'])) {
            throw new \RuntimeException('Dự án chưa cấu hình kết nối WordPress.');
        }
        return new WordPressService($project['wp_url'], $project['wp_username'], (string)Crypto::decrypt($project['wp_app_password_enc']));
    }

    public static function hasSheet(array $project): bool
    {
        return !empty($project['gsheet_id']);
    }
}
