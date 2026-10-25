<?php
/**
 * @var array  $umfrage   @var array  $seiten    @var ?array $connector @var string $zustand
 * @var string $adresse   @var array  $antworten @var array  $auswertung @var bool $darf
 * @var int    $letzterAbruf @var bool $abholbar
 */
$id = (int)$umfrage['id'];
$qrZeigen = $adresse !== '' && $connector !== null && connector_gekoppelt($connector)
    && in_array($zustand, ['wartet', 'laeuft', 'abgelaufen'], true);
$aktion = static function (string $action, string $text, string $klasse = 'btn btn--sec btn--sm', string $frage = '', string $frage2 = '') use ($id): string {
    $bestaetigen = $frage !== '' ? ' data-confirm="' . e($frage) . '"' . ($frage2 !== '' ? ' data-confirm2="' . e($frage2) . '"' : '') : '';
    return '<form method="post" action="' . e(url('umfrage_action')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="' . e($klasse) . '" type="submit"' . $bestaetigen . '>' . e($text) . '</button></form>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$umfrage['titel']) ?> <?= umfrage_zustand_badge($zustand) ?></h1>
    <p><?= trim((string)($umfrage['beginn'] ?? '')) !== '' ? 'Von ' . e(de_datetime((string)$umfrage['beginn'])) . ' bis ' : 'Bis ' ?><?= e(de_datetime((string)$umfrage['ende'])) ?>
      · <?= umfrage_fragen_anzahl($seiten) ?> Frage(n) auf <?= count($seiten) ?> Seite(n)
      <?php if ($connector !== null): ?> · Connector <strong><?= e((string)$connector['name']) ?></strong><?php else: ?> · <span style="color:var(--warn)">kein Connector</span><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('umfragen')) ?>">Zur Liste</a>
    <?php if ($darf): ?>
      <a class="btn btn--sec" href="<?= e(url('umfrage_edit', ['id' => $id])) ?>">Bearbeiten</a>
      <?php if ($zustand === 'entwurf' || $zustand === 'geschlossen'): ?>
        <?= $aktion('freigeben', 'Freigeben', 'btn') ?>
      <?php elseif ($zustand !== 'geschlossen'): ?>
        <?= $aktion('schliessen', 'Schließen', 'btn btn--sec', 'Abstimmung schließen? Der Connector nimmt dann nichts mehr an; was dort liegt, wird vorher geholt.') ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string)$umfrage['beschreibung']) !== ''): ?>
  <p style="white-space:pre-wrap"><?= e((string)$umfrage['beschreibung']) ?></p>
<?php endif; ?>

<section class="card" id="zugang">
  <div class="card__head">
    <h2>Zugang: QR-Code und Link</h2>
    <?php if ($darf): ?>
      <div class="btnrow">
        <?= $aktion('code_neu', 'Code neu', 'btn btn--sec btn--sm', 'Neuen Code erzeugen? Der bisherige QR-Code und Link gehen dann ins Leere.', 'Bist du wirklich sicher?') ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($connector === null): ?>
    <p class="small">Ohne Connector gibt es keine Adresse. Beim <a href="<?= e(url('umfrage_edit', ['id' => $id])) ?>">Bearbeiten</a> einen
      Connector mit der Verwendung „Abstimmungen" wählen<?= $darf ? '' : ' (Leitung)' ?>.</p>
  <?php elseif ($zustand === 'entwurf'): ?>
    <p class="small"><strong>Noch nicht im Netz.</strong> Die Abstimmung ist ein Entwurf; der Connector kennt sie erst nach dem
      <strong>Freigeben</strong> oben rechts. Dann erscheinen hier QR-Code und Link. Die Adresse wird lauten
      (funktioniert jetzt noch nicht):</p>
    <p class="small muted mono" style="word-break:break-all;opacity:.6"><?= e($adresse) ?></p>
  <?php elseif ($zustand === 'geschlossen'): ?>
    <p class="small">Geschlossen – beim Connector abgemeldet, die Adresse geht ins Leere.</p>
  <?php elseif ($qrZeigen): ?>
    <div class="qr-block" data-qr="<?= e($adresse) ?>" data-qr-titel="<?= e((string)$umfrage['titel']) ?>"
         data-qr-untertitel="Abstimmung" data-qr-hinweis="Mit der Kamera scannen und abstimmen. Die Antwort wird im Browser verschlüsselt. Offen bis <?= e(de_datetime((string)$umfrage['ende'])) ?>.">
      <div class="qr-bild" id="qr-bild"></div>
      <div>
        <p class="small">Zum <strong>Scannen</strong> auf die Leinwand oder ans schwarze Brett; zum <strong>Weitergeben</strong> den Link kopieren
          oder den QR-Code als Bild herunterladen und in eine Nachricht legen.</p>
        <p class="small mono" style="word-break:break-all" id="umfrage-link"><?= e($adresse) ?></p>
        <?php if ($zustand === 'abgelaufen'): ?>
          <p class="small" style="color:var(--warn)">Das Zeitfenster ist vorbei – die Seite sagt das und nimmt nichts mehr an.</p>
        <?php endif; ?>
        <div class="btnrow">
          <button class="btn btn--sec btn--sm" type="button" data-kopieren="#umfrage-link">Link kopieren</button>
          <button class="btn btn--sec btn--sm" type="button" id="qr-drucken">Drucken</button>
          <button class="btn btn--sec btn--sm" type="button" id="qr-laden">QR als Bild</button>
        </div>
        <p class="small muted">Code <span class="mono"><?= e((string)$umfrage['code']) ?></span> – lässt sich auch auf der Startseite des Connectors eintippen.</p>
      </div>
    </div>
  <?php else: ?>
    <p class="small">Der Connector <strong><?= e((string)$connector['name']) ?></strong> ist nicht gekoppelt – bitte in der
      <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a> nachsehen.</p>
  <?php endif; ?>
</section>

<section class="card" id="bericht">
  <div class="card__head">
    <h2>Ergebnis per E-Mail</h2>
    <?php if ($darf && $empfaenger && $mailAktiv): ?>
      <?= $aktion('bericht', in_array($zustand, ['abgelaufen', 'geschlossen'], true) ? 'Ergebnis jetzt senden' : 'Zwischenstand jetzt senden') ?>
    <?php endif; ?>
  </div>
  <?php if ((string)($umfrage['bericht_rhythmus'] ?? '') === '' || !$empfaenger): ?>
    <p class="small muted">Kein automatischer Bericht. Beim <a href="<?= e(url('umfrage_edit', ['id' => $id])) ?>">Bearbeiten</a> lassen sich
      Kontakte eintragen, die täglich, wöchentlich oder am Ende die Ergebnisse bekommen.</p>
  <?php else: ?>
    <p class="small"><strong><?= e(umfrage_bericht_text_rhythmus((string)$umfrage['bericht_rhythmus'])) ?></strong>
      an <strong><?= count($empfaenger) ?> Empfänger</strong>: <?= e(implode(', ', array_map(static fn($k) => contact_name($k) . ' (' . $k['email'] . ')', $empfaenger))) ?><?= (int)($umfrage['bericht_ki'] ?? 0) === 1 ? ' – mit KI-Zusammenfassung' : '' ?>.
      <?php if (!empty($umfrage['bericht_zuletzt'])): ?>Zuletzt verschickt <?= e(de_datetime((string)$umfrage['bericht_zuletzt'])) ?>.<?php else: ?>Noch nichts verschickt.<?php endif; ?>
      <?php if (!empty($umfrage['bericht_ende_am'])): ?>Ergebnis verschickt <?= e(de_datetime((string)$umfrage['bericht_ende_am'])) ?>.<?php endif; ?></p>
    <?php if (!$mailAktiv): ?>
      <p class="small" style="color:var(--warn)">Der E-Mail-Versand ist nicht eingerichtet – der Bericht bleibt liegen
        (<a href="<?= e(url('admin_mail')) ?>">Verwaltung → E-Mail</a>).</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card" id="fragen">
  <div class="card__head"><h2>Fragen</h2><span class="small muted">So sieht es der Connector – Antworten sieht er nicht.</span></div>
  <?php foreach ($seiten as $nr => $s): ?>
    <div class="umfrage-seite-vorschau">
      <div class="small muted">Seite <?= $nr + 1 ?><?= trim((string)$s['titel']) !== '' ? ' · <strong>' . e((string)$s['titel']) . '</strong>' : '' ?></div>
      <?php if (trim((string)$s['text']) !== ''): ?><div class="small" style="white-space:pre-wrap"><?= e((string)$s['text']) ?></div><?php endif; ?>
      <ol class="umfrage-fragen">
        <?php foreach ($s['felder'] as $f): ?>
          <li><?php if ($f['typ'] === 'hinweis'): ?><span class="muted">Hinweis:</span> <?= e((string)$f['frage']) ?>
            <?php else: ?><?= e((string)$f['frage']) ?><?= !empty($f['pflicht']) ? ' <span style="color:var(--bad)">*</span>' : '' ?>
              <span class="badge badge--outline"><?= e(UMFRAGE_FELDTYPEN[$f['typ']] ?? $f['typ']) ?></span>
              <?php if (isset($f['optionen'])): ?><div class="small muted"><?= e(implode(' · ', $f['optionen'])) ?></div><?php endif; ?>
              <?php if ($f['typ'] === 'skala'): ?><div class="small muted"><?= (int)$f['min'] ?> bis <?= (int)$f['max'] ?><?= $f['links'] !== '' || $f['rechts'] !== '' ? ' (' . e((string)$f['links']) . ' … ' . e((string)$f['rechts']) . ')' : '' ?></div><?php endif; ?>
            <?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endforeach; ?>
  <?php if ((int)$umfrage['name_feld'] === 1): ?><p class="small muted">Am Ende wird freiwillig nach dem Namen gefragt.</p><?php endif; ?>
</section>

<section class="card" id="ergebnisse">
  <div class="card__head">
    <h2>Ergebnisse <span class="badge badge--muted"><?= (int)$auswertung['anzahl'] ?> Antwort(en)</span></h2>
    <div class="btnrow">
      <?php if ($darf && $abholbar): ?><?= $aktion('abholen', 'Antworten abholen') ?><?php endif; ?>
      <?php if ($antworten): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('umfrage_export', ['id' => $id])) ?>">CSV</a>
        <?php if ($darf): ?>
          <?= $aktion('antworten_loeschen', 'Antworten löschen', 'btn btn--sec btn--sm', 'Alle Antworten dieser Abstimmung löschen? Das lässt sich nicht rückgängig machen.', 'Bist du wirklich sicher?') ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($letzterAbruf > 0): ?>
    <p class="small muted">Zuletzt abgeholt: <?= e(de_datetime(date('Y-m-d H:i:s', $letzterAbruf))) ?>. Der automatische Abruf holt im
      Takt aus den Einstellungen; beim Connector bleibt nach dem Abholen nichts liegen.</p>
  <?php endif; ?>
  <?php if (!$antworten): ?>
    <div class="empty">Noch keine Antworten<?= $zustand === 'laeuft' ? ' – oder noch nicht abgeholt' : '' ?>.</div>
  <?php else: ?>
    <?php foreach ($auswertung['felder'] as $fid => $f): ?>
      <div class="umfrage-ergebnis">
        <div class="umfrage-ergebnis__frage"><?= e($f['frage']) ?> <span class="small muted"><?= (int)$f['beantwortet'] ?> von <?= (int)$auswertung['anzahl'] ?></span></div>
        <?php if ($f['typ'] === 'einfach' || $f['typ'] === 'mehrfach'): ?>
          <?php $basis = $f['typ'] === 'einfach' ? (int)$f['beantwortet'] : (int)$auswertung['anzahl']; ?>
          <?php foreach ($f['optionen'] as $i => $o): $n = (int)$f['zaehler'][$i]; $p = umfrage_prozent($n, $basis); ?>
            <div class="balken-zeile"><span class="balken-zeile__text"><?= e((string)$o) ?></span>
              <span class="balken"><span style="width:<?= $p ?>%"></span></span>
              <span class="balken-zeile__zahl"><?= $n ?> · <?= $p ?> %</span></div>
          <?php endforeach; ?>
        <?php elseif ($f['typ'] === 'janein'): ?>
          <?php foreach (['ja' => 'Ja', 'nein' => 'Nein'] as $k => $l): $n = (int)$f[$k]; $p = umfrage_prozent($n, (int)$f['beantwortet']); ?>
            <div class="balken-zeile"><span class="balken-zeile__text"><?= $l ?></span>
              <span class="balken"><span style="width:<?= $p ?>%"></span></span>
              <span class="balken-zeile__zahl"><?= $n ?> · <?= $p ?> %</span></div>
          <?php endforeach; ?>
        <?php elseif ($f['typ'] === 'skala'): ?>
          <div class="small"><strong>Ø <?= $f['schnitt'] !== null ? e(number_format((float)$f['schnitt'], 1, ',', '.')) : '–' ?></strong>
            <?= $f['links'] !== '' || $f['rechts'] !== '' ? '<span class="muted">(' . (int)$f['min'] . ' = ' . e($f['links']) . ' … ' . (int)$f['max'] . ' = ' . e($f['rechts']) . ')</span>' : '' ?></div>
          <?php foreach ($f['verteilung'] as $wert => $n): $p = umfrage_prozent((int)$n, (int)$f['beantwortet']); ?>
            <div class="balken-zeile"><span class="balken-zeile__text"><?= (int)$wert ?></span>
              <span class="balken"><span style="width:<?= $p ?>%"></span></span>
              <span class="balken-zeile__zahl"><?= (int)$n ?> · <?= $p ?> %</span></div>
          <?php endforeach; ?>
        <?php elseif ($f['typ'] === 'text'): ?>
          <?php if (!$f['texte']): ?><div class="small muted">Keine Eingaben.</div><?php else: ?>
            <ul class="umfrage-texte"><?php foreach ($f['texte'] as $t): ?><li><?= nl2br(e($t)) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ((int)$umfrage['name_feld'] === 1 && array_filter(array_column($antworten, 'name'))): ?>
      <div class="umfrage-ergebnis">
        <div class="umfrage-ergebnis__frage">Namen <span class="small muted">(freiwillig angegeben)</span></div>
        <div class="small"><?= e(implode(', ', array_filter(array_column($antworten, 'name')))) ?></div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($darf): ?>
  <section class="card">
    <div class="card__head"><h2>Aufräumen</h2></div>
    <div class="btnrow">
      <?php if ($zustand !== 'entwurf'): ?>
        <?= $aktion('entwurf', 'Zurück in den Entwurf', 'btn btn--sec btn--sm', 'Zurück in den Entwurf? Beim Connector wird die Abstimmung abgemeldet.') ?>
      <?php endif; ?>
      <?= $aktion('loeschen', 'Abstimmung löschen', 'btn btn--sec btn--sm', 'Abstimmung samt allen Antworten löschen?', 'Bist du wirklich sicher?') ?>
    </div>
  </section>
<?php endif; ?>
