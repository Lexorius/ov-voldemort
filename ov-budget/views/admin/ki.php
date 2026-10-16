<?php
/** @var string $fehler @var string $hinweis @var ?array $test @var array $status @var array $fehlerliste @var array $gruende
 *  @var bool $aktiv @var string $url @var ?string $urlGrund @var bool $pdftotext @var bool $pdftoppm @var array $module */
$hauptschalter = setting_bool('ki_aktiv', false);
?>
<div class="pagehead">
  <div>
    <h1>KI-Auswertung</h1>
    <p>Ein Ollama im eigenen Netz fasst hochgeladene Dateien zusammen und vergibt Schlagwörter.
       Die Dokumente verlassen das Haus nicht – öffentliche Adressen nimmt die Einstellung nicht an.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('ki_suche')) ?>">Dateisuche</a>
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'KI-Auswertung'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<?php if (!$hauptschalter): ?>
  <div class="alert alert--warn">Die KI-Auswertung ist <strong>ausgeschaltet</strong>. Es wird nichts eingereiht und keine
    Verbindung aufgebaut; vorhandene Zusammenfassungen bleiben sichtbar. Einschalten geht in den
    <a href="<?= e(url('admin_settings', ['group' => 'KI-Auswertung'])) ?>">Einstellungen</a>.</div>
<?php elseif ($urlGrund !== null): ?>
  <div class="alert alert--error">Der Hauptschalter ist an, aber die Adresse taugt nicht: <?= e($urlGrund) ?>
    Solange passiert nichts.</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <div class="card__head"><h2>Verbindung</h2></div>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Adresse</div><div class="dl__value"><?= $url !== '' ? e($url) : '<span class="muted">keine gültige</span>' ?></div></div>
      <div class="dl__item"><div class="dl__label">Textmodell</div><div class="dl__value"><?= e((string)setting('ki_modell', '')) ?: '<span class="muted">–</span>' ?></div></div>
      <div class="dl__item"><div class="dl__label">Vision-Modell</div><div class="dl__value"><?= e((string)setting('ki_vision_modell', '')) ?: '<span class="muted">keins – Bilder und gescannte PDFs bleiben außen vor</span>' ?></div></div>
      <div class="dl__item"><div class="dl__label">PDF-Werkzeuge</div><div class="dl__value">pdftotext <?= $pdftotext ? '✔' : '✘ fehlt' ?> · pdftoppm <?= $pdftoppm ? '✔' : '✘ fehlt' ?></div></div>
      <div class="dl__item"><div class="dl__label">Ablagen</div><div class="dl__value">
        <?php foreach ($module as $m): ?><span class="badge <?= $m['an'] ? '' : 'badge--outline' ?>"><?= e($m['label']) ?><?= $m['an'] ? '' : ' (aus)' ?></span> <?php endforeach; ?>
      </div></div>
    </dl>
    <form method="post" action="<?= e(url('admin_ki')) ?>" class="btnrow" style="margin-top:.6rem">
      <?= csrf_field() ?>
      <button class="btn" type="submit" name="action" value="test"<?= $url === '' ? ' disabled' : '' ?>>Verbindung testen</button>
    </form>
    <?php if ($test && $test['ok']): ?>
      <p class="small" style="margin-top:.6rem">Modelle auf dem Ollama-Rechner:
        <?php foreach ($test['modelle'] as $m): ?><code><?= e($m) ?></code> <?php endforeach; ?>
        <?php if (!$test['modelle']): ?><span class="muted">keine – erst „ollama pull qwen2.5:7b" ausführen</span><?php endif; ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card__head"><h2>Warteschlange</h2></div>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Offen</div><div class="dl__value"><?= (int)$status['offen'] ?></div></div>
      <div class="dl__item"><div class="dl__label">Läuft gerade</div><div class="dl__value"><?= (int)$status['laeuft'] ?></div></div>
      <div class="dl__item"><div class="dl__label">Fertig</div><div class="dl__value"><?= (int)$status['fertig'] ?></div></div>
      <div class="dl__item"><div class="dl__label">Fehlgeschlagen</div><div class="dl__value"><?= (int)$status['fehler'] ?></div></div>
      <div class="dl__item"><div class="dl__label">Zusammenfassungen</div><div class="dl__value"><?= (int)$status['ergebnisse'] ?></div></div>
    </dl>
    <p class="small muted">Der minütliche Abruf (cron.php) nimmt sich jeweils bis zu drei Dateien vor.
       Je nach Rechner dauert eine Datei einige Sekunden bis gut eine Minute.</p>
    <form method="post" action="<?= e(url('admin_ki')) ?>" class="btnrow">
      <?= csrf_field() ?>
      <button class="btn btn--sec" type="submit" name="action" value="abarbeiten"<?= $aktiv ? '' : ' disabled' ?> title="Bis zu drei offene Aufträge jetzt im Vordergrund abarbeiten">Jetzt abarbeiten</button>
      <button class="btn btn--sec" type="submit" name="action" value="alle"<?= $aktiv ? '' : ' disabled' ?> data-confirm="Alle vorhandenen Dateien der eingeschalteten Ablagen einreihen, die noch nicht ausgewertet sind?">Vorhandene Dateien nachholen</button>
      <?php if ((int)$status['fehler'] > 0): ?>
        <button class="btn btn--sec" type="submit" name="action" value="erneut">Fehlgeschlagene erneut</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php $gruende ??= []; if ($gruende): ?>
<div class="card">
  <div class="card__head"><h2>Woran es scheitert</h2><span class="muted small"><?= (int)$status['fehler'] ?> Datei(en)</span></div>
  <table class="data">
    <thead><tr><th>Grund</th><th style="text-align:right">Dateien</th></tr></thead>
    <tbody>
    <?php foreach ($gruende as $g): ?>
      <tr><td><?= e((string)$g['fehler']) ?></td><td style="text-align:right"><?= (int)$g['n'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="small muted">Dauerhafte Gründe (kein Vision-Modell, Dateiart nicht lesbar, Datei fehlt) werden nicht wiederholt.
     Nach dem Beheben – etwa einem eingetragenen Vision-Modell – „Fehlgeschlagene erneut".</p>
</div>
<?php endif; ?>

<?php if ($fehlerliste): ?>
<div class="card">
  <div class="card__head"><h2>Zuletzt fehlgeschlagen</h2><span class="muted small"><?= count($fehlerliste) ?> von <?= (int)$status['fehler'] ?></span></div>
  <table class="data">
    <thead><tr><th>Datei</th><th>Ablage</th><th>Grund</th><th>Versuche</th><th>Wann</th></tr></thead>
    <tbody>
    <?php foreach ($fehlerliste as $a): ?>
      <tr>
        <td><?= $a['eltern'] ? '<a href="' . e($a['eltern']['url']) . '">' . e($a['name']) . '</a>' . ($a['eltern']['text'] !== $a['name'] ? '<div class="small muted">' . e($a['eltern']['text']) . '</div>' : '') : e($a['name']) ?></td>
        <td class="small"><?= e(KI_TYPEN[$a['typ']]['label'] ?? $a['typ']) ?></td>
        <td class="small"><?= e((string)$a['fehler']) ?></td>
        <td class="small"><?= (int)$a['versuche'] ?></td>
        <td class="small"><?= e(de_datetime((string)$a['erledigt_am'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card__head"><h2>So richtet man es ein</h2></div>
  <ol class="small">
    <li>Auf einem Rechner im eigenen Netz <a href="https://ollama.com" target="_blank" rel="noopener">Ollama</a> installieren
        und ein Modell laden: <code>ollama pull qwen2.5:7b</code> (Texte), optional <code>ollama pull llava:7b</code> (Bilder).</li>
    <li>Ollama muss aus dem Netz erreichbar sein (Umgebungsvariable <code>OLLAMA_HOST=0.0.0.0</code>).</li>
    <li>In den <a href="<?= e(url('admin_settings', ['group' => 'KI-Auswertung'])) ?>">Einstellungen</a> die Adresse
        eintragen, Modelle benennen, Hauptschalter an. Dann hier „Verbindung testen".</li>
    <li>Neue Uploads werden ab dann ausgewertet; für den Bestand „Vorhandene Dateien nachholen".</li>
  </ol>
</div>
