<?php
/** @var string $ansicht @var string $monat @var int $jahr @var int $mon @var string $heute @var ?array $raster
 *  @var array $eintraege @var array $jeTag @var array $quellen @var array $erlaubt @var bool $darfAnlegen @var array $haFehler */
$monatsnamen = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$vor = date('Y-m', mktime(0, 0, 0, $mon - 1, 1, $jahr));
$nach = date('Y-m', mktime(0, 0, 0, $mon + 1, 1, $jahr));
$qArgs = count($quellen) === count($erlaubt) ? [] : ['q' => $quellen];
$link = static fn(array $p) => url('kalender', $p + $qArgs);
$tage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('kalender_modul_name', 'Kalender')) ?></h1>
    <p><?= nl2br(e((string)setting('kalender_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darfAnlegen): ?>
      <a class="btn" href="<?= e(url('kalender_edit', ['datum' => $ansicht === 'monat' && substr($heute, 0, 7) === $monat ? $heute : $monat . '-01'])) ?>">+ Termin</a>
    <?php endif; ?>
    <a class="btn btn--sec<?= $ansicht === 'monat' ? ' is-active' : '' ?>" href="<?= e($link(['monat' => $monat])) ?>">Monat</a>
    <a class="btn btn--sec<?= $ansicht === 'liste' ? ' is-active' : '' ?>" href="<?= e($link(['ansicht' => 'liste'])) ?>">Nächste 60 Tage</a>
  </div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="kalender">
  <?php if ($ansicht === 'liste'): ?><input type="hidden" name="ansicht" value="liste"><?php else: ?><input type="hidden" name="monat" value="<?= e($monat) ?>"><?php endif; ?>
  <div class="chips">
    <?php foreach (KALENDER_QUELLEN as $key => $q): if (!in_array($key, $erlaubt, true)) { continue; } ?>
      <label class="chip" style="cursor:pointer;border-left:4px solid <?= e($q['color']) ?>">
        <input type="checkbox" name="q[]" value="<?= e($key) ?>"<?= in_array($key, $quellen, true) ? ' checked' : '' ?> onchange="this.form.submit()"> <?= e($q['label']) ?>
      </label>
    <?php endforeach; ?>
  </div>
</form>

<?php if ($haFehler): ?>
  <div class="alert alert--warn small">Home Assistant: <?= e(implode(' · ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($haFehler), $haFehler))) ?></div>
<?php endif; ?>

<?php if ($ansicht === 'monat'): ?>
  <section class="card">
    <div class="card__head">
      <div class="btnrow">
        <a class="btn btn--sec btn--sm" href="<?= e($link(['monat' => $vor])) ?>" title="voriger Monat">‹</a>
        <h2 style="margin:0"><?= e($monatsnamen[$mon]) ?> <?= (int)$jahr ?></h2>
        <a class="btn btn--sec btn--sm" href="<?= e($link(['monat' => $nach])) ?>" title="nächster Monat">›</a>
      </div>
      <a class="btn btn--sec btn--sm" href="<?= e($link(['monat' => substr($heute, 0, 7)])) ?>">Heute</a>
    </div>
    <div class="kal">
      <?php foreach ($tage as $t): ?><div class="kal__kopf"><?= e($t) ?></div><?php endforeach; ?>
      <?php foreach ($raster['wochen'] as $woche): foreach ($woche as $tag):
          $imMonat = substr($tag, 0, 7) === $monat;
          $liste = $jeTag[$tag] ?? [];
          $feiertag = null;
          foreach ($liste as $e) { if ($e['quelle'] === 'feiertag') { $feiertag = $e; break; } }
          $wochenende = (int)date('N', strtotime($tag)) >= 6;
      ?>
        <div class="kal__tag<?= $imMonat ? '' : ' kal__tag--aussen' ?><?= $tag === $heute ? ' kal__tag--heute' : '' ?><?= $wochenende || $feiertag ? ' kal__tag--frei' : '' ?>">
          <div class="kal__nr">
            <?php if ($darfAnlegen): ?><a href="<?= e(url('kalender_edit', ['datum' => $tag])) ?>" title="Termin an diesem Tag anlegen"><?= (int)substr($tag, 8, 2) ?></a>
            <?php else: ?><?= (int)substr($tag, 8, 2) ?><?php endif; ?>
            <?php if ($feiertag): ?><span class="kal__feiertag" title="<?= e($feiertag['titel']) ?>"><?= e($feiertag['titel']) ?></span><?php endif; ?>
          </div>
          <?php foreach ($liste as $e): if ($e['quelle'] === 'feiertag') { continue; } $zeit = kalender_zeit($e); ?>
            <?php if ($e['url'] !== ''): ?><a class="kal__e" href="<?= e($e['url']) ?>" style="border-left-color:<?= e($e['farbe']) ?>" title="<?= e(($zeit !== '' ? $zeit . ' Uhr · ' : '') . $e['titel'] . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : '') . ($e['ort'] !== '' ? ' · ' . $e['ort'] : '')) ?>">
            <?php else: ?><span class="kal__e" style="border-left-color:<?= e($e['farbe']) ?>" title="<?= e(($zeit !== '' ? $zeit . ' Uhr · ' : '') . $e['titel'] . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : '')) ?>"><?php endif; ?>
              <?php if ($zeit !== ''): ?><span class="kal__zeit"><?= e(substr($zeit, 0, 5)) ?></span><?php endif; ?><?= e($e['titel']) ?>
            <?= $e['url'] !== '' ? '</a>' : '</span>' ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <section class="card">
    <h2>Nächste 60 Tage</h2>
    <?php if (!$eintraege): ?>
      <div class="empty">Nichts eingetragen.</div>
    <?php else: ?>
      <?php $letzterTag = ''; ?>
      <div class="itemlist">
      <?php foreach ($eintraege as $e): $tag = substr($e['beginn'], 0, 10); $zeit = kalender_zeit($e); ?>
        <?php if ($tag !== $letzterTag): $letzterTag = $tag; ?>
          <div class="kal__datum<?= $tag === $heute ? ' kal__datum--heute' : '' ?>"><?= e($tage[(int)date('N', strtotime($tag)) - 1]) ?>, <?= e(de_date($tag)) ?><?= $tag === $heute ? ' · heute' : '' ?></div>
        <?php endif; ?>
        <?php $inner = '<div class="item__top"><div style="min-width:0"><div class="item__title">' . e($e['titel']) . '</div><div class="item__sub">'
            . e(trim(($zeit !== '' ? $zeit . ' Uhr' : 'ganztägig') . ($e['ende'] && substr($e['ende'], 0, 10) !== $tag ? ' bis ' . de_date(substr($e['ende'], 0, 10)) : '')
                . ($e['ort'] !== '' ? ' · ' . $e['ort'] : '') . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : '')))
            . '</div></div><span class="badge" style="background:' . e($e['farbe']) . '">' . e(KALENDER_QUELLEN[$e['quelle']]['label'] ?? $e['quelle']) . '</span></div>'; ?>
        <?php if ($e['url'] !== ''): ?><a class="item" href="<?= e($e['url']) ?>" style="border-left-color:<?= e($e['farbe']) ?>"><?= $inner ?></a>
        <?php else: ?><div class="item" style="border-left-color:<?= e($e['farbe']) ?>"><?= $inner ?></div><?php endif; ?>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<p class="small muted">Eigene Termine gelten für dich, deine Fachgruppe, eine Funktion oder den ganzen Ortsverband.
  Besprechungen, Veranstaltungen, Fristen, fällige Aufgaben und Stichtage kommen aus den Modulen, die Feiertage
  <?= e(KALENDER_BUNDESLAENDER[(string)setting('kalender_bundesland', 'BW')] ?? '') ?> werden gerechnet<?= setting('kalender_ha_entitaeten') ? ', Kalender aus Home Assistant werden regelmäßig geholt' : '' ?>.</p>
