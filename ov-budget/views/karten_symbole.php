<?php
/** @var array $gruppiert @var int $anzahl @var array $gruppen @var bool $verwalten @var int $maxMb */
?>
<div class="pagehead">
  <div>
    <h1>Symbole</h1>
    <p>Die Bibliothek für alle Karten: taktische Zeichen, Piktogramme, eigene Zeichnungen. SVG bleibt in jeder Größe scharf; Bilder gehen auch.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('karten')) ?>">Zu den Karten</a>
    <?php if (can('view_ablage')): ?><a class="btn btn--sec" href="<?= e(url('ablage', ['k' => 'karte'])) ?>">🗂 In der Dateiablage</a><?php endif; ?>
  </div>
</div>

<?php if ($verwalten): ?>
  <section class="card">
    <h2>Symbole hochladen</h2>
    <form method="post" action="<?= e(url('karten_symbole')) ?>" class="form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="hochladen">
      <div class="grid3">
        <div class="field">
          <label for="symbole">Dateien</label>
          <?= upload_hinweis_html() ?><input type="file" id="symbole" name="symbole[]" multiple required accept=".svg,.png,.jpg,.jpeg,.webp,.gif,image/svg+xml,image/*">
          <small class="muted">SVG, PNG, JPG, WebP oder GIF, je höchstens <?= (int)$maxMb ?> MB (SVG 2 MB). Mehrere auf einmal möglich.</small>
        </div>
        <div class="field">
          <label for="gruppe">Gruppe <span class="muted">(freiwillig)</span></label>
          <input type="text" id="gruppe" name="gruppe" maxlength="100" list="karten-gruppen" placeholder="z. B. Taktische Zeichen">
        </div>
        <div class="field">
          <label for="name">Name <span class="muted">(bei einer einzelnen Datei)</span></label>
          <input type="text" id="name" name="name" maxlength="150" placeholder="sonst der Dateiname">
        </div>
      </div>
      <datalist id="karten-gruppen"><?php foreach ($gruppen as $g): ?><option value="<?= e((string)$g) ?>"><?php endforeach; ?></datalist>
      <div class="btnrow"><button class="btn" type="submit">Hochladen</button></div>
      <p class="small muted" style="margin:.5rem 0 0">Eine SVG wird vor dem Ablegen geprüft: enthält sie Skript, Ereignis-Attribute oder lädt sie etwas von außen nach,
        wird sie abgelehnt. Die Gruppe wird in der Symbolauswahl und in der Dateiablage zur Unterkategorie.</p>
    </form>
  </section>
<?php endif; ?>

<?php if (!$anzahl): ?>
  <div class="card"><div class="empty">Noch kein Symbol in der Bibliothek.<?= $verwalten ? ' Oben die ersten hochladen.' : ' Die Leitung lädt sie hoch.' ?></div></div>
<?php endif; ?>

<?php foreach ($gruppiert as $gruppe => $liste): ?>
  <section class="card">
    <div class="card__head"><h2><?= (string)$gruppe !== '' ? e((string)$gruppe) : 'Ohne Gruppe' ?></h2><span class="muted small"><?= count($liste) ?></span></div>
    <div class="symbolraster">
      <?php foreach ($liste as $s): ?>
        <div class="symbolraster__eintrag">
          <a class="symbolraster__bild" href="<?= e(url('karten_symbol', ['id' => $s['id']])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(url('karten_symbol', ['id' => $s['id']])) ?>" alt="<?= e((string)$s['name']) ?>" loading="lazy"></a>
          <div class="symbolraster__name"><?= e((string)$s['name']) ?></div>
          <div class="small muted"><?= e(strtoupper(pathinfo((string)$s['orig_name'], PATHINFO_EXTENSION))) ?> · <?= e(bytes_human((int)$s['size_bytes'])) ?><?= (int)$s['verwendet'] > 0 ? ' · ' . (int)$s['verwendet'] . '× gesetzt' : '' ?></div>
          <?php if ($verwalten): ?>
            <details class="ki__edit">
              <summary class="small muted">Ändern</summary>
              <form method="post" action="<?= e(url('karten_symbole')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="symbol_id" value="<?= (int)$s['id'] ?>">
                <div class="field"><label>Name</label><input type="text" name="name" maxlength="150" value="<?= e((string)$s['name']) ?>"></div>
                <div class="field"><label>Gruppe</label><input type="text" name="gruppe" maxlength="100" list="karten-gruppen" value="<?= e((string)$s['gruppe']) ?>"></div>
                <div class="btnrow">
                  <button class="btn btn--sm" type="submit" name="action" value="aendern">Speichern</button>
                  <button class="btn btn--sec btn--sm" type="submit" name="action" value="loeschen" data-confirm="Symbol „<?= e((string)$s['name']) ?>“ aus der Bibliothek löschen?">Löschen</button>
                </div>
              </form>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
