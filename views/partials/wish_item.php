<?php
/** @var array $w  Zeile aus wish_query() */
$dringColor = $w['dring_color'] ?: '#64748b';
?>
<a class="item" href="<?= e(url('wish', ['id' => $w['id']])) ?>" style="border-left-color:<?= e($dringColor) ?>">
  <div class="item__top">
    <div style="min-width:0">
      <div class="item__title"><?= e($w['bezeichnung']) ?></div>
      <div class="item__sub">
        <?= e($w['fachgruppe_label'] ?: 'ohne Fachgruppe') ?>
        <?php if ($w['anzahl'] > 1): ?>
          · <?= e(rtrim(rtrim(number_format((float)$w['anzahl'], 2, ',', '.'), '0'), ',')) ?>
          <?= e($w['einheit_label'] ?: 'Stück') ?>
        <?php endif; ?>
        <?php if (!empty($w['benoetigt_bis'])): ?>
          · benötigt bis <?= e(de_date($w['benoetigt_bis'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="item__amount"><?= e(money((float)$w['netto_gesamt'])) ?></div>
  </div>
  <div class="item__meta">
    <?= badge($w['status_label'] ? ['label' => $w['status_label'], 'color' => $w['status_color']] : null, 'ohne Status') ?>
    <?= badge($w['dring_label'] ? ['label' => $w['dring_label'], 'color' => $w['dring_color']] : null, 'ohne Dringlichkeit') ?>
    <?php if ((int)$w['nice_to_have']): ?><span class="badge badge--nice">nice to have</span><?php endif; ?>
    <?php if ((int)$w['anlagen'] > 0): ?>
      <span class="badge badge--outline">📎 <?= (int)$w['anlagen'] ?></span>
    <?php endif; ?>
    <?php if ((int)$w['votes'] > 0): ?>
      <span class="badge badge--outline">👍 <?= (int)$w['votes'] ?></span>
    <?php endif; ?>
    <?php if ($w['source'] === 'divera'): ?>
      <span class="badge badge--outline">Divera</span>
    <?php endif; ?>
    <?php if (!empty($w['budget_name'])): ?>
      <span class="badge badge--outline"><?= e($w['budget_name']) ?></span>
    <?php endif; ?>
  </div>
</a>
