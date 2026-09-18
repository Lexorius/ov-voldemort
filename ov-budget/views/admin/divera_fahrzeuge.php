<?php
/** @var bool $aktiv @var bool $grundlage @var int $status @var int $stamm @var array $offen
 *  @var array $fahrzeuge @var array $verknuepft @var array $protokoll */
$wann = static fn(int $t) => $t > 0 ? de_datetime(date('Y-m-d H:i:s', $t)) : 'noch nie';
?>
<div class="pagehead">
  <div>
    <h1>Divera-Fahrzeuge</h1>
    <p>Holt aus Divera 24/7 den Funkstatus (FMS) mit Zeitpunkt, die letzte Position und die Besatzung
       sowie OPTA, RIC, Kennzeichen und ISSI. Jeder Statuswechsel landet als Fahrtenbuch im Journal der
       Fahrzeugakte.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if (!$grundlage): ?>
  <div class="alert alert--info">Zuerst die Divera-Anbindung einrichten: unter
    <a href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Einstellungen → Divera 24/7</a>
    „Divera-24/7-Anbindung aktiv" und den Accesskey.</div>
<?php elseif (!$aktiv): ?>
  <div class="alert alert--info">Der Fahrzeugabgleich ist aus. Unter
    <a href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">Einstellungen → Divera 24/7</a>
    „Fahrzeugdaten aus Divera abgleichen" einschalten.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Funkstatus</div>
    <div class="stat__value" style="font-size:1.05rem"><?= e($wann($status)) ?></div>
    <div class="stat__hint">alle <?= setting_int('divera_status_intervall_minuten', 2) ?> Minuten</div>
  </div>
  <div class="stat">
    <div class="stat__label">Stammdaten</div>
    <div class="stat__value" style="font-size:1.05rem"><?= e($wann($stamm)) ?></div>
    <div class="stat__hint">alle <?= setting_int('divera_stamm_intervall_minuten', 60) ?> Minuten</div>
  </div>
  <div class="stat">
    <div class="stat__label">Verknüpft</div>
    <div class="stat__value"><?= count($verknuepft) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Ohne Zuordnung</div>
    <div class="stat__value"<?= $offen ? ' style="color:var(--warn)"' : '' ?>><?= count($offen) ?></div>
  </div>
</div>

<section class="card">
  <h2>Jetzt abrufen</h2>
  <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="dv_sync">
    <button class="btn" type="submit"<?= $aktiv ? '' : ' disabled' ?>>Stammdaten und Funkstatus abrufen</button>
  </form>
  <p class="small muted" style="margin-bottom:0">
    Zugeordnet wird selbst: über ISSI, Kennzeichen oder Funkrufname – aber nur, wenn genau ein Fahrzeug
    passt. Die Stammdaten kommen aus der v3-Schnittstelle von Divera (noch Beta); sie braucht einen
    persönlichen Accesskey mit Verwaltungsrechten. Funkstatus, Position und Besatzung kommen mit dem
    normalen Accesskey. Statuswechsel zwischen zwei Abrufen sieht die Anwendung nicht – je kürzer der
    Abstand, desto vollständiger das Fahrtenbuch.
  </p>
</section>

<?php if ($offen): ?>
  <section class="card">
    <h2>Divera-Fahrzeuge ohne Zuordnung</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>In Divera</th><th>Zuordnen zu</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($offen as $d): ?>
          <tr>
            <form method="post" action="<?= e(url('vehicle_action')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="dv_assign">
              <input type="hidden" name="divera_id" value="<?= (int)$d['id'] ?>">
              <td>
                <strong><?= e((string)$d['name']) ?></strong>
                <div class="small muted"><?= e((string)($d['typ'] ?? '')) ?> · <span class="mono"><?= (int)$d['id'] ?></span></div>
              </td>
              <td>
                <select name="vehicle_id" aria-label="Fahrzeug für <?= e((string)$d['name']) ?>">
                  <option value="">– bitte wählen –</option>
                  <?php foreach ($fahrzeuge as $v): ?>
                    <option value="<?= (int)$v['id'] ?>"><?= e($v['bezeichnung']) ?><?= $v['funkrufname'] ? ' · ' . e($v['funkrufname']) : '' ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><button class="btn btn--sm" type="submit">Zuordnen</button></td>
            </form>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($verknuepft): ?>
  <section class="card">
    <h2>Verknüpfte Fahrzeuge</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fahrzeug</th><th>OPTA / RIC</th><th>Funkstatus</th><th>Abgerufen</th></tr></thead>
        <tbody>
        <?php foreach ($verknuepft as $v): ?>
          <tr>
            <td><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e($v['bezeichnung']) ?></a>
              <div class="small muted"><?= e((string)$v['funkrufname']) ?></div></td>
            <td class="small"><?= e(implode(' · ', array_filter([(string)$v['opta'], (string)$v['ric']])) ?: '–') ?></td>
            <td>
              <?php if ($v['fms_status'] !== null): ?>
                <span class="badge" style="background:<?= e(fms_color((int)$v['fms_status'])) ?>"><?= e(fms_label((int)$v['fms_status'])) ?></span>
                <?php if ($v['fms_at']): ?><div class="small muted">seit <?= e(de_datetime($v['fms_at'])) ?></div><?php endif; ?>
              <?php else: ?><span class="muted">–</span><?php endif; ?>
            </td>
            <td class="small nowrap"><?= $v['divera_sync_at'] ? e(de_datetime($v['divera_sync_at'])) : '–' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Protokoll der Abrufe</h2>
  <?php if (!$protokoll): ?>
    <div class="empty">Noch kein Abruf erfolgt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wann</th><th>Ergebnis</th><th>Meldung</th></tr></thead>
        <tbody>
        <?php foreach ($protokoll as $l): ?>
          <tr>
            <td class="small nowrap"><?= e(de_datetime($l['created_at'])) ?></td>
            <td><span class="badge" style="background:<?= $l['status'] === 'ok' ? '#15803d' : '#b91c1c' ?>"><?= e($l['status']) ?></span></td>
            <td class="small"><?= e($l['message']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
