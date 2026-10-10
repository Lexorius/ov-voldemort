<?php
/** @var array $konto @var array $errors @var array $kategorien @var array $gewaehlt @var array $kontenNamen */
$isNew = empty($konto['id']);
$kontenNamen ??= [];
?>
<div class="pagehead">
  <h1><?= $isNew ? 'Objektkonto anlegen' : 'Objektkonto bearbeiten' ?></h1>
  <a class="btn btn--sec" href="<?= e(url('objektkonten')) ?>">Abbrechen</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('objektkonto_edit', $isNew ? [] : ['id' => $konto['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="nummer">Kontonummer</label>
      <input type="text" id="nummer" name="nummer" maxlength="20" value="<?= e((string)($konto['nummer'] ?? '')) ?>" placeholder="z. B. 4210">
      <small>Wie im Haushaltsplan oder in der Buchhaltung – kann leer bleiben.</small>
    </div>
    <div class="field">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required maxlength="150" value="<?= e((string)($konto['name'] ?? '')) ?>" placeholder="z. B. Verpflegung">
    </div>
    <div class="field">
      <label for="color">Farbe</label>
      <input type="color" id="color" name="color" value="<?= e((string)($konto['color'] ?: '#64748b')) ?>">
    </div>
    <div class="field">
      <label for="sort_order">Reihenfolge</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= (int)($konto['sort_order'] ?? 0) ?>">
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($konto['is_active']) ? 'checked' : '' ?>>
      <label for="is_active">Aktiv</label>
    </div>
  </div>
  <div class="field">
    <label for="beschreibung">Beschreibung</label>
    <input type="text" id="beschreibung" name="beschreibung" maxlength="255" value="<?= e((string)($konto['beschreibung'] ?? '')) ?>">
  </div>

  <h2 class="mt">Kategorien auf diesem Konto</h2>
  <p class="small muted">Eine Kategorie kann nur auf einem Konto liegen – wer sie hier anhakt, nimmt sie dem bisherigen Konto weg.</p>
  <?php foreach (OBJEKTKONTO_LISTEN as $key => $label): $liste = array_filter($kategorien, static fn($k) => (string)$k['list_key'] === $key); if (!$liste) { continue; } ?>
    <h3><?= e($label) ?></h3>
    <div class="chips">
      <?php foreach ($liste as $k): $an = in_array((int)$k['id'], $gewaehlt, true); $anderes = (int)($k['objektkonto_id'] ?? 0); ?>
        <label class="chip<?= $an ? ' is-on' : '' ?><?= (int)$k['is_active'] !== 1 ? ' is-muted' : '' ?>" style="border-left:4px solid <?= e((string)$k['color']) ?>">
          <input type="checkbox" name="kategorien[]" value="<?= (int)$k['id'] ?>"<?= $an ? ' checked' : '' ?>>
          <?= e((string)$k['label']) ?>
          <?php if ($anderes > 0 && $anderes !== (int)($konto['id'] ?? 0) && isset($kontenNamen[$anderes])): ?>
            <span class="muted small">· bisher <?= e(objektkonto_titel($kontenNamen[$anderes])) ?></span>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="btnrow mt">
    <button class="btn" type="submit">Speichern</button>
    <a class="btn btn--sec" href="<?= e(url('objektkonten')) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" class="card" action="<?= e(url('objektkonto_edit', ['id' => $konto['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit" data-confirm="Objektkonto löschen? Die Kategorien bleiben erhalten, nur die Zuordnung ist weg.">Objektkonto löschen</button>
  </form>
<?php endif; ?>
