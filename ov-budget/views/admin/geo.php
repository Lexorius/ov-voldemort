<?php
/** @var string $fehler @var string $hinweis @var bool $aktiv @var ?array $heim @var array $plaetze
 *  @var array $status @var array $fehlerliste @var int $pause @var string $osrmUrl @var string $nominatimUrl */
?>
<div class="pagehead">
  <div>
    <h1>Entfernungen <span class="badge badge--warn">!! TEST !!</span></h1>
    <p>Luftlinie und Fahrstrecke von der Unterkunft zu Terminen, Veranstaltungen und THW-Dienststellen.
       Adressen übersetzt Nominatim (OpenStreetMap), Strecken rechnet der öffentliche OSRM-Demoserver –
       beides <strong>ohne Gewähr</strong>, deshalb Testbetrieb.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_settings', ['group' => 'Entfernungen (!! TEST !!)'])) ?>">Einstellungen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<div class="alert alert--warn"><strong>!! TEST !!</strong> Die Dienste sind öffentlich und kostenlos, dafür ohne Zusage:
  der OSRM-Demoserver kann jederzeit langsam sein oder wegfallen, Nominatim verlangt wenige Anfragen.
  Darum geht genau <strong>eine Anfrage je Abruf</strong> hinaus, und nur alle <?= (int)$pause ?> Sekunden.
  Jede Adresse und jede Strecke wird nur einmal angefragt und dann gemerkt.</div>

<?php if (!$aktiv): ?>
  <div class="alert alert--warn">Die Entfernungsrechnung ist <strong>ausgeschaltet</strong>. Es wird nichts angefragt; schon
    gemerkte Entfernungen bleiben sichtbar. Einschalten in den
    <a href="<?= e(url('admin_settings', ['group' => 'Entfernungen (!! TEST !!)'])) ?>">Einstellungen</a>.</div>
<?php endif; ?>

<div class="grid2">
  <div class="card">
    <div class="card__head"><h2>Ausgangspunkt</h2></div>
    <p class="small muted">Von welchem Platz aus gerechnet wird – die Unterkunft. Zur Auswahl stehen Plätze mit GPS-Koordinaten
       (Verwaltung → Stell- und Lagerplätze → Lage).</p>
    <?php if (!$plaetze): ?>
      <div class="empty">Kein Platz hat Koordinaten. Erst der Unterkunft unter Stell- und Lagerplätze die Lage geben.</div>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin_geo')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="heim">
        <div class="field"><label for="standort_id">Unterkunft</label>
          <select id="standort_id" name="standort_id">
            <option value="0">– nicht gewählt –</option>
            <?php foreach ($plaetze as $p): ?>
              <option value="<?= (int)$p['id'] ?>"<?= $heim && (int)$heim['id'] === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['pfad']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="btnrow"><button class="btn" type="submit">Übernehmen</button></div>
      </form>
    <?php endif; ?>
    <dl class="dl" style="margin-top:.6rem">
      <div class="dl__item"><div class="dl__label">Adressen (Nominatim)</div><div class="dl__value"><?= e($nominatimUrl) ?></div></div>
      <div class="dl__item"><div class="dl__label">Strecken (OSRM)</div><div class="dl__value"><?= e($osrmUrl) ?> <span class="badge badge--warn">Test</span></div></div>
      <div class="dl__item"><div class="dl__label">Pause</div><div class="dl__value"><?= (int)$pause ?> Sekunden zwischen zwei Anfragen</div></div>
    </dl>
  </div>

  <div class="card">
    <div class="card__head"><h2>Warteschlange</h2></div>
    <table class="data">
      <thead><tr><th></th><th style="text-align:right">offen</th><th style="text-align:right">fertig</th><th style="text-align:right">fehlgeschlagen</th></tr></thead>
      <tbody>
        <tr><td>Adressen</td><td style="text-align:right"><?= (int)$status['adressen']['offen'] ?></td><td style="text-align:right"><?= (int)$status['adressen']['fertig'] ?></td><td style="text-align:right"><?= (int)$status['adressen']['fehler'] ?></td></tr>
        <tr><td>Strecken</td><td style="text-align:right"><?= (int)$status['routen']['offen'] ?></td><td style="text-align:right"><?= (int)$status['routen']['fertig'] ?></td><td style="text-align:right"><?= (int)$status['routen']['fehler'] ?></td></tr>
      </tbody>
    </table>
    <p class="small muted">
      <?php if ($status['letzte_anfrage'] > 0): ?>Letzte Anfrage <?= e(date('d.m.Y H:i:s', (int)$status['letzte_anfrage'])) ?>.<?php endif; ?>
      <?= $status['naechste_in'] > 0 ? sprintf('Nächste frühestens in %d Sekunden.', (int)$status['naechste_in']) : 'Die nächste darf sofort.' ?>
      Der minütliche Abruf nimmt sich jeweils eine Adresse oder eine Strecke vor – Adressen zuerst.
    </p>
    <form method="post" action="<?= e(url('admin_geo')) ?>" class="btnrow">
      <?= csrf_field() ?>
      <button class="btn btn--sec" type="submit" name="action" value="anfrage"<?= $aktiv && $heim ? '' : ' disabled' ?>>Eine Anfrage jetzt</button>
      <button class="btn btn--sec" type="submit" name="action" value="dienststellen"<?= $aktiv && $heim ? '' : ' disabled' ?> title="Anschriften aller aktiven THW-Dienststellen in die Reihe stellen">Dienststellen vormerken</button>
      <?php if ((int)$status['adressen']['fehler'] + (int)$status['routen']['fehler'] > 0): ?>
        <button class="btn btn--sec" type="submit" name="action" value="erneut">Fehlgeschlagene erneut</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($fehlerliste): ?>
<div class="card">
  <div class="card__head"><h2>Adressen, die nicht gefunden wurden</h2><span class="muted small"><?= count($fehlerliste) ?></span></div>
  <table class="data">
    <thead><tr><th>Adresse</th><th>Grund</th><th>Versuche</th><th>Wann</th></tr></thead>
    <tbody>
    <?php foreach ($fehlerliste as $a): ?>
      <tr><td><?= e((string)$a['text']) ?></td><td class="small"><?= e((string)$a['fehler']) ?></td><td class="small"><?= (int)$a['versuche'] ?></td><td class="small"><?= $a['erledigt_am'] ? e(de_datetime((string)$a['erledigt_am'])) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="small muted">Meist hilft eine genauere Schreibweise: Straße mit Hausnummer, Postleitzahl und Ort.</p>
</div>
<?php endif; ?>

<div class="card">
  <div class="card__head"><h2>Wo die Entfernung erscheint</h2></div>
  <ul class="small">
    <li><strong>Terminseite</strong> neben „Navigation starten": zum ersten Platz mit Lage, sonst zum freien Ort, sonst zur Anschrift der THW-Dienststelle.</li>
    <li><strong>THW-Kontakte</strong>: hinter der Anschrift jeder Dienststelle.</li>
    <li><strong>Veranstaltung</strong>: unter dem Ort.</li>
  </ul>
  <p class="small muted">Beim ersten Aufruf steht dort „wird ermittelt …" – die Anfrage geht in die Reihe; sobald sie dran war, steht die Zahl.</p>
</div>
