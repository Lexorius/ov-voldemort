<?php
/** @var array $fahrzeuge @var array $fristen @var int $auffaellig @var array $filter
 *  @var bool $alle @var array $auftraege @var bool $steinAktiv @var int $steinStand
 *  @var array $titelbilder @var string $sort @var string $ansicht
 *  @var bool $nurFavoriten @var int $favoriten */
$titelbilder ??= [];
$sort ??= 'standard';
$ansicht ??= 'liste';
$nurFavoriten ??= false;
$favoriten ??= 0;

/** Adresse mit geänderten Parametern, der Rest bleibt stehen */
$mitParam = static function (array $neu): string {
    $p = $_GET;
    unset($p['p']);
    foreach ($neu as $k => $v) {
        if ($v === null) {
            unset($p[$k]);
        } else {
            $p[$k] = $v;
        }
    }
    return url('vehicles', $p);
};

/** Knopf zum Anheften */
$stern = static function (array $v): string {
    $an = (int)($v['favorit'] ?? 0) > 0;
    return '<form method="post" action="' . e(url('vehicle_action')) . '" class="inline-form">'
        . csrf_field()
        . '<input type="hidden" name="action" value="favorit">'
        . '<input type="hidden" name="vehicle_id" value="' . (int)$v['id'] . '">'
        . '<button class="stern' . ($an ? ' stern--an' : '') . '" type="submit"'
        . ' title="' . ($an ? 'Nicht mehr anheften' : 'Oben anheften') . '"'
        . ' aria-label="' . ($an ? 'Nicht mehr anheften' : 'Oben anheften') . '">'
        . ($an ? '★' : '☆') . '</button></form>';
};
$offen = 0;
foreach ($fahrzeuge as $v) {
    $offen += (int)$v['offene_auftraege'];
}

/** Fristen als Plaketten */
$fristBadges = static function (array $liste): string {
    $html = '';
    foreach ($liste as $f) {
        if ($f['status'] === 'ok') {
            continue;
        }
        $farbe = $f['status'] === 'abgelaufen' ? '#b91c1c' : '#a16207';
        $text = $f['label'] . ' ' . ($f['status'] === 'abgelaufen' ? 'abgelaufen' : 'bis ' . de_date($f['datum']));
        $html .= '<span class="badge" style="background:' . $farbe . '">' . e($text) . '</span>';
    }
    return $html;
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('fahrzeug_modul_name', 'Fahrzeuge')) ?></h1>
    <p><?= nl2br(e((string)setting('fahrzeug_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_vehicles')): ?>
      <a class="btn" href="<?= e(url('vehicle_edit')) ?>">+ Fahrzeug</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Fahrzeuge</div>
    <div class="stat__value"><?= count($fahrzeuge) ?></div>
    <div class="stat__hint"><?= $alle ? 'mit ausgemusterten' : 'im Dienst' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Offene Aufträge</div>
    <div class="stat__value"<?= $offen > 0 ? ' style="color:var(--warn)"' : '' ?>><?= $offen ?></div>
    <div class="stat__hint">Instandsetzung und Meldungen</div>
  </div>
  <div class="stat">
    <div class="stat__label">Fristen</div>
    <div class="stat__value"<?= $auffaellig > 0 ? ' style="color:var(--bad)"' : '' ?>><?= $auffaellig ?></div>
    <div class="stat__hint">HU, SP oder UVV fällig</div>
  </div>
  <div class="stat">
    <div class="stat__label">Stein.APP</div>
    <div class="stat__value" style="font-size:1.05rem">
      <?= $steinAktiv ? ($steinStand > 0 ? e(date('H:i', $steinStand)) . ' Uhr' : 'wartet') : 'aus' ?>
    </div>
    <div class="stat__hint"><?= $steinAktiv ? 'letzter Abgleich' : 'nicht eingerichtet' ?></div>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="vehicles">
  <div class="grid3">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>"
             placeholder="Bezeichnung, Kennzeichen, Funkrufname, ISSI">
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('fahrzeug_status', $filter['status_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('fahrzeug_typ', $filter['typ_id'], 'alle') ?></select>
    </div>
  </div>
  <div class="grid3">
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach (vehicle_sorts() as $wert => $text): ?>
          <option value="<?= e($wert) ?>"<?= $sort === $wert ? ' selected' : '' ?>><?= e($text) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="alle" name="alle" value="1"<?= $alle ? ' checked' : '' ?>>
      <label for="alle">Ausgemusterte mit anzeigen</label>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="favoriten" name="favoriten" value="1"<?= $nurFavoriten ? ' checked' : '' ?>>
      <label for="favoriten">Nur meine angehefteten<?= $favoriten > 0 ? ' (' . $favoriten . ')' : '' ?></label>
    </div>
  </div>
  <input type="hidden" name="ansicht" value="<?= e($ansicht) ?>">
</form>

<section class="card">
  <div class="card__head">
    <h2>Fahrzeuge</h2>
    <div class="btnrow">
      <span class="muted small"><?= count($fahrzeuge) ?></span>
      <a class="btn btn--sec btn--sm<?= $ansicht === 'liste' ? ' is-active' : '' ?>"
         href="<?= e($mitParam(['ansicht' => 'liste'])) ?>">Liste</a>
      <a class="btn btn--sec btn--sm<?= $ansicht === 'kacheln' ? ' is-active' : '' ?>"
         href="<?= e($mitParam(['ansicht' => 'kacheln'])) ?>">Kacheln</a>
    </div>
  </div>
  <?php if (!$fahrzeuge): ?>
    <div class="empty">
      <?php if ($nurFavoriten): ?>
        Noch kein Fahrzeug angeheftet – dafür in der Liste auf den Stern tippen.
      <?php else: ?>
        Kein Fahrzeug gefunden.
        <?php if (can('manage_vehicles')): ?><br><a href="<?= e(url('vehicle_edit')) ?>">Erstes Fahrzeug anlegen</a><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php elseif ($ansicht === 'kacheln'): ?>
    <div class="kacheln">
      <?php foreach ($fahrzeuge as $v): ?>
        <div class="kachel<?= (int)$v['is_active'] ? '' : ' kachel--aus' ?>"
             style="border-top-color:<?= e($v['status_color'] ?: '#94a3b8') ?>">
          <div class="kachel__stern"><?= $stern($v) ?></div>
          <a class="kachel__bild" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>">
            <?php if (isset($titelbilder[(int)$v['id']])): ?>
              <img alt="" loading="lazy"
                   src="<?= e(url('vehicle_file', ['id' => $titelbilder[(int)$v['id']]['id'], 'vorschau' => 1])) ?>">
            <?php else: ?>
              <span class="kachel__leer" aria-hidden="true">⛟</span>
            <?php endif; ?>
          </a>
          <div class="kachel__text">
            <a class="item__title" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e($v['bezeichnung']) ?></a>
            <div class="item__sub small"><?= e(implode(' · ', array_filter([$v['funkrufname'], $v['kennzeichen']]))) ?></div>
            <div class="item__meta">
              <?= badge($v['status_label'] ? ['label' => $v['status_label'], 'color' => $v['status_color']] : null, 'ohne Status') ?>
              <?php if (($v['fms_status'] ?? null) !== null && $v['fms_status'] !== ''): ?>
                <span class="badge" style="background:<?= e(fms_color((int)$v['fms_status'])) ?>">S<?= (int)$v['fms_status'] ?></span>
              <?php endif; ?>
              <?= $fristBadges($fristen[(int)$v['id']] ?? []) ?>
              <?php if ((int)$v['offene_auftraege'] > 0): ?>
                <span class="badge badge--outline"><?= (int)$v['offene_auftraege'] ?> Auftrag/Aufträge</span>
              <?php endif; ?>
              <?php if (!(int)$v['is_active']): ?><span class="badge badge--muted">ausgemustert</span><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="itemlist">
      <?php foreach ($fahrzeuge as $v): ?>
        <div class="item-mit-stern">
        <?= $stern($v) ?>
        <a class="item<?= (int)$v['is_active'] ? '' : ' item--done' ?>" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"
           style="border-left-color:<?= e($v['status_color'] ?: '#94a3b8') ?>">
          <div class="item__top">
            <?php if (isset($titelbilder[(int)$v['id']])): ?>
              <img class="item__bild" alt="" loading="lazy"
                   src="<?= e(url('vehicle_file', ['id' => $titelbilder[(int)$v['id']]['id'], 'vorschau' => 1])) ?>">
            <?php endif; ?>
            <div style="min-width:0;flex:1">
              <div class="item__title"><?= e($v['bezeichnung']) ?></div>
              <div class="item__sub">
                <?= e(implode(' · ', array_filter([
                    $v['funkrufname'], $v['kennzeichen'], $v['typ_label'], $v['fachgruppe_label'],
                ]))) ?>
              </div>
              <?php if (trim((string)$v['issi']) !== ''): ?>
                <div class="item__sub small">ISSI <?= e((string)$v['issi']) ?></div>
              <?php endif; ?>
            </div>
            <?php if ((int)$v['offene_auftraege'] > 0): ?>
              <div class="item__amount"><?= (int)$v['offene_auftraege'] ?></div>
            <?php endif; ?>
          </div>
          <div class="item__meta">
            <?= badge($v['status_label'] ? ['label' => $v['status_label'], 'color' => $v['status_color']] : null, 'ohne Status') ?>
            <?php if (($v['fms_status'] ?? null) !== null && $v['fms_status'] !== ''): ?>
              <span class="badge" style="background:<?= e(fms_color((int)$v['fms_status'])) ?>"
                    title="<?= e(fms_label((int)$v['fms_status']) . ($v['fms_at'] ? ' seit ' . de_datetime($v['fms_at']) : '')) ?>">
                S<?= (int)$v['fms_status'] ?> <?= e(FMS_STATUS[(int)$v['fms_status']] ?? '') ?></span>
            <?php endif; ?>
            <?= $fristBadges($fristen[(int)$v['id']] ?? []) ?>
            <?php if ((int)$v['offene_auftraege'] > 0): ?>
              <span class="badge badge--outline"><?= (int)$v['offene_auftraege'] ?> offene(r) Auftrag/Aufträge</span>
            <?php endif; ?>
            <?php if ($v['stein_asset_id']): ?>
              <span class="badge badge--outline" title="Mit der Stein.APP verbunden">Stein.APP</span>
            <?php endif; ?>
            <?php if (!(int)$v['is_active']): ?><span class="badge badge--muted">ausgemustert</span><?php endif; ?>
          </div>
        </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($auftraege): ?>
  <section class="card">
    <h2>Offene Aufträge</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Nr.</th><th>Fahrzeug</th><th>Vorgang</th><th>Priorität</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($auftraege as $o): ?>
          <tr>
            <td class="mono small nowrap"><a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['nummer']) ?></a></td>
            <td class="small"><?= e($o['fahrzeug']) ?></td>
            <td>
              <a href="<?= e(url('vehicle_order', ['id' => $o['id']])) ?>"><?= e($o['titel']) ?></a>
              <?php if ((int)$o['ausfall']): ?> <span class="badge" style="background:#b91c1c">Ausfall</span><?php endif; ?>
            </td>
            <td><?= badge($o['prio_label'] ? ['label' => $o['prio_label'], 'color' => $o['prio_color']] : null) ?></td>
            <td><?= badge($o['status_label'] ? ['label' => $o['status_label'], 'color' => $o['status_color']] : null) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
