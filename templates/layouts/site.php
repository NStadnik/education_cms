<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
        $seo = is_array($seo ?? null) ? $seo : [];
        $metaTitle = (string) ($seo['title'] ?? $title ?? 'Сайт закладу освіти');
        $metaDescription = trim((string) ($seo['description'] ?? ''));
    ?>
    <title><?= e($metaTitle) ?></title>
    <?php if ($metaDescription !== ''): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
    <?php if (!empty($seo['url'])): ?><link rel="canonical" href="<?= e((string) $seo['url']) ?>"><?php endif; ?>
    <meta property="og:locale" content="uk_UA">
    <meta property="og:type" content="<?= e((string) ($seo['type'] ?? 'website')) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <?php if ($metaDescription !== ''): ?><meta property="og:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
    <?php if (!empty($seo['url'])): ?><meta property="og:url" content="<?= e((string) $seo['url']) ?>"><?php endif; ?>
    <?php if (!empty($seo['site_name'])): ?><meta property="og:site_name" content="<?= e((string) $seo['site_name']) ?>"><?php endif; ?>
    <?php if (!empty($seo['image'])): ?><meta property="og:image" content="<?= e((string) $seo['image']) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('/assets/site.css?v=20260709-11') ?>">
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
    <?= $this->partial('layouts/site-header', ['settings' => $settings, 'menu' => $menu ?? [], 'headerLayout' => $headerLayout, 'isHomePage' => !empty($isHomePage)]) ?>
    <?= $content ?>
    <?= $this->partial('layouts/site-footer', ['settings' => $settings, 'globalFields' => $globalFields, 'footerLayout' => $footerLayout]) ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= url('/assets/site.js?v=20260709-11') ?>"></script>
</body>
</html>
