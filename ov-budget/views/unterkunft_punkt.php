<?php
/** @var array $punkt @var array $standorte @var array $verlauf @var bool $verwalten @var bool $darfAendern @var bool $notizDarf */
$id = (int)$punkt['id'];
$ort = unterkunft_ort_text($punkt, $standorte);
$final = (int)$punkt['status_final'] === 1;
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)$punkt['titel']) ?></h1>
    <p><?= badge($punkt['status_label'] ? ['label' => $punkt['status_label'], 'color' => $punkt['status_color']] : null, 'ohne Status') ?>
      <?= badge($punkt['prioritaet_label'] ? ['label' => $punkt['prioritaet_label'], 'color' => $punkt['prioritaet_color']] : null, '') ?>
      <span class="badge badge--outline"><?= e(UNTERKUNFT_ARTEN[(string)$punkt['art']] ?? 'Mangel') ?></span>
      <?php if ((string)$punkt['divera_entry_id'] !== ''): ?><span class="badge badge--outline">aus Divera</span><?php endif; ?>
      <?php if (unterkunft_ueberfaellig($punkt)): ?><span class="badge" style="background:var(--bad)">überfällig</span><?php endif; ?></p>
  </div>
  <div class="btnrow">
    <?php if ($darfAendern): ?><a class="btn" href="<?= e(url('unterkunft_punkt_edit', ['id' => $id])) ?>">Bearbeiten</a><?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('unterkunft')) ?>#punkte">Zur Unterkunft</a>
  </div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Worum es geht</h2>
    <?php if (trim((string)$punkt['beschreibung']) !== ''): ?>
      <div class="comment__body"><?= e((string)$punkt['beschreibung']) ?></div>
    <?php else: ?>
      <p class="small muted">Keine weitere Beschreibung.</p>
    <?php endif; ?>
    <dl class="dl" style="margin-top:.8rem">
      <div class="dl__item"><div class="dl__label">Ort</div><div class="dl__value">
        <?php if ((int)$punkt['standort_id'] > 0): ?><a href="<?= e(url('standorte', ['id' => $punkt['standort_id']])) ?>"><?= e($ort) ?></a>
        <?php else: ?><?= $ort !== '' ? e($ort) . ' <span class="muted small">(Freitext, kein Platz zugeordnet)</span>' : '<span class="muted">nicht angegeben</span>' ?><?php endif; ?></div></div>
      <div class="dl__item"><div class="dl__label">Gemeldet</div><div class="dl__value"><?= e(de_datetime((string)$punkt['created_at'])) ?> von <?= e(trim((string)($punkt['melder_user_name'] ?: $punkt['melder_name'])) ?: 'unbekannt') ?> · <?= $final ? 'abgeschlossen' : 'seit ' . (int)unterkunft_alter_tage($punkt) . ' Tagen offen' ?></div></div>
      <div class="dl__item"><div class="dl__label">Zuständig</div><div class="dl__value"><?= trim((string)($punkt['zustaendig_name'] ?? '')) !== '' ? e((string)$punkt['zustaendig_name']) : '<span style="color:var(--warn)">niemand</span>' ?><?= trim((string)$punkt['firma']) !== '' ? ' · Firma: ' . e((string)$punkt['firma']) : '' ?></div></div>
      <div class="dl__item"><div class="dl__label">Frist</div><div class="dl__value"><?= (string)$punkt['faellig_am'] !== '' ? e(de_date((string)$punkt['faellig_am'])) : '<span class="muted">keine</span>' ?></div></div>
      <?php if ($final && (string)$punkt['erledigt_am'] !== ''): ?>
        <div class="dl__item"><div class="dl__label">Abgeschlossen</div><div class="dl__value"><?= e(de_datetime((string)$punkt['erledigt_am'])) ?></div></div>
      <?php endif; ?>
    </dl>
  </section>

  <section class="card" id="stand">
    <h2>Stand setzen</h2>
    <?php if ($verwalten): ?>
      <form method="post" class="form" action="<?= e(url('unterkunft_punkt', ['id' => $id])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <div class="field">
          <label for="status_id">Status</label>
          <select id="status_id" name="status_id"><?= list_options('unterkunft_status', (int)$punkt['status_id'], '') ?></select>
        </div>
        <div class="field">
          <label for="notiz">Notiz zum Schritt <span class="muted">(freiwillig)</span></label>
          <textarea id="notiz" name="notiz" rows="2" placeholder="z. B. Elektriker kommt am Donnerstag"></textarea>
        </div>
        <div class="btnrow"><button class="btn" type="submit">Speichern</button></div>
      </form>
    <?php elseif ($notizDarf): ?>
      <p class="small muted">Den Stand setzt die Leitung. Eine Notiz kannst du trotzdem hinterlassen – etwa, wenn sich etwas verändert hat.</p>
      <form method="post" class="form" action="<?= e(url('unterkunft_punkt', ['id' => $id])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notiz">
        <div class="field"><label for="notiz">Notiz</label><textarea id="notiz" name="notiz" rows="2" required></textarea></div>
        <div class="btnrow"><button class="btn btn--sec" type="submit">Notiz vermerken</button></div>
      </form>
    <?php else: ?>
      <p class="small muted">Den Stand setzt die Leitung.</p>
    <?php endif; ?>
  </section>
</div>

<section class="card" id="verlauf">
  <div class="card__head"><h2>Verlauf</h2><span class="muted small"><?= count($verlauf) ?> Einträge</span></div>
  <?php if (!$verlauf): ?>
    <div class="empty">Noch kein Eintrag.</div>
  <?php else: ?>
    <?php foreach ($verlauf as $l): ?>
      <div class="comment">
        <div class="comment__meta"><?= e(de_datetime((string)$l['created_at'])) ?> · <?= e(trim((string)($l['user_name'] ?: $l['autor'])) ?: ((string)$l['quelle'] === 'divera' ? 'Divera' : 'System')) ?>
          <?php if ($l['status_neu'] !== null): ?> · <?= $l['alt_label'] ? e((string)$l['alt_label']) . ' → ' : '' ?><?= badge(['label' => (string)($l['neu_label'] ?? 'Status'), 'color' => (string)($l['neu_color'] ?? '#64748b')]) ?><?php endif; ?></div>
        <?php if (trim((string)$l['text']) !== ''): ?><div class="comment__body"><?= e((string)$l['text']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php if ($verwalten): ?>
  <form method="post" class="form" style="margin-top:1rem" action="<?= e(url('unterkunft_punkt', ['id' => $id])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="loeschen">
    <button class="btn btn--sec btn--sm" type="submit" data-confirm="Diesen Punkt samt Verlauf löschen? Erledigtes besser auf einen abschließenden Status setzen.">Punkt löschen</button>
  </form>
<?php endif; ?>
