<?php /** @var array $gruppe @var array $errors */ ?>
<div class="pagehead">
  <div>
    <h1>Verteiler anlegen</h1>
    <p>Ein Verteiler bündelt Kontakte für einen Anlass. Die Kontakte kommen im nächsten
       Schritt dazu.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('contact_groups')) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('contact_group')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <div class="grid3">
    <div class="field">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required value="<?= e((string)$gruppe['name']) ?>"
             placeholder="z.B. Einladung Jubiläum 2027">
    </div>
    <div class="field">
      <label for="anlass_am">Termin</label>
      <input type="date" id="anlass_am" name="anlass_am" value="<?= e((string)$gruppe['anlass_am']) ?>">
    </div>
    <div class="field">
      <label for="ort">Ort</label>
      <input type="text" id="ort" name="ort" value="<?= e((string)$gruppe['ort']) ?>">
    </div>
  </div>
  <div class="field">
    <label for="beschreibung">Beschreibung</label>
    <textarea id="beschreibung" name="beschreibung"><?= e((string)$gruppe['beschreibung']) ?></textarea>
  </div>
  <div class="field field--check">
    <input type="checkbox" id="is_active" name="is_active" value="1" checked>
    <label for="is_active">Aktiv</label>
  </div>
  <div class="btnrow">
    <button class="btn" type="submit">Anlegen</button>
    <a class="btn btn--sec" href="<?= e(url('contact_groups')) ?>">Abbrechen</a>
  </div>
</form>
