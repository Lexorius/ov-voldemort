<?php
/** @var array $vehicle @var array $fristen @var array $auftraege @var array $journal
 *  @var string $journalArt @var int $gesamt @var array $extraFields @var array $extra
 *  @var ?array $pruefung @var array $bilder @var array $dokumente @var ?array $titelbild */
$verwalten = can('manage_vehicles');
// Der QR-Zeichner wird nur geladen, wenn es hier auch einen Code gibt
$GLOBALS['ovb_qr_js'] = trim((string)($vehicle['qr_token'] ?? '')) !== '';
$melden = can('report_vehicle');

$quellen = ['mensch' => '', 'stein' => 'Stein.APP', 'divera' => 'Divera', 'system' => 'System'];
$arten = [
    '' => 'alles',
    'notiz' => 'Notizen',
    'auftrag' => 'Aufträge',
    'fms' => 'Funkstatus',
    'stein' => 'Stein.APP',
    'divera' => 'Divera',
    'stammdaten' => 'Stammdaten',
    'datei' => 'Dateien',
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
      <?php if (($vehicle['fms_status'] ?? null) !== null && $vehicle['fms_status'] !== ''): ?>
        <span class="badge" style="background:<?= e(fms_color((int)$vehicle['fms_status'])) ?>"><?= e(fms_label((int)$vehicle['fms_status'])) ?></span>
      <?php endif; ?>
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
    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="favorit">
      <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
      <input type="hidden" name="zurueck" value="fahrzeug">
      <button class="btn btn--sec" type="submit"><?= !empty($favorit) ? '★ Angeheftet' : '☆ Anheften' ?></button>
    </form>
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
    <?php if ($titelbild): ?>
      <a href="<?= e(url('vehicle_file', ['id' => $titelbild['id']])) ?>" class="titelbild">
        <img src="<?= e(url('vehicle_file', ['id' => $titelbild['id'], 'vorschau' => 1])) ?>"
             alt="<?= e($vehicle['bezeichnung']) ?>">
      </a>
    <?php endif; ?>
    <h2>Stammdaten</h2>
    <dl class="dl">
      <?php
      $zeilen = [
          'Bezeichnung'       => $vehicle['bezeichnung'],
          'Funkrufname'       => $vehicle['funkrufname'],
          'ISSI'              => $vehicle['issi'],
          'OPTA'              => $vehicle['opta'] ?? '',
          'RIC'               => $vehicle['ric'] ?? '',
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

    <?php $hatPosition = $vehicle['geo_lat'] !== null && $vehicle['geo_lng'] !== null; ?>
    <?php if ($hatPosition || can('report_vehicle')): ?>
      <h3 class="mt">Standort</h3>
      <?php if ($hatPosition): ?>
        <dl class="dl">
        <?php if ($vehicle['geo_lat'] !== null && $vehicle['geo_lng'] !== null
                  && setting_bool('fahrzeug_karte', true)): ?>
          <div class="dl__item" style="grid-column:1 / -1">
            <div class="dl__label">Karte</div>
            <div class="dl__value">
              <iframe class="karte" loading="lazy" referrerpolicy="no-referrer"
                      title="Standort auf der Karte"
                      src="<?= e(dv_map_embed_url((float)$vehicle['geo_lat'], (float)$vehicle['geo_lng'])) ?>"></iframe>
              <div class="small muted">Karte von OpenStreetMap, direkt von dort geladen.</div>
            </div></div>
        <?php endif; ?>
        <?php if ($vehicle['geo_lat'] !== null && $vehicle['geo_lng'] !== null): ?>
          <div class="dl__item"><div class="dl__label">Letzte Position</div>
            <div class="dl__value">
              <a href="<?= e(dv_map_url((float)$vehicle['geo_lat'], (float)$vehicle['geo_lng'])) ?>" target="_blank" rel="noopener">
                <?= e(number_format((float)$vehicle['geo_lat'], 5, ',', '') . ' / ' . number_format((float)$vehicle['geo_lng'], 5, ',', '')) ?></a>
              <?php if ($vehicle['geo_at']): ?>
                <span class="small muted">
                  <?= ($vehicle['geo_quelle'] ?? '') === 'mensch' ? 'von Hand gesetzt' : 'abgerufen' ?>
                  <?= e(de_datetime($vehicle['geo_at'])) ?></span>
              <?php endif; ?>
            </div></div>
        <?php endif; ?>
        </dl>
      <?php else: ?>
        <p class="small muted">Für dieses Fahrzeug ist kein Standort bekannt.</p>
      <?php endif; ?>

      <?php $qrConnector = connector_of_vehicle($vehicle); ?>
      <?php if (connector_enabled() || trim((string)($vehicle['qr_token'] ?? '')) !== ''): ?>
        <div id="qr" class="mt">
          <?php $qrToken = $qrConnector === null ? '' : trim((string)($vehicle['qr_token'] ?? '')); ?>
          <?php if ($qrToken !== ''): ?>
            <?php $qrAdresse = connector_qr_url($qrConnector, $qrToken, connector_qr_name($vehicle)); ?>
            <div class="qr-block" data-qr="<?= e($qrAdresse) ?>">
              <div class="qr-bild" id="qr-bild"></div>
              <div>
                <p class="small">Im Fahrzeug aufhängen: Wer den Code scannt, kann den Standort melden –
                  ohne Zugang zu dieser Anwendung.</p>
                <p class="small muted mono" style="word-break:break-all"><?= e($qrAdresse) ?></p>
                <p class="small muted">Der Teil hinter dem <span class="mono">#</span> bleibt im Browser –
                  der Connector erfährt nie, zu welchem Fahrzeug der Code gehört.
                  Er läuft über <strong><?= e((string)$qrConnector['name']) ?></strong>.</p>
                <div class="btnrow">
                  <button class="btn btn--sec btn--sm" type="button" id="qr-drucken">Drucken</button>
                  <?php if (can('manage_vehicles')): ?>
                    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="qr_neu">
                      <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
                      <button class="btn btn--sec btn--sm" type="submit"
                              data-confirm="Neuen QR-Code erzeugen? Der bisherige gilt dann nicht mehr – ausgedruckte Codes im Fahrzeug werden damit unbrauchbar."
                              data-confirm2="Bist du wirklich sicher? Danach muss der neue Code ausgedruckt und im Fahrzeug ausgetauscht werden.">Neu erzeugen</button>
                    </form>
                    <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="qr_weg">
                      <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
                      <button class="btn btn--sec btn--sm" type="submit"
                              data-confirm="QR-Code zurückziehen? Meldungen über diesen Code werden dann nicht mehr angenommen."
                              data-confirm2="Bist du wirklich sicher? Der Code im Fahrzeug funktioniert danach nicht mehr, bis ein neuer erzeugt wird.">Zurückziehen</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php elseif (can('manage_vehicles')): ?>
            <?php $qrConnectoren = connector_liste('fahrzeuge'); ?>
            <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_neu">
              <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
              <?php if (count($qrConnectoren) > 1): ?>
                <select name="connector_id" aria-label="Connector">
                  <?php foreach ($qrConnectoren as $con): ?>
                    <option value="<?= (int)$con['id'] ?>"><?= e((string)$con['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
              <button class="btn btn--sec" type="submit">QR-Code erzeugen</button>
            </form>
            <p class="small muted">Erzeugt einen Zugang, mit dem jede Person den Standort dieses Fahrzeugs
              melden kann – ohne Anmeldung und ohne Einblick in die Akte.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (can('report_vehicle')): ?>
        <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form"
              id="position-form" data-position>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="position">
          <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
          <input type="hidden" name="lat" value="">
          <input type="hidden" name="lng" value="">
          <input type="hidden" name="genauigkeit" value="">
          <button class="btn btn--sec" type="submit">Jetzt Position setzen</button>
        </form>
        <p class="small muted" id="position-hinweis">Übernimmt den Standort dieses Geräts – praktisch,
          wenn das Fahrzeug irgendwo abgestellt wurde. Der Browser fragt dafür um Erlaubnis.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($vehicle['divera_vehicle_id'])): ?>
      <h3 class="mt">Divera</h3>
      <dl class="dl">
        <?php if ($vehicle['fms_status'] !== null && $vehicle['fms_status'] !== ''): ?>
          <div class="dl__item"><div class="dl__label">Funkstatus</div>
            <div class="dl__value">
              <span class="badge" style="background:<?= e(fms_color((int)$vehicle['fms_status'])) ?>"><?= e(fms_label((int)$vehicle['fms_status'])) ?></span>
              <?php if ($vehicle['fms_at']): ?><span class="small muted">seit <?= e(de_datetime($vehicle['fms_at'])) ?></span><?php endif; ?>
              <?php if (trim((string)$vehicle['fms_note']) !== ''): ?><div class="small"><?= e((string)$vehicle['fms_note']) ?></div><?php endif; ?>
            </div></div>
        <?php endif; ?>
        <?php if (setting_bool('divera_besatzung_anzeigen', true) && ($crew = dv_crew_of($vehicle))): ?>
          <div class="dl__item"><div class="dl__label">Besatzung</div>
            <div class="dl__value"><?= e(implode(', ', $crew)) ?></div></div>
        <?php endif; ?>
      </dl>
      <p class="small muted">
        Verknüpft mit Divera-Fahrzeug <span class="mono"><?= (int)$vehicle['divera_vehicle_id'] ?></span>.
        <?php if ($vehicle['divera_sync_at']): ?>Zuletzt abgerufen am <?= e(de_datetime($vehicle['divera_sync_at'])) ?>.<?php endif; ?>
        Jeder Statuswechsel steht im Journal unter „Funkstatus".
      </p>
      <?php if (can('manage_vehicles')): ?>
        <form method="post" action="<?= e(url('vehicle_action')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="dv_unassign">
          <input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
          <button class="btn btn--sec btn--sm" type="submit"
                  data-confirm="Verknüpfung mit Divera lösen? Funkstatus, Position und Besatzung werden dann nicht mehr abgerufen.">
            Divera-Verknüpfung lösen</button>
        </form>
      <?php endif; ?>
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

<div class="grid2">
  <?= render_partial('partials/vehicle_files', [
      'vehicle' => $vehicle, 'order' => null, 'bilder' => $bilder, 'dokumente' => $dokumente, 'modus' => 'bilder',
  ]) ?>
  <?= render_partial('partials/vehicle_files', [
      'vehicle' => $vehicle, 'order' => null, 'bilder' => $bilder, 'dokumente' => $dokumente, 'modus' => 'dokumente',
  ]) ?>
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
            <td class="mono small nowrap"><a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['nummer']) ?></a>
              <?php if (trim((string)($o['thw_nummer'] ?? '')) !== ''): ?>
                <div class="muted" title="Nummer der THW-Verwaltung">THW <?= e((string)$o['thw_nummer']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['titel']) ?></a>
              <?php if ((int)$o['ausfall']): ?> <span class="badge" style="background:#b91c1c">Ausfall</span><?php endif; ?>
              <?php if (!empty($o['fotos']) || !empty($o['dokumente'])): ?>
                <span class="small muted" title="angehängte Dateien"><?php
                  echo implode(' · ', array_filter([
                      $o['fotos'] ? (int)$o['fotos'] . ' Foto(s)' : '',
                      $o['dokumente'] ? (int)$o['dokumente'] . ' Dokument(e)' : '',
                  ])); ?></span>
              <?php endif; ?>
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

<?php if (can('view_radios')): ?>
<section class="card" id="funk">
  <div class="card__head">
    <h2>Funkgeräte</h2>
    <div class="btnrow">
      <span class="muted small"><?= count($funkgeraete ?? []) ?></span>
      <?php if (can('manage_radios')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('radio_edit',
            ['ziel_typ' => 'fahrzeug', 'ziel_id' => $vehicle['id']])) ?>">+ Funkgerät</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!($funkgeraete ?? [])): ?>
    <div class="empty">Für dieses Fahrzeug ist kein Funkgerät hinterlegt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Gerät</th><th>Art</th><th>Karte</th><th>Zuletzt gesehen</th></tr></thead>
        <tbody>
        <?php foreach ($funkgeraete as $r): ?>
          <?php $gesehen = radio_gesehen($r); ?>
          <tr>
            <td><a href="<?= e(url('radio', ['id' => $r['id']])) ?>"><strong><?= e((string)$r['bezeichnung']) ?></strong></a>
              <?php if (trim((string)$r['funkrufname']) !== ''): ?>
                <div class="small muted"><?= e((string)$r['funkrufname']) ?></div>
              <?php endif; ?></td>
            <td class="small"><?= badge($r['typ_label']
                ? ['label' => $r['typ_label'], 'color' => $r['typ_color']] : null, '–') ?></td>
            <td class="small"><?= (int)$r['karten'] > 0
                ? (int)$r['karten'] . ' Karte(n)' : '<span class="muted">keine</span>' ?></td>
            <td class="small"><?= $gesehen['stufe'] === 'nie'
                ? '<span class="muted">–</span>'
                : e(de_date(substr((string)$r['zuletzt_gesehen'], 0, 10))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (can('view_sims')): ?>
<section class="card" id="sims">
  <div class="card__head">
    <h2>SIM-Karten</h2>
    <div class="btnrow">
      <span class="muted small"><?= count($sims ?? []) ?></span>
      <?php if (can('manage_sims')): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('sim_edit',
            ['ziel_typ' => 'fahrzeug', 'ziel_id' => $vehicle['id']])) ?>">+ SIM-Karte</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!($sims ?? [])): ?>
    <div class="empty">Für dieses Fahrzeug ist keine SIM-Karte hinterlegt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Rufnummer</th><th>Art</th><th>Steckt in</th><th>Vertrag</th></tr></thead>
        <tbody>
        <?php foreach ($sims as $s): ?>
          <tr>
            <td>
              <?php if (sim_ist_tetra($s)): ?>
                <strong class="mono"><?= e(sim_kennung($s)) ?: '–' ?></strong>
                <span class="badge badge--outline">TETRA</span>
                <?php if (trim((string)$s['opta']) !== ''): ?>
                  <div class="small muted mono"><?= e((string)$s['opta']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <strong><?= phone_html((string)$s['rufnummer'], '–') ?></strong>
                <?php if (trim((string)$s['iccid']) !== ''): ?>
                  <div class="small muted mono">ICCID <?= e((string)$s['iccid']) ?></div>
                <?php endif; ?>
              <?php endif; ?></td>
            <td class="small">
              <?= badge($s['typ_label'] ? ['label' => $s['typ_label'], 'color' => $s['typ_color']] : null, '–') ?>
              <?= $s['status_label'] ? badge(['label' => $s['status_label'], 'color' => $s['status_color']]) : '' ?>
            </td>
            <td class="small"><?= e((string)$s['geraet']) ?: '<span class="muted">–</span>' ?></td>
            <td class="small">
              <?= sim_hat_vertrag($s) && $s['vertrag_bis']
                  ? e(de_date((string)$s['vertrag_bis']))
                  : '<span class="muted">' . (sim_hat_vertrag($s) ? 'unbefristet' : 'ohne Vertrag') . '</span>' ?>
              <?php if (can('manage_sims')): ?>
                <div><a href="<?= e(url('sim_edit', ['id' => $s['id']])) ?>">bearbeiten</a></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card" id="wuensche">
  <div class="card__head">
    <h2><?= e((string)setting('wunsch_modul_name', 'Wünsch dir was')) ?> für dieses Fahrzeug</h2>
    <div class="btnrow">
      <span class="muted small"><?= count($wuensche ?? []) ?></span>
      <?php if (can('create_wish')): ?>
        <a class="btn btn--sm" href="<?= e(url('wish_edit', ['vehicle_id' => $vehicle['id']])) ?>">+ Wunsch</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (empty($wuensche)): ?>
    <div class="empty">Kein Wunsch für dieses Fahrzeug – etwa Ersatzteile oder Ausstattung.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Wunsch</th><th>Status</th><th>Dringlichkeit</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($wuensche as $w): ?>
          <tr>
            <td><a href="<?= e(url('wish', ['id' => $w['id']])) ?>"><?= e($w['bezeichnung']) ?></a>
              <?php if ((int)$w['nice_to_have']): ?> <span class="badge badge--outline">nice to have</span><?php endif; ?></td>
            <td><?= badge($w['status_label'] ? ['label' => $w['status_label'], 'color' => $w['status_color']] : null, '–') ?></td>
            <td><?= badge($w['dring_label'] ? ['label' => $w['dring_label'], 'color' => $w['dring_color']] : null, '–') ?></td>
            <td class="num"><?= e(money((float)$w['netto_gesamt'], false)) ?></td>
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
