<?php
/** @var array $laufend @var array $uebrige @var array $jahre @var array $blatt @var array $blattParams
 *  @var ?int $jahr @var string $status @var ?int $art @var string $q @var bool $mitGeld */

$karte = static function (array $e) use ($mitGeld): string {
    $farbe = trim((string)($e['art_color'] ?? '')) !== '' ? (string)$e['art_color'] : EINSATZ_STATUS[einsatz_status((string)$e['status'])]['farbe'];
    $tage = (int)($e['tage'] ?? 0);
    $html = '<a class="item" href="' . e(url('einsatz', ['id' => $e['id']])) . '" style="border-left-color:' . e($farbe) . '">'
        . '<div class="item__top"><div style="min-width:0">'
        . '<div class="item__title">' . e(einsatz_bezeichnung($e)) . '</div>'
        . '<div class="item__sub">' . e(einsatz_zeitraum_text($e))
        . (trim((string)$e['ort']) !== '' ? ' · ' . e((string)$e['ort']) : '')
        . (trim((string)$e['anforderer']) !== '' ? ' · für ' . e((string)$e['anforderer']) : '') . '</div>'
        . '</div><div class="item__amount">' . ($tage === 1 ? '1 Tag' : $tage . ' Tage') . '</div></div>'
        . '<div class="item__meta">' . einsatz_status_badge((string)$e['status']);
    if (!empty($e['art_label'])) {
        $html .= badge(['label' => $e['art_label'], 'color' => $e['art_color']]);
    }
    if ((int)($e['staerke_max'] ?? 0) > 0) {
        $html .= '<span class="badge badge--outline">Stärke bis ' . (int)$e['staerke_max'] . '</span>';
    }
    if (!empty($e['fachgruppe_label'])) {
        $html .= '<span class="badge badge--outline">' . e((string)$e['fachgruppe_label']) . '</span>';
    }
    // je Fahrzeug ein Abzeichen – eines für alle brach nicht um und sprengte die Zeile
    foreach (array_filter(array_map('trim', explode(' · ', (string)($e['fahrzeuge_namen'] ?? ''))), 'strlen') as $fz) {
        $html .= '<span class="badge badge--outline badge--fahrzeug">' . e($fz) . '</span>';
    }
    if ($mitGeld && ((float)$e['ausgaben'] > 0 || (float)$e['einnahmen'] > 0)) {
        $erg = einsatz_ergebnis(['saldo' => (float)$e['einnahmen'] - (float)$e['ausgaben']]);
        $html .= '<span class="badge badge--outline">Ausgaben ' . e(money((float)$e['ausgaben'])) . ' · Einnahmen ' . e(money((float)$e['einnahmen'])) . '</span>'
            . '<span class="badge badge--outline' . ($erg['klasse'] !== '' ? ' badge--' . $erg['klasse'] : '') . '" title="Einnahmen minus Ausgaben">' . e($erg['wort']) . ' ' . e($erg['text']) . '</span>';
    }
    return $html . '</div></a>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('einsatz_modul_name', 'Einsätze')) ?></h1>
    <p><?= nl2br(e((string)setting('einsatz_intro', 'Einsätze des Ortsverbands: Tage und Schichten mit Stärke, eingesetzte Fahrzeuge, Ausgaben und Einnahmen an einer Stelle.'))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_einsaetze')): ?>
      <a class="btn" href="<?= e(url('einsatz_edit')) ?>">+ Einsatz</a>
    <?php endif; ?>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="einsaetze">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Titel, Nummer, Ort, Anforderer">
    </div>
    <div class="field">
      <label for="jahr">Jahr</label>
      <select id="jahr" name="jahr">
        <option value="">alle</option>
        <?php foreach ($jahre as $j): ?>
          <option value="<?= (int)$j ?>" <?= (int)$j === (int)$jahr ? 'selected' : '' ?>><?= (int)$j ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="art_id">Art</label>
      <select id="art_id" name="art_id"><?= list_options('einsatz_art', $art, 'alle') ?></select>
    </div>
    <div class="field">
      <label for="status">Stand</label>
      <select id="status" name="status">
        <option value="">alle</option>
        <?php foreach (EINSATZ_STATUS as $key => $s): ?>
          <option value="<?= e($key) ?>" <?= $key === $status ? 'selected' : '' ?>><?= e($s['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
</form>

<?php if ($laufend): ?>
<div class="card">
  <div class="card__head">
    <h2>Laufende Einsätze</h2>
    <span class="muted small"><?= count($laufend) ?></span>
  </div>
  <div class="itemlist">
    <?php foreach ($laufend as $e): ?><?= $karte($e) ?><?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" id="liste">
  <div class="card__head">
    <h2><?= $laufend ? 'Beendete Einsätze' : 'Einsätze' ?></h2>
    <span class="muted small"><?= (int)$blatt['gesamt'] ?></span>
  </div>
  <?php if (!$uebrige): ?>
    <div class="empty"><?= $laufend ? 'Sonst nichts.' : ($q !== '' || $jahr || $art || $status !== '' ? 'Kein Einsatz passt zu diesem Filter.' : 'Noch kein Einsatz erfasst.') ?>
      <?php if (!$laufend && can('manage_einsaetze') && $q === '' && !$jahr && !$art && $status === ''): ?>
        <br><a href="<?= e(url('einsatz_edit')) ?>">Ersten Einsatz erfassen</a>
      <?php endif; ?></div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($uebrige as $e): ?><?= $karte($e) ?><?php endforeach; ?>
    </div>
    <?= render_partial('partials/seiten', ['blatt' => $blatt, 'route' => 'einsaetze', 'params' => $blattParams, 'anker' => 'liste']) ?>
  <?php endif; ?>
</div>
