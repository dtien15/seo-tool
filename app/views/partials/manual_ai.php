<?php /* Hộp thoại chế độ copy–dán với ChatGPT / Claude. Nút mở: [data-manual-ai] */ ?>
<div class="modal fade" id="manual-ai-modal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" class="modal-content" id="manual-ai-form">
            <?= csrf_field() ?>
            <input type="hidden" name="task">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> <span data-title>Làm với ChatGPT / Claude</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <b>Bước 1.</b> Copy prompt
                            <button type="button" class="btn btn-sm btn-dark ms-auto" data-copy="#manual-ai-prompt"><i class="bi bi-clipboard"></i> Copy</button>
                        </div>
                        <textarea id="manual-ai-prompt" class="form-control font-monospace small" rows="16" readonly>Đang tải...</textarea>
                        <div class="small text-muted mt-2">
                            Dán vào
                            <a href="https://chatgpt.com/" target="_blank" rel="noopener">ChatGPT</a> hoặc
                            <a href="https://claude.ai/new" target="_blank" rel="noopener">Claude</a>
                            (cuộc trò chuyện mới), đợi trả lời xong.
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-2"><b>Bước 2.</b> Copy toàn bộ câu trả lời và dán vào đây</div>
                        <textarea name="result" class="form-control font-monospace small" rows="16" required placeholder="Dán câu trả lời của ChatGPT / Claude..."></textarea>
                        <div class="small text-muted mt-2">Bấm nút copy (biểu tượng 📋) dưới câu trả lời, hoặc nút "Copy code" của khối code, để giữ nguyên các thẻ.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Hủy</button>
                <button class="btn btn-primary"><i class="bi bi-check2"></i> Lưu kết quả</button>
            </div>
        </form>
    </div>
</div>
