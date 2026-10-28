<?php
/**
 * @var array $n @var ?array $connector @var array $vorlage @var string $fuss @var array $empfaenger @var array $stand
 * @var array $versand @var array $kiDaten @var bool $darf @var bool $mailAktiv @var string $meineMail
 */
$id = (int)$n['id'];
$status = (string)$n['status'];
$aenderbar = in_array($status, ['entwurf', 'geplant'], true);
$aktion = static function (string $action, string $text, string $klasse = 'btn btn--sec btn--sm', string $frage = '') use ($id): string {
    return '<form method="post" action="' . e(url('newsletter_action')) . '" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="' . e($klasse) . '" type="submit"' . ($frage !== '' ? ' data-confirm="' . e($frage) . '"' : '') . '>' . e($text) . '</button></form>';
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$n['titel']) ?> <?= newsletter_status_badge($status) ?></h1>
    <p><?= !empty($n['orig_name']) ? e((string)$n['orig_name']) . ' · ' . e(bytes_human((int)$n['size_bytes'])) : '<span style="color:var(--warn)">kein PDF</span>' ?>
      <?php if ($connector !== null): ?> · Connector <strong><?= e((string)$connector['name']) ?></strong><?php else: ?> · <span style="color:var(--warn)">kein Connector</span><?php endif; ?>
      <?php if (!empty($n['senden_am'])): ?> · Versand ab <?= e(de_datetime((string)$n['senden_am'])) ?><?php endif; ?>
      <?php if (!empty($n['gesendet_am'])): ?> · fertig <?= e(de_datetime((string)$n['gesendet_am'])) ?><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('newsletter')) ?>">Zur Liste</a>
    <?php if (!empty($n['stored_name'])): ?>
      <a class="btn btn--sec" href="<?= e(url('newsletter_datei', ['id' => $id])) ?>" target="_blank" rel="noopener">PDF ansehen</a>
    <?php endif; ?>
    <?php if ($darf && $aenderbar): ?><a class="btn btn--sec" href="<?= e(url('newsletter_edit', ['id' => $id])) ?>">Bearbeiten</a><?php endif; ?>
  </div>
</div>

<section class="card" id="ki">
  <div class="card__head"><h2>Zusammenfassung für die Verwaltung</h2><span class="small muted">von der KI-Auswertung, ohne Gewähr</span></div>
  <?php if (empty($n['stored_name'])): ?>
    <p class="small muted">Erst das PDF hochladen.</p>
  <?php else: ?>
    <?= function_exists('ki_hinweis_html') ? ki_hinweis_html('newsletter', $n + ['orig_name' => (string)$n['orig_name']], $kiDaten, $darf) : '<p class="small muted">Die KI-Auswertung ist nicht eingerichtet.</p>' ?>
  <?php endif; ?>
</section>

<section class="card" id="empfaenger">
  <div class="card__head"><h2>Empfänger</h2>
    <span class="small muted"><?= count($empfaenger['empfaenger']) ?> Adressen<?= $empfaenger['gesperrt'] > 0 ? ' · ' . (int)$empfaenger['gesperrt'] . ' gesperrt, werden ausgelassen' : '' ?></span></div>
  <?php if (!$empfaenger['empfaenger']): ?>
    <p class="small muted">Noch keine Empfänger mit E-Mail-Adresse. Beim <a href="<?= e(url('newsletter_edit', ['id' => $id])) ?>">Bearbeiten</a> Kontakte,
      Verteiler oder freie Adressen eintragen.</p>
  <?php else: ?>
    <p class="small"><?= e(implode(', ', array_map(static fn($e) => ($e['name'] !== '' ? $e['name'] . ' ' : '') . '<' . $e['email'] . '>', array_slice($empfaenger['empfaenger'], 0, 60)))) ?><?= count($empfaenger['empfaenger']) > 60 ? ' … und ' . (count($empfaenger['empfaenger']) - 60) . ' weitere' : '' ?></p>
  <?php endif; ?>
</section>

<section class="card" id="mail">
  <div class="card__head"><h2>E-Mail</h2><?php if ($darf && $aenderbar && $mailAktiv && $meineMail !== ''): ?><?= $aktion('probe', 'Probe an mich') ?><?php endif; ?></div>
  <?php if ($darf && $aenderbar): ?>
    <form method="post" action="<?= e(url('newsletter_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="text">
      <div class="field"><label for="betreff">Betreff</label>
        <input type="text" id="betreff" name="betreff" maxlength="200" value="<?= e((string)$vorlage['betreff']) ?>"></div>
      <div class="field"><label for="text">Text <span class="muted">(das PDF hängt an, die Fußzeile mit dem Abmeldelink kommt automatisch darunter)</span></label>
        <textarea id="text" name="text" rows="8"><?= e((string)$vorlage['text']) ?></textarea></div>
      <div class="btnrow"><button class="btn btn--sec btn--sm" type="submit">Betreff und Text speichern</button></div>
    </form>
  <?php else: ?>
    <p class="small"><strong>Betreff:</strong> <?= e((string)$vorlage['betreff']) ?></p>
    <pre class="getraenke-mail"><?= e((string)$vorlage['text']) ?></pre>
  <?php endif; ?>
  <?php if ($fuss !== ''): ?><pre class="getraenke-mail small muted" style="margin-top:.5rem"><?= e(trim($fuss)) ?></pre>
    <p class="small muted">Statt der x-Zeichen steht in jeder Mail die Kennung des Empfängers; der Text kommt aus den Einstellungen (Gruppe „Newsletter").</p><?php endif; ?>
</section>

<?php if ($darf && in_array($status, ['entwurf', 'geplant'], true)): ?>
  <div class="alert alert--warn"><strong>Nur für interne Newsletter.</strong> E-Mail-Adressen nur mit Zustimmung der
  Empfänger verwenden – Mitglieder, Helferinnen und Helfer, Angehörige, die den Newsletter ausdrücklich wollen. Für Werbung an
  Außenstehende ist dieses Modul nicht gedacht. Bitte die rechtlichen Voraussetzungen beachten (Einwilligung, Datenschutz,
  Impressum in der Mail, jederzeit mögliche Abmeldung); die Verantwortung liegt beim Ortsverband.</div>
<?php endif; ?>

<section class="card" id="versand">
  <div class="card__head"><h2>Versand</h2>
    <?php if ($stand['gesamt'] > 0): ?><span class="small muted"><?= (int)$stand['fertig'] ?> von <?= (int)$stand['gesamt'] ?> verschickt<?= $stand['fehler'] > 0 ? ', ' . (int)$stand['fehler'] . ' Fehler' : '' ?><?= $stand['offen'] > 0 ? ', ' . (int)$stand['offen'] . ' offen' : '' ?></span><?php endif; ?></div>
  <?php if ($darf && $status === 'entwurf'): ?>
    <?php if (!$mailAktiv): ?><p class="small" style="color:var(--warn)">Der E-Mail-Versand ist nicht eingerichtet (<a href="<?= e(url('admin_mail')) ?>">Verwaltung → E-Mail</a>).</p><?php endif; ?>
    <form method="post" action="<?= e(url('newsletter_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="grid2">
        <div class="field"><label for="senden_am">Versand am</label>
          <input type="datetime-local" id="senden_am" name="senden_am" value="<?= e(!empty($n['senden_am']) ? date('Y-m-d\TH:i', (int)strtotime((string)$n['senden_am'])) : date('Y-m-d\TH:i', strtotime('tomorrow 08:00'))) ?>"></div>
      </div>
      <div class="btnrow">
        <button class="btn" type="submit" name="action" value="planen" <?= $mailAktiv ? '' : 'disabled' ?> data-confirm="Versand zu diesem Zeitpunkt einplanen? Die Empfängerliste wird jetzt festgehalten.">Zum Zeitpunkt verschicken</button>
        <button class="btn btn--sec" type="submit" name="action" value="sofort" <?= $mailAktiv ? '' : 'disabled' ?> data-confirm="Jetzt verschicken? Der automatische Abruf beginnt in der nächsten Minute.">Sofort verschicken</button>
      </div>
      <p class="small muted">Beim Einplanen wird die Empfängerliste festgehalten und die Sperrliste angewandt; ab dem Zeitpunkt verschickt der automatische
        Abruf in Portionen. Bis dahin lassen sich Text, Empfänger und Zeitpunkt noch ändern – Ändern setzt die Planung zurück.</p>
    </form>
  <?php elseif ($darf && in_array($status, ['geplant', 'laeuft'], true)): ?>
    <p class="small"><?= $status === 'geplant' ? 'Eingeplant ab ' . e(de_datetime((string)$n['senden_am'])) . ' an ' . (int)$stand['gesamt'] . ' Empfänger.' : 'Der Versand läuft – der automatische Abruf verschickt je Minute eine Portion.' ?></p>
    <div class="btnrow">
      <?php if ($status === 'geplant'): ?><?= $aktion('sofort', 'Jetzt verschicken', 'btn btn--sm', 'Jetzt verschicken statt zum geplanten Zeitpunkt?') ?><?php endif; ?>
      <?= $aktion('abbrechen', $status === 'geplant' ? 'Planung aufheben' : 'Versand abbrechen', 'btn btn--sec btn--sm', $status === 'geplant' ? 'Planung aufheben? Der Newsletter wird wieder ein Entwurf.' : 'Versand abbrechen? Was schon raus ist, bleibt raus; der Rest wird nicht verschickt.') ?>
    </div>
  <?php elseif ($status === 'gesendet'): ?>
    <p class="small">Verschickt am <?= e(de_datetime((string)$n['gesendet_am'])) ?>.</p>
  <?php endif; ?>
  <?php if ($versand): ?>
    <div class="tablewrap" style="margin-top:.6rem">
      <table class="data">
        <thead><tr><th>Empfänger</th><th>Verschickt</th><th>Fehler</th></tr></thead>
        <tbody>
        <?php foreach ($versand as $v): ?>
          <tr>
            <td class="small"><?= e(trim((string)$v['name'] . ' <' . (string)$v['email'] . '>')) ?></td>
            <td class="small nowrap"><?= !empty($v['gesendet_am']) ? e(de_datetime((string)$v['gesendet_am'])) : '<span class="muted">offen</span>' ?></td>
            <td class="small" style="color:var(--bad)"><?= e((string)$v['fehler']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($stand['gesamt'] > count($versand)): ?><p class="small muted">Die ersten <?= count($versand) ?> von <?= (int)$stand['gesamt'] ?> Zeilen, Fehler zuerst.</p><?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($darf && $status !== 'laeuft'): ?>
  <form method="post" action="<?= e(url('newsletter_action')) ?>" class="form" style="margin-top:1rem"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Newsletter samt PDF und Versandliste löschen?" data-confirm2="Bist du wirklich sicher?">Newsletter löschen</button></form>
<?php endif; ?>
