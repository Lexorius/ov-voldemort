<?php
/** @var array $sim @var array $errors @var array $fahrzeuge @var array $fachgruppen @var array $personen */
$isNew = empty($sim['id']);
$karteArt = sim_karte_art((string)($sim['karte_art'] ?? 'mobilfunk'));
$tetra = $karteArt === 'tetra';
$zielTyp = sim_ziel_typ((string)($sim['ziel_typ'] ?? 'ov'));
$zielId = (int)($sim['ziel_id'] ?? 0);
?>
<div class="pagehead">
  <div><h1><?= $isNew ? 'SIM-Karte anlegen' : 'SIM-Karte bearbeiten' ?></h1></div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('sims')) ?>">Abbrechen</a>
  </div>
</div>

<?php foreach ($errors as $f): ?>
  <div class="alert alert--error"><?= e($f) ?></div>
<?php endforeach; ?>

<form method="post" class="form">
  <?= csrf_field() ?>

  <section class="card">
    <h2>Karte</h2>
    <div class="field">
      <label for="karte_art">Kartenwelt</label>
      <select id="karte_art" name="karte_art" data-karte-art>
        <?php foreach (SIM_ARTEN as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === $karteArt ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Mobilfunk hat Rufnummer und Vertrag, eine TETRA-Sicherheitskarte
        stattdessen ISSI und OPTA.</small>
    </div>
    <div class="grid2">
      <div class="field" data-art-feld="mobilfunk"<?= $tetra ? ' hidden' : '' ?>>
        <label for="rufnummer">Rufnummer</label>
        <input type="text" id="rufnummer" name="rufnummer" inputmode="tel"
               value="<?= e((string)($sim['rufnummer'] ?? '')) ?>" placeholder="0151 1234567">
        <small class="muted">Wird international geschrieben (+49 …), genau wie im Kontaktmodul.</small>
      </div>
      <div class="field" data-art-feld="tetra"<?= $tetra ? '' : ' hidden' ?>>
        <label for="issi">ISSI</label>
        <input type="text" id="issi" name="issi" inputmode="numeric" maxlength="16"
               value="<?= e((string)($sim['issi'] ?? '')) ?>" placeholder="z. B. 2621234">
        <small class="muted">Die Teilnehmerkennung der Karte – nur Ziffern.</small>
      </div>
      <div class="field" data-art-feld="tetra"<?= $tetra ? '' : ' hidden' ?>>
        <label for="opta">OPTA</label>
        <input type="text" id="opta" name="opta" maxlength="60"
               value="<?= e((string)($sim['opta'] ?? '')) ?>" placeholder="z. B. NW THW OV MUST 01">
        <small class="muted">Operativ-taktische Adresse, wie sie im Funk erscheint.</small>
      </div>
      <div class="field">
        <label for="iccid">Kartennummer<span data-art-feld="mobilfunk"<?= $tetra ? ' hidden' : '' ?>> (ICCID)</span></label>
        <input type="text" id="iccid" name="iccid" inputmode="numeric" maxlength="30"
               value="<?= e((string)($sim['iccid'] ?? '')) ?>" placeholder="8949…">
        <small class="muted">Steht auf der Karte selbst – hilft beim Sperren.</small>
      </div>
      <div class="field">
        <label for="typ_id">Art</label>
        <select id="typ_id" name="typ_id"><?= list_options('sim_typ', (int)($sim['typ_id'] ?? 0)) ?></select>
      </div>
      <div class="field">
        <label for="status_id">Status</label>
        <select id="status_id" name="status_id"><?= list_options('sim_status', (int)($sim['status_id'] ?? 0)) ?></select>
      </div>
    </div>
    <div class="field">
      <label for="radio_id">Steckt in einem Funkgerät</label>
      <select id="radio_id" name="radio_id">
        <option value="">– keinem –</option>
        <?php foreach (radio_query(['aktiv' => 'alle']) as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)($sim['radio_id'] ?? 0) ? 'selected' : '' ?>>
            <?= e((string)$r['bezeichnung']) ?><?= $r['typ_label'] ? ' · ' . e((string)$r['typ_label']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">Geräte stehen im Funkmodul. Für Router und Tablets reicht das Freitextfeld darunter.</small>
    </div>
    <div class="field">
      <label for="geraet">Steckt in <span class="muted">(freiwillig, Freitext)</span></label>
      <input type="text" id="geraet" name="geraet" maxlength="150"
             value="<?= e((string)($sim['geraet'] ?? '')) ?>" placeholder="z. B. Router GKW 1, Tablet FGr N">
    </div>
  </section>

  <section class="card">
    <h2>Zuordnung</h2>
    <div class="field">
      <label for="ziel_typ">Gehört zu</label>
      <select id="ziel_typ" name="ziel_typ" data-ziel-auswahl>
        <?php foreach (SIM_ZIELE as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === $zielTyp ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <small class="muted">„Ortsverband" heißt: keine feste Zuordnung – etwa eine Karte im Schrank.</small>
    </div>

    <div class="field" data-ziel-feld="fahrzeug"<?= $zielTyp === 'fahrzeug' ? '' : ' hidden' ?>>
      <label for="ziel_fahrzeug">Fahrzeug</label>
      <select id="ziel_fahrzeug" name="ziel_id_fahrzeug">
        <option value="">– bitte wählen –</option>
        <?php foreach ($fahrzeuge as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= $zielTyp === 'fahrzeug' && (int)$v['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$v['bezeichnung']) ?><?= $v['kennzeichen'] ? ' · ' . e((string)$v['kennzeichen']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" data-ziel-feld="fachgruppe"<?= $zielTyp === 'fachgruppe' ? '' : ' hidden' ?>>
      <label for="ziel_fachgruppe">Fachgruppe</label>
      <select id="ziel_fachgruppe" name="ziel_id_fachgruppe">
        <option value="">– bitte wählen –</option>
        <?php foreach ($fachgruppen as $fg): ?>
          <option value="<?= (int)$fg['id'] ?>" <?= $zielTyp === 'fachgruppe' && (int)$fg['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$fg['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" data-ziel-feld="person"<?= $zielTyp === 'person' ? '' : ' hidden' ?>>
      <label for="ziel_person">Person</label>
      <select id="ziel_person" name="ziel_id_person">
        <option value="">– bitte wählen –</option>
        <?php foreach ($personen as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= $zielTyp === 'person' && (int)$u['id'] === $zielId ? 'selected' : '' ?>>
            <?= e((string)$u['display_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <input type="hidden" name="ziel_id" id="ziel_id" value="<?= $zielId ?: '' ?>">

    <div class="field">
      <label for="ausgegeben_am">Ausgegeben am</label>
      <input type="date" id="ausgegeben_am" name="ausgegeben_am"
             value="<?= e((string)($sim['ausgegeben_am'] ?? '')) ?>">
    </div>
  </section>

  <section class="card">
    <h2>Vertrag</h2>
    <div class="field field--check">
      <input type="checkbox" id="hat_vertrag" name="hat_vertrag" value="1"
             data-schalter="vertrag" <?= sim_hat_vertrag($sim) ? 'checked' : '' ?>>
      <label for="hat_vertrag">Diese Karte hat einen Vertrag</label>
    </div>
    <small class="muted" style="margin-top:-.6rem">Aus heißt: keine Angaben zu Anbieter, Tarif,
      Kosten und Laufzeit – etwa bei einer TETRA-Sicherheitskarte oder einer Karte aus dem Bestand
      des Landesverbands.</small>
    <div class="grid2" data-schalter-block="vertrag"<?= sim_hat_vertrag($sim) ? '' : ' hidden' ?>>
      <div class="field">
        <label for="anbieter">Anbieter</label>
        <input type="text" id="anbieter" name="anbieter" maxlength="80"
               value="<?= e((string)($sim['anbieter'] ?? '')) ?>" placeholder="z. B. Telekom">
      </div>
      <div class="field">
        <label for="tarif">Tarif</label>
        <input type="text" id="tarif" name="tarif" maxlength="120"
               value="<?= e((string)($sim['tarif' ] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="datenvolumen">Datenvolumen</label>
        <input type="text" id="datenvolumen" name="datenvolumen" maxlength="40"
               value="<?= e((string)($sim['datenvolumen'] ?? '')) ?>" placeholder="z. B. 10 GB">
      </div>
      <div class="field">
        <label for="kosten_monat">Kosten je Monat</label>
        <input type="text" id="kosten_monat" name="kosten_monat" inputmode="decimal"
               value="<?= $sim['kosten_monat'] !== null && $sim['kosten_monat'] !== ''
                   ? e(num_input((float)$sim['kosten_monat'])) : '' ?>" placeholder="0,00">
      </div>
      <div class="field">
        <label for="vertrag_bis">Vertrag läuft bis</label>
        <input type="date" id="vertrag_bis" name="vertrag_bis"
               value="<?= e((string)($sim['vertrag_bis'] ?? '')) ?>">
        <small class="muted">Leer heißt unbefristet.</small>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>PIN und PUK</h2>
    <div class="field field--check">
      <input type="checkbox" id="hat_pin" name="hat_pin" value="1"
             data-schalter="pin" <?= sim_hat_pin($sim) ? 'checked' : '' ?>>
      <label for="hat_pin">PIN und PUK hier hinterlegen</label>
    </div>
    <p class="small">Sie stehen dann nur der Leitung offen und werden in der Liste verdeckt
      angezeigt. Ohne Haken bleiben die Felder leer – und was schon darin stand, wird beim
      Speichern entfernt.</p>
    <div class="grid2" data-schalter-block="pin"<?= sim_hat_pin($sim) ? '' : ' hidden' ?>>
      <div class="field">
        <label for="pin">PIN</label>
        <input type="text" id="pin" name="pin" maxlength="20" autocomplete="off"
               value="<?= e((string)($sim['pin'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="puk">PUK</label>
        <input type="text" id="puk" name="puk" maxlength="30" autocomplete="off"
               value="<?= e((string)($sim['puk'] ?? '')) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Sonstiges</h2>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($sim['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1"
             <?= (int)($sim['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">Im Bestand</label>
    </div>
    <small class="muted" style="margin-top:-.6rem">Aus heißt: ausgemustert oder zurückgegeben –
      die Karte bleibt zum Nachschlagen erhalten.</small>
    <div class="btnrow">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn btn--sec" href="<?= e(url('sims')) ?>">Abbrechen</a>
    </div>
  </section>
</form>

<?php if (!$isNew): ?>
  <section class="card">
    <h2>SIM-Karte löschen</h2>
    <p class="small">Nur, wenn die Karte nie existiert hat. Sonst besser aus dem Bestand nehmen –
      dann bleibt nachvollziehbar, dass es sie gab.</p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="loeschen">
      <button class="btn btn--danger" type="submit"
              data-confirm="Diese SIM-Karte endgültig löschen?"
              data-confirm2="Bist du wirklich sicher?">Löschen</button>
    </form>
  </section>
<?php endif; ?>
