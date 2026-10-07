<?php
/**
 * Fahrzeug-Kachel: Bild oder Platzhalter, Stempel, Status, Fristen, Aufträge.
 * Dieselbe Kachel in der Fahrzeugliste und an den Stell- und Lagerplätzen.
 *
 * @var array  $v           Zeile aus vehicle_query
 * @var array  $titelbilder vehicle_id => Titelbild (vfile_covers)
 * @var array  $fristen     Fristen dieses Fahrzeugs (vehicle_deadlines) – optional
 * @var string $stern       HTML des Anheft-Knopfs – optional, leer = keiner
 * @var bool   $mitPlatz    Platznamen in der Unterzeile zeigen (Vorgabe: ja)
 */
$titelbilder ??= [];
$fristen ??= [];
$stern ??= '';
$mitPlatz ??= true;
$aktiv = (int)($v['is_active'] ?? 1) === 1;
$stempel = vehicle_stamp($v);
$id = (int)$v['id'];
?>
<div class="kachel<?= $aktiv ? '' : ' kachel--aus' ?>" style="border-top-color:<?= e((string)($v['status_color'] ?? '') ?: '#94a3b8') ?>">
  <?php if ($stern !== ''): ?><div class="kachel__stern"><?= $stern ?></div><?php endif; ?>
  <a class="kachel__bild" href="<?= e(url('vehicle', ['id' => $id])) ?>">
    <?php if ($stempel !== null): ?>
      <span class="stempel<?= $aktiv ? '' : ' stempel--grau' ?>"><?= e($stempel) ?></span>
    <?php endif; ?>
    <?php if (isset($titelbilder[$id])): ?>
      <img alt="" loading="lazy" src="<?= e(url('vehicle_file', ['id' => $titelbilder[$id]['id'], 'vorschau' => 1])) ?>">
    <?php else: ?>
      <span class="kachel__leer" aria-hidden="true">⛟</span>
    <?php endif; ?>
  </a>
  <div class="kachel__text">
    <a class="item__title" href="<?= e(url('vehicle', ['id' => $id])) ?>"><?= e((string)$v['bezeichnung']) ?></a>
    <div class="item__sub small"><?= e(implode(' · ', array_filter([(string)($v['funkrufname'] ?? ''), (string)($v['kennzeichen'] ?? ''), $mitPlatz ? (string)($v['stellplatz_name'] ?? '') : '']))) ?></div>
    <div class="item__meta">
      <?= badge(!empty($v['status_label']) ? ['label' => $v['status_label'], 'color' => $v['status_color'] ?? ''] : null, 'ohne Status') ?>
      <?php if (($v['fms_status'] ?? null) !== null && $v['fms_status'] !== ''): ?>
        <span class="badge" style="background:<?= e(fms_color((int)$v['fms_status'])) ?>">S<?= (int)$v['fms_status'] ?></span>
      <?php endif; ?>
      <?= vehicle_frist_badges($fristen) ?>
      <?php if ((int)($v['offene_auftraege'] ?? 0) > 0): ?>
        <span class="badge badge--outline"><?= (int)$v['offene_auftraege'] ?> Auftrag/Aufträge</span>
      <?php endif; ?>
      <?php if (!$aktiv): ?><span class="badge badge--muted">ausgemustert</span><?php endif; ?>
    </div>
  </div>
</div>
