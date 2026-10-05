<?php
$featured = null;
foreach ($images as $img) {
    if ((int)$img['position'] === 0 && $img['file_path']) {
        $featured = $img;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($article['meta_title'] ?: $article['title'] ?: $article['keyword']) ?></title>
    <style>
        body { font: 18px/1.7 Georgia, "Times New Roman", serif; color: #222; max-width: 760px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 2em; line-height: 1.25; } h2 { margin-top: 1.6em; } img { max-width: 100%; height: auto; border-radius: 6px; }
        figure { margin: 1.5em 0; } table { border-collapse: collapse; width: 100%; } td, th { border: 1px solid #ddd; padding: 6px 10px; }
        .serp { font-family: Arial, sans-serif; border: 1px solid #eee; border-radius: 8px; padding: 14px 16px; margin-bottom: 30px; background: #fafafa; }
        .serp .t { color: #1a0dab; font-size: 20px; } .serp .u { color: #006621; font-size: 14px; } .serp .d { color: #545454; font-size: 14px; line-height: 1.5; }
        a { color: #0b57d0; }
    </style>
</head>
<body>
<div class="serp">
    <div class="u"><?= e(rtrim((string)($project['wp_url'] ?: $project['domain']), '/')) ?>/<?= e($article['slug']) ?></div>
    <div class="t"><?= e($article['meta_title'] ?: $article['title']) ?></div>
    <div class="d"><?= e($article['meta_description']) ?></div>
</div>
<h1><?= e($article['title'] ?: $article['keyword']) ?></h1>
<?php if ($featured): ?><figure><img src="<?= url($featured['file_path']) ?>" alt="<?= e($featured['alt_text']) ?>"></figure><?php endif; ?>
<?= $article['content'] ?>
</body>
</html>
