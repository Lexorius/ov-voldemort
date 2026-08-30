<?php
/** @var array $users */
$rollen = ['admin' => 'Administration', 'leitung' => 'Leitung', 'user' => 'Mitglied'];
?>
<div class="pagehead">
  <div>
    <h1>Benutzer</h1>
    <p><?= count($users) ?> Zugänge</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('admin_user_edit')) ?>">+ Benutzer</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<div class="card only-mobile">
  <div class="itemlist">
    <?php foreach ($users as $u2): ?>
      <a class="item" href="<?= e(url('admin_user_edit', ['id' => $u2['id']])) ?>">
        <div class="item__top">
          <div>
            <div class="item__title"><?= e($u2['display_name'] ?: $u2['username']) ?></div>
            <div class="item__sub"><?= e($u2['username']) ?> · <?= e($u2['fachgruppe_label'] ?: 'ohne Fachgruppe') ?></div>
          </div>
        </div>
        <div class="item__meta">
          <span class="badge badge--outline"><?= e($rollen[$u2['role']] ?? $u2['role']) ?></span>
          <?php if (!(int)$u2['is_active']): ?><span class="badge" style="background:#b91c1c">gesperrt</span><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card hide-mobile">
  <div class="tablewrap">
    <table class="data">
      <thead>
        <tr><th>Name</th><th>Benutzername</th><th>Rolle</th><th>Fachgruppe</th><th>Funktionen</th>
            <th>Letzte Anmeldung</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u2): ?>
        <tr>
          <td>
            <strong><?= e($u2['display_name'] ?: '–') ?></strong>
            <?php if (!(int)$u2['is_active']): ?> <span class="badge" style="background:#b91c1c">gesperrt</span><?php endif; ?>
            <?php if ((int)$u2['must_change_pw']): ?> <span class="badge badge--muted">PW ändern</span><?php endif; ?>
          </td>
          <td class="mono"><?= e($u2['username']) ?></td>
          <td><?= e($rollen[$u2['role']] ?? $u2['role']) ?></td>
          <td><?= e($u2['fachgruppe_label'] ?: '–') ?></td>
          <td class="small"><?= e($u2['funktionen'] ?: '–') ?></td>
          <td class="small nowrap"><?= e(de_datetime($u2['last_login']) ?: 'nie') ?></td>
          <td><a class="btn btn--sec btn--sm" href="<?= e(url('admin_user_edit', ['id' => $u2['id']])) ?>">Bearbeiten</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
