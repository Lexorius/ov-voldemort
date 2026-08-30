<?php
/** @var array $wish @var array $errors @var array $budgets @var array $anlagen */
$isNew = empty($wish['id']);
$extra = wish_extra($wish);
$extraFields = wish_extra_fields();
$pflichtAb = setting_float('wunsch_angebot_pflicht_ab', 0);
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Neuer Wunsch' : 'Wunsch bearbeiten' ?></h1>
    <?php if (!$isNew): ?><p class="muted small">#<?= (int)$wish['id'] ?></p><?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= $isNew ? e(url('wishes')) : e(url('wish', ['id' => $wish['id']])) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error">
    <strong>Bitte noch korrigieren:</strong>
    <ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form"
      action="<?= e(url('wish_edit', $isNew ? [] : ['id' => $wish['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Was wird gebraucht?</h2>
    <div class="form">
      <div class="field">
        <label for="bezeichnung">Bezeichnung *</label>
        <input type="text" id="bezeichnung" name="bezeichnung" required maxlength="200"
               value="<?= e((string)$wish['bezeichnung']) ?>" placeholder="z.B. Akku-Bohrhammer inkl. 2 Akkus">
      </div>
      <div class="field">
        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung" placeholder="Technische Daten, Ausführung, Zubehör ..."><?= e((string)$wish['beschreibung']) ?></textarea>
      </div>
      <div class="field">
        <label for="begruendung">Begründung<?= setting_bool('wunsch_begruendung_pflicht', true) ? ' *' : '' ?></label>
        <textarea id="begruendung" name="begruendung" placeholder="Wofür wird das gebraucht? Was geht ohne nicht?"><?= e((string)$wish['begruendung']) ?></textarea>
        <small>Je konkreter, desto leichter die gemeinsame Priorisierung.</small>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Menge und Kosten</h2>
    <div class="grid3">
      <div class="field">
        <label for="f-anzahl">Anzahl</label>
        <input type="text" inputmode="decimal" id="f-anzahl" name="anzahl"
               value="<?= e(num_input($wish['anzahl'] ?: 1, true)) ?>">
      </div>
      <div class="field">
        <label for="einheit_id">Einheit</label>
        <select id="einheit_id" name="einheit_id"><?= list_options('einheit', (int)($wish['einheit_id'] ?? 0), '') ?></select>
      </div>
      <div class="field">
        <label for="f-netto-einzel">Nettobetrag je Einheit</label>
        <input type="text" inputmode="decimal" id="f-netto-einzel" name="netto_einzel"
               value="<?= e(num_input($wish['netto_einzel'] ?? '')) ?>" placeholder="0,00">
      </div>
      <div class="field">
        <label for="f-netto-gesamt">Nettobetrag gesamt</label>
        <input type="text" inputmode="decimal" id="f-netto-gesamt" name="netto_gesamt"
               value="<?= e(num_input($wish['netto_gesamt'] ?? '')) ?>" placeholder="0,00">
        <small id="f-gesamt-hinweis"></small>
      </div>
      <div class="field">
        <label for="mwst_satz">MwSt (%)</label>
        <input type="text" inputmode="decimal" id="mwst_satz" name="mwst_satz"
               value="<?= e(num_input($wish['mwst_satz'] ?: 19, true)) ?>">
      </div>
      <div class="field">
        <label for="benoetigt_bis">Benötigt bis</label>
        <input type="date" id="benoetigt_bis" name="benoetigt_bis" value="<?= e((string)($wish['benoetigt_bis'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Einordnung</h2>
    <div class="grid3">
      <div class="field">
        <label for="fachgruppe_id">Fachgruppe / Einheit</label>
        <select id="fachgruppe_id" name="fachgruppe_id"><?= list_options('fachgruppe', (int)($wish['fachgruppe_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="kategorie_id">Kategorie</label>
        <select id="kategorie_id" name="kategorie_id"><?= list_options('kategorie', (int)($wish['kategorie_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="dringlichkeit_id">Dringlichkeit</label>
        <select id="dringlichkeit_id" name="dringlichkeit_id"><?= list_options('dringlichkeit', (int)($wish['dringlichkeit_id'] ?? 0), '') ?></select>
      </div>
      <?php if (can('change_status')): ?>
        <div class="field">
          <label for="status_id">Status</label>
          <select id="status_id" name="status_id"><?= list_options('wunsch_status', (int)($wish['status_id'] ?? 0), '') ?></select>
        </div>
      <?php endif; ?>
      <?php if (can('manage_wishes')): ?>
        <div class="field">
          <label for="budget_id">Budgettopf</label>
          <select id="budget_id" name="budget_id">
            <option value="">– keinem Topf zugeordnet –</option>
            <?php foreach ($budgets as $b): ?>
              <option value="<?= (int)$b['id'] ?>"<?= (int)($wish['budget_id'] ?? 0) === (int)$b['id'] ? ' selected' : '' ?>>
                <?= e($b['jahr'] . ' · ' . $b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="prioritaet">Priorität (Reihung)</label>
          <input type="number" id="prioritaet" name="prioritaet" value="<?= (int)($wish['prioritaet'] ?? 0) ?>" step="1">
          <small>Höhere Zahl = weiter oben in der Liste.</small>
        </div>
      <?php endif; ?>
      <div class="field field--check">
        <input type="checkbox" id="nice_to_have" name="nice_to_have" value="1" <?= !empty($wish['nice_to_have']) ? 'checked' : '' ?>>
        <label for="nice_to_have">Nice to have (verzichtbar)</label>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Beschaffung</h2>
    <div class="grid3">
      <div class="field">
        <label for="lieferant">Lieferant / Händler</label>
        <input type="text" id="lieferant" name="lieferant" value="<?= e((string)($wish['lieferant'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="artikelnummer">Artikelnummer</label>
        <input type="text" id="artikelnummer" name="artikelnummer" value="<?= e((string)($wish['artikelnummer'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="link">Link zum Produkt</label>
        <input type="url" id="link" name="link" value="<?= e((string)($wish['link'] ?? '')) ?>" placeholder="https://">
      </div>
      <div class="field">
        <label for="antragsteller">Antragsteller</label>
        <input type="text" id="antragsteller" name="antragsteller" value="<?= e((string)($wish['antragsteller'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <?php if ($extraFields): ?>
    <section class="card">
      <h2>Weitere Angaben</h2>
      <div class="grid2">
        <?php foreach ($extraFields as $key => $def):
            $val = (string)($extra[$key] ?? ''); ?>
          <div class="field<?= $def['type'] === 'bool' ? ' field--check' : '' ?>">
            <?php if ($def['type'] === 'bool'): ?>
              <input type="checkbox" id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
            <?php elseif ($def['type'] === 'textarea'): ?>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
              <textarea id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>"><?= e($val) ?></textarea>
            <?php else: ?>
              <label for="extra_<?= e($key) ?>"><?= e($def['label']) ?></label>
              <input type="<?= $def['type'] === 'date' ? 'date' : ($def['type'] === 'number' ? 'number' : 'text') ?>"
                     id="extra_<?= e($key) ?>" name="extra_<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="card">
    <h2>Angebote und Anlagen</h2>
    <?php if ($pflichtAb > 0): ?>
      <p class="small muted">Ab einem Nettobetrag von <?= e(money($pflichtAb)) ?> ist mindestens ein Angebot verpflichtend.</p>
    <?php endif; ?>

    <?php if ($anlagen): ?>
      <div class="filelist mb0" style="margin-bottom:1rem">
        <?php foreach ($anlagen as $a): ?>
          <div class="file">
            <div>
              <div class="file__name"><a href="<?= e(url('download', ['id' => $a['id']])) ?>"><?= e($a['orig_name']) ?></a></div>
              <div class="file__meta"><?= e($a['kind']) ?> · <?= e(bytes_human((int)$a['size_bytes'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid3">
      <div class="field">
        <label for="anlagen">Dateien hinzufügen</label>
        <input type="file" id="anlagen" name="anlagen[]" multiple data-max-mb="<?= setting_int('upload_max_mb', 10) ?>">
        <small>Erlaubt: <?= e(implode(', ', upload_allowed_extensions())) ?> · max. <?= setting_int('upload_max_mb', 10) ?> MB je Datei</small>
      </div>
      <div class="field">
        <label for="anlage_typ">Art der Anlage</label>
        <select id="anlage_typ" name="anlage_typ">
          <?php foreach (list_items('anlage_typ') as $t): ?>
            <option value="<?= e($t['slug']) ?>"><?= e($t['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="anlage_betrag">Angebotssumme netto (optional)</label>
        <input type="text" inputmode="decimal" id="anlage_betrag" name="anlage_betrag" placeholder="0,00">
      </div>
    </div>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Wunsch eintragen' : 'Änderungen speichern' ?></button>
    <a class="btn btn--sec" href="<?= $isNew ? e(url('wishes')) : e(url('wish', ['id' => $wish['id']])) ?>">Abbrechen</a>
  </div>
</form>
