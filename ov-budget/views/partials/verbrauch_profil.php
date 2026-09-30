<?php
/**
 * Verbrauchsprofil: je Wochentag und je Tagesstunde im Durchschnitt, dazu
 * die stärksten Zeiten. Wird auf der Zählerseite und im Bericht gezeigt.
 *
 * @var array  $profil   aus verbrauch_profil()
 * @var string $einheit
 * @var string $farbe
 * @var bool   $bericht  Druckfassung (kleinere Schrift, keine Karten)
 */
$tage = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$maxTag = max(array_merge([0.0], $profil['wochentage']));
$maxStd = max(array_merge([0.0], $profil['stunden']));
$bericht = !empty($bericht);
?>
<?php if (!$profil['abschnitte']): ?>
  <p class="<?= $bericht ? 'klein' : 'small muted' ?>">Noch zu wenige Stände für ein Profil – es braucht mindestens zwei.</p>
<?php else: ?>
  <div class="profil<?= $bericht ? ' profil--bericht' : '' ?>">
    <div class="profil__block">
      <div class="profil__titel">Durchschnitt je Wochentag <span class="profil__einheit">· <?= e($einheit) ?> je Tag</span></div>
      <div class="profil__balken profil__balken--tage">
        <?php foreach ($profil['wochentage'] as $i => $v): $h = $maxTag > 0 ? max(2, $v / $maxTag * 100) : 0; ?>
          <div class="profil__spalte">
            <div class="profil__wert"><?= e(menge($v, '', $v >= 100 ? 0 : 1)) ?></div>
            <div class="profil__stab" style="height:<?= number_format($h, 1, '.', '') ?>%;background:<?= e($farbe) ?>"
                 title="<?= e($tage[$i] . ': ' . menge($v, $einheit, 2)) ?>"></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="profil__achse profil__achse--tage">
        <?php foreach ($tage as $i => $t): ?>
          <div class="profil__name<?= $profil['spitze_tag'] === $i ? ' profil__name--spitze' : '' ?>"><?= e($t) ?></div>
        <?php endforeach; ?>
      </div>
      <?php if (!$profil['wochentage_aussagekraeftig']): ?>
        <div class="<?= $bericht ? 'klein' : 'small muted' ?>">Die Stände liegen im Schnitt <?= e(number_format($profil['abstand_stunden'] / 24, 1, ',', '.')) ?> Tage
          auseinander – zwischen den Wochentagen wird dann gleichmäßig verteilt. Für ein echtes Profil braucht es
          mindestens einen Stand je Tag.</div>
      <?php endif; ?>
    </div>

    <div class="profil__block">
      <div class="profil__titel">Durchschnitt je Tagesstunde <span class="profil__einheit">· <?= e($einheit) ?> je Stunde</span></div>
      <div class="profil__balken profil__balken--stunden">
        <?php foreach ($profil['stunden'] as $i => $v): $h = $maxStd > 0 ? max(2, $v / $maxStd * 100) : 0; ?>
          <div class="profil__spalte">
            <div class="profil__stab" style="height:<?= number_format($h, 1, '.', '') ?>%;background:<?= e($farbe) ?>"
                 title="<?= e(sprintf('%02d–%02d Uhr: %s', $i, $i + 1, menge($v, $einheit, 3))) ?>"></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="profil__achse profil__achse--stunden">
        <?php $spitzen = array_column($profil['spitzen_stunden'], 'stunde'); for ($i = 0; $i < 24; $i++): ?>
          <div class="profil__name<?= in_array($i, $spitzen, true) ? ' profil__name--spitze' : '' ?>"><?= $i % 3 === 0 || in_array($i, $spitzen, true) ? (int)$i : '' ?></div>
        <?php endfor; ?>
      </div>
      <?php if (!$profil['stunden_aussagekraeftig']): ?>
        <div class="<?= $bericht ? 'klein' : 'small muted' ?>">Die Stände liegen im Schnitt <?= e(number_format($profil['abstand_stunden'], 1, ',', '.')) ?> Stunden
          auseinander – innerhalb des Tages wird gleichmäßig verteilt. Eine Tageskurve entsteht erst mit
          stündlichen Ständen, etwa aus Home Assistant.</div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($profil['stunden_aussagekraeftig'] || $profil['wochentage_aussagekraeftig']): ?>
    <dl class="<?= $bericht ? 'eck' : 'dl' ?>" style="margin-top:.6rem">
      <?php if ($profil['wochentage_aussagekraeftig']): ?>
        <div class="dl__item"><div class="dl__label">Stärkster Wochentag</div>
          <div class="dl__value"><?= e(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][$profil['spitze_tag']]) ?>
            <span class="<?= $bericht ? 'klein' : 'small muted' ?>">· <?= e(menge($profil['wochentage'][$profil['spitze_tag']], $einheit, 1)) ?> je Tag,
              schwächster <?= e($tage[$profil['schwach_tag']]) ?> mit <?= e(menge($profil['wochentage'][$profil['schwach_tag']], $einheit, 1)) ?></span></div></div>
      <?php endif; ?>
      <?php if ($profil['stunden_aussagekraeftig']): ?>
        <div class="dl__item"><div class="dl__label">Stärkste Stunden am Tag</div>
          <div class="dl__value"><?= e(implode(', ', array_map(static fn($s) => sprintf('%02d–%02d Uhr', $s['stunde'], $s['stunde'] + 1), $profil['spitzen_stunden']))) ?>
            <span class="<?= $bericht ? 'klein' : 'small muted' ?>">· <?= e(menge($profil['spitzen_stunden'][0]['wert'] ?? 0, $einheit, 2)) ?> in der stärksten Stunde,
              <?= (int)$profil['nacht_anteil'] ?> % des Tages entfallen auf 22–6 Uhr</span></div></div>
        <div class="dl__item"><div class="dl__label">Stärkste Zeiten in der Woche</div>
          <div class="dl__value"><?= e(implode(', ', array_map(static fn($s) => $tage[$s['tag']] . sprintf(' %02d–%02d Uhr', $s['stunde'], $s['stunde'] + 1), $profil['spitzen_woche']))) ?></div></div>
      <?php endif; ?>
    </dl>
  <?php endif; ?>
  <p class="<?= $bericht ? 'klein' : 'small muted' ?>">Grundlage: <?= (int)$profil['abschnitte'] ?> Abschnitte zwischen Ablesungen,
    <?= e(number_format($profil['tage'], 0, ',', '.')) ?> Tage. Jeder Abschnitt ist gleichmäßig auf seine Stunden verteilt.</p>
<?php endif; ?>
