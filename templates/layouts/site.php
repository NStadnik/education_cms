<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Сайт закладу освіти') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/site.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/rich-editor.css') ?>">
    <?php
        $siteTemplateKey = preg_replace('/[^a-z0-9_-]/i', '', (string) ($settings['site_template'] ?? 'official')) ?: 'official';
        $siteTheme = \App\Services\SiteThemes::get($siteTemplateKey);
        if (!empty($siteTheme['css'])):
    ?>
        <link rel="stylesheet" href="<?= e(url((string) $siteTheme['css'])) ?>">
    <?php endif; ?>
</head>
<?php
    $siteTemplate = (string) ($siteTheme['key'] ?? $siteTemplateKey);
    $globalFields = json_decode((string) ($settings['global_fields'] ?? '[]'), true);
    $globalFields = is_array($globalFields) ? $globalFields : [];
    $templateLayouts = json_decode((string) ($settings['site_template_layouts'] ?? ''), true);
    $templateLayouts = is_array($templateLayouts) ? $templateLayouts : [];
    $activeTemplateLayout = is_array($templateLayouts[$siteTemplate] ?? null) ? $templateLayouts[$siteTemplate] : [];
    $legacyHeaderLayout = json_decode((string) ($settings['site_header_layout'] ?? ''), true);
    $legacyFooterLayout = json_decode((string) ($settings['site_footer_layout'] ?? ''), true);
    $headerLayout = is_array($activeTemplateLayout['header'] ?? null) ? $activeTemplateLayout['header'] : (is_array($legacyHeaderLayout) ? $legacyHeaderLayout : []);
    $footerLayout = is_array($activeTemplateLayout['footer'] ?? null) ? $activeTemplateLayout['footer'] : (is_array($legacyFooterLayout) ? $legacyFooterLayout : []);
?>
<body class="site-template-<?= e($siteTemplate) ?>">
    <?= $this->partial('layouts/site-header', ['settings' => $settings, 'menu' => $menu ?? [], 'headerLayout' => $headerLayout]) ?>
    <?= $content ?>
    <?= $this->partial('layouts/site-footer', ['settings' => $settings, 'globalFields' => $globalFields, 'footerLayout' => $footerLayout]) ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
