<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private readonly string $path)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/site'): string
    {
        $content = $this->partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->partial($layout, array_replace($data, ['content' => $content]));
    }

    public function partial(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->path . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
