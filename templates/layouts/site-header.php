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
    $renderMenuLabel = static function (string $label, string $icon, string $fallback): string {
        $icon = trim($icon);
        $icon = preg_replace('/^mdi\s+/', '', $icon) ?? '';
        $icon = preg_replace('/^mdi-/', '', $icon) ?? '';
        $iconClass = $icon !== '' && preg_match('/^[a-z0-9-]+$/i', $icon) ? 'mdi-' . strtolower($icon) : '';
        return ($iconClass !== '' ? '<span class="mdi ' . e($iconClass) . '" aria-hidden="true"></span>' : '') . '<span>' . e($label ?: $fallback) . '</span>';
    };
    $renderMenuLinks = static function (array $links) use (&$renderMenuLinks, $renderMenuLabel): string {
        $html = '';
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? '#')) ?: '#';
            $children = is_array($link['children'] ?? null) ? $link['children'] : [];
            $columns = is_array($link['columns'] ?? null) ? $link['columns'] : [];
            $type = (string) ($link['type'] ?? 'link');
            $icon = (string) ($link['icon'] ?? '');
            if ($label === '' && !$children && !$columns) {
                continue;
            }
            $html .= '<span class="site-menu-item">';
            if ($type === 'section') {
                $html .= '<span class="site-menu-section"' . (($children || $columns) ? ' tabindex="0"' : '') . '>' . $renderMenuLabel($label, $icon, 'Секція') . '</span>';
            } else {
                $html .= '<a href="' . e($url) . '">' . $renderMenuLabel($label, $icon, 'Пункт меню') . '</a>';
            }
            if ($children || $columns) {
                $submenu = '';
                if ($columns) {
                    $submenu .= '<span class="site-menu-columns">';
                    foreach ($columns as $column) {
                        if (!is_array($column)) {
                            continue;
                        }
                        $columnTitle = trim((string) ($column['title'] ?? ''));
                        $columnChildren = is_array($column['children'] ?? null) ? $column['children'] : [];
                        if ($columnTitle === '' && !$columnChildren) {
                            continue;
                        }
                        $submenu .= '<span class="site-menu-column">';
                        if ($columnTitle !== '') {
                            $submenu .= '<span class="site-menu-column-title">' . e($columnTitle) . '</span>';
                        }
                        $submenu .= $renderMenuLinks($columnChildren);
                        $submenu .= '</span>';
                    }
                    $submenu .= '</span>';
                }
                $submenu .= $renderMenuLinks($children);
                $html .= '<span class="site-submenu">' . $submenu . '</span>';
            }
            $html .= '</span>';
        }

        return $html;
    };
?>
<header class="topbar site-header site-header-<?= e($variant) ?>" data-site-header>
    <div class="container topbar-inner site-header-inner">
        <?php if (!empty($layout['show_brand'])): ?>
            <a class="brand" href="<?= url('/') ?>">
                <?php if ($siteLogo !== ''): ?>
                    <img class="site-logo" src="<?= url('/thumb/' . $siteLogo . '?w=96&h=96&fit=contain') ?>" alt="">
                <?php endif; ?>
                <span><?= e($settings['institution_name'] ?? 'Заклад освіти') ?></span>
            </a>
        <?php endif; ?>
        <button class="site-menu-toggle" type="button" data-site-menu-toggle aria-expanded="false" aria-controls="siteMenuPanel">
            <span class="site-menu-toggle-bars" aria-hidden="true"></span>
            <span>Меню</span>
        </button>
        <div class="site-header-menu-panel" id="siteMenuPanel" data-site-menu-panel>
            <nav class="nav site-header-nav" aria-label="Головне меню">
                <?php foreach (($layout['links'] ?? []) as $link): ?>
                    <?= $renderMenuLinks([$link]) ?>
                <?php endforeach; ?>
            </nav>
            <?php if (!empty($layout['cta_label']) && !empty($layout['cta_url'])): ?>
                <a class="button site-header-cta" href="<?= e((string) $layout['cta_url']) ?>"><?= e((string) $layout['cta_label']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>
