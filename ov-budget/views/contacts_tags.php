<?php
/** @var array $kontakte @var array $status @var array $tags @var bool $kiAktiv @var bool $ruhe */
?>
<div class="pagehead">
  <div>
    <h1>Schlagwörter</h1>
    <p>Wofür ein Kontakt gebraucht wird – Ehrengast, Presseverteiler, Spender. Die Liste ist fest; die KI im
       eigenen Netz schlägt je Kontakt passende Einträge daraus vor, übernommen wird nur von Hand.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Zu den Kontakten</a>
    <?php if (can('admin')): ?>
      <a class="btn btn--sec" href="<?= e(url('admin_lists', ['key' => 'kontakt_tag'])) ?>">Liste pflegen</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Mit Schlagwörtern</div><div class="stat__value"><?= (int)$status['mit_tags'] ?></div></div>
  <div class="stat"><div class="stat__label">Noch ohne</div><div class="stat__value"><?= (int)$status['ohne_tags'] ?></div></div>
  <div class="stat"><div class="stat__label">Mit Vorschlägen</div><div class="stat__value" style="color:#7c3aed"><?= (int)$status['vorschlaege'] ?></div></div>
  <div class="stat"><div class="stat__label">In der Warteschlange</div><div class="stat__value"><?= (int)$status['offen'] ?></div>
    <?php if ((int)$status['fehler'] > 0): ?><div class="stat__hint"><?= (int)$status['fehler'] ?> fehlgeschlagen</div><?php endif; ?></div>
</div>

<section class="card">
  <div class="card__head">
    <div>
      <h2 style="margin:0">Vorschläge anfordern</h2>
      <div class="small muted">
        <?php if (!$kiAktiv): ?>
          Die KI-Auswertung für Kontakte ist aus – einschalten unter Verwaltung → Einstellungen → KI-Auswertung.
        <?php elseif (!$tags): ?>
          Die Auswahlliste „Schlagwörter (Kontakte)" ist leer. Erst Einträge anlegen, dann kann die KI daraus wählen.
        <?php elseif ($ruhe): ?>
          Ollama war zuletzt nicht erreichbar – die Warteschlange ruht ein paar Minuten und läuft dann weiter.
        <?php else: ?>
          Reiht alle aktiven Kontakte ohne Schlagwörter ein. Der minütliche Abruf fragt die KI nach und nach;
          die Vorschläge erscheinen hier und am Kontakt.
        <?php endif; ?>
      </div>
    </div>
    <form method="post" class="inline-form" action="<?= e(url('contacts_tags')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="alle_einreihen">
      <button class="btn" type="submit" <?= $kiAktiv && $tags ? '' : 'disabled' ?>>✨ Alle ohne Schlagwörter einreihen</button>
    </form>
  </div>
  <?php if ($tags): ?>
    <div class="chips mt">
      <?php foreach ($tags as $t): ?>
        <a class="chip" href="<?= e(url('contacts', ['tag_id' => (int)$t['id']])) ?>" style="border-color:<?= e((string)$t['color']) ?>"
           title="<?= e((string)$t['description']) ?>"><?= e((string)$t['label']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!$kontakte): ?>
  <div class="card"><div class="empty">Keine offenen Vorschläge.</div></div>
<?php else: ?>
  <div class="card__head">
    <h2 style="margin:0">Offene Vorschläge (<?= count($kontakte) ?>)</h2>
    <form method="post" class="inline-form" action="<?= e(url('contacts_tags')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="alle_verwerfen">
      <button class="btn btn--sec btn--sm" type="submit" data-confirm="Alle offenen Vorschläge verwerfen? Die Schlagwörter der Kontakte bleiben unverändert.">Alle verwerfen</button>
    </form>
  </div>
  <div class="itemlist mt">
    <?php foreach ($kontakte as $c): ?>
      <form method="post" class="item" action="<?= e(url('contacts_tags')) ?>" style="border-left-color:<?= e($c['kategorie_color'] ?: '#94a3b8') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="entscheiden">
        <input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>">
        <div class="item__top">
          <div style="min-width:0">
            <div class="item__title"><a class="item__link" href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>#schlagwoerter"><?= e(contact_name($c)) ?></a></div>
            <div class="item__sub">
              <?= e($c['organisation'] ?: '–') ?>
              <?php if ($c['position']): ?> · <?= e($c['position']) ?><?php endif; ?>
              <?php if ($c['ort']): ?> · <?= e($c['ort']) ?><?php endif; ?>
              <?php if ($c['kategorie_label']): ?> · <?= e($c['kategorie_label']) ?><?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($c['tags']): ?>
          <div class="small muted mt">Bisher: <?= e(implode(', ', array_column($c['tags'], 'label'))) ?></div>
        <?php endif; ?>
        <div class="chips mt">
          <?php foreach ($c['vorschlaege_liste'] as $v): ?>
            <label class="chip" style="border-color:<?= e((string)$v['color']) ?>">
              <input type="checkbox" name="vorschlag[]" value="<?= (int)$v['id'] ?>" checked> ✨ <?= e((string)$v['label']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="btnrow mt">
          <button class="btn btn--sm" type="submit">Angehakte übernehmen</button>
          <button class="btn btn--sec btn--sm" type="submit" name="vorschlag" value="" formnovalidate
                  onclick="this.form.querySelectorAll('input[name=\'vorschlag[]\']').forEach(function (i) { i.checked = false; })">Alle verwerfen</button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
