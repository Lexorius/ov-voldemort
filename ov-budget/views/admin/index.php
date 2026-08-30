<?php /** @var array $zahlen */ ?>
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

<div class="adminmenu">
  <a href="<?= e(url('admin_users')) ?>"><div class="card"><h3>Benutzer</h3>
    <p>Zugänge anlegen, Rollen, Fachgruppe und Funktionen zuordnen, Passwörter zurücksetzen.</p></div></a>
  <a href="<?= e(url('admin_lists')) ?>"><div class="card"><h3>Auswahllisten</h3>
    <p>Fachgruppen, Funktionen, Kategorien, Dringlichkeiten, Status und Einheiten – inklusive Farben und Reihenfolge.</p></div></a>
  <a href="<?= e(url('admin_settings')) ?>"><div class="card"><h3>Einstellungen</h3>
    <p>Bezeichnungen, Einleitungstexte, Pflichtfelder, Upload-Grenzen, Haushaltsjahr und Sicherheit.</p></div></a>
  <a href="<?= e(url('admin_divera')) ?>"><div class="card"><h3>Divera 24/7</h3>
    <p>Zugang einrichten, Formulare abrufen, Felder zuordnen und Einträge als Wünsche übernehmen.</p></div></a>
  <a href="<?= e(url('budget')) ?>"><div class="card"><h3>Budget</h3>
    <p>Haushaltsjahre und Budgettöpfe pflegen.</p></div></a>
  <a href="<?= e(url('admin_log')) ?>"><div class="card"><h3>Protokoll</h3>
    <p>Wer hat wann was geändert – und was der letzte Divera-Abruf gemeldet hat.</p></div></a>
</div>
