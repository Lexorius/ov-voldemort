<?php
/** @var array $baum @var ?array $knoten @var string $key @var string $q @var array $dateien @var array $kiDaten
 *  @var array $eigene @var bool $verwalten @var bool $hochladen @var array $tags @var int $maxMb @var array $typen */
$modul = (string)setting('ablage_modul_name', 'Dateiablage');
$weg = $knoten ? ablage_pfad($key, $baum) : [];
$hier = url('ablage', $q !== '' ? ['q' => $q] : ($knoten ? ['k' => $key] : []));
$kinder = $knoten ? $knoten['kinder'] : $baum['wurzeln'];
$eigen = $knoten && !$knoten['auto'];
$hatEigene = false;
foreach ($baum['knoten'] as $n) {
    if (!$n['auto']) { $hatEigene = true; break; }
}
$zeile = static fn(array $d, bool $ort): string => render_partial('partials/ablage_datei', [
    'd' => $d, 'kiDaten' => $kiDaten, 'baum' => $baum, 'eigene' => $eigene, 'zeigeOrt' => $ort, 'hier' => $hier,
]);
?>
<div class="pagehead">
  <div>
    <h1><?= e($modul) ?></h1>
    <p><?= e((string)setting('ablage_intro', 'Alle Dokumente des Ortsverbands an einem Ort: eigene Kategorien und die Ablagen der Module.')) ?></p>
  </div>
  <?php if ($verwalten): ?>
    <div class="btnrow">
      <a class="btn" href="<?= e(url('ablage_kategorie_edit', $eigen ? ['eltern' => $key] : [])) ?>">+ <?= $eigen ? 'Unterkategorie' : 'Kategorie' ?></a>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <form method="get" action="<?= e(url()) ?>" class="form">
    <input type="hidden" name="p" value="ablage">
    <div class="btnrow">
      <input type="search" name="q" value="<?= e($q) ?>" aria-label="In der Dateiablage suchen" placeholder="Name, Schlagwort oder Wort aus der Zusammenfassung – z. B. rechnung, hu, mietvertrag" style="flex:1 1 14rem">
      <button class="btn" type="submit">Suchen</button>
      <?php if ($q !== ''): ?><a class="btn btn--sec" href="<?= e(url('ablage', $knoten ? ['k' => $key] : [])) ?>">Suche beenden</a><?php endif; ?>
    </div>
  </form>
  <?php if ($tags): ?>
    <div class="chips" style="margin-top:.6rem">
      <?php foreach ($tags as $t): ?>
        <a class="chip chip--link" href="<?= e(url('ablage', ['q' => $t['tag']])) ?>">#<?= e($t['tag']) ?> <span class="muted"><?= (int)$t['n'] ?></span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="ablage">
  <aside class="card ablage__baum">
    <div class="card__head">
      <h2>Kategorien</h2>
      <?php if ($knoten || $q !== ''): ?><a class="small" href="<?= e(url('ablage')) ?>">Übersicht</a><?php endif; ?>
    </div>
    <?= ablage_baum_html($baum, $key) ?>
    <p class="small muted" style="margin:.7rem 0 0">🔒 kommt aus einem Modul und wird dort gepflegt.<?= $verwalten ? ' 📁 eigene Kategorien lassen sich anlegen, umbenennen und umhängen.' : '' ?></p>
  </aside>

  <div class="ablage__inhalt">
  <?php if ($q !== ''): ?>
    <section class="card">
      <div class="card__head"><h2>Treffer für „<?= e($q) ?>"</h2><span class="muted small"><?= count($dateien) ?></span></div>
      <?php if (!$dateien): ?>
        <div class="empty">Nichts gefunden.</div>
      <?php else: ?>
        <div class="doclist"><?php foreach ($dateien as $d): ?><?= $zeile($d, true) ?><?php endforeach; ?></div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <?php if ($knoten): ?>
      <nav class="small" aria-label="Pfad" style="margin-bottom:.5rem">
        <a href="<?= e(url('ablage')) ?>"><?= e($modul) ?></a>
        <?php foreach ($weg as $w): ?> › <?php if ($w['key'] === $key): ?><strong><?= e((string)$w['name']) ?></strong><?php else: ?><a href="<?= e(url('ablage', ['k' => $w['key']])) ?>"><?= e((string)$w['name']) ?></a><?php endif; ?><?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <section class="card">
      <div class="card__head">
        <h2><?php if ($knoten): ?><?= $knoten['auto'] ? '<span title="Kommt aus dem Modul und wird dort gepflegt">🔒</span> ' : '' ?><?= e((string)$knoten['name']) ?>
          <?= $knoten['hinweis'] !== '' ? '<span class="muted small">' . e((string)$knoten['hinweis']) . '</span>' : '' ?>
          <?= $knoten['auto'] ? '<span class="badge badge--outline">aus dem Modul</span>' : '' ?>
          <?= $knoten['nur_leitung'] ? '<span class="badge badge--muted" title="Diese Kategorie und alles darunter sieht nur die Leitung">nur Leitung</span>' : '' ?>
          <?php else: ?>Alle Kategorien<?php endif; ?></h2>
        <div class="btnrow">
          <?php if ($knoten && $knoten['auto'] && $knoten['url'] !== ''): ?>
            <a class="btn btn--sec btn--sm" href="<?= e((string)$knoten['url']) ?>">Im Modul öffnen</a>
          <?php endif; ?>
          <?php if ($eigen && $verwalten): ?>
            <a class="btn btn--sec btn--sm" href="<?= e(url('ablage_kategorie_edit', ['id' => $knoten['id']])) ?>">Bearbeiten</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($knoten && $knoten['beschreibung'] !== ''): ?><p class="small" style="margin-top:0"><?= e((string)$knoten['beschreibung']) ?></p><?php endif; ?>

      <?php if ($kinder): ?>
        <div class="akat">
          <?php foreach ($kinder as $kk): $n = $baum['knoten'][$kk]; ?>
            <a class="akat__eintrag" href="<?= e(url('ablage', ['k' => $kk])) ?>">
              <span class="akat__icon" aria-hidden="true"><?= $n['auto'] ? '🔒' : ($n['nur_leitung'] ? '👁' : '📁') ?></span>
              <span class="akat__name"><?= e((string)$n['name']) ?><?= $n['hinweis'] !== '' ? ' <span class="muted small">' . e((string)$n['hinweis']) . '</span>' : '' ?></span>
              <span class="akat__zahl"><?= (int)$n['gesamt'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!$knoten && !$hatEigene): ?>
        <p class="small muted" style="margin-bottom:0">Noch keine eigene Kategorie.<?= $verwalten ? ' Mit „+ Kategorie" entsteht die erste – etwa Verwaltung, Ausbildung oder Verträge; darunter beliebig viele Unterkategorien.' : ' Die Leitung legt sie an.' ?></p>
      <?php endif; ?>

      <?php if ($knoten): ?>
        <?php if ($dateien): ?>
          <h3 style="margin:1rem 0 .2rem">Dokumente <span class="muted small"><?= count($dateien) ?></span></h3>
          <div class="doclist"><?php foreach ($dateien as $d): ?><?= $zeile($d, false) ?><?php endforeach; ?></div>
        <?php elseif (!$kinder): ?>
          <div class="empty">Hier liegt noch nichts.</div>
        <?php endif; ?>

        <?php if ($knoten['auto']): ?>
          <p class="small muted" style="margin:.8rem 0 0">Diese Kategorie spiegelt die Ablage eines Moduls. Hochladen und Entfernen geht dort<?= $knoten['url'] !== '' ? ' – <a href="' . e((string)$knoten['url']) . '">im Modul öffnen</a>' : '' ?>;
            Zusammenfassung und Schlagwörter lassen sich auch hier anpassen.</p>
        <?php elseif ($hochladen): ?>
          <details class="mt"<?= $dateien ? '' : ' open' ?>>
            <summary>Dokument ablegen</summary>
            <form method="post" action="<?= e(url('ablage', ['k' => $key])) ?>" class="form" enctype="multipart/form-data" style="margin-top:.8rem">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="hochladen">
              <div class="grid2">
                <div class="field">
                  <label for="dateien">Dateien</label>
                  <input type="file" id="dateien" name="dateien[]" multiple required>
                  <small class="muted">Mehrere auf einmal möglich, je höchstens <?= (int)$maxMb ?> MB. Erlaubt: <?= e(implode(', ', $typen)) ?>.</small>
                </div>
                <div class="field">
                  <label for="titel">Bezeichnung <span class="muted">(freiwillig)</span></label>
                  <input type="text" id="titel" name="titel" maxlength="200" placeholder="sonst der Dateiname">
                </div>
              </div>
              <div class="btnrow"><button class="btn" type="submit">Ablegen</button></div>
              <p class="small muted" style="margin:.5rem 0 0"><?= function_exists('ki_modul_aktiv') && ki_modul_aktiv('ablage')
                ? 'Jedes Dokument geht danach durch die KI im eigenen Netz und bekommt eine Zusammenfassung und Schlagwörter.'
                : 'Die KI-Auswertung ist ausgeschaltet – Schlagwörter lassen sich am Dokument von Hand eintragen.' ?></p>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if (!$knoten && $dateien): ?>
      <section class="card">
        <div class="card__head"><h2>Zuletzt abgelegt</h2></div>
        <div class="doclist"><?php foreach ($dateien as $d): ?><?= $zeile($d, true) ?><?php endforeach; ?></div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>
