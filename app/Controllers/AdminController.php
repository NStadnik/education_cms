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
            'forms' => $auth->can('forms.manage'),
            'activity' => $auth->can('content.manage_all') || $auth->can('users.manage'),
        ];
        $stats = [];
        $contentStatuses = [];
        $formsStats = [];
        $recentSubmissions = [];
        $recentActivity = [];
        $userId = (int) (($auth->user()['id'] ?? 0));
        $canManageAll = $auth->can('content.manage_all');
        if ($visibility['pages']) {
            $scope = $canManageAll ? '' : ' where created_by=?';
            $params = $canManageAll ? [] : [$userId];
            $rows = $this->db()->fetchAll('select status, count(*) as c from pages' . $scope . ' group by status', $params);
            $contentStatuses['pages'] = array_fill_keys(['published', 'draft'], 0);
            foreach ($rows as $row) {
                $contentStatuses['pages'][(string) $row['status']] = (int) $row['c'];
            }
            $stats['pages'] = array_sum($contentStatuses['pages']);
        }
        if ($visibility['news']) {
            $canSeeAllNews = $canManageAll || $auth->can('news.review') || $auth->can('news.publish');
            $scope = $canSeeAllNews ? '' : ' where created_by=?';
            $params = $canSeeAllNews ? [] : [$userId];
            $rows = $this->db()->fetchAll('select status, count(*) as c from news' . $scope . ' group by status', $params);
            $contentStatuses['news'] = array_fill_keys(['published', 'draft', 'pending_review', 'changes_requested'], 0);
            foreach ($rows as $row) {
                $contentStatuses['news'][(string) $row['status']] = (int) $row['c'];
            }
            $stats['news'] = array_sum($contentStatuses['news']);
        }
        if ($visibility['media']) {
            $stats['media'] = $canManageAll
                ? MediaMetadata::count()
                : (int) ($this->db()->fetch('select count(*) as c from media where uploaded_by=?', [$userId])['c'] ?? 0);
        }
        if ($visibility['forms']) {
            $formsStats = $this->db()->fetch(
                'select count(distinct f.id) as total, count(distinct case when f.status=? then f.id end) as published, count(s.id) as submissions, sum(case when s.status=? then 1 else 0 end) as new_submissions, sum(case when s.created_at>=? then 1 else 0 end) as recent_submissions from forms f left join form_submissions s on s.form_id=f.id',
                ['published', 'new', date('c', time() - 604800)]
            ) ?? [];
            $recentSubmissions = $this->db()->fetchAll(
                'select s.id, s.created_at, s.status, s.submitter_email, f.id as form_id, f.title as form_title from form_submissions s inner join forms f on f.id=s.form_id order by s.created_at desc limit 5'
            );
        }
        if ($visibility['activity']) {
            $recentActivity = $this->db()->fetchAll(
                'select a.action, a.entity, a.entity_id, a.details, a.created_at, coalesce(u.name, ?) as user_name from audit_logs a left join users u on u.id=a.user_id order by a.created_at desc, a.id desc limit 8',
                ['Система']
            );
        }

        return $this->admin('admin/dashboard', [
            'title' => 'Панель керування',
            'stats' => $stats,
            'contentStatuses' => $contentStatuses,
            'formsStats' => $formsStats,
            'recentSubmissions' => $recentSubmissions,
            'recentActivity' => $recentActivity,
            'visibility' => $visibility,
        ]);
    }
}
