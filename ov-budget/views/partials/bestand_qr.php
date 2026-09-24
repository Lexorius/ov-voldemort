<?php
/**
 * QR-Code für die Bestandsmeldung – am Gerät oder am Koffer.
 *
 * @var array   $ziel        Gerät oder Gruppe
 * @var string  $art         'geraet' oder 'gruppe'
 * @var ?array  $connector   Connector, über den der Code läuft
 * @var array   $bestandConnectoren  einsatzbereite Connectoren
 * @var string  $qrName      Bezeichnung für den Anker (bleibt im Browser)
 */
$darf = can('manage_radios');
$feld = $art === 'gruppe' ? 'group_id' : 'radio_id';
$token = trim((string)($ziel['qr_token'] ?? ''));
$gesehen = radio_gesehen($ziel);
if ($token !== '' && $connector !== null) {
    $GLOBALS['ovb_qr_js'] = true;
}
?>
<section class="card" id="qr">
  <div class="card__head">
    <h2>Am Lagerort melden</h2>
    <?php if ($darf && $token !== ''): ?>
      <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="abholen">
        <input type="hidden" name="<?= e($feld) ?>" value="<?= (int)$ziel['id'] ?>">
        <button class="btn btn--sec btn--sm" type="submit">Meldungen abholen</button>
      </form>
    <?php endif; ?>
  </div>

  <dl class="dl">
    <div class="dl__item"><div class="dl__label">Zuletzt gesehen</div>
      <div class="dl__value">
        <?php if ($gesehen['stufe'] === 'nie'): ?>
          <span class="muted">noch keine Meldung</span>
        <?php else: ?>
          <?= e(de_datetime((string)$ziel['zuletzt_gesehen'])) ?>
          <?php if (trim((string)($ziel['zuletzt_melder'] ?? '')) !== ''): ?>
            <span class="muted small">· <?= e((string)$ziel['zuletzt_melder']) ?></span>
          <?php endif; ?>
          <?php if ($art === 'gruppe' && ($ziel['zuletzt_anzahl'] ?? null) !== null): ?>
            <div class="small<?= (int)$ziel['zuletzt_anzahl'] < (int)$ziel['geraete']
                ? ' ' : '' ?>"<?= (int)$ziel['zuletzt_anzahl'] < (int)$ziel['geraete']
                ? ' style="color:var(--warn);font-weight:700"' : '' ?>>
              <?= (int)$ziel['zuletzt_anzahl'] ?> von <?= (int)$ziel['geraete'] ?> Geräten gemeldet</div>
          <?php endif; ?>
        <?php endif; ?>
      </div></div>
  </dl>

  <?php if ($token !== '' && $connector !== null): ?>
    <?php $qrAdresse = connector_qr_bestand_url($connector, $token, $qrName); ?>
    <div class="qr-block" data-qr="<?= e($qrAdresse) ?>">
      <div class="qr-bild" id="qr-bild"></div>
      <div>
        <p class="small">Am Lagerort aufhängen<?= $art === 'gruppe' ? ' – am Koffer oder am Regal' : '' ?>:
          Wer den Code scannt, meldet mit einem Tipp, dass
          <?= $art === 'gruppe' ? 'alle Geräte da sind' : 'das Gerät da ist' ?> – ohne Anmeldung
          und ohne Zugang zu dieser Anwendung.</p>
        <p class="small muted mono" style="word-break:break-all"><?= e($qrAdresse) ?></p>
        <p class="small muted">Der Teil hinter dem <span class="mono">#</span> bleibt im Browser –
          der Connector erfährt nie, worum es geht. Läuft über
          <strong><?= e((string)$connector['name']) ?></strong>.</p>
        <?php if ($darf): ?>
          <div class="btnrow">
            <button class="btn btn--sec btn--sm" type="button" id="qr-drucken">Drucken</button>
            <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_neu">
              <input type="hidden" name="<?= e($feld) ?>" value="<?= (int)$ziel['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit"
                      data-confirm="Neuen QR-Code erzeugen? Der bisherige gilt dann nicht mehr – ausgedruckte Codes werden unbrauchbar."
                      data-confirm2="Bist du wirklich sicher?">Neu erzeugen</button>
            </form>
            <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_weg">
              <input type="hidden" name="<?= e($feld) ?>" value="<?= (int)$ziel['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit"
                      data-confirm="QR-Code zurückziehen? Meldungen darüber werden dann nicht mehr angenommen."
                      data-confirm2="Bist du wirklich sicher?">Zurückziehen</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($token !== ''): ?>
    <p class="small">Für diesen Code ist kein Connector mehr eingetragen – die Adresse geht ins Leere.
      Bitte neu erzeugen.</p>
  <?php elseif ($darf): ?>
    <?php if (!$bestandConnectoren): ?>
      <p class="small">Für die Meldung am Lagerort braucht es einen <strong>Connector</strong> mit der
        Verwendung „Funkgeräte". Einzurichten in der
        <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('radio_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="qr_neu">
        <input type="hidden" name="<?= e($feld) ?>" value="<?= (int)$ziel['id'] ?>">
        <?php if (count($bestandConnectoren) > 1): ?>
          <select name="connector_id" aria-label="Connector">
            <?php foreach ($bestandConnectoren as $con): ?>
              <option value="<?= (int)$con['id'] ?>"><?= e((string)$con['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <button class="btn btn--sec" type="submit">QR-Code erzeugen</button>
      </form>
      <p class="small muted">Erzeugt einen Zugang, mit dem jede Person am Lagerort melden kann, dass
        <?= $art === 'gruppe' ? 'die Geräte' : 'das Gerät' ?> da <?= $art === 'gruppe' ? 'sind' : 'ist' ?> –
        ohne Anmeldung. Die Meldung wird im Browser verschlüsselt.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="small muted">Für dieses <?= $art === 'gruppe' ? 'Gruppe' : 'Gerät' ?> hängt kein QR-Code.</p>
  <?php endif; ?>
</section>
