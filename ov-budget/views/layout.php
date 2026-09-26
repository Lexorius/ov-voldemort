<?php
/** @var string $__content */
/** @var string|null $title */
$u = current_user();
$appName = (string)setting('app_name', 'OV-Multitool');
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
  <button class="mainnav__pfeil mainnav__pfeil--links" type="button"
          data-nav-pfeil="-1" aria-label="Weiter nach links" tabindex="-1">‹</button>
  <button class="mainnav__pfeil mainnav__pfeil--rechts" type="button"
          data-nav-pfeil="1" aria-label="Weiter nach rechts" tabindex="-1">›</button>
  <div class="mainnav__inner">
    <?php foreach (nav_leiste() as $m): ?>
      <a class="mainnav__item<?= nav_active(...$m['aktiv']) ?>" href="<?= e(url($m['route'])) ?>">
        <span class="mainnav__icon"><?= e($m['icon']) ?></span><span><?= e($m['label']) ?></span></a>
    <?php endforeach; ?>
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
  <div class="footer__meta">
    <?= e($ovName) ?>
    <?= $ovName !== '' ? ' · ' : '' ?><a href="<?= e(url('neu')) ?>" title="Was ist neu?">OV-Multitool <?= e(app_version()) ?></a>
  </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($GLOBALS['ovb_push_js'])): ?>
  <script src="<?= e(asset('js/push.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['ovb_qr_js'])): ?>
  <script src="<?= e(asset('js/qrcode.js')) ?>" defer></script>
  <script src="<?= e(asset('js/qr-zeichnen.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
