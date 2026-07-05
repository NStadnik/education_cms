<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class SettingsController extends \App\Controllers\AdminBaseController
{
    public function settings(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/settings', [
            'title' => 'Налаштування',
            'settings' => $this->siteSettings(),
            'globalFields' => $this->globalFields(),
        ]);
    }

    public function settingsSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $this->saveSetting('institution_name', (string) $request->input('institution_name'));
            $globalFields = json_encode($this->normalizeGlobalFields($request), JSON_UNESCAPED_UNICODE);
            $this->saveSetting('global_fields', $globalFields === false ? '[]' : $globalFields);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Налаштування збережено.']);
            }

            redirect('/admin/settings');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function templates(): Response
    {
        $this->guard('settings.manage');
        return $this->admin('admin/templates/index', [
            'title' => 'Шаблони сайту',
            'settings' => $this->siteSettings(),
            'siteTemplates' => $this->siteTemplates(),
        ]);
    }

    public function templatesSave(Request $request): Response
    {
        $this->guard('settings.manage');
        Csrf::verify();
        try {
            $siteTemplate = (string) $request->input('site_template', 'official');
            if (!array_key_exists($siteTemplate, $this->siteTemplates())) {
                $siteTemplate = 'official';
            }
            $this->saveSetting('site_template', $siteTemplate);

            if ($this->isAjax($request)) {
                return $this->json(['ok' => true, 'message' => 'Шаблон сайту збережено.']);
            }

            redirect('/admin/templates');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }
}
