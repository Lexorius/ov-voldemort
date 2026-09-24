<?php
/** @var array $gruppe @var array $geraete @var ?array $connector
 *  @var array $bestandConnectoren @var int $warn */
$darf = can('manage_radios');
$id = (int)$gruppe['id'];
$fehlend = 0;
foreach ($geraete as $g) {
    if (radio_gesehen($g)['stufe'] !== 'frisch') {
        $fehlend++;
    }
}
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$gruppe['name']) ?></h1>
    <p><?= (int)$gruppe['geraete'] ?> Gerät(e)
      <?= trim((string)$gruppe['lagerort']) !== '' ? ' · ' . e((string)$gruppe['lagerort']) : '' ?>
      · <?= e(sim_ziel_text($gruppe)) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('radio_group_edit', ['id' => $id])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('radios')) ?>">Alle Geräte</a>
  </div>
</div>

<?php if (trim((string)($gruppe['beschreibung'] ?? '')) !== ''): ?>
  <div class="card"><?= nl2br(e((string)$gruppe['beschreibung'])) ?></div>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Geräte in dieser Gruppe</h2>
    <span class="muted small"><?= count($geraete) ?></span>
  </div>
  <?php if (!$geraete): ?>
    <div class="empty">Dieser Gruppe ist noch kein Gerät zugeordnet.
      Das steht beim jeweiligen Gerät unter „Gruppe".</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Gerät</th><th>Art</th><th>Karte</th><th>Zuletzt gesehen</th></tr></thead>
        <tbody>
        <?php foreach ($geraete as $r): ?>
          <?php $gesehen = radio_gesehen($r); ?>
          <tr<?= (int)$r['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td>
              <a href="<?= e(url('radio', ['id' => $r['id']])) ?>"><strong><?= e((string)$r['bezeichnung']) ?></strong></a>
              <?php if (trim((string)$r['funkrufname']) !== ''): ?>
                <div class="small muted"><?= e((string)$r['funkrufname']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= badge($r['typ_label']
                ? ['label' => $r['typ_label'], 'color' => $r['typ_color']] : null, '–') ?></td>
            <td class="small"><?= (int)$r['karten'] > 0
                ? (int)$r['karten'] . ' Karte(n)' : '<span class="muted">keine</span>' ?></td>
            <td class="small">
              <?php if ($gesehen['stufe'] === 'nie'): ?>
                <span class="muted">–</span>
              <?php else: ?>
                <span<?= $gesehen['stufe'] === 'alt' ? ' style="color:var(--warn)"' : '' ?>>
                  <?= e(de_datetime((string)$r['zuletzt_gesehen'])) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($fehlend > 0): ?>
      <p class="small muted"><?= (int)$fehlend ?> Gerät(e) wurden länger nicht gemeldet.
        Eine Meldung über den QR-Code der Gruppe setzt alle auf einmal – wenn wirklich alle da sind.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?= render_partial('partials/bestand_qr', [
    'ziel' => $gruppe, 'art' => 'gruppe', 'connector' => $connector,
    'bestandConnectoren' => $bestandConnectoren, 'qrName' => radio_group_qr_name($gruppe),
]) ?>
