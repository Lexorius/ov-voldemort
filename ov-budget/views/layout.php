<?php
/** @var string $__content */
/** @var string|null $title */
$u = current_user();
$appName = (string)setting('app_name', 'OV-Budget');
$ovName  = (string)setting('ov_name', '');
$accent  = (string)setting('theme_color', '#003399');
$pageTitle = ($title ?? '') !== '' ? $title . ' · ' . $appName : $appName;
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?= e($accent) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent: <?= e($accent) ?>;}</style>
</head>
<body>

<header class="topbar">
  <a class="topbar__brand" href="<?= e(url('dashboard')) ?>">
    <span class="topbar__logo" aria-hidden="true">THW</span>
    <span class="topbar__names">
      <strong><?= e($appName) ?></strong>
      <?php if ($ovName !== ''): ?><small><?= e(setting('ov_kurz', $ovName)) ?></small><?php endif; ?>
    </span>
  </a>
  <?php if ($u): ?>
    <div class="topbar__right">
      <a class="topbar__user" href="<?= e(url('profile')) ?>" title="Profil">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['display_name'] ?: $u['username'], 0, 2))) ?></span>
        <span class="topbar__username"><?= e($u['display_name'] ?: $u['username']) ?></span>
      </a>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('logout')) ?>">Abmelden</a>
    </div>
  <?php endif; ?>
</header>

<?php if ($u): ?>
<nav class="mainnav" aria-label="Hauptnavigation">
  <div class="mainnav__inner">
    <a class="mainnav__item<?= nav_active('dashboard') ?>" href="<?= e(url('dashboard')) ?>">
      <span class="mainnav__icon">▦</span><span>Übersicht</span></a>
    <a class="mainnav__item<?= nav_active('wishes', 'wish', 'wish_edit') ?>" href="<?= e(url('wishes')) ?>">
      <span class="mainnav__icon">★</span><span><?= e(setting('wunsch_modul_name', 'Wünsch dir was')) ?></span></a>
    <a class="mainnav__item<?= nav_active('todos', 'todo', 'todo_edit') ?>" href="<?= e(url('todos')) ?>">
      <span class="mainnav__icon">☑</span><span><?= e(setting('todo_modul_name', 'Aufgaben')) ?></span></a>
    <a class="mainnav__item<?= nav_active('budget', 'budget_edit', 'budget_year_edit', 'expenses', 'expense_edit') ?>" href="<?= e(url('budget')) ?>">
      <span class="mainnav__icon">€</span><span>Budget</span></a>
    <?php if (can('admin')): ?>
      <a class="mainnav__item<?= nav_active('admin', 'admin_users', 'admin_user_edit', 'admin_lists', 'admin_list_edit', 'admin_settings', 'admin_divera', 'admin_divera_form', 'admin_log') ?>" href="<?= e(url('admin')) ?>">
        <span class="mainnav__icon">⚙</span><span>Verwaltung</span></a>
    <?php endif; ?>
  </div>
</nav>
<?php endif; ?>

<main class="page">
  <?php foreach (flash_take() as $f): ?>
    <div class="alert alert--<?= e($f['type']) ?>"><?= $f['msg'] ?></div>
  <?php endforeach; ?>
  <?= $__content ?>
</main>

<footer class="footer">
  <div><?= nl2br(e((string)setting('footer_text', ''))) ?></div>
  <div class="footer__meta"><?= e($ovName) ?></div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
