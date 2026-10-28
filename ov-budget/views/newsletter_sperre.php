<?php
/** @var array $rows @var array $blatt @var bool $darf @var array $connectoren @var int $letzterAbruf */
?>
<div class="pagehead">
  <div>
    <h1>Sperrliste</h1>
    <p>Wer keinen Newsletter mehr bekommt – über den Abmeldelink oder von Hand. Gespeichert wird keine Adresse, nur ihre
       Prüfsumme: Beim Versand wird jede Adresse geprüft, ob ihre Prüfsumme hier steht.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('newsletter')) ?>">Zu den Newslettern</a>
    <?php if ($darf && $connectoren): ?>
      <form method="post" action="<?= e(url('newsletter_action')) ?>" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="abmeldungen">
        <button class="btn btn--sec" type="submit">Abmeldungen abholen</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if ($darf): ?>
  <section class="card">
    <div class="card__head"><h2>Adresse sperren</h2><span class="small muted">die Adresse wird nur als Prüfsumme abgelegt</span></div>
    <form method="post" action="<?= e(url('newsletter_action')) ?>" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sperren">
      <div class="grid2">
        <div class="field"><label for="email">E-Mail-Adresse</label><input type="email" id="email" name="email" required maxlength="150"></div>
        <div class="field"><label for="grund">Grund <span class="muted">(freiwillig)</span></label><input type="text" id="grund" name="grund" maxlength="150" placeholder="z. B. telefonisch abgemeldet"></div>
      </div>
      <div class="btnrow"><button class="btn btn--sec" type="submit">Sperren</button></div>
    </form>
  </section>
<?php endif; ?>

<section class="card">
  <div class="card__head"><h2>Gesperrt</h2>
    <span class="small muted"><?= (int)$blatt['gesamt'] ?> Einträge<?= $letzterAbruf > 0 ? ' · Abmeldungen zuletzt geholt ' . e(de_datetime(date('Y-m-d H:i:s', $letzterAbruf))) : '' ?></span></div>
  <?php if (!$rows): ?>
    <div class="empty">Niemand gesperrt.</div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Prüfsumme</th><th>Grund</th><th>Seit</th><?php if ($darf): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
          <tr>
            <td class="mono small" title="<?= e((string)$s['token']) ?>"><?= e(substr((string)$s['token'], 0, 16)) ?>…</td>
            <td class="small"><?= e((string)$s['grund']) ?></td>
            <td class="small nowrap"><?= e(de_datetime((string)$s['gesperrt_am'])) ?></td>
            <?php if ($darf): ?>
              <td><form method="post" action="<?= e(url('newsletter_action')) ?>" class="inline-form"><?= csrf_field() ?>
                <input type="hidden" name="action" value="entsperren"><input type="hidden" name="sperre_id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn--sec btn--sm" type="submit" data-confirm="Sperre aufheben? Die Adresse bekommt dann wieder Newsletter.">Aufheben</button></form></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= render_partial('partials/seiten', ['blatt' => $blatt, 'route' => 'newsletter_sperre']) ?>
  <?php endif; ?>
  <p class="small muted">Um nachzusehen, ob eine bestimmte Adresse gesperrt ist, oben „Adresse sperren" verwenden: Steht sie schon drin,
    sagt die Meldung das, ohne die Adresse zu speichern.</p>
</section>
