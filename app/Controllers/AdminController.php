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

    public function dashboard(Request $request): Response
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
        $newsPublicationTrend = [];
        $newsTrendPeriod = $request->input('news_period') === 'academic_year' ? 'academic_year' : '30_days';
        $newsTrendLabel = 'Останні 30 днів';
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

            $currentYear = (int) date('Y');
            $academicStartYear = (int) date('n') >= 9 ? $currentYear : $currentYear - 1;
            $trendStart = $newsTrendPeriod === 'academic_year'
                ? $academicStartYear . '-09-01'
                : date('Y-m-d', strtotime('-29 days'));
            $trendEnd = $newsTrendPeriod === 'academic_year' ? ($academicStartYear + 1) . '-09-01' : date('Y-m-d', strtotime('+1 day'));
            $newsTrendLabel = $newsTrendPeriod === 'academic_year'
                ? 'Навчальний рік ' . $academicStartYear . '/' . ($academicStartYear + 1)
                : 'Останні 30 днів';
            $dateExpression = $newsTrendPeriod === 'academic_year'
                ? "date_format(published_at, '%Y-%m-01')"
                : 'date(published_at)';
            $trendScope = $canSeeAllNews ? '' : ' and created_by=?';
            $trendParams = $canSeeAllNews ? [$trendStart, $trendEnd] : [$trendStart, $trendEnd, $userId];
            $trendRows = $this->db()->fetchAll(
                'select ' . $dateExpression . ' as publication_date, count(*) as c from news where status=? and published_at is not null and published_at>=? and published_at<?' . $trendScope . ' group by ' . $dateExpression . ' order by publication_date',
                array_merge(['published'], $trendParams)
            );
            $trendCounts = [];
            foreach ($trendRows as $row) {
                $trendCounts[(string) $row['publication_date']] = (int) $row['c'];
            }
            $pointCount = $newsTrendPeriod === 'academic_year' ? 12 : 30;
            for ($point = 0; $point < $pointCount; $point++) {
                $modifier = $newsTrendPeriod === 'academic_year' ? '+' . $point . ' months' : '+' . $point . ' days';
                $date = date('Y-m-d', strtotime($trendStart . ' ' . $modifier));
                $newsPublicationTrend[] = [
                    'date' => $date,
                    'count' => $trendCounts[$date] ?? 0,
                    'label' => $newsTrendPeriod === 'academic_year' ? date('m.Y', strtotime($date)) : date('d.m', strtotime($date)),
                ];
            }
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
            'newsPublicationTrend' => $newsPublicationTrend,
            'newsTrendPeriod' => $newsTrendPeriod,
            'newsTrendLabel' => $newsTrendLabel,
            'visibility' => $visibility,
        ]);
    }

    public function dashboardNewsTrend(Request $request): Response
    {
        $this->guard();
        $auth = Container::get('auth');
        if (!$auth->can('news.manage') && !$auth->can('news.review') && !$auth->can('news.publish')) {
            return new Response(json_encode(['ok' => false], JSON_UNESCAPED_UNICODE), 403, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        $period = $request->input('period') === 'academic_year' ? 'academic_year' : '30_days';
        $compare = (string) $request->input('compare', '0') === '1';
        $offset = max(-24, min(0, (int) $request->input('offset', 0)));
        $userId = (int) ($auth->user()['id'] ?? 0);
        $canSeeAll = $auth->can('content.manage_all') || $auth->can('news.review') || $auth->can('news.publish');
        $data = $this->publicationTrend($period, $userId, $canSeeAll, $compare, $offset);

        return new Response(json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function publicationTrend(string $period, int $userId, bool $canSeeAll, bool $compare, int $offset): array
    {
        $year = (int) date('Y');
        $academicYear = (int) date('n') >= 9 ? $year : $year - 1;
        if ($period === 'academic_year') {
            $academicYear += $offset;
            $start = $academicYear . '-09-01';
            $end = ($academicYear + 1) . '-09-01';
        } else {
            $end = date('Y-m-d', strtotime('+1 day ' . ($offset * 30) . ' days'));
            $start = date('Y-m-d', strtotime($end . ' -30 days'));
        }
        $previousStart = $period === 'academic_year' ? ($academicYear - 1) . '-09-01' : date('Y-m-d', strtotime($start . ' -30 days'));
        $previousEnd = $start;
        $expression = $period === 'academic_year' ? "date_format(published_at, '%Y-%m-01')" : 'date(published_at)';
        $scope = $canSeeAll ? '' : ' and created_by=?';
        $fetch = function (string $from, string $to) use ($expression, $scope, $canSeeAll, $userId): array {
            $params = $canSeeAll ? ['published', $from, $to] : ['published', $from, $to, $userId];
            $rows = $this->db()->fetchAll('select ' . $expression . ' d, count(*) c from news where status=? and published_at>=? and published_at<?' . $scope . ' group by ' . $expression, $params);
            $counts = [];
            foreach ($rows as $row) $counts[(string) $row['d']] = (int) $row['c'];
            return $counts;
        };
        $current = $fetch($start, $end);
        $previous = $compare ? $fetch($previousStart, $previousEnd) : [];
        $points = [];
        $count = $period === 'academic_year' ? 12 : 30;
        for ($i = 0; $i < $count; $i++) {
            $unit = $period === 'academic_year' ? 'months' : 'days';
            $date = date('Y-m-d', strtotime($start . ' +' . $i . ' ' . $unit));
            $previousDate = date('Y-m-d', strtotime($previousStart . ' +' . $i . ' ' . $unit));
            $points[] = ['date' => $date, 'label' => $period === 'academic_year' ? date('m.Y', strtotime($date)) : date('d.m', strtotime($date)), 'current' => $current[$date] ?? 0, 'previous' => $previous[$previousDate] ?? 0];
        }
        $total = array_sum(array_column($points, 'current'));
        $previousTotal = array_sum(array_column($points, 'previous'));
        $change = $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100, 1) : ($total > 0 ? 100 : 0);
        $label = $period === 'academic_year'
            ? 'Навчальний рік ' . $academicYear . '/' . ($academicYear + 1)
            : date('d.m.Y', strtotime($start)) . ' — ' . date('d.m.Y', strtotime($end . ' -1 day'));
        $previousLabel = $period === 'academic_year'
            ? 'Навчальний рік ' . ($academicYear - 1) . '/' . $academicYear
            : date('d.m.Y', strtotime($previousStart)) . ' — ' . date('d.m.Y', strtotime($previousEnd . ' -1 day'));
        return ['period' => $period, 'offset' => $offset, 'can_go_next' => $offset < 0, 'label' => $label, 'previous_label' => $previousLabel, 'compare' => $compare, 'points' => $points, 'total' => $total, 'previous_total' => $previousTotal, 'change' => $change];
    }
}
