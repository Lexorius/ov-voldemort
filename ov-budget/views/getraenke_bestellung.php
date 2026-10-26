<?php
/**
 * @var array $b @var array $positionen @var ?array $lieferant @var ?array $standort @var float $summe @var string $vorschau @var string $betreff @var bool $bearbeitet
 * @var array $plaetze @var array $lagerplaetze @var string $kategorien @var bool $darf @var bool $darfBudget @var bool $mailAktiv @var ?array $expense
 */
$id = (int)$b['id'];
$status = (string)$b['status'];
?>
<div class="pagehead">
  <div>
    <h1>Bestellung <?= e((string)$b['nummer']) ?> <?= getraenke_status_badge($status) ?></h1>
    <p>Lieferant <strong><?= e((string)($b['lieferant_name'] ?? '–')) ?></strong>
      <?= $lieferant !== null && trim((string)$lieferant['email']) !== '' ? '(' . e((string)$lieferant['email']) . ')' : '<span style="color:var(--warn)">(ohne E-Mail-Adresse)</span>' ?>
      <?= $lieferant !== null && getraenke_lieferant_anschrift($lieferant) !== '' ? '· ' . e(getraenke_lieferant_anschrift($lieferant)) : '' ?>
      <?php if (trim((string)($b['absender_name'] ?? '')) !== ''): ?>· im Namen von <strong><?= e((string)$b['absender_name']) ?></strong><?php endif; ?>
      · angelegt <?= e(de_datetime((string)$b['created_at'])) ?>
      <?php if (!empty($b['gesendet_am'])): ?> · verschickt <?= e(de_datetime((string)$b['gesendet_am'])) ?> an <?= e((string)$b['gesendet_an']) ?><?php endif; ?>
      <?php if (!empty($b['geliefert_am'])): ?> · geliefert <?= e(de_datetime((string)$b['geliefert_am'])) ?><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#bestellungen">Zur Übersicht</a>
    <?php if ($darf && $status === 'entwurf'): ?>
      <a class="btn btn--sec" href="<?= e(url('getraenke_bestellung_edit', ['id' => $id])) ?>">Positionen bearbeiten</a>
      <a class="btn" href="#mail">E-Mail prüfen und bestellen</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($darf && $status === 'entwurf' && !$mailAktiv): ?>
  <div class="alert alert--warn">Der E-Mail-Versand ist nicht eingerichtet – ohne ihn lässt sich die Bestellung nicht verschicken
    (<a href="<?= e(url('admin_mail')) ?>">Verwaltung → E-Mail</a>).</div>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2>Positionen</h2><span class="small muted"><?= count($positionen) ?> Getränk(e)<?= $summe > 0 ? ' · ' . e(money($summe)) . ' nach Listenpreis' : '' ?></span></div>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Getränk</th><th class="num">Bestellt</th><?php if ($status !== 'entwurf' && $status !== 'bestellt'): ?><th class="num">Geliefert</th><?php endif; ?><th class="num">Preis</th><th class="num">Summe</th></tr></thead>
      <tbody>
      <?php foreach ($positionen as $p): ?>
        <tr>
          <td><?= e((string)$p['getraenk_name']) ?> <span class="small muted"><?= e(getraenk_gebinde_text($p)) ?></span></td>
          <td class="num"><?= (int)$p['anzahl'] ?></td>
          <?php if ($status !== 'entwurf' && $status !== 'bestellt'): ?><td class="num"><?= $p['geliefert'] !== null ? (int)$p['geliefert'] : '–' ?></td><?php endif; ?>
          <td class="num"><?= (float)$p['preis'] > 0 ? e(money((float)$p['preis'])) : '–' ?></td>
          <td class="num"><?= (float)$p['preis'] > 0 ? e(money((int)$p['anzahl'] * (float)$p['preis'])) : '–' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (trim((string)($b['notiz'] ?? '')) !== ''): ?><p class="small" style="white-space:pre-wrap"><?= e((string)$b['notiz']) ?></p><?php endif; ?>
</section>

<section class="card" id="mail">
  <div class="card__head"><h2><?= $status === 'entwurf' ? 'E-Mail prüfen und bestellen' : 'Verschickte E-Mail' ?></h2>
    <?php if ($status === 'entwurf' && $bearbeitet): ?><span class="badge" style="background:#a16207">von Hand bearbeitet</span>
    <?php elseif ($standort !== null): ?><span class="small muted">Abladen: <?= e((string)$standort['name']) ?></span><?php endif; ?></div>
  <?php if ($status === 'entwurf' && $darf): ?>
    <form method="post" action="<?= e(url('getraenke_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="field">
        <label for="mail_betreff">Betreff</label>
        <input type="text" id="mail_betreff" name="mail_betreff" maxlength="200" value="<?= e($betreff) ?>">
      </div>
      <div class="field">
        <label for="mail_text">Text <span class="muted">(so geht er hinaus – Anrede, Positionen, Anschrift, Gruß)</span></label>
        <textarea id="mail_text" name="mail_text" rows="<?= max(12, min(30, substr_count($vorschau, "\n") + 3)) ?>" class="mono"><?= e($vorschau) ?></textarea>
      </div>
      <div class="btnrow">
        <button class="btn" type="submit" name="action" value="senden" <?= $mailAktiv && $lieferant !== null && trim((string)$lieferant['email']) !== '' ? '' : 'disabled' ?>
                data-confirm="Bestellung jetzt so per E-Mail an den Lieferanten schicken?">Geprüft – per E-Mail bestellen</button>
        <button class="btn btn--sec" type="submit" name="action" value="mail_speichern">Text speichern</button>
        <button class="btn btn--sec" type="submit" name="action" value="mail_neu" data-confirm="Betreff und Text verwerfen und aus der Vorlage neu erzeugen?">Vorlage neu erzeugen</button>
      </div>
      <p class="small muted">Die Vorlage nimmt Positionen, Lieferadresse und Hinweistext aus den Einstellungen und den Absender. Wer die
        Positionen ändert, bekommt den Text neu erzeugt<?= $lieferant !== null && trim((string)$lieferant['email']) !== '' ? '; verschickt wird an ' . e((string)$lieferant['email']) : '' ?>.</p>
    </form>
  <?php else: ?>
    <?php if ($betreff !== ''): ?><p class="small"><strong>Betreff:</strong> <?= e($betreff) ?></p><?php endif; ?>
    <pre class="getraenke-mail"><?= e($vorschau) ?></pre>
  <?php endif; ?>
</section>

<?php if ($darf && in_array($status, ['bestellt', 'entwurf'], true)): ?>
  <section class="card" id="liefern">
    <div class="card__head"><h2>Lieferung eintragen</h2><span class="small muted">bucht einen Zugang ins Lager</span></div>
    <form method="post" action="<?= e(url('getraenke_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="liefern"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="grid2">
        <div class="field">
          <label for="standort_id">Eingelagert an</label>
          <select id="standort_id" name="standort_id" required>
            <option value="">– Platz wählen –</option>
            <?php foreach ($plaetze as $p): ?>
              <option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === (int)($b['standort_id'] ?? 0) ? ' selected' : '' ?>><?= e((string)$p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Getränk</th><th class="num">Bestellt</th><th class="num">Geliefert</th></tr></thead>
          <tbody>
          <?php foreach ($positionen as $p): ?>
            <tr><td><?= e((string)$p['getraenk_name']) ?></td><td class="num"><?= (int)$p['anzahl'] ?></td>
              <td class="num"><input type="number" name="geliefert[<?= (int)$p['getraenk_id'] ?>]" min="0" max="9999" value="<?= (int)$p['anzahl'] ?>" style="width:6rem"></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Als geliefert buchen</button></div>
    </form>
  </section>
<?php endif; ?>

<?php if ($status === 'abgerechnet'): ?>
  <section class="card" id="abrechnung">
    <div class="card__head"><h2>Abrechnung</h2></div>
    <p>Rechnungsbetrag <strong><?= e(money((float)($b['rechnungsbetrag'] ?? 0))) ?></strong>
      <?php if ($expense !== null): ?> – als Ausgabe <a href="<?= e(url('expense_edit', ['id' => $expense['id']])) ?>"><?= e((string)$expense['bezeichnung']) ?></a>
        im Budget <?= (int)$expense['jahr'] ?> (<?= e(buchung_status((string)$expense['status'])) ?>).<?php else: ?> im Budget verbucht.<?php endif; ?></p>
  </section>
<?php elseif ($darf && $darfBudget && in_array($status, ['geliefert', 'bestellt'], true)): ?>
  <section class="card" id="abrechnung">
    <div class="card__head"><h2>Rechnung abrechnen</h2><span class="small muted">legt eine Ausgabe im Budget an</span></div>
    <form method="post" action="<?= e(url('getraenke_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="abrechnen"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="grid2">
        <div class="field">
          <label for="betrag">Rechnungsbetrag</label>
          <input type="text" inputmode="decimal" id="betrag" name="betrag" required value="<?= $summe > 0 ? e(number_format($summe, 2, ',', '')) : '' ?>">
        </div>
        <div class="field">
          <label for="datum">Rechnungsdatum</label>
          <input type="date" id="datum" name="datum" required value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="field">
          <label for="kategorie_id">Kategorie</label>
          <select id="kategorie_id" name="kategorie_id"><?= $kategorien ?></select>
        </div>
        <div class="field">
          <label for="beleg_nr">Rechnungsnummer</label>
          <input type="text" id="beleg_nr" name="beleg_nr" maxlength="100">
        </div>
        <div class="field">
          <label for="bezahlt_am">Bezahlt am <span class="muted">(leer = gebucht, noch nicht bezahlt)</span></label>
          <input type="date" id="bezahlt_am" name="bezahlt_am">
        </div>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Ausgabe im Budget anlegen</button></div>
    </form>
  </section>
<?php endif; ?>

<?php if ($darf && $status === 'entwurf'): ?>
  <form method="post" action="<?= e(url('getraenke_action')) ?>" class="form" style="margin-top:1rem"><?= csrf_field() ?>
    <input type="hidden" name="action" value="bestellung_loeschen"><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Entwurf löschen?">Entwurf löschen</button></form>
<?php endif; ?>
