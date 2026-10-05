<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Article;

/**
 * Đồng bộ 2 chiều bài viết <-> Google Sheet.
 * Cột: A ID | B Từ khóa | C Tiêu đề | D Trạng thái | E Người phụ trách | F Link bài | G Cập nhật lúc
 *
 * Quy tắc khi 2 bên khác nhau: so "Cập nhật lúc" trên sheet với thời điểm đổi trạng thái trên web,
 * bên nào mới hơn thì thắng (sheet không có thời gian => sheet thắng).
 */
class SheetSync
{
    public const HEADERS = ['ID', 'Từ khóa', 'Tiêu đề', 'Trạng thái', 'Người phụ trách', 'Link bài', 'Cập nhật lúc'];

    private static function rowFor(array $article): array
    {
        $assignee = $article['assigned_to']
            ? (string)db()->value('SELECT name FROM users WHERE id = ?', [$article['assigned_to']])
            : '';
        return [
            (string)$article['id'],
            (string)$article['keyword'],
            (string)($article['title'] ?? ''),
            status_label($article['status']),
            $assignee,
            (string)($article['wp_url'] ?? ''),
            (string)$article['status_changed_at'],
        ];
    }

    private static function range(array $project, string $cells): string
    {
        return GoogleSheetsService::range($project['gsheet_tab'], $cells);
    }

    /** Tạo tab, tiêu đề cột, dropdown trạng thái và đồng bộ toàn bộ bài. */
    public static function init(array $project): void
    {
        $gs = GoogleSheetsService::fromSettings();
        $meta = $gs->meta($project['gsheet_id']);
        $sheetId = null;
        foreach ($meta['sheets'] ?? [] as $s) {
            if (($s['properties']['title'] ?? '') === $project['gsheet_tab']) {
                $sheetId = (int)$s['properties']['sheetId'];
            }
        }
        if ($sheetId === null) {
            $res = $gs->batchUpdate($project['gsheet_id'], [['addSheet' => ['properties' => ['title' => $project['gsheet_tab']]]]]);
            $sheetId = (int)$res['replies'][0]['addSheet']['properties']['sheetId'];
        }
        $gs->updateValues($project['gsheet_id'], self::range($project, 'A1:G1'), [self::HEADERS]);

        $labels = array_map(fn($s) => ['userEnteredValue' => $s[0]], array_values(article_statuses()));
        $gs->batchUpdate($project['gsheet_id'], [
            ['setDataValidation' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'startColumnIndex' => 3, 'endColumnIndex' => 4],
                'rule' => ['condition' => ['type' => 'ONE_OF_LIST', 'values' => $labels], 'strict' => true, 'showCustomUi' => true],
            ]],
            ['repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'startColumnIndex' => 6, 'endColumnIndex' => 7],
                'cell' => ['userEnteredFormat' => ['numberFormat' => ['type' => 'TEXT']]],
                'fields' => 'userEnteredFormat.numberFormat',
            ]],
            ['repeatCell' => [
                'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1],
                'cell' => ['userEnteredFormat' => ['textFormat' => ['bold' => true], 'backgroundColor' => ['red' => 0.85, 'green' => 0.92, 'blue' => 1]]],
                'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
            ]],
            ['updateSheetProperties' => [
                'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['frozenRowCount' => 1]],
                'fields' => 'gridProperties.frozenRowCount',
            ]],
        ]);
        self::pull($project);
        self::pushAll($project);
    }

    /** Đọc cột ID trên sheet: [articleId => rowNumber] */
    private static function idRows(GoogleSheetsService $gs, array $project): array
    {
        $map = [];
        foreach ($gs->getValues($project['gsheet_id'], self::range($project, 'A2:A')) as $i => $row) {
            $id = (int)trim((string)($row[0] ?? ''));
            if ($id > 0) {
                $map[$id] = $i + 2;
            }
        }
        return $map;
    }

    public static function pushArticle(array $project, array $article): void
    {
        $gs = GoogleSheetsService::fromSettings();
        $row = (int)($article['sheet_row'] ?? 0);
        if ($row > 1) {
            $check = $gs->getValues($project['gsheet_id'], self::range($project, 'A' . $row));
            if ((int)($check[0][0] ?? 0) !== (int)$article['id']) {
                $row = 0;
            }
        }
        if ($row <= 1) {
            $row = self::idRows($gs, $project)[(int)$article['id']] ?? 0;
        }
        if ($row > 1) {
            $gs->updateValues($project['gsheet_id'], self::range($project, "A$row:G$row"), [self::rowFor($article)]);
        } else {
            $res = $gs->appendValues($project['gsheet_id'], self::range($project, 'A:G'), [self::rowFor($article)]);
            if (preg_match('~![A-Z]+(\d+)~', (string)($res['updates']['updatedRange'] ?? ''), $m)) {
                $row = (int)$m[1];
            }
        }
        if ($row > 1 && $row !== (int)$article['sheet_row']) {
            db()->update('articles', ['sheet_row' => $row], 'id = ?', [$article['id']]);
        }
    }

    public static function pushAll(array $project): int
    {
        $gs = GoogleSheetsService::fromSettings();
        $rows = self::idRows($gs, $project);
        $updates = [];
        $appends = [];
        $articles = db()->fetchAll('SELECT * FROM articles WHERE project_id = ? ORDER BY id', [$project['id']]);
        foreach ($articles as $a) {
            if (isset($rows[(int)$a['id']])) {
                $r = $rows[(int)$a['id']];
                $updates[] = [self::range($project, "A$r:G$r"), [self::rowFor($a)]];
            } else {
                $appends[] = self::rowFor($a);
            }
        }
        foreach (array_chunk($updates, 200) as $chunk) {
            $gs->updateMany($project['gsheet_id'], $chunk);
        }
        if ($appends) {
            $gs->appendValues($project['gsheet_id'], self::range($project, 'A:G'), $appends);
        }
        return count($articles);
    }

    /** Đọc toàn bộ sheet và cập nhật về web. Trả về số dòng đã thay đổi. */
    public static function pull(array $project): int
    {
        $gs = GoogleSheetsService::fromSettings();
        $values = $gs->getValues($project['gsheet_id'], self::range($project, 'A2:G'));
        $changed = 0;
        $writeBack = [];
        $pushBack = [];
        foreach ($values as $i => $row) {
            $rowNumber = $i + 2;
            $result = self::applyRow($project, $rowNumber, [
                'id' => trim((string)($row[0] ?? '')),
                'keyword' => trim((string)($row[1] ?? '')),
                'title' => trim((string)($row[2] ?? '')),
                'status' => trim((string)($row[3] ?? '')),
                'updated_at' => trim((string)($row[6] ?? '')),
            ]);
            if ($result['action'] === 'created') {
                $writeBack[] = [self::range($project, "A$rowNumber"), [[(string)$result['id']]]];
                $changed++;
            } elseif ($result['action'] === 'updated') {
                $changed++;
            } elseif ($result['action'] === 'web_newer') {
                $pushBack[] = $result['id'];
            }
        }
        $gs->updateMany($project['gsheet_id'], $writeBack);
        foreach ($pushBack as $id) {
            $a = Article::find($id);
            if ($a) {
                self::pushArticle($project, $a);
            }
        }
        db()->update('projects', ['sheet_pulled_at' => now()], 'id = ?', [$project['id']]);
        return $changed;
    }

    /**
     * Áp dụng một dòng trên sheet vào web.
     * @return array{action: string, id: int|null, error?: string}
     */
    public static function applyRow(array $project, int $rowNumber, array $data): array
    {
        $sheetTime = self::parseTime($data['updated_at'] ?? '');
        $id = (int)$data['id'];

        if ($id <= 0) {
            if ($data['keyword'] === '') {
                return ['action' => 'skip', 'id' => null];
            }
            $newId = Article::create($project, [
                'keyword' => $data['keyword'],
                'title' => $data['title'] !== '' ? $data['title'] : null,
                'status' => status_from_label($data['status']) ?? 'idea',
                'assigned_to' => $project['owner_id'],
            ]);
            db()->update('articles', ['sheet_row' => $rowNumber], 'id = ?', [$newId]);
            return ['action' => 'created', 'id' => $newId];
        }

        $article = db()->fetch('SELECT * FROM articles WHERE id = ? AND project_id = ?', [$id, $project['id']]);
        if (!$article) {
            return ['action' => 'skip', 'id' => null, 'error' => 'ID ' . $id . ' không thuộc dự án này'];
        }
        if ((int)$article['sheet_row'] !== $rowNumber) {
            db()->update('articles', ['sheet_row' => $rowNumber], 'id = ?', [$id]);
        }

        $updated = false;
        // Từ khóa / tiêu đề chỉ nhận từ sheet khi bài còn ở giai đoạn ý tưởng.
        if ($article['status'] === 'idea' && empty($article['content'])) {
            $fields = [];
            if ($data['keyword'] !== '' && $data['keyword'] !== $article['keyword']) {
                $fields['keyword'] = mb_substr($data['keyword'], 0, 255);
            }
            if ($data['title'] !== '' && $data['title'] !== (string)$article['title']) {
                $fields['title'] = $data['title'];
            }
            if ($fields) {
                db()->update('articles', $fields, 'id = ?', [$id]);
                $updated = true;
            }
        }

        $sheetStatus = status_from_label($data['status']);
        if ($sheetStatus === null || $sheetStatus === $article['status']) {
            return ['action' => $updated ? 'updated' : 'same', 'id' => $id];
        }
        $webTime = strtotime($article['status_changed_at']);
        if ($sheetTime !== null && $sheetTime < $webTime) {
            return ['action' => 'web_newer', 'id' => $id];
        }
        Article::setStatus($id, $sheetStatus, true, $sheetTime ? date('Y-m-d H:i:s', $sheetTime) : now());
        return ['action' => 'updated', 'id' => $id];
    }

    private static function parseTime(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$~', $value, $m)) {
            return mktime((int)$m[4], (int)$m[5], (int)($m[6] ?? 0), (int)$m[2], (int)$m[1], (int)$m[3]);
        }
        $t = strtotime($value);
        return $t === false ? null : $t;
    }

    public static function webhookUrl(): string
    {
        return absolute_url('api/sheet-webhook');
    }

    /** Mã Apps Script để dán vào Google Sheet (Tiện ích mở rộng → Apps Script). */
    public static function appsScript(array $project): string
    {
        $url = json_encode(self::webhookUrl(), JSON_UNESCAPED_SLASHES);
        $token = json_encode($project['sheet_token']);
        $tab = json_encode($project['gsheet_tab'], JSON_UNESCAPED_UNICODE);
        return <<<JS
// ====== Đồng bộ Google Sheet -> Web SEO (dự án #{$project['id']}) ======
const WEBHOOK_URL = {$url};
const TOKEN = {$token};
const SHEET_NAME = {$tab};

// Chạy hàm này 1 lần (nút ▶ Run) để tạo trigger và cấp quyền.
function setup() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'onEditHandler') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('onEditHandler').forSpreadsheet(SpreadsheetApp.getActive()).onEdit().create();
}

function onEditHandler(e) {
  var sh = e.range.getSheet();
  if (sh.getName() !== SHEET_NAME) return;
  // Chỉ theo dõi cột B (Từ khóa), C (Tiêu đề), D (Trạng thái)
  if (e.range.getLastColumn() < 2 || e.range.getColumn() > 4) return;
  var now = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'yyyy-MM-dd HH:mm:ss');
  var start = Math.max(2, e.range.getRow());
  var end = e.range.getLastRow();
  for (var r = start; r <= end; r++) {
    var v = sh.getRange(r, 1, 1, 7).getValues()[0];
    if (!v[0] && !v[1]) continue;
    sh.getRange(r, 7).setNumberFormat('@').setValue(now);
    var payload = { token: TOKEN, row: r, id: String(v[0] || ''), keyword: String(v[1] || ''),
                    title: String(v[2] || ''), status: String(v[3] || ''), updated_at: now };
    try {
      var res = UrlFetchApp.fetch(WEBHOOK_URL, { method: 'post', contentType: 'application/json',
        payload: JSON.stringify(payload), muteHttpExceptions: true });
      var data = JSON.parse(res.getContentText());
      if (data.ok) {
        if (data.id && !v[0]) sh.getRange(r, 1).setValue(data.id);
        sh.getRange(r, 4).clearNote();
      } else {
        sh.getRange(r, 4).setNote('Lỗi đồng bộ: ' + data.error);
      }
    } catch (err) {
      sh.getRange(r, 4).setNote('Lỗi đồng bộ: ' + err);
    }
  }
}
JS;
    }
}
