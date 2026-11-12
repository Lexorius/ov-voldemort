<?php
/**
 * Ein Dokument in der Dateiablage – gleich, aus welcher Ablage es kommt.
 * @var array  $d         Zeile aus ablage_zeilen() mit darf_ki, darf_aendern und (bei Treffern) knoten
 * @var array  $kiDaten   [typ => Ergebnisse und Aufträge]
 * @var array  $baum
 * @var array  $eigene    eigene Kategorien für das Verschieben
 * @var bool   $zeigeOrt  die Kategorie dazuschreiben (Suche, Zuletzt abgelegt)
 * @var string $hier      Adresse der Seite, auf der die Zeile steht
 */
$typ = (string)$d['typ'];
$anker = $typ === 'ablage' ? 'd' . (int)$d['id'] : 'd-' . $typ . '-' . (int)$d['id'];
$ortKey = (string)($d['knoten'] ?? '');
$meta = array_filter([
    $d['name'] !== (string)$d['orig_name'] ? (string)$d['orig_name'] : '',
    bytes_human((int)$d['size_bytes']),
    !empty($d['created_at']) ? de_datetime((string)$d['created_at']) : '',
    (string)($d['hochgeladen_von'] ?? ''),
], static fn(string $x): bool => $x !== '');
?>
<div class="doc" id="<?= e($anker) ?>">
  <?php if ($d['vorschau'] !== ''): ?>
    <a href="<?= e($d['link']) ?>" target="_blank" rel="noopener"><img class="doc__thumb" src="<?= e($d['vorschau']) ?>" alt="" loading="lazy"></a>
  <?php else: ?>
    <span class="doc__icon" aria-hidden="true"><?= e(ablage_endung((string)$d['orig_name'])) ?></span>
  <?php endif; ?>
  <div class="doc__text">
    <a href="<?= e($d['link']) ?>" target="_blank" rel="noopener"><?= e((string)$d['name']) ?></a>
    <?php if ($d['zusatz'] !== ''): ?><span class="badge badge--outline"><?= e($d['zusatz']) ?></span><?php endif; ?>
    <div class="small muted"><?= e(implode(' · ', $meta)) ?></div>
    <?php if ($zeigeOrt && $ortKey !== ''): ?>
      <div class="small"><?= !empty($baum['knoten'][$ortKey]['auto']) ? '🔒' : '📁' ?> <a href="<?= e(url('ablage', ['k' => $ortKey])) ?>#<?= e($anker) ?>"><?= e(ablage_pfad_text($ortKey, $baum)) ?></a></div>
    <?php endif; ?>
    <?= function_exists('ki_hinweis_html') && isset(KI_TYPEN[$typ])
        ? ki_hinweis_html($typ, $d, $kiDaten[$typ] ?? ['ergebnisse' => [], 'auftraege' => []], !empty($d['darf_ki']),
            ['zurueck' => $hier . '#' . $anker, 'suche' => 'ablage', 'immer' => true])
        : '' ?>
    <?php if (!empty($d['darf_aendern'])): ?>
      <details class="ki__edit">
        <summary class="small muted">Umbenennen, verschieben, entfernen</summary>
        <form method="post" action="<?= e($hier) ?>" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="datei_id" value="<?= (int)$d['id'] ?>">
          <div class="grid2">
            <div class="field"><label>Bezeichnung</label>
              <input type="text" name="titel" maxlength="200" value="<?= e((string)$d['titel']) ?>"></div>
            <div class="field"><label>Kategorie</label>
              <select name="kategorie_id"><?= ablage_kategorie_optionen(
                  array_values(array_filter($eigene, static fn(array $k): bool => isset($baum['knoten']['k' . (int)$k['id']]))), (int)$d['objekt_id']) ?></select></div>
          </div>
          <div class="btnrow">
            <button class="btn btn--sm" type="submit" name="action" value="datei_aendern">Speichern</button>
            <button class="btn btn--sec btn--sm" type="submit" name="action" value="datei_loeschen" data-confirm="„<?= e((string)$d['name']) ?>“ endgültig entfernen?">Entfernen</button>
          </div>
        </form>
      </details>
    <?php endif; ?>
  </div>
</div>
