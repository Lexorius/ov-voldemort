<?php
/** @var array $karten @var array $stats @var int $warn @var array $filter */
$darf = can('manage_tankkarten');
$anbieterText = [];
foreach (array_slice($stats['anbieter'], 0, 3, true) as $name => $n) {
    $anbieterText[] = $name . ' ' . $n;
}
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('tankkarte_modul_name', 'Tankkarten')) ?></h1>
    <p><?= nl2br(e((string)setting('tankkarte_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('tankkarte_edit')) ?>">+ Tankkarte</a>
      <a class="btn btn--sec" href="<?= e(url('tankkarten_import')) ?>">Importieren</a>
      <a class="btn btn--sec" href="<?= e(url('tankkarten_export', $filter)) ?>">CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Karten</div>
    <div class="stat__value"><?= (int)$stats['anzahl'] ?></div>
    <div class="stat__hint"><?= (int)$stats['ohne_fahrzeug'] ?> ohne Fahrzeug</div>
  </div>
  <div class="stat">
    <div class="stat__label">Anbieter</div>
    <div class="stat__value"><?= count($stats['anbieter']) ?></div>
    <div class="stat__hint"><?= $anbieterText ? e(implode(' · ', $anbieterText)) : '–' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Laden</div>
    <div class="stat__value"><?= (int)$stats['laden'] ?></div>
    <div class="stat__hint">schalten Ladesäulen frei<?= (int)$stats['gesperrt'] > 0 ? ' · <span style="color:var(--bad)">' . (int)$stats['gesperrt'] . ' gesperrt</span>' : '' ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Läuft ab</div>
    <div class="stat__value"><?= (int)$stats['gueltig_bald'] ?></div>
    <div class="stat__hint">in den nächsten <?= (int)$warn ?> Tagen oder schon vorbei</div>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="tankkarten">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>" placeholder="Nummer, Anbieter, Aufbewahrung">
    </div>
    <div class="field">
      <label for="anbieter_id">Anbieter</label>
      <select id="anbieter_id" name="anbieter_id"><?= list_options('tankkarte_anbieter', $filter['anbieter_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="art">Freischaltung</label>
      <select id="art" name="art">
        <option value="">alle</option>
        <?php foreach (TANKKARTE_ARTEN as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)$filter['art'] ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('tankkarte_status', $filter['status_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach (['' => 'Anbieter', 'nummer' => 'Nummer', 'gueltig' => 'Gültig bis', 'neu' => 'zuletzt angelegt'] as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)$filter['sort'] ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="aktiv">Bestand</label>
      <select id="aktiv" name="aktiv">
        <option value="">nur geführte</option>
        <option value="alle" <?= $filter['aktiv'] === 'alle' ? 'selected' : '' ?>>auch gesperrte, zurückgegebene, abgelaufene</option>
      </select>
    </div>
  </div>
</form>

<section class="card">
  <div class="card__head">
    <h2>Karten</h2>
    <span class="muted small"><?= count($karten) ?></span>
  </div>
  <?php if (!$karten): ?>
    <div class="empty">Keine Karte gefunden.
      <?php if ($darf): ?><br><a href="<?= e(url('tankkarte_edit')) ?>">Erste Karte anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Karte</th><th>Freischaltung</th><th>Fahrzeuge</th><th>Liegt bei</th><th>Gültig bis</th>
          <?php if ($darf): ?><th>PIN</th><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($karten as $k): ?>
          <?php $gueltig = tankkarte_gueltig($k, $warn); ?>
          <tr<?= (int)$k['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <?= badge($k['anbieter_label'] ? ['label' => $k['anbieter_label'], 'color' => $k['anbieter_color']] : null, 'ohne Anbieter') ?>
              <strong class="mono"><?= e((string)$k['nummer']) ?></strong>
              <?php if (tankkarte_gesperrt($k)): ?><span class="badge" style="background:var(--bad)" title="<?= e(tankkarte_verbleib_text($k)) ?>">gesperrt</span>
              <?php elseif ((string)($k['verbleib'] ?? '') !== ''): ?><span class="badge badge--muted"><?= e(TANKKARTE_VERBLEIB[(string)$k['verbleib']]) ?></span>
              <?php elseif ((int)$k['is_active'] !== 1): ?><span class="badge badge--muted">ausgemustert</span><?php endif; ?>
              <?php if ((string)($k['verbleib'] ?? '') !== ''): ?><div class="small" style="color:var(--bad)"><?= e(tankkarte_verbleib_text($k)) ?></div><?php endif; ?>
              <?php if (trim((string)$k['kundennummer']) !== ''): ?><div class="small muted mono">Kunde <?= e((string)$k['kundennummer']) ?></div><?php endif; ?>
              <?php if ($k['status_label']): ?><div style="margin-top:.25rem"><?= badge(['label' => $k['status_label'], 'color' => $k['status_color']]) ?></div><?php endif; ?>
            </td>
            <td class="small">
              <?php if (tankkarte_laedt($k)): ?><span class="badge" style="background:#0369a1">⚡ Laden</span><?php endif; ?>
              <?php if (tankkarte_tankt($k)): ?><span class="badge badge--outline">Kraftstoff</span><?php endif; ?>
            </td>
            <td class="small">
              <?php if (!$k['fahrzeuge']): ?>
                <span class="muted">keines</span>
              <?php else: ?>
                <?php foreach ($k['fahrzeuge'] as $v): ?>
                  <div><?php if (can('view_vehicles')): ?><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"><?= e($v['bezeichnung']) ?></a><?php else: ?><?= e($v['bezeichnung']) ?><?php endif; ?><?= $v['kennzeichen'] !== '' ? ' <span class="muted">' . e($v['kennzeichen']) . '</span>' : '' ?></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= trim((string)($k['person_label'] ?? '')) !== '' ? e((string)$k['person_label']) : '' ?>
              <?php if (trim((string)$k['aufbewahrung']) !== ''): ?><div class="muted"><?= e((string)$k['aufbewahrung']) ?></div><?php endif; ?>
              <?php if (trim((string)($k['person_label'] ?? '')) === '' && trim((string)$k['aufbewahrung']) === ''): ?><span class="muted">–</span><?php endif; ?>
            </td>
            <td class="small">
              <?php if ($gueltig['stufe'] === 'offen'): ?>
                <span class="muted">unbefristet</span>
              <?php else: ?>
                <span<?= $gueltig['stufe'] === 'faellig' ? ' style="color:var(--bad);font-weight:700"' : ($gueltig['stufe'] === 'bald' ? ' style="color:var(--warn);font-weight:700"' : '') ?>><?= e(de_date((string)$k['gueltig_bis'])) ?></span>
              <?php endif; ?>
            </td>
            <?php if ($darf): ?>
              <td class="small mono">
                <?php if (tankkarte_hat_pin($k) && trim((string)$k['pin']) !== ''): ?>
                  <div>
                    <span data-geheim="<?= e((string)$k['pin']) ?>"><?= e(tankkarte_verdeckt((string)$k['pin'])) ?></span>
                    <span><button type="button" class="btn btn--ghost btn--sm" data-geheim-zeigen data-aus="verbergen">PIN</button></span>
                  </div>
                <?php else: ?><span class="muted">–</span><?php endif; ?>
              </td>
              <td style="text-align:right">
                <a class="btn btn--sec btn--sm" href="<?= e(url('tankkarte_edit', ['id' => $k['id']])) ?>">Bearbeiten</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($darf): ?>
      <p class="small muted">Die PIN sieht nur die Leitung. „PIN" blendet sie kurz ein – auf einem geteilten Bildschirm besser zweimal überlegen.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
