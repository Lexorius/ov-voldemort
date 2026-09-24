<?php
/** @var array $sims @var array $stats @var int $warn @var array $filter */
$darf = can('manage_sims');
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('sim_modul_name', 'SIM-Karten')) ?></h1>
    <p><?= nl2br(e((string)setting('sim_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('sim_edit')) ?>">+ SIM-Karte</a>
      <a class="btn btn--sec" href="<?= e(url('sims_export', $filter)) ?>">CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Karten</div>
    <div class="stat__value"><?= (int)$stats['anzahl'] ?></div>
    <div class="stat__hint"><?= (int)$stats['ohne_zuordnung'] ?> ohne feste Zuordnung</div>
  </div>
  <div class="stat">
    <div class="stat__label">TETRA</div>
    <div class="stat__value"><?= (int)$stats['tetra'] ?></div>
    <div class="stat__hint">Sicherheitskarten</div>
  </div>
  <div class="stat">
    <div class="stat__label">Monatlich</div>
    <div class="stat__value"><?= e(money((float)$stats['kosten'])) ?></div>
    <div class="stat__hint">Summe der hinterlegten Tarife</div>
  </div>
  <div class="stat">
    <div class="stat__label">Vertrag läuft aus</div>
    <div class="stat__value"><?= (int)$stats['vertrag_bald'] ?></div>
    <div class="stat__hint">in den nächsten <?= (int)$warn ?> Tagen oder schon vorbei</div>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="sims">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filter['q']) ?>"
             placeholder="Nummer, ICCID, Anbieter, Gerät">
    </div>
    <div class="field">
      <label for="karte_art">Kartenwelt</label>
      <select id="karte_art" name="karte_art">
        <option value="">alle</option>
        <?php foreach (SIM_ARTEN as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)($filter['karte_art'] ?? '') ? 'selected' : '' ?>>
            <?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="typ_id">Art</label>
      <select id="typ_id" name="typ_id"><?= list_options('sim_typ', $filter['typ_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="status_id">Status</label>
      <select id="status_id" name="status_id"><?= list_options('sim_status', $filter['status_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="ziel_typ">Gehört zu</label>
      <select id="ziel_typ" name="ziel_typ">
        <option value="">alle</option>
        <?php foreach (SIM_ZIELE as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === (string)$filter['ziel_typ'] ? 'selected' : '' ?>>
            <?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach (['' => 'Art', 'nummer' => 'Rufnummer', 'vertrag' => 'Vertragsende',
                        'kosten' => 'Kosten', 'neu' => 'zuletzt angelegt'] as $key => $label): ?>
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
    <h2>Karten</h2>
    <span class="muted small"><?= count($sims) ?></span>
  </div>
  <?php if (!$sims): ?>
    <div class="empty">Keine Karte gefunden.
      <?php if ($darf): ?><br><a href="<?= e(url('sim_edit')) ?>">Erste Karte anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Rufnummer / ISSI</th><th>Art</th><th>Gehört zu</th><th>Vertrag</th>
          <?php if ($darf): ?><th>PIN / PUK</th><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($sims as $s): ?>
          <?php $vertrag = sim_vertrag($s, $warn); ?>
          <tr<?= (int)$s['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <?php if (sim_ist_tetra($s)): ?>
                <strong class="mono"><?= e(sim_kennung($s)) ?: '–' ?></strong>
                <span class="badge badge--outline">TETRA</span>
                <?php if (trim((string)$s['opta']) !== ''): ?>
                  <div class="small muted mono"><?= e((string)$s['opta']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <strong><?= phone_html((string)$s['rufnummer'], '–') ?></strong>
              <?php endif; ?>
              <?php if ((int)$s['is_active'] !== 1): ?>
                <span class="badge badge--muted">ausgemustert</span>
              <?php endif; ?>
              <?php if (trim((string)$s['iccid']) !== ''): ?>
                <div class="small muted mono"><?= sim_ist_tetra($s) ? 'Karte' : 'ICCID' ?>
                  <?= e((string)$s['iccid']) ?></div>
              <?php endif; ?>
              <?php if (trim((string)($s['funkgeraet'] ?? '')) !== ''): ?>
                <div class="small muted">im Gerät:
                  <?php if (can('view_radios')): ?>
                    <a href="<?= e(url('radio', ['id' => $s['radio_id']])) ?>"><?= e((string)$s['funkgeraet']) ?></a>
                  <?php else: ?><?= e((string)$s['funkgeraet']) ?><?php endif; ?></div>
              <?php elseif (trim((string)$s['geraet']) !== ''): ?>
                <div class="small muted">steckt in: <?= e((string)$s['geraet']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= badge($s['typ_label'] ? ['label' => $s['typ_label'], 'color' => $s['typ_color']] : null, '–') ?>
              <?php if ($s['status_label']): ?>
                <div style="margin-top:.25rem"><?= badge(['label' => $s['status_label'], 'color' => $s['status_color']]) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ((string)$s['ziel_typ'] === 'fahrzeug' && $s['fahrzeug_label'] && can('view_vehicles')): ?>
                <a href="<?= e(url('vehicle', ['id' => $s['ziel_id']])) ?>"><?= e(sim_ziel_text($s)) ?></a>
              <?php else: ?>
                <?= e(sim_ziel_text($s)) ?>
              <?php endif; ?>
              <div class="muted"><?= e(SIM_ZIELE[(string)$s['ziel_typ']] ?? '') ?></div>
            </td>
            <td class="small">
              <?php if (!sim_hat_vertrag($s)): ?>
                <span class="muted">ohne Vertrag</span>
              <?php elseif ($vertrag['stufe'] === 'offen'): ?>
                <span class="muted">unbefristet</span>
              <?php else: ?>
                <span<?= $vertrag['stufe'] === 'faellig' ? ' style="color:var(--bad);font-weight:700"'
                    : ($vertrag['stufe'] === 'bald' ? ' style="color:var(--warn);font-weight:700"' : '') ?>>
                  <?= e(de_date((string)$s['vertrag_bis'])) ?></span>
              <?php endif; ?>
              <?php if ($s['kosten_monat'] !== null): ?>
                <div class="muted"><?= e(money((float)$s['kosten_monat'])) ?> / Monat</div>
              <?php endif; ?>
              <?php if (trim((string)$s['anbieter']) !== ''): ?>
                <div class="muted"><?= e((string)$s['anbieter']) ?></div>
              <?php endif; ?>
            </td>
            <?php if ($darf): ?>
              <td class="small mono">
                <?php if (trim((string)$s['pin']) !== ''): ?>
                  <div>
                    <span data-geheim="<?= e((string)$s['pin']) ?>"><?= e(sim_verdeckt((string)$s['pin'])) ?></span>
                    <span><button type="button" class="btn btn--ghost btn--sm"
                                  data-geheim-zeigen data-aus="verbergen">PIN</button></span>
                  </div>
                <?php else: ?><span class="muted">–</span><?php endif; ?>
                <?php if (trim((string)$s['puk']) !== ''): ?>
                  <div>
                    <span data-geheim="<?= e((string)$s['puk']) ?>"><?= e(sim_verdeckt((string)$s['puk'])) ?></span>
                    <span><button type="button" class="btn btn--ghost btn--sm"
                                  data-geheim-zeigen data-aus="verbergen">PUK</button></span>
                  </div>
                <?php endif; ?>
              </td>
              <td style="text-align:right">
                <a class="btn btn--sec btn--sm" href="<?= e(url('sim_edit', ['id' => $s['id']])) ?>">Bearbeiten</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($darf): ?>
      <p class="small muted">PIN und PUK sieht nur die Leitung. „Zeigen" blendet sie kurz ein –
        auf einem geteilten Bildschirm besser zweimal überlegen.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
