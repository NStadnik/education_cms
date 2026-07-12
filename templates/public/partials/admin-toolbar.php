<?php
$actions = is_array($toolbar['actions'] ?? null) ? $toolbar['actions'] : [];
$user = is_array($toolbar['user'] ?? null) ? $toolbar['user'] : [];
?>
<aside class="site-admin-toolbar" aria-label="Панель керування сайтом">
    <div class="site-admin-toolbar-inner">
        <a class="site-admin-toolbar-brand" href="<?= url('/admin') ?>" title="Відкрити панель керування">
            <span class="mdi mdi-shield-account-outline" aria-hidden="true"></span>
            <span>Керування</span>
        </a>
        <nav class="site-admin-toolbar-nav" aria-label="Розділи панелі керування">
            <?php if (!empty($toolbar['canPages'])): ?>
                <a href="<?= url('/admin/pages') ?>"><span class="mdi mdi-file-document-multiple-outline" aria-hidden="true"></span><span>Сторінки</span></a>
            <?php endif; ?>
            <?php if (!empty($toolbar['canNews'])): ?>
                <a href="<?= url('/admin/news') ?>"><span class="mdi mdi-newspaper-variant-outline" aria-hidden="true"></span><span>Новини</span></a>
            <?php endif; ?>
            <?php if (!empty($toolbar['canCreateNews'])): ?>
                <a href="<?= url('/admin/news/edit') ?>"><span class="mdi mdi-plus-circle-outline" aria-hidden="true"></span><span>Додати новину</span></a>
            <?php endif; ?>
        </nav>
        <?php if ($actions): ?>
            <div class="site-admin-toolbar-context">
                <?php foreach ($actions as $action): ?>
                    <a class="<?= !empty($action['primary']) ? 'is-primary' : '' ?>" href="<?= e((string) $action['url']) ?>">
                        <span class="mdi <?= e((string) ($action['icon'] ?? 'mdi-pencil-outline')) ?>" aria-hidden="true"></span>
                        <span><?= e((string) $action['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="site-admin-toolbar-account">
            <span class="site-admin-toolbar-user" title="<?= e((string) ($user['name'] ?? 'Користувач')) ?>">
                <span class="mdi mdi-account-circle-outline" aria-hidden="true"></span>
                <span><?= e((string) ($user['name'] ?? 'Користувач')) ?></span>
            </span>
            <form method="post" action="<?= url('/admin/logout') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" title="Вийти"><span class="mdi mdi-logout" aria-hidden="true"></span><span class="visually-hidden">Вийти</span></button>
            </form>
        </div>
    </div>
</aside>
