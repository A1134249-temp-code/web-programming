<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/** Google Charts loader script tag */
function charts_google_loader_tag(): string
{
    return '<script src="https://www.gstatic.com/charts/loader.js"></script>';
}

/** 將 [label => count] 轉為 Google Charts DataTable 二維陣列 */
function charts_to_rows(array $counts): array
{
    $rows = [['項目', '數量']];
    foreach ($counts as $label => $cnt) {
        $rows[] = [(string)$label, (int)$cnt];
    }
    return $rows;
}

function charts_user_project_ids(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT DISTINCT p.id
        FROM projects p
        INNER JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = :uid
        WHERE p.is_archived = 0
    ');
    $stmt->execute([':uid' => $userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function charts_project_ids_placeholder(array $projectIds): string
{
    if (!$projectIds) {
        return '0';
    }
    return implode(',', array_map('intval', $projectIds));
}

function chart_bug_status_for_projects(PDO $pdo, array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = charts_project_ids_placeholder($projectIds);
    $rows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM bugs WHERE project_id IN ({$in}) GROUP BY status")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['status']] = (int)$r['cnt'];
    }
    return $out;
}

function chart_bug_severity_for_projects(PDO $pdo, array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = charts_project_ids_placeholder($projectIds);
    $rows = $pdo->query("
        SELECT COALESCE(NULLIF(severity, ''), '未設定') AS sev, COUNT(*) AS cnt
        FROM bugs WHERE project_id IN ({$in})
        GROUP BY sev
    ")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['sev']] = (int)$r['cnt'];
    }
    return $out;
}

function chart_bug_project_ratio(PDO $pdo, array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = charts_project_ids_placeholder($projectIds);
    $rows = $pdo->query("
        SELECT p.name, COUNT(b.id) AS cnt
        FROM bugs b
        INNER JOIN projects p ON p.id = b.project_id
        WHERE b.project_id IN ({$in})
        GROUP BY p.id, p.name
        ORDER BY p.name ASC
    ")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['name']] = (int)$r['cnt'];
    }
    return $out;
}

function chart_bug_tags_for_projects(PDO $pdo, array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = charts_project_ids_placeholder($projectIds);
    $rows = $pdo->query("
        SELECT COALESCE(NULLIF(tags, ''), '未設定') AS tag, COUNT(*) AS cnt
        FROM bugs WHERE project_id IN ({$in})
        GROUP BY tag
        ORDER BY tag ASC
    ")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['tag']] = (int)$r['cnt'];
    }
    return $out;
}

function chart_member_roles_for_projects(PDO $pdo, array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = charts_project_ids_placeholder($projectIds);
    $rows = $pdo->query("
        SELECT u.role, COUNT(DISTINCT u.id) AS cnt
        FROM project_members pm
        INNER JOIN users u ON u.id = pm.user_id
        WHERE pm.project_id IN ({$in})
        GROUP BY u.role
    ")->fetchAll();
    $out = ['admin' => 0, 'pm' => 0, 'member' => 0];
    foreach ($rows as $r) {
        $role = (string)$r['role'];
        if (isset($out[$role])) {
            $out[$role] = (int)$r['cnt'];
        }
    }
    return array_filter($out, static fn(int $v): bool => $v > 0);
}

function chart_system_user_roles(PDO $pdo): array
{
    $rows = $pdo->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY role ASC")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['role']] = (int)$r['cnt'];
    }
    return $out;
}

function chart_daily_unique_logins(PDO $pdo, int $days = 7): array
{
    $days = max(1, min(30, $days));
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $out[$date] = 0;
    }

    $stmt = $pdo->prepare('
        SELECT DATE(created_at) AS d, COUNT(DISTINCT user_id) AS cnt
        FROM action_logs
        WHERE action = :action
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
    ');
    $stmt->execute([':action' => 'User logged in', ':days' => $days - 1]);
    foreach ($stmt->fetchAll() as $r) {
        $d = (string)$r['d'];
        if (array_key_exists($d, $out)) {
            $out[$d] = (int)$r['cnt'];
        }
    }

    return $out;
}

function charts_render_pie(string $elementId, array $counts, string $title): void
{
    $rows = charts_to_rows($counts ?: ['無資料' => 0]);
    $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    ?>
<div class="chart-box">
  <div id="<?= e($elementId) ?>" class="chart-canvas"></div>
</div>
<script>
google.charts.setOnLoadCallback(function () {
  var data = google.visualization.arrayToDataTable(<?= $json ?>);
  var chart = new google.visualization.PieChart(document.getElementById(<?= json_encode($elementId) ?>));
  chart.draw(data, { title: <?= json_encode($title, JSON_UNESCAPED_UNICODE) ?>, legend: { position: 'bottom' }, chartArea: { width: '90%', height: '70%' } });
});
</script>
    <?php
}

function charts_render_line(string $elementId, array $counts, string $title): void
{
    $rows = [['日期', '活躍登入人數']];
    foreach ($counts as $label => $cnt) {
        $rows[] = [(string)$label, (int)$cnt];
    }
    $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    ?>
<div class="chart-box chart-box-wide">
  <div id="<?= e($elementId) ?>" class="chart-canvas"></div>
</div>
<script>
google.charts.setOnLoadCallback(function () {
  var data = google.visualization.arrayToDataTable(<?= $json ?>);
  var chart = new google.visualization.LineChart(document.getElementById(<?= json_encode($elementId) ?>));
  chart.draw(data, { title: <?= json_encode($title, JSON_UNESCAPED_UNICODE) ?>, legend: { position: 'none' }, chartArea: { width: '85%', height: '70%' } });
});
</script>
    <?php
}

function charts_render_project_dashboard(PDO $pdo, array $projectIds, string $idPrefix): void
{
    if (!$projectIds) {
        echo '<p class="muted">尚無專案資料可顯示圖表。</p>';
        return;
    }
    ?>
<div class="charts-row">
  <?php
    charts_render_pie($idPrefix . '_status', chart_bug_status_for_projects($pdo, $projectIds), 'Bug 處理狀況');
    charts_render_pie($idPrefix . '_severity', chart_bug_severity_for_projects($pdo, $projectIds), 'Bug 緊急程度');
    charts_render_pie($idPrefix . '_project', chart_bug_project_ratio($pdo, $projectIds), 'Bug 所屬專案比例');
    charts_render_pie($idPrefix . '_tags', chart_bug_tags_for_projects($pdo, $projectIds), 'Bug 標籤比例');
    charts_render_pie($idPrefix . '_roles', chart_member_roles_for_projects($pdo, $projectIds), '專案成員身分比例');
    ?>
</div>
    <?php
}

function charts_render_admin_dashboard(PDO $pdo): void
{
    ?>
<div class="charts-row">
  <?php
    charts_render_pie('admin_roles', chart_system_user_roles($pdo), '系統帳號身分統計');
    charts_render_line('admin_logins', chart_daily_unique_logins($pdo, 7), '近七日活躍登入人數');
    ?>
</div>
    <?php
}

function charts_init_script(): string
{
    return '<script>google.charts.load("current", {packages:["corechart"]});</script>';
}
