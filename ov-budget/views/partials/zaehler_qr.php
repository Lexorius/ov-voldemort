<?php
/**
 * QR-Code am Zähler: Wer ihn scannt, trägt den Stand ein – ohne Anmeldung,
 * verschlüsselt über den Connector.
 *
 * @var array  $meter
 * @var ?array $connector
 * @var array  $zaehlerConnectoren
 */
$darf = can('manage_verbrauch');
$token = trim((string)($meter['qr_token'] ?? ''));
if ($token !== '' && $connector !== null) {
    $GLOBALS['ovb_qr_js'] = true;
}
?>
<section class="card" id="qr">
  <div class="card__head">
    <h2>Ablesen per QR-Code</h2>
    <?php if ($darf && $token !== ''): ?>
      <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="abholen">
        <input type="hidden" name="meter_id" value="<?= (int)$meter['id'] ?>">
        <button class="btn btn--sec btn--sm" type="submit">Meldungen abholen</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($token !== '' && $connector !== null): ?>
    <?php $qrAdresse = connector_qr_zaehler_url($connector, $token, meter_qr_name($meter)); ?>
    <div class="qr-block" data-qr="<?= e($qrAdresse) ?>">
      <div class="qr-bild" id="qr-bild"></div>
      <div>
        <p class="small">Am Zähler aufhängen: Wer den Code scannt, tippt den Stand ein – ohne Anmeldung
          und ohne Zugang zu dieser Anwendung. Der Stand wird im Browser verschlüsselt.</p>
        <p class="small muted mono" style="word-break:break-all"><?= e($qrAdresse) ?></p>
        <p class="small muted">Der Teil hinter dem <span class="mono">#</span> bleibt im Browser –
          der Connector erfährt nie, welcher Zähler es ist. Läuft über
          <strong><?= e((string)$connector['name']) ?></strong>.</p>
        <?php if ($darf): ?>
          <div class="btnrow">
            <button class="btn btn--sec btn--sm" type="button" id="qr-drucken">Drucken</button>
            <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_neu">
              <input type="hidden" name="meter_id" value="<?= (int)$meter['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit"
                      data-confirm="Neuen QR-Code erzeugen? Der bisherige gilt dann nicht mehr."
                      data-confirm2="Bist du wirklich sicher?">Neu erzeugen</button>
            </form>
            <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="qr_weg">
              <input type="hidden" name="meter_id" value="<?= (int)$meter['id'] ?>">
              <button class="btn btn--sec btn--sm" type="submit"
                      data-confirm="QR-Code zurückziehen? Stände darüber werden dann nicht mehr angenommen."
                      data-confirm2="Bist du wirklich sicher?">Zurückziehen</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($token !== ''): ?>
    <p class="small">Für diesen Code ist kein Connector mehr eingetragen – die Adresse geht ins Leere. Bitte neu erzeugen.</p>
  <?php elseif ($darf): ?>
    <?php if (!$zaehlerConnectoren): ?>
      <p class="small">Fürs Ablesen per QR-Code braucht es einen <strong>Connector</strong> mit der Verwendung
        „Zähler". Einzurichten in der <a href="<?= e(url('admin_connectors')) ?>">Verwaltung</a>.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('verbrauch_action')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="qr_neu">
        <input type="hidden" name="meter_id" value="<?= (int)$meter['id'] ?>">
        <?php if (count($zaehlerConnectoren) > 1): ?>
          <select name="connector_id" aria-label="Connector">
            <?php foreach ($zaehlerConnectoren as $con): ?>
              <option value="<?= (int)$con['id'] ?>"><?= e((string)$con['name']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <button class="btn btn--sec" type="submit">QR-Code erzeugen</button>
      </form>
      <p class="small muted">Erzeugt einen Zugang, mit dem jede Person am Zähler den Stand melden kann –
        ohne Anmeldung. Der Connector erfährt nur, dass gemeldet wurde, nicht was.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="small muted">An diesem Zähler hängt kein QR-Code.</p>
  <?php endif; ?>
</section>
