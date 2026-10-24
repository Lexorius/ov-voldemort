<?php
/** @var array $gruppen @var bool $darf @var array $connectoren @var string $title */
$bloecke = [
    'laufend' => ['Läuft gerade', 'Der Connector nimmt Antworten an.'],
    'kommend' => ['Freigegeben, öffnet später', 'Die Seite steht schon, nimmt aber erst ab dem Beginn an.'],
    'entwurf' => ['Entwürfe', 'Noch nicht freigegeben – beim Connector unbekannt.'],
    'vorbei'  => ['Beendet und geschlossen', 'Keine Antworten mehr; die Ergebnisse bleiben, bis sie gelöscht werden.'],
];
$leer = !array_filter($gruppen);
?>
<div class="pagehead">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Umfragen mit einer oder mehreren Seiten, hier gestaltet und über den Connector im Netz. Die Antworten
       kommen verschlüsselt zurück; beim Connector liegt nach dem Abholen nichts mehr. Jede Abstimmung hat ein Zeitfenster.</p>
  </div>
  <?php if ($darf): ?>
    <div class="btnrow"><a class="btn" href="<?= e(url('umfrage_edit')) ?>">+ Abstimmung</a></div>
  <?php endif; ?>
</div>

<?php if ($darf && !$connectoren): ?>
  <div class="alert alert--warn">Zum Freigeben braucht es einen <strong>Connector</strong> mit der Verwendung
    „Abstimmungen" – einzurichten in der <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>.
    Gestalten geht auch ohne.</div>
<?php endif; ?>

<?php if ($leer): ?>
  <div class="empty">Noch keine Abstimmung.</div>
<?php endif; ?>

<?php foreach ($bloecke as $key => [$ueberschrift, $hinweis]): if (!$gruppen[$key]) { continue; } ?>
  <section class="card">
    <div class="card__head">
      <h2><?= e($ueberschrift) ?></h2>
      <span class="small muted"><?= e($hinweis) ?></span>
    </div>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Abstimmung</th><th>Zeitfenster</th><th class="num">Antworten</th><th>Zustand</th></tr></thead>
        <tbody>
        <?php foreach ($gruppen[$key] as $u): ?>
          <tr>
            <td><a href="<?= e(url('umfrage', ['id' => $u['id']])) ?>"><strong><?= e((string)$u['titel']) ?></strong></a>
              <div class="small muted mono">Code <?= e((string)$u['code']) ?></div></td>
            <td class="small nowrap"><?= trim((string)($u['beginn'] ?? '')) !== '' ? 'ab ' . e(de_datetime((string)$u['beginn'])) . '<br>' : '' ?>
              bis <?= e(de_datetime((string)$u['ende'])) ?></td>
            <td class="num"><?= (int)($u['antworten_anzahl'] ?? 0) ?></td>
            <td><?= umfrage_zustand_badge((string)$u['zustand']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>
