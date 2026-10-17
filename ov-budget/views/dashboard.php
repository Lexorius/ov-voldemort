<?php
/** @var array $user @var int $jahr @var array $wuensche @var array $statsW
 *  @var array $budgets @var array $zahlen @var float $budgetVerplant
 *  @var array $todos @var int $todosGesamt @var int $ueberfaellig @var array $zuBestellen @var array $fahrzeuge */
$warnProzent = setting_int('budget_warn_prozent', 90);
$quote = (float)$zahlen['quote'];
$quoteCls = ($zahlen['verfuegbar'] > 0 && $zahlen['ausgaben'] > $zahlen['verfuegbar']) ? 'is-over'
    : ($quote >= $warnProzent ? 'is-warn' : '');
?>
<?php if (can('admin') && ($wanderungFehler = state_get('wanderung_fehler', '')) !== ''): ?>
  <div class="alert alert--error">
    <strong>Die Datenbank-Wanderung beim letzten Start ist fehlgeschlagen.</strong> Die Anwendung läuft, aber neue Felder oder
    Tabellen können fehlen. Bitte den Fehler melden: <span class="mono small"><?= e($wanderungFehler) ?></span>
  </div>
<?php endif; ?>
<div class="pagehead">
  <div>
    <h1>Moin <?= e(explode(' ', trim((string)($user['display_name'] ?: $user['username'])))[0]) ?>!</h1>
    <p>Haushaltsjahr <?= (int)$jahr ?> · <?= e((string)setting('ov_name', '')) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('wish_edit')) ?>">+ Wunsch</a>
    <?php if (can('create_todo')): ?>
      <a class="btn btn--sec" href="<?= e(url('todo_edit')) ?>">+ Aufgabe</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($zuBestellen): ?>
  <div class="alert alert--info">
    <strong><?= count($zuBestellen) ?> <?= count($zuBestellen) === 1 ? 'Wunsch ist' : 'Wünsche sind' ?> freigegeben – bitte bestellen</strong>
    (<?= e(money(array_sum(array_map(static fn($w) => (float)$w['netto_gesamt'], $zuBestellen)))) ?>).
    <a href="<?= e(url('budget')) ?>#bestellung">Zur Liste</a>
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat">
    <div class="stat__label">Offene Wünsche</div>
    <div class="stat__value"><?= (int)$statsW['anzahl'] ?></div>
    <div class="stat__hint"><?= e(money($statsW['netto_offen'])) ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Davon "nice to have"</div>
    <div class="stat__value"><?= e(money($statsW['nice'], false)) ?></div>
    <div class="stat__hint">verzichtbar</div>
  </div>
  <div class="stat">
    <div class="stat__label"><?= $zahlen['frei'] >= 0 ? 'Budget frei' : 'Budget überzogen' ?> <?= (int)$jahr ?></div>
    <div class="stat__value" style="<?= $zahlen['frei'] < 0 ? 'color:var(--bad)' : '' ?>"><?= e(money(abs($zahlen['frei']), false)) ?></div>
    <div class="stat__hint">von <?= e(money($zahlen['verfuegbar'], false)) ?> verfügbar (<?= number_format($quote, 0) ?>&nbsp;% ausgegeben)<?php
      if (!empty($zahlen['stichtag'])): ?><br><?= !empty($zahlen['gesperrt'])
          ? '<span style="color:var(--bad);font-weight:700">Jahr geschlossen seit ' . e(de_date($zahlen['stichtag'])) . '</span>'
          : '<span' . ((int)$zahlen['stichtag_tage'] <= 30 ? ' style="color:#b45309;font-weight:700"' : '') . '>Stichtag ' . e(de_date($zahlen['stichtag'])) . ', noch ' . (int)$zahlen['stichtag_tage'] . ' Tag' . ((int)$zahlen['stichtag_tage'] === 1 ? '' : 'e') . '</span>' ?><?php endif; ?></div>
  </div>
  <div class="stat">
    <div class="stat__label">Meine Aufgaben</div>
    <div class="stat__value"><?= (int)$todosGesamt ?></div>
    <div class="stat__hint">
      <?php if ($ueberfaellig > 0): ?>
        <span style="color:var(--bad);font-weight:700"><?= (int)$ueberfaellig ?> überfällig</span>
      <?php else: ?>alles im Zeitplan<?php endif; ?>
    </div>
  </div>
</div>

<?php $zweiWochen ??= null; $darfTermin ??= false; $heute = date('Y-m-d'); $tageKurz = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']; if ($zweiWochen): ?>
  <section class="card" id="zwei-wochen">
    <div class="card__head">
      <h2>Nächste zwei Wochen</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('kalender')) ?>">Kalender</a>
    </div>
    <div class="kal kal--kompakt">
      <?php for ($i = 0; $i < 14; $i++): $tag = date('Y-m-d', strtotime($zweiWochen['von']) + $i * 86400); $liste = $zweiWochen['jeTag'][$tag] ?? [];
        $feiertag = null; foreach ($liste as $e) { if ($e['quelle'] === 'feiertag') { $feiertag = $e; break; } }
        $wochenende = (int)date('N', strtotime($tag)) >= 6; ?>
        <div class="kal__tag<?= $tag === $heute ? ' kal__tag--heute' : '' ?><?= $wochenende || $feiertag ? ' kal__tag--frei' : '' ?>">
          <div class="kal__nr">
            <?php if ($darfTermin): ?><a href="<?= e(url('kalender_edit', ['datum' => $tag])) ?>" title="Termin an diesem Tag anlegen"><?= e($tageKurz[(int)date('N', strtotime($tag)) - 1]) ?> <?= (int)substr($tag, 8, 2) ?>.<?= (int)substr($tag, 5, 2) ?>.</a>
            <?php else: ?><?= e($tageKurz[(int)date('N', strtotime($tag)) - 1]) ?> <?= (int)substr($tag, 8, 2) ?>.<?= (int)substr($tag, 5, 2) ?>.<?php endif; ?>
            <?php if ($feiertag): ?><span class="kal__feiertag" title="<?= e($feiertag['titel']) ?>"><?= e($feiertag['titel']) ?></span><?php endif; ?>
          </div>
          <?php foreach ($liste as $e): if ($e['quelle'] === 'feiertag') { continue; } $zeit = kalender_zeit($e);
            $tip = ($zeit !== '' ? $zeit . ' Uhr · ' : '') . $e['titel'] . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : '') . ($e['ort'] !== '' ? ' · ' . $e['ort'] : '') . (($e['fahrzeuge'] ?? '') !== '' ? ' · ⛟ ' . $e['fahrzeuge'] : ''); ?>
            <?php if ($e['url'] !== ''): ?><a class="kal__e" href="<?= e($e['url']) ?>" style="border-left-color:<?= e($e['farbe']) ?>" title="<?= e($tip) ?>"><?php else: ?><span class="kal__e" style="border-left-color:<?= e($e['farbe']) ?>" title="<?= e($tip) ?>"><?php endif; ?>
              <?php if ($zeit !== ''): ?><span class="kal__zeit"><?= e(substr($zeit, 0, 5)) ?></span><?php endif; ?><?= e($e['titel']) ?><?= ($e['fahrzeuge'] ?? '') !== '' ? ' <span class="muted">⛟</span>' : '' ?>
            <?= $e['url'] !== '' ? '</a>' : '</span>' ?>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </section>
<?php endif; ?>

<?php $fahrzeugeWoche ??= []; $woche ??= ['', '']; $fzBilder ??= []; if ($fahrzeugeWoche): $konflikte = array_sum(array_map(static fn($g) => (int)($g['konflikte'] ?? 0), $fahrzeugeWoche)); ?>
  <section class="card" id="fahrzeuge-woche">
    <div class="card__head">
      <h2>⛟ Fahrzeuge jetzt und diese Woche verplant</h2>
      <span class="muted small"><?= e(de_date($woche[0])) ?> – <?= e(de_date($woche[1])) ?> · <?= count($fahrzeugeWoche) ?> Fahrzeug(e)</span>
    </div>
    <?php if ($konflikte > 0): ?>
      <div class="alert alert--warn"><strong>Doppelbuchung:</strong> <?= count(array_filter($fahrzeugeWoche, static fn($g) => (int)($g['konflikte'] ?? 0) > 0)) === 1 ? 'Ein Fahrzeug ist' : 'Mehrere Fahrzeuge sind' ?>
        zur selben Zeit in zwei Terminen eingeplant – rot markiert. Bitte einen der Termine ändern.</div>
    <?php endif; ?>
    <div class="fzwoche">
      <?php foreach ($fahrzeugeWoche as $g): $v = $g['fahrzeug']; $hatKonflikt = (int)($g['konflikte'] ?? 0) > 0; ?>
        <div class="fzwoche__karte<?= $hatKonflikt ? ' fzwoche__karte--konflikt' : '' ?>">
          <a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>" class="fzwoche__kopf">
            <?php if (isset($fzBilder[(int)$v['id']])): ?>
              <img class="fzwoche__bild" alt="" loading="lazy" src="<?= e(url('vehicle_file', ['id' => $fzBilder[(int)$v['id']]['id'], 'vorschau' => 1])) ?>">
            <?php else: ?><span class="fzwoche__bild fzwoche__leer">⛟</span><?php endif; ?>
            <strong><?= e((string)$v['bezeichnung']) ?></strong>
            <span class="muted small"><?= e(implode(' · ', array_filter([$v['funkrufname'], $v['kennzeichen']]))) ?></span>
            <?php if ($hatKonflikt): ?><span class="badge" style="background:#b91c1c">Doppelbuchung</span><?php endif; ?>
          </a>
          <div class="chips" style="margin-top:.4rem">
            <?php foreach ($g['termine'] as $t): $tag = substr($t['beginn'], 0, 10); $zeit = kalender_zeit($t); ?>
              <a class="chip<?= !empty($t['konflikt']) ? ' chip--konflikt' : '' ?>" href="<?= e(!empty($t['url']) ? (string)$t['url'] : url('kalender_termin', ['id' => $t['id']])) ?>"<?= !empty($t['konflikt']) ? ' title="Überschneidet sich mit einem anderen Termin dieses Fahrzeugs"' : '' ?>><strong><?= e($tageKurz[(int)date('N', strtotime($tag)) - 1] . ' ' . date('d.m.', strtotime($tag))) ?></strong>
                <?= $zeit !== '' ? e($zeit) : 'ganztägig' ?> · <?= e($t['titel']) ?><?= $t['ort'] !== '' ? ' <span class="muted small">' . e($t['ort']) . '</span>' : '' ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div class="grid2">
  <section class="card">
    <div class="card__head">
      <h2>Oben auf der Wunschliste</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('wishes')) ?>">Alle</a>
    </div>
    <?php if (!$wuensche): ?>
      <div class="empty">Noch keine offenen Wünsche.<br>
        <a href="<?= e(url('wish_edit')) ?>">Jetzt den ersten eintragen</a>
      </div>
    <?php else: ?>
      <div class="itemlist">
        <?php foreach ($wuensche as $w): ?>
          <?= render_partial('partials/wish_item', ['w' => $w]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Nächste Termine</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('kalender')) ?>">Kalender</a>
    </div>
    <?php if (empty($termine)): ?>
      <div class="empty">In den nächsten 14 Tagen steht nichts an.</div>
    <?php else: ?>
      <div class="itemlist">
        <?php foreach ($termine as $e): $tag = substr($e['beginn'], 0, 10); $zeit = kalender_zeit($e);
          $inner = '<div class="item__top"><div style="min-width:0"><div class="item__title">' . e($e['titel']) . '</div><div class="item__sub">'
              . e(de_date($tag) . ($zeit !== '' ? ', ' . $zeit . ' Uhr' : '') . ($e['ort'] !== '' ? ' · ' . $e['ort'] : '') . ($e['untertitel'] !== '' ? ' · ' . $e['untertitel'] : '')
                  . (($e['fahrzeuge'] ?? '') !== '' ? ' · ⛟ ' . $e['fahrzeuge'] : ''))
              . '</div></div><span class="badge" style="background:' . e($e['farbe']) . '">' . e(KALENDER_QUELLEN[$e['quelle']]['label'] ?? $e['quelle']) . '</span></div>'; ?>
          <?php if ($e['url'] !== ''): ?><a class="item" href="<?= e($e['url']) ?>" style="border-left-color:<?= e($e['farbe']) ?>"><?= $inner ?></a>
          <?php else: ?><div class="item" style="border-left-color:<?= e($e['farbe']) ?>"><?= $inner ?></div><?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php if (($termineGesamt ?? 0) > count($termine)): ?><p class="small muted" style="margin:.5rem 0 0"><?= (int)$termineGesamt ?> Termine in 14 Tagen – <a href="<?= e(url('kalender', ['ansicht' => 'liste'])) ?>">alle</a></p><?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Meine Aufgaben</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('todos')) ?>">Alle</a>
    </div>
    <?php if (!$todos): ?>
      <div class="empty">Keine offenen Aufgaben. 🎉</div>
    <?php else: ?>
      <div class="itemlist">
        <?php foreach ($todos as $t): ?>
          <?= render_partial('partials/todo_item', ['t' => $t]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if ($fahrzeuge): ?>
  <section class="card">
    <div class="card__head">
      <h2>Fahrzeuge im Blick</h2>
      <a class="btn btn--sec btn--sm" href="<?= e(url('vehicles')) ?>">Alle</a>
    </div>
    <div class="itemlist">
      <?php foreach ($fahrzeuge as $w): $v = $w['fahrzeug']; ?>
        <a class="item" href="<?= e(url('vehicle', ['id' => $v['id']])) ?>"
           style="border-left-color:<?= $w['ausfall'] > 0 ? '#b91c1c' : '#a16207' ?>">
          <div class="item__top"><div style="min-width:0">
            <div class="item__title"><?= e($v['bezeichnung']) ?></div>
            <div class="item__sub"><?= e(implode(' · ', array_filter([$v['funkrufname'], $v['kennzeichen']]))) ?></div>
          </div></div>
          <div class="item__meta">
            <?php if ($w['ausfall'] > 0): ?>
              <span class="badge" style="background:#b91c1c">steht still</span>
            <?php endif; ?>
            <?php foreach ($w['fristen'] as $f): ?>
              <span class="badge" style="background:<?= $f['status'] === 'abgelaufen' ? '#b91c1c' : '#a16207' ?>">
                <?= e($f['label']) ?> <?= $f['status'] === 'abgelaufen' ? 'abgelaufen' : 'bis ' . e(de_date($f['datum'])) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Budget <?= (int)$jahr ?></h2>
    <a class="btn btn--sec btn--sm" href="<?= e(url('budget')) ?>">Budget</a>
  </div>
  <?php if ($zahlen['verfuegbar'] <= 0): ?>
    <div class="empty">Für <?= (int)$jahr ?> ist noch kein Jahresbudget hinterlegt.
      <?php if (can('manage_budget')): ?>
        <br><a href="<?= e(url('budget_year_edit', ['jahr' => $jahr])) ?>">Jahresbudget eintragen</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
      <span class="small">
        <?= e(money($zahlen['budget'], false)) ?> Budget
        <?php if ($zahlen['einnahmen'] > 0): ?>+ <?= e(money($zahlen['einnahmen'], false)) ?> eingegangen<?php endif; ?>
        = <strong><?= e(money($zahlen['verfuegbar'])) ?></strong><?= ($zahlen['einnahmen_zugesagt'] ?? 0) > 0 ? ' <span class="muted">· mit Zusagen ' . e(money($zahlen['verfuegbar_mit_zusagen'] ?? 0)) . '</span>' : '' ?>
      </span>
      <span class="small nowrap"><?= e(money($zahlen['ausgaben'], false)) ?> gebucht ·
        <strong><?= e(money($zahlen['frei'])) ?></strong> <?= $zahlen['frei'] >= 0 ? 'frei' : 'überzogen' ?></span>
    </div>
    <div class="bar" style="height:14px"><div class="bar__fill <?= $quoteCls ?>" style="width:<?= number_format($quote, 1, '.', '') ?>%"></div></div>
    <?php if ($budgetVerplant > 0 || ($zahlen['geplant'] ?? 0) > 0 || ($zahlen['einnahmen_offen'] ?? 0) > 0): ?>
      <p class="small muted" style="margin:.5rem 0 0">
        <?php if ($budgetVerplant > 0): ?>Dazu <?= e(money($budgetVerplant)) ?> in offenen Wünschen verplant.<?php endif; ?>
        <?php if (($zahlen['geplant'] ?? 0) > 0): ?><?= e(money($zahlen['geplant'])) ?> geplant<?= ($zahlen['geplant_veranstaltungen'] ?? 0) > 0 ? ' (Veranstaltungen, Verpflegung)' : '' ?>.<?php endif; ?>
        <?php if (($zahlen['einnahmen_offen'] ?? 0) > 0): ?><?= e(money($zahlen['einnahmen_offen'])) ?> abgerechnet oder in Rechnung gestellt, noch nicht zugesagt.<?php endif; ?>
        <?php $w = $zahlen['abrechnungen_wartend'] ?? null; if ($w && $w['ueber30'] > 0): ?>
          <a href="<?= e(url('expenses', ['art' => 'einnahme'])) ?>#einnahmen-stand" style="color:<?= EINNAHME_WARN_FARBEN[$w['rot'] > 0 ? 'rot' : ($w['orange'] > 0 ? 'orange' : 'gelb')] ?>;font-weight:700"><?= (int)$w['ueber30'] ?> Abrechnung<?= $w['ueber30'] === 1 ? '' : 'en' ?> wartet über 30 Tage auf Geld</a> (<?= e(einnahmen_wartend_text($w)) ?>).
        <?php endif; ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (count($budgets) > 1): ?>
    <h3 class="mt">Budgettöpfe</h3>
    <?php foreach ($budgets as $b):
        $soll = (float)$b['betrag_netto'];
        $ist = (float)$b['verplant'];
        $pct = $soll > 0 ? min(100, ($ist / $soll) * 100) : 0;
        $cls = $soll > 0 && $ist > $soll ? 'is-over' : ($pct >= $warnProzent ? 'is-warn' : '');
    ?>
      <div style="margin-bottom:.9rem">
        <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
          <strong><?= e($b['name']) ?></strong>
          <span class="small"><?= e(money($ist, false)) ?> / <?= e(money($soll)) ?></span>
        </div>
        <div class="bar"><div class="bar__fill <?= $cls ?>" style="width:<?= number_format($pct, 1, '.', '') ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
