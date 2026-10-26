<?php
/**
 * @var string $title @var array $getraenke @var array $gruppen @var array $lieferanten @var array $matrix @var array $summe
 * @var array $meldungen @var array $bedarf @var array $offen @var array $erledigt @var array $lagerplaetze @var array $plaetzeAlle
 * @var bool $darf @var bool $mailAktiv @var array $connectoren
 */
$lieferantName = static fn(int $id): string => $id > 0 ? (string)($lieferanten[$id]['name'] ?? 'Lieferant #' . $id) : 'ohne Lieferanten';
?>
<div class="pagehead">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Wer am Lagerplatz den QR-Code scannt, meldet „leer" oder „wird knapp". Daraus und aus dem Mindestbestand
       entsteht je Lieferant ein Bedarf; eine verantwortliche Person prüft die Bestellung und schickt sie per E-Mail.
       Lieferung geht ins Lager, die Rechnung ins Budget.</p>
  </div>
  <?php if ($darf): ?>
    <div class="btnrow">
      <a class="btn" href="<?= e(url('getraenke_bestellung_edit')) ?>">+ Bestellung</a>
      <a class="btn btn--sec" href="<?= e(url('getraenk_edit')) ?>">+ Getränk</a>
      <a class="btn btn--sec" href="<?= e(url('getraenke_lieferant_edit')) ?>">+ Lieferant</a>
      <?php if ($connectoren): ?>
        <form method="post" action="<?= e(url('getraenke_action')) ?>" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="abholen">
          <button class="btn btn--sec" type="submit">Meldungen abholen</button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($darf && !$mailAktiv): ?>
  <div class="alert alert--info">Bestellungen gehen per E-Mail an den Lieferanten – dafür muss der Versand in der
    <a href="<?= e(url('admin_mail')) ?>">Verwaltung → E-Mail</a> eingerichtet sein. Entwürfe lassen sich schon jetzt anlegen.</div>
<?php endif; ?>

<section class="card" id="bedarf">
  <div class="card__head">
    <h2>Bedarf <?php if ($meldungen): ?><span class="badge" style="background:#b91c1c"><?= count($meldungen) ?> Meldung(en)</span><?php endif; ?></h2>
    <span class="small muted">aus Leermeldungen und Mindestbestand, je Lieferant</span>
  </div>
  <?php if (!$bedarf && !$meldungen): ?>
    <div class="empty">Nichts fehlt – kein Getränk unter dem Mindestbestand, keine offene Meldung.</div>
  <?php else: ?>
    <?php foreach ($bedarf as $lid => $vorschlag): ?>
      <div class="getraenke-bedarf">
        <div class="getraenke-bedarf__kopf">
          <strong><?= e($lieferantName((int)$lid)) ?></strong>
          <?php if ($darf): ?>
            <a class="btn btn--sm" href="<?= e(url('getraenke_bestellung_edit', ['lieferant_id' => $lid])) ?>">Bestellung vorbereiten</a>
          <?php endif; ?>
        </div>
        <ul class="getraenke-bedarf__liste">
          <?php foreach ($vorschlag as $gid => $n): $g = null; foreach ($getraenke as $x) { if ((int)$x['id'] === (int)$gid) { $g = $x; } } ?>
            <li><?= e((string)($g['name'] ?? '')) ?>: <strong><?= (int)$n ?> × <?= e((string)($g['gebinde'] ?? '')) ?></strong>
              <span class="small muted">(Bestand <?= (int)($summe[(int)$gid] ?? 0) ?>, Mindestbestand <?= (int)($g['mindestbestand'] ?? 0) ?>)</span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
    <?php if ($meldungen): ?>
      <div class="tablewrap">
        <table class="data">
          <thead><tr><th>Gemeldet</th><th>Getränk</th><th>Platz</th><th>Stand</th><th>Von</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($meldungen as $m): ?>
            <tr>
              <td class="small nowrap"><?= e(de_datetime((string)$m['gemeldet_am'])) ?></td>
              <td><?= e((string)$m['getraenk_name']) ?> <span class="small muted"><?= e($lieferantName((int)($m['lieferant_id'] ?? 0))) ?></span></td>
              <td><a href="<?= e(url('getraenke_lager', ['standort_id' => $m['standort_id']])) ?>"><?= e((string)$m['standort_name']) ?></a></td>
              <td><?= getraenke_stand_badge((string)$m['stand']) ?><?= $m['anzahl'] !== null ? ' <span class="small muted">noch ' . (int)$m['anzahl'] . '</span>' : '' ?></td>
              <td class="small"><?= e((string)$m['melder']) ?></td>
              <td><?php if ($darf): ?>
                <form method="post" action="<?= e(url('getraenke_action')) ?>" class="inline-form"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="meldung_erledigt"><input type="hidden" name="meldung_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit" title="ohne Bestellung erledigen">Erledigt</button></form>
              <?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="card" id="lager">
  <div class="card__head">
    <h2>Lager</h2>
    <div class="btnrow">
      <?php foreach ($lagerplaetze as $p): ?>
        <a class="btn btn--sec btn--sm" href="<?= e(url('getraenke_lager', ['standort_id' => $p['id']])) ?>"><?= e((string)$p['name']) ?></a>
      <?php endforeach; ?>
      <?php if ($darf): ?>
        <form method="get" action="<?= e(url('getraenke_lager')) ?>" class="inline-form">
          <input type="hidden" name="p" value="getraenke_lager">
          <select name="standort_id" aria-label="Platz" onchange="this.form.submit()">
            <option value="">+ Platz als Lager …</option>
            <?php foreach ($plaetzeAlle as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e((string)$p['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$matrix['zeilen']): ?>
    <div class="empty">Noch keine Getränkeart angelegt.</div>
  <?php elseif (!$matrix['plaetze']): ?>
    <div class="empty">Noch kein Lager – oben einen Stell- oder Lagerplatz wählen und dort den Bestand eintragen.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Getränk</th>
          <?php foreach ($matrix['plaetze'] as $sid => $name): ?><th class="num"><a href="<?= e(url('getraenke_lager', ['standort_id' => $sid])) ?>"><?= e($name) ?></a></th><?php endforeach; ?>
          <th class="num">Gesamt</th><th class="num">Mindest</th></tr></thead>
        <tbody>
        <?php foreach ($getraenke as $g): if ((int)$g['is_active'] !== 1 || !isset($matrix['zeilen'][(int)$g['id']])) { continue; } $z = $matrix['zeilen'][(int)$g['id']]; $knapp = $z['summe'] < (int)$g['mindestbestand']; ?>
          <tr<?= $knapp ? ' style="color:var(--bad)"' : '' ?>>
            <td><?= e((string)$g['name']) ?> <span class="small muted"><?= e((string)$g['gebinde']) ?></span></td>
            <?php foreach ($matrix['plaetze'] as $sid => $name): ?><td class="num"><?= isset($z['je'][$sid]) ? (int)$z['je'][$sid] : '<span class="muted">–</span>' ?></td><?php endforeach; ?>
            <td class="num"><strong><?= (int)$z['summe'] ?></strong></td>
            <td class="num muted"><?= (int)$g['mindestbestand'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="bestellungen">
  <div class="card__head"><h2>Bestellungen</h2><span class="small muted">Entwurf → bestellt → geliefert → abgerechnet</span></div>
  <?php if (!$offen && !$erledigt): ?>
    <div class="empty">Noch keine Bestellung.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Nummer</th><th>Lieferant</th><th>Absender</th><th class="num">Gebinde</th><th class="num">Summe</th><th>Status</th><th>Wann</th></tr></thead>
        <tbody>
        <?php foreach (array_merge($offen, $erledigt) as $b): ?>
          <tr<?= (string)$b['status'] === 'abgerechnet' ? ' class="is-muted"' : '' ?>>
            <td><a href="<?= e(url('getraenke_bestellung', ['id' => $b['id']])) ?>"><strong><?= e((string)$b['nummer']) ?></strong></a></td>
            <td><?= e((string)($b['lieferant_name'] ?? '–')) ?></td>
            <td class="small"><?= trim((string)($b['absender_name'] ?? '')) !== '' ? e((string)$b['absender_name']) : '<span class="muted">–</span>' ?></td>
            <td class="num"><?= (int)$b['gebinde_anzahl'] ?></td>
            <td class="num"><?= (float)$b['summe'] > 0 ? e(money((float)$b['summe'])) : '<span class="muted">–</span>' ?></td>
            <td><?= getraenke_status_badge((string)$b['status']) ?></td>
            <td class="small nowrap"><?= e(de_datetime((string)($b['gesendet_am'] ?: $b['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="absender">
  <div class="card__head"><h2>Absender</h2>
    <?php if ($darf): ?><a class="btn btn--sec btn--sm" href="<?= e(url('getraenke_absender_edit')) ?>">+ Absender</a><?php endif; ?></div>
  <?php if (!$absender): ?>
    <p class="small muted">In wessen Namen bestellt wird – Ortsverband, Helferverein, Regionalstelle; aus den THW-Kontakten
      übernehmbar. Ohne Absender steht der Name des Ortsverbands aus den Einstellungen in der Bestellung.</p>
  <?php else: ?>
    <ul class="getraenke-bedarf__liste">
      <?php foreach ($absender as $a): ?>
        <li<?= (int)$a['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
          <?php if ($darf): ?><a href="<?= e(url('getraenke_absender_edit', ['id' => $a['id']])) ?>"><?= e((string)$a['name']) ?></a><?php else: ?><?= e((string)$a['name']) ?><?php endif; ?>
          <span class="small muted"><?= e(str_replace("\n", ', ', trim((string)$a['anschrift']))) ?><?= trim((string)$a['email']) !== '' ? ' · ' . e((string)$a['email']) : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card" id="getraenke">
  <div class="card__head"><h2>Getränke und Lieferanten</h2>
    <?php if ($darf): ?><a class="btn btn--sec btn--sm" href="<?= e(url('getraenk_edit')) ?>">+ Getränk</a><?php endif; ?></div>
  <?php if (!$getraenke && !$lieferanten): ?>
    <div class="empty">Erst einen Lieferanten anlegen, dann die Getränke – jedes Getränk gehört zu einem Lieferanten.</div>
  <?php endif; ?>
  <?php foreach ($lieferanten + ($gruppen[0] ?? null ? [0 => ['id' => 0, 'name' => 'ohne Lieferanten', 'email' => '', 'is_active' => 1]] : []) as $lid => $l): $liste = $gruppen[(int)$lid] ?? []; ?>
    <div class="getraenke-lieferant" id="lieferanten">
      <div class="getraenke-bedarf__kopf">
        <div><strong><?= e((string)$l['name']) ?></strong>
          <?php if ((int)$l['is_active'] !== 1): ?><span class="badge badge--muted">nicht aktiv</span><?php endif; ?>
          <?php if ((int)$lid > 0): ?>
            <span class="small muted"><?= trim((string)$l['email']) !== '' ? e((string)$l['email']) : '<span style="color:var(--warn)">keine E-Mail – Bestellen nicht möglich</span>' ?><?= trim((string)($l['telefon'] ?? '')) !== '' ? ' · ' . e((string)$l['telefon']) : '' ?><?= getraenke_lieferant_anschrift($l) !== '' ? ' · ' . e(getraenke_lieferant_anschrift($l)) : '' ?></span>
          <?php endif; ?></div>
        <?php if ($darf && (int)$lid > 0): ?>
          <span class="btnrow"><a class="btn btn--sec btn--sm" href="<?= e(url('getraenke_lieferant_edit', ['id' => $lid])) ?>">Bearbeiten</a>
            <a class="btn btn--sec btn--sm" href="<?= e(url('getraenk_edit', ['lieferant_id' => $lid])) ?>">+ Getränk</a></span>
        <?php endif; ?>
      </div>
      <?php if (!$liste): ?><div class="small muted">Noch kein Getränk.</div><?php else: ?>
        <ul class="getraenke-bedarf__liste">
          <?php foreach ($liste as $g): ?>
            <li<?= (int)$g['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
              <?php if ($darf): ?><a href="<?= e(url('getraenk_edit', ['id' => $g['id']])) ?>"><?= e((string)$g['name']) ?></a><?php else: ?><?= e((string)$g['name']) ?><?php endif; ?>
              <span class="small muted"><?= e((string)$g['gebinde']) ?><?= (float)$g['preis'] > 0 ? ' · ' . e(money((float)$g['preis'])) : '' ?> · Mindestbestand <?= (int)$g['mindestbestand'] ?> · Bestand <?= (int)($summe[(int)$g['id']] ?? 0) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
