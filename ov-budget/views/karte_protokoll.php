<?php
/** @var array $karte @var ?array $einsatz @var array $zeiten @var array $protokoll @var bool $darfFahrzeuge @var array $fahrzeuge */
$id = (int)$karte['id'];
$aktive = count(array_filter($zeiten, static fn($z) => $z['aktiv']));
?>
<div class="pagehead">
  <div>
    <h1>Protokoll <span class="muted">· <?= e((string)$karte['name']) ?></span></h1>
    <p>Wann welche Einheit und welches Fahrzeug gesetzt, versetzt oder herausgenommen wurde – und wie lange jedes im Einsatz ist.
      <?php if ($einsatz): ?>Die Karte gehört zum Einsatz <a href="<?= e(url('einsatz', ['id' => $einsatz['id']])) ?>#karten"><?= e(einsatz_bezeichnung($einsatz)) ?></a>.<?php endif; ?></p>
  </div>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('karte', ['id' => $id])) ?>">Zur Karte</a>
    <?php if ($protokoll): ?><a class="btn btn--sec" href="<?= e(url('karte_protokoll', ['id' => $id, 'csv' => 1])) ?>">Als CSV</a><?php endif; ?>
  </div>
</div>

<div class="card" id="zeiten">
  <div class="card__head">
    <h2>Einsatzzeiten</h2>
    <span class="muted small"><?= $aktive === 1 ? '1 im Einsatz' : $aktive . ' im Einsatz' ?></span>
  </div>
  <?php if (!$zeiten): ?>
    <div class="empty">Noch nichts protokolliert. Die Zeit einer Einheit beginnt, sobald sie auf die Karte gesetzt wird, und endet, wenn sie gelöscht wird.
      Ein Fahrzeug kommt hinein, wenn es auf der Karte gezogen oder unten aufgenommen wird.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Einheit / Fahrzeug</th><th>Abschnitt</th><th>Stärke</th><th>seit</th><th>bis</th><th class="num">Dauer</th><th class="num">bewegt</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($zeiten as $z): ?>
          <tr>
            <td><strong><?= e((string)$z['name']) ?></strong> <span class="badge badge--outline"><?= e((string)$z['art_text']) ?></span></td>
            <td class="small"><?= e((string)$z['abschnitt'] !== '' ? (string)$z['abschnitt'] : '–') ?></td>
            <td class="nowrap"><?= (string)$z['staerke'] !== '' ? '<span class="mono">' . e((string)$z['staerke']) . '</span>' : '–' ?></td>
            <td class="nowrap small"><?= e((string)$z['seit_text']) ?></td>
            <td class="nowrap small"><?= $z['aktiv'] ? '<span class="badge" style="background:#b91c1c">noch im Einsatz</span>' : e((string)$z['bis_text']) ?></td>
            <td class="num"><strong><?= e((string)$z['dauer_text']) ?></strong></td>
            <td class="num small"><?= (int)$z['bewegungen'] > 0 ? (int)$z['bewegungen'] . '× · ' . e((string)$z['strecke_text']) : '–' ?></td>
            <td><?php if ($darfFahrzeuge && $z['art'] === 'fahrzeug' && $z['aktiv'] && (int)$z['vehicle_id'] > 0): ?>
              <form method="post" action="<?= e(url('karte_protokoll', ['id' => $id])) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="fahrzeug_ende">
                <input type="hidden" name="vehicle_id" value="<?= (int)$z['vehicle_id'] ?>">
                <button class="btn btn--sec btn--sm" type="submit" data-confirm="Einsatz von „<?= e((string)$z['name']) ?>“ jetzt beenden?">Einsatz beendet</button>
              </form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted" style="margin-bottom:0">Die Dauer läuft vom Setzen bis zum Herausnehmen; wer wieder gesetzt wird, sammelt weiter.
      „Bewegt" zählt, wie oft und wie weit etwas auf der Karte versetzt wurde (Luftlinie zwischen den Stellen – nicht die gefahrene Strecke;
      die Kilometer je Fahrzeug stehen beim Einsatz).</p>
  <?php endif; ?>
  <?php if ($darfFahrzeuge && $fahrzeuge): ?>
    <form method="post" action="<?= e(url('karte_protokoll', ['id' => $id])) ?>" class="form mt">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="fahrzeug_auf">
      <div class="filters">
        <div class="field">
          <label for="vehicle_id">Fahrzeug in den Einsatz nehmen</label>
          <select id="vehicle_id" name="vehicle_id" required>
            <option value="">– Fahrzeug wählen –</option>
            <?php foreach ($fahrzeuge as $v): ?>
              <option value="<?= (int)$v['id'] ?>"><?= e((string)$v['bezeichnung']) ?><?= trim((string)$v['funkrufname']) !== '' ? ' (' . e((string)$v['funkrufname']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>&nbsp;</label><button class="btn btn--sec" type="submit">Aufnehmen</button></div>
      </div>
      <small class="muted">Für Fahrzeuge, die ihre Position selbst melden (Divera, QR-Code) und deshalb nie gezogen werden: Die Zeit läuft ab dem Aufnehmen.</small>
    </form>
  <?php endif; ?>
</div>

<div class="card" id="protokoll">
  <div class="card__head">
    <h2>Protokoll</h2>
    <span class="muted small"><?= count($protokoll) ?></span>
  </div>
  <?php if (!$protokoll): ?>
    <div class="empty">Noch kein Eintrag.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Zeit</th><th>Einheit / Fahrzeug</th><th>Was</th><th>von</th><th>nach</th><th>Wer</th></tr></thead>
        <tbody>
        <?php foreach ($protokoll as $p): ?>
          <tr>
            <td class="nowrap small"><?= e((string)$p['zeit']) ?></td>
            <td><?= e((string)$p['name']) ?> <span class="muted small"><?= e((string)$p['art']) ?></span></td>
            <td><?= e((string)$p['was']) ?></td>
            <td class="small mono"><?= e((string)$p['von'] !== '' ? (string)$p['von'] : '–') ?></td>
            <td class="small mono"><?= e((string)$p['nach'] !== '' ? (string)$p['nach'] : '–') ?></td>
            <td class="small"><?= e((string)$p['wer'] !== '' ? (string)$p['wer'] : '–') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
