<?php
/** @var array $bibliotheken @var array $dienste @var array $symbole */
$symbole ??= [];
?>
<div class="pagehead">
  <div>
    <h1>Bibliotheken und Lizenzen</h1>
    <p>OV-Multitool bringt einige wenige Bibliotheken anderer mit. Hier steht, von wem sie stammen, unter welcher Lizenz sie stehen und woher sie kommen –
      mit Dank an alle, die sie geschrieben haben.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('neu')) ?>">Was ist neu</a>
</div>

<?php foreach ($bibliotheken as $key => $b): ?>
  <section class="card" id="<?= e((string)$key) ?>">
    <div class="card__head">
      <h2><?= e((string)$b['name']) ?></h2>
      <span class="badge badge--outline"><?= e((string)$b['lizenz']) ?></span>
    </div>
    <p><?= e((string)$b['wofuer']) ?></p>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Urheber</div><div class="dl__value"><?= e((string)$b['urheber']) ?></div></div>
      <div class="dl__item"><div class="dl__label">Lizenz</div><div class="dl__value"><?= e((string)$b['lizenzname']) ?> (<?= e((string)$b['lizenz']) ?>)</div></div>
      <div class="dl__item"><div class="dl__label">Quelle</div>
        <div class="dl__value"><a href="<?= e((string)$b['quelle']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$b['quelle']) ?></a></div></div>
      <div class="dl__item"><div class="dl__label">Stand</div><div class="dl__value"><?= e((string)$b['stand']) ?></div></div>
      <div class="dl__item"><div class="dl__label">Mitgelieferte Datei</div>
        <div class="dl__value"><?php if ($b['vorhanden']): ?><a class="mono small" href="<?= e((string)$b['datei_url']) ?>" target="_blank" rel="noopener">assets/<?= e((string)$b['datei']) ?></a>
          <span class="muted small">· <?= e(bytes_human((int)$b['groesse'])) ?></span><?php else: ?><span class="muted">fehlt in dieser Installation</span><?php endif; ?></div></div>
      <div class="dl__item"><div class="dl__label">Änderungen</div><div class="dl__value"><?= e((string)$b['aenderung']) ?></div></div>
    </dl>
    <?php if ((string)$b['text'] !== ''): ?>
      <details>
        <summary>Lizenztext</summary>
        <pre class="lizenz__text"><?= e((string)$b['text']) ?></pre>
        <p class="small muted">Als Datei: <a class="mono" href="<?= e((string)$b['lizenz_url']) ?>" target="_blank" rel="noopener">assets/<?= e((string)$b['lizenztext']) ?></a></p>
      </details>
    <?php else: ?>
      <p class="small muted">Der Lizenztext fehlt in dieser Installation – er steht unter der genannten Quelle.</p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<section class="card" id="dienste">
  <h2>Dienste und Daten</h2>
  <p class="small muted">Nicht mitgeliefert, aber zur Laufzeit genutzt – jeweils nur, wenn die Funktion eingeschaltet ist.</p>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Dienst</th><th>Wofür</th><th>Hinweis</th></tr></thead>
      <tbody>
      <?php foreach ($dienste as $d): ?>
        <tr>
          <td class="nowrap"><a href="<?= e((string)$d['quelle']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$d['name']) ?></a></td>
          <td class="small"><?= e((string)$d['wofuer']) ?></td>
          <td class="small"><?= e((string)$d['hinweis']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($symbole): ?>
<section class="card" id="symbole">
  <h2>Symbole der Kartenbibliothek</h2>
  <p class="small muted">Mitgeliefert wird kein Symbol. Diese wurden in dieser Installation aus fremden Quellen geholt; Quelle und Lizenz stehen an jedem Symbol.</p>
  <div class="tablewrap">
    <table class="data">
      <thead><tr><th>Quelle</th><th>Lizenz</th><th class="num">Symbole</th></tr></thead>
      <tbody>
      <?php foreach ($symbole as $s): ?>
        <tr><td class="mono small"><?= e((string)$s['quelle']) ?></td><td><?= e((string)$s['lizenz'] !== '' ? (string)$s['lizenz'] : 'nicht angegeben') ?></td><td class="num"><?= (int)$s['anzahl'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<p class="small muted">Fehlt hier etwas oder stimmt eine Angabe nicht? Bitte der Administration melden – die Liste steht in <span class="mono">src/lib/lizenzen.php</span>.</p>
