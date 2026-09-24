<?php
/** @var array|null $c @var bool $aktiv @var array $fahrzeuge @var ?array $zustand
 *  @var string $fehler @var string $hinweis */
$neu = $c === null;
?>
<div class="pagehead">
  <div>
    <h1><?= $neu ? 'Connector anlegen' : e((string)$c['name']) ?></h1>
    <p>Der Connector nimmt Meldungen von außen entgegen und reicht sie verschlüsselt weiter. Er kann
       sie nicht lesen und weiß auch nicht, zu welchem Fahrzeug oder zu welcher Person sie gehören.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin_connectors')) ?>">Alle Connectoren</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<div class="card">
  <h2>Angaben</h2>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="speichern">
    <div class="grid2">
      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required maxlength="100"
               placeholder="z. B. Webserver des OV"
               value="<?= e((string)($c['name'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="url">Adresse</label>
        <input type="url" id="url" name="url" required placeholder="https://ov.example.de/connector"
               value="<?= e((string)($c['url'] ?? '')) ?>"
               <?= !$neu && connector_gekoppelt($c) ? 'readonly' : '' ?>>
        <small class="muted">Ohne <span class="mono">index.php</span>. Solange die Kopplung steht, lässt
          sie sich nicht ändern.</small>
      </div>
    </div>
    <div class="field">
      <label for="kurz_url">Kurze Adresse für Einladungen <span class="muted">(freiwillig)</span></label>
      <input type="url" id="kurz_url" name="kurz_url" placeholder="https://i.example.de"
             value="<?= e((string)($c['kurz_url'] ?? '')) ?>">
      <small class="muted">Zeigt derselbe Server auch unter einem kurzen Namen, stehen Einladungslinks so
        auf dem Papier: <span class="mono">https://i.example.de/AB12CD</span>. Dafür braucht der
        Webserver eine Umschreibregel auf <span class="mono">index.php?e=&lt;Code&gt;</span> –
        sie steht in der Anleitung des Connectors.</small>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="fuer_fahrzeuge" name="fuer_fahrzeuge" value="1"
             <?= (int)($c['fuer_fahrzeuge'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="fuer_fahrzeuge">Fahrzeuge: Standort melden per QR-Code</label>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="fuer_veranstaltungen" name="fuer_veranstaltungen" value="1"
             <?= (int)($c['fuer_veranstaltungen'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label for="fuer_veranstaltungen">Veranstaltungen: Einladungen und Rückmeldungen</label>
    </div>
    <div class="field field--check">
      <input type="checkbox" id="fuer_bestand" name="fuer_bestand" value="1"
             <?= (int)($c['fuer_bestand'] ?? 0) === 1 ? 'checked' : '' ?>>
      <label for="fuer_bestand">Funkgeräte: „ist am Lagerort" per QR-Code</label>
    </div>
    <small class="muted" style="margin-top:-.6rem">Beides zusammen ist möglich – ein Connector, eine Kopplung.</small>
    <div class="field field--check">
      <input type="checkbox" id="is_active" name="is_active" value="1"
             <?= (int)($c['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="is_active">In Betrieb</label>
    </div>
    <div class="field">
      <label for="notiz">Notiz</label>
      <textarea id="notiz" name="notiz" rows="2"><?= e((string)($c['notiz'] ?? '')) ?></textarea>
    </div>
    <div class="btnrow">
      <button class="btn" type="submit"><?= $neu ? 'Anlegen' : 'Speichern' ?></button>
      <a class="btn btn--sec" href="<?= e(url('admin_connectors')) ?>">Abbrechen</a>
    </div>
  </form>
</div>

<?php if (!$neu): ?>
<div class="card">
  <h2>Kopplung</h2>
  <?php if (!connector_gekoppelt($c)): ?>
    <ol class="small">
      <li>Den Ordner <span class="mono">connector/</span> auf den Webserver legen, das Dokumentenverzeichnis
        auf <span class="mono">connector/public</span> zeigen lassen.</li>
      <li>Die Startseite des Connectors einmal aufrufen – dabei legt er den Kopplungscode an.</li>
      <li>Den Code aus der Datei <span class="mono">connector/daten/kopplungscode.txt</span> holen
        (FTP oder SSH) und hier eintragen.</li>
    </ol>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="koppeln">
      <div class="field">
        <label for="code">Kopplungscode</label>
        <input type="text" id="code" name="code" required placeholder="A1B2C3-D4E5F6-071829" autocomplete="off">
      </div>
      <div class="btnrow"><button class="btn" type="submit">Koppeln</button></div>
    </form>
  <?php else: ?>
    <dl class="dl">
      <div class="dl__item"><div class="dl__label">Gekoppelt seit</div>
        <div class="dl__value"><?= $c['gekoppelt_am'] ? e(de_datetime((string)$c['gekoppelt_am'])) : '–' ?>
          <?= trim((string)$c['version']) !== '' ? '<span class="muted small">· Fassung '
              . e((string)$c['version']) . '</span>' : '' ?></div></div>
      <div class="dl__item"><div class="dl__label">Zuletzt abgeholt</div>
        <div class="dl__value"><?= $c['letzter_abruf'] ? e(de_datetime((string)$c['letzter_abruf'])) : 'noch nie' ?></div></div>
      <div class="dl__item"><div class="dl__label">Unser Schlüssel</div>
        <div class="dl__value mono small" style="word-break:break-all"><?= e(substr((string)$c['pubkey'], 0, 24)) ?>…</div></div>
    </dl>
    <div class="btnrow">
      <?php if ((int)$c['fuer_fahrzeuge'] === 1): ?>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="abholen">
          <button class="btn" type="submit">Jetzt abholen</button></form>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="senden">
          <button class="btn btn--sec" type="submit">Fahrzeuge neu anmelden</button></form>
      <?php endif; ?>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="zustand">
        <button class="btn btn--sec" type="submit">Zustand abfragen</button></form>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="trennen">
        <button class="btn btn--sec" type="submit"
                data-confirm="Kopplung lösen? Die QR-Codes und Einladungen funktionieren erst nach einer neuen Kopplung wieder."
                data-confirm2="Bist du wirklich sicher? Auf dem Server muss dafür auch daten/kopplung.json gelöscht werden.">Kopplung lösen</button></form>
    </div>
  <?php endif; ?>
</div>

<?php if ($zustand !== null): ?>
<div class="card">
  <h2>Zustand des Connectors</h2>
  <p class="small">Diese Zahlen stehen auf keiner Webseite des Connectors – seine Startseite
    verrät nichts über den Ortsverband. Abgefragt werden sie signiert.</p>
  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Fassung</div>
      <div class="dl__value"><?= e((string)($zustand['version'] ?? '?')) ?>
        <span class="muted small">· PHP <?= e((string)($zustand['php'] ?? '?')) ?></span></div></div>
    <div class="dl__item"><div class="dl__label">Fahrzeuge dort angemeldet</div>
      <div class="dl__value"><?= (int)($zustand['fahrzeuge'] ?? 0) ?></div></div>
    <div class="dl__item"><div class="dl__label">Veranstaltungen</div>
      <div class="dl__value"><?= (int)($zustand['veranstaltungen'] ?? 0) ?>
        <span class="muted small">· <?= (int)($zustand['einladungen'] ?? 0) ?> Einladungen</span></div></div>
    <div class="dl__item"><div class="dl__label">Wartet auf Abholung</div>
      <div class="dl__value"><?= (int)($zustand['meldungen'] ?? 0) ?> Standortmeldung(en),
        <?= (int)($zustand['rueckmeldungen'] ?? 0) ?> Rückmeldung(en)</div></div>
    <div class="dl__item"><div class="dl__label">Ablage</div>
      <div class="dl__value"><?= !empty($zustand['schreibbar'])
          ? 'beschreibbar' : '<span style="color:var(--bad)">nicht beschreibbar</span>' ?>
        <?php if (!empty($zustand['frei'])): ?>
          <span class="muted small">· <?= e(bytes_human((int)$zustand['frei'])) ?> frei</span>
        <?php endif; ?></div></div>
  </dl>
</div>
<?php endif; ?>

<?php if ((int)$c['fuer_fahrzeuge'] === 1): ?>
<div class="card">
  <div class="card__head">
    <h2>Fahrzeuge mit QR-Code</h2>
    <span class="muted small"><?= count($fahrzeuge) ?></span>
  </div>
  <?php if (!$fahrzeuge): ?>
    <div class="empty">Noch kein Fahrzeug hat über diesen Connector einen Code. Den gibt es in der
      jeweiligen Fahrzeugakte.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Fahrzeug</th><th>Letzte Position</th><th>Quelle</th></tr></thead>
        <tbody>
        <?php foreach ($fahrzeuge as $v): ?>
          <tr>
            <td><a href="<?= e(url('vehicle', ['id' => $v['id']])) ?>#qr"><?= e((string)$v['bezeichnung']) ?></a>
              <?= $v['kennzeichen'] ? '<span class="muted small">· ' . e((string)$v['kennzeichen']) . '</span>' : '' ?></td>
            <td class="small"><?= $v['geo_at'] ? e(de_datetime((string)$v['geo_at'])) : '<span class="muted">–</span>' ?></td>
            <td class="small"><?= e(match ((string)$v['geo_quelle']) {
                'qr'     => 'QR-Meldung',
                'divera' => 'Divera',
                'mensch' => 'von Hand',
                default  => '–',
            }) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Entfernen</h2>
  <p class="small">Der Connector verschwindet aus dieser Liste. QR-Codes und Einladungslinks, die
    darauf zeigen, gehen danach ins Leere.</p>
  <form method="post" class="inline-form"><?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec" type="submit"
            data-confirm="Diesen Connector löschen? Alles, was darüber läuft, hört damit auf zu funktionieren."
            data-confirm2="Bist du wirklich sicher?">Connector löschen</button></form>
</div>
<?php endif; ?>
