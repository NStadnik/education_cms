<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Files;
use Throwable;

final class PagesController extends \App\Controllers\AdminBaseController
{
    public function pages(Request $request): Response
    {
        $this->guard();
        $query = trim((string) $request->input('q', ''));
        [$where, $params] = $this->pageListWhere($request, $query);
        $sort = $this->pageListOrder((string) $request->input('sort', 'order_asc'));
        $pagination = $this->pagination($request);
        $items = $this->db()->fetchAll(
            'select * from pages ' . $where . ' order by ' . $sort . ' limit ' . $pagination['limit'] . ' offset ' . $pagination['offset'],
            $params
        );
        $total = (int) ($this->db()->fetch('select count(*) as c from pages ' . $where, $params)['c'] ?? 0);

        if ($this->isAjaxRequest()) {
            return $this->listJson('admin/pages/rows', ['items' => $items], $pagination, $total);
        }

        $stats = $this->statusStats('pages');
        return $this->admin('admin/pages/index', [
            'title' => 'Сторінки',
            'items' => $items,
            'total' => $total,
            'limit' => $pagination['limit'],
            'stats' => $stats,
            'filters' => [
                'q' => $query,
                'status' => (string) $request->input('status', ''),
                'template' => (string) $request->input('template', ''),
                'sort' => (string) $request->input('sort', 'order_asc'),
            ],
            'templates' => $this->pageTemplates(),
        ]);
    }

    public function pageForm(Request $request): Response
    {
        $this->guard();
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from pages where id = ?', [$id]) : null;
        return $this->admin('admin/pages/form', ['title' => 'Сторінка', 'item' => $item, 'templates' => $this->pageTemplates()]);
    }

    public function pageSave(Request $request): Response
    {
        $this->guard('pages.manage');
        Csrf::verify();
        try {
            $now = date('c');
            $id = (int) $request->input('id', 0);
            $blocks = $this->blocksFromLayoutJson((string) $request->input('layout_json'));
            if (!$blocks) {
                $blocks = $this->blocksFromText((string) $request->input('blocks_text'));
            }
            $slug = $this->slug((string) $request->input('slug', $request->input('title')));
            $template = (string) $request->input('template', 'default');
            if (!array_key_exists($template, $this->pageTemplates())) {
                $template = 'default';
            }
            $data = [
                $request->input('title'),
                $slug,
                $request->input('excerpt'),
                $template,
                json_encode($blocks, JSON_UNESCAPED_UNICODE),
                $request->input('status', 'draft'),
                (int) $request->input('sort_order', 0),
                $now,
            ];

            if ($id) {
                $this->db()->execute('update pages set title=?, slug=?, excerpt=?, template=?, blocks_json=?, status=?, sort_order=?, updated_at=? where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into pages (title, slug, excerpt, template, blocks_json, status, sort_order, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $now]);
                $id = (int) $this->db()->lastInsertId();
            }
            $this->audit('save', 'page', $id);

            if ($this->isAjax($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => 'Сторінку збережено.',
                    'id' => $id,
                    'edit_url' => url('/admin/pages/edit?id=' . $id),
                    'view_url' => $request->input('status') === 'published' ? url($slug === 'home' ? '/' : '/page/' . $slug) : null,
                ]);
            }

            redirect('/admin/pages');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    private function blocksFromLayoutJson(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $sections = [];
        foreach ($data as $section) {
            if (!is_array($section)) {
                continue;
            }

            $rows = [];
            foreach (($section['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $columns = [];
                foreach (($row['columns'] ?? []) as $column) {
                    if (!is_array($column)) {
                        continue;
                    }

                    $cards = [];
                    foreach (($column['cards'] ?? []) as $card) {
                        if (!is_array($card)) {
                            continue;
                        }

                        $title = trim((string) ($card['title'] ?? ''));
                        $text = trim((string) ($card['text'] ?? ''));
                        $image = trim((string) ($card['image'] ?? ''));
                        $buttonText = trim((string) ($card['button_text'] ?? ''));
                        $buttonUrl = trim((string) ($card['button_url'] ?? ''));
                        if ($title === '' && $text === '' && $image === '') {
                            continue;
                        }

                        $cards[] = [
                            'type' => 'card',
                            'style' => $this->layoutChoice((string) ($card['style'] ?? 'default'), ['default', 'accent', 'plain'], 'default'),
                            'title' => $title,
                            'text' => $text,
                            'image' => $image,
                            'button_text' => $buttonText,
                            'button_url' => $buttonUrl,
                        ];
                    }

                    if ($cards) {
                        $columns[] = [
                            'width' => $this->layoutChoice((string) ($column['width'] ?? 'col-md-12'), ['col-md-12', 'col-md-8', 'col-md-6', 'col-md-4'], 'col-md-12'),
                            'cards' => $cards,
                        ];
                    }
                }

                if ($columns) {
                    $rows[] = ['columns' => $columns];
                }
            }

            if ($rows) {
                $sections[] = [
                    'type' => 'layout',
                    'title' => trim((string) ($section['title'] ?? '')),
                    'background' => $this->layoutChoice((string) ($section['background'] ?? 'default'), ['default', 'light', 'accent'], 'default'),
                    'rows' => $rows,
                ];
            }
        }

        return $sections;
    }

    private function layoutChoice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    public function pagesBulk(Request $request): Response
    {
        $this->guard('pages.manage');
        Csrf::verify();
        $ids = $this->bulkIds($request);
        $action = (string) $request->input('bulk_action', '');
        if ($ids && in_array($action, ['publish', 'draft'], true)) {
            $status = $action === 'publish' ? 'published' : 'draft';
            $this->bulkUpdateStatus('pages', $ids, $status);
            $this->audit('bulk_' . $action, 'pages', null, 'ids: ' . implode(',', $ids));
        } elseif ($ids && $action === 'delete') {
            $this->bulkDelete('pages', $ids);
            $this->audit('bulk_delete', 'pages', null, 'ids: ' . implode(',', $ids));
        }

        if ($this->isAjax($request)) {
            return $this->json($this->adminListPayload('pages', $request, 'Групову дію виконано.'));
        }

        redirect('/admin/pages');
    }

    private function pageListWhere(Request $request, string $query): array
    {
        $clauses = [];
        $params = [];

        if ($query !== '') {
            $clauses[] = '(title like ? or slug like ? or excerpt like ? or status like ?)';
            array_push($params, '%' . $query . '%', '%' . $query . '%', '%' . $query . '%', '%' . $query . '%');
        }

        $status = (string) $request->input('status', '');
        if (in_array($status, ['published', 'draft'], true)) {
            $clauses[] = 'status = ?';
            $params[] = $status;
        }

        $template = (string) $request->input('template', '');
        if ($template !== '' && array_key_exists($template, $this->pageTemplates())) {
            $clauses[] = 'template = ?';
            $params[] = $template;
        }

        return [$clauses ? 'where ' . implode(' and ', $clauses) : '', $params];
    }

    private function pageListOrder(string $sort): string
    {
        return [
            'order_asc' => 'sort_order asc, id desc',
            'order_desc' => 'sort_order desc, id desc',
            'title_asc' => 'title asc, id desc',
            'title_desc' => 'title desc, id desc',
            'updated_desc' => 'updated_at desc, id desc',
            'created_desc' => 'created_at desc, id desc',
        ][$sort] ?? 'sort_order asc, id desc';
    }
}
