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
<?php /* Als App: Manifest (mit Sitzung, wegen des Ingress), Symbole für Reiter, Startbildschirm und iPhone */ ?>
<link rel="manifest" href="<?= e(base_path()) ?>/manifest.php" crossorigin="use-credentials">
<link rel="icon" href="<?= e(asset('icon/app.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('icon/app-192.png')) ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?= e(asset('icon/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-title" content="<?= e((string)setting('ov_kurz', $appName)) ?>">
<meta name="mobile-web-app-capable" content="yes">
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
  <?php $navBaum = nav_baum_fuer(); ?>
  <div class="mainnav__inner">
    <?php foreach ($navBaum as $knoten): ?>
      <?php if ($knoten['typ'] === 'gruppe'): ?>
        <?php /* Ohne JavaScript führt die Gruppe zu ihrem ersten Modul; mit klappt sie ihre Module auf */ ?>
        <a class="mainnav__item mainnav__item--gruppe<?= $knoten['aktiv'] ? ' is-active' : '' ?>" href="<?= e(url($knoten['route'])) ?>"
           data-nav-gruppe="<?= e($knoten['id']) ?>" aria-controls="nav-<?= e($knoten['id']) ?>" aria-expanded="<?= $knoten['aktiv'] ? 'true' : 'false' ?>">
          <span class="mainnav__icon"><?= e($knoten['icon']) ?></span><span><?= e($knoten['name']) ?> <span class="mainnav__winkel" aria-hidden="true">▾</span></span></a>
      <?php else: $m = $knoten['m']; ?>
        <a class="mainnav__item<?= $knoten['aktiv'] ? ' is-active' : '' ?>" href="<?= e(url($m['route'])) ?>">
          <span class="mainnav__icon"><?= e($m['icon']) ?></span><span><?= e($m['label']) ?></span></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php foreach ($navBaum as $knoten): if ($knoten['typ'] !== 'gruppe') { continue; } ?>
    <div class="mainnav__panel" id="nav-<?= e($knoten['id']) ?>" data-nav-panel="<?= e($knoten['id']) ?>"<?= $knoten['aktiv'] ? ' data-nav-aktiv' : ' hidden' ?>
         role="group" aria-label="<?= e($knoten['name']) ?>">
      <div class="mainnav__panel-kopf">
        <strong><span class="mainnav__icon"><?= e($knoten['icon']) ?></span> <?= e($knoten['name']) ?></strong>
        <button class="mainnav__zu" type="button" data-nav-zu aria-label="Menü schließen">✕</button>
      </div>
      <div class="mainnav__panel-inner">
        <?php foreach ($knoten['module'] as $eintrag): $m = $eintrag['m']; ?>
          <a class="mainnav__unter<?= $eintrag['aktiv'] ? ' is-active' : '' ?>" href="<?= e(url($m['route'])) ?>"<?= $eintrag['aktiv'] ? ' aria-current="page"' : '' ?>>
            <span class="mainnav__icon"><?= e($m['icon']) ?></span><span><?= e($m['label']) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<main class="page">
  <?= flash_ausgabe() ?>
  <?= $__content ?>
</main>

<footer class="footer">
  <div><?= nl2br(e((string)setting('footer_text', ''))) ?></div>
  <div class="footer__meta">
    <?= e($ovName) ?>
    <?= $ovName !== '' ? ' · ' : '' ?><a href="<?= e(url('neu')) ?>" title="Was ist neu?">OV-Multitool <?= e(app_version()) ?></a>
    · <a href="<?= e(url('lizenzen')) ?>" title="Mitgelieferte Bibliotheken, ihre Urheber und Lizenzen">Bibliotheken und Lizenzen</a>
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
<?php if (!empty($GLOBALS['ovb_karte_js'])): ?>
  <script src="<?= e(asset('js/karte.js')) ?>" defer></script>
<?php endif; ?>
<?php /* Fremde Bibliotheken, unverändert mitgeliefert – siehe Seite „Bibliotheken und Lizenzen" */ ?>
<?php if (!empty($GLOBALS['ovb_einsatzauftrag_js']) || !empty($GLOBALS['ovb_karte_einsatz_js'])): ?>
  <script src="<?= e(asset('vendor/jspdf/jspdf.umd.min.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['ovb_einsatzauftrag_js'])): ?>
  <script src="<?= e(asset('vendor/einsatzauftrag/einsatzauftrag.js')) ?>" defer></script>
  <script src="<?= e(asset('js/einsatzauftrag-maske.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['ovb_karte_einsatz_js'])): ?>
  <script src="<?= e(asset('js/karte-einsatz.js')) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($GLOBALS['ovb_umfrage_js'])): ?>
  <script src="<?= e(asset('js/umfrage-editor.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
