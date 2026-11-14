<?php
/** @var array $einsatz @var array $tage @var array $summen @var array $jeFachgruppe @var array $jeFahrzeug
 *  @var array $dateien @var array $karten @var array $buchungen @var array $geld @var bool $mitGeld @var int $uploadMaxMb */

$id = (int)$einsatz['id'];
$darf = can('manage_einsaetze');
$buchen = $mitGeld && can('manage_budget');
$jeFachgruppe ??= [];
$jeFahrzeug ??= [];
$dateien ??= [];
$karten ??= [];
$uploadMaxMb ??= 32;
$kmGesamt = round(array_sum(array_column($jeFahrzeug, 'km')), 1);
$bhGesamt = round(array_sum(array_column($jeFahrzeug, 'bh')), 1);
$leistung = implode(' · ', array_filter([$kmGesamt > 0 ? einsatz_km_text($kmGesamt) : '', $bhGesamt > 0 ? einsatz_bh_text($bhGesamt) : '']));
$mitFahrzeugen = (bool)$jeFahrzeug;
$mitFachgruppen = count(array_filter(array_keys($jeFachgruppe))) > 0;
$auftraege = array_values(array_filter($dateien, static fn($d) => (string)$d['herkunft'] === 'auftrag'));
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$einsatz['titel']) ?></h1>
    <p><?php if (trim((string)$einsatz['nummer']) !== ''): ?><span class="mono"><?= e((string)$einsatz['nummer']) ?></span> · <?php endif; ?>
      <?= e(einsatz_zeitraum_text($einsatz)) ?>
      <?= trim((string)$einsatz['ort']) !== '' ? ' · ' . e((string)$einsatz['ort']) : '' ?></p>
    <p><?= einsatz_status_badge((string)$einsatz['status']) ?>
      <?php if (!empty($einsatz['art_label'])): ?><?= badge(['label' => $einsatz['art_label'], 'color' => $einsatz['art_color']]) ?><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('einsatz_edit', ['id' => $id])) ?>">Bearbeiten</a>
      <a class="btn btn--sec" href="<?= e(url('einsatz_auftrag', ['einsatz_id' => $id])) ?>">+ Einsatzauftrag</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('einsaetze')) ?>">Alle Einsätze</a>
  </div>
</div>

<?php if (trim((string)($einsatz['beschreibung'] ?? '')) !== ''): ?>
  <div class="card"><?= nl2br(e((string)$einsatz['beschreibung'])) ?></div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Einsatztage</div>
    <div class="stat__value"><?= (int)$summen['tage'] ?></div>
    <div class="stat__hint"><?= (int)$summen['zeilen'] > (int)$summen['tage'] ? (int)$summen['zeilen'] . ' Zeilen' : 'vom ' . e(de_date(substr((string)$einsatz['beginn'], 0, 10))) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Einsatzstunden</div>
    <div class="stat__value"><?= e(einsatz_stunden_text((int)$summen['minuten'])) ?></div>
    <div class="stat__hint"><?= (int)$summen['ohne_dauer'] > 0
        ? ((int)$summen['ohne_dauer'] === 1 ? 'eine Zeile' : (int)$summen['ohne_dauer'] . ' Zeilen') . ' noch ohne Ende'
        : 'Summe über alle Zeilen' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Helferstunden</div>
    <div class="stat__value"><?= e(einsatz_stunden_text((int)$summen['helferminuten'])) ?></div>
    <div class="stat__hint">Dauer mal Stärke<?= (int)$summen['staerke_max'] > 0 ? ' · Stärke bis ' . e((string)$summen['staerke_max_text']) : '' ?></div>
  </div>
  <?php if ($mitFahrzeugen): ?>
    <div class="stat">
      <div class="stat__label">Fahrzeuge</div>
      <div class="stat__value"><?= count($jeFahrzeug) ?></div>
      <div class="stat__hint"><?= $leistung !== '' ? e($leistung) : 'Kilometer und Betriebsstunden nicht eingetragen' ?></div>
    </div>
  <?php endif; ?>
  <?php if ($mitGeld): ?>
    <div class="stat">
      <div class="stat__label">Ausgaben</div>
      <div class="stat__value"><?= e(money((float)$geld['ausgaben'])) ?></div>
      <div class="stat__hint"><?= (float)$geld['ausgaben_geplant'] > 0 ? 'dazu geplant ' . e(money((float)$geld['ausgaben_geplant'])) : 'bezahlt und gebucht' ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Einnahmen</div>
      <div class="stat__value"><?= e(money((float)$geld['einnahmen'])) ?></div>
      <div class="stat__hint"><?= (float)$geld['einnahmen_offen'] > 0 ? 'noch unterwegs ' . e(money((float)$geld['einnahmen_offen'])) : 'eingegangen' ?></div>
    </div>
  <?php endif; ?>
</div>

<div class="card" id="tage">
  <div class="card__head">
    <h2>Tage, Fachgruppen und Fahrzeuge</h2>
    <?php if ($darf): ?><a class="btn btn--sec btn--sm" href="<?= e(url('einsatz_edit', ['id' => $id])) ?>#tage">Zeilen bearbeiten</a><?php endif; ?>
  </div>
  <?php if (!$tage): ?>
    <div class="empty">Noch kein Tag eingetragen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Tag</th><th>Zeit</th><th class="num">Dauer</th><th>Fachgruppe</th><th>Stärke</th><th class="num">Helferstunden</th><th>Fahrzeuge</th><th>Tätigkeit</th></tr></thead>
        <tbody>
        <?php $letzterTag = ''; foreach ($tage as $t): $min = einsatz_tag_minuten($t); $st = einsatz_tag_staerke($t);
              $von = einsatz_zeit($t['von'] ?? ''); $bis = einsatz_zeit($t['bis'] ?? ''); $gleicherTag = (string)$t['datum'] === $letzterTag; $letzterTag = (string)$t['datum']; ?>
          <tr>
            <td class="nowrap"><?= $gleicherTag ? '<span class="muted" title="' . e(einsatz_tag_text((string)$t['datum'])) . '">″</span>' : e(einsatz_tag_text((string)$t['datum'])) ?></td>
            <td class="nowrap small"><?php if (is_string($von)): ?><?= e($von) ?> – <?= is_string($bis) ? e($bis) . ($bis <= $von ? ' <span class="muted" title="endet am Folgetag">+1</span>' : '') : '<span class="muted">offen</span>' ?><?php else: ?><span class="muted">ganztägig</span><?php endif; ?></td>
            <td class="num"><?= $min !== null ? e(einsatz_stunden_text($min)) : '–' ?></td>
            <td><?= !empty($t['fachgruppe_label']) ? badge(['label' => $t['fachgruppe_label'], 'color' => $t['fachgruppe_color'] ?? '']) : '<span class="muted">–</span>' ?></td>
            <td class="nowrap"><?= $st['gesamt'] > 0 ? '<span class="mono">' . e($st['text']) . '</span>' : '–' ?></td>
            <td class="num"><?= $min !== null && $st['gesamt'] > 0 ? e(einsatz_stunden_text($min * $st['gesamt'])) : '–' ?></td>
            <td class="small"><?php if (empty($t['fahrzeuge'])): ?><span class="muted">–</span><?php else: foreach ($t['fahrzeuge'] as $i => $v): ?><?= $i > 0 ? '<br>' : '' ?><?= e((string)$v['bezeichnung']) ?><?= ($l = einsatz_fahrzeug_leistung($v)) !== '' ? ' <span class="muted">· ' . e($l) . '</span>' : '' ?><?php endforeach; endif; ?></td>
            <td class="small"><?= nl2br(e((string)$t['taetigkeit'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="2"><?= (int)$summen['tage'] === 1 ? '1 Einsatztag' : (int)$summen['tage'] . ' Einsatztage' ?></th>
          <th class="num"><?= e(einsatz_stunden_text((int)$summen['minuten'])) ?></th>
          <th colspan="2"></th>
          <th class="num"><?= e(einsatz_stunden_text((int)$summen['helferminuten'])) ?></th>
          <th><?= e($leistung) ?></th>
          <th></th>
        </tr></tfoot>
      </table>
    </div>
    <p class="small muted" style="margin-bottom:0">Eine Zeile ist die Einsatzzeit einer Einheit an einem Tag; mehrere Fachgruppen am selben Tag stehen untereinander.
      Stärke wie gemeldet: Führer / Unterführer / Helfer / gesamt. Helferstunden sind Dauer mal Gesamtstärke der Zeile.</p>
  <?php endif; ?>
</div>

<?php if ($mitFachgruppen || $mitFahrzeugen): ?>
<div class="grid2">
  <?php if ($mitFachgruppen): ?>
  <div class="card" id="fachgruppen">
    <h2>Je Fachgruppe</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fachgruppe</th><th class="num">Tage</th><th class="num">Stunden</th><th class="num">Helferstunden</th></tr></thead>
        <tbody>
        <?php foreach ($jeFachgruppe as $fg): ?>
          <tr><td><?= e((string)$fg['label']) ?></td><td class="num"><?= (int)$fg['tage'] ?></td>
            <td class="num"><?= e(einsatz_stunden_text((int)$fg['minuten'])) ?></td><td class="num"><?= e(einsatz_stunden_text((int)$fg['helferminuten'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($mitFahrzeugen): ?>
  <div class="card" id="fahrzeuge">
    <h2>Je Fahrzeug</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fahrzeug</th><th class="num">Tage</th><th class="num">Einsatzzeit</th><th class="num">Kilometer</th><th class="num">Betriebsstunden</th></tr></thead>
        <tbody>
        <?php foreach ($jeFahrzeug as $vid => $fz): ?>
          <tr><td><a href="<?= e(url('vehicle', ['id' => (int)$vid])) ?>"><?= e((string)$fz['bezeichnung']) ?></a></td><td class="num"><?= (int)$fz['tage'] ?></td>
            <td class="num"><?= (int)$fz['minuten'] > 0 ? e(einsatz_stunden_text((int)$fz['minuten'])) : '–' ?></td>
            <td class="num"><?= (float)$fz['km'] > 0 ? e(einsatz_km_text((float)$fz['km'])) : '–' ?></td>
            <td class="num"><?= (float)($fz['bh'] ?? 0) > 0 ? e(einsatz_bh_text((float)$fz['bh'])) : '–' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($kmGesamt > 0 || $bhGesamt > 0): ?><tfoot><tr><th colspan="3">Insgesamt</th><th class="num"><?= $kmGesamt > 0 ? e(einsatz_km_text($kmGesamt)) : '–' ?></th><th class="num"><?= $bhGesamt > 0 ? e(einsatz_bh_text($bhGesamt)) : '–' ?></th></tr></tfoot><?php endif; ?>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Eckdaten</h2>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Einsatznummer</div>
      <div class="dl__value"><?= trim((string)$einsatz['nummer']) !== '' ? '<span class="mono">' . e((string)$einsatz['nummer']) . '</span>' : '–' ?></div></div>
    <div class="dl__item"><div class="dl__label">Anforderer / Kostenträger</div>
      <div class="dl__value"><?= e(trim((string)$einsatz['anforderer']) !== '' ? (string)$einsatz['anforderer'] : '–') ?></div></div>
    <div class="dl__item"><div class="dl__label">Einsatzort</div>
      <div class="dl__value"><?= e(trim((string)$einsatz['ort']) !== '' ? (string)$einsatz['ort'] : '–') ?></div></div>
    <div class="dl__item"><div class="dl__label">Federführend</div>
      <div class="dl__value"><?= e((string)($einsatz['fachgruppe_label'] ?? '') !== '' ? (string)$einsatz['fachgruppe_label'] : 'ortsverbandsweit') ?></div></div>
    <div class="dl__item"><div class="dl__label">Erfasst</div>
      <div class="dl__value"><?= e(de_datetime((string)$einsatz['created_at'])) ?><?= !empty($einsatz['ersteller']) ? ' · ' . e((string)$einsatz['ersteller']) : '' ?></div></div>
  </dl>
  <?php if (trim((string)($einsatz['notiz'] ?? '')) !== ''): ?>
    <p class="small muted"><?= nl2br(e((string)$einsatz['notiz'])) ?></p>
  <?php endif; ?>
</div>

<div class="card" id="dateien">
  <div class="card__head">
    <h2>Einsatzaufträge und Dokumente</h2>
    <?php if ($darf): ?><a class="btn btn--sec btn--sm" href="<?= e(url('einsatz_auftrag', ['einsatz_id' => $id])) ?>">+ Einsatzauftrag (Fb Fü 3)</a><?php endif; ?>
  </div>
  <?php if (!$dateien): ?>
    <div class="empty">Noch kein Dokument. Ein Einsatzauftrag wird hier als PDF abgelegt, sobald er gespeichert ist;
      eine Lagekarte kommt aus dem Kartenmodul über „In den Einsatz speichern".</div>
  <?php else: ?>
    <div class="doclist">
      <?php foreach ($dateien as $f): $name = (string)($f['titel'] ?: $f['orig_name']); $herkunft = (string)$f['herkunft']; ?>
        <div class="doc" style="align-items:flex-start">
          <?php if (!empty($f['thumb_name'])): ?>
            <a href="<?= e(url('einsatz_datei', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><img class="doc__thumb" src="<?= e(url('einsatz_datei', ['id' => $f['id'], 'vorschau' => 1])) ?>" alt="" loading="lazy"></a>
          <?php else: ?>
            <span class="doc__icon" aria-hidden="true"><?= e(strtoupper(pathinfo((string)$f['orig_name'], PATHINFO_EXTENSION) ?: '?')) ?></span>
          <?php endif; ?>
          <div class="doc__text">
            <a href="<?= e(url('einsatz_datei', ['id' => $f['id']])) ?>" target="_blank" rel="noopener"><?= e($name) ?></a>
            <?php if ($herkunft !== 'upload'): ?><span class="badge badge--outline"><?= e(EINSATZ_DATEI_HERKUNFT[$herkunft] ?? $herkunft) ?></span><?php endif; ?>
            <div class="small muted"><?= e(bytes_human((int)$f['size_bytes']) . ' · ' . de_datetime((string)$f['created_at'])
                . (!empty($f['hochgeladen_von']) ? ' · ' . $f['hochgeladen_von'] : '')
                . ($herkunft === 'karte' && !empty($f['karte_name']) ? ' · Karte „' . $f['karte_name'] . '"' : '')) ?></div>
          </div>
          <?php if ($darf): ?>
          <div class="doc__aktion btnrow">
            <?php if ($herkunft === 'auftrag'): ?>
              <a class="btn btn--sec btn--sm" href="<?= e(url('einsatz_auftrag', ['einsatz_id' => $id, 'vorlage' => $f['id']])) ?>" title="Neuer Einsatzauftrag mit den Texten von diesem">Als Vorlage</a>
            <?php endif; ?>
            <form method="post" action="<?= e(url('einsatz_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="datei_weg">
              <input type="hidden" name="datei_id" value="<?= (int)$f['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit" data-confirm="„<?= e($name) ?>“ entfernen?">Entfernen</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($darf): ?>
    <details class="mt">
      <summary>Dokument oder Foto hochladen</summary>
      <form method="post" class="form mt" enctype="multipart/form-data" action="<?= e(url('einsatz_action')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="einsatz_id" value="<?= $id ?>">
        <div class="grid2">
          <div class="field">
            <label for="dateien_feld">Dateien</label>
            <?= upload_hinweis_html() ?><input type="file" id="dateien_feld" name="dateien[]" multiple required>
            <small class="muted">Mehrere auf einmal möglich, je höchstens <?= (int)$uploadMaxMb ?> MB – Lagemeldung, Anforderung, Foto.</small>
          </div>
          <div class="field">
            <label for="datei_titel">Bezeichnung <span class="muted">(freiwillig)</span></label>
            <input type="text" id="datei_titel" name="titel" maxlength="200" placeholder="sonst der Dateiname">
          </div>
        </div>
        <button class="btn btn--sec" type="submit">Hochladen</button>
      </form>
    </details>
  <?php endif; ?>
  <p class="small muted" style="margin-bottom:0">Alle Dokumente des Einsatzes stehen auch in der Dateiablage unter <?= e((string)setting('einsatz_modul_name', 'Einsätze')) ?>.
    <?= count($auftraege) > 0 ? (count($auftraege) === 1 ? 'Ein Einsatzauftrag ist' : count($auftraege) . ' Einsatzaufträge sind') . ' gespeichert.' : '' ?></p>
</div>

<?php if (can('view_karten')): ?>
<div class="card" id="karten">
  <div class="card__head">
    <h2>Karten</h2>
    <?php if (can('manage_karten')): ?><a class="btn btn--sec btn--sm" href="<?= e(url('karte_edit', ['einsatz_id' => $id])) ?>">+ Karte zum Einsatz</a><?php endif; ?>
  </div>
  <?php if (!$karten): ?>
    <div class="empty">Diesem Einsatz ist noch keine Karte zugeordnet. Eine Karte gehört zu einem Einsatz, wenn er beim Bearbeiten der Karte gewählt ist –
      dann lässt sich die Lage von dort als PDF hierher speichern.</div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($karten as $k): ?>
        <div class="item">
          <div class="item__top"><div style="min-width:0">
            <div class="item__title"><a href="<?= e(url('karte', ['id' => $k['id']])) ?>">🗺 <?= e((string)$k['name']) ?></a></div>
            <div class="item__sub"><?= (int)$k['marker'] === 1 ? '1 Einheit' : (int)$k['marker'] . ' Einheiten' ?> · <?= (int)$k['protokoll'] === 1 ? '1 Eintrag' : (int)$k['protokoll'] . ' Einträge' ?> im Protokoll</div>
          </div><div class="btnrow"><a class="btn btn--sec btn--sm" href="<?= e(url('karte_protokoll', ['id' => $k['id']])) ?>">Protokoll und Einsatzzeiten</a></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($mitGeld): ?>
<div class="card" id="geld">
  <div class="card__head">
    <h2>Ausgaben und Einnahmen</h2>
    <?php if ($buchen): ?>
      <div class="btnrow">
        <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['art' => 'ausgabe', 'einsatz_id' => $id])) ?>">+ Ausgabe</a>
        <a class="btn btn--sec btn--sm" href="<?= e(url('expense_edit', ['art' => 'einnahme', 'einsatz_id' => $id])) ?>">+ Einnahme</a>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!$buchungen): ?>
    <div class="empty">Für diesen Einsatz ist noch nichts gebucht.
      <?= $buchen ? 'Mit „+ Ausgabe" und „+ Einnahme" entsteht die Buchung im Budget und trägt den Bezug schon mit.'
                  : 'Buchungen bekommen den Bezug im Budget beim Erfassen oder Bearbeiten.' ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Datum</th><th>Bezeichnung</th><th>Kategorie</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($buchungen as $b): $einnahme = (string)$b['art'] === 'einnahme'; ?>
          <tr<?= buchung_zeile_klasse((string)($b['status'] ?? 'bezahlt')) ?>>
            <td class="nowrap small"><?= e(de_date((string)$b['datum'])) ?></td>
            <td><?php if ($buchen): ?><a href="<?= e(url('expense_edit', ['id' => $b['id']])) ?>"><?= e((string)$b['bezeichnung']) ?></a><?php else: ?><?= e((string)$b['bezeichnung']) ?><?php endif; ?>
              <?= buchung_status_badge((string)($b['status'] ?? 'bezahlt'), $einnahme ? 'einnahme' : 'ausgabe') ?>
              <?php if (trim((string)$b['lieferant']) !== ''): ?><div class="small muted"><?= e((string)$b['lieferant']) ?></div><?php endif; ?>
              <?php if ($einnahme && ($weg = einnahme_weg($b)) !== ''): ?><div class="small muted"><?= e($weg) ?></div><?php endif; ?></td>
            <td><?= badge($b['kategorie_label'] ? ['label' => $b['kategorie_label'], 'color' => $b['kategorie_color']] : null, '–') ?></td>
            <td class="num" style="<?= $einnahme ? 'color:var(--ok)' : '' ?>"><strong><?= $einnahme ? '+' : '−' ?><?= e(money((float)$b['betrag_brutto'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3">Ausgaben <?= e(money((float)$geld['ausgaben'])) ?>, Einnahmen <?= e(money((float)$geld['einnahmen'])) ?></th>
            <th class="num"><?= e(money((float)$geld['saldo'])) ?></th>
          </tr>
          <?php if ((float)$geld['ausgaben_geplant'] > 0 || (float)$geld['einnahmen_offen'] > 0): ?>
          <tr>
            <td colspan="3" class="small muted">Mit dem, was noch aussteht – geplante Ausgaben <?= e(money((float)$geld['ausgaben_geplant'])) ?>,
              Einnahmen unterwegs <?= e(money((float)$geld['einnahmen_offen'])) ?></td>
            <td class="num small muted"><?= e(money((float)$geld['saldo_erwartet'])) ?></td>
          </tr>
          <?php endif; ?>
        </tfoot>
      </table>
    </div>
    <p class="small muted" style="margin-bottom:0">In der Summe zählt wie im Budget, was bezahlt oder gebucht ist –
      auch über den Jahreswechsel, wenn die Erstattung erst im Folgejahr kommt.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
