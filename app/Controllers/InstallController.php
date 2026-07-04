<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Installer;

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
        ], 'layouts/minimal');
    }

    public function store(Request $request): Response
    {
        if (Installer::installed()) {
            redirect('/');
        }
        Csrf::verify();

        if (!$request->input('admin_email') || !$request->input('admin_password')) {
            return $this->render('install/show', [
                'title' => 'Встановлення',
                'requirements' => Installer::requirements(),
                'error' => 'Вкажіть email і пароль адміністратора.',
            ], 'layouts/minimal');
        }

        (new Installer())->install($request->post);
        redirect('/admin/login');
    }
}
