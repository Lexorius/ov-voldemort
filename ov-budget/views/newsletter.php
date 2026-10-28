<?php
/** @var string $title @var array $rows @var array $blatt @var bool $darf @var bool $mailAktiv @var array $connectoren @var int $gesperrt */
?>
<div class="pagehead">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Fertige Newsletter als PDF an eine Liste von Adressen: Kontakte, Verteiler, freie Adressen. Jede Mail trägt einen
       Abmeldelink über den Connector; wer sich abmeldet, steht auf der Sperrliste und bekommt nichts mehr.</p>
  </div>
  <div class="btnrow">
    <?php if ($darf): ?><a class="btn" href="<?= e(url('newsletter_edit')) ?>">+ Newsletter</a><?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('newsletter_sperre')) ?>">Sperrliste<?= $gesperrt > 0 ? ' (' . $gesperrt . ')' : '' ?></a>
  </div>
</div>

<?php if ($darf && !$mailAktiv): ?>
  <div class="alert alert--warn">Der E-Mail-Versand ist nicht eingerichtet – ohne ihn geht kein Newsletter hinaus
    (<a href="<?= e(url('admin_mail')) ?>">Verwaltung → E-Mail</a>).</div>
<?php endif; ?>
<?php if ($darf && !$connectoren): ?>
  <div class="alert alert--info">Für den Abmeldelink braucht es einen <strong>Connector</strong> mit der Verwendung „Newsletter"
    (<a href="<?= e(url('admin_connectors')) ?>">Verwaltung → Connectoren</a>). Anlegen und vorbereiten geht schon jetzt.</div>
<?php endif; ?>

<section class="card">
  <?php if (!$rows): ?>
    <div class="empty">Noch kein Newsletter.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Newsletter</th><th>Versand</th><th class="num">Empfänger</th><th>Stand</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $n): ?>
          <tr>
            <td><a href="<?= e(url('newsletter_detail', ['id' => $n['id']])) ?>"><strong><?= e((string)$n['titel']) ?></strong></a>
              <div class="small muted"><?= e((string)($n['orig_name'] ?: 'kein PDF')) ?><?= (int)$n['size_bytes'] > 0 ? ' · ' . e(bytes_human((int)$n['size_bytes'])) : '' ?></div></td>
            <td class="small nowrap"><?= !empty($n['gesendet_am']) ? 'verschickt ' . e(de_datetime((string)$n['gesendet_am'])) : (!empty($n['senden_am']) ? 'ab ' . e(de_datetime((string)$n['senden_am'])) : '<span class="muted">nicht geplant</span>') ?></td>
            <td class="num"><?= (int)$n['versand_gesamt'] > 0 ? (int)$n['versand_fertig'] . ' / ' . (int)$n['versand_gesamt'] . ((int)$n['versand_fehler'] > 0 ? ' <span style="color:var(--bad)">(' . (int)$n['versand_fehler'] . ' Fehler)</span>' : '') : '<span class="muted">–</span>' ?></td>
            <td><?= newsletter_status_badge((string)$n['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_partial('partials/seiten', ['blatt' => $blatt, 'route' => 'newsletter']) ?>
  <?php endif; ?>
</section>
