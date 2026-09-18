<?php
/** @var array $form @var array $map @var array $felder @var array $beispiel
 *  @var string $fehler @var string $hinweis @var array $vorschau */
$ziel = divera_ziel($form);
$thema = $ziel === 'thema';
$targets = divera_map_targets($ziel);
?>
<div class="pagehead">
  <div>
    <h1>Feldzuordnung</h1>
    <p><?= e($form['name']) ?> · <?= e(DIVERA_ZIELE[$ziel]) ?> · ID <span class="mono"><?= e($form['form_id']) ?></span></p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin_divera')) ?>">Zurück</a>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--info"><?= e($hinweis) ?></div><?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2>Felder aus Divera einlesen</h2>
    <form method="post" class="inline-form" action="<?= e(url('admin_divera_form', ['id' => $form['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="probe">
      <button class="btn" type="submit">Formularfelder abrufen</button>
    </form>
  </div>
  <?php if (!$felder): ?>
    <p class="small muted">Noch keine Felder bekannt. Der Abruf liest die vorhandenen Einträge und sammelt daraus
      die Feldnamen – danach lassen sie sich unten bequem auswählen.</p>
  <?php else: ?>
    <div class="chips"><?php foreach ($felder as $f): ?><span class="chip mono"><?= e($f) ?></span><?php endforeach; ?></div>
    <?php if ($beispiel): ?>
      <h3 class="mt">Beispieleintrag</h3>
      <pre class="raw"><?= e(json_encode($beispiel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    <?php endif; ?>
  <?php endif; ?>
</div>

<form method="post" class="card form" action="<?= e(url('admin_divera_form', ['id' => $form['id']])) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <div class="field" style="max-width:26rem">
    <label for="ziel">Einträge übernehmen als</label>
    <select id="ziel" name="ziel">
      <?php foreach (DIVERA_ZIELE as $k => $l): ?>
        <option value="<?= e($k) ?>"<?= $k === $ziel ? ' selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($thema): ?>
      <p class="small muted">Jeder Eintrag wird ein Thema im Themenspeicher – von dort setzt die Leitung es auf
        eine Tagesordnung. Stimmt der Name des Einreichers mit einem Benutzer überein, gilt das Thema als von dieser
        Person eingebracht.</p>
    <?php endif; ?>
    <p class="small muted">Beim Wechsel werden die Felder neu vorgeschlagen.</p>
  </div>

  <h2>Zuordnung Divera-Feld → <?= $thema ? 'Themen-Feld' : 'Wunsch-Feld' ?></h2>
  <p class="small muted">Links steht das Feld in der Anwendung, rechts der Name des Divera-Feldes. Groß- und
     Kleinschreibung ist egal; leere Zeilen werden ignoriert. Nicht zugeordnete Divera-Felder landen gesammelt
     in der Beschreibung – es geht also nichts verloren.</p>

  <datalist id="divera-felder">
    <?php foreach ($felder as $f): ?><option value="<?= e($f) ?>"></option><?php endforeach; ?>
  </datalist>

  <div class="grid2">
    <?php foreach ($targets as $key => $label): ?>
      <div class="field">
        <label for="map_<?= e($key) ?>"><?= e($label) ?></label>
        <input type="text" id="map_<?= e($key) ?>" name="map_<?= e($key) ?>" list="divera-felder"
               value="<?= e((string)($map[$key] ?? '')) ?>" placeholder="Name des Divera-Feldes">
      </div>
    <?php endforeach; ?>
  </div>

  <fieldset>
    <legend>Vorgaben für übernommene <?= $thema ? 'Themen' : 'Wünsche' ?></legend>
    <div class="grid3">
      <div class="field">
        <label for="name">Anzeigename des Formulars</label>
        <input type="text" id="name" name="name" value="<?= e($form['name']) ?>">
      </div>
      <div class="field">
        <label for="default_status_id">Status</label>
        <select id="default_status_id" name="default_status_id">
          <?= list_options($thema ? 'tp_status' : 'wunsch_status', (int)($form['default_status_id'] ?? 0), $thema ? 'Standard' : 'Standard aus den Einstellungen') ?>
        </select>
      </div>
      <div class="field">
        <label for="default_fachgruppe_id">Fachgruppe, falls nicht erkannt</label>
        <select id="default_fachgruppe_id" name="default_fachgruppe_id">
          <?= list_options('fachgruppe', (int)($form['default_fachgruppe_id'] ?? 0), 'keine') ?>
        </select>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="auto_import" name="auto_import" value="1" <?= (int)$form['auto_import'] ? 'checked' : '' ?>>
        <label for="auto_import">Beim automatischen Abruf (Cron) berücksichtigen</label>
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>Bearbeitungsstand an Divera zurückmelden</legend>
    <div class="field field--check">
      <input type="checkbox" id="status_sync" name="status_sync" value="1" <?= (int)($form['status_sync'] ?? 0) ? 'checked' : '' ?>>
      <label for="status_sync">Status der Einträge in Divera setzen</label>
    </div>
    <p class="small muted">
      Übernommen → <strong>Weitergeleitet</strong> ·
      <?php if ($thema): ?>
        auf einer Tagesordnung → <strong>In Bearbeitung</strong> · besprochen, beschlossen oder zurückgezogen → <strong>Abgeschlossen</strong>.
      <?php else: ?>
        Status <span class="mono"><?= e((string)setting('divera_status_bearbeitung', 'freigegeben')) ?></span> → <strong>In Bearbeitung</strong> ·
        <span class="mono"><?= e((string)setting('divera_status_abgeschlossen', 'bestellt')) ?></span> und abschließende Status
        (beschafft, abgelehnt …) → <strong>Abgeschlossen</strong>
        (<a href="<?= e(url('admin_settings', ['group' => 'Divera 24/7'])) ?>">ändern</a>).
      <?php endif; ?>
      Es geht nur vorwärts: Was in Divera schon weiter ist, wird nicht zurückgesetzt. Der Abgleich läuft mit dem
      automatischen Abruf; der persönliche Schlüssel braucht dafür Bearbeitungsrechte am Formular.
    </p>
    <?php if ((int)($form['status_sync'] ?? 0)): ?>
      <button class="btn btn--sec btn--sm" type="submit" name="action" value="status_sync">Status jetzt abgleichen</button>
    <?php endif; ?>
  </fieldset>

  <div class="btnrow">
    <button class="btn" type="submit">Zuordnung speichern</button>
    <button class="btn btn--sec" type="submit" name="action" value="preview">Speichern und Vorschau</button>
    <button class="btn btn--ok" type="submit" name="action" value="import"
            data-confirm="Alle noch nicht übernommenen Einträge jetzt als <?= $thema ? 'Themen' : 'Wünsche' ?> anlegen?">Jetzt importieren</button>
  </div>
</form>

<?php if ($vorschau && $thema): ?>
  <div class="card">
    <h2>Vorschau – diese Themen kämen in den Themenspeicher</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Thema</th><th>Fachgruppe</th><th>Priorität</th><th class="num">Minuten</th><th>Eingereicht von</th></tr></thead>
        <tbody>
        <?php foreach ($vorschau as $v): ?>
          <tr>
            <td><?= e($v['titel']) ?></td>
            <td><?= e(list_label((int)($v['fachgruppe_id'] ?? 0), '–')) ?></td>
            <td><?= e(list_label((int)($v['prioritaet_id'] ?? 0), '–')) ?></td>
            <td class="num"><?= $v['dauer_min'] ? (int)$v['dauer_min'] : '–' ?></td>
            <td><?= $v['eingebracht_von'] ? 'Benutzer #' . (int)$v['eingebracht_von'] : e($v['einbringer_name'] ?: '–') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php elseif ($vorschau): ?>
  <div class="card">
    <h2>Vorschau – diese Wünsche würden entstehen</h2>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Bezeichnung</th><th>Fachgruppe</th><th>Dringlichkeit</th><th class="num">Anzahl</th>
                   <th class="num">Netto gesamt</th><th>nice to have</th></tr></thead>
        <tbody>
        <?php foreach ($vorschau as $v): ?>
          <tr>
            <td><?= e($v['bezeichnung']) ?></td>
            <td><?= e(list_label((int)($v['fachgruppe_id'] ?? 0), '–')) ?></td>
            <td><?= e(list_label((int)($v['dringlichkeit_id'] ?? 0), '–')) ?></td>
            <td class="num"><?= e(rtrim(rtrim(number_format((float)$v['anzahl'], 2, ',', '.'), '0'), ',')) ?></td>
            <td class="num"><?= e(money((float)$v['netto_gesamt'], false)) ?></td>
            <td><?= (int)$v['nice_to_have'] ? 'ja' : 'nein' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
