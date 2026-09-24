<?php
/** @var array $radio @var array $karten @var array $frei @var ?array $connector
 *  @var array $bestandConnectoren @var int $warn */
$darf = can('manage_radios');
$id = (int)$radio['id'];
$pruefung = radio_pruefung($radio, $warn);
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$radio['bezeichnung']) ?></h1>
    <p>
      <?= $radio['typ_label'] ? e((string)$radio['typ_label']) : 'Funkgerät' ?>
      <?= trim((string)$radio['funkrufname']) !== '' ? ' · ' . e((string)$radio['funkrufname']) : '' ?>
      · <?= e(radio_ziel_text($radio)) ?>
      <?php if (trim((string)($radio['gruppe_name'] ?? '')) !== ''): ?>
        · Gruppe <?= e((string)$radio['gruppe_name']) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('radio_edit', ['id' => $id])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('radios')) ?>">Alle Geräte</a>
  </div>
</div>

<section class="card">
  <h2>Angaben</h2>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Art</div>
      <div class="dl__value"><?= badge($radio['typ_label']
          ? ['label' => $radio['typ_label'], 'color' => $radio['typ_color']] : null, '–') ?>
        <?= $radio['status_label'] ? badge(['label' => $radio['status_label'], 'color' => $radio['status_color']]) : '' ?>
      </div></div>
    <?php foreach ([
        'Hersteller'     => (string)$radio['hersteller'],
        'Modell'         => (string)$radio['modell'],
        'Seriennummer'   => (string)$radio['seriennummer'],
        'Inventarnummer' => (string)$radio['inventarnummer'],
        'Funkrufname'    => (string)$radio['funkrufname'],
        'Standort'       => (string)$radio['standort'],
    ] as $label => $wert): ?>
      <?php if (trim($wert) !== ''): ?>
        <div class="dl__item"><div class="dl__label"><?= e($label) ?></div>
          <div class="dl__value"><?= e($wert) ?></div></div>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="dl__item"><div class="dl__label">Gehört zu</div>
      <div class="dl__value">
        <?php if ((string)$radio['ziel_typ'] === 'fahrzeug' && $radio['fahrzeug_label'] && can('view_vehicles')): ?>
          <a href="<?= e(url('vehicle', ['id' => $radio['ziel_id']])) ?>"><?= e(radio_ziel_text($radio)) ?></a>
        <?php else: ?>
          <?= e(radio_ziel_text($radio)) ?>
        <?php endif; ?>
      </div></div>
    <?php if (trim((string)($radio['gruppe_name'] ?? '')) !== ''): ?>
      <div class="dl__item"><div class="dl__label">Gruppe</div>
        <div class="dl__value"><a href="<?= e(url('radio_group', ['id' => $radio['group_id']])) ?>">
          <?= e((string)$radio['gruppe_name']) ?></a></div></div>
    <?php endif; ?>
    <?php if ($radio['beschafft_am']): ?>
      <div class="dl__item"><div class="dl__label">Beschafft am</div>
        <div class="dl__value"><?= e(de_date((string)$radio['beschafft_am'])) ?></div></div>
    <?php endif; ?>
    <div class="dl__item"><div class="dl__label">Prüfung</div>
      <div class="dl__value">
        <?php if ($pruefung['stufe'] === 'offen'): ?>
          <span class="muted">keine Frist hinterlegt</span>
        <?php else: ?>
          <span<?= $pruefung['stufe'] === 'faellig' ? ' style="color:var(--bad);font-weight:700"'
              : ($pruefung['stufe'] === 'bald' ? ' style="color:var(--warn);font-weight:700"' : '') ?>>
            <?= e(de_date((string)$radio['pruefung_bis'])) ?></span>
        <?php endif; ?>
      </div></div>
  </dl>
  <?php if (trim((string)($radio['notiz'] ?? '')) !== ''): ?>
    <p class="small muted"><?= nl2br(e((string)$radio['notiz'])) ?></p>
  <?php endif; ?>
</section>

<section class="card" id="karten">
  <div class="card__head">
    <h2>Karten im Gerät</h2>
    <span class="muted small"><?= count($karten) ?></span>
  </div>
  <?php if (!$karten): ?>
    <div class="empty">In diesem Gerät steckt keine Karte.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Karte</th><th>Art</th><th>Gehört zu</th><?php if ($darf): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($karten as $s): ?>
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
              <?php endif; ?>
              <?php if (can('manage_sims')): ?>
                <div class="small"><a href="<?= e(url('sim_edit', ['id' => $s['id']])) ?>">Karte bearbeiten</a></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= badge($s['typ_label']
                ? ['label' => $s['typ_label'], 'color' => $s['typ_color']] : null, '–') ?></td>
            <td class="small"><?= e(sim_ziel_text($s)) ?></td>
            <?php if ($darf): ?>
              <td style="text-align:right">
                <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="karte_weg">
                  <input type="hidden" name="radio_id" value="<?= $id ?>">
                  <input type="hidden" name="sim_id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit"
                          data-confirm="Karte aus dem Gerät nehmen? Sie liegt danach wieder frei.">Entnehmen</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($darf && can('view_sims')): ?>
    <details class="mt"<?= $karten ? '' : ' open' ?>>
      <summary>Karte buchen</summary>
      <?php if (!$frei): ?>
        <p class="small">Alle geführten Karten stecken bereits in einem Gerät.
          <a href="<?= e(url('sim_edit', ['ziel_typ' => (string)$radio['ziel_typ'],
              'ziel_id' => (int)($radio['ziel_id'] ?? 0)])) ?>">Neue Karte anlegen</a></p>
      <?php else: ?>
        <form method="post" action="<?= e(url('radio_action')) ?>" class="form" style="margin-top:.8rem">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="karte_buchen">
          <input type="hidden" name="radio_id" value="<?= $id ?>">
          <div class="field">
            <label for="sim_id">Freie Karte</label>
            <select id="sim_id" name="sim_id" required>
              <?php foreach ($frei as $s): ?>
                <option value="<?= (int)$s['id'] ?>">
                  <?= e(sim_bezeichnung($s)) ?><?= $s['typ_label'] ? ' · ' . e((string)$s['typ_label']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field field--check">
            <input type="checkbox" id="zuordnung" name="zuordnung" value="1" checked>
            <label for="zuordnung">Karte übernimmt die Zuordnung des Geräts
              (<?= e(radio_ziel_text($radio)) ?>)</label>
          </div>
          <div class="btnrow">
            <button class="btn" type="submit">Karte buchen</button>
          </div>
        </form>
      <?php endif; ?>
    </details>
  <?php endif; ?>
</section>

<?= render_partial('partials/bestand_qr', [
    'ziel' => $radio, 'art' => 'geraet', 'connector' => $connector,
    'bestandConnectoren' => $bestandConnectoren, 'qrName' => radio_qr_name($radio),
]) ?>
