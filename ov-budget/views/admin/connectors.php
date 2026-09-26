<?php
/** @var array $liste @var array $fahrzeuge @var bool $aktiv @var string $hinweis @var string $fehler */
?>
<div class="pagehead">
  <div>
    <h1>Connectoren</h1>
    <p>Ein Connector ist ein Briefkasten auf einem öffentlich erreichbaren Webserver. Über ihn kommt
       herein, was niemand mit Zugang zu dieser Anwendung meldet: Standorte aus den Fahrzeugen
       (QR-Code) und Rückmeldungen auf Einladungen. Lesen kann er nichts davon – alles ist schon im
       Browser des Absenders für uns verschlüsselt.</p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('admin_connector')) ?>">Connector anlegen</a>
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Verwaltung</a>
  </div>
</div>

<?php if ($fehler !== ''): ?><div class="alert alert--error"><?= e($fehler) ?></div><?php endif; ?>
<?php if ($hinweis !== ''): ?><div class="alert alert--success"><?= e($hinweis) ?></div><?php endif; ?>

<?php if (!$aktiv): ?>
  <div class="alert alert--warn">Die Standortmeldung per QR-Code ist ausgeschaltet. In den
    <a href="<?= e(url('admin_settings', ['group' => 'Fahrzeuge'])) ?>">Einstellungen</a> einschalten.
    Einladungen zu Veranstaltungen laufen davon unabhängig.</div>
<?php endif; ?>

<?php if (!$liste): ?>
  <div class="card">
    <div class="empty">Noch kein Connector eingerichtet.</div>
    <ol class="small">
      <li>Den Ordner <span class="mono">connector/</span> auf den Webserver legen, das
        Dokumentenverzeichnis auf <span class="mono">connector/public</span> zeigen lassen.</li>
      <li>Die Startseite des Connectors einmal aufrufen – dabei legt er den Kopplungscode an.</li>
      <li>Hier <a href="<?= e(url('admin_connector')) ?>">einen Connector anlegen</a> und den Code
        aus <span class="mono">connector/daten/kopplungscode.txt</span> eintragen.</li>
    </ol>
  </div>
<?php else: ?>
  <div class="card">
    <div class="tablewrap">
      <table class="data">
        <thead><tr>
          <th>Name</th><th>Adresse</th><th>Verwendung</th><th>Zustand</th><th>Zuletzt abgeholt</th>
        </tr></thead>
        <tbody>
        <?php foreach ($liste as $c): ?>
          <?php
            $zwecke = [];
            if ((int)$c['fuer_fahrzeuge'] === 1) {
                $zwecke[] = 'Fahrzeuge' . (isset($fahrzeuge[(int)$c['id']])
                    ? ' (' . (int)$fahrzeuge[(int)$c['id']] . ')' : '');
            }
            if ((int)$c['fuer_veranstaltungen'] === 1) {
                $zwecke[] = 'Veranstaltungen';
            }
            if ((int)($c['fuer_bestand'] ?? 0) === 1) {
                $zwecke[] = 'Funkgeräte';
            }
            if ((int)($c['fuer_verbrauch'] ?? 0) === 1) {
                $zwecke[] = 'Zähler';
            }
          ?>
          <tr>
            <td><a href="<?= e(url('admin_connector', ['id' => $c['id']])) ?>"><?= e((string)$c['name']) ?></a>
              <?php if ((int)$c['is_active'] !== 1): ?>
                <span class="muted small">· abgeschaltet</span>
              <?php endif; ?></td>
            <td class="small mono" style="word-break:break-all"><?= e((string)$c['url']) ?>
              <?php if (trim((string)$c['kurz_url']) !== ''): ?>
                <br><span class="muted">kurz: <?= e((string)$c['kurz_url']) ?></span>
              <?php endif; ?></td>
            <td class="small"><?= $zwecke ? e(implode(', ', $zwecke)) : '<span class="muted">– keine –</span>' ?></td>
            <td class="small"><?= connector_gekoppelt($c)
                ? '<span style="color:var(--good)">gekoppelt</span>'
                : '<span style="color:var(--warn)">nicht gekoppelt</span>' ?></td>
            <td class="small"><?= $c['letzter_abruf']
                ? e(de_datetime((string)$c['letzter_abruf']))
                : '<span class="muted">noch nie</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="btnrow">
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="abholen">
        <button class="btn btn--sec" type="submit">Überall jetzt abholen</button></form>
    </div>
  </div>
<?php endif; ?>
