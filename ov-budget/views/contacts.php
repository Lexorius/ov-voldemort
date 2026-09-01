<?php
/** @var array $rows @var array $filters @var array $verteiler */
$mitEmail = 0;
$mitAnschrift = 0;
foreach ($rows as $r) {
    if (trim((string)$r['email']) !== '') {
        $mitEmail++;
    }
    if (trim((string)$r['strasse']) !== '' && trim((string)$r['ort']) !== '') {
        $mitAnschrift++;
    }
}
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('kontakte_modul_name', 'Kontakte')) ?></h1>
    <p><?= nl2br(e((string)setting('kontakte_intro', ''))) ?></p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_contacts')): ?>
      <a class="btn" href="<?= e(url('contact_edit')) ?>">+ Kontakt</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('contact_groups')) ?>">Verteiler</a>
    <a class="btn btn--sec" href="<?= e(url('contacts_export', array_diff_key($_GET, ['p' => 1]))) ?>">CSV</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Kontakte</div><div class="stat__value"><?= count($rows) ?></div></div>
  <div class="stat"><div class="stat__label">Mit E-Mail</div><div class="stat__value"><?= $mitEmail ?></div></div>
  <div class="stat"><div class="stat__label">Mit Anschrift</div><div class="stat__value"><?= $mitAnschrift ?></div></div>
  <div class="stat"><div class="stat__label">Verteiler</div><div class="stat__value"><?= count($verteiler) ?></div></div>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="contacts">
  <div class="filters">
    <div class="field">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e((string)$filters['q']) ?>"
             placeholder="Name, Organisation, Ort, E-Mail">
    </div>
    <div class="field">
      <label for="kategorie_id">Kategorie</label>
      <select id="kategorie_id" name="kategorie_id"><?= list_options('kontakt_kategorie', $filters['kategorie_id'], 'alle') ?></select>
    </div>
    <div class="field">
      <label for="sort">Sortierung</label>
      <select id="sort" name="sort">
        <?php foreach (['name' => 'Nachname', 'org' => 'Organisation', 'ort' => 'Ort', 'neu' => 'Neueste'] as $k => $lbl): ?>
          <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn btn--sec" type="submit">Filtern</button>
    </div>
  </div>
  <div class="chips mt">
    <a class="chip<?= $filters['nur_mit_email'] ? ' is-active' : '' ?>"
       href="<?= e(url('contacts', array_merge(array_diff_key($_GET, ['p' => 1]), ['mit_email' => $filters['nur_mit_email'] ? '' : '1']))) ?>">nur mit E-Mail</a>
    <a class="chip<?= $filters['aktiv'] === 'alle' ? ' is-active' : '' ?>"
       href="<?= e(url('contacts', array_merge(array_diff_key($_GET, ['p' => 1]), ['aktiv' => $filters['aktiv'] === 'alle' ? '' : 'alle']))) ?>">inkl. inaktive</a>
    <?php if ($filters['q'] || $filters['kategorie_id']): ?>
      <a class="chip" href="<?= e(url('contacts')) ?>">Filter zurücksetzen</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($mitEmail > 0): ?>
  <?php
  $adressen = [];
  foreach ($rows as $r) {
      if (trim((string)$r['email']) !== '') {
          $adressen[] = $r['email'];
      }
  }
  ?>
  <div class="card card--tight">
    <div class="field">
      <label for="mailliste">E-Mail-Adressen der angezeigten Kontakte (<?= count($adressen) ?>)</label>
      <textarea id="mailliste" readonly rows="2"
                onclick="this.select()"><?= e(implode('; ', $adressen)) ?></textarea>
      <small>Zum Kopieren anklicken – für das Blindkopie-Feld im Mailprogramm.</small>
    </div>
  </div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Keine Kontakte gefunden.
    <?php if (can('manage_contacts')): ?><br><a href="<?= e(url('contact_edit')) ?>">Ersten Kontakt anlegen</a><?php endif; ?>
  </div></div>
<?php else: ?>
  <div class="card only-mobile">
    <div class="itemlist">
      <?php foreach ($rows as $c): ?>
        <a class="item" style="border-left-color:<?= e($c['kategorie_color'] ?: '#94a3b8') ?>"
           href="<?= can('manage_contacts') ? e(url('contact_edit', ['id' => $c['id']])) : '#' ?>">
          <div class="item__top">
            <div style="min-width:0">
              <div class="item__title"><?= e(contact_name($c)) ?></div>
              <div class="item__sub">
                <?= e($c['organisation'] ?: '–') ?>
                <?php if ($c['ort']): ?> · <?= e($c['ort']) ?><?php endif; ?>
              </div>
            </div>
          </div>
          <div class="item__meta">
            <?= badge($c['kategorie_label'] ? ['label' => $c['kategorie_label'], 'color' => $c['kategorie_color']] : null, 'ohne Kategorie') ?>
            <?php if (!(int)$c['is_active']): ?><span class="badge badge--muted">inaktiv</span><?php endif; ?>
            <?php if ((int)$c['verteiler'] > 0): ?>
              <span class="badge badge--outline"><?= (int)$c['verteiler'] ?>× Verteiler</span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card hide-mobile">
    <div class="tablewrap">
      <table class="data">
        <thead>
          <tr><th>Name</th><th>Organisation</th><th>Kategorie</th><th>Ort</th>
              <th>E-Mail</th><th>Telefon</th><th class="num">Verteiler</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td>
              <strong><?= e(contact_name($c)) ?></strong>
              <?php if (!(int)$c['is_active']): ?> <span class="badge badge--muted">inaktiv</span><?php endif; ?>
              <?php if ($c['position']): ?><div class="small muted"><?= e($c['position']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= e($c['organisation'] ?: '–') ?></td>
            <td><?= badge($c['kategorie_label'] ? ['label' => $c['kategorie_label'], 'color' => $c['kategorie_color']] : null, '–') ?></td>
            <td class="small"><?= e(trim($c['plz'] . ' ' . $c['ort']) ?: '–') ?></td>
            <td class="small">
              <?php if ($c['email']): ?>
                <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
              <?php else: ?>–<?php endif; ?>
            </td>
            <td class="small nowrap"><?= e($c['telefon'] ?: $c['mobil'] ?: '–') ?></td>
            <td class="num small"><?= (int)$c['verteiler'] ?></td>
            <td><?php if (can('manage_contacts')): ?>
              <a class="btn btn--sec btn--sm" href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>">Bearbeiten</a>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
