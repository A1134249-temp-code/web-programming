<?php
declare(strict_types=1);

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

function layout_head(string $title, array $extraHead = []): void
{
    ?>
<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/wireframe.css')) ?>" />
  <?php foreach ($extraHead as $line): ?>
    <?= $line ?>
  <?php endforeach; ?>
</head>
<body>
    <?php
}

function layout_nav(?array $user): void
{
    ?>
<header class="site-header">
  <h1>Issue Tracker</h1>
  <nav class="site-nav">
    <?php if ($user === null): ?>
      <a href="<?= e(url('login.php')) ?>">登入</a>
      <?php if (registration_allowed()): ?>
        <a href="<?= e(url('register.php')) ?>">註冊</a>
      <?php endif; ?>
    <?php else: ?>
      
      <?php if (($user['username'] ?? '') === 'testAdmin'): ?>
        <span style="color: #ef4444; font-weight: bold; margin-right: 15px;">⚠️ 系統引導模式</span>
        <form method="post" action="<?= e(url('index.php')) ?>" style="display:inline; margin-left: 10px;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="logout" />
          <button type="submit" style="background: none; border: none; padding: 0; color: #000000; text-decoration: underline; cursor: pointer; font-size: inherit;">登出</button>
        </form>
      
      <?php else: ?>
        <?php layout_notifications_bell($user); ?>
        <a href="<?= e(url('index.php')) ?>">首頁</a>
        <?php
        $role = (string)($user['role'] ?? '');
        if ($role === 'admin'): ?>
          <a href="<?= e(url('admin.php')) ?>">系統管理</a>
          <a href="<?= e(url('admin_logs.php')) ?>">稽核日誌</a>
        <?php endif;
        if (in_array($role, ['pm', 'member'], true)): ?>
          <a href="<?= e(url('projects.php')) ?>">我的專案</a>
          <a href="<?= e(url('bugs.php')) ?>">Bug 搜尋</a>
          <a href="<?= e(url('bug_new.php')) ?>">提報 Bug</a>
        <?php endif;
        if ($role === 'pm'): ?>
          <a href="<?= e(url('pm.php')) ?>">PM 管理</a>
        <?php endif; ?>
        <a href="<?= e(url('profile.php')) ?>">個人資料</a>
        <span class="muted"><?= e((string)($user['username'] ?? '')) ?> (<?= e($role) ?>)</span>
        <form method="post" action="<?= e(url('index.php')) ?>" style="display:inline; margin-left: 10px;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="logout" />
          <button type="submit" style="background: none; border: none; padding: 0; color: #000000; text-decoration: underline; cursor: pointer; font-size: inherit;">登出</button>
        </form>
      <?php endif; ?>

    <?php endif; ?>
  </nav>
</header>
    <?php
}

function layout_notifications_bell(array $user): void
{
    require_once __DIR__ . '/notifications.php';
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) {
        return;
    }

    $unread = notification_unread_count($uid);
    $items = notification_fetch_recent($uid, 8);
    ?>
<details class="notify-bell">
  <summary>
    <span class="bell-icon" aria-hidden="true">&#128276;</span>
    <?php if ($unread > 0): ?><span class="notify-dot" title="未讀 <?= e((string)$unread) ?>"></span><?php endif; ?>
  </summary>
  <div class="notify-dropdown panel">
    <div class="notify-dropdown-head">
      <strong>通知</strong>
      <?php if ($unread > 0): ?>
        <form class="inline-form" method="post" action="<?= e(url('notifications.php')) ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="read_all" />
          <button type="submit" class="btn-small">全部已讀</button>
        </form>
      <?php endif; ?>
    </div>
    <?php if (!$items): ?>
      <p class="muted">尚無通知</p>
    <?php else: ?>
      <ul class="notify-list">
        <?php foreach ($items as $n): ?>
          <li class="<?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>">
            <div class="notify-msg"><?= e((string)$n['message']) ?></div>
            <div class="notify-meta muted"><?= e((string)$n['created_at']) ?></div>
            <?php if ((int)$n['is_read'] === 0): ?>
              <form method="post" action="<?= e(url('notifications.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="action" value="read_one" />
                <input type="hidden" name="id" value="<?= e((string)$n['id']) ?>" />
                <button type="submit" class="btn-small">標記已讀</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p><a href="<?= e(url('notifications.php')) ?>">查看全部</a></p>
  </div>
</details>
    <?php
}

function layout_start(string $title, ?array $user = null, array $extraHead = []): void
{
    if ($user !== null) {
        auth_require_fresh_password();
    }
    layout_head($title, $extraHead);
    echo '<div class="wrap">';
    layout_nav($user);
}

function layout_end(): void
{
    echo '</div></body></html>';
}

function flash_set(string $type, string $message): void
{
    auth_init();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    auth_init();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (!is_array($flash)) {
        return;
    }
    $type = (string)($flash['type'] ?? 'info');
    $message = (string)($flash['message'] ?? '');
    if ($message === '') {
        return;
    }
    echo '<div class="msg ' . e($type) . '">' . e($message) . '</div>';
}
