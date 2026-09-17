<?php
/** @var array $vehicle @var array $fristen @var array $auftraege @var array $journal
 *  @var string $journalArt @var int $gesamt @var array $extraFields @var array $extra
 *  @var ?array $pruefung */
$verwalten = can('manage_vehicles');
$melden = can('report_vehicle');

$quellen = ['mensch' => '', 'stein' => 'Stein.APP', 'system' => 'System'];
$arten = [
    '' => 'alles',
    'notiz' => 'Notizen',
    'auftrag' => 'Aufträge',
    'stein' => 'Stein.APP',
    'stammdaten' => 'Stammdaten',
];
?>
<div class="pagehead">
  <div>
    <h1><?= e($vehicle['bezeichnung']) ?></h1>
    <p class="muted small">
      <?= e(implode(' · ', array_filter([
          $vehicle['funkrufname'], $vehicle['kennzeichen'], $vehicle['typ_label'], $vehicle['fachgruppe_label'],
      ]))) ?>
    </p>
    <p class="item__meta" style="margin-top:.3rem">
      <?= badge($vehicle['status_label'] ? ['label' => $vehicle['status_label'], 'color' => $vehicle['status_color']] : null, 'ohne Status') ?>
      <?php foreach ($fristen as $f): ?>
        <?php if ($f['status'] !== 'ok'): ?>
          <span class="badge" style="background:<?= $f['status'] === 'abgelaufen' ? '#b91c1c' : '#a16207' ?>">
            <?= e($f['label']) ?> <?= $f['status'] === 'abgelaufen' ? 'abgelaufen am ' . e(de_date($f['datum'])) : 'bis ' . e(de_date($f['datum'])) ?>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($vehicle['stein_asset_id']): ?>
        <span class="badge badge--outline">Stein.APP<?= $vehicle['stein_sync_at'] ? ' · ' . e(de_datetime($vehicle['stein_sync_at'])) : '' ?></span>
      <?php endif; ?>
      <?php if (!(int)$vehicle['is_active']): ?><span class="badge badge--muted">ausgemustert</span><?php endif; ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if ($melden): ?>
      <a class="btn" href="<?= e(url('vehicle_order_edit', ['vehicle_id' => $vehicle['id']])) ?>">+ Auftrag / Meldung</a>
    <?php endif; ?>
    <?php if ($verwalten): ?>
      <a class="btn btn--sec" href="<?= e(url('vehicle_edit', ['id' => $vehicle['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('vehicles')) ?>">Alle Fahrzeuge</a>
  </div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Stammdaten</h2>
    <dl class="dl">
      <?php
      $zeilen = [
          'Bezeichnung'       => $vehicle['bezeichnung'],
          'Funkrufname'       => $vehicle['funkrufname'],
          'ISSI'              => $vehicle['issi'],
          'Kennzeichen'       => $vehicle['kennzeichen'],
          'Kennung'           => $vehicle['kennung'],
          'Art'               => $vehicle['typ_label'],
          'Fachgruppe'        => $vehicle['fachgruppe_label'],
          'Hersteller / Modell' => trim((string)$vehicle['hersteller'] . ' ' . (string)$vehicle['modell']),
          'Baujahr'           => $vehicle['baujahr'],
          'Erstzulassung'     => $vehicle['erstzulassung'] ? de_date($vehicle['erstzulassung']) : '',
          'Fahrgestellnummer' => $vehicle['fahrgestellnummer'],
          'Kilometerstand'    => $vehicle['km_stand'] !== null ? number_format((float)$vehicle['km_stand'], 0, ',', '.') . ' km' : '',
          'Betriebsstunden'   => $vehicle['betriebsstunden'] !== null ? (int)$vehicle['betriebsstunden'] . ' h' : '',
          'Standort'          => $vehicle['standort'],
      ];
      foreach ($extraFields as $f) {
          $zeilen[$f['label']] = $f['type'] === 'bool'
              ? (!empty($extra[$f['key']]) ? 'ja' : 'nein')
              : (string)($extra[$f['key']] ?? '');
      }
      foreach ($zeilen as $label => $wert):
          if (trim((string)$wert) === '') { continue; } ?>
        <div class="dl__item"><div class="dl__label"><?= e((string)$label) ?></div>
          <div class="dl__value"><?= e((string)$wert) ?></div></div>
      <?php endforeach; ?>
    </dl>
    <?php if ($vehicle['notiz']): ?>
      <h3 class="mt">Bemerkung</h3>
      <div class="comment__body"><?= e((string)$vehicle['notiz']) ?></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Fristen</h2>
    <?php if (!$fristen): ?>
      <div class="empty">Keine Fristen hinterlegt.
        <?php if ($verwalten): ?><br><a href="<?= e(url('vehicle_edit', ['id' => $vehicle['id']])) ?>">HU, SP und UVV eintragen</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="data">
          <tbody>
          <?php foreach ($fristen as $f): ?>
            <tr>
              <td><strong><?= e($f['label']) ?></strong></td>
              <td class="nowrap"><?= e(de_date($f['datum'])) ?></td>
              <td>
                <?php if ($f['status'] === 'abgelaufen'): ?>
                  <span class="badge" style="background:#b91c1c">seit <?= abs($f['tage']) ?> Tagen abgelaufen</span>
                <?php elseif ($f['status'] === 'bald'): ?>
                  <span class="badge" style="background:#a16207">in <?= (int)$f['tage'] ?> Tagen fällig</span>
                <?php else: ?>
                  <span class="badge" style="background:#15803d">in <?= (int)$f['tage'] ?> Tagen</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($vehicle['stein_asset_id']): ?>
      <h3 class="mt">Stand in der Stein.APP</h3>
      <?php
      $stein = json_decode((string)($vehicle['stein_daten'] ?? ''), true);
      $stein = is_array($stein) ? $stein : [];
      $steinZeilen = [
          'Status'           => stein_value_text('status', $stein['status'] ?? null),
          'Kategorie'        => (string)($stein['category'] ?? ''),
          'ISSI'             => (string)($stein['issi'] ?? ''),
          'Einsatzvorbehalt' => isset($stein['operationReservation']) ? stein_value_text('operationReservation', $stein['operationReservation']) : '',
          'Bemerkung'        => (string)($stein['comment'] ?? ''),
          'HU gültig bis'    => stein_value_text('huValidUntil', $stein['huValidUntil'] ?? null),
          'SP gültig bis'    => stein_value_text('spValidUntil', $stein['spValidUntil'] ?? null),
      ];
      if (!empty($stein['deleted'])) {
          $steinZeilen['Hinweis'] = 'In der Stein.APP gelöscht';
      }
      $steinZeilen = array_filter($steinZeilen, static fn($v) => trim((string)$v) !== '');
      ?>
      <?php if ($steinZeilen): ?>
        <dl class="dl">
          <?php foreach ($steinZeilen as $label => $wert): ?>
            <div class="dl__item"><div class="dl__label"><?= e((string)$label) ?></div>
              <div class="dl__value"><?= e((string)$wert) ?></div></div>
          <?php endforeach; ?>
        </dl>
      <?php endif; ?>
      <p class="small muted">
        Verknüpft mit <span class="mono"><?= e((string)$vehicle['stein_asset_id']) ?></span>.
        <?php if ($vehicle['stein_sync_at']): ?>Zuletzt abgeglichen am <?= e(de_datetime($vehicle['stein_sync_at'])) ?>.<?php endif; ?>
        <?php if (!empty($stein['lastModifiedBy'])): ?>
          Zuletzt geändert dort von <?= e((string)$stein['lastModifiedBy']) ?><?php if (!empty($stein['lastModified'])): ?>
          am <?= e(de_datetime(str_replace('T', ' ', substr((string)$stein['lastModified'], 0, 19)))) ?><?php endif; ?>.
        <?php endif; ?>
        Änderungen stehen unten im Journal.
      </p>
      <?php if (can('admin') && $stein): ?>
        <details>
          <summary class="small">Rohdaten aus der Stein.APP</summary>
          <pre class="small" style="overflow-x:auto"><?= e(json_encode($stein, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
      <?php endif; ?>
      <?php if (can('manage_vehicles')): ?>
        <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="stein_unassign">
          <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
          <button class="btn btn--sec btn--sm" type="submit"
                  data-confirm="Verknüpfung mit der Stein.APP lösen? Der Abgleich fasst dieses Fahrzeug dann nicht mehr an.">
            Verknüpfung lösen</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<section class="card" id="auftraege">
  <div class="card__head">
    <h2>Instandsetzungsaufträge</h2>
    <?php if ($melden): ?>
      <a class="btn btn--sm" href="<?= e(url('vehicle_order_edit', ['vehicle_id' => $vehicle['id']])) ?>">+ Auftrag</a>
    <?php endif; ?>
  </div>
  <?php if (!$auftraege): ?>
    <div class="empty">Kein Auftrag erfasst.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Nr.</th><th>Vorgang</th><th>Art</th><th>Priorität</th><th>Status</th><th class="nowrap">Gemeldet</th></tr></thead>
        <tbody>
        <?php foreach ($auftraege as $o): $fertig = (int)$o['status_final'] === 1; ?>
          <tr<?= $fertig ? ' class="muted"' : '' ?>>
            <td class="mono small nowrap"><a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['nummer']) ?></a></td>
            <td>
              <a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['titel']) ?></a>
              <?php if ((int)$o['ausfall']): ?> <span class="badge" style="background:#b91c1c">Ausfall</span><?php endif; ?>
            </td>
            <td class="small"><?= e((string)$o['art_label']) ?></td>
            <td><?= badge($o['prio_label'] ? ['label' => $o['prio_label'], 'color' => $o['prio_color']] : null) ?></td>
            <td><?= badge($o['status_label'] ? ['label' => $o['status_label'], 'color' => $o['status_color']] : null) ?></td>
            <td class="small nowrap"><?= e(de_date($o['gemeldet_am'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="journal">
  <div class="card__head">
    <h2>Journal der Fahrzeugakte</h2>
    <span class="small muted"><?= (int)$gesamt ?> Einträge · wird nur ergänzt, nie geändert</span>
  </div>

  <?php if ($pruefung !== null): ?>
    <div class="alert alert--<?= $pruefung['ok'] ? 'success' : 'warn' ?>">
      <?php if ($pruefung['ok']): ?>
        <strong>Journal unverändert.</strong> <?= (int)$pruefung['geprueft'] ?> Einträge geprüft, die Kette der Prüfsummen stimmt.
      <?php else: ?>
        <strong>Achtung:</strong> <?= count($pruefung['fehler']) ?> Auffälligkeit(en) bei <?= (int)$pruefung['geprueft'] ?> Einträgen.
        <ul>
          <?php foreach ($pruefung['fehler'] as $f): ?>
            <li>Eintrag #<?= (int)$f['id'] ?>: <?= e($f['grund']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tabs">
    <?php foreach ($arten as $slug => $label): ?>
      <a class="tab<?= $journalArt === $slug ? ' is-active' : '' ?>"
         href="<?= e(url('vehicle', array_filter(['id' => $vehicle['id'], 'art' => $slug]))) ?>#journal"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($melden): ?>
    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="form" style="margin-bottom:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="note">
      <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
      <div class="field">
        <label for="text">Eintrag hinzufügen</label>
        <textarea id="text" name="text" rows="2" required
                  placeholder="Was ist passiert? Der Eintrag lässt sich später nicht mehr ändern."></textarea>
      </div>
      <div class="btnrow"><button class="btn btn--sm" type="submit">Ins Journal schreiben</button></div>
    </form>
  <?php endif; ?>

  <?php if (!$journal): ?>
    <div class="empty">Noch kein Eintrag.</div>
  <?php else: ?>
    <div class="journal">
      <?php foreach ($journal as $j): ?>
        <div class="journal__row journal__row--<?= e($j['quelle']) ?>">
          <div class="journal__when">
            <?= e(de_datetime($j['created_at'])) ?>
            <div class="small muted">
              <?= e($j['autor'] ?: ($quellen[$j['quelle']] ?? '')) ?: '–' ?>
            </div>
          </div>
          <div class="journal__what">
            <strong><?= e($j['titel'] ?: ucfirst((string)$j['art'])) ?></strong>
            <?php if ($j['quelle'] !== 'mensch'): ?>
              <span class="badge badge--outline"><?= e($quellen[$j['quelle']] ?? $j['quelle']) ?></span>
            <?php endif; ?>
            <?php if ($j['feld'] && ($j['alt_wert'] !== '' || $j['neu_wert'] !== '')): ?>
              <div class="small">
                <span class="muted"><?= e($j['alt_wert'] !== '' ? $j['alt_wert'] : '(leer)') ?></span>
                → <strong><?= e($j['neu_wert'] !== '' ? $j['neu_wert'] : '(leer)') ?></strong>
              </div>
            <?php endif; ?>
            <?php if (trim((string)$j['text']) !== ''): ?>
              <div class="comment__body"><?= e((string)$j['text']) ?></div>
            <?php endif; ?>
            <?php if ($j['ref_typ'] === 'auftrag' && $j['ref_id']): ?>
              <a class="small" href="<?= e(url('vehicle_order', ['id' => $j['ref_id']])) ?>">zum Auftrag</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($gesamt > count($journal)): ?>
      <p class="small muted">Die <?= count($journal) ?> jüngsten von <?= (int)$gesamt ?> Einträgen –
        <a href="<?= e(url('vehicle', ['id' => $vehicle['id'], 'alles' => 1])) ?>#journal">alle anzeigen</a></p>
    <?php endif; ?>
  <?php endif; ?>

  <p class="small muted" style="margin-top:.8rem">
    <a href="<?= e(url('vehicle', ['id' => $vehicle['id'], 'pruefen' => 1])) ?>#journal">Journal auf Veränderungen prüfen</a>
    – jeder Eintrag trägt die Prüfsumme des vorherigen.
  </p>
</section>
