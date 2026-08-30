<?php
/** @var array $wish @var array $anlagen @var array $kommentare @var bool $meinVote @var array $todos */
$extra = wish_extra($wish);
$extraFields = wish_extra_fields();
$brutto = (float)$wish['netto_gesamt'] * (1 + ((float)$wish['mwst_satz'] / 100));
?>
<div class="pagehead">
  <div>
    <h1><?= e($wish['bezeichnung']) ?></h1>
    <p class="muted small">
      Wunsch #<?= (int)$wish['id'] ?> · angelegt am <?= e(de_datetime($wish['created_at'])) ?>
      <?php if ($wish['ersteller']): ?> von <?= e($wish['ersteller']) ?><?php endif; ?>
      <?php if ($wish['source'] === 'divera'): ?> · aus Divera 24/7 übernommen<?php endif; ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if (can('edit_wish', $wish)): ?>
      <a class="btn" href="<?= e(url('wish_edit', ['id' => $wish['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('wishes')) ?>">Zurück</a>
  </div>
</div>

<div class="card">
  <div class="item__meta" style="margin-top:0">
    <?= badge($wish['status_label'] ? ['label' => $wish['status_label'], 'color' => $wish['status_color']] : null, 'ohne Status') ?>
    <?= badge($wish['dring_label'] ? ['label' => $wish['dring_label'], 'color' => $wish['dring_color']] : null, 'ohne Dringlichkeit') ?>
    <?php if ((int)$wish['nice_to_have']): ?><span class="badge badge--nice">nice to have</span><?php endif; ?>
    <?php if ($wish['fachgruppe_label']): ?><span class="badge badge--outline"><?= e($wish['fachgruppe_label']) ?></span><?php endif; ?>
    <?php if ($wish['kategorie_label']): ?><span class="badge badge--outline"><?= e($wish['kategorie_label']) ?></span><?php endif; ?>

    <?php if (can('vote')): ?>
      <form method="post" action="<?= e(url('wish_action')) ?>" class="inline-form" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="vote">
        <input type="hidden" name="id" value="<?= (int)$wish['id'] ?>">
        <button class="vote<?= $meinVote ? ' is-on' : '' ?>" type="submit">
          👍 <?= (int)$wish['votes'] ?> <?= $meinVote ? 'unterstützt' : 'unterstützen' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>

  <dl class="dl mt">
    <div class="dl__item">
      <div class="dl__label">Anzahl</div>
      <div class="dl__value"><?= e(rtrim(rtrim(number_format((float)$wish['anzahl'], 2, ',', '.'), '0'), ',')) ?>
        <?= e($wish['einheit_label'] ?: '') ?></div>
    </div>
    <div class="dl__item">
      <div class="dl__label">Nettobetrag je Einheit</div>
      <div class="dl__value"><?= e(money((float)$wish['netto_einzel'])) ?></div>
    </div>
    <div class="dl__item">
      <div class="dl__label">Nettobetrag gesamt</div>
      <div class="dl__value"><?= e(money((float)$wish['netto_gesamt'])) ?></div>
    </div>
    <div class="dl__item">
      <div class="dl__label">Bruttobetrag (<?= e(rtrim(rtrim(number_format((float)$wish['mwst_satz'], 2, ',', '.'), '0'), ',')) ?>&nbsp;% MwSt)</div>
      <div class="dl__value"><?= e(money($brutto)) ?></div>
    </div>
    <?php if ($wish['benoetigt_bis']): ?>
      <div class="dl__item"><div class="dl__label">Benötigt bis</div>
        <div class="dl__value"><?= e(de_date($wish['benoetigt_bis'])) ?></div></div>
    <?php endif; ?>
    <?php if ($wish['budget_name']): ?>
      <div class="dl__item"><div class="dl__label">Budgettopf</div>
        <div class="dl__value"><?= e($wish['budget_jahr'] . ' · ' . $wish['budget_name']) ?></div></div>
    <?php endif; ?>
    <?php if ($wish['lieferant']): ?>
      <div class="dl__item"><div class="dl__label">Lieferant</div><div class="dl__value"><?= e($wish['lieferant']) ?></div></div>
    <?php endif; ?>
    <?php if ($wish['artikelnummer']): ?>
      <div class="dl__item"><div class="dl__label">Artikelnummer</div><div class="dl__value"><?= e($wish['artikelnummer']) ?></div></div>
    <?php endif; ?>
    <?php if ($wish['antragsteller']): ?>
      <div class="dl__item"><div class="dl__label">Antragsteller</div><div class="dl__value"><?= e($wish['antragsteller']) ?></div></div>
    <?php endif; ?>
    <?php if ($wish['link']): ?>
      <div class="dl__item"><div class="dl__label">Link</div>
        <div class="dl__value"><a href="<?= e($wish['link']) ?>" target="_blank" rel="noopener noreferrer">Produktseite öffnen</a></div></div>
    <?php endif; ?>
    <?php foreach ($extraFields as $key => $def): $v = (string)($extra[$key] ?? ''); if ($v === '') continue; ?>
      <div class="dl__item"><div class="dl__label"><?= e($def['label']) ?></div>
        <div class="dl__value"><?= $def['type'] === 'bool' ? ($v === '1' ? 'ja' : 'nein') : e($v) ?></div></div>
    <?php endforeach; ?>
  </dl>

  <?php if ($wish['beschreibung']): ?>
    <h3 class="mt">Beschreibung</h3>
    <div class="comment__body"><?= e($wish['beschreibung']) ?></div>
  <?php endif; ?>
  <?php if ($wish['begruendung']): ?>
    <h3 class="mt">Begründung</h3>
    <div class="comment__body"><?= e($wish['begruendung']) ?></div>
  <?php endif; ?>
</div>

<?php if (can('change_status') || can('manage_wishes')): ?>
  <div class="card">
    <h2>Schnellaktionen</h2>
    <form method="post" action="<?= e(url('wish_action')) ?>" class="grid3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="id" value="<?= (int)$wish['id'] ?>">
      <div class="field">
        <label for="status_id">Status setzen</label>
        <select id="status_id" name="status_id"><?= list_options('wunsch_status', (int)$wish['status_id'], '') ?></select>
      </div>
      <?php if (can('manage_wishes')): ?>
        <div class="field">
          <label for="prioritaet">Priorität</label>
          <input type="number" id="prioritaet" name="prioritaet" value="<?= (int)$wish['prioritaet'] ?>">
        </div>
      <?php endif; ?>
      <div class="field">
        <label>&nbsp;</label>
        <button class="btn" type="submit">Übernehmen</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card__head">
    <h2>Angebote und Anlagen</h2>
    <?php if (can('edit_wish', $wish)): ?>
      <a class="btn btn--sec btn--sm" href="<?= e(url('wish_edit', ['id' => $wish['id']])) ?>#anlagen">Hinzufügen</a>
    <?php endif; ?>
  </div>
  <?php if (!$anlagen): ?>
    <div class="empty">Noch keine Anlagen hinterlegt.</div>
  <?php else: ?>
    <div class="filelist">
      <?php foreach ($anlagen as $a): ?>
        <div class="file">
          <div style="min-width:0">
            <div class="file__name"><a href="<?= e(url('download', ['id' => $a['id']])) ?>"><?= e($a['orig_name']) ?></a></div>
            <div class="file__meta">
              <?= e($a['kind']) ?> · <?= e(bytes_human((int)$a['size_bytes'])) ?>
              <?php if ($a['betrag_netto'] !== null): ?> · <?= e(money((float)$a['betrag_netto'])) ?><?php endif; ?>
              <?php if ($a['uploader']): ?> · <?= e($a['uploader']) ?><?php endif; ?>
            </div>
          </div>
          <?php if (can('edit_wish', $wish)): ?>
            <form method="post" action="<?= e(url('wish_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="attachment_delete">
              <input type="hidden" name="id" value="<?= (int)$wish['id'] ?>">
              <input type="hidden" name="attachment_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit" data-confirm="Anlage wirklich löschen?">Löschen</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($todos): ?>
  <div class="card">
    <h2>Verknüpfte Aufgaben</h2>
    <div class="itemlist">
      <?php foreach ($todos as $t): ?>
        <?= render_partial('partials/todo_item', ['t' => $t]) ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Diskussion</h2>
  <?php if (!$kommentare): ?>
    <p class="muted small">Noch keine Wortmeldungen.</p>
  <?php else: ?>
    <?php foreach ($kommentare as $c): ?>
      <div class="comment">
        <div class="comment__meta"><strong><?= e($c['autor'] ?: 'unbekannt') ?></strong> · <?= e(de_datetime($c['created_at'])) ?></div>
        <div class="comment__body"><?= e($c['body']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <form method="post" action="<?= e(url('wish_action')) ?>" class="form mt">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="comment">
    <input type="hidden" name="id" value="<?= (int)$wish['id'] ?>">
    <div class="field">
      <label for="body">Kommentar</label>
      <textarea id="body" name="body" required placeholder="Anmerkung, Alternative, Erfahrung ..."></textarea>
    </div>
    <div><button class="btn" type="submit">Absenden</button></div>
  </form>
</div>

<?php if (can('delete_wish', $wish)): ?>
  <form method="post" action="<?= e(url('wish_action')) ?>" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$wish['id'] ?>">
    <button class="btn btn--danger" type="submit"
            data-confirm="Diesen Wunsch samt Anlagen und Kommentaren endgültig löschen?">Wunsch löschen</button>
  </form>
<?php endif; ?>
