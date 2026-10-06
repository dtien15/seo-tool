# SEO Tool – Web quản lý dự án SEO cho team

Web nội bộ (PHP 8.1+ và MySQL) để team SEO quản lý nhiều dự án:

- **Tài khoản 5 vai trò** (Admin, Trưởng phòng, SEO, Content, Design), duyệt 2 cấp SEO + TP, hạn mức chi phí AI theo tháng.
- **Theo quy trình SEO**: nghiên cứu → đối thủ → kiểm tra website → bộ từ khóa → KPI → plan content → triển khai → tối ưu lại định kỳ.
- **Dự án**: thông tin thương hiệu, giọng văn, đối tượng, quy tắc nội dung. AI đọc phần này khi viết bài.
- **Interlink**: quét `sitemap.xml` (có hỗ trợ sitemap index của Yoast/Rank Math) hoặc lấy bài/trang trực tiếp từ WordPress. Khi viết, tool chọn các link liên quan nhất để AI chèn tự nhiên vào bài.
- **Viết bài bằng AI (Claude)**: tạo outline → viết bài HTML chuẩn SEO (title, slug, meta, excerpt) → AI đề xuất prompt ảnh và alt text.
- **Tạo ảnh (OpenAI)**: ảnh đại diện và ảnh trong bài dạng WebP, tự chèn vào nội dung.
- **Đăng WordPress**: upload ảnh lên Media, đặt ảnh đại diện, chọn chuyên mục, đăng nháp / chờ duyệt / đăng ngay. Đăng lại thì cập nhật bài cũ.
- **Google Sheet 2 chiều**: đổi trạng thái trên web thì sheet cập nhật. Đổi trên sheet thì web cập nhật sau vài giây (qua Apps Script). Thêm dòng từ khóa mới trên sheet thì web tự tạo bài.
- **Hàng đợi chạy nền**: tác vụ AI và đăng bài chạy bằng cron, không bị timeout trên shared hosting.
- **Thống kê chi phí AI** theo người dùng và dự án.

---

## 1. Chuẩn bị

| Thứ cần có | Ghi chú |
|---|---|
| Hosting cPanel | PHP **8.1 trở lên**, có các extension `pdo_mysql`, `curl`, `openssl`, `mbstring`, `simplexml` |
| Database MySQL | Tạo trong cPanel → *MySQL® Databases* (tạo DB, tạo user, gán user vào DB với **ALL PRIVILEGES**) |
| AI viết bài – chọn 1 trong 2 | **Claude API** (console.anthropic.com) hoặc **OpenAI API** (platform.openai.com), trả theo lượng dùng |
| OpenAI API key (tạo hình bằng AI, không bắt buộc) | https://platform.openai.com – Designer cũng có thể tự làm hình rồi upload |
| Google Service Account | Dùng để đồng bộ Google Sheet (hướng dẫn trong trang Cài đặt hệ thống) |

## 2. Đưa code lên hosting bằng Git™ Version Control

1. Repo **Private** nên cần cho hosting quyền đọc:
   - cPanel → *SSH Access* → *Manage SSH Keys* → *Generate a New Key* (không đặt passphrase) → *Manage* → **Authorize** → *View/Download* public key.
   - GitHub → repo → *Settings* → *Deploy keys* → *Add deploy key* → dán public key.
2. cPanel → **Git™ Version Control** → *Create*:
   - *Clone URL*: `git@github.com:dtien15/seo-tool.git`
   - *Repository Path*: **thư mục web** (Document Root) của domain/subdomain chạy tool, thư mục phải trống.
3. Cài thư viện (1 lần): cPanel → *Terminal* → `cd ~/<thư-mục-web> && composer install --no-dev`
4. Cấu hình database, chọn 1 trong 2 cách:
   - **Tự tạo file:** trong File Manager copy `config.sample.php` thành `config.php`, điền `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_URL`. Mở website → tạo tài khoản admin đầu tiên.
   - **Form cài đặt:** chưa có `config.php` thì mở website sẽ hiện form, điền xong tool tự tạo `config.php`.

   Khóa mã hóa API key / mật khẩu WordPress được tự tạo ở `storage/app.key`. **Sao lưu file này cùng database**: mất file thì phải nhập lại các key và mật khẩu.

### Cập nhật phiên bản mới

cPanel → *Git™ Version Control* → *Manage* → **Update from Remote**. Nếu `composer.json` thay đổi thì chạy lại `composer install --no-dev`.
Thay đổi database (file mới trong `database/migrations/`) tự áp dụng ở lần truy cập tiếp theo.

## 3. Cài cron (bắt buộc)

cPanel → **Cron Jobs** → thêm lệnh chạy **mỗi phút** (`* * * * *`):

```
/usr/local/bin/php /home/abcuser/seo.congty.com/cron/worker.php >/dev/null 2>&1
```

Đường dẫn PHP có thể khác tùy hosting, ví dụ `/opt/cpanel/ea-php82/root/usr/bin/php`. Trang *Cài đặt hệ thống* có hiện sẵn lệnh và báo cron đang chạy hay chưa.

## 4. Cấu hình lần đầu (tài khoản admin)

1. **Cài đặt hệ thống**: nhập Claude API key, OpenAI API key, tải file JSON Service Account, rồi bấm *Kiểm tra*.
2. **Tài khoản**: cấp tài khoản theo vai trò, đặt hạn mức $/tháng nếu cần.
3. SEO đăng nhập → **Tạo dự án** → điền thông tin, kết nối WordPress bằng **Application Password** (WP Admin → Người dùng → Hồ sơ → Application Passwords).
4. Tab **Interlink**: quét sitemap.
5. Tab **Google Sheet**: chia sẻ sheet cho email service account → dán link → *Khởi tạo & đồng bộ* → dán Apps Script theo hướng dẫn trên trang.
6. **Thêm bài viết** → *Viết bài bằng AI* → duyệt và sửa → *Đăng lên WP*.

## 5. Quy trình SEO trong tool

**Vai trò tài khoản:** Admin · Trưởng phòng (TP) · SEO · Content · Design.
Content / Design chỉ thấy bài được giao cho mình.

**Mỗi dự án đi theo các tab:**

1. **Nghiên cứu**: website, sản phẩm/dịch vụ, khách hàng, hành vi người dùng.
2. **Đối thủ**: import từ khóa top (CSV từ Ahrefs/Semrush), số liệu traffic/backlink theo tháng, ghi chú backlink & content.
3. **Website**: chưa có web thì lên kế hoạch build; đã có web thì dùng checklist Technical / Content / Giao diện, kết luận (ổn / không ổn / web rác) và phương án đề xuất.
4. **Từ khóa**: bộ từ khóa, AI gom nhóm chủ đề, tạo bài vào kế hoạch.
5. **KPI**: mục tiêu / thực tế theo tháng.
6. **Plan content**: danh sách bài và phân công SEO / Content / Design.
7. **Nội dung web**: rà soát trang cũ (giữ / tối ưu / gộp / xóa) và tạo việc tối ưu lại.

**AI hỗ trợ ở từng bước (tùy chọn – muốn làm tay thì cứ nhập như bình thường):** nút ✨ ở tab Nghiên cứu (AI viết nháp từng mục), Đối thủ (AI phân tích web đối thủ), Website (tự kiểm tra kỹ thuật + AI điền checklist, kết luận), Từ khóa (AI gợi ý, AI gom nhóm), KPI (AI đề xuất chỉ tiêu), Nội dung web (AI rà soát 20 trang/lần). AI chỉ điền vào chỗ trống hoặc nối thêm, không xóa phần đã nhập. Điểm tốc độ PageSpeed cần PageSpeed API key (miễn phí) trong Cài đặt hệ thống.

**Luồng một bài viết:**

`Kế hoạch → Outline (SEO) → TP duyệt outline → Viết bài (AI viết nháp, Content sửa) → SEO + TP duyệt bài → Làm hình (Design upload / AI) → SEO + TP duyệt hình → Sẵn sàng đăng → SEO đăng WordPress`

- Bước duyệt bài và duyệt hình cần **cả SEO và TP** duyệt. Một bên "Yêu cầu sửa" (bắt buộc ghi chú) thì bài quay lại người làm.
- Bài đã đăng quá số ngày cài đặt (mặc định 30 ngày) sẽ hiện ở mục **"Bài cũ đến hạn tối ưu lại"**. SEO chọn "Đã kiểm tra" hoặc "Tối ưu lại" (bài quay lại bước viết; khi đăng sẽ cập nhật đè bài cũ).
- Mọi thao tác được ghi vào **Lịch sử xử lý** của bài.
- Nếu bật "Tự động đẩy lên WordPress" trong cài đặt dự án, bài tự đăng khi duyệt hình xong.

## 6. Xử lý sự cố thường gặp

| Hiện tượng | Cách xử lý |
|---|---|
| Bài cứ ở trạng thái "Chờ viết bài" | Cron chưa chạy. Kiểm tra Cron Jobs và đường dẫn PHP. |
| WordPress báo 401/403 | Sai Application Password, hoặc plugin bảo mật / Cloudflare chặn REST API. Cần whitelist IP hosting của tool. |
| "Phản hồi không phải JSON" từ WordPress | Firewall/WAF trả về trang HTML. Tắt chặn `/wp-json/` cho IP của tool. |
| Google Sheets 403/404 | Chưa chia sẻ sheet cho email service account với quyền Người chỉnh sửa. |
| Sheet đổi trạng thái nhưng web không đổi | Chưa chạy hàm `setup` trong Apps Script. Dù vậy cron vẫn đồng bộ lại mỗi 5 phút. |
| Xem lỗi chi tiết | Menu **Hàng đợi** (admin) hoặc file `storage/logs/app-YYYY-MM.log` |

## Cấu trúc thư mục

```
index.php              Front controller + router
app/Controllers/       Xử lý request
app/Models/            Project, Article
app/Services/          Claude, OpenAI ảnh, WordPress, Sitemap, Google Sheets, Interlink, Prompt viết bài
app/Jobs.php           Các tác vụ chạy nền
app/Worker.php         Bộ xử lý hàng đợi (cron/worker.php gọi)
app/views/             Giao diện (Bootstrap 5)
database/migrations/   Cấu trúc database (tự chạy)
assets/                CSS/JS
uploads/               Ảnh AI tạo ra (không đưa lên Git)
config.php             Cấu hình database (tự tạo từ config.sample.php) – KHÔNG đưa lên Git
storage/app.key        Khóa mã hóa tự sinh – KHÔNG đưa lên Git, nhớ sao lưu
```
