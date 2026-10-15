<?php
/** @var string $q @var array $treffer @var array $tags @var bool $aktiv */
?>
<div class="pagehead">
  <div>
    <h1>Dateisuche</h1>
    <p>Sucht in den Schlagwörtern und Zusammenfassungen, die die KI-Auswertung zu den Dateien
       der Fahrzeugakten, Veranstaltungen, Termine und Plätze abgelegt hat.</p>
  </div>
  <?php if (can('admin')): ?>
    <div class="btnrow"><a class="btn btn--sec" href="<?= e(url('admin_ki')) ?>">KI-Auswertung</a></div>
  <?php endif; ?>
</div>

<div class="card">
  <form method="get" action="<?= e(url()) ?>" class="form">
    <input type="hidden" name="p" value="ki_suche">
    <div class="field"><label for="q">Schlagwort oder Wort aus der Zusammenfassung</label>
      <div class="btnrow">
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="z. B. rechnung, hu, einladung" style="flex:1 1 14rem" autofocus>
        <button class="btn" type="submit">Suchen</button>
      </div>
    </div>
  </form>
  <?php if ($tags): ?>
    <div class="chips" style="margin-top:.5rem">
      <?php foreach ($tags as $t): ?>
        <a class="chip chip--link<?= mb_strtolower($t['tag']) === mb_strtolower($q) ? ' chip--aktiv' : '' ?>" href="<?= e(url('ki_suche', ['q' => $t['tag']])) ?>">#<?= e($t['tag']) ?> <span class="muted"><?= (int)$t['n'] ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php elseif (!$aktiv): ?>
    <p class="muted small" style="margin-top:.5rem">Noch keine Schlagwörter – die KI-Auswertung ist ausgeschaltet<?= can('admin') ? ', einschalten geht in den <a href="' . e(url('admin_settings', ['group' => 'KI-Auswertung'])) . '">Einstellungen</a>' : '' ?>.</p>
  <?php else: ?>
    <p class="muted small" style="margin-top:.5rem">Noch keine Schlagwörter – neue Dateien werden nach dem Hochladen ausgewertet.</p>
  <?php endif; ?>
</div>

<?php if ($q !== ''): ?>
<div class="card">
  <div class="card__head"><h2>Treffer für „<?= e($q) ?>"</h2><span class="muted small"><?= count($treffer) ?></span></div>
  <?php if (!$treffer): ?>
    <div class="empty">Nichts gefunden.</div>
  <?php else: ?>
    <div class="doclist">
      <?php foreach ($treffer as $t): ?>
        <div class="doc">
          <span class="doc__icon" aria-hidden="true"><?= e(strtoupper(pathinfo((string)$t['datei']['orig_name'], PATHINFO_EXTENSION) ?: '?')) ?></span>
          <div class="doc__text">
            <a href="<?= e($t['link']) ?>"><?= e((string)($t['datei']['titel'] ?: $t['datei']['orig_name'])) ?></a>
            <span class="badge badge--outline"><?= e($t['label']) ?></span>
            <div class="small"><a href="<?= e($t['eltern']['url']) ?>"><?= e($t['eltern']['text']) ?></a><?= !empty($t['datei']['created_at']) ? ' · <span class="muted">' . e(de_datetime((string)$t['datei']['created_at'])) . '</span>' : '' ?></div>
            <?php if ($t['ki']['zusammenfassung'] !== ''): ?>
              <div class="small ki__text"><?= e((string)$t['ki']['zusammenfassung']) ?></div>
            <?php endif; ?>
            <?php if ($t['ki']['tags']): ?>
              <div class="chips ki__tags">
                <?php foreach ($t['ki']['tags'] as $tag): ?>
                  <a class="chip chip--link" href="<?= e(url('ki_suche', ['q' => $tag])) ?>">#<?= e($tag) ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
