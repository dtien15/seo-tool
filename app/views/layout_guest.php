<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'SEO Tool') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body class="guest">
<div class="container py-5">
    <?php foreach (flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> mx-auto" style="max-width:640px"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</div>
</body>
</html>
