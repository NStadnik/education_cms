<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\MediaMetadata;
use Throwable;

final class AdminController extends AdminBaseController
{
    public function login(): Response
    {
        return $this->render('admin/login', ['title' => 'Вхід'], 'layouts/minimal');
    }

    public function authenticate(Request $request): Response
    {
        Csrf::verify();
        if (Container::get('auth')->attempt((string) $request->input('email'), (string) $request->input('password'))) {
            redirect('/admin');
        }

        return $this->render('admin/login', ['title' => 'Вхід', 'error' => 'Невірний email або пароль.'], 'layouts/minimal');
    }

    public function logout(): Response
    {
        Csrf::verify();
        Container::get('auth')->logout();
        redirect('/');
    }

    public function dashboard(): Response
    {
        $this->guard();
        $auth = Container::get('auth');
        $visibility = [
            'pages' => $auth->can('pages.manage'),
            'news' => $auth->can('news.manage') || $auth->can('news.review') || $auth->can('news.publish'),
            'media' => $auth->can('media.manage'),
        ];
        $stats = [];
        $userId = (int) (($auth->user()['id'] ?? 0));
        $canManageAll = $auth->can('content.manage_all');
        if ($visibility['pages']) {
            $stats['pages'] = $canManageAll
                ? $this->count('pages')
                : (int) ($this->db()->fetch('select count(*) as c from pages where created_by=?', [$userId])['c'] ?? 0);
        }
        if ($visibility['news']) {
            $canSeeAllNews = $canManageAll || $auth->can('news.review') || $auth->can('news.publish');
            $stats['news'] = $canSeeAllNews
                ? $this->count('news')
                : (int) ($this->db()->fetch('select count(*) as c from news where created_by=?', [$userId])['c'] ?? 0);
        }
        if ($visibility['media']) {
            $stats['media'] = $canManageAll
                ? MediaMetadata::count()
                : (int) ($this->db()->fetch('select count(*) as c from media where uploaded_by=?', [$userId])['c'] ?? 0);
        }
        return $this->admin('admin/dashboard', ['title' => 'Панель керування', 'stats' => $stats]);
    }
}
