<?php
/**
 * Abschnitt im Formular von Termin, Veranstaltung und Besprechung:
 * in Divera 24/7 als Termin anlegen, Empfänger, Push, Rückmeldung.
 * @var string $typ   termin | veranstaltung | besprechung
 * @var ?int   $refId id des Eintrags, null beim Anlegen
 */
if (!function_exists('divera_termine_aktiv') || !divera_termine_aktiv()) {
    return;
}
$refId = isset($refId) ? (int)$refId : null;
$eintrag = $refId ? divera_termin_eintrag($typ, $refId) : null;
$o = $eintrag['optionen'] ?? divera_termin_optionen_normieren(['empfaenger' => 'alle', 'push' => setting_bool('divera_termine_push', true), 'rueckmeldung' => true, 'text' => false]);
$an = $eintrag !== null && (string)$eintrag['status'] !== 'archiviert';
if (isset($_POST['divera_feld'])) {
    $an = post_bool('divera_an') === 1;
    $o = divera_termin_optionen_normieren(['empfaenger' => post_str('divera_empfaenger', 'alle'), 'gruppen' => (array)($_POST['divera_gruppen'] ?? []),
        'push' => post_bool('divera_push'), 'rueckmeldung' => post_bool('divera_rueckmeldung'), 'text' => post_bool('divera_text')]);
}
$gruppen = divera_gruppen();
$gruppenFehler = state_get('divera_gruppen_fehler', '');
$stand = is_array($eintrag['stand'] ?? null) ? $eintrag['stand'] : [];
?>
<section class="card" id="divera">
  <div class="card__head"><h2>Divera 24/7</h2>
    <?php if ($eintrag): ?>
      <span class="small <?= (string)$eintrag['status'] === 'fehler' ? '' : 'muted' ?>" style="<?= (string)$eintrag['status'] === 'fehler' ? 'color:var(--warn)' : '' ?>">
        <?= match ((string)$eintrag['status']) {
            'gesendet'   => 'in Divera' . (!empty($eintrag['divera_id']) ? ' (#' . (int)$eintrag['divera_id'] . ')' : '') . (!empty($eintrag['gesendet_am']) ? ', zuletzt ' . e(de_datetime((string)$eintrag['gesendet_am'])) : ''),
            'fehler'     => 'noch nicht angekommen: ' . e((string)$eintrag['fehler']),
            'archiviert' => 'in Divera archiviert',
            default      => 'wird beim nächsten Abruf angelegt',
        } ?><?= $stand ? ' · ' . e(divera_termin_stand_text($stand)) : '' ?></span>
    <?php endif; ?>
  </div>
  <input type="hidden" name="divera_feld" value="1">
  <div class="field field--check">
    <input type="checkbox" id="divera_an" name="divera_an" value="1" <?= $an ? 'checked' : '' ?>>
    <label for="divera_an">Als Termin in Divera 24/7 <?= $eintrag ? 'führen' : 'anlegen' ?></label>
  </div>
  <p class="small muted" style="margin:-.4rem 0 .6rem">Beim Speichern wird der Termin in Divera angelegt oder nachgezogen; Haken weg, Absage oder Löschen archiviert ihn dort.
    Unsere Kennung steht als Fremdschlüssel in Divera, damit nichts doppelt entsteht.</p>
  <div class="grid2">
    <div class="field">
      <label>Empfänger</label>
      <label class="field--check" style="display:flex;gap:.4rem;align-items:center"><input type="radio" name="divera_empfaenger" value="alle" <?= $o['empfaenger'] === 'alle' ? 'checked' : '' ?>> alle der Einheit</label>
      <label class="field--check" style="display:flex;gap:.4rem;align-items:center"><input type="radio" name="divera_empfaenger" value="gruppen" <?= $o['empfaenger'] === 'gruppen' ? 'checked' : '' ?>> ausgewählte Gruppen</label>
      <?php if ($gruppen): ?>
        <div style="margin:.3rem 0 0 1.4rem;display:grid;gap:.15rem">
          <?php foreach ($gruppen as $gid => $gname): ?>
            <label class="small" style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" name="divera_gruppen[]" value="<?= (int)$gid ?>" <?= in_array((int)$gid, $o['gruppen'], true) ? 'checked' : '' ?>> <?= e((string)$gname) ?></label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <small class="muted">Keine Gruppen von Divera bekommen<?= $gruppenFehler !== '' ? ' (' . e($gruppenFehler) . ')' : '' ?> – dann gehen Termine an alle der Einheit.</small>
      <?php endif; ?>
    </div>
    <div class="field">
      <label>Versand</label>
      <div class="field--check" style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" id="divera_push" name="divera_push" value="1" <?= $o['push'] ? 'checked' : '' ?>><label for="divera_push">Push-Nachricht an die App</label></div>
      <div class="field--check" style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" id="divera_rueckmeldung" name="divera_rueckmeldung" value="1" <?= $o['rueckmeldung'] ? 'checked' : '' ?>><label for="divera_rueckmeldung">Rückmeldung erbitten (Ja, Unsicher, Nein)</label></div>
      <div class="field--check" style="display:flex;gap:.4rem;align-items:center"><input type="checkbox" id="divera_text" name="divera_text" value="1" <?= $o['text'] ? 'checked' : '' ?>><label for="divera_text">Beschreibung mitschicken</label></div>
      <small class="muted">Ohne Haken bei der Beschreibung gehen nur Titel, Zeit und Ort nach Divera.</small>
    </div>
  </div>
</section>
