<?php /** @var array $zahlen @var array $zeit */ ?>
<div class="pagehead">
  <div>
    <h1>Verwaltung</h1>
    <p>Hier werden Benutzer, Auswahllisten, Texte und die Divera-Anbindung gepflegt.</p>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Benutzer</div><div class="stat__value"><?= $zahlen['benutzer'] ?></div></div>
  <div class="stat"><div class="stat__label">Wünsche</div><div class="stat__value"><?= $zahlen['wuensche'] ?></div></div>
  <div class="stat"><div class="stat__label">Aufgaben</div><div class="stat__value"><?= $zahlen['aufgaben'] ?></div></div>
  <div class="stat"><div class="stat__label">Listeneinträge</div><div class="stat__value"><?= $zahlen['listen'] ?></div></div>
</div>

<?php if ($zeit['versatz'] > 120): ?>
  <div class="alert alert--warn">
    <strong>Anwendung und Datenbank zeigen unterschiedliche Zeiten.</strong>
    Anwendung: <?= e(de_datetime($zeit['app'])) ?> (<?= e($zeit['zone']) ?>),
    Datenbank: <?= e(de_datetime($zeit['db'])) ?>.
    Dadurch stehen in Protokollen Zeiten, die daneben liegen. Im Home-Assistant-Add-on
    lässt sich die Zeitzone in der Add-on-Konfiguration setzen (<span class="mono">zeitzone</span>),
    sonst über die Umgebungsvariable <span class="mono">TZ</span> des Containers.
  </div>
<?php endif; ?>

<div class="adminmenu">
  <a href="<?= e(url('admin_users')) ?>"><div class="card"><h3>Benutzer</h3>
    <p>Zugänge anlegen, Rollen, Fachgruppe und Funktionen zuordnen, Passwörter zurücksetzen.</p></div></a>
  <a href="<?= e(url('admin_lists')) ?>"><div class="card"><h3>Auswahllisten</h3>
    <p>Fachgruppen, Funktionen, Kategorien, Dringlichkeiten, Status und Einheiten – inklusive Farben und Reihenfolge.</p></div></a>
  <a href="<?= e(url('admin_settings')) ?>"><div class="card"><h3>Einstellungen</h3>
    <p>Bezeichnungen, Einleitungstexte, Pflichtfelder, Upload-Grenzen, Haushaltsjahr und Sicherheit.</p></div></a>
  <a href="<?= e(url('admin_order_rights')) ?>"><div class="card"><h3>Bestellberechtigungen</h3>
    <p>Wer Wünsche zur Bestellung freigeben darf – etwa Ortsbeauftragte:r, auch mit Betragsgrenze – und wer bestellt.</p></div></a>
  <div class="card">
    <h3>Dateiablage</h3>
    <p class="small">Bilder und Dokumente der Fahrzeuge liegen unter
      <span class="mono"><?= e($ablage['pfad']) ?></span>.</p>
    <p class="small">
      <?php if (!$ablage['vorhanden']): ?>
        <strong style="color:var(--bad)">Der Ordner fehlt.</strong>
      <?php elseif (!$ablage['beschreibbar']): ?>
        <strong style="color:var(--bad)">Nicht beschreibbar</strong> – der Webserver läuft als
        „<?= e($ablage['benutzer'] ?: 'unbekannt') ?>". Hochladen kann deshalb nicht klappen.
      <?php else: ?>
        <?= (int)$ablage['dateien'] ?> Datei(en) abgelegt, <?= (int)$ablage['eintraege'] ?> Einträge geprüft.
      <?php endif; ?>
    </p>
    <?php if ($ablage['fehlend']): ?>
      <p class="small" style="color:var(--warn)"><?= count($ablage['fehlend']) ?> Eintrag/Einträge ohne Datei
        auf der Platte, z. B.
        <?= e(implode(', ', array_map(static fn($f) => (string)($f['titel'] ?: $f['stored_name']),
            array_slice($ablage['fehlend'], 0, 3)))) ?>.
        Diese Dateien sind beim Hochladen nicht angekommen oder später verloren gegangen.</p>
    <?php endif; ?>
  </div>

  <a href="<?= e(url('admin_connectors')) ?>"><div class="card"><h3>Connectoren</h3>
    <p>Briefkästen auf öffentlichen Webservern: Standort per QR-Code und Einladungen zu
       Veranstaltungen. Koppeln, anmelden, abholen.</p></div></a>
  <a href="<?= e(url('admin_ha')) ?>"><div class="card"><h3>Home Assistant</h3>
    <p>Kennzahlen und Fahrzeuge über MQTT an Home Assistant melden – mit Auto-Discovery.</p></div></a>
  <a href="<?= e(url('admin_stein')) ?>"><div class="card"><h3>Stein.APP</h3>
    <p>Fahrzeuge aus der Stein.APP abgleichen, zuordnen und das Protokoll der Abrufe ansehen.</p></div></a>
  <a href="<?= e(url('admin_divera_fahrzeuge')) ?>"><div class="card"><h3>Divera-Fahrzeuge</h3>
    <p>Funkstatus, Position und Besatzung aus Divera; Fahrzeuge zuordnen und Abrufe ansehen.</p></div></a>
  <a href="<?= e(url('admin_divera')) ?>"><div class="card"><h3>Divera 24/7</h3>
    <p>Zugang einrichten, Formulare abrufen, Felder zuordnen und Einträge als Wünsche übernehmen.</p></div></a>
  <a href="<?= e(url('budget')) ?>"><div class="card"><h3>Budget</h3>
    <p>Haushaltsjahre und Budgettöpfe pflegen.</p></div></a>
  <a href="<?= e(url('admin_backup')) ?>"><div class="card"><h3>Sicherung</h3>
    <p>Datenbank und Dateiablage als ZIP sichern, herunterladen, hochladen und wiederherstellen –
       auf Wunsch automatisch.</p></div></a>
  <a href="<?= e(url('admin_log')) ?>"><div class="card"><h3>Protokoll</h3>
    <p>Wer hat wann was geändert – und was der letzte Divera-Abruf gemeldet hat.</p></div></a>
</div>
