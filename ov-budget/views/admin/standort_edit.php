<?php
/** @var array $s @var ?array $parent @var array $alle @var array $errors @var string $pfad @var int $kinder @var array $bilder */
$bilder ??= [];
$hatPosition = ($s['geo_lat'] ?? null) !== null && ($s['geo_lng'] ?? null) !== null;
$isNew = empty($s['id']);
$typ = standort_typ((string)($s['typ'] ?? 'raum'));
$ausser = $isNew ? [] : array_merge([(int)$s['id']], standort_nachkommen((int)$s['id'], $alle));
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Platz anlegen' : 'Platz bearbeiten' ?></h1>
    <?php if ($pfad !== ''): ?><p class="muted small"><?= e($pfad) ?></p><?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin_standorte')) ?>">Zur Übersicht</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('admin_standort_edit', $isNew ? [] : ['id' => $s['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="parent_id">Liegt in</label>
      <select id="parent_id" name="parent_id" data-standort-eltern>
        <?= standort_optionen($alle, (int)($s['parent_id'] ?? 0), '– ganz oben (Gebäude, Halle, Hof) –', $ausser) ?>
      </select>
      <small>Gebäude, Hallen und Höfe stehen ganz oben. Stockwerke und Räume liegen in Gebäuden, Stellplätze in Hallen,
        Schränke in Räumen, Regale in Schränken.</small>
    </div>
    <div class="field">
      <label for="typ">Art</label>
      <select id="typ" name="typ">
        <?php foreach (STANDORT_TYPEN as $key => $t): ?>
          <option value="<?= e($key) ?>"<?= $typ === $key ? ' selected' : '' ?> data-eltern="<?= e(implode(',', $t['eltern'])) ?>"><?= e($t['icon'] . ' ' . $t['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="grid2">
    <div class="field">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required maxlength="120" value="<?= e((string)$s['name']) ?>" placeholder="z. B. Haupthaus, 1. Stock, Raum, Stellplatz">
      <?php if ($isNew): ?><small>Bei einer Serie wird die Nummer angehängt: „Raum 1", „Raum 2", …</small><?php endif; ?>
    </div>
    <div class="field">
      <label for="kurz">Kurzzeichen</label>
      <input type="text" id="kurz" name="kurz" maxlength="30" value="<?= e((string)($s['kurz'] ?? '')) ?>" placeholder="z. B. HH, 1OG, R">
      <small>Fürs Etikett oder die Inventarliste; bei einer Serie mit Nummer.</small>
    </div>
  </div>
  <?php if ($isNew): ?>
    <div class="grid2">
      <div class="field">
        <label for="anzahl">Wie viele auf einmal</label>
        <input type="number" id="anzahl" name="anzahl" min="1" max="<?= STANDORT_MAX_SERIE ?>" value="<?= (int)($s['anzahl'] ?? 1) ?>" style="max-width:8rem">
        <small>1 = nur dieser Platz. 16 = „Raum 1" bis „Raum 16".</small>
      </div>
      <div class="field">
        <label for="start">Erste Nummer</label>
        <input type="number" id="start" name="start" min="0" value="<?= (int)($s['start'] ?? 1) ?>" style="max-width:8rem">
      </div>
    </div>
  <?php endif; ?>
  <div class="field">
    <label for="notiz">Notiz</label>
    <textarea id="notiz" name="notiz" rows="2"><?= e((string)($s['notiz'] ?? '')) ?></textarea>
  </div>
  <div class="field">
    <label for="koordinaten">GPS-Koordinaten</label>
    <input type="text" id="koordinaten" name="koordinaten" inputmode="decimal" style="max-width:20rem"
           value="<?= e(isset($s['koordinaten']) ? (string)$s['koordinaten'] : ($hatPosition ? number_format((float)$s['geo_lat'], 6, '.', '') . ', ' . number_format((float)$s['geo_lng'], 6, '.', '') : '')) ?>"
           placeholder="49.142345, 9.218765">
    <small>Breite, Länge mit Punkt – etwa aus der Kartenadresse kopiert. Leer lassen = keine Position; am Handy geht es unten mit einem Klick.</small>
  </div>
  <div class="field field--check">
    <input type="checkbox" id="is_active" name="is_active" value="1"<?= (int)($s['is_active'] ?? 1) === 1 ? ' checked' : '' ?>>
    <label for="is_active">Aktiv (auswählbar)</label>
  </div>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('admin_standorte')) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <section class="card" id="lage">
    <div class="card__head">
      <h2>Lage auf der Karte</h2>
      <?php if ($hatPosition): ?>
        <a class="small" href="<?= e(dv_map_url((float)$s['geo_lat'], (float)$s['geo_lng'])) ?>" target="_blank" rel="noopener">
          <?= e(standort_koordinaten_text((float)$s['geo_lat'], (float)$s['geo_lng'])) ?></a>
      <?php endif; ?>
    </div>
    <?php if ($hatPosition): ?>
      <iframe class="karte" loading="lazy" referrerpolicy="no-referrer" title="Lage auf der Karte"
              src="<?= e(dv_map_embed_url((float)$s['geo_lat'], (float)$s['geo_lng'], 0.004)) ?>"></iframe>
      <p class="small muted">Karte von OpenStreetMap, direkt von dort geladen.
        <?= ($s['geo_quelle'] ?? '') === 'geraet' ? 'Vom Gerät übernommen' : 'Von Hand eingetragen' ?><?= !empty($s['geo_at']) ? ' am ' . e(de_datetime((string)$s['geo_at'])) : '' ?><?= ($s['geo_genauigkeit'] ?? null) !== null ? ', Genauigkeit etwa ' . (int)round((float)$s['geo_genauigkeit']) . ' m' : '' ?>.</p>
    <?php else: ?>
      <p class="muted">Noch keine Position. Oben die Koordinaten eintragen – oder am Handy vor Ort stehen und den Knopf drücken.</p>
    <?php endif; ?>
    <div class="btnrow">
      <form method="post" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>" class="inline-form" data-position>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="position">
        <input type="hidden" name="lat" value="">
        <input type="hidden" name="lng" value="">
        <input type="hidden" name="genauigkeit" value="">
        <button class="btn btn--sec" type="submit">Jetzt Position setzen</button>
      </form>
      <?php if ($hatPosition): ?>
        <form method="post" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="position_weg">
          <button class="btn btn--sec" type="submit" data-confirm="Position entfernen?">Position entfernen</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="small muted" id="position-hinweis">Übernimmt den Standort dieses Geräts – der Browser fragt dafür um Erlaubnis.</p>
  </section>

  <?php $fahrzeuge ??= []; if ($fahrzeuge): ?>
  <section class="card" id="fahrzeuge">
    <div class="card__head">
      <h2>Fahrzeuge mit diesem Stellplatz</h2>
      <span class="muted small"><?= count($fahrzeuge) ?></span>
    </div>
    <div class="chips">
      <?php foreach ($fahrzeuge as $v): ?>
        <a class="chip" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e((string)$v['bezeichnung']) ?><?= $v['funkrufname'] ? ' <span class="muted small">' . e((string)$v['funkrufname']) . '</span>' : '' ?><?= (int)$v['is_active'] !== 1 ? ' <span class="badge badge--muted">ausgemustert</span>' : '' ?></a>
      <?php endforeach; ?>
    </div>
    <p class="small muted">Gesetzt in der Fahrzeugakte unter „Stellplatz (Standard)".</p>
  </section>
  <?php endif; ?>

  <section class="card" id="bilder">
    <div class="card__head">
      <h2>Bilder</h2>
      <span class="muted small"><?= count($bilder) ?></span>
    </div>
    <?php if ($bilder): ?>
      <div class="galerie">
        <?php foreach ($bilder as $b): ?>
          <figure class="galerie__bild<?= (int)$b['is_cover'] === 1 ? ' galerie__bild--titel' : '' ?>">
            <a href="<?= e(url('standort_bild', ['id' => $b['id']])) ?>" target="_blank" rel="noopener">
              <img src="<?= e(url('standort_bild', ['id' => $b['id'], 'vorschau' => 1])) ?>" alt="<?= e((string)$b['titel']) ?>" loading="lazy"></a>
            <figcaption><?= e((string)$b['titel']) ?><?= (int)$b['is_cover'] === 1 ? ' <span class="badge badge--outline">Titelbild</span>' : '' ?>
              <span class="btnrow" style="margin-top:.3rem">
                <?php if ((int)$b['is_cover'] !== 1): ?>
                  <form method="post" class="inline-form" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="bild_cover"><input type="hidden" name="bild_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn--sec btn--sm" type="submit">Titelbild</button></form>
                <?php endif; ?>
                <form method="post" class="inline-form" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="bild_delete"><input type="hidden" name="bild_id" value="<?= (int)$b['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit" data-confirm="<?= e('„' . $b['titel'] . '" entfernen?') ?>">Entfernen</button></form>
              </span></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="muted">Noch kein Bild – etwa vom Regal, der Tür oder dem Stellplatz, damit jeder ihn findet.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form mt" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="bild_upload">
      <div class="grid2">
        <div class="field">
          <label for="bilder-upload">Bilder hochladen</label>
          <input type="file" id="bilder-upload" name="bilder[]" multiple accept="image/*" capture="environment">
          <small>JPG, PNG, WebP oder GIF, bis <?= (int)round(upload_max_bytes() / 1048576) ?> MB je Bild. Bilder werden verkleinert und verlieren den Aufnahmeort.</small>
        </div>
        <div class="field">
          <label for="bild-titel">Titel (für alle)</label>
          <input type="text" id="bild-titel" name="titel" maxlength="200" placeholder="sonst der Dateiname">
        </div>
      </div>
      <div><button class="btn btn--sec" type="submit">Hochladen</button></div>
    </form>
  </section>

  <div class="grid2">
    <form method="post" class="card" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="aktiv">
      <h2><?= (int)$s['is_active'] === 1 ? 'Stilllegen' : 'Wieder aktivieren' ?></h2>
      <p class="small muted">Ein stillgelegter Platz bleibt im Baum, lässt sich aber nirgends mehr auswählen.</p>
      <button class="btn btn--sec" type="submit"><?= (int)$s['is_active'] === 1 ? 'Stilllegen' : 'Aktivieren' ?></button>
    </form>
    <form method="post" class="card" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <h2>Löschen</h2>
      <?php if ($kinder > 0): ?>
        <p class="small muted">Hat noch <?= (int)$kinder ?> Unterplatz/Unterplätze – erst diese löschen oder woanders einhängen.</p>
        <button class="btn btn--danger" type="submit" disabled>Löschen</button>
      <?php else: ?>
        <p class="small muted">Endgültig. Bezüge von Geräten oder Fahrzeugen auf diesen Platz werden leer.</p>
        <button class="btn btn--danger" type="submit" data-confirm="Diesen Platz endgültig löschen?">Löschen</button>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>
