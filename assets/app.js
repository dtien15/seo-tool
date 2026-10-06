/* SEO Tool – tiện ích giao diện */
(function () {
    'use strict';

    const base = document.body.dataset.base || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function post(url, data) {
        const body = data instanceof FormData ? data : new URLSearchParams(data || {});
        body.append('_token', csrf);
        const res = await fetch(url, {
            method: 'POST',
            body,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        try {
            return await res.json();
        } catch (e) {
            return { ok: false, error: 'Phản hồi không hợp lệ (HTTP ' + res.status + ')' };
        }
    }

    async function getJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        return res.json();
    }

    function showResult(el, res) {
        if (!el) return;
        el.className = 'small mt-2 ' + (res.ok ? 'text-success' : 'text-danger');
        el.textContent = res.ok ? '✔ ' + (res.message || 'OK') : '✖ ' + (res.error || 'Lỗi');
    }

    // Xác nhận trước khi gửi form / bấm nút
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-confirm]');
        if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
    });

    // Chọn nhiều bài + thao tác hàng loạt
    const bulkForm = document.getElementById('bulk-form');
    if (bulkForm) {
        const bar = bulkForm.querySelector('.bulk-bar');
        const update = () => {
            const n = bulkForm.querySelectorAll('input[name="ids[]"]:checked').length;
            bar.classList.toggle('d-none', n === 0);
            bar.classList.toggle('d-flex', n > 0);
            bar.querySelector('.bulk-count').textContent = n;
        };
        bulkForm.addEventListener('change', (e) => {
            if (e.target.matches('[data-check-all]')) {
                bulkForm.querySelectorAll('input[name="ids[]"]').forEach((c) => (c.checked = e.target.checked));
            }
            if (e.target.name === 'action') {
                bulkForm.querySelectorAll('.bulk-extra').forEach((el) => el.classList.toggle('d-none', el.dataset.for !== e.target.value));
            }
            update();
        });
        bulkForm.addEventListener('submit', (e) => {
            const action = bulkForm.querySelector('[name="action"]').value;
            const n = bulkForm.querySelectorAll('input[name="ids[]"]:checked').length;
            const costly = ['write', 'images', 'outline'].includes(action);
            if (action === 'delete' && !confirm('Xóa ' + n + ' bài viết đã chọn?')) e.preventDefault();
            else if (costly && n > 5 && !confirm('Chạy AI cho ' + n + ' bài (tốn chi phí API). Tiếp tục?')) e.preventDefault();
            else if (action === 'publish' && !confirm('Đăng ' + n + ' bài lên WordPress?')) e.preventDefault();
        });
    }

    // Chọn tất cả checkbox trong cùng bảng
    document.addEventListener('change', (e) => {
        const all = e.target.closest('[data-check-all-in]');
        if (!all) return;
        all.closest(all.dataset.checkAllIn).querySelectorAll('input[name="ids[]"]').forEach((c) => {
            if (c.closest('tr')?.style.display !== 'none') c.checked = all.checked;
        });
    });

    // Lọc nhanh dòng trong bảng
    document.querySelectorAll('[data-filter-table]').forEach((input) => {
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase();
            document.querySelectorAll(input.dataset.filterTable + ' tbody tr').forEach((tr) => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    });

    // Bộ từ khóa: hiện ô giá trị theo thao tác
    document.querySelectorAll('[data-kw-action]').forEach((sel) => {
        const value = sel.form.querySelector('[data-kw-value]');
        const placeholders = { set_cluster: 'Tên nhóm chủ đề', set_priority: '1, 2 hoặc 3', set_intent: 'informational / commercial / transactional...', plan: 'Ngày dự kiến (YYYY-MM-DD, không bắt buộc)' };
        sel.addEventListener('change', () => {
            value.classList.toggle('d-none', !placeholders[sel.value]);
            value.placeholder = placeholders[sel.value] || '';
            value.type = sel.value === 'plan' ? 'date' : 'text';
        });
        sel.form.addEventListener('submit', (e) => {
            const n = sel.form.querySelectorAll('input[name="ids[]"]:checked').length;
            if (!n) { e.preventDefault(); alert('Chưa chọn từ khóa nào.'); return; }
            if (sel.value === 'delete' && !confirm('Xóa ' + n + ' từ khóa?')) e.preventDefault();
            if (sel.value === 'plan' && !confirm('Tạo ' + n + ' bài trong kế hoạch content?')) e.preventDefault();
        });
    });

    // Form thêm bài: hiện ô link khi chọn "tối ưu lại"
    document.querySelectorAll('input[name="type"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const form = radio.closest('form');
            form.querySelectorAll('[data-show-when]').forEach((el) => el.classList.toggle('d-none', el.dataset.showWhen !== form.type.value));
        });
    });

    // Có tác vụ AI đang chạy: tự tải lại trang khi xong (bỏ qua nếu người dùng đang nhập liệu)
    if (document.querySelector('[data-ai-pending]')) {
        let typing = false;
        document.addEventListener('input', () => (typing = true));
        setInterval(() => { if (!typing) location.reload(); }, 15000);
    }

    // Kiểm tra kết nối WordPress
    document.querySelectorAll('[data-wp-test]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const form = btn.closest('form');
            const out = document.getElementById('wp-test-result');
            out.className = 'small mt-2 text-muted';
            out.textContent = 'Đang kiểm tra...';
            const res = await post(btn.dataset.wpTest, {
                wp_url: form.wp_url.value,
                wp_username: form.wp_username.value,
                wp_app_password: form.wp_app_password.value,
            });
            showResult(out, res);
        });
    });

    // Tải danh sách chuyên mục WordPress vào select
    document.querySelectorAll('select[data-wp-categories]').forEach(async (sel) => {
        const url = sel.dataset.wpCategories;
        if (!url) return;
        const selected = sel.dataset.selected;
        try {
            const res = await getJson(url);
            if (!res.ok) return;
            const first = sel.options[0];
            sel.innerHTML = '';
            sel.appendChild(first);
            const byParent = {};
            res.categories.forEach((c) => (byParent[c.parent] = byParent[c.parent] || []).push(c));
            const add = (parent, depth) => (byParent[parent] || []).forEach((c) => {
                const o = new Option('— '.repeat(depth) + c.name.replace(/&amp;/g, '&'), c.id);
                if (String(c.id) === selected) o.selected = true;
                sel.appendChild(o);
                add(c.id, depth + 1);
            });
            add(0, 0);
        } catch (e) { /* giữ nguyên lựa chọn hiện tại */ }
    });

    // Nút kiểm tra API trong Cài đặt
    document.querySelectorAll('[data-ajax-test]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const out = document.querySelector(btn.dataset.result);
            out.className = 'small mt-2 text-muted';
            out.textContent = 'Đang kiểm tra...';
            const extra = btn.dataset.extra ? document.querySelector(btn.dataset.extra).value : '';
            showResult(out, await post(btn.dataset.ajaxTest, { sheet_id: extra }));
        });
    });

    // Copy
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const text = document.querySelector(btn.dataset.copy).textContent;
            await navigator.clipboard.writeText(text);
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i> Đã copy';
            setTimeout(() => (btn.innerHTML = old), 1500);
        });
    });

    // Sửa link nội bộ trực tiếp trong bảng
    document.querySelectorAll('tr[data-link]').forEach((row) => {
        const url = row.dataset.link;
        row.querySelectorAll('[data-field]').forEach((input) => {
            input.addEventListener('change', async () => {
                const res = await post(url + '/update', { [input.dataset.field]: input.value });
                input.classList.add(res.ok ? 'is-valid' : 'is-invalid');
                setTimeout(() => input.classList.remove('is-valid', 'is-invalid'), 1200);
                if (input.dataset.field === 'audit_action' && input.value) {
                    row.querySelector('[data-field="audit_note"]')?.classList.remove('d-none');
                }
            });
        });
        row.querySelector('[data-link-delete]')?.addEventListener('click', async () => {
            const res = await post(url + '/delete');
            if (res.ok) row.remove();
        });
    });

    // Bộ đếm ký tự
    document.querySelectorAll('[data-counter]').forEach((input) => {
        const max = +input.dataset.counter;
        const badge = document.createElement('div');
        badge.className = 'form-text text-end';
        input.after(badge);
        const update = () => {
            const n = input.value.length;
            badge.textContent = n + '/' + max + ' ký tự';
            badge.classList.toggle('text-danger', n > max);
        };
        input.addEventListener('input', update);
        update();
    });

    window.SEO = {
        post,

        initEditor(selector) {
            if (!window.tinymce) return;
            const readonly = document.querySelector(selector)?.dataset.readonly === '1';
            tinymce.init({
                selector,
                readonly,
                license_key: 'gpl',
                height: 720,
                menubar: false,
                promotion: false,
                branding: false,
                plugins: 'lists link table image code fullscreen wordcount searchreplace',
                toolbar: 'blocks | bold italic | bullist numlist | link image table blockquote | alignleft aligncenter | searchreplace removeformat code fullscreen',
                block_formats: 'Đoạn văn=p; Heading 2=h2; Heading 3=h3; Heading 4=h4',
                relative_urls: false,
                remove_script_host: false,
                convert_urls: false,
                entity_encoding: 'raw',
                content_style: 'body{font-family:Georgia,serif;font-size:17px;line-height:1.7;max-width:780px;margin:1rem auto}img{max-width:100%;height:auto}a{color:#0b57d0}',
            });
        },

        /** Trước khi gửi duyệt: nếu bài có thay đổi chưa lưu thì lưu trước. */
        guardWorkflow() {
            const main = document.getElementById('main-form');
            if (!main) return;
            let dirty = false;
            main.addEventListener('input', () => (dirty = true));
            main.addEventListener('change', () => (dirty = true));
            main.addEventListener('submit', () => (dirty = false));
            const isDirty = () => dirty || (window.tinymce && tinymce.activeEditor && tinymce.activeEditor.isDirty());
            document.querySelectorAll('.workflow-form').forEach((form) => {
                form.addEventListener('submit', async (e) => {
                    if (!isDirty()) return;
                    e.preventDefault();
                    if (window.tinymce) tinymce.triggerSave();
                    const res = await fetch(main.action, { method: 'POST', body: new FormData(main), credentials: 'same-origin' });
                    if (!res.ok) { alert('Không lưu được bài, vui lòng bấm Lưu trước.'); return; }
                    dirty = false;
                    if (window.tinymce && tinymce.activeEditor) tinymce.activeEditor.setDirty(false);
                    form.submit();
                });
            });
            window.addEventListener('beforeunload', (e) => {
                if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        /** Trang sửa bài: tự tải lại khi AI chạy xong. */
        pollArticle() {
            const el = document.getElementById('ai-state');
            if (!el || el.dataset.busy !== '1') return;
            const tick = async () => {
                try {
                    const res = await getJson(el.dataset.stateUrl);
                    if (res.ok && !['queued', 'running'].includes(res.ai_state)) {
                        location.reload();
                        return;
                    }
                } catch (e) { /* thử lại */ }
                setTimeout(tick, 4000);
            };
            setTimeout(tick, 4000);
        },

        /** Danh sách bài: tự làm mới khi còn bài đang xử lý (nếu người dùng không đang chọn bài). */
        pollRows() {
            const busy = document.querySelectorAll('tr[data-ai-state="queued"], tr[data-ai-state="running"]');
            if (!busy.length) return;
            setTimeout(() => {
                if (!document.querySelector('input[name="ids[]"]:checked')) location.reload();
                else SEO.pollRows();
            }, 15000);
        },
    };
})();
