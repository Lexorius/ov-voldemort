<?php
/**
 * „Besprochen in": Tagesordnungspunkte, die auf dieses Fahrzeug, Gerät oder
 * diesen Termin verweisen.
 * @var array $punkte  aus tps_fuer()
 */
$punkte ??= [];
?>
<section class="card" id="besprochen">
  <div class="card__head">
    <h2>In Besprechungen</h2>
    <span class="muted small"><?= count($punkte) ?></span>
  </div>
  <?php if (!$punkte): ?>
    <div class="empty">Noch in keinem Tagesordnungspunkt – beim Thema lässt sich der Bezug unter „Bezüge" setzen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <tbody>
        <?php foreach ($punkte as $p): ?>
          <tr>
            <td class="nowrap small"><?= $p['meeting_datum'] ? e(de_date((string)$p['meeting_datum'])) : '<span class="muted">Themenspeicher</span>' ?></td>
            <td><a href="<?= e(url('talking_point', ['id' => $p['id']])) ?>"><?= e((string)$p['titel']) ?></a>
              <?php if ($p['meeting_titel']): ?><div class="small muted"><?= e((string)$p['meeting_titel']) ?></div><?php endif; ?>
              <?php if ($p['ergebnis']): ?><div class="small"><?= e(mb_strimwidth((string)$p['ergebnis'], 0, 160, '…')) ?></div><?php endif; ?></td>
            <td><?= badge($p['status_label'] ? ['label' => $p['status_label'], 'color' => $p['status_color']] : null, '–') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
