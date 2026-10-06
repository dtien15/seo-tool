<?php use App\Settings; ?>
<h1 class="h4 mb-3">Cài đặt hệ thống</h1>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body small">
        <div class="fw-semibold mb-1"><i class="bi bi-box-seam"></i> Thư viện Claude SDK</div>
        <?= $sdkInstalled ? '<span class="text-success">Đã cài</span>' : '<span class="text-danger">Chưa cài – chạy <code>composer install</code> trong thư mục web (xem README)</span>' ?>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body small">
        <div class="fw-semibold mb-1"><i class="bi bi-clock-history"></i> Cron worker</div>
        <?php if ($lastJob && strtotime($lastJob) > time() - 600): ?>
            <span class="text-success">Hoạt động</span> – job gần nhất <?= e(time_ago($lastJob)) ?>
        <?php elseif ($pendingJobs > 0): ?>
            <span class="text-danger">Có <?= $pendingJobs ?> job đang chờ nhưng cron chưa chạy!</span> Kiểm tra Cron Jobs trên cPanel.
        <?php else: ?>
            <span class="text-muted">Chưa có job nào gần đây.</span>
        <?php endif; ?>
        <div class="text-muted mt-1">Lệnh cron (mỗi phút):<br><code class="user-select-all">php <?= e(BASE_PATH) ?>/cron/worker.php &gt;/dev/null 2&gt;&amp;1</code></div>
    </div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body small">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Phiên bản</div>
        PHP <?= PHP_VERSION ?> · App <?= APP_VERSION ?>
    </div></div></div>
</div>

<form method="post" action="<?= url('/settings') ?>" enctype="multipart/form-data" autocomplete="off">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h6 text-uppercase text-muted mb-2"><i class="bi bi-plug"></i> Kết nối AI</h2>
            <p class="small text-muted">Nhập API key rồi bấm <b>Lưu cài đặt</b> – hệ thống tự kiểm tra kết nối. Các AI kết nối được (✓) sẽ hiện trong ô chọn AI ở từng chức năng (outline, viết bài, nghiên cứu, kiểm tra web...).</p>
            <?php
            $statusBadge = function (string $p) {
                $st = \App\Services\AiText::status($p);
                if (!\App\Services\AiText::hasKey($p)) {
                    return '<span class="badge text-bg-light border">Chưa kết nối</span>';
                }
                if ($st === null) {
                    return '<span class="badge text-bg-warning">Chưa kiểm tra</span>';
                }
                return $st['ok']
                    ? '<span class="badge text-bg-success" title="' . e($st['message']) . '"><i class="bi bi-check-lg"></i> Đã kết nối</span>'
                    : '<span class="badge text-bg-danger" title="' . e($st['message']) . '"><i class="bi bi-x-lg"></i> Lỗi kết nối</span>';
            };
            $statusLine = function (string $p) {
                $st = \App\Services\AiText::status($p);
                return $st
                    ? '<div class="small mt-2 ' . ($st['ok'] ? 'text-success' : 'text-danger') . '" id="status-' . $p . '">' . ($st['ok'] ? '✔ ' : '✖ ') . e($st['message']) . ' <span class="text-muted">· ' . e(time_ago($st['at'])) . '</span></div>'
                    : '<div class="small mt-2" id="status-' . $p . '"></div>';
            };
            ?>
            <div class="card mb-4">
                <div class="card-header bg-white d-flex align-items-center gap-2"><strong><i class="bi bi-stars"></i> Claude (Anthropic)</strong> <?= $statusBadge('anthropic') ?>
                    <span class="ms-auto small text-muted">Viết: outline, bài viết, nghiên cứu...</span></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">API key</label>
                        <input type="password" name="anthropic_api_key" class="form-control" placeholder="<?= $hasClaude ? '•••••••• đã lưu (để trống nếu không đổi)' : 'sk-ant-...' ?>" autocomplete="new-password">
                        <div class="form-text">Tạo tại <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a> (trả phí theo lượng dùng – khác gói Claude Pro).</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Model</label>
                            <select name="claude_model" class="form-select">
                                <?php foreach (Settings::CLAUDE_MODELS as $id => [$label, $in, $out]): ?>
                                    <option value="<?= $id ?>" <?= Settings::get('claude_model') === $id ? 'selected' : '' ?>><?= e($label) ?> – $<?= $in ?>/$<?= $out ?> mỗi 1M token</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Mức suy nghĩ</label>
                            <select name="claude_effort" class="form-select">
                                <?php foreach (['low' => 'Thấp (nhanh, rẻ)', 'medium' => 'Trung bình (khuyên dùng)', 'high' => 'Cao (kỹ hơn, tốn hơn)'] as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= Settings::get('claude_effort') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-ajax-test="<?= url('/settings/test-ai?provider=anthropic') ?>" data-result="#status-anthropic" <?= $hasClaude ? '' : 'disabled' ?>><i class="bi bi-plug"></i> Kiểm tra kết nối</button>
                        <?php if ($hasClaude): ?><label class="small"><input type="checkbox" name="clear_anthropic_api_key" value="1"> Xóa key</label><?php endif; ?>
                    </div>
                    <?= $statusLine('anthropic') ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white d-flex align-items-center gap-2"><strong><i class="bi bi-openai"></i> OpenAI</strong> <?= $statusBadge('openai') ?>
                    <span class="ms-auto small text-muted">Viết (nếu nhập model) + tạo hình</span></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">API key</label>
                        <input type="password" name="openai_api_key" class="form-control" placeholder="<?= $hasOpenAI ? '•••••••• đã lưu (để trống nếu không đổi)' : 'sk-...' ?>" autocomplete="new-password">
                        <div class="form-text">Tạo tại <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a> (khác gói ChatGPT Plus).</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 small fw-semibold text-muted">Viết bài (để trống model nếu chỉ dùng OpenAI tạo hình)</div>
                        <div class="col-md-4"><label class="form-label small">Model viết</label><input name="openai_text_model" class="form-control form-control-sm" value="<?= e(Settings::get('openai_text_model', '')) ?>" placeholder="vd gpt-5-mini"></div>
                        <div class="col-md-4"><label class="form-label small">Giá input $/1M token</label><input type="number" step="0.01" min="0" name="openai_price_in" class="form-control form-control-sm" value="<?= e(Settings::get('openai_price_in', '')) ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Giá output $/1M token</label><input type="number" step="0.01" min="0" name="openai_price_out" class="form-control form-control-sm" value="<?= e(Settings::get('openai_price_out', '')) ?>"></div>
                        <div class="col-12 small fw-semibold text-muted mt-3">Tạo hình</div>
                        <div class="col-md-4"><label class="form-label small">Model hình</label><input name="image_model" class="form-control form-control-sm" value="<?= e(Settings::get('image_model')) ?>"></div>
                        <div class="col-md-4"><label class="form-label small">Kích thước</label>
                            <select name="image_size" class="form-select form-select-sm">
                                <?php foreach (['1536x1024' => 'Ngang 1536×1024', '1024x1024' => 'Vuông 1024×1024', '1024x1536' => 'Dọc 1024×1536'] as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= Settings::get('image_size') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-4"><label class="form-label small">Chất lượng</label>
                            <select name="image_quality" class="form-select form-select-sm">
                                <?php foreach (['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao'] as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= Settings::get('image_quality') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-4"><label class="form-label small">Giá mỗi hình (USD)</label><input type="number" step="0.001" min="0" name="image_price_usd" class="form-control form-control-sm" value="<?= e(Settings::get('image_price_usd')) ?>"></div>
                        <div class="col-12 form-text mt-0">Giá dùng để thống kê chi phí – xem bảng giá tại platform.openai.com.</div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-ajax-test="<?= url('/settings/test-ai?provider=openai') ?>" data-result="#status-openai" <?= $hasOpenAI ? '' : 'disabled' ?>><i class="bi bi-plug"></i> Kiểm tra kết nối</button>
                        <?php if ($hasOpenAI): ?><label class="small"><input type="checkbox" name="clear_openai_api_key" value="1"> Xóa key</label><?php endif; ?>
                    </div>
                    <?= $statusLine('openai') ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-google"></i> Google Service Account (đồng bộ Sheet)</strong></div>
                <div class="card-body">
                    <?php if ($serviceEmail): ?>
                        <div class="alert alert-success py-2 small">Đang dùng: <code class="user-select-all"><?= e($serviceEmail) ?></code><br>SEOer cần chia sẻ Google Sheet cho email này (quyền Người chỉnh sửa).</div>
                    <?php endif; ?>
                    <ol class="small text-muted ps-3">
                        <li>Vào <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a> → tạo project → bật <b>Google Sheets API</b>.</li>
                        <li>IAM &amp; Admin → Service Accounts → tạo mới → tab Keys → Add key → JSON → tải file về.</li>
                        <li>Tải file JSON đó lên đây.</li>
                    </ol>
                    <input type="file" name="google_service_account_file" class="form-control mb-2" accept=".json,application/json">
                    <details class="small"><summary>Hoặc dán nội dung JSON</summary>
                        <textarea name="google_service_account" class="form-control font-monospace small mt-2" rows="4"></textarea>
                    </details>
                    <div class="input-group input-group-sm mt-3">
                        <input id="test-sheet-id" class="form-control" placeholder="(tùy chọn) ID sheet để thử truy cập">
                        <button type="button" class="btn btn-outline-secondary" data-ajax-test="<?= url('/settings/test-google') ?>" data-result="#google-test" data-extra="#test-sheet-id"><i class="bi bi-plug"></i> Kiểm tra</button>
                    </div>
                    <div class="small mt-2" id="google-test"></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-speedometer"></i> PageSpeed API key (kiểm tra tốc độ website)</strong></div>
                <div class="card-body">
                    <input type="password" name="pagespeed_api_key" class="form-control" autocomplete="new-password" placeholder="<?= Settings::has('pagespeed_api_key') ? '•••••••• đã lưu (để trống nếu không đổi)' : 'AIza...' ?>">
                    <div class="form-text">Miễn phí. Google Cloud Console → bật <b>PageSpeed Insights API</b> → APIs &amp; Services → Credentials → Create credentials → API key. Dùng khi bấm "AI kiểm tra website".</div>
                    <?php if (Settings::has('pagespeed_api_key')): ?><label class="small mt-1"><input type="checkbox" name="clear_pagespeed_api_key" value="1"> Xóa key</label><?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-wallet2"></i> Hạn mức chi phí</strong></div>
                <div class="card-body">
                    <label class="form-label">Hạn mức mặc định cho mỗi SEOer (USD/tháng)</label>
                    <input type="number" step="0.5" min="0" name="default_monthly_budget" class="form-control" value="<?= e(Settings::get('default_monthly_budget', '')) ?>" placeholder="Để trống = không giới hạn">
                    <div class="form-text">Có thể đặt riêng cho từng người ở trang Tài khoản. Admin không bị giới hạn.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="sticky-actions"><button class="btn btn-primary"><i class="bi bi-save"></i> Lưu cài đặt</button></div>
</form>
