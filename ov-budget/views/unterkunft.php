<?php
/** @var array $standorte @var array $punkte @var int $gesamt @var array $blatt @var array $filter @var array $stats @var array $gebaeude
 *  @var int $jahr @var ?array $kosten @var ?array $zaehler @var array $defektFormulare @var bool $melden @var bool $verwalten */
$gefiltert = $filter['art'] !== '' || $filter['status_id'] || $filter['prioritaet_id'] || $filter['q'] !== '' || $filter['ohne_ort'] || $filter['standort'] || $filter['zustand'] !== 'offen';
$seitenParams = array_filter(['zustand' => $filter['zustand'] !== 'offen' ? $filter['zustand'] : null, 'art' => $filter['art'], 'status_id' => $filter['status_id'],
    'prioritaet_id' => $filter['prioritaet_id'], 'q' => $filter['q'], 'ohne_ort' => $filter['ohne_ort'] ? '1' : null, 'standort' => $filter['standort']]);
$typVon = static fn(array $s): array => (defined('STANDORT_TYPEN') ? STANDORT_TYPEN[(string)$s['typ']] ?? null : null) ?? ['icon' => '▢', 'label' => 'Platz'];
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('unterkunft_modul_name', 'Unterkunft')) ?></h1>
    <p><?= nl2br(e((string)setting('unterkunft_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($melden || $verwalten): ?>
      <a class="btn" href="<?= e(url('unterkunft_punkt_edit')) ?>">+ Mangel melden</a>
      <a class="btn btn--sec" href="<?= e(url('unterkunft_punkt_edit', ['art' => 'aufgabe'])) ?>">+ Aufgabe</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <a class="stat" href="<?= e(url('unterkunft')) ?>#punkte" style="text-decoration:none">
    <div class="stat__label">Offene Punkte</div>
    <div class="stat__value"><?= (int)$stats['offen'] ?></div>
    <div class="stat__hint"><?= (int)$stats['maengel'] ?> Mängel · <?= (int)$stats['aufgaben'] ?> Aufgaben<?= $stats['aus_divera'] > 0 ? ' · ' . (int)$stats['aus_divera'] . ' aus Divera' : '' ?></div>
  </a>
  <div class="stat">
    <div class="stat__label">Überfällig</div>
    <div class="stat__value"<?= $stats['ueberfaellig'] > 0 ? ' style="color:var(--bad)"' : '' ?>><?= (int)$stats['ueberfaellig'] ?></div>
    <div class="stat__hint">Frist überschritten</div>
  </div>
  <div class="stat">
    <div class="stat__label">Ohne Zuständigkeit</div>
    <div class="stat__value"<?= $stats['ohne_zustaendig'] > 0 ? ' style="color:var(--warn)"' : '' ?>><?= (int)$stats['ohne_zustaendig'] ?></div>
    <div class="stat__hint">niemand kümmert sich</div>
  </div>
  <div class="stat">
    <div class="stat__label">Ältester offener Punkt</div>
    <div class="stat__value"><?= $stats['offen'] > 0 ? (int)$stats['aeltester_tage'] . ' Tage' : '–' ?></div>
    <div class="stat__hint">seit der Meldung</div>
  </div>
</div>

<section class="card" id="punkte">
  <div class="card__head">
    <h2><?= $filter['zustand'] === 'erledigt' ? 'Erledigte Punkte' : ($filter['zustand'] === 'alle' ? 'Alle Punkte' : 'Offene Punkte') ?></h2>
    <span class="muted small"><?= (int)$gesamt ?><?= $filter['standort'] ? ' · ' . e(function_exists('standort_pfad') ? standort_pfad((int)$filter['standort'], $standorte) : '') . ' und darunter' : '' ?></span>
  </div>
  <form method="get" action="<?= e(url()) ?>" data-autosubmit>
    <input type="hidden" name="p" value="unterkunft">
    <div class="filters">
      <div class="field">
        <label for="q">Suche</label>
        <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>" placeholder="Titel, Ort, Melder">
      </div>
      <div class="field">
        <label for="zustand">Zustand</label>
        <select id="zustand" name="zustand">
          <?php foreach (['offen' => 'offen', 'erledigt' => 'erledigt', 'alle' => 'alle'] as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= $filter['zustand'] === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="art">Art</label>
        <select id="art" name="art">
          <option value="">alle</option>
          <?php foreach (UNTERKUNFT_ARTEN as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= $filter['art'] === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('unterkunft_status', $filter['status_id'], 'alle') ?></select>
      </div>
      <div class="field">
        <label for="prioritaet_id">Dringlichkeit</label>
        <select id="prioritaet_id" name="prioritaet_id"><?= list_options('todo_prioritaet', $filter['prioritaet_id'], 'alle') ?></select>
      </div>
      <?php if ($standorte && function_exists('standort_optionen')): ?>
        <div class="field">
          <label for="standort">Ort (samt allem darunter)</label>
          <select id="standort" name="standort"><?= standort_optionen($standorte, $filter['standort'], 'überall') ?></select>
        </div>
      <?php endif; ?>
    </div>
  </form>
  <?php if (!$punkte): ?>
    <div class="empty"><?= $gefiltert ? 'Kein Punkt passt zu diesem Filter.' : 'Nichts offen – die Unterkunft ist in Ordnung.' ?>
      <?php if ($gefiltert): ?><br><a href="<?= e(url('unterkunft')) ?>#punkte">Filter zurücksetzen</a><?php endif; ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Punkt</th><th>Ort</th><th>Dringlichkeit</th><th>Stand</th><th>Zuständig</th><th>Frist</th><th class="num">Alter</th></tr></thead>
        <tbody>
        <?php foreach ($punkte as $p): $ort = unterkunft_ort_text($p, $standorte); $final = (int)$p['status_final'] === 1; ?>
          <tr<?= $final ? ' class="is-muted"' : '' ?>>
            <td><a href="<?= e(url('unterkunft_punkt', ['id' => $p['id']])) ?>"><strong><?= e((string)$p['titel']) ?></strong></a>
              <?php if ((string)$p['art'] === 'aufgabe'): ?><span class="badge badge--outline">Aufgabe</span><?php endif; ?>
              <?php if ((string)$p['divera_entry_id'] !== ''): ?><span class="badge badge--outline" title="aus dem Divera-Formular">Divera</span><?php endif; ?>
              <div class="small muted"><?= e(trim((string)($p['melder_user_name'] ?: $p['melder_name'])) ?: 'ohne Melder') ?> · <?= e(de_date(substr((string)$p['created_at'], 0, 10))) ?></div></td>
            <td class="small"><?php if ((int)$p['standort_id'] > 0): ?><a href="<?= e(url('standorte', ['id' => $p['standort_id']])) ?>"><?= e($ort) ?></a><?php else: ?><?= $ort !== '' ? e($ort) . ' <span class="muted">(Freitext)</span>' : '<span class="muted">–</span>' ?><?php endif; ?></td>
            <td class="small"><?= badge($p['prioritaet_label'] ? ['label' => $p['prioritaet_label'], 'color' => $p['prioritaet_color']] : null, '–') ?></td>
            <td class="small"><?= badge($p['status_label'] ? ['label' => $p['status_label'], 'color' => $p['status_color']] : null, '–') ?>
              <?php if (trim((string)$p['firma']) !== ''): ?><div class="muted"><?= e((string)$p['firma']) ?></div><?php endif; ?></td>
            <td class="small"><?= trim((string)($p['zustaendig_name'] ?? '')) !== '' ? e((string)$p['zustaendig_name']) : '<span style="color:var(--warn)">niemand</span>' ?></td>
            <td class="small nowrap"><?php if ((string)$p['faellig_am'] !== ''): ?><span<?= unterkunft_ueberfaellig($p) ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= e(de_date((string)$p['faellig_am'])) ?></span><?php else: ?><span class="muted">–</span><?php endif; ?></td>
            <td class="num small"><?= $final && (string)$p['erledigt_am'] !== '' ? 'erledigt ' . e(de_date(substr((string)$p['erledigt_am'], 0, 10))) : (int)unterkunft_alter_tage($p) . ' T' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_partial('partials/seiten', ['blatt' => $blatt, 'route' => 'unterkunft', 'params' => $seitenParams, 'anker' => 'punkte']) ?>
  <?php endif; ?>
  <?php if ($defektFormulare): ?>
    <p class="small muted" style="margin:.6rem 0 0">Mängel kommen auch aus Divera:
      <?php foreach ($defektFormulare as $i => $f): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= e(url('admin_divera_form', ['id' => $f['id']])) ?>"><?= e((string)$f['name']) ?></a><?= (int)$f['auto_import'] ? ' (automatisch' . ($f['last_sync'] ? ', zuletzt ' . e(de_datetime((string)$f['last_sync'])) : '') . ')' : ' (von Hand)' ?><?php endforeach; ?>.</p>
  <?php elseif (can('admin')): ?>
    <p class="small muted" style="margin:.6rem 0 0">Noch kein Divera-Formular für Defekte eingebunden – unter
      <a href="<?= e(url('admin_divera')) ?>">Verwaltung → Divera 24/7</a> das Formular „Als Defektformular" übernehmen.</p>
  <?php endif; ?>
</section>

<section class="card" id="raeume">
  <div class="card__head">
    <h2>Räume und Lagerflächen</h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('standorte')) ?>">Alle Plätze</a>
  </div>
  <?php if (!$gebaeude['gebaeude']): ?>
    <div class="empty">Noch keine Gebäude, Hallen oder Höfe angelegt.<?= can('admin') ? ' <a href="' . e(url('admin_standorte')) . '">Plätze pflegen</a>' : '' ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Platz</th><th>Darunter</th><th class="num">Offene Punkte</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($gebaeude['gebaeude'] as $g): $typ = $typVon($g['platz']); ?>
          <tr>
            <td><a href="<?= e(url('standorte', ['id' => $g['platz']['id']])) ?>"><?= e($typ['icon']) ?> <strong><?= e((string)$g['platz']['name']) ?></strong></a>
              <div class="small muted"><?= e($typ['label']) ?></div></td>
            <td class="small"><?= e(unterkunft_darunter_text($g['darunter'])) ?: '<span class="muted">nichts angelegt</span>' ?></td>
            <td class="num"><?php if ($g['offen'] > 0): ?><a href="<?= e(url('unterkunft', ['standort' => $g['platz']['id']])) ?>#punkte"><strong><?= (int)$g['offen'] ?></strong></a><?php else: ?><span class="muted">0</span><?php endif; ?></td>
            <td class="nowrap" style="text-align:right"><?php if ($melden || $verwalten): ?><a class="btn btn--sec btn--sm" href="<?= e(url('unterkunft_punkt_edit', ['standort_id' => $g['platz']['id']])) ?>">+ Mangel hier</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($gebaeude['ohne'] > 0): ?>
      <p class="small muted" style="margin:.5rem 0 0"><a href="<?= e(url('unterkunft', ['ohne_ort' => 1])) ?>#punkte"><?= (int)$gebaeude['ohne'] ?> offene(r) Punkt(e)</a> ohne zugeordneten Platz – etwa aus Divera mit einem Ort, den die Anwendung nicht kennt.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($kosten !== null): ?>
<section class="card" id="kosten">
  <div class="card__head">
    <h2>Gebäudekosten <?= (int)$jahr ?></h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('budget_objektkonten', ['jahr' => $jahr])) ?>">Objektkonten im Budget</a>
  </div>
  <?php if (!$kosten['zeilen']): ?>
    <div class="empty">Noch keine Objektkonten angelegt – im Budget lassen sich Kategorien wie Miete, Nebenkosten und Instandhaltung zu Konten bündeln.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Objektkonto</th><th class="num">Ausgaben</th><th class="num">Anteil</th><th class="num">Einnahmen</th><th class="num">Buchungen</th></tr></thead>
        <tbody>
        <?php foreach ($kosten['zeilen'] as $z): ?>
          <tr>
            <td><strong><?= e(objektkonto_titel($z['konto'])) ?></strong>
              <?php if ($z['posten']): ?><div class="small muted"><?= e(implode(' · ', array_map(static fn($po) => $po['label'], array_slice($z['posten'], 0, 4)))) ?><?= count($z['posten']) > 4 ? ' …' : '' ?></div><?php endif; ?></td>
            <td class="num"><?= $z['ausgaben'] > 0 ? e(money($z['ausgaben'])) : '<span class="muted">–</span>' ?></td>
            <td class="num small"><?= $z['anteil_ausgaben'] !== null ? e(number_format($z['anteil_ausgaben'], 1, ',', '.')) . ' %' : '' ?></td>
            <td class="num" style="color:var(--ok)"><?= $z['einnahmen'] > 0 ? '+' . e(money($z['einnahmen'])) : '' ?></td>
            <td class="num small"><?= (int)$z['anzahl'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th>Alle Buchungen des Jahres</th><th class="num"><?= e(money($kosten['summe']['ausgaben'])) ?></th><th></th><th class="num"><?= $kosten['summe']['einnahmen'] > 0 ? '+' . e(money($kosten['summe']['einnahmen'])) : '' ?></th><th></th></tr></tfoot>
      </table>
    </div>
    <?php if ($kosten['ohne']['ausgaben'] > 0): ?><p class="small muted" style="margin:.5rem 0 0"><?= e(money($kosten['ohne']['ausgaben'])) ?> Ausgaben liegen auf Kategorien ohne Objektkonto.</p><?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($zaehler !== null): ?>
<section class="card" id="zaehler">
  <div class="card__head">
    <h2>Zähler</h2>
    <div class="btnrow">
      <span class="muted small">Kosten <?= (int)$jahr ?>: <strong><?= e(money((float)$zaehler['stats']['kosten_jahr'])) ?></strong><?= (int)$zaehler['stats']['alt'] > 0 ? ' · <span style="color:var(--warn)">' . (int)$zaehler['stats']['alt'] . ' ohne frischen Stand</span>' : '' ?></span>
      <a class="btn btn--sec btn--sm" href="<?= e(url('verbrauch')) ?>">Zum Verbrauch</a>
    </div>
  </div>
  <div class="stats">
    <?php foreach ($zaehler['stats']['je_art'] as $art => $a): if ((int)$a['zaehler'] === 0) { continue; } ?>
      <div class="stat">
        <div class="stat__label"><?= e(METER_ARTEN[$art]['label'] ?? $art) ?> <?= (int)$jahr ?></div>
        <div class="stat__value" style="font-size:1.2rem"><?= e(menge($a['jahr'], (string)$a['einheit'], 0)) ?></div>
        <div class="stat__hint"><?= e(money((float)$a['kosten'])) ?> · letzte 30 Tage <?= e(menge($a['tage30'], (string)$a['einheit'], 0)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$zaehler['meter']): ?>
    <div class="empty">Noch kein Zähler angelegt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zähler</th><th>Art</th><th>Ort</th><th class="num">Letzter Stand</th><th>Abgelesen</th></tr></thead>
        <tbody>
        <?php foreach ($zaehler['meter'] as $m): $alter = meter_stand_alter($m); ?>
          <tr<?= (int)($m['is_active'] ?? 1) !== 1 ? ' class="is-muted"' : '' ?>>
            <td><a href="<?= e(url('meter', ['id' => $m['id']])) ?>"><strong><?= e((string)$m['name']) ?></strong></a>
              <?php if (trim((string)($m['parent_name'] ?? '')) !== ''): ?><div class="small muted">Unterzähler von <?= e((string)$m['parent_name']) ?></div><?php endif; ?></td>
            <td class="small"><?= e(METER_ARTEN[(string)$m['art']]['label'] ?? (string)$m['art']) ?></td>
            <td class="small"><?= trim((string)($m['stellplatz_name'] ?? '')) !== '' ? '<a href="' . e(url('standorte', ['id' => $m['standort_id']])) . '">' . e((string)$m['stellplatz_name']) . '</a>' : '<span class="muted">–</span>' ?></td>
            <td class="num nowrap"><?= $m['letzter_stand'] !== null ? e(menge($m['letzter_stand'], (string)$m['einheit'], 1)) : '<span class="muted">–</span>' ?></td>
            <td class="small nowrap"><?= (string)($m['letzte_ablesung'] ?? '') !== '' ? '<span' . ($alter['stufe'] === 'alt' ? ' style="color:var(--warn)"' : '') . '>' . e(de_date(substr((string)$m['letzte_ablesung'], 0, 10))) . '</span>' : '<span class="muted">nie</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
