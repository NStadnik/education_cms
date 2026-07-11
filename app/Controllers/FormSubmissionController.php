<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\Notifications;

final class FormSubmissionController extends BaseController
{
    public function submit(Request $request, array $params): Response
    {
        Csrf::verify();
        $id = (int) ($params['id'] ?? 0);
        $form = $this->db()->fetch('select * from forms where id=? and status=?', [$id, 'published']);
        if (!$form) { return $this->json(['ok' => false, 'message' => 'Форма недоступна.'], 404); }
        $nowTime = time();
        if ((!empty($form['starts_at']) && strtotime((string) $form['starts_at']) > $nowTime) || (!empty($form['ends_at']) && strtotime((string) $form['ends_at']) < $nowTime)) {
            return $this->json(['ok' => false, 'message' => 'Приймання відповідей завершено.'], 410);
        }
        if (trim((string) $request->input('_website', '')) !== '') { return $this->json(['ok' => true, 'message' => 'Дякуємо!']); }
        $fields = json_decode((string) $form['fields_json'], true) ?: [];
        $answers = []; $errors = []; $email = null;
        foreach ($fields as $field) {
            $key = (string) ($field['id'] ?? '');
            $value = $request->input('field_' . $key, '');
            if (is_array($value)) { $value = array_values(array_map('strval', $value)); }
            else { $value = trim((string) $value); }
            if (!empty($field['required']) && ($value === '' || $value === [])) { $errors[$key] = 'Обов’язкове поле.'; }
            if (($field['type'] ?? '') === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[$key] = 'Вкажіть коректний email.'; }
            $answers[$key] = $value;
            if (($field['type'] ?? '') === 'email' && is_string($value)) { $email = $value; }
        }
        if ($errors) { return $this->json(['ok' => false, 'message' => 'Перевірте заповнення полів.', 'errors' => $errors], 422); }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = $ip === '' ? null : hash('sha256', $ip . Csrf::token());
        if ($ipHash) {
            $recent = $this->db()->fetch('select count(*) c from form_submissions where form_id=? and ip_hash=? and created_at>=?', [$id, $ipHash, date('c', time() - 600)]);
            if ((int) ($recent['c'] ?? 0) >= 5) { return $this->json(['ok' => false, 'message' => 'Забагато спроб. Спробуйте пізніше.'], 429); }
        }
        $context = ['page_url' => (string) $request->input('_page_url', ''), 'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? '')];
        $now = date('c');
        $this->db()->execute('insert into form_submissions (form_id,form_version,answers_json,schema_snapshot_json,context_json,status,submitter_email,ip_hash,user_agent,created_at,updated_at) values (?,?,?,?,?,?,?,?,?,?,?)', [
            $id, (int) $form['version'], json_encode($answers, JSON_UNESCAPED_UNICODE), $form['fields_json'], json_encode($context, JSON_UNESCAPED_UNICODE), 'new', $email,
            $ipHash, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), $now, $now
        ]);
        Notifications::formSubmission($form, $fields, $answers);
        $settings = json_decode((string) $form['settings_json'], true) ?: [];
        return $this->json(['ok' => true, 'message' => (string) ($settings['success_message'] ?? 'Дякуємо! Відповідь надіслано.')]);
    }
}
