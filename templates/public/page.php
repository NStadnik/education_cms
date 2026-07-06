<?php
    $template = (string) ($page['template'] ?? 'default');
    $allowedTemplates = ['default', 'wide'];
    if (!in_array($template, $allowedTemplates, true)) {
        $template = 'default';
    }
?>
<?= $this->partial('public/page-templates/' . $template, [
    'page' => $page,
    'blocks' => $blocks,
    'latestNews' => $latestNews,
]) ?>
