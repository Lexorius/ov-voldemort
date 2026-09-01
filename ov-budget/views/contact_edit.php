<?php
/** @var array $contact @var array $errors @var array $verteiler @var array $mitglied */
$isNew = empty($contact['id']);
$extra = extra_values($contact);
$extraFelder = contact_extra_fields();
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Kontakt anlegen' : 'Kontakt bearbeiten' ?></h1>
    <?php if (!$isNew): ?>
      <p class="muted small"><?= e(contact_salutation($contact)) ?></p>
    <?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Zur Liste</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form" action="<?= e(url('contact_edit', $isNew ? [] : ['id' => $contact['id']])) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Person und Organisation</h2>
    <p class="small muted">Nachname oder Organisation genügt – für reine Behördenkontakte
       reicht also die Einrichtung ohne Ansprechpartner.</p>
    <div class="grid3">
      <div class="field">
        <label for="anrede">Anrede</label>
        <select id="anrede" name="anrede">
          <?php foreach (['' => '– keine –', 'Herr' => 'Herr', 'Frau' => 'Frau'] as $k => $lbl): ?>
            <option value="<?= e($k) ?>"<?= (string)$contact['anrede'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="titel">Titel</label>
        <input type="text" id="titel" name="titel" value="<?= e((string)$contact['titel']) ?>" placeholder="Dr.">
      </div>
      <div class="field">
        <label for="kategorie_id">Kategorie</label>
        <select id="kategorie_id" name="kategorie_id"><?= list_options('kontakt_kategorie', (int)($contact['kategorie_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="vorname">Vorname</label>
        <input type="text" id="vorname" name="vorname" value="<?= e((string)$contact['vorname']) ?>">
      </div>
      <div class="field">
        <label for="nachname">Nachname</label>
        <input type="text" id="nachname" name="nachname" value="<?= e((string)$contact['nachname']) ?>">
      </div>
      <div class="field">
        <label for="position">Funktion / Position</label>
        <input type="text" id="position" name="position" value="<?= e((string)$contact['position']) ?>"
               placeholder="z.B. Bürgermeister, Wehrführer">
      </div>
    </div>
    <div class="field">
      <label for="organisation">Organisation</label>
      <input type="text" id="organisation" name="organisation" value="<?= e((string)$contact['organisation']) ?>"
             placeholder="z.B. Stadt Musterstadt, Freiwillige Feuerwehr">
    </div>
  </section>

  <section class="card">
    <h2>Erreichbarkeit</h2>
    <div class="grid3">
      <div class="field">
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" value="<?= e((string)$contact['email']) ?>">
      </div>
      <div class="field">
        <label for="telefon">Telefon</label>
        <input type="tel" id="telefon" name="telefon" value="<?= e((string)$contact['telefon']) ?>">
      </div>
      <div class="field">
        <label for="mobil">Mobil</label>
        <input type="tel" id="mobil" name="mobil" value="<?= e((string)$contact['mobil']) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Anschrift</h2>
    <div class="grid3">
      <div class="field" style="grid-column:1 / -1">
        <label for="strasse">Straße und Hausnummer</label>
        <input type="text" id="strasse" name="strasse" value="<?= e((string)$contact['strasse']) ?>">
      </div>
      <div class="field">
        <label for="plz">PLZ</label>
        <input type="text" id="plz" name="plz" value="<?= e((string)$contact['plz']) ?>">
      </div>
      <div class="field">
        <label for="ort">Ort</label>
        <input type="text" id="ort" name="ort" value="<?= e((string)$contact['ort']) ?>">
      </div>
      <div class="field">
        <label for="land">Land</label>
        <input type="text" id="land" name="land" value="<?= e((string)$contact['land']) ?>"
               placeholder="nur wenn nicht Deutschland">
      </div>
    </div>
    <div class="field">
      <label for="anschreiben">Briefanrede</label>
      <input type="text" id="anschreiben" name="anschreiben" value="<?= e((string)$contact['anschreiben']) ?>"
             placeholder="<?= e(contact_salutation(array_merge($contact, ['anschreiben' => '']))) ?>">
      <small>Leer lassen: wird aus Anrede und Nachname gebildet und landet so im CSV für den Serienbrief.</small>
    </div>
  </section>

  <?php if ($extraFelder): ?>
    <section class="card">
      <h2>Weitere Angaben</h2>
      <div class="grid2">
        <?php foreach ($extraFelder as $key => $def):
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
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" placeholder="Woher stammt der Kontakt, was ist zu beachten?"><?= e((string)$contact['notiz']) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($contact['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv (erscheint in Listen und Verteilern)</label>
    </div>

    <?php if ($isNew && $verteiler): ?>
      <div class="field">
        <label for="add_to_group">Gleich auf einen Verteiler setzen</label>
        <select id="add_to_group" name="add_to_group">
          <option value="">– nein –</option>
          <?php foreach ($verteiler as $v): ?>
            <option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <?php if (!$isNew && $mitglied): ?>
      <h3 class="mt">Auf diesen Verteilern</h3>
      <div class="chips">
        <?php foreach ($mitglied as $m): ?>
          <a class="chip" href="<?= e(url('contact_group', ['id' => $m['id']])) ?>"><?= e($m['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Kontakt anlegen' : 'Speichern' ?></button>
    <?php if ($isNew): ?>
      <button class="btn btn--sec" type="submit" name="weiter" value="1">Anlegen und nächster</button>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('contact_edit', ['id' => $contact['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <p class="small muted">Zum vorübergehenden Ausblenden besser den Haken „Aktiv" entfernen –
       dann bleiben Verteilerzuordnungen erhalten.</p>
    <button class="btn btn--danger" type="submit"
            data-confirm="Diesen Kontakt endgültig löschen? Er verschwindet auch aus allen Verteilern.">Kontakt löschen</button>
  </form>
<?php endif; ?>
