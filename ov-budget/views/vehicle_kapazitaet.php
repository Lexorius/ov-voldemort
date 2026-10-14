<?php
/** @var array $rows @var array $summe @var array $filter @var string $sort */
$sorts = ['personen' => 'Sitzplätze (meiste zuerst)', 'zuladung' => 'Zuladung (meiste zuerst)', 'name' => 'Name'];
?>
<div class="pagehead">
  <div>
    <h1>Kapazitäten</h1>
    <p>Wie viele Personen und wie viel Zuladung die Fahrzeuge schaffen – zum Planen von Fahrten und Transporten.
      Filtern nach Art, Fachgruppe, Status oder Mindestwerten; unten die Summe der gefilterten Fahrzeuge.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('vehicles')) ?>">Zur Fahrzeugliste</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Fahrzeuge</div><div class="stat__value"><?= (int)$summe['fahrzeuge'] ?></div><div class="stat__hint">in der Auswahl</div></div>
  <div class="stat"><div class="stat__label">Sitzplätze gesamt</div><div class="stat__value"><?= (int)$summe['personen'] ?></div>
    <div class="stat__hint"><?= $summe['ohne_personen'] > 0 ? (int)$summe['ohne_personen'] . ' Fahrzeug(e) ohne Angabe' : 'Personen samt Fahrer' ?></div></div>
  <div class="stat"><div class="stat__label">Zuladung gesamt</div><div class="stat__value"><?= e(number_format((int)$summe['zuladung'], 0, ',', '.')) ?> <small>kg</small></div>
    <div class="stat__hint"><?= $summe['ohne_zuladung'] > 0 ? (int)$summe['ohne_zuladung'] . ' Fahrzeug(e) ohne Angabe' : 'maximale Zuladung' ?></div></div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="vehicle_kapazitaet">
  <div class="grid3">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>" placeholder="Name, Funkrufname, Kennzeichen">
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('fahrzeug_typ', (int)($filter['typ_id'] ?? 0), 'alle Arten') ?></select>
    </div>
    <div class="field">
      <label for="fachgruppe_id">Fachgruppe</label>
      <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($filter['fachgruppe_id'] ?? 0), 'alle Fachgruppen') ?></select>
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('fahrzeug_status', (int)($filter['status_id'] ?? 0), 'alle') ?></select>
    </div>
    <div class="field">
      <label for="min_personen">Mindestens Sitzplätze</label>
      <input type="number" id="min_personen" name="min_personen" min="0" value="<?= (int)($filter['min_personen'] ?? 0) ?: '' ?>" placeholder="z. B. 9">
    </div>
    <div class="field">
      <label for="min_zuladung">Mindestens Zuladung (kg)</label>
      <input type="number" id="min_zuladung" name="min_zuladung" min="0" value="<?= (int)($filter['min_zuladung'] ?? 0) ?: '' ?>" placeholder="z. B. 1000">
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach ($sorts as $k => $l): ?><option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field field--check" style="align-self:end">
      <input type="checkbox" id="alle" name="alle" value="1"<?= !empty($filter['alle']) ? ' checked' : '' ?>>
      <label for="alle">auch ausgemusterte</label>
    </div>
  </div>
  <noscript><button class="btn btn--sec" type="submit">Filtern</button></noscript>
</form>

<section class="card">
  <?php if (!$rows): ?>
    <div class="empty">Kein Fahrzeug passt zur Auswahl.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fahrzeug</th><th>Art</th><th>Fachgruppe</th><th>Status</th><th class="num">Sitzplätze</th><th class="num">Zuladung</th><th>Stellplatz</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $v): ?>
          <tr<?= (int)$v['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><strong><?= e((string)$v['bezeichnung']) ?></strong></a>
              <div class="small muted"><?= e(implode(' · ', array_filter([(string)($v['funkrufname'] ?? ''), (string)($v['kennzeichen'] ?? '')]))) ?></div></td>
            <td class="small"><?= e((string)($v['typ_label'] ?? '') ?: '–') ?></td>
            <td class="small"><?= e((string)($v['fachgruppe_label'] ?? '') ?: '–') ?></td>
            <td><?= badge(!empty($v['status_label']) ? ['label' => $v['status_label'], 'color' => $v['status_color'] ?? ''] : null, 'ohne Status') ?></td>
            <td class="num"><?= ($v['sitzplaetze'] ?? null) !== null && $v['sitzplaetze'] !== '' ? '<strong>' . (int)$v['sitzplaetze'] . '</strong>' : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= ($v['zuladung_kg'] ?? null) !== null && $v['zuladung_kg'] !== '' ? e(number_format((int)$v['zuladung_kg'], 0, ',', '.')) . ' kg' : '<span class="muted">–</span>' ?></td>
            <td class="small"><?= e((string)($v['stellplatz_pfad'] ?? $v['stellplatz_name'] ?? '') ?: '–') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4"><?= (int)$summe['fahrzeuge'] ?> Fahrzeug(e)</th><th class="num"><?= (int)$summe['personen'] ?></th><th class="num"><?= e(number_format((int)$summe['zuladung'], 0, ',', '.')) ?> kg</th><th></th></tr></tfoot>
      </table>
    </div>
    <?php if ($summe['ohne_personen'] > 0 || $summe['ohne_zuladung'] > 0): ?>
      <p class="small muted" style="margin:.6rem 0 0">Fahrzeuge ohne Angabe zählen in den Summen nicht mit. Sitzplätze und Zuladung stehen in der Fahrzeugakte unter „Bearbeiten".</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
