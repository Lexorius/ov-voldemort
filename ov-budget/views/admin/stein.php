<?php
/** @var bool $aktiv @var int $letzter @var int $intervall @var ?string $wartet
 *  @var array $offen @var array $fahrzeuge @var array $verknuepft @var array $protokoll
 *  @var bool $webhook */
?>
<div class="pagehead">
  <div>
    <h1>Stein.APP</h1>
    <p>Holt regelmäßig den Stand aller Fahrzeuge und schreibt jede Änderung in die Fahrzeugakte.
       Die Schnittstelle hat ein striktes Rate Limit, deshalb wird je Abgleich genau ein Aufruf gemacht
       und der Abstand zwischen zwei Abrufen eingehalten.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Stein.APP'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if (!$aktiv): ?>
  <div class="alert alert--info">
    Der Abgleich ist noch nicht eingerichtet. Unter
    <a href="<?= e(url('admin_settings', ['group' => 'Stein.APP'])) ?>">Einstellungen → Stein.APP</a>
    den API-Schlüssel und die BU-ID eintragen und den Abgleich einschalten.
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Letzter Abruf</div>
    <div class="stat__value" style="font-size:1.1rem"><?= $letzter > 0 ? e(de_datetime(date('Y-m-d H:i:s', $letzter))) : 'noch nie' ?></div>
    <div class="stat__hint">Intervall: <?= (int)$intervall ?> Minuten</div>
  </div>
  <div class="stat">
    <div class="stat__label">Verknüpfte Fahrzeuge</div>
    <div class="stat__value"><?= count($verknuepft) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Ohne Zuordnung</div>
    <div class="stat__value"<?= $offen ? ' style="color:var(--warn)"' : '' ?>><?= count($offen) ?></div>
    <div class="stat__hint">aus dem letzten Abruf</div>
  </div>
</div>

<section class="card">
  <div class="card__head">
    <h2>Jetzt abgleichen</h2>
    <?php if ($wartet !== null): ?><span class="small muted"><?= e($wartet) ?></span><?php endif; ?>
  </div>
  <div class="btnrow">
    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="stein_sync">
      <button class="btn" type="submit"<?= $aktiv ? '' : ' disabled' ?>>Abgleich starten</button>
    </form>
    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="stein_sync">
      <input type="hidden" name="erzwingen" value="1">
      <button class="btn btn--sec" type="submit"<?= $aktiv ? '' : ' disabled' ?>
              data-confirm="Das Intervall überspringen? Die Schnittstelle bremst bei zu vielen Abrufen.">
        Ohne Wartezeit abrufen</button>
    </form>
    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="stein_test">
      <button class="btn btn--sec" type="submit">Verbindung testen</button>
    </form>
  </div>
  <p class="small muted" style="margin-bottom:0">
    Von selbst läuft der Abgleich beim Öffnen des Fahrzeugmoduls und über
    <span class="mono">cron.php</span>. Im Home-Assistant-Add-on ruft der Container
    <span class="mono">cron.php</span> jede Minute auf; abgerufen wird aber nur, wenn das Intervall um ist.
  </p>
</section>

<section class="card">
  <h2>Grenzen der Schnittstelle</h2>
  <ul class="small">
    <li>Höchstens <strong>20 Anfragen je Minute</strong> und IP-Adresse. Wer darüber liegt,
      wird <strong>eine Stunde gesperrt</strong>. Ein Abgleich braucht genau eine Anfrage.</li>
    <li>Der Zugriff ist auf <strong>IP-Adressen aus Deutschland</strong> beschränkt. Von außerhalb
      antwortet die Stein.APP mit 404.</li>
    <li>Die Stein.APP empfiehlt statt regelmäßigem Abfragen einen <strong>Webhook</strong>.</li>
  </ul>

  <h3>Webhook</h3>
  <?php if ($webhook): ?>
    <p class="small">Ein Secret ist hinterlegt. Trage in der Stein.APP unter den Einstellungen
      des Ortsverbands die Adresse dieser Anwendung mit dem Pfad
      <span class="mono">/webhook.php</span> ein. Meldet die Stein.APP eine Änderung, wird
      sofort abgeglichen – längstens alle 30 Sekunden.</p>
  <?php else: ?>
    <p class="small muted">Kein Secret hinterlegt, der Webhook ist damit aus. Das Secret steht in der
      Stein.APP in den Einstellungen des Ortsverbands und gehört in die
      <a href="<?= e(url('admin_settings', ['group' => 'Stein.APP'])) ?>">Einstellungen</a>.
      Die Adresse muss von außen erreichbar sein – über Ingress allein ist sie das nicht.</p>
  <?php endif; ?>
</section>

<?php if ($offen): ?>
  <section class="card">
    <h2>Fahrzeuge aus der Stein.APP ohne Zuordnung</h2>
    <p class="small muted">Einem vorhandenen Fahrzeug zuordnen oder als neue Akte anlegen.
       Beides braucht keinen weiteren Abruf. Ein Kennzeichen führt die Stein.APP nicht als
       eigenes Feld – es wird aus Bezeichnung, Name und Bemerkung gelesen.</p>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Aus der Stein.APP</th><th>Status</th><th>Zuordnen zu</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($offen as $a): ?>
          <tr>
            <form method="post" action="<?= e(url('vehicle_action')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="stein_assign">
              <input type="hidden" name="asset_id" value="<?= e((string)$a['id']) ?>">
              <input type="hidden" name="name" value="<?= e((string)$a['name']) ?>">
              <input type="hidden" name="funk" value="<?= e((string)$a['funk']) ?>">
              <input type="hidden" name="kennzeichen" value="<?= e((string)$a['kennzeichen']) ?>">
              <td>
                <strong><?= e((string)$a['name']) ?></strong>
                <div class="small muted mono"><?= e((string)$a['id']) ?></div>
                <?php if ((string)$a['kennzeichen'] !== ''): ?>
                  <div class="small">Kennzeichen: <?= e((string)$a['kennzeichen']) ?></div>
                <?php endif; ?>
                <?php if ((string)$a['grund'] !== ''): ?>
                  <div class="small muted"><?= e((string)$a['grund']) ?></div>
                <?php endif; ?>
              </td>
              <td class="small"><?= e((string)$a['status']) ?></td>
              <td>
                <select name="vehicle_id" aria-label="Fahrzeug für <?= e((string)$a['name']) ?>">
                  <option value="0">– neue Fahrzeugakte anlegen –</option>
                  <?php foreach ($fahrzeuge as $v): ?>
                    <?php if (!$v['stein_asset_id']): ?>
                      <option value="<?= (int)$v['id'] ?>"><?= e($v['bezeichnung']) ?><?= $v['kennzeichen'] ? ' · ' . e($v['kennzeichen']) : '' ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><button class="btn btn--sm" type="submit">Übernehmen</button></td>
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
        <thead><tr><th>Fahrzeug</th><th>Asset-ID</th><th>Status laut Stein.APP</th><th>Letzter Abgleich</th></tr></thead>
        <tbody>
        <?php foreach ($verknuepft as $v): ?>
          <tr>
            <td><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e($v['bezeichnung']) ?></a></td>
            <td class="mono small"><?= e((string)$v['stein_asset_id']) ?></td>
            <td class="small"><?= e(stein_value_text('status', $v['stein_status'])) ?></td>
            <td class="small nowrap"><?= $v['stein_sync_at'] ? e(de_datetime($v['stein_sync_at'])) : '–' ?></td>
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
        <thead><tr><th>Wann</th><th>Ergebnis</th><th class="num">Fahrzeuge</th><th class="num">Änderungen</th><th>Meldung</th></tr></thead>
        <tbody>
        <?php foreach ($protokoll as $l): ?>
          <tr>
            <td class="small nowrap"><?= e(de_datetime($l['created_at'])) ?></td>
            <td>
              <span class="badge" style="background:<?= $l['status'] === 'ok' ? '#15803d' : ($l['status'] === 'fehler' ? '#b91c1c' : '#64748b') ?>">
                <?= e($l['status']) ?></span>
            </td>
            <td class="num"><?= (int)$l['assets'] ?></td>
            <td class="num"><?= (int)$l['aenderungen'] ?></td>
            <td class="small"><?= e($l['message']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
