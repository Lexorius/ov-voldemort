<?php
/** @var string $q @var array $alle @var array $baum @var array $zaehlung @var array $postfaecher @var array $jeDienststelle @var bool $darfPflegen */
$jeDienststelle ??= [];
$darfPflegen ??= false;
$netzBadges = static function (array $p): string {
    $html = '';
    if ((int)($p['offiziell'] ?? 0) === 1) {
        $html .= '<span class="badge badge--outline" title="über das Internet erreichbar">offizielles Netz</span> ';
    }
    if ((int)($p['behoerdennetz'] ?? 0) === 1) {
        $html .= '<span class="badge" style="background:#1d4ed8" title="nur aus dem Behördennetz erreichbar">Behördennetz</span> ';
    }
    return $html !== '' ? $html : '<span class="badge badge--muted">Erreichbarkeit offen</span>';
};
$zeile = static function (array $d, int $tiefe) use (&$zeile, $jeDienststelle, $darfPflegen, $netzBadges): void {
    $typ = THW_TYPEN[thw_typ((string)$d['typ'])];
    $pf = $jeDienststelle[(int)$d['id']] ?? [];
    ?>
    <div class="standort<?= (int)$d['is_active'] !== 1 ? ' standort--aus' : '' ?>" style="margin-left:<?= $tiefe * 1.4 ?>rem">
      <div class="standort__zeile">
        <span class="standort__icon" title="<?= e($typ['label']) ?>"><?= e($typ['icon']) ?></span>
        <span class="standort__name">
          <strong><?= e((string)$d['name']) ?></strong>
          <?php if ($d['kurz'] !== ''): ?><span class="mono muted small"><?= e((string)$d['kurz']) ?></span><?php endif; ?>
          <span class="muted small"><?= e($typ['label']) ?></span>
          <?php if ((int)$d['is_active'] !== 1): ?><span class="badge badge--muted">stillgelegt</span><?php endif; ?>
          <?php $anschrift = thw_anschrift($d); if ($anschrift !== '' || $d['telefon'] !== '' || $d['email'] !== '' || $d['web'] !== ''): ?>
            <div class="small muted">
              <?= $anschrift !== '' ? e($anschrift) : '' ?>
              <?php if ($d['telefon'] !== ''): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)$d['telefon'])) ?>"><?= e((string)$d['telefon']) ?></a><?php endif; ?>
              <?php if ($d['email'] !== ''): ?> · <a href="mailto:<?= e((string)$d['email']) ?>"><?= e((string)$d['email']) ?></a><?php endif; ?>
              <?php if ($d['web'] !== ''): ?> · <a href="<?= e(str_starts_with((string)$d['web'], 'http') ? (string)$d['web'] : 'https://' . $d['web']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if (trim((string)($d['notiz'] ?? '')) !== ''): ?><div class="small muted"><?= nl2br(e((string)$d['notiz'])) ?></div><?php endif; ?>
          <?php if ($pf): ?>
            <div class="chips" style="margin-top:.25rem">
              <?php foreach ($pf as $p): ?>
                <a class="chip<?= (int)$p['is_active'] !== 1 ? ' is-muted' : '' ?>" href="mailto:<?= e((string)$p['email']) ?>" title="<?= e((string)$p['beschreibung'] . ' – ' . postfach_netz_text($p)) ?>">✉ <?= e((string)$p['name']) ?> <span class="muted small"><?= e((string)$p['email']) ?></span></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </span>
        <?php if ($darfPflegen): ?>
          <span class="standort__knoepfe">
            <a class="btn btn--sec btn--sm" href="<?= e(url('thw_dienststelle_edit', ['parent_id' => $d['id']])) ?>" title="Dienststelle darunter anlegen">+</a>
            <a class="btn btn--sec btn--sm" href="<?= e(url('thw_postfach_edit', ['dienststelle_id' => $d['id']])) ?>" title="Postfach anlegen">✉+</a>
            <a class="btn btn--sec btn--sm" href="<?= e(url('thw_dienststelle_edit', ['id' => $d['id']])) ?>">Bearbeiten</a>
          </span>
        <?php endif; ?>
      </div>
    </div>
    <?php
    foreach ($d['kinder'] as $k) {
        $zeile($k, $tiefe + 1);
    }
};
?>
<div class="pagehead">
  <div>
    <h1><?= e((string)setting('thw_modul_name', 'THW-Kontakte')) ?></h1>
    <p>Die Dienststellen, mit denen der Ortsverband zu tun hat – Landesverband, Regionalstelle, Nachbar-Ortsverbände – als Baum,
      und die Funktionspostfächer: welches Postfach wofür da ist und ob es über das offizielle Netz oder das Behördennetz erreichbar ist.</p>
  </div>
  <?php if ($darfPflegen): ?>
  <div class="btnrow">
    <a class="btn" href="<?= e(url('thw_dienststelle_edit')) ?>">+ Dienststelle</a>
    <a class="btn btn--sec" href="<?= e(url('thw_postfach_edit')) ?>">+ Postfach</a>
  </div>
  <?php endif; ?>
</div>

<form class="card card--tight" method="get" data-autosubmit>
  <input type="hidden" name="p" value="thw">
  <div class="field">
    <label for="q">Suche</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Dienststelle, Ort, Postfach, Zweck …">
  </div>
  <noscript><button class="btn btn--sec" type="submit">Suchen</button></noscript>
</form>

<?php if ($alle): ?>
  <div class="chips" style="margin-bottom:1rem">
    <?php foreach (THW_TYPEN as $key => $t): if (empty($zaehlung[$key])) { continue; } ?>
      <span class="chip"><?= e($t['icon']) ?> <?= (int)$zaehlung[$key] ?> <?= e((int)$zaehlung[$key] === 1 ? $t['label'] : $t['mehrzahl']) ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card" id="dienststellen">
  <div class="card__head"><h2>Dienststellen</h2><span class="muted small"><?= count($alle) ?></span></div>
  <?php if (!$alle): ?>
    <div class="empty">Noch keine Dienststelle angelegt.<?= $darfPflegen ? ' Beginne mit dem Landesverband, darunter die Regionalstelle, darunter die Ortsverbände.' : '' ?></div>
  <?php elseif (!$baum): ?>
    <div class="empty">Keine Dienststelle passt zu „<?= e($q) ?>".</div>
  <?php else: ?>
    <?php foreach ($baum as $d) { $zeile($d, 0); } ?>
  <?php endif; ?>
</section>

<section class="card" id="postfaecher">
  <div class="card__head"><h2>Funktionspostfächer</h2><span class="muted small"><?= count($postfaecher) ?></span></div>
  <?php if (!$postfaecher): ?>
    <div class="empty"><?= $q !== '' ? 'Kein Postfach passt zu „' . e($q) . '".' : 'Noch kein Postfach angelegt – etwa Einsatz, Verwaltung oder Ausbildung mit der jeweiligen Adresse.' ?></div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="data">
        <thead><tr><th>Postfach</th><th>Wofür</th><th>Dienststelle</th><th>Erreichbar über</th><?php if ($darfPflegen): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($postfaecher as $p): ?>
          <tr<?= (int)$p['is_active'] !== 1 ? ' class="is-muted"' : '' ?>>
            <td><strong><?= e((string)$p['name']) ?></strong><?= (int)$p['is_active'] !== 1 ? ' <span class="badge badge--muted">stillgelegt</span>' : '' ?>
              <div class="small"><a href="mailto:<?= e((string)$p['email']) ?>"><?= e((string)$p['email']) ?></a></div></td>
            <td class="small"><?= e((string)$p['beschreibung']) ?: '<span class="muted">–</span>' ?></td>
            <td class="small"><?= e((string)($p['dienststelle_name'] ?? '') ?: '–') ?></td>
            <td><?= $netzBadges($p) ?></td>
            <?php if ($darfPflegen): ?>
              <td class="nowrap" style="text-align:right">
                <a class="btn btn--sec btn--sm" href="<?= e(url('thw_postfach_edit', ['id' => $p['id']])) ?>">Bearbeiten</a>
                <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="postfach_aktiv"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn--sec btn--sm" type="submit"><?= (int)$p['is_active'] === 1 ? 'Stilllegen' : 'Aktivieren' ?></button></form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted" style="margin-top:.6rem"><span class="badge badge--outline">offizielles Netz</span> = über das Internet erreichbar ·
      <span class="badge" style="background:#1d4ed8">Behördennetz</span> = nur aus dem Behördennetz.</p>
  <?php endif; ?>
</section>
