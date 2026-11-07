<?php
/** @var array $verfuegbar @var string $listeFehler @var array $ausgewaehlt @var string $bundesland @var int $intervall
 *  @var int $letzterAbruf @var int $anzahl @var string $fehler @var string $hinweis */
$fremde = array_diff($ausgewaehlt, array_keys($verfuegbar));
?>
<div class="pagehead">
  <div>
    <h1>Kalender</h1>
    <p>Feiertage des Bundeslands und Kalender aus Home Assistant – etwa den Dienstplan, die Schulferien oder den
      Kalender der Jugendgruppe. Was hier ausgewählt ist, erscheint im Kalender für alle.</p>
  </div>
  <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="speichern">
  <h2>Feiertage</h2>
  <div class="field">
    <label for="bundesland">Bundesland</label>
    <select id="bundesland" name="bundesland" style="max-width:20rem">
      <?php foreach (KALENDER_BUNDESLAENDER as $k => $name): ?>
        <option value="<?= e($k) ?>"<?= $bundesland === $k ? ' selected' : '' ?>><?= e($name) ?></option>
      <?php endforeach; ?>
    </select>
    <small>Die gesetzlichen Feiertage werden gerechnet, ohne Abruf – auch für kommende Jahre.</small>
  </div>

  <h2 class="mt">Kalender aus Home Assistant</h2>
  <?php if ($listeFehler !== ''): ?>
    <div class="alert alert--warn">Home Assistant antwortet nicht: <?= e($listeFehler) ?><br>
      <span class="small">Kalender lassen sich unten trotzdem von Hand eintragen (z. B. <span class="mono">calendar.dienstplan</span>).</span></div>
  <?php elseif (!$verfuegbar): ?>
    <div class="empty">Home Assistant kennt keine Kalender. Eine Kalender-Integration (lokaler Kalender, CalDAV, Google …) einrichten, dann erscheinen sie hier.</div>
  <?php else: ?>
    <div class="field">
      <?php foreach ($verfuegbar as $entity => $name): ?>
        <label class="wahl"><input type="checkbox" name="entitaeten[]" value="<?= e($entity) ?>"<?= in_array($entity, $ausgewaehlt, true) ? ' checked' : '' ?>>
          <?= e($name) ?> <span class="mono muted small"><?= e($entity) ?></span></label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="grid2">
    <div class="field">
      <label for="weitere">Weitere Kalender (Entitäten, mit Komma)</label>
      <input type="text" id="weitere" name="weitere" value="<?= e(implode(', ', $fremde)) ?>" placeholder="calendar.schulferien, calendar.dienstplan">
    </div>
    <div class="field">
      <label for="intervall">Abruf alle … Minuten</label>
      <input type="number" id="intervall" name="intervall" min="5" max="1440" value="<?= (int)$intervall ?>" style="max-width:8rem">
      <small>Geholt werden 30 Tage zurück und ein Jahr voraus.</small>
    </div>
  </div>
  <div class="btnrow"><button class="btn" type="submit">Speichern</button></div>
</form>

<section class="card">
  <div class="card__head">
    <h2>Stand</h2>
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <input type="hidden" name="action" value="abrufen">
      <button class="btn btn--sec btn--sm" type="submit"<?= $ausgewaehlt ? '' : ' disabled' ?>>Jetzt abrufen</button></form>
  </div>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Ausgewählt</div><div class="dl__value"><?= count($ausgewaehlt) ?> Kalender</div></div>
    <div class="dl__item"><div class="dl__label">Einträge im Zwischenspeicher</div><div class="dl__value"><?= (int)$anzahl ?></div></div>
    <div class="dl__item"><div class="dl__label">Letzter Abruf</div><div class="dl__value"><?= $letzterAbruf > 0 ? e(de_datetime(date('Y-m-d H:i:s', $letzterAbruf))) : 'noch nie' ?></div></div>
  </dl>
</section>

<?php $icsAktiv ??= false; $icsAdresse ??= ''; $icsOptionen ??= []; $icsLetzter ??= 0; $icsIp ??= ''; $fachgruppen ??= []; ?>
<section class="card" id="ics">
  <div class="card__head">
    <h2>Kalender-Abo (ICS) für Home Assistant und Geräte</h2>
    <span class="badge<?= $icsAktiv ? '' : ' badge--muted' ?>"<?= $icsAktiv ? ' style="background:#15803d"' : '' ?>><?= $icsAktiv ? 'eingeschaltet' : 'aus' ?></span>
  </div>
  <p class="small">Der Kalender des OV als Abonnement im Standardformat: Home Assistant holt ihn mit der eingebauten Integration
    <strong>„Remote Calendar"</strong> (Einstellungen → Integrationen → Hinzufügen → Remote Calendar, Adresse eintragen), ebenso Outlook,
    Google Kalender, Thunderbird und das Handy. Die Adresse ist ohne Anmeldung erreichbar und durch ein Token geschützt – wer die Adresse kennt,
    liest den Kalender. Darum: nur an Geräte und Konten geben, denen man das anvertraut, und bei Verdacht das Token erneuern.</p>

  <?php if ($icsAktiv): ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Adresse</div>
        <div class="dl__value mono small"><span data-geheim="<?= e($icsAdresse) ?>" style="word-break:break-all"><?= e(preg_replace('/token=[0-9a-f]+/', 'token=' . str_repeat('•', 16), $icsAdresse)) ?></span>
          <span class="btnrow" style="display:inline-flex;gap:.3rem;margin-left:.4rem;vertical-align:middle">
            <button type="button" class="btn btn--sec btn--sm" data-geheim-zeigen data-aus="verbergen">Adresse zeigen</button>
            <button type="button" class="btn btn--sec btn--sm" data-geheim-kopieren>Adresse kopieren</button>
          </span>
          <?php if (!str_starts_with($icsAdresse, 'http')): ?><div class="muted">Ohne Basisadresse (Einstellungen → Home Assistant → „Adresse der Anwendung") steht hier nur der Pfad – davor gehört die Adresse des Add-ons über seinen Port, nicht die Ingress-Adresse.</div><?php endif; ?></div></div>
      <?php if ($fachgruppen): ?>
        <div class="dl__item"><div class="dl__label">Je Fachgruppe</div>
          <div class="dl__value small">An die Adresse <span class="mono">&amp;fachgruppe=ID</span> anhängen – dann kommen nur OV, Funktionen und diese Gruppe:
            <?= e(implode(', ', array_map(static fn($fg) => $fg['label'] . ' = ' . (int)$fg['id'], $fachgruppen))) ?></div></div>
      <?php endif; ?>
      <div class="dl__item"><div class="dl__label">Zuletzt abgerufen</div>
        <div class="dl__value"><?= $icsLetzter > 0 ? e(de_datetime(date('Y-m-d H:i:s', $icsLetzter))) . ($icsIp !== '' ? ' von ' . e($icsIp) : '') : 'noch nie' ?></div></div>
    </dl>
  <?php endif; ?>

  <form method="post" class="form" style="margin-top:.6rem"><?= csrf_field() ?>
    <input type="hidden" name="action" value="ics_optionen">
    <fieldset>
      <legend>Was hinausgeht</legend>
      <p class="small muted" style="margin:0 0 .4rem">Persönliche Termine (Ziel Person) gehen nie hinaus, ebenso keine Gästelisten, Tagesordnungen oder Namen.</p>
      <?php foreach (['termine' => 'Termine für OV, Fachgruppen und Funktionen', 'veranstaltungen' => 'Veranstaltungen', 'besprechungen' => 'Besprechungen',
                      'fristen' => 'Fahrzeugfristen (HU, SP, UVV) als ganztägige Einträge', 'beschreibung' => 'Beschreibungen mitgeben (sonst nur Titel, Zeit und Ort)'] as $key => $label): ?>
        <div class="field field--check">
          <input type="checkbox" id="ics_<?= e($key) ?>" name="ics_<?= e($key) ?>" value="1" <?= !empty($icsOptionen[$key]) ? 'checked' : '' ?>>
          <label for="ics_<?= e($key) ?>"><?= e($label) ?></label>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <div class="btnrow"><button class="btn btn--sec btn--sm" type="submit">Inhalt speichern</button></div>
  </form>

  <div class="btnrow" style="margin-top:.6rem">
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <input type="hidden" name="action" value="ics_neu">
      <button class="btn btn--sm" type="submit"<?= $icsAktiv ? ' data-confirm="Neues Token erzeugen? Die bisherige Adresse hört sofort auf zu funktionieren."' : '' ?>><?= $icsAktiv ? 'Token erneuern' : 'Abo einschalten (Token erzeugen)' ?></button></form>
    <?php if ($icsAktiv): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="ics_weg">
        <button class="btn btn--sec btn--sm" type="submit" data-confirm="Kalender-Abo abschalten? Alle Geräte verlieren den Kalender.">Abo abschalten</button></form>
    <?php endif; ?>
  </div>
  <p class="small muted" style="margin:.6rem 0 0">Zur Sicherheit: Das Token hat 64 Zeichen und wird in konstanter Zeit verglichen; nach zehn Fehlversuchen
    von einer Adresse ist diese so lange gesperrt wie bei der Anmeldung. Der Abruf legt keine Sitzung an, wird nicht zwischengespeichert und bleibt
    Suchmaschinen verborgen. Beim Import aus Home Assistant werden Einträge aus diesem Abo übersprungen, damit nichts im Kreis läuft.</p>
</section>
