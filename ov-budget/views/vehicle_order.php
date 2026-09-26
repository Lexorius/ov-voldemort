<?php
/** @var array $order @var ?array $vehicle @var array $verlauf @var array $bilder @var array $dokumente */
$verwalten = can('manage_vehicles');
$fertig = (int)$order['status_final'] === 1;
?>
<div class="pagehead">
  <div>
    <h1><?= e($order['titel']) ?></h1>
    <p class="muted small">
      Auftrag <span class="mono"><?= e($order['nummer']) ?></span> ·
      <a href="<?= e(url('vehicle', ['id' => $order['vehicle_id']])) ?>"><?= e($order['fahrzeug']) ?></a>
      <?php if ($order['kennzeichen']): ?> · <?= e($order['kennzeichen']) ?><?php endif; ?>
      <?php if ($order['ersteller']): ?> · angelegt von <?= e($order['ersteller']) ?><?php endif; ?>
    </p>
    <p class="item__meta" style="margin-top:.3rem">
      <?= badge($order['status_label'] ? ['label' => $order['status_label'], 'color' => $order['status_color']] : null) ?>
      <?= badge($order['art_label'] ? ['label' => $order['art_label'], 'color' => $order['art_color']] : null) ?>
      <?= badge($order['prio_label'] ? ['label' => $order['prio_label'], 'color' => $order['prio_color']] : null) ?>
      <?php if ((int)$order['ausfall']): ?><span class="badge" style="background:#b91c1c">Fahrzeug fällt aus</span><?php endif; ?>
    </p>
  </div>
  <div class="btnrow">
    <?php if (order_editable($order)): ?>
      <a class="btn btn--sec" href="<?= e(url('vehicle_order_edit', ['id' => $order['id']])) ?>">Bearbeiten</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('vehicle', ['id' => $order['vehicle_id']])) ?>#auftraege">Zur Fahrzeugakte</a>
  </div>
</div>

<div class="grid2">
  <section class="card">
    <h2>Vorgang</h2>
    <?php if (trim((string)$order['beschreibung']) !== ''): ?>
      <div class="comment__body"><?= e((string)$order['beschreibung']) ?></div>
    <?php else: ?>
      <div class="empty">Keine weitere Beschreibung.</div>
    <?php endif; ?>
    <dl class="dl mt">
      <?php
      $zeilen = [
          'Gemeldet von'   => $order['gemeldet_von'],
          'Gemeldet am'    => $order['gemeldet_am'] ? de_date($order['gemeldet_am']) : '',
          'Fällig am'      => $order['faellig_am'] ? de_date($order['faellig_am']) : '',
          'Erledigt am'    => $order['erledigt_am'] ? de_date($order['erledigt_am']) : '',
          'Nummer der THW-Verwaltung' => $order['thw_nummer'] ?? '',
          'Werkstatt'      => $order['werkstatt'],
          'Auftragsnummer der Werkstatt' => $order['auftragsnummer'],
          'Kilometerstand' => $order['km_stand'] !== null ? number_format((float)$order['km_stand'], 0, ',', '.') . ' km' : '',
          'Kosten geschätzt' => $order['kosten_geschaetzt'] !== null ? money((float)$order['kosten_geschaetzt']) : '',
          'Kosten tatsächlich' => $order['kosten_netto'] !== null ? money((float)$order['kosten_netto']) : '',
      ];
      foreach ($zeilen as $label => $wert):
          if (trim((string)$wert) === '') { continue; } ?>
        <div class="dl__item"><div class="dl__label"><?= e((string)$label) ?></div>
          <div class="dl__value"><?= e((string)$wert) ?></div></div>
      <?php endforeach; ?>
    </dl>
  </section>

  <section class="card">
    <h2>Bearbeitung</h2>
    <?php if (!$verwalten): ?>
      <div class="empty">Den Stand pflegt die Leitung.</div>
    <?php else: ?>
      <form method="post" action="<?= e(url('vehicle_action')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order_status">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
        <div class="field">
          <label for="status_id">Neuer Stand</label>
          <select id="status_id" name="status_id"><?= list_options('auftrag_status', (int)$order['status_id'], '') ?></select>
        </div>
        <div class="field">
          <label for="bemerkung">Was ist passiert?</label>
          <textarea id="bemerkung" name="bemerkung" rows="2"
                    placeholder="z.B. Termin in der Werkstatt am 24.09., Ersatzteil bestellt"></textarea>
        </div>
        <div class="field">
          <label for="kosten_netto">Kosten (nur bei Abschluss nötig)</label>
          <input type="text" inputmode="decimal" id="kosten_netto" name="kosten_netto"
                 value="<?= e(num_input($order['kosten_netto'])) ?>">
        </div>
        <div class="btnrow"><button class="btn" type="submit">Eintragen</button></div>
      </form>
      <p class="small muted">Jeder Schritt landet unveränderbar in der Fahrzeugakte.</p>
    <?php endif; ?>
  </section>
</div>

<?php if ($vehicle): ?>
  <?= render_partial('partials/vehicle_files', [
      'vehicle' => $vehicle, 'order' => $order, 'bilder' => $bilder, 'dokumente' => $dokumente, 'modus' => 'auftrag',
  ]) ?>
<?php endif; ?>

<section class="card">
  <div class="card__head">
    <h2>Verlauf</h2>
    <span class="small muted"><?= count($verlauf) ?> Einträge aus dem Journal</span>
  </div>
  <?php if (!$verlauf): ?>
    <div class="empty">Noch kein Schritt festgehalten.</div>
  <?php else: ?>
    <div class="journal">
      <?php foreach ($verlauf as $j): ?>
        <div class="journal__row">
          <div class="journal__when">
            <?= e(de_datetime($j['created_at'])) ?>
            <div class="small muted"><?= e((string)($j['autor'] ?: '–')) ?></div>
          </div>
          <div class="journal__what">
            <strong><?= e($j['titel']) ?></strong>
            <?php if ($j['feld'] === 'status'): ?>
              <div class="small"><span class="muted"><?= e($j['alt_wert'] ?: '–') ?></span> → <strong><?= e($j['neu_wert']) ?></strong></div>
            <?php endif; ?>
            <?php if (trim((string)$j['text']) !== ''): ?>
              <div class="comment__body"><?= e((string)$j['text']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
