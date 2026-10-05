<?php
/*
 * File cấu hình mẫu.
 * Cách dùng: copy file này thành config.php (cùng thư mục), điền thông tin bên dưới.
 * config.php KHÔNG được đưa lên Git (đã có trong .gitignore).
 * Nếu chưa có config.php, mở website sẽ hiện form cài đặt tự tạo file này.
 */
return [
    // Địa chỉ website chạy tool (không có dấu / ở cuối)
    'app_url' => 'https://app.dgmasia.vn',

    // MySQL – tên database và user trên cPanel có tiền tố tài khoản, ví dụ uihcnetycm_seotool
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'uihcnetycm_seotool',
    'db_user' => 'uihcnetycm_seo',
    'db_pass' => '',

    // Khóa mã hóa API key / mật khẩu WordPress: điền một chuỗi ngẫu nhiên dài (ít nhất 32 ký tự).
    // ĐIỀN 1 LẦN, KHÔNG ĐỔI về sau (đổi sẽ phải nhập lại toàn bộ API key và mật khẩu WordPress).
    'app_key' => '',

    // true để hiện lỗi chi tiết khi cần sửa lỗi, bình thường để false
    'debug' => false,
];
