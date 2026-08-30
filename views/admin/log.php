<?php
/** @var string $tab @var array $rows */
?>
<div class="pagehead">
  <div>
    <h1>Protokoll</h1>
    <p>Die letzten 300 Einträge.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
</div>

<div class="tabs">
  <a class="tab<?= $tab === 'audit' ? ' is-active' : '' ?>" href="<?= e(url('admin_log', ['tab' => 'audit'])) ?>">Änderungen</a>
  <a class="tab<?= $tab === 'divera' ? ' is-active' : '' ?>" href="<?= e(url('admin_log', ['tab' => 'divera'])) ?>">Divera-Import</a>
  <a class="tab<?= $tab === 'login' ? ' is-active' : '' ?>" href="<?= e(url('admin_log', ['tab' => 'login'])) ?>">Fehlversuche</a>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty">Keine Einträge.</div>
  <?php elseif ($tab === 'audit'): ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th>Person</th><th>Aktion</th><th>Objekt</th><th>Details</th><th class="hide-mobile">IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_datetime($r['created_at'])) ?></td>
            <td class="small"><?= e($r['display_name'] ?: ($r['username'] ?: 'System')) ?></td>
            <td class="mono small"><?= e($r['action']) ?></td>
            <td class="small"><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></td>
            <td class="small"><?= e($r['detail']) ?></td>
            <td class="mono small hide-mobile"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($tab === 'divera'): ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th>Formular</th><th>Eintrag</th><th>Status</th><th>Meldung</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_datetime($r['created_at'])) ?></td>
            <td class="mono small"><?= e($r['form_id']) ?></td>
            <td class="mono small"><?= e($r['entry_id']) ?></td>
            <td><?= $r['status'] === 'ok'
                ? '<span class="badge" style="background:#15803d">ok</span>'
                : '<span class="badge" style="background:#b91c1c">Fehler</span>' ?></td>
            <td class="small">
              <?php if ($r['wish_id']): ?>
                <a href="<?= e(url('wish', ['id' => $r['wish_id']])) ?>"><?= e($r['message']) ?></a>
              <?php else: ?><?= e($r['message']) ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeitpunkt</th><th>Benutzername</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap small"><?= e(de_datetime($r['created_at'])) ?></td>
            <td class="mono small"><?= e($r['username']) ?></td>
            <td class="mono small"><?= e($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
