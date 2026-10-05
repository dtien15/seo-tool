# SEO Tool – Web quản lý dự án SEO cho team

Web nội bộ (PHP 8.1+ và MySQL) để team SEO quản lý nhiều dự án:

- **Tài khoản và phân quyền**: admin cấp tài khoản cho từng SEOer. Mỗi người quản lý nhiều dự án. Admin chia sẻ được dự án cho người khác và đặt hạn mức chi phí AI theo tháng.
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
| Claude API key | https://console.anthropic.com. **Gói Claude Pro không dùng được** cho tool, phải nạp tiền API riêng. |
| OpenAI API key (tạo ảnh) | https://platform.openai.com. **Gói ChatGPT Plus không dùng được**, phải nạp tiền API riêng. |
| Google Service Account | Dùng để đồng bộ Google Sheet (hướng dẫn trong trang Cài đặt hệ thống) |

## 2. Đưa code lên hosting bằng Git™ Version Control

1. Repo **Private** nên cần cho hosting quyền đọc:
   - cPanel → *SSH Access* → *Manage SSH Keys* → *Generate a New Key* (không đặt passphrase) → *Manage* → **Authorize** → *View/Download* public key.
   - GitHub → repo → *Settings* → *Deploy keys* → *Add deploy key* → dán public key.
2. cPanel → **Git™ Version Control** → *Create*:
   - *Clone URL*: `git@github.com:dtien15/seo-tool.git`
   - *Repository Path*: **thư mục web** (Document Root) của domain/subdomain chạy tool, thư mục phải trống.
3. Cài thư viện (1 lần): cPanel → *Terminal* → `cd ~/<thư-mục-web> && composer install --no-dev`
4. Mở website → **trình cài đặt** hiện ra → điền MySQL và tạo tài khoản admin (làm ngay sau khi clone).

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
2. **Tài khoản**: cấp tài khoản cho từng SEOer, đặt hạn mức $/tháng nếu cần.
3. SEOer đăng nhập → **Tạo dự án** → điền thông tin, kết nối WordPress bằng **Application Password** (WP Admin → Người dùng → Hồ sơ → Application Passwords).
4. Tab **Interlink**: quét sitemap.
5. Tab **Google Sheet**: chia sẻ sheet cho email service account → dán link → *Khởi tạo & đồng bộ* → dán Apps Script theo hướng dẫn trên trang.
6. **Thêm bài viết** → *Viết bài bằng AI* → duyệt và sửa → *Đăng lên WP*.

## 5. Quy trình trạng thái bài

`Ý tưởng → Đang viết → Chờ duyệt → (Cần sửa) → Đã duyệt → Nháp trên WP / Đã đăng`

- Nếu bật *"Tự động đẩy lên WordPress khi Đã duyệt"* trong cài đặt dự án, chỉ cần đổi trạng thái sang **Đã duyệt** (trên web hoặc trên Google Sheet) là bài tự đăng.
- Meta title/description được gửi theo field của Rank Math/Yoast. Field này chỉ có tác dụng khi site cho phép ghi meta qua REST API. Nếu không, meta description vẫn được dùng làm excerpt.

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
config.php             Do trình cài đặt tạo – KHÔNG đưa lên Git
```
