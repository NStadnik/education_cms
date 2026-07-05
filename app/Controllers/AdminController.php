<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
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
        $stats = [
            'pages' => $this->count('pages'),
            'news' => $this->count('news'),
            'documents' => $this->count('documents'),
            'media' => count(Files::all()),
            'publicFilled' => $this->db()->fetch("select count(distinct public_info_section_id) as c from documents where status = 'published' and public_info_section_id is not null")['c'] ?? 0,
            'publicTotal' => $this->count('public_info_sections'),
        ];
        return $this->admin('admin/dashboard', ['title' => 'Панель керування', 'stats' => $stats]);
    }
}
