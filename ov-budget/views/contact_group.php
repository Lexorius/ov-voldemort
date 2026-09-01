<?php
/** @var array $gruppe @var array $members @var array $stats @var array $errors
 *  @var array $kandidaten @var string $suche */
$adressen = [];
foreach ($members as $m) {
    if (trim((string)$m['email']) !== '') {
        $adressen[] = $m['email'];
    }
}
?>
<div class="pagehead">
  <div>
    <h1><?= e($gruppe['name']) ?></h1>
    <p class="muted small">
      <?php if ($gruppe['anlass_am']): ?>Termin: <?= e(de_date($gruppe['anlass_am'])) ?><?php endif; ?>
      <?php if ($gruppe['ort']): ?> · <?= e($gruppe['ort']) ?><?php endif; ?>
      <?php if (!(int)$gruppe['is_active']): ?> · <span class="badge badge--muted">abgeschlossen</span><?php endif; ?>
    </p>
    <?php if ($gruppe['beschreibung']): ?>
      <p><?= nl2br(e((string)$gruppe['beschreibung'])) ?></p>
    <?php endif; ?>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('contacts_export', ['group_id' => $gruppe['id']])) ?>">CSV für Serienbrief</a>
    <a class="btn btn--sec" href="<?= e(url('contact_groups')) ?>">Alle Verteiler</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Auf dem Verteiler</div><div class="stat__value"><?= (int)$stats['anzahl'] ?></div></div>
  <div class="stat"><div class="stat__label">Zugesagte Personen</div>
    <div class="stat__value" style="color:var(--ok)"><?= (int)$stats['personen'] ?></div></div>
  <div class="stat"><div class="stat__label">Mit E-Mail</div><div class="stat__value"><?= (int)$stats['mit_email'] ?></div></div>
  <div class="stat"><div class="stat__label">Mit Anschrift</div><div class="stat__value"><?= (int)$stats['mit_anschrift'] ?></div></div>
</div>

<?php if ($adressen): ?>
  <div class="card card--tight">
    <div class="field">
      <label for="mailliste">E-Mail-Adressen des Verteilers (<?= count($adressen) ?>)</label>
      <textarea id="mailliste" readonly rows="2" onclick="this.select()"><?= e(implode('; ', $adressen)) ?></textarea>
      <small>Zum Kopieren anklicken – gehört ins Blindkopie-Feld, damit die Adressen untereinander nicht sichtbar werden.</small>
    </div>
  </div>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Eingeladene</h2>
    <span class="muted small"><?= count($members) ?></span>
  </div>

  <?php if (!$members): ?>
    <div class="empty">Noch niemand auf diesem Verteiler.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead>
          <tr><th>Name</th><th>Organisation</th><th>Kontakt</th><th>Status</th>
              <th class="num">Pers.</th><th>Bemerkung</th><?php if (can('manage_contacts')): ?><th></th><?php endif; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
          <tr>
            <td>
              <strong><?= e(contact_name($m)) ?></strong>
              <?php if ($m['position']): ?><div class="small muted"><?= e($m['position']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= e($m['organisation'] ?: '–') ?></td>
            <td class="small">
              <?php if ($m['email']): ?><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><br><?php endif; ?>
              <?= e($m['telefon'] ?: $m['mobil'] ?: '') ?>
            </td>
            <td><?= badge($m['status_label'] ? ['label' => $m['status_label'], 'color' => $m['status_color']] : null, 'offen') ?></td>
            <td class="num"><?= (int)$m['personen'] ?></td>
            <td class="small"><?= e($m['teilnahme_notiz'] ?: '–') ?></td>
            <?php if (can('manage_contacts')): ?>
              <td>
                <form method="post" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>"
                      style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="contact_id" value="<?= (int)$m['id'] ?>">
                  <select name="status_id" style="min-height:36px;padding:.3rem;width:auto">
                    <?= list_options('einladung_status', (int)$m['status_id'], '') ?>
                  </select>
                  <input type="number" name="personen" value="<?= (int)$m['personen'] ?>" min="0" max="99"
                         style="min-height:36px;padding:.3rem;width:4rem" title="Anzahl Personen">
                  <input type="text" name="notiz" value="<?= e((string)$m['teilnahme_notiz']) ?>"
                         placeholder="Bemerkung" style="min-height:36px;padding:.3rem;width:9rem">
                  <button class="btn btn--sm" type="submit">OK</button>
                </form>
                <form method="post" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="contact_id" value="<?= (int)$m['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit"
                          data-confirm="Vom Verteiler entfernen? Der Kontakt selbst bleibt bestehen.">Entfernen</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if (can('manage_contacts')): ?>
  <section class="card">
    <h2>Kontakte hinzufügen</h2>

    <form method="post" class="grid2" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_category">
      <div class="field">
        <label for="kategorie_id">Ganze Kategorie übernehmen</label>
        <select id="kategorie_id" name="kategorie_id"><?= list_options('kontakt_kategorie', null) ?></select>
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <button class="btn btn--sec" type="submit">Alle dieser Kategorie hinzufügen</button>
      </div>
    </form>

    <form method="get" class="card card--tight mt" style="box-shadow:none">
      <input type="hidden" name="p" value="contact_group">
      <input type="hidden" name="id" value="<?= (int)$gruppe['id'] ?>">
      <div class="field">
        <label for="suche">Einzeln suchen</label>
        <input type="search" id="suche" name="suche" value="<?= e($suche) ?>" placeholder="Name, Organisation, Ort">
      </div>
      <div><button class="btn btn--sec btn--sm" type="submit">Suchen</button></div>
    </form>

    <?php if (!$kandidaten): ?>
      <p class="muted small">Keine weiteren Kontakte gefunden – alle passenden stehen bereits auf dem Verteiler.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="tablewrap" style="max-height:22rem;overflow-y:auto">
          <table class="data">
            <tbody>
            <?php foreach (array_slice($kandidaten, 0, 200) as $k): ?>
              <tr>
                <td style="width:2.5rem">
                  <input type="checkbox" name="contact_ids[]" value="<?= (int)$k['id'] ?>"
                         id="k<?= (int)$k['id'] ?>">
                </td>
                <td><label for="k<?= (int)$k['id'] ?>"><strong><?= e(contact_name($k)) ?></strong></label></td>
                <td class="small"><?= e($k['organisation'] ?: '–') ?></td>
                <td class="small"><?= e($k['ort'] ?: '') ?></td>
                <td><?= badge($k['kategorie_label'] ? ['label' => $k['kategorie_label'], 'color' => $k['kategorie_color']] : null, '–') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($kandidaten) > 200): ?>
          <p class="small muted">Es werden die ersten 200 Treffer gezeigt – bitte die Suche einschränken.</p>
        <?php endif; ?>
        <div class="btnrow mt"><button class="btn" type="submit">Ausgewählte hinzufügen</button></div>
      </form>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Verteiler bearbeiten</h2>
    <form method="post" class="form" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="grid3">
        <div class="field">
          <label for="name">Name *</label>
          <input type="text" id="name" name="name" required value="<?= e($gruppe['name']) ?>">
        </div>
        <div class="field">
          <label for="anlass_am">Termin</label>
          <input type="date" id="anlass_am" name="anlass_am" value="<?= e((string)$gruppe['anlass_am']) ?>">
        </div>
        <div class="field">
          <label for="ort">Ort</label>
          <input type="text" id="ort" name="ort" value="<?= e((string)$gruppe['ort']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="beschreibung">Beschreibung</label>
        <textarea id="beschreibung" name="beschreibung"><?= e((string)$gruppe['beschreibung']) ?></textarea>
      </div>
      <div class="field field--check">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$gruppe['is_active'] ? 'checked' : '' ?>>
        <label for="is_active">Aktiv</label>
      </div>
      <div class="btnrow"><button class="btn" type="submit">Speichern</button></div>
    </form>
  </section>

  <form method="post" class="card" action="<?= e(url('contact_group', ['id' => $gruppe['id']])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button class="btn btn--danger" type="submit"
            data-confirm="Verteiler löschen? Die Kontakte selbst bleiben erhalten.">Verteiler löschen</button>
  </form>
<?php endif; ?>
