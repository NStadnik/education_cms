<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\Installer;
use Throwable;

final class ErrorController extends BaseController
{
    public static function response(int $status = 404, string $message = ''): Response
    {
        return (new self())->show($status, $message);
    }

    public function show(int $status = 404, string $message = ''): Response
    {
        $status = $this->normalizeStatus($status);
        $copy = $this->copy($status, $message);
        $settings = $this->safeSettings();
        $layout = Installer::installed() && $settings ? 'layouts/site' : 'layouts/minimal';

        return new Response($this->view()->render('public/error', [
            'title' => $copy['title'],
            'settings' => $settings,
            'menu' => [],
            'status' => $status,
            'label' => $copy['label'],
            'headline' => $copy['headline'],
            'message' => $copy['message'],
            'primaryUrl' => url('/'),
            'primaryLabel' => 'На головну',
            'secondaryUrl' => url('/news'),
            'secondaryLabel' => 'До новин',
        ], $layout), $status);
    }

    private function safeSettings(): array
    {
        if (!Installer::installed()) {
            return [];
        }

        try {
            return $this->siteSettings();
        } catch (Throwable) {
            return [];
        }
    }

    private function normalizeStatus(int $status): int
    {
        return in_array($status, [400, 401, 403, 404, 405, 419, 422, 429, 500, 503], true) ? $status : 500;
    }

    private function copy(int $status, string $message): array
    {
        $defaults = [
            400 => ['label' => 'Некоректний запит', 'headline' => 'Запит не вдалося обробити', 'message' => 'Спробуйте оновити сторінку або поверніться на головну.'],
            401 => ['label' => 'Потрібен вхід', 'headline' => 'Потрібна авторизація', 'message' => 'Увійдіть в адмін-панель, щоб продовжити роботу.'],
            403 => ['label' => 'Доступ закрито', 'headline' => 'У вас немає доступу до цієї сторінки', 'message' => 'Перевірте права користувача або поверніться до доступних розділів.'],
            404 => ['label' => 'Сторінку не знайдено', 'headline' => 'Такої сторінки тут немає', 'message' => 'Адреса могла змінитися, або матеріал ще не опублікований.'],
            405 => ['label' => 'Метод не підтримується', 'headline' => 'Цю дію не можна виконати таким способом', 'message' => 'Поверніться назад і повторіть дію зі сторінки сайту.'],
            419 => ['label' => 'Сесію завершено', 'headline' => 'Сторінка очікувала занадто довго', 'message' => 'Оновіть сторінку і повторіть дію.'],
            422 => ['label' => 'Помилка даних', 'headline' => 'Дані потребують перевірки', 'message' => 'Перевірте заповнені поля та спробуйте ще раз.'],
            429 => ['label' => 'Забагато запитів', 'headline' => 'Трохи забагато активності', 'message' => 'Зачекайте хвилину і спробуйте повторити запит.'],
            500 => ['label' => 'Помилка сервера', 'headline' => 'Щось пішло не так', 'message' => 'Ми не змогли виконати запит. Спробуйте пізніше або повідомте адміністратора.'],
            503 => ['label' => 'Сервіс недоступний', 'headline' => 'Сайт тимчасово недоступний', 'message' => 'Тривають технічні роботи або сервіс перевантажений.'],
        ];

        $copy = $defaults[$status] ?? $defaults[500];
        if (trim($message) !== '') {
            $copy['message'] = trim($message);
        }
        $copy['title'] = $status . ' - ' . $copy['label'];

        return $copy;
    }
}
