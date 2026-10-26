<?php
/**
 * @var ?array $standort @var array $plaetze @var array $lagerplaetze @var bool $darf
 * @var array $getraenke @var array $bestand @var array $bewegungen @var array $meldungen @var ?array $connector @var array $connectoren
 */
?>
<div class="pagehead">
  <div>
    <h1><?= $standort !== null ? 'Lager ' . e((string)$standort['name']) : 'Lager' ?></h1>
    <p>Bestand je Getränk an diesem Platz, Bewegungen zum Buchen und der QR-Code, über den jede Person „leer" oder
       „wird knapp" melden kann.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#lager">Zur Übersicht</a>
    <?php if ($standort !== null): ?><a class="btn btn--sec" href="<?= e(url('standorte', ['id' => $standort['id']])) ?>">Zum Platz</a><?php endif; ?>
  </div>
</div>

<?php if ($standort === null): ?>
  <section class="card">
    <div class="card__head"><h2>Platz wählen</h2></div>
    <?php if ($lagerplaetze): ?>
      <div class="btnrow" style="margin-bottom:.8rem">
        <?php foreach ($lagerplaetze as $p): ?><a class="btn btn--sec" href="<?= e(url('getraenke_lager', ['standort_id' => $p['id']])) ?>"><?= e((string)$p['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="get" class="form"><input type="hidden" name="p" value="getraenke_lager">
      <div class="field"><label for="standort_id">Ein anderer Stell- oder Lagerplatz</label>
        <select id="standort_id" name="standort_id"><?php foreach ($plaetze as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e((string)$p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="btnrow"><button class="btn" type="submit">Öffnen</button></div>
    </form>
  </section>
  <?php return; ?>
<?php endif; ?>

<?php $token = trim((string)($standort['getraenke_qr_token'] ?? '')); ?>

<section class="card" id="bestand">
  <div class="card__head"><h2>Bestand</h2><span class="small muted">Gebinde an diesem Platz</span></div>
  <?php if (!$getraenke): ?>
    <div class="empty">Noch keine Getränkeart angelegt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Getränk</th><th class="num">Bestand</th><th class="num">Mindest (gesamt)</th><?php if ($darf): ?><th>Buchen</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($getraenke as $g): $gid = (int)$g['id']; $n = (int)($bestand[$gid] ?? 0); ?>
          <tr>
            <td><?= e((string)$g['name']) ?> <span class="small muted"><?= e(getraenk_gebinde_text($g)) ?></span></td>
            <td class="num"><strong><?= $n ?></strong></td>
            <td class="num muted"><?= (int)$g['mindestbestand'] ?></td>
            <?php if ($darf): ?>
              <td>
                <form method="post" action="<?= e(url('getraenke_action')) ?>" class="getraenke-buchen"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="buchen"><input type="hidden" name="standort_id" value="<?= (int)$standort['id'] ?>">
                  <input type="hidden" name="getraenk_id" value="<?= $gid ?>">
                  <select name="art" aria-label="Art"><?php foreach (GETRAENKE_BEWEGUNG as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select>
                  <input type="number" name="anzahl" min="0" max="9999" value="1" aria-label="Anzahl" style="width:5rem">
                  <button class="btn btn--sec btn--sm" type="submit">Buchen</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted">Zugang und Abgang verschieben um die Anzahl, Inventur setzt den Bestand auf die Anzahl.</p>
  <?php endif; ?>
</section>

<?php if ($meldungen): ?>
  <section class="card" id="meldungen">
    <div class="card__head"><h2>Offene Meldungen von diesem Platz</h2></div>
    <ul class="getraenke-bedarf__liste">
      <?php foreach ($meldungen as $m): ?>
        <li><?= getraenke_stand_badge((string)$m['stand']) ?> <?= e((string)$m['getraenk_name']) ?>
          <span class="small muted"><?= e(de_datetime((string)$m['gemeldet_am'])) ?><?= trim((string)$m['melder']) !== '' ? ' · ' . e((string)$m['melder']) : '' ?><?= $m['anzahl'] !== null ? ' · noch ' . (int)$m['anzahl'] : '' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="card" id="qr">
  <div class="card__head"><h2>Melden per QR-Code</h2></div>
  <?php if ($token !== '' && $connector !== null): ?>
    <?php $qrAdresse = connector_qr_getraenke_url($connector, $token, getraenke_qr_name($standort)); ?>
    <div class="qr-block" data-qr="<?= e($qrAdresse) ?>" data-qr-titel="<?= e((string)$standort['name']) ?>" data-qr-untertitel="Getränke melden"
         data-qr-hinweis="Mit der Kamera scannen und antippen, was leer ist oder knapp wird.">
      <div class="qr-bild" id="qr-bild"></div>
      <div>
        <p class="small">Am Regal oder Kühlschrank aufhängen: Wer den Code scannt, sieht die Getränke und tippt an, was leer ist
          oder knapp wird – ohne Anmeldung. Die Meldung wird im Browser verschlüsselt.</p>
        <p class="small muted mono" style="word-break:break-all"><?= e($qrAdresse) ?></p>
        <p class="small muted">Der Connector kennt nur die Getränkenamen; welcher Platz es ist, steht hinter dem <span class="mono">#</span>
          und bleibt im Browser. Läuft über <strong><?= e((string)$connector['name']) ?></strong>.</p>
        <?php if ($darf): ?>
          <div class="btnrow">
            <button class="btn btn--sec btn--sm" type="button" id="qr-drucken">Drucken</button>
            <button class="btn btn--sec btn--sm" type="button" id="qr-laden">QR als Bild</button>
            <form method="post" action="<?= e(url('getraenke_action')) ?>" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_neu"><input type="hidden" name="standort_id" value="<?= (int)$standort['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit" data-confirm="Neuen QR-Code erzeugen? Der bisherige gilt dann nicht mehr." data-confirm2="Bist du wirklich sicher?">Neu erzeugen</button></form>
            <form method="post" action="<?= e(url('getraenke_action')) ?>" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_weg"><input type="hidden" name="standort_id" value="<?= (int)$standort['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit" data-confirm="QR-Code zurückziehen? Meldungen darüber werden dann nicht mehr angenommen." data-confirm2="Bist du wirklich sicher?">Zurückziehen</button></form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($token !== ''): ?>
    <p class="small">Für diesen Code ist kein Connector mehr eingetragen – bitte neu erzeugen.</p>
  <?php elseif ($darf): ?>
    <?php if (!$connectoren): ?>
      <p class="small">Fürs Melden per QR-Code braucht es einen <strong>Connector</strong> mit der Verwendung „Getränke" –
        einzurichten in der <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('getraenke_action')) ?>" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="qr_neu"><input type="hidden" name="standort_id" value="<?= (int)$standort['id'] ?>">
        <?php if (count($connectoren) > 1): ?>
          <select name="connector_id" aria-label="Connector"><?php foreach ($connectoren as $con): ?><option value="<?= (int)$con['id'] ?>"><?= e((string)$con['name']) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <button class="btn btn--sec" type="submit">QR-Code erzeugen</button></form>
      <p class="small muted">Erzeugt einen Zugang für diesen Platz. Die Meldeseite zeigt alle aktiven Getränke.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="small muted">An diesem Platz hängt kein QR-Code.</p>
  <?php endif; ?>
</section>

<section class="card" id="bewegungen">
  <div class="card__head"><h2>Letzte Bewegungen</h2></div>
  <?php if (!$bewegungen): ?><div class="empty">Noch keine Bewegung an diesem Platz.</div><?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wann</th><th>Getränk</th><th>Art</th><th class="num">Anzahl</th><th class="num">Danach</th><th>Wer</th><th>Notiz</th></tr></thead>
        <tbody>
        <?php foreach ($bewegungen as $w): ?>
          <tr>
            <td class="small nowrap"><?= e(de_datetime((string)$w['created_at'])) ?></td>
            <td><?= e((string)$w['getraenk_name']) ?></td>
            <td class="small"><?= e(GETRAENKE_BEWEGUNG[(string)$w['art']] ?? (string)$w['art']) ?></td>
            <td class="num"><?= (int)$w['anzahl'] ?></td>
            <td class="num"><strong><?= (int)$w['bestand_neu'] ?></strong></td>
            <td class="small"><?= e((string)($w['benutzer'] ?: $w['melder'])) ?></td>
            <td class="small muted"><?= e((string)$w['notiz']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
