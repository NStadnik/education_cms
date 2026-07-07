<?php
    $layout = array_replace([
        'variant' => 'default',
        'show_brand' => true,
        'show_home' => false,
        'show_news' => false,
        'links' => [],
        'cta_label' => '',
        'cta_url' => '',
    ], is_array($headerLayout ?? null) ? $headerLayout : []);
    $variant = preg_replace('/[^a-z0-9_-]/i', '', (string) $layout['variant']) ?: 'default';
    $siteLogo = \App\Services\Files::normalize((string) ($settings['site_logo'] ?? ''));
    $renderMenuLinks = static function (array $links) use (&$renderMenuLinks): string {
        $html = '';
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? '#')) ?: '#';
            $children = is_array($link['children'] ?? null) ? $link['children'] : [];
            if ($label === '' && !$children) {
                continue;
            }
            $html .= '<span class="site-menu-item">';
            $html .= '<a href="' . e($url) . '">' . e($label ?: 'Пункт меню') . '</a>';
            if ($children) {
                $html .= '<span class="site-submenu">' . $renderMenuLinks($children) . '</span>';
            }
            $html .= '</span>';
        }

        return $html;
    };
?>
<header class="topbar site-header site-header-<?= e($variant) ?>">
    <div class="container topbar-inner site-header-inner">
        <?php if (!empty($layout['show_brand'])): ?>
            <a class="brand" href="<?= url('/') ?>">
                <?php if ($siteLogo !== ''): ?>
                    <img class="site-logo" src="<?= url('/thumb/' . $siteLogo . '?w=96&h=96&fit=contain') ?>" alt="">
                <?php endif; ?>
                <span><?= e($settings['institution_name'] ?? 'Заклад освіти') ?></span>
            </a>
        <?php endif; ?>
        <nav class="nav site-header-nav" aria-label="Головне меню">
            <?php foreach (($layout['links'] ?? []) as $link): ?>
                <?= $renderMenuLinks([$link]) ?>
            <?php endforeach; ?>
        </nav>
        <?php if (!empty($layout['cta_label']) && !empty($layout['cta_url'])): ?>
            <a class="button site-header-cta" href="<?= e((string) $layout['cta_url']) ?>"><?= e((string) $layout['cta_label']) ?></a>
        <?php endif; ?>
    </div>
</header>
