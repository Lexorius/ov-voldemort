<?php
/** @var array $radios @var array $gruppen @var array $stats @var int $warn @var array $filter */
$darf = can('manage_radios');
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('funk_modul_name', 'Funkgeräte')) ?></h1>
    <p><?= nl2br(e((string)setting('funk_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('radio_edit')) ?>">+ Funkgerät</a>
      <a class="btn btn--sec" href="<?= e(url('radio_group_edit')) ?>">+ Gruppe</a>
    <?php endif; ?>
    <?php if (can('view_sims')): ?>
      <a class="btn btn--sec" href="<?= e(url('sims')) ?>">Karten</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Geräte</div>
    <div class="stat__value"><?= (int)$stats['anzahl'] ?></div>
    <div class="stat__hint"><?= (int)$stats['mit_karte'] ?> mit Karte</div>
  </div>
  <div class="stat">
    <div class="stat__label">Gruppen</div>
    <div class="stat__value"><?= count($gruppen) ?></div>
    <div class="stat__hint">Koffer, Ladeschalen, Sätze</div>
  </div>
  <div class="stat">
    <div class="stat__label">Lange nicht gesehen</div>
    <div class="stat__value"><?= (int)$stats['lange_nicht_gesehen'] ?></div>
    <div class="stat__hint">seit über 30 Tagen keine Meldung</div>
  </div>
  <div class="stat">
    <div class="stat__label">Prüfung fällig</div>
    <div class="stat__value"><?= (int)$stats['pruefung_bald'] ?></div>
    <div class="stat__hint">in den nächsten <?= (int)$warn ?> Tagen oder vorbei</div>
  </div>
</div>

<?php if ($gruppen): ?>
<section class="card">
  <div class="card__head">
    <h2>Gruppen</h2>
    <span class="muted small"><?= count($gruppen) ?></span>
  </div>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Gruppe</th><th>Lagerort</th><th>Geräte</th><th>Zuletzt gesehen</th><th>QR</th></tr></thead>
      <tbody>
      <?php foreach ($gruppen as $g): ?>
        <?php $gesehen = radio_gesehen($g); ?>
        <tr<?= (int)$g['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
          <td>
            <a href="<?= e(url('radio_group', ['id' => $g['id']])) ?>"><strong><?= e((string)$g['name']) ?></strong></a>
            <?php if ((int)$g['is_active'] !== 1): ?>
              <span class="badge badge--muted">stillgelegt</span>
            <?php endif; ?>
            <div class="small muted"><?= e(sim_ziel_text($g)) ?></div>
          </td>
          <td class="small"><?= e((string)$g['lagerort']) ?: '<span class="muted">–</span>' ?></td>
          <td class="small"><?= (int)$g['geraete'] ?></td>
          <td class="small">
            <?php if ($gesehen['stufe'] === 'nie'): ?>
              <span class="muted">noch nie gemeldet</span>
            <?php else: ?>
              <span<?= $gesehen['stufe'] === 'alt' ? ' style="color:var(--warn)"' : '' ?>>
                <?= e(de_datetime((string)$g['zuletzt_gesehen'])) ?></span>
              <?php if ($g['zuletzt_anzahl'] !== null): ?>
                <div class="muted"><?= (int)$g['zuletzt_anzahl'] ?> von <?= (int)$g['geraete'] ?> da</div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="small"><?= trim((string)$g['qr_token']) !== ''
              ? '<span class="badge badge--outline">hängt</span>' : '<span class="muted">–</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="radios">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>"
             placeholder="Bezeichnung, Seriennummer, Funkrufname">
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('funk_typ', $filter['typ_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('funk_status', $filter['status_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="ziel_typ">Gehört zu</label>
      <select id="ziel_typ" name="ziel_typ">
        <option value="">alle</option>
        <?php foreach (RADIO_ZIELE as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)$filter['ziel_typ'] ? 'selected' : '' ?>>
            <?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach (['' => 'Art', 'ziel' => 'Zuordnung', 'pruefung' => 'Prüfung',
                        'neu' => 'zuletzt angelegt'] as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)$filter['sort'] ? 'selected' : '' ?>>
            <?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="aktiv">Bestand</label>
      <select id="aktiv" name="aktiv">
        <option value="">nur geführte</option>
        <option value="alle" <?= $filter['aktiv'] === 'alle' ? 'selected' : '' ?>>auch ausgemusterte</option>
      </select>
    </div>
  </div>
</form>

<section class="card">
  <div class="card__head">
    <h2>Geräte</h2>
    <span class="muted small"><?= count($radios) ?></span>
  </div>
  <?php if (!$radios): ?>
    <div class="empty">Kein Gerät gefunden.
      <?php if ($darf): ?><br><a href="<?= e(url('radio_edit')) ?>">Erstes Gerät anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Gerät</th><th>Art</th><th>Gehört zu</th><th>Karte</th>
          <th>Zuletzt gesehen</th><th>Prüfung</th>
        </tr></thead>
        <tbody>
        <?php foreach ($radios as $r): ?>
          <?php $pruefung = radio_pruefung($r, $warn); $gesehen = radio_gesehen($r); ?>
          <tr<?= (int)$r['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <a href="<?= e(url('radio', ['id' => $r['id']])) ?>"><strong><?= e((string)$r['bezeichnung']) ?></strong></a>
              <?php if ((int)$r['is_active'] !== 1): ?>
                <span class="badge badge--muted">ausgemustert</span>
              <?php endif; ?>
              <?php if (trim((string)$r['funkrufname']) !== ''): ?>
                <div class="small muted"><?= e((string)$r['funkrufname']) ?></div>
              <?php endif; ?>
              <?php if (trim((string)($r['gruppe_name'] ?? '')) !== ''): ?>
                <div class="small muted">Gruppe: <?= e((string)$r['gruppe_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= badge($r['typ_label'] ? ['label' => $r['typ_label'], 'color' => $r['typ_color']] : null, '–') ?>
              <?php if ($r['status_label']): ?>
                <div style="margin-top:.25rem"><?= badge(['label' => $r['status_label'], 'color' => $r['status_color']]) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ((string)$r['ziel_typ'] === 'fahrzeug' && $r['fahrzeug_label'] && can('view_vehicles')): ?>
                <a href="<?= e(url('vehicle', ['id' => $r['ziel_id']])) ?>"><?= e(radio_ziel_text($r)) ?></a>
              <?php else: ?>
                <?= e(radio_ziel_text($r)) ?>
              <?php endif; ?>
              <?php if (trim((string)$r['standort']) !== ''): ?>
                <div class="muted"><?= e((string)$r['standort']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= (int)$r['karten'] > 0
                ? (int)$r['karten'] . ' Karte(n)'
                : '<span class="muted">keine</span>' ?></td>
            <td class="small">
              <?php if ($gesehen['stufe'] === 'nie'): ?>
                <span class="muted">–</span>
              <?php else: ?>
                <span<?= $gesehen['stufe'] === 'alt' ? ' style="color:var(--warn)"' : '' ?>>
                  <?= e(de_date(substr((string)$r['zuletzt_gesehen'], 0, 10))) ?></span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($pruefung['stufe'] === 'offen'): ?>
                <span class="muted">–</span>
              <?php else: ?>
                <span<?= $pruefung['stufe'] === 'faellig' ? ' style="color:var(--bad);font-weight:700"'
                    : ($pruefung['stufe'] === 'bald' ? ' style="color:var(--warn);font-weight:700"' : '') ?>>
                  <?= e(de_date((string)$r['pruefung_bis'])) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
