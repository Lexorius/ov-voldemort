<?php
/** @var array $versionen @var ?array $aktuell @var ?array $gezeigt @var array $dateien @var bool $darf @var int $maxMb
 *  @var string $q @var bool $alleVersionen @var array $treffer @var array $kiDaten @var int $textOffen */
$q ??= ''; $alleVersionen ??= false; $treffer ??= []; $kiDaten ??= ['ergebnisse' => [], 'auftraege' => []]; $textOffen ??= 0;
$kiZeile = static fn(array $d): string => function_exists('ki_hinweis_html') ? ki_hinweis_html('stan', $d, $kiDaten, $darf) : '';
$baum = stan_baum($dateien);
$istAktuell = $gezeigt && $aktuell && (int)$gezeigt['id'] === (int)$aktuell['id'];
$icon = static function (array $d): string {
    $e = strtolower(pathinfo((string)$d['name'], PATHINFO_EXTENSION));
    return match (true) {
        $e === 'pdf' => '📄',
        in_array($e, ['png', 'jpg', 'jpeg', 'webp'], true) => '🖼',
        in_array($e, ['xlsx', 'xls', 'ods', 'csv'], true) => '📊',
        default => '📝',
    };
};
?>
<div class="pagehead">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Die Stärke- und Ausstattungsnachweisung als Dokumentenpaket. Jedes hochgeladene ZIP wird eine neue Version;
       ältere Versionen bleiben zum Nachschlagen erhalten.</p>
  </div>
  <?php if ($gezeigt && stan_zip_pfad($gezeigt) !== null): ?>
    <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('stan_zip', ['id' => $gezeigt['id']])) ?>">⬇ <?= e(stan_version_text($gezeigt)) ?> als ZIP</a></div>
  <?php endif; ?>
</div>

<?php if (!$versionen): ?>
  <div class="card"><div class="empty">Noch kein Paket hochgeladen.<?= $darf ? ' Unten das ZIP mit allen PDFs auswählen – es wird Version 1.' : '' ?></div></div>
<?php else: ?>
  <div class="card">
    <div class="card__head"><h2>Versionen</h2><span class="muted small"><?= count($versionen) ?></span></div>
    <div class="chips">
      <?php foreach ($versionen as $v): ?>
        <a class="chip<?= $gezeigt && (int)$v['id'] === (int)$gezeigt['id'] ? ' is-on' : '' ?>" href="<?= e(url('stan', ['version' => $v['id']])) ?>" title="<?= e(de_datetime((string)$v['created_at']) . ($v['hochgeladen_von'] ? ' · ' . $v['hochgeladen_von'] : '') . ' · ' . (int)$v['anzahl'] . ' Datei(en)') ?>">
          <?= e(stan_version_text($v)) ?><?= (int)$v['id'] === (int)$aktuell['id'] ? ' <span class="badge">aktuell</span>' : '' ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="card" id="suche">
    <div class="card__head"><h2>Suchen</h2><span class="muted small">Dateiname, Ordner, Inhalt<?= function_exists('ki_modul_aktiv') && ki_modul_aktiv('stan') ? ', KI-Schlagwörter' : '' ?></span></div>
    <form method="get" action="<?= e(url()) ?>" class="form">
      <input type="hidden" name="p" value="stan">
      <?php if ($gezeigt): ?><input type="hidden" name="version" value="<?= (int)$gezeigt['id'] ?>"><?php endif; ?>
      <div class="btnrow">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="z. B. Fachgruppe N, Notstromaggregat, 50 kVA" style="flex:1 1 14rem">
        <label class="chip<?= $alleVersionen ? ' is-on' : '' ?>"><input type="checkbox" name="alle" value="1"<?= $alleVersionen ? ' checked' : '' ?>> alle Versionen</label>
        <button class="btn" type="submit">Suchen</button>
      </div>
    </form>
    <?php if ($textOffen > 0): ?><p class="small muted" style="margin-top:.4rem">⏳ Bei <?= (int)$textOffen ?> Datei(en) wird der Inhalt noch gelesen – der minütliche Abruf trägt ihn nach, dann findet die Suche auch darin.</p><?php endif; ?>
    <?php if ($q !== ''): ?>
      <h3 style="margin-top:.8rem">Treffer für „<?= e($q) ?>" <span class="muted small"><?= count($treffer) ?><?= $alleVersionen ? ' in allen Versionen' : ($gezeigt ? ' in ' . e(stan_version_text($gezeigt)) : '') ?></span></h3>
      <?php if (!$treffer): ?>
        <div class="empty">Nichts gefunden.<?= !$alleVersionen && count($versionen) > 1 ? ' Vielleicht in einer anderen Version – Haken „alle Versionen" setzen.' : '' ?></div>
      <?php else: ?>
        <div class="doclist">
          <?php foreach ($treffer as $t): ?>
            <div class="doc">
              <span class="doc__icon" aria-hidden="true"><?= $icon($t) ?></span>
              <div class="doc__text"><a href="<?= e(url('stan_file', ['id' => $t['id']])) ?>" target="_blank" rel="noopener"><?= e((string)$t['name']) ?></a>
                <span class="badge badge--outline"><?= e((string)$t['wo']) ?></span>
                <div class="small muted"><?= e('Version ' . (int)$t['version_nummer'] . (dirname((string)$t['pfad']) !== '.' ? ' · 📁 ' . dirname((string)$t['pfad']) : '') . ' · ' . bytes_human((int)$t['size_bytes'])) ?></div>
                <?php if ($t['ausschnitt'] !== ''): ?><div class="small"><?= e((string)$t['ausschnitt']) ?></div><?php endif; ?>
                <?= $kiZeile($t) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card" id="dateien">
    <div class="card__head">
      <h2><?= e(stan_version_text($gezeigt)) ?><?= $istAktuell ? ' <span class="badge">aktuell</span>' : ' <span class="badge badge--muted">ältere Version</span>' ?></h2>
      <span class="muted small"><?= e(de_datetime((string)$gezeigt['created_at'])) ?><?= $gezeigt['hochgeladen_von'] ? ' · ' . e((string)$gezeigt['hochgeladen_von']) : '' ?> · <?= count($dateien) ?> Datei(en) · <?= e(bytes_human((int)array_sum(array_column($dateien, 'size_bytes')))) ?> · aus <?= e((string)$gezeigt['orig_name']) ?></span>
    </div>
    <?php if (!$dateien): ?>
      <div class="empty">In dieser Version liegt keine Datei.</div>
    <?php else: ?>
      <?php foreach ($baum as $ordner => $liste): ?>
        <?php if ($ordner !== ''): ?><h3 class="small muted" style="margin:.8rem 0 .3rem">📁 <?= e($ordner) ?></h3><?php endif; ?>
        <div class="doclist">
          <?php foreach ($liste as $d): ?>
            <div class="doc">
              <span class="doc__icon" aria-hidden="true"><?= $icon($d) ?></span>
              <div class="doc__text"><a href="<?= e(url('stan_file', ['id' => $d['id']])) ?>" target="_blank" rel="noopener"><?= e((string)$d['name']) ?></a>
                <div class="small muted"><?= e(bytes_human((int)$d['size_bytes'])) ?></div><?= $kiZeile($d) ?></div>
              <?php if ($darf): ?>
                <div class="doc__aktion">
                  <form method="post" action="<?= e(url('stan', ['version' => $gezeigt['id']])) ?>" class="inline-form"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="datei_weg"><input type="hidden" name="datei_id" value="<?= (int)$d['id'] ?>">
                    <button class="btn btn--sec btn--sm" type="submit" data-confirm="<?= e('„' . $d['name'] . '" aus ' . stan_version_text($gezeigt) . ' entfernen?') ?>">Entfernen</button></form>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($darf): ?>
      <details class="mt">
        <summary>Dateien zu dieser Version nachreichen</summary>
        <form method="post" action="<?= e(url('stan', ['version' => $gezeigt['id']])) ?>" enctype="multipart/form-data" class="form upload" data-stueckweise="<?= e(url('stan_upload')) ?>" data-stueck-mb="<?= STAN_STUECK_MB ?>" data-ziel="nachreichen">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="nachreichen">
          <input type="hidden" name="version_id" value="<?= (int)$gezeigt['id'] ?>">
          <div class="grid2">
            <div class="field"><label>Datei(en)</label><input type="file" name="dateien[]" multiple required data-max-mb="<?= (int)$maxMb ?>"></div>
            <div class="field"><label>Ordner (optional)</label><input type="text" name="ordner" maxlength="200" placeholder="z. B. Fachgruppe N"></div>
          </div>
          <small class="muted">Einzelne PDFs, die im Paket fehlen oder sich geändert haben. Für ein ganz neues Paket lieber unten ein ZIP als neue Version hochladen.</small>
          <div class="btnrow" style="margin-top:.5rem"><button class="btn btn--sm" type="submit">Nachreichen</button></div>
          <div class="stan__fortschritt small" hidden></div>
        </form>
      </details>
      <details class="mt">
        <summary class="muted small">Diese Version löschen</summary>
        <form method="post" action="<?= e(url('stan')) ?>" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="version_weg"><input type="hidden" name="version_id" value="<?= (int)$gezeigt['id'] ?>">
          <button class="btn btn--sec btn--sm" type="submit" data-confirm="<?= e(stan_version_text($gezeigt) . ' mit allen ' . count($dateien) . ' Dateien und dem ZIP endgültig löschen?') ?>">Version löschen</button></form>
      </details>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($darf): ?>
<section class="card" id="paket">
  <div class="card__head"><h2>Neues Paket als Version <?= $aktuell ? (int)$aktuell['nummer'] + 1 : 1 ?> hochladen</h2></div>
  <form method="post" action="<?= e(url('stan')) ?>" enctype="multipart/form-data" class="form upload" data-stueckweise="<?= e(url('stan_upload')) ?>" data-stueck-mb="<?= STAN_STUECK_MB ?>" data-ziel="paket">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="paket">
    <div class="grid2">
      <div class="field"><label for="paket">ZIP mit den Dokumenten</label>
        <input type="file" id="paket" name="paket" accept=".zip,application/zip" required data-max-mb="<?= (int)$maxMb ?>"></div>
      <div class="field"><label for="bemerkung">Bemerkung (optional)</label>
        <input type="text" id="bemerkung" name="bemerkung" maxlength="200" placeholder="z. B. Stand 10/2026"></div>
    </div>
    <small class="muted">Erlaubt im Paket: <?= e(implode(', ', array_map('strtoupper', STAN_ERLAUBT))) ?>; Unterordner bleiben erhalten, Systemdateien werden übergangen.
      Höchstens <?= (int)$maxMb ?> MB je Upload (Einstellungen → Allgemein → „STAN-Paket höchstens … MB"). Der Browser überträgt in Stücken von <?= STAN_STUECK_MB ?> MB – so geht es auch über die Home-Assistant-Oberfläche, die nur 16 MB je Anfrage durchlässt. Große Pakete brauchen je nach Leitung einige Minuten, das Fenster offen lassen. Das ZIP bleibt zum Herunterladen liegen.</small>
    <div class="btnrow" style="margin-top:.5rem"><button class="btn" type="submit">Als neue Version einspielen</button></div>
    <div class="stan__fortschritt small" hidden></div>
  </form>
</section>
<?php endif; ?>
