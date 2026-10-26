<?php
/** @var array $absender @var array $errors @var array $dienststellen @var array $pfade @var int $gewaehlt */
$isNew = empty($absender['id']);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Absender anlegen' : 'Absender bearbeiten' ?></h1>
    <p>In wessen Namen bestellt wird: der Ortsverband, der Helferverein oder die Regionalstelle. Name und
       Rechnungsanschrift stehen in der Bestell-Mail; die Unterschrift nennt die Person, die geklickt hat, und diesen Absender.</p>
  </div>
  <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#absender">Abbrechen</a></div>
</div>

<?php foreach ($errors as $f): ?><div class="alert alert--error"><?= e($f) ?></div><?php endforeach; ?>

<?php if ($isNew && $dienststellen): ?>
  <section class="card">
    <div class="card__head"><h2>Aus den THW-Kontakten übernehmen</h2><span class="small muted">füllt Name, Anschrift, E-Mail und Telefon vor</span></div>
    <form method="get" class="form">
      <input type="hidden" name="p" value="getraenke_absender_edit">
      <div class="field">
        <label for="dienststelle_id">Dienststelle</label>
        <select id="dienststelle_id" name="dienststelle_id">
          <option value="">– Dienststelle wählen –</option>
          <?php foreach ($dienststellen as $d): ?>
            <option value="<?= (int)$d['id'] ?>"<?= (int)$d['id'] === $gewaehlt ? ' selected' : '' ?>><?= e((string)($pfade[(int)$d['id']] ?? $d['name'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="btnrow"><button class="btn btn--sec" type="submit">Übernehmen</button></div>
    </form>
  </section>
<?php endif; ?>

<form method="post" class="form">
  <?= csrf_field() ?>
  <section class="card">
    <div class="grid2">
      <div class="field" style="grid-column:1/-1">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)$absender['name']) ?>" placeholder="z. B. THW Ortsverband Heilbronn">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="anschrift">Rechnungsanschrift <span class="muted">(eine Zeile je Adresszeile, steht unter „Rechnung an")</span></label>
        <textarea id="anschrift" name="anschrift" rows="4" maxlength="500" placeholder="THW Ortsverband Heilbronn&#10;Musterstraße 1&#10;74072 Heilbronn"><?= e((string)$absender['anschrift']) ?></textarea>
      </div>
      <div class="field">
        <label for="email">E-Mail <span class="muted">(Rückfragen, steht in der Unterschrift)</span></label>
        <input type="email" id="email" name="email" maxlength="150" value="<?= e((string)$absender['email']) ?>">
      </div>
      <div class="field">
        <label for="telefon">Telefon</label>
        <input type="text" id="telefon" name="telefon" maxlength="60" value="<?= e((string)$absender['telefon']) ?>">
      </div>
      <div class="field">
        <label for="sort_order">Reihenfolge <span class="muted">(der erste ist die Vorgabe)</span></label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int)$absender['sort_order'] ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label for="notiz">Notiz <span class="muted">(z. B. Kundennummern bei den Lieferanten)</span></label>
        <input type="text" id="notiz" name="notiz" maxlength="200" value="<?= e((string)$absender['notiz']) ?>">
      </div>
      <div class="field field--check">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)($absender['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label for="is_active">Aktiv</label>
      </div>
    </div>
  </section>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('getraenke')) ?>#absender">Abbrechen</a>
  </div>
</form>
<?php if (!$isNew): ?>
  <form method="post" class="form" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Absender löschen?">Löschen</button>
  </form>
<?php endif; ?>
