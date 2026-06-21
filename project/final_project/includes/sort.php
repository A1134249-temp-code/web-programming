<?php
declare(strict_types=1);

/**
 * 列表排序 — 共用 ORDER BY 片段（僅白名單，避免 SQL 注入）。
 */

function bug_list_sort_clause(string $sort): string
{
    return match ($sort) {
        'project' => 'p.name ASC, b.title ASC',
        'severity' => 'b.severity ASC, b.title ASC',
        'status' => 'b.status ASC, b.title ASC',
        'created' => 'b.created_at DESC, b.title ASC',
        default => 'b.id DESC',
    };
}

function bug_list_sort_options(): array
{
    return [
        '' => '預設（最新 ID）',
        'project' => '專案名稱',
        'severity' => '緊急程度',
        'status' => '狀態',
        'created' => '建立時間',
    ];
}

function project_list_sort_clause(string $sort): string
{
    return match ($sort) {
        'name' => 'p.name ASC, p.id ASC',
        'id' => 'p.id ASC',
        default => 'p.id DESC',
    };
}

function project_list_sort_options(): array
{
    return [
        '' => '預設（最新 ID）',
        'name' => '專案名稱',
        'id' => '專案 ID',
    ];
}

function user_list_sort_clause(string $sort): string
{
    return match ($sort) {
        'username' => 'username ASC, id ASC',
        'role' => 'role ASC, username ASC',
        'email' => 'email ASC, username ASC',
        default => 'id ASC',
    };
}

function user_list_sort_options(): array
{
    return [
        '' => '預設（ID）',
        'username' => '帳號',
        'role' => '角色',
        'email' => 'Email',
    ];
}

function generic_name_sort_clause(string $sort, string $nameColumn = 'name', string $idColumn = 'id'): string
{
    return match ($sort) {
        'name' => "{$nameColumn} ASC, {$idColumn} ASC",
        default => "{$idColumn} DESC",
    };
}

function render_sort_form(string $action, array $options, string $currentSort, array $hiddenFields = [], string $sortParam = 'sort'): void
{
    ?>
<form class="inline-form sort-form" method="get" action="<?= e(url($action)) ?>">
  <label class="sort-label">排序</label>
  <select name="<?= e($sortParam) ?>" onchange="this.form.submit()">
    <?php foreach ($options as $value => $label): ?>
      <option value="<?= e((string)$value) ?>" <?= $currentSort === (string)$value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <?php foreach ($hiddenFields as $name => $value): ?>
    <input type="hidden" name="<?= e((string)$name) ?>" value="<?= e((string)$value) ?>" />
  <?php endforeach; ?>
</form>
    <?php
}
