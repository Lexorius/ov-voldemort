<?php
/** @var array $gruppen @var array $zeilen @var array $zaehler @var array $ignoriert @var int $gesamt */
$stufenText = [
    'sicher'         => 'Gleiche E-Mail-Adresse, gleiche Rufnummer oder gleicher Name – fast immer derselbe Kontakt.',
    'wahrscheinlich' => 'Gleicher Nachname mit fehlendem Vornamen oder Tippfehler bei gleicher Organisation.',
    'moeglich'       => 'Gleiche Adresse oder Nummer, aber ein anderer Name – oft das zentrale Postfach einer Stelle.',
];
?>
<div class="pagehead">
  <div>
    <h1>Dubletten</h1>
    <p>Kontakte, die wahrscheinlich doppelt angelegt sind. Zusammenführen behält einen Kontakt
       und hängt Verteiler, Termine und Gästelisten der anderen daran; „keine Dublette" merkt
       sich das Paar, es taucht hier nicht mehr auf.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('contacts')) ?>">Zu den Kontakten</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat__label">Geprüfte Kontakte</div><div class="stat__value"><?= (int)$gesamt ?></div></div>
  <div class="stat"><div class="stat__label">Sicher</div>
    <div class="stat__value" style="color:#b91c1c"><?= (int)$zaehler['sicher'] ?></div></div>
  <div class="stat"><div class="stat__label">Wahrscheinlich</div>
    <div class="stat__value" style="color:#b45309"><?= (int)$zaehler['wahrscheinlich'] ?></div></div>
  <div class="stat"><div class="stat__label">Möglich</div>
    <div class="stat__value" style="color:#64748b"><?= (int)$zaehler['moeglich'] ?></div></div>
</div>

<?php if (!$gruppen): ?>
  <div class="card"><div class="empty">Keine Dubletten gefunden – alle Kontakte unterscheiden sich in
    E-Mail-Adresse, Rufnummer und Name.</div></div>
<?php else: ?>
  <?php foreach ($gruppen as $gi => $g):
      [$stufeLabel, $stufeFarbe] = dublette_stufe($g['stufe']);
      $ids = array_values(array_filter($g['ids'], static fn($id) => isset($zeilen[$id])));
      if (count($ids) < 2) {
          continue;
      }
  ?>
    <section class="card" id="gruppe-<?= (int)$gi ?>">
      <div class="card__head">
        <div>
          <span class="badge" style="background:<?= e($stufeFarbe) ?>"><?= e($stufeLabel) ?></span>
          <span class="small"><?= e(implode(' · ', $g['gruende'])) ?></span>
          <div class="small muted" title="<?= e($stufenText[$g['stufe']] ?? '') ?>"><?= count($ids) ?> Kontakte</div>
        </div>
        <div class="btnrow">
          <a class="btn btn--sm" href="<?= e(url('contacts_dubletten', ['ids' => implode(',', $ids)])) ?>">Zusammenführen</a>
          <form method="post" class="inline-form" action="<?= e(url('contacts_dubletten')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ignore">
            <?php foreach ($ids as $id): ?><input type="hidden" name="ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
            <button class="btn btn--sec btn--sm" type="submit">Keine Dublette</button>
          </form>
        </div>
      </div>

      <div class="tablewrap">
        <table class="data">
          <thead>
            <tr><th>Name</th><th>Organisation</th><th>E-Mail</th><th>Telefon</th><th>Ort</th>
                <th class="num">Verteiler</th><th>Angelegt</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($ids as $id): $c = $zeilen[$id]; ?>
            <tr<?= (int)$c['is_active'] ? '' : ' class="is-muted"' ?>>
              <td>
                <strong><?= e(contact_name($c)) ?></strong>
                <?php if (!(int)$c['is_active']): ?> <span class="badge badge--muted">inaktiv</span><?php endif; ?>
                <?php if ($c['position']): ?><div class="small muted"><?= e($c['position']) ?></div><?php endif; ?>
                <div class="small muted">#<?= (int)$c['id'] ?>
                  <?php if ($c['kategorie_label']): ?> · <?= e($c['kategorie_label']) ?><?php endif; ?></div>
              </td>
              <td class="small"><?= e($c['organisation'] ?: '–') ?></td>
              <td class="small"><?= email_html($c['email']) ?></td>
              <td class="small nowrap">
                <?= phone_html($c['telefon'], $c['mobil'] ? '' : '–') ?>
                <?php if ($c['mobil']): ?>
                  <?php if ($c['telefon']): ?><br><?php endif; ?>
                  <?= phone_html($c['mobil']) ?> <span class="muted">mobil</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e(trim($c['plz'] . ' ' . $c['ort']) ?: '–') ?></td>
              <td class="num small"><?= (int)$c['verteiler'] ?></td>
              <td class="small nowrap"><?= e(de_date($c['created_at'])) ?></td>
              <td><a class="btn btn--sec btn--sm" href="<?= e(url('contact_edit', ['id' => $c['id']])) ?>">Bearbeiten</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($ignoriert): ?>
  <section class="card">
    <details>
      <summary><strong>Als „keine Dublette" gemerkt</strong> (<?= count($ignoriert) ?> Paare)</summary>
      <div class="tablewrap mt">
        <table class="data">
          <tbody>
          <?php foreach ($ignoriert as [$a, $b]):
              if (!isset($zeilen[$a], $zeilen[$b])) {
                  continue;
              } ?>
            <tr>
              <td><?= e(contact_name($zeilen[$a])) ?>
                <span class="small muted"><?= e($zeilen[$a]['organisation'] ? '· ' . $zeilen[$a]['organisation'] : '') ?></span></td>
              <td><?= e(contact_name($zeilen[$b])) ?>
                <span class="small muted"><?= e($zeilen[$b]['organisation'] ? '· ' . $zeilen[$b]['organisation'] : '') ?></span></td>
              <td class="num">
                <form method="post" class="inline-form" action="<?= e(url('contacts_dubletten')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="unignore">
                  <input type="hidden" name="a" value="<?= (int)$a ?>">
                  <input type="hidden" name="b" value="<?= (int)$b ?>">
                  <button class="btn btn--sec btn--sm" type="submit">Wieder prüfen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Wie gesucht wird</h2>
  <dl class="dl">
    <?php foreach ($stufenText as $stufe => $text): [$lbl, $farbe] = dublette_stufe($stufe); ?>
      <div class="dl__item">
        <div class="dl__label"><span class="badge" style="background:<?= e($farbe) ?>"><?= e($lbl) ?></span></div>
        <div class="dl__value small"><?= e($text) ?></div>
      </div>
    <?php endforeach; ?>
  </dl>
  <p class="small muted mb0">Groß- und Kleinschreibung, Umlaute, Leer- und Satzzeichen spielen keine Rolle;
    Rufnummern werden international verglichen. Inaktive Kontakte werden mitgeprüft.</p>
</section>
