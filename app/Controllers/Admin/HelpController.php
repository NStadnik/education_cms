<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AdminBaseController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminHelp;

final class HelpController extends AdminBaseController
{
    public function show(Request $request): Response
    {
        $this->guard();
        $help = new AdminHelp(Container::get('auth'));
        $query = trim((string) $request->input('q', ''));

        if ($query !== '') {
            return $this->render('admin/help/search', [
                'query' => $query,
                'results' => $help->search($query),
            ], null);
        }

        $key = trim((string) $request->input('topic', 'dashboard'));
        $topic = $help->topic($key) ?? $help->topic('dashboard');
        $anchor = preg_replace('/[^a-z0-9_-]/i', '', (string) $request->input('anchor', '')) ?: '';

        return $this->render('admin/help/topic', [
            'topic' => $topic,
            'related' => $topic ? $help->related($topic) : [],
            'anchor' => $anchor,
        ], null);
    }
}
