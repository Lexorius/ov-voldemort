<?php
/** @var array $kontakte @var array $status @var array $kategorien @var bool $kiAktiv @var bool $ruhe */
$ohneKategorie = count(array_filter($kontakte, static fn($c) => empty($c['kategorie_id'])));
?>
<div class="pagehead">
  <div>
    <h1>KI-Vorschläge</h1>
    <p>Die KI im eigenen Netz ordnet Kontakte den vorhandenen Kontaktkategorien zu – nur aus der Auswahlliste,
       nie eine neue. Sie schlägt vor, übernommen wird hier oder im Kontakt von Hand.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Zu den Kontakten</a>
    <?php if (can('admin')): ?>
      <a class="btn btn--sec" href="<?= e(url('admin_lists', ['key' => 'kontakt_kategorie'])) ?>">Kategorien pflegen</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Mit Kategorie</div><div class="stat__value"><?= (int)$status['mit_kategorie'] ?></div></div>
  <div class="stat"><div class="stat__label">Ohne Kategorie</div><div class="stat__value"><?= (int)$status['ohne_kategorie'] ?></div></div>
  <div class="stat"><div class="stat__label">Offene Vorschläge</div><div class="stat__value" style="color:#7c3aed"><?= (int)$status['vorschlaege'] ?></div></div>
  <div class="stat"><div class="stat__label">In der Warteschlange</div><div class="stat__value"><?= (int)$status['offen'] ?></div>
    <?php if ((int)$status['fehler'] > 0): ?><div class="stat__hint"><?= (int)$status['fehler'] ?> fehlgeschlagen – Verwaltung → KI-Auswertung</div><?php endif; ?></div>
</div>

<section class="card">
  <div class="card__head">
    <div>
      <h2 style="margin:0">Vorschläge anfordern</h2>
      <div class="small muted">
        <?php if (!$kiAktiv): ?>
          Die KI-Auswertung für Kontakte ist aus – einschalten unter Verwaltung → Einstellungen → KI-Auswertung.
        <?php elseif (!$kategorien): ?>
          Die Auswahlliste „Kontaktkategorien" ist leer – ohne Liste gibt es nichts vorzuschlagen.
        <?php elseif ($ruhe): ?>
          Ollama war zuletzt nicht erreichbar – die Warteschlange ruht ein paar Minuten und läuft dann weiter.
        <?php else: ?>
          Reiht alle aktiven Kontakte ohne Kategorie ein. Der minütliche Abruf fragt die KI nach und nach;
          die Vorschläge erscheinen hier und am Kontakt. Einzelne Kontakte mit Kategorie: im Kontakt „Kategorie vorschlagen lassen".
        <?php endif; ?>
      </div>
    </div>
    <form method="post" class="inline-form" action="<?= e(url('contacts_ki')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="alle_einreihen">
      <button class="btn" type="submit" <?= $kiAktiv && $kategorien ? '' : 'disabled' ?>>✨ Alle ohne Kategorie einreihen</button>
    </form>
  </div>
  <?php if ($kategorien): ?>
    <div class="chips mt">
      <?php foreach ($kategorien as $k): ?>
        <a class="chip" href="<?= e(url('contacts', ['kategorie_id' => (int)$k['id']])) ?>" style="border-color:<?= e((string)$k['color']) ?>"
           title="<?= e((string)$k['description']) ?>"><?= e((string)$k['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <p class="small muted mb0 mt">Die Beschreibung je Kategorie (Verwaltung → Auswahllisten) bekommt die KI als Erklärung mit.</p>
  <?php endif; ?>
</section>

<?php if (!$kontakte): ?>
  <div class="card"><div class="empty">Keine offenen Vorschläge.</div></div>
<?php else: ?>
  <div class="card__head">
    <h2 style="margin:0">Offene Vorschläge (<?= count($kontakte) ?>)</h2>
    <div class="btnrow">
      <?php if ($ohneKategorie > 0): ?>
        <form method="post" class="inline-form" action="<?= e(url('contacts_ki')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="alle_uebernehmen">
          <button class="btn btn--sm" type="submit" data-confirm="<?= e(sprintf('%d Vorschläge übernehmen? Nur bei Kontakten ohne Kategorie; gesetzte Kategorien bleiben.', $ohneKategorie)) ?>">
            <?= (int)$ohneKategorie ?> ohne Kategorie übernehmen</button>
        </form>
      <?php endif; ?>
      <form method="post" class="inline-form" action="<?= e(url('contacts_ki')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="alle_verwerfen">
        <button class="btn btn--sec btn--sm" type="submit" data-confirm="Alle offenen Vorschläge verwerfen? Die Kategorien der Kontakte bleiben unverändert.">Alle verwerfen</button>
      </form>
    </div>
  </div>
  <div class="card mt">
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Kontakt</th><th>Organisation</th><th>Bisher</th><th>Vorschlag</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($kontakte as $c): ?>
          <tr<?= (int)$c['is_active'] ? '' : ' class="is-muted"' ?>>
            <td>
              <strong><a class="item__link" href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>#kategorie"><?= e(contact_name($c)) ?></a></strong>
              <?php if (!(int)$c['is_active']): ?> <span class="badge badge--muted">inaktiv</span><?php endif; ?>
              <?php if ($c['position']): ?><div class="small muted"><?= e($c['position']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= e($c['organisation'] ?: '–') ?><?php if ($c['ort']): ?><div class="muted"><?= e($c['ort']) ?></div><?php endif; ?></td>
            <td><?= badge($c['kategorie_label'] ? ['label' => $c['kategorie_label'], 'color' => $c['kategorie_color']] : null, 'ohne Kategorie') ?></td>
            <td>
              <span class="badge" style="background:<?= e((string)$c['vorschlag_color']) ?>">✨ <?= e((string)$c['vorschlag_label']) ?></span>
              <div class="small muted"><?= e((string)$c['vorschlag_modell']) ?> · <?= e(de_datetime((string)$c['vorschlag_am'])) ?></div>
            </td>
            <td class="nowrap">
              <form method="post" class="inline-form" action="<?= e(url('contacts_ki')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn--sm" type="submit" name="action" value="uebernehmen">Übernehmen</button>
                <button class="btn btn--sec btn--sm" type="submit" name="action" value="verwerfen">Verwerfen</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
