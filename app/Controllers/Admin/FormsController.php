<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use RuntimeException;
use Throwable;

final class FormsController extends \App\Controllers\AdminBaseController
{
    public function index(Request $request): Response
    {
        $this->guard('forms.manage');
        $items = $this->db()->fetchAll(
            'select f.*, count(s.id) submissions_count, max(s.created_at) last_submission_at
             from forms f left join form_submissions s on s.form_id=f.id
             group by f.id order by f.updated_at desc'
        );
        return $this->admin('admin/forms/index', ['title' => 'Форми', 'items' => $items]);
    }

    public function edit(Request $request): Response
    {
        $this->guard('forms.manage');
        $id = (int) $request->input('id', 0);
        $item = $id ? $this->db()->fetch('select * from forms where id=?', [$id]) : null;
        return $this->admin('admin/forms/form', ['title' => $id ? 'Редагування форми' : 'Нова форма', 'item' => $item]);
    }

    public function save(Request $request): Response
    {
        $this->guard('forms.manage');
        Csrf::verify();
        try {
            $id = (int) $request->input('id', 0);
            $title = trim((string) $request->input('title'));
            if ($title === '') {
                throw new RuntimeException('Вкажіть назву форми.');
            }
            $fields = $this->normalizeFields((string) $request->input('fields_json', '[]'));
            if (!$fields) {
                throw new RuntimeException('Додайте хоча б одне поле.');
            }
            $slug = $this->slug((string) $request->input('slug', $title));
            $duplicate = $this->db()->fetch('select id from forms where slug=? and id<>?', [$slug, $id]);
            if ($duplicate) {
                throw new RuntimeException('Форма з такою адресою вже існує.');
            }
            $now = date('c');
            $settings = json_encode([
                'success_message' => trim((string) $request->input('success_message', 'Дякуємо! Відповідь надіслано.')),
                'submit_label' => trim((string) $request->input('submit_label', 'Надіслати')) ?: 'Надіслати',
            ], JSON_UNESCAPED_UNICODE);
            $data = [$title, $slug, trim((string) $request->input('description')), (string) $request->input('type', 'generic'), json_encode($fields, JSON_UNESCAPED_UNICODE), $settings, (string) $request->input('status', 'draft'), $now];
            if ($id) {
                $this->db()->execute('update forms set title=?,slug=?,description=?,type=?,fields_json=?,settings_json=?,status=?,updated_at=?,version=version+1 where id=?', [...$data, $id]);
            } else {
                $this->db()->execute('insert into forms (created_by,title,slug,description,type,fields_json,settings_json,status,version,created_at,updated_at) values (?,?,?,?,?,?,?,?,1,?,?)', [$this->currentUserId(), ...array_slice($data, 0, 7), $now, $now]);
                $id = (int) $this->db()->lastInsertId();
            }
            $this->audit('save', 'form', $id);
            redirect('/admin/forms');
        } catch (Throwable $e) {
            return $this->ajaxError($request, $e);
        }
    }

    public function delete(Request $request): Response
    {
        $this->guard('forms.manage');
        Csrf::verify();
        $id = (int) $request->input('id', 0);
        $this->db()->execute('delete from forms where id=?', [$id]);
        $this->audit('delete', 'form', $id);
        redirect('/admin/forms');
    }

    public function submissions(Request $request): Response
    {
        $this->guard('forms.manage');
        $formId = (int) $request->input('form_id', 0);
        $params = [];
        $where = '';
        if ($formId) { $where = ' where s.form_id=?'; $params[] = $formId; }
        $items = $this->db()->fetchAll('select s.*, f.title form_title from form_submissions s inner join forms f on f.id=s.form_id' . $where . ' order by s.created_at desc limit 500', $params);
        $forms = $this->db()->fetchAll('select id,title from forms order by title');
        return $this->admin('admin/forms/submissions', ['title' => 'Відповіді форм', 'items' => $items, 'forms' => $forms, 'formId' => $formId]);
    }

    private function normalizeFields(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) { throw new RuntimeException('Некоректний JSON полів.'); }
        $allowed = ['text','textarea','email','tel','number','date','select','radio','checkbox','consent'];
        $result = [];
        $usedIds = [];
        foreach ($decoded as $i => $field) {
            if (!is_array($field)) { continue; }
            $id = preg_replace('/[^a-z0-9_\-]/i', '_', (string) ($field['id'] ?? 'field_' . ($i + 1)));
            $id = trim((string) $id, '_-') ?: 'field_' . ($i + 1);
            $baseId = $id; $suffix = 2;
            while (isset($usedIds[$id])) { $id = $baseId . '_' . $suffix++; }
            $usedIds[$id] = true;
            $label = trim((string) ($field['label'] ?? ''));
            $type = (string) ($field['type'] ?? 'text');
            if ($id === '' || $label === '' || !in_array($type, $allowed, true)) { continue; }
            $options = [];
            foreach (($field['options'] ?? []) as $option) {
                if (is_string($option)) { $options[] = ['value' => $option, 'label' => $option]; }
                elseif (is_array($option) && trim((string) ($option['value'] ?? '')) !== '') { $options[] = ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])]; }
            }
            $result[] = ['id' => $id, 'type' => $type, 'label' => $label, 'required' => !empty($field['required']), 'options' => $options];
        }
        return $result;
    }
}
