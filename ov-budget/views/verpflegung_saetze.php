<?php
/** @var array $saetze @var ?array $aktuell @var string $heute */
$darf = can('manage_events');
?>
<div class="pagehead">
  <div>
    <h1>Verpflegung: Tagessätze</h1>
    <p>Je Person und Tag steht ein Betrag zur Verfügung, verteilt auf Frühstück, Mittag- und Abendessen.
       Ändert sich der Satz im Jahr, bekommt der alte ein Ende und der neue beginnt am Tag danach –
       gerechnet wird mit dem Satz, der am Beginn der Veranstaltung gilt.</p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?>
      <a class="btn" href="<?= e(url('verpflegung_satz_edit')) ?>">+ Tagessatz</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('events')) ?>">Zu den Veranstaltungen</a>
  </div>
</div>

<?php if (!$aktuell): ?>
  <div class="alert alert--warn">Für heute gilt kein Tagessatz – Veranstaltungen mit Verpflegung rechnen
    dann nichts aus.</div>
<?php endif; ?>

<section class="card">
  <?php if (!$saetze): ?>
    <div class="empty">Noch kein Tagessatz angelegt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Gültig</th><th class="num">Tagessatz</th><th class="num">Frühstück</th>
                   <th class="num">Mittag</th><th class="num">Abend</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($saetze as $s):
            $bis = (string)($s['gueltig_bis'] ?? '');
            $gilt = (string)$s['gueltig_von'] <= $heute && ($bis === '' || $bis >= $heute);
            $vorbei = $bis !== '' && $bis < $heute;
            $teile = tagessatz_mahlzeiten($s);
        ?>
          <tr<?= $vorbei ? ' class="is-muted"' : '' ?>>
            <td class="nowrap">
              <?php if ($darf): ?>
                <a href="<?= e(url('verpflegung_satz_edit', ['id' => $s['id']])) ?>"><?= e(de_date((string)$s['gueltig_von'])) ?> – <?= $bis !== '' ? e(de_date($bis)) : 'offen' ?></a>
              <?php else: ?>
                <?= e(de_date((string)$s['gueltig_von'])) ?> – <?= $bis !== '' ? e(de_date($bis)) : 'offen' ?>
              <?php endif; ?>
              <?php if ($gilt): ?> <span class="badge" style="background:#15803d">gilt</span><?php endif; ?>
              <?php if (trim((string)($s['notiz'] ?? '')) !== ''): ?><div class="small muted"><?= e((string)$s['notiz']) ?></div><?php endif; ?>
            </td>
            <td class="num"><strong><?= e(money((float)$s['tagessatz'])) ?></strong></td>
            <?php foreach (['fruehstueck', 'mittag', 'abend'] as $k): ?>
              <td class="num small"><?= (int)$s['anteil_' . $k] ?> % <span class="muted">· <?= e(money($teile[$k])) ?></span></td>
            <?php endforeach; ?>
            <td></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
