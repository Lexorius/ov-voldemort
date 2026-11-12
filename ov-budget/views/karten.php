<?php
/** @var array $karten @var int $symbole @var bool $verwalten */
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('karten_modul_name', 'Karten')) ?></h1>
    <p><?= e((string)setting('karten_intro', 'Kartenausschnitte mit eigenen Symbolen und den Fahrzeugen, die ihre Position gemeldet haben.')) ?></p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('karten_symbole')) ?>">Symbole<?= $symbole > 0 ? ' (' . (int)$symbole . ')' : '' ?></a>
    <?php if ($verwalten): ?><a class="btn" href="<?= e(url('karte_edit')) ?>">+ Karte</a><?php endif; ?>
  </div>
</div>

<?php if (!$karten): ?>
  <div class="card"><div class="empty">Noch keine Karte angelegt.<?= $verwalten
      ? ' Mit „+ Karte" entsteht der erste Ausschnitt – etwa das Gelände der Unterkunft oder ein Übungsgebiet.'
      : ' Die Leitung legt sie an.' ?></div></div>
<?php else: ?>
  <div class="grid3">
    <?php foreach ($karten as $k): ?>
      <div class="item">
        <a class="item__link" href="<?= e(url('karte', ['id' => $k['id']])) ?>">
          <div class="item__top">
            <span class="item__bild standort-icon" aria-hidden="true">🗺</span>
            <div style="min-width:0;flex:1">
              <div class="item__title"><?= e((string)$k['name']) ?></div>
              <div class="item__sub"><?= (int)$k['marker'] === 1 ? '1 Symbol' : (int)$k['marker'] . ' Symbole' ?><?= (int)$k['fahrzeuge'] === 1 ? ' · mit Fahrzeugen' : '' ?></div>
              <?php if ((string)$k['beschreibung'] !== ''): ?><div class="item__sub"><?= e((string)$k['beschreibung']) ?></div><?php endif; ?>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
