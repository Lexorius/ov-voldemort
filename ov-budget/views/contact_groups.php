<?php /** @var array $rows */ ?>
<div class="pagehead">
  <div>
    <h1>Verteiler</h1>
    <p>Listen für Einladungen und Anschreiben – etwa zum Jubiläum, zur Jahresabschlussfeier
       oder für die Presse.</p>
  </div>
  <div class="btnrow">
    <?php if (can('manage_contacts')): ?>
      <a class="btn" href="<?= e(url('contact_group')) ?>">+ Verteiler</a>
    <?php endif; ?>
    <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Kontakte</a>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="card"><div class="empty">Noch kein Verteiler angelegt.
    <?php if (can('manage_contacts')): ?>
      <br><a href="<?= e(url('contact_group')) ?>">Ersten Verteiler anlegen</a>
    <?php endif; ?>
  </div></div>
<?php else: ?>
  <div class="itemlist">
    <?php foreach ($rows as $g): ?>
      <a class="item<?= (int)$g['is_active'] ? '' : ' item--done' ?>"
         href="<?= e(url('contact_group', ['id' => $g['id']])) ?>">
        <div class="item__top">
          <div style="min-width:0">
            <div class="item__title"><?= e($g['name']) ?></div>
            <div class="item__sub">
              <?php if ($g['anlass_am']): ?><?= e(de_date($g['anlass_am'])) ?><?php else: ?>ohne Termin<?php endif; ?>
              <?php if ($g['ort']): ?> · <?= e($g['ort']) ?><?php endif; ?>
            </div>
          </div>
          <div class="item__amount"><?= (int)$g['anzahl'] ?></div>
        </div>
        <div class="item__meta">
          <span class="badge badge--outline"><?= (int)$g['anzahl'] ?> Kontakte</span>
          <?php if ((int)$g['zugesagt'] > 0): ?>
            <span class="badge" style="background:#15803d"><?= (int)$g['zugesagt'] ?> Personen zugesagt</span>
          <?php endif; ?>
          <?php if (!(int)$g['is_active']): ?><span class="badge badge--muted">abgeschlossen</span><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
