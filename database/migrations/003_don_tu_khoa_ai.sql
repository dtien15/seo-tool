-- Dọn nhãn thừa AI ghi vào tên nhóm chủ đề ("nhóm chủ đề: cà phê" -> "cà phê")
UPDATE keywords SET cluster = TRIM(SUBSTRING(cluster, 13)) WHERE cluster LIKE 'nhóm chủ đề:%';
UPDATE keywords SET cluster = TRIM(SUBSTRING(cluster, 6)) WHERE cluster LIKE 'nhóm:%';
-- Bỏ từ khóa rác 1 chữ do AI gợi ý (chưa gắn bài viết)
DELETE FROM keywords WHERE source = 'AI gợi ý' AND article_id IS NULL AND keyword NOT LIKE '% %';
