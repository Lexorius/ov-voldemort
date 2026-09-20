<?php
/** @var ?array $cfg @var array $werte @var array $fahrzeuge @var string $basis
 *  @var int $letzter @var int $anmeldung @var string $fehler @var string $hinweis
 *  @var array $dienste @var string $diensteFehler @var array $empfaenger
 *  @var array $warteschlange @var int $offen @var array $push @var string $haQuelle */
$aktiv = setting_bool('ha_mqtt_aktiv', false);
$melden = notify_enabled();
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

<section class="card" id="benachrichtigungen">
  <div class="card__head">
    <h2>Benachrichtigungen</h2>
    <div class="btnrow">
      <?php if ($melden): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="dienste">
          <button class="btn btn--sec btn--sm" type="submit">Ziele neu einlesen</button></form>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="test_notify">
          <button class="btn btn--sec btn--sm" type="submit">Testnachricht an mich</button></form>
        <?php if ($offen > 0): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?>
            <input type="hidden" name="action" value="senden">
            <button class="btn btn--sm" type="submit">Warteschlange senden (<?= $offen ?>)</button></form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$melden): ?>
    <div class="empty">Benachrichtigungen sind ausgeschaltet. In den
      <a href="<?= e(url('admin_settings', ['group' => 'Home Assistant'])) ?>">Einstellungen</a> einschalten –
      dort steht auch, welche Ereignisse gemeldet werden.</div>
  <?php else: ?>
    <p class="small muted">Gesendet wird über <span class="mono">notify.&lt;Ziel&gt;</span>, also in der Regel die
      Companion-App. Jede Person hinterlegt ihr Ziel im eigenen Profil; hier lässt es sich auch über die
      Benutzerverwaltung setzen. Der Minutenlauf schickt die Warteschlange los.</p>

    <p class="small muted">Zugang zu Home Assistant: <?= $haQuelle === ''
        ? '<strong style="color:var(--bad)">kein Token</strong> – das Add-on einmal neu starten'
        : 'vorhanden (' . e(match ($haQuelle) {
            'Datei' => 'beim Start hinterlegt',
            's6'    => 'aus der Container-Umgebung',
            default => 'Umgebung',
        }) . ')' ?>.</p>

    <?php if ($diensteFehler !== ''): ?>
      <div class="alert alert--warn">Ziele konnten nicht gelesen werden: <?= e($diensteFehler) ?></div>
    <?php elseif ($dienste): ?>
      <div class="chips"><?php foreach ($dienste as $d): ?><span class="chip mono">notify.<?= e($d) ?></span><?php endforeach; ?></div>
    <?php else: ?>
      <div class="alert alert--warn">Home Assistant meldet keine Benachrichtigungsziele. Ist die Companion-App
        eingerichtet?</div>
    <?php endif; ?>

    <h3 class="mt">Ereignisse</h3>
    <ul class="small">
      <?php foreach (notify_ereignisse() as $key => $er): ?>
        <li><strong><?= e($er['label']) ?></strong><?= !empty($er['taeglich']) ? ' <span class="badge badge--outline">täglich</span>' : '' ?>
          – <?= e($er['text']) ?>
          <?= setting_bool('notify_' . $key, true) ? '' : '<span class="muted">(aus)</span>' ?></li>
      <?php endforeach; ?>
    </ul>

    <h3 class="mt">Wer bekommt etwas?</h3>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Person</th><th>Ziel</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($empfaenger as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td class="mono small"><?= $p['ha_notify'] ? e('notify.' . $p['ha_notify']) : '<span class="muted">kein Ziel</span>' ?></td>
            <td class="small"><?= $p['ha_notify'] === '' ? '–' : ((int)$p['notify_aktiv'] ? 'bekommt Meldungen' : 'abgeschaltet') ?></td>
            <td><a class="btn btn--sec btn--sm" href="<?= e(url('admin_user_edit', ['id' => $p['id']])) ?>">Bearbeiten</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="mt">Zuletzt</h3>
    <?php if (!$warteschlange): ?>
      <div class="empty">Noch nichts versendet.</div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Zeitpunkt</th><th>An</th><th>Ereignis</th><th>Titel</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($warteschlange as $n): ?>
            <tr>
              <td class="small nowrap"><?= e(de_datetime($n['created_at'])) ?></td>
              <td class="small"><?= e($n['name']) ?></td>
              <td class="small mono"><?= e($n['ereignis']) ?></td>
              <td class="small"><?= e($n['titel']) ?></td>
              <td class="small">
                <?php if ($n['status'] === 'gesendet'): ?>
                  <span class="badge" style="background:#15803d">gesendet</span>
                <?php elseif ($n['status'] === 'fehler'): ?>
                  <span class="badge" style="background:#b91c1c">Fehler</span>
                  <div class="muted"><?= e($n['fehler']) ?></div>
                <?php else: ?>
                  <span class="badge badge--outline">wartet</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card" id="webpush">
  <div class="card__head">
    <h2>Benachrichtigungen im Browser (Web Push)</h2>
    <div class="btnrow">
      <?php if ($push['aktiv'] && !$push['schluessel']): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="push_schluessel">
          <button class="btn" type="submit">Schlüssel erzeugen</button></form>
      <?php elseif ($push['aktiv']): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="push_test">
          <button class="btn btn--sec btn--sm" type="submit">Testnachricht an meine Browser</button></form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$push['aktiv']): ?>
    <div class="empty">Ausgeschaltet. In den
      <a href="<?= e(url('admin_settings', ['group' => 'Home Assistant'])) ?>">Einstellungen</a> einschalten –
      danach melden sich die Browser im jeweiligen Profil selbst an.</div>
  <?php else: ?>
    <p class="small muted">Push funktioniert nur über HTTPS – über den Ingress von Home Assistant also von
      selbst, über den direkten Port 8099 nicht. Auf dem iPhone muss die Seite zum Home-Bildschirm
      hinzugefügt sein. Der Container muss die Push-Dienste von Google und Mozilla erreichen können.</p>
    <?php if (!$push['schluessel']): ?>
      <div class="alert alert--warn">Es fehlen noch die VAPID-Schlüssel. Sie werden einmalig erzeugt und
        bleiben danach gleich – tauscht man sie aus, müssen sich alle Browser neu anmelden.</div>
    <?php else: ?>
      <?php if (!$push['abos']): ?>
        <div class="empty">Noch kein Browser angemeldet. Das geht unter
          <a href="<?= e(url('profile')) ?>">Mein Profil</a>.</div>
      <?php else: ?>
        <div class="tablewrap">
          <table class="data">
            <thead><tr><th>Person</th><th>Gerät</th><th>angemeldet</th><th>zuletzt erreicht</th></tr></thead>
            <tbody>
            <?php foreach ($push['abos'] as $a): ?>
              <tr>
                <td><?= e($a['name']) ?></td>
                <td class="small"><?= e(mb_substr((string)$a['geraet'], 0, 60) ?: 'unbekannt') ?></td>
                <td class="small nowrap"><?= e(de_date($a['created_at'])) ?></td>
                <td class="small nowrap"><?= $a['last_ok'] ? e(de_datetime($a['last_ok'])) : '<span class="muted">noch nie</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>

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
