<?php
/** @var ?array $cfg @var array $werte @var array $fahrzeuge @var string $basis
 *  @var int $letzter @var int $anmeldung @var string $fehler @var string $hinweis */
$aktiv = setting_bool('ha_mqtt_aktiv', false);
?>
<div class="pagehead">
  <div>
    <h1>Home Assistant</h1>
    <p>Kennzahlen und Fahrzeuge über MQTT melden. Home Assistant legt die Entitäten selbst an
       (Auto-Discovery); nötig ist das Mosquitto-Add-on oder ein eigener Broker.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Home Assistant'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<?php if (!$aktiv): ?>
  <div class="alert alert--warn">Die Meldung ist ausgeschaltet. In den
    <a href="<?= e(url('admin_settings', ['group' => 'Home Assistant'])) ?>">Einstellungen</a> einschalten.</div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2>Broker</h2>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <button class="btn" type="submit">Jetzt senden</button>
    </form>
  </div>
  <?php if (!$cfg): ?>
    <div class="empty">Kein Broker bekannt. Entweder das Mosquitto-Add-on installieren (dann kommen die
      Zugangsdaten von selbst) oder Host, Benutzer und Passwort in den Einstellungen eintragen.</div>
  <?php else: ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Quelle</div><div class="dl__value"><?= e($cfg['quelle']) ?></div></div>
      <div class="dl__item"><div class="dl__label">Adresse</div>
        <div class="dl__value mono small"><?= e(($cfg['ssl'] ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . $cfg['port']) ?></div></div>
      <div class="dl__item"><div class="dl__label">Benutzer</div>
        <div class="dl__value"><?= $cfg['user'] !== '' ? e($cfg['user']) : '<span class="muted">ohne Anmeldung</span>' ?></div></div>
      <div class="dl__item"><div class="dl__label">Themenbaum</div><div class="dl__value mono small"><?= e($basis) ?>/status</div></div>
      <div class="dl__item"><div class="dl__label">Zuletzt gemeldet</div>
        <div class="dl__value"><?= $letzter ? e(de_datetime(date('Y-m-d H:i:s', $letzter))) : 'noch nie' ?></div></div>
      <div class="dl__item"><div class="dl__label">Entitäten angemeldet</div>
        <div class="dl__value"><?= $anmeldung ? e(de_datetime(date('Y-m-d H:i:s', $anmeldung))) : 'noch nie' ?></div></div>
    </dl>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card__head">
    <h2>Was gemeldet wird</h2>
    <span class="small muted"><?= count(ha_sensoren()) ?> Sensoren<?= $fahrzeuge ? ' + ' . count($fahrzeuge) . ' Fahrzeuge' : '' ?></span>
  </div>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Entität</th><th>Bedeutung</th><th class="num">aktueller Wert</th></tr></thead>
      <tbody>
      <?php foreach (ha_sensoren() as $key => $s): $w = $werte[$key] ?? null; ?>
        <tr>
          <td class="mono small">sensor.<?= e($basis . '_' . $key) ?></td>
          <td class="small"><?= e($s['name']) ?></td>
          <td class="num small"><?= $w === null || $w === '' ? '<span class="muted">–</span>' : e((string)$w) ?>
            <?= !empty($s['unit']) && $w !== null ? ' ' . e($s['unit']) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($fahrzeuge): ?>
    <p class="small muted mt">Je Fahrzeug entsteht ein eigenes Gerät mit Status, Funkstatus, HU, SP,
      Kilometerstand und offenen Aufträgen<?= setting_bool('ha_mqtt_position', false) ? ' sowie dem Standort auf der Karte' : '' ?>:
      <?= e(implode(', ', array_map(static fn($v) => (string)$v['bezeichnung'], $fahrzeuge))) ?>.</p>
  <?php else: ?>
    <p class="small muted mt">Einzelne Fahrzeuge werden nicht gemeldet – das lässt sich in den Einstellungen einschalten.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Aufräumen</h2>
  <p class="small muted">Entfernt die Entitäten wieder aus Home Assistant. Die Daten hier bleiben unberührt.</p>
  <form method="post" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="entfernen">
    <button class="btn btn--sec" type="submit"
            data-confirm="Alle OV-Budget-Entitäten aus Home Assistant entfernen?">Entitäten entfernen</button>
  </form>
</div>
