<?php
/** @var array $baum @var array $module @var bool $eigen */
$gruppen = [];
foreach ($baum as $i => $k) {
    if (isset($k['gruppe'])) {
        $gruppen[$i] = (string)$k['gruppe'];
    }
}
$n = count($baum);

// Knöpfe ▲ ▼ für einen Knoten oder ein Modul in seiner Gruppe
$pfeile = static function (int $knoten, string $modul, bool $erster, bool $letzter): string {
    $html = '';
    foreach (['hoch' => ['▲', 'nach oben', $erster], 'runter' => ['▼', 'nach unten', $letzter]] as $action => [$zeichen, $titel, $aus]) {
        $html .= '<form method="post" class="inline-form">' . csrf_field()
            . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="knoten" value="' . $knoten . '">'
            . '<input type="hidden" name="modul" value="' . e($modul) . '">'
            . '<button class="btn btn--sec btn--sm" type="submit" aria-label="' . $titel . '" title="' . $titel . '"' . ($aus ? ' disabled' : '') . '>' . $zeichen . '</button></form>';
    }
    return $html;
};
// Auswahl „hängt in": oberste Ebene oder eine der Gruppen
$umhaengen = static function (string $modul, int $jetzt) use ($gruppen): string {
    if (!$gruppen) {
        return '';
    }
    $html = '<form method="post" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="action" value="umhaengen"><input type="hidden" name="modul" value="' . e($modul) . '">'
        . '<select name="ziel" aria-label="Gruppe">'
        . '<option value="-1"' . ($jetzt === -1 ? ' selected' : '') . '>– oberste Ebene –</option>';
    foreach ($gruppen as $i => $name) {
        $html .= '<option value="' . (int)$i . '"' . ($jetzt === $i ? ' selected' : '') . '>' . e($name) . '</option>';
    }
    return $html . '</select> <button class="btn btn--sec btn--sm" type="submit">Umhängen</button></form>';
};
$modulZeile = static function (string $key, int $knoten, int $inGruppe, bool $erster, bool $letzter) use ($module, $pfeile, $umhaengen): string {
    $m = $module[$key] ?? ['icon' => '?', 'label' => $key, 'darf' => false];
    return '<li class="navbaum__knoten"><div class="navbaum__zeile">'
        . '<span class="navbaum__name"><span class="mainnav__icon" style="margin-right:.4rem">' . e((string)$m['icon']) . '</span>' . e((string)$m['label']) . '</span>'
        . $umhaengen($key, $inGruppe)
        . '<span class="nowrap">' . $pfeile($knoten, $inGruppe >= 0 ? $key : '', $erster, $letzter) . '</span>'
        . '</div></li>';
};
?>
<div class="pagehead">
  <div>
    <h1>Menü</h1>
    <p>Das Menü ist ein Baum: oben stehen einzelne Module und <strong>Gruppen</strong>, in einer Gruppe ihre Module. Eine Gruppe klappt in der Leiste
       ihre Module in einer zweiten Zeile auf. So, wie es hier steht, sehen es alle – wer ein Modul nicht sehen darf, bekommt es ohnehin nicht angezeigt.</p>
  </div>
  <div class="btnrow">
    <a class="btn btn--sec" href="<?= e(url('admin')) ?>">Zur Verwaltung</a>
  </div>
</div>

<p class="alert alert--info"><?= $eigen
    ? 'Dieses Menü ist angepasst. „Grundvorschlag wiederherstellen" unten setzt es zurück.'
    : 'Zu sehen ist der <strong>Grundvorschlag</strong>. Jede Änderung hier macht daraus euer eigenes Menü.' ?></p>

<section class="card">
  <div class="card__head"><h2>Aufbau</h2><span class="muted small"><?= count($gruppen) === 1 ? '1 Gruppe' : count($gruppen) . ' Gruppen' ?></span></div>
  <ol class="navbaum">
    <?php foreach ($baum as $i => $k): ?>
      <?php if (isset($k['gruppe'])): $drin = (array)$k['module']; ?>
        <li class="navbaum__knoten navbaum__knoten--gruppe">
          <div class="navbaum__zeile">
            <form method="post" class="navbaum__zeile" style="flex:1 1 18rem"><?= csrf_field() ?>
              <input type="hidden" name="action" value="gruppe_aendern"><input type="hidden" name="knoten" value="<?= (int)$i ?>">
              <input type="text" name="icon" value="<?= e((string)$k['icon']) ?>" maxlength="4" style="width:3.4rem;text-align:center" aria-label="Zeichen der Gruppe" title="Zeichen der Gruppe">
              <input type="text" name="name" value="<?= e((string)$k['gruppe']) ?>" maxlength="<?= NAV_GRUPPE_NAME_MAX ?>" required style="flex:1 1 9rem" aria-label="Name der Gruppe">
              <button class="btn btn--sec btn--sm" type="submit">Umbenennen</button>
            </form>
            <form method="post" class="inline-form"><?= csrf_field() ?>
              <input type="hidden" name="action" value="gruppe_aufloesen"><input type="hidden" name="knoten" value="<?= (int)$i ?>">
              <button class="btn btn--sec btn--sm" type="submit" title="Die Module stehen danach für sich"
                      <?= $drin ? 'data-confirm="Gruppe „' . e((string)$k['gruppe']) . '“ auflösen? Ihre Module stehen danach einzeln in der Leiste."' : '' ?>>Auflösen</button>
            </form>
            <span class="nowrap"><?= $pfeile((int)$i, '', $i === 0, $i === $n - 1) ?></span>
          </div>
          <?php if (!$drin): ?>
            <p class="small muted" style="margin:.5rem 0 0">Noch leer – eine leere Gruppe erscheint nicht in der Leiste. Module hängt man über ihre Auswahl „Umhängen" hinein.</p>
          <?php else: ?>
            <ol class="navbaum__kinder">
              <?php foreach ($drin as $j => $key): ?><?= $modulZeile((string)$key, (int)$i, (int)$i, $j === 0, $j === count($drin) - 1) ?><?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </li>
      <?php else: ?>
        <?= $modulZeile((string)$k['modul'], (int)$i, -1, $i === 0, $i === $n - 1) ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
  <p class="small muted" style="margin-bottom:0">Eine Gruppe, in der jemand nur ein Modul sehen darf, zeigt ihm dieses Modul direkt. Die Namen der Module stehen unter Einstellungen.</p>
</section>

<section class="card">
  <h2>Neue Gruppe</h2>
  <form method="post" class="form"><?= csrf_field() ?>
    <input type="hidden" name="action" value="gruppe_neu">
    <div class="filters">
      <div class="field" style="max-width:6rem">
        <label for="gruppe_icon">Zeichen</label>
        <input type="text" id="gruppe_icon" name="icon" maxlength="4" placeholder="<?= e(NAV_GRUPPE_ICON) ?>">
      </div>
      <div class="field">
        <label for="gruppe_name">Name</label>
        <input type="text" id="gruppe_name" name="name" maxlength="<?= NAV_GRUPPE_NAME_MAX ?>" required placeholder="z. B. Einsatz">
      </div>
      <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Gruppe anlegen</button></div>
    </div>
  </form>
</section>

<section class="card">
  <h2>Im Ganzen</h2>
  <div class="btnrow">
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <input type="hidden" name="action" value="grundvorschlag">
      <button class="btn btn--sec" type="submit"<?= $eigen ? ' data-confirm="Das Menü auf den Grundvorschlag zurücksetzen? Eure Gruppen und die Reihenfolge gehen dabei verloren."' : ' disabled' ?>>Grundvorschlag wiederherstellen</button>
    </form>
    <form method="post" class="inline-form"><?= csrf_field() ?>
      <input type="hidden" name="action" value="flach">
      <button class="btn btn--sec" type="submit"<?= $gruppen ? ' data-confirm="Alle Gruppen auflösen? Die Module stehen danach wie früher in einer Reihe."' : ' disabled' ?>>Alles auf eine Ebene</button>
    </form>
  </div>
  <p class="small muted" style="margin-bottom:0">Der Grundvorschlag: Übersicht, Dateiablage und Verwaltung für sich, dazu die Gruppen
    <?= e(implode(', ', array_column(array_filter(NAV_GRUNDVORSCHLAG, static fn($k) => isset($k['gruppe'])), 'gruppe'))) ?>.</p>
</section>
