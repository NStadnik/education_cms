<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;
use Throwable;

final class InstallController extends BaseController
{
    public function show(): Response
    {
        if (Installer::installed()) {
            redirect('/');
        }

        return $this->render('install/show', [
            'title' => 'Встановлення',
            'requirements' => Installer::requirements(),
            'old' => [],
        ], 'layouts/minimal');
    }

    public function store(Request $request): Response
    {
        if (Installer::installed()) {
            redirect('/');
        }
        Csrf::verify();

        if (!$request->input('db_name') || !$request->input('db_user') || !$request->input('institution_name') || !$request->input('admin_email') || !$request->input('admin_password')) {
            return $this->render('install/show', [
                'title' => 'Встановлення',
                'requirements' => Installer::requirements(),
                'error' => 'Заповніть назву БД, користувача БД, назву закладу, email і пароль адміністратора.',
                'old' => $request->post,
            ], 'layouts/minimal');
        }

        try {
            (new Installer())->install($request->post);
            redirect('/admin/login');
        } catch (Throwable $e) {
            return $this->render('install/show', [
                'title' => 'Встановлення',
                'requirements' => Installer::requirements(),
                'error' => $e->getMessage(),
                'old' => $request->post,
            ], 'layouts/minimal');
        }
    }
}
