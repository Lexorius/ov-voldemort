<?php
/** @var string $art @var array $expense @var array $errors @var array $budgets @var array $wishes @var array $stichtag */
$isNew = empty($expense['id']);
$istEinnahme = $art === 'einnahme';
$wort = $istEinnahme ? 'Einnahme' : 'Ausgabe';

$betragWert = $expense['betrag_brutto'] ?? '';
$zurueck = url('expenses', ['jahr' => (int)($expense['jahr'] ?? date('Y')), 'art' => $art]);
?>
<div class="pagehead">
  <div>
    <h1><?= e($wort) ?> <?= $isNew ? 'erfassen' : 'bearbeiten' ?></h1>
  </div>
  <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$istEinnahme && !empty($stichtag['stichtag'])): ?>
  <div class="alert <?= $stichtag['gesperrt'] ? 'alert--error' : ($stichtag['tage'] <= 30 ? 'alert--warn' : 'alert--info') ?>">
    <?php if ($stichtag['gesperrt']): ?>
      <strong>Haushaltsjahr <?= (int)$expense['jahr'] ?> ist seit dem <?= e(de_date($stichtag['stichtag'])) ?> geschlossen.</strong>
      Ausgaben mit späterem Datum werden abgelehnt – nur „geplant" lässt sich noch vormerken.
    <?php else: ?>
      Stichtag des Haushaltsjahres <?= (int)$expense['jahr'] ?>: <strong><?= e(de_date($stichtag['stichtag'])) ?></strong>
      (noch <?= (int)$stichtag['tage'] ?> Tag<?= (int)$stichtag['tage'] === 1 ? '' : 'e' ?>). Danach dürfen keine Ausgaben mehr auf das Jahresbudget.
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('expense_edit', $isNew ? [] : ['id' => $expense['id']])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="art" value="<?= e($art) ?>">

  <section class="card">
    <div class="grid3">
      <div class="field">
        <label for="datum">Datum *</label>
        <input type="date" id="datum" name="datum" required value="<?= e((string)($expense['datum'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="jahr">Haushaltsjahr</label>
        <input type="number" id="jahr" name="jahr" min="2000" max="2100" value="<?= (int)($expense['jahr'] ?? date('Y')) ?>">
        <small>Weicht nur selten vom Datum ab.</small>
      </div>
      <div class="field">
        <label for="betrag">Betrag *</label>
        <input type="text" inputmode="decimal" id="betrag" name="betrag"<?= $istEinnahme ? '' : ' required' ?> placeholder="0,00"
               value="<?= e(num_input($betragWert)) ?>">
        <small><?= $istEinnahme ? 'Der Betrag, mit dem gerechnet wird – leer: der Betrag der letzten Stufe unten.' : 'Der Betrag, der tatsächlich bezahlt wurde.' ?></small>
      </div>
    </div>

    <div class="field">
      <label for="bezeichnung">Bezeichnung *</label>
      <input type="text" id="bezeichnung" name="bezeichnung" required maxlength="200"
             value="<?= e((string)$expense['bezeichnung']) ?>"
             placeholder="<?= $istEinnahme ? 'z.B. Kostenerstattung Einsatz Hochwasser' : 'z.B. Stromabschlag Februar' ?>">
    </div>
    <div class="field">
      <label for="beschreibung">Beschreibung</label>
      <textarea id="beschreibung" name="beschreibung"><?= e((string)($expense['beschreibung'] ?? '')) ?></textarea>
    </div>
  </section>

  <section class="card">
    <h2>Einordnung</h2>
    <div class="grid3">
      <div class="field">
        <label for="kategorie_id">Kategorie</label>
        <select id="kategorie_id" name="kategorie_id"><?= list_options(buchung_list_key($art), (int)($expense['kategorie_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($expense['fachgruppe_id'] ?? 0), 'ortsverbandsweit') ?></select>
      </div>
      <div class="field">
        <label for="budget_id">Budgettopf</label>
        <select id="budget_id" name="budget_id">
          <option value="">– keinem Topf zugeordnet –</option>
          <?php foreach ($budgets as $b): ?>
            <option value="<?= (int)$b['id'] ?>"<?= (int)($expense['budget_id'] ?? 0) === (int)$b['id'] ? ' selected' : '' ?>>
              <?= e($b['jahr'] . ' · ' . $b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="event_id">Gehört zu Veranstaltung</label>
        <select id="event_id" name="event_id">
          <option value="">– kein Bezug –</option>
          <?php foreach ($events as $v): ?>
            <option value="<?= (int)$v['id'] ?>"<?= (int)($expense['event_id'] ?? 0) === (int)$v['id'] ? ' selected' : '' ?>>
              <?= e(de_date(substr((string)$v['beginn'], 0, 10)) . ' · ' . mb_substr((string)$v['titel'], 0, 60)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="wish_id">Gehört zu Wunsch</label>
        <select id="wish_id" name="wish_id">
          <option value="">– kein Bezug –</option>
          <?php foreach ($wishes as $w): ?>
            <option value="<?= (int)$w['id'] ?>"<?= (int)($expense['wish_id'] ?? 0) === (int)$w['id'] ? ' selected' : '' ?>>
              #<?= (int)$w['id'] ?> · <?= e(mb_substr($w['bezeichnung'], 0, 60)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </section>

  <section class="card">
    <h2><?= $istEinnahme ? 'Abrechnung' : 'Beleg' ?></h2>
    <div class="grid3">
      <div class="field">
        <label for="lieferant"><?= $istEinnahme ? 'Auftraggeber / Kostenträger' : 'Lieferant / Empfänger' ?></label>
        <input type="text" id="lieferant" name="lieferant" value="<?= e((string)($expense['lieferant'] ?? '')) ?>"
               placeholder="<?= $istEinnahme ? 'z.B. Landkreis, Feuerwehr, Firma' : '' ?>">
      </div>
      <div class="field">
        <label for="beleg_nr"><?= $istEinnahme ? 'Aktenzeichen / Vorgangsnummer' : 'Belegnummer' ?></label>
        <input type="text" id="beleg_nr" name="beleg_nr" value="<?= e((string)($expense['beleg_nr'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="referenz"><?= $istEinnahme ? 'Einsatz- / Auftragsnummer' : 'Referenz' ?></label>
        <input type="text" id="referenz" name="referenz" value="<?= e((string)($expense['referenz'] ?? '')) ?>"
               placeholder="<?= $istEinnahme ? 'z.B. Einsatz 2026-014' : '' ?>">
      </div>
      <?php if (!$istEinnahme): ?>
      <div class="field">
        <label for="bezahlt_am">Bezahlt am</label>
        <input type="date" id="bezahlt_am" name="bezahlt_am" value="<?= e((string)($expense['bezahlt_am'] ?? '')) ?>">
      </div>
      <?php endif; ?>
    </div>
    <?php if ($istEinnahme): ?>
      <?php $stand = buchung_status((string)($expense['status'] ?? 'bezahlt')); if (!array_key_exists($stand, EINNAHME_STUFEN)) { $stand = $stand === 'offen' ? 'gestellt' : 'bezahlt'; } ?>
      <h3 class="mt">Weg der Einnahme</h3>
      <p class="small muted">Der OV rechnet ab, die Regionalstelle stellt Rechnung oder Gebührenbescheid, sagt Mittel zu
        und weist sie zu. Trage ein, was schon passiert ist – jedes Datum hebt den Stand auf seine Stufe. Weichen die
        Beträge ab, siehst du in der Liste, was unterwegs verloren ging.</p>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Stufe</th><th>Datum</th><th>Betrag</th><th>Nummer</th></tr></thead>
          <tbody>
          <?php foreach (EINNAHME_STUFEN_FELDER as $key => $f): ?>
            <tr>
              <td><strong><?= e($f['label']) ?></strong></td>
              <td><input type="date" name="<?= e($f['datum']) ?>" value="<?= e((string)($expense[$f['datum']] ?? '')) ?>"></td>
              <td><?php if (!empty($f['betrag'])): ?>
                <input type="text" inputmode="decimal" name="<?= e($f['betrag']) ?>" placeholder="0,00" style="max-width:9rem"
                       value="<?= e(($expense[$f['betrag']] ?? null) === null || $expense[$f['betrag']] === '' ? '' : num_input($expense[$f['betrag']])) ?>">
                <?php else: ?><span class="small muted">Betrag oben</span><?php endif; ?></td>
              <td><?php if (!empty($f['nr'])): ?>
                <input type="text" name="<?= e($f['nr']) ?>" maxlength="100" placeholder="Rechnungs-/Bescheidnummer" value="<?= e((string)($expense[$f['nr']] ?? '')) ?>">
                <?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="field mt">
        <label>Stand</label>
        <div>
          <?php foreach (EINNAHME_STUFEN as $key => $label): ?>
            <label class="wahl"><input type="radio" name="status" value="<?= e($key) ?>"<?= $stand === $key ? ' checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <small>Nur Eingegangenes zählt als Einnahme und ins Verfügbare. Zugesagtes steht daneben, abgerechnet und
          gestellt sind Forderungen, erwartet ist Planung. Ein Datum unten kann den Stand nur anheben.</small>
      </div>
    <?php else: ?>
    <div class="field">
      <label>Stand</label>
      <?php $stand = buchung_status((string)($expense['status'] ?? 'bezahlt')); ?>
      <div>
        <?php foreach (BUCHUNG_STATUS as $key => $label): ?>
          <label class="wahl"><input type="radio" name="status" value="<?= e($key) ?>"<?= $stand === $key ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>
      <small>Bezahlt und gebucht zählen als Ausgaben des Jahres, geplant nur in der Planung. Ein
        Zahlungsdatum setzt den Stand auf bezahlt.</small>
    </div>
    <?php endif; ?>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz"><?= e((string)($expense['notiz'] ?? '')) ?></textarea>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? e($wort) . ' eintragen' : 'Speichern' ?></button>
    <?php if ($isNew): ?>
      <button class="btn btn--sec" type="submit" name="weiter" value="1">Eintragen und nächste</button>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e($zurueck) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('expense_edit', ['id' => $expense['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit"
            data-confirm="Diese Buchung endgültig löschen?"><?= e($wort) ?> löschen</button>
  </form>
<?php endif; ?>
