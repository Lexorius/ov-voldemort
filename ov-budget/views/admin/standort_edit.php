<?php
/** @var array $s @var ?array $parent @var array $alle @var array $errors @var string $pfad @var int $kinder */
$isNew = empty($s['id']);
$typ = standort_typ((string)($s['typ'] ?? 'raum'));
$ausser = $isNew ? [] : array_merge([(int)$s['id']], standort_nachkommen((int)$s['id'], $alle));
?>
<div class="pagehead">
  <div>
    <h1><?= $isNew ? 'Platz anlegen' : 'Platz bearbeiten' ?></h1>
    <?php if ($pfad !== ''): ?><p class="muted small"><?= e($pfad) ?></p><?php endif; ?>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin_standorte')) ?>">Zur Übersicht</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert--error"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form" action="<?= e(url('admin_standort_edit', $isNew ? [] : ['id' => $s['id']])) ?>">
  <?= csrf_field() ?>
  <div class="grid2">
    <div class="field">
      <label for="parent_id">Liegt in</label>
      <select id="parent_id" name="parent_id" data-standort-eltern>
        <?= standort_optionen($alle, (int)($s['parent_id'] ?? 0), '– ganz oben (Gebäude, Halle, Hof) –', $ausser) ?>
      </select>
      <small>Gebäude, Hallen und Höfe stehen ganz oben. Stockwerke und Räume liegen in Gebäuden, Stellplätze in Hallen,
        Schränke in Räumen, Regale in Schränken.</small>
    </div>
    <div class="field">
      <label for="typ">Art</label>
      <select id="typ" name="typ">
        <?php foreach (STANDORT_TYPEN as $key => $t): ?>
          <option value="<?= e($key) ?>"<?= $typ === $key ? ' selected' : '' ?> data-eltern="<?= e(implode(',', $t['eltern'])) ?>"><?= e($t['icon'] . ' ' . $t['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="grid2">
    <div class="field">
      <label for="name">Name *</label>
      <input type="text" id="name" name="name" required maxlength="120" value="<?= e((string)$s['name']) ?>" placeholder="z. B. Haupthaus, 1. Stock, Raum, Stellplatz">
      <?php if ($isNew): ?><small>Bei einer Serie wird die Nummer angehängt: „Raum 1", „Raum 2", …</small><?php endif; ?>
    </div>
    <div class="field">
      <label for="kurz">Kurzzeichen</label>
      <input type="text" id="kurz" name="kurz" maxlength="30" value="<?= e((string)($s['kurz'] ?? '')) ?>" placeholder="z. B. HH, 1OG, R">
      <small>Fürs Etikett oder die Inventarliste; bei einer Serie mit Nummer.</small>
    </div>
  </div>
  <?php if ($isNew): ?>
    <div class="grid2">
      <div class="field">
        <label for="anzahl">Wie viele auf einmal</label>
        <input type="number" id="anzahl" name="anzahl" min="1" max="<?= STANDORT_MAX_SERIE ?>" value="<?= (int)($s['anzahl'] ?? 1) ?>" style="max-width:8rem">
        <small>1 = nur dieser Platz. 16 = „Raum 1" bis „Raum 16".</small>
      </div>
      <div class="field">
        <label for="start">Erste Nummer</label>
        <input type="number" id="start" name="start" min="0" value="<?= (int)($s['start'] ?? 1) ?>" style="max-width:8rem">
      </div>
    </div>
  <?php endif; ?>
  <div class="field">
    <label for="notiz">Notiz</label>
    <textarea id="notiz" name="notiz" rows="2"><?= e((string)($s['notiz'] ?? '')) ?></textarea>
  </div>
  <div class="field field--check">
    <input type="checkbox" id="is_active" name="is_active" value="1"<?= (int)($s['is_active'] ?? 1) === 1 ? ' checked' : '' ?>>
    <label for="is_active">Aktiv (auswählbar)</label>
  </div>
  <div class="btnrow">
    <button class="btn" type="submit"><?= $isNew ? 'Anlegen' : 'Speichern' ?></button>
    <a class="btn btn--sec" href="<?= e(url('admin_standorte')) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <div class="grid2">
    <form method="post" class="card" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="aktiv">
      <h2><?= (int)$s['is_active'] === 1 ? 'Stilllegen' : 'Wieder aktivieren' ?></h2>
      <p class="small muted">Ein stillgelegter Platz bleibt im Baum, lässt sich aber nirgends mehr auswählen.</p>
      <button class="btn btn--sec" type="submit"><?= (int)$s['is_active'] === 1 ? 'Stilllegen' : 'Aktivieren' ?></button>
    </form>
    <form method="post" class="card" action="<?= e(url('admin_standort_edit', ['id' => $s['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <h2>Löschen</h2>
      <?php if ($kinder > 0): ?>
        <p class="small muted">Hat noch <?= (int)$kinder ?> Unterplatz/Unterplätze – erst diese löschen oder woanders einhängen.</p>
        <button class="btn btn--danger" type="submit" disabled>Löschen</button>
      <?php else: ?>
        <p class="small muted">Endgültig. Bezüge von Geräten oder Fahrzeugen auf diesen Platz werden leer.</p>
        <button class="btn btn--danger" type="submit" data-confirm="Diesen Platz endgültig löschen?">Löschen</button>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>
