<?php
/** @var array $jeArt @var string $heute */
$darf = can('manage_verbrauch');
?>
<div class="pagehead">
  <div>
    <h1>Tarife</h1>
    <p>Je Art ein Tarif mit Zeitraum. Für jeden Monat gilt der Tarif, der am 15. gültig ist;
       Kosten = Arbeitspreis × Verbrauch + Grundpreis anteilig.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('verbrauch')) ?>">Zu den Zählern</a>
  </div>
</div>

<?php foreach (METER_ARTEN as $key => $a): $block = $jeArt[$key]; ?>
  <section class="card" style="border-left:4px solid <?= e($a['color']) ?>">
    <div class="card__head">
      <h2><?= e($a['label']) ?></h2>
      <div class="btnrow">
        <?php if ($block['aktuell']): ?>
          <span class="small muted">heute: <?= e((string)$block['aktuell']['name']) ?></span>
        <?php else: ?>
          <span class="small" style="color:var(--warn)">kein gültiger Tarif</span>
        <?php endif; ?>
        <?php if ($darf): ?>
          <a class="btn btn--sec btn--sm" href="<?= e(url('tarif_edit', ['art' => $key])) ?>">+ Tarif</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$block['liste']): ?>
      <div class="empty">Noch kein Tarif für <?= e($a['label']) ?>.</div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Tarif</th><th>Gültig</th><th class="num">Arbeitspreis</th><th class="num">Grundpreis</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($block['liste'] as $t):
              $bis = (string)($t['gueltig_bis'] ?? '');
              $gilt = (string)$t['gueltig_von'] <= $heute && ($bis === '' || $bis >= $heute);
              $vorbei = $bis !== '' && $bis < $heute;
          ?>
            <tr<?= $vorbei ? ' class="is-muted"' : '' ?>>
              <td>
                <?php if ($darf): ?>
                  <a href="<?= e(url('tarif_edit', ['id' => $t['id']])) ?>"><strong><?= e((string)$t['name']) ?></strong></a>
                <?php else: ?><strong><?= e((string)$t['name']) ?></strong><?php endif; ?>
                <?php if ($gilt): ?> <span class="badge" style="background:#15803d">gilt</span><?php endif; ?>
                <?php if (trim((string)$t['anbieter']) !== ''): ?><div class="small muted"><?= e((string)$t['anbieter']) ?></div><?php endif; ?>
              </td>
              <td class="small nowrap"><?= e(de_date((string)$t['gueltig_von'])) ?> – <?= $bis !== '' ? e(de_date($bis)) : 'offen' ?></td>
              <td class="num nowrap"><?= e(number_format((float)$t['arbeitspreis'], 4, ',', '.')) ?> €/<?= e((string)$t['einheit']) ?></td>
              <td class="num nowrap"><?= (float)$t['grundpreis_monat'] > 0 ? e(money((float)$t['grundpreis_monat'])) . ' / Monat' : '–' ?></td>
              <td class="small"><?= trim((string)($t['notiz'] ?? '')) !== '' ? e(mb_substr((string)$t['notiz'], 0, 60)) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
