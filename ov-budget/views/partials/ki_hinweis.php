<?php
/**
 * KI-Zusammenfassung und Schlagwörter unter einer Datei.
 * @var string $typ      fahrzeug | veranstaltung | termin | standort
 * @var array  $datei
 * @var ?array $ki       Ergebnis (tags als Liste) oder null
 * @var ?array $auftrag  offener/fehlgeschlagener Auftrag oder null
 * @var bool   $darf     bearbeiten und neu auswerten dürfen
 */
$ki ??= null;
$auftrag ??= null;
$aktiv = ki_modul_aktiv($typ);
$status = (string)($auftrag['status'] ?? '');
if (!$ki && !($aktiv && $darf) && !in_array($status, ['offen', 'laeuft', 'fehler'], true)) {
    return;
}
?>
<div class="ki">
  <?php if ($ki): ?>
    <?php if ($ki['zusammenfassung'] !== ''): ?>
      <div class="ki__text"><span class="ki__marke" title="Von der KI zusammengefasst">✨</span> <?= e((string)$ki['zusammenfassung']) ?></div>
    <?php endif; ?>
    <?php if ($ki['tags']): ?>
      <div class="chips ki__tags">
        <?php foreach ($ki['tags'] as $t): ?>
          <a class="chip chip--link" href="<?= e(url('ki_suche', ['q' => $t])) ?>" title="Dateien mit diesem Schlagwort">#<?= e($t) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="small muted">KI-Auswertung<?= $ki['modell'] !== '' ? ' · ' . e((string)$ki['modell']) : '' ?> · <?= e(de_datetime((string)$ki['geaendert_am'])) ?><?= ($ki['quelle'] ?? '') === 'hand' ? ' · von Hand angepasst' : '' ?><?= in_array($status, ['offen', 'laeuft'], true) ? ' · ⏳ wird neu ausgewertet' : '' ?></div>
  <?php elseif (in_array($status, ['offen', 'laeuft'], true)): ?>
    <div class="small muted">⏳ KI-Auswertung ausstehend – kommt in den nächsten Minuten.</div>
  <?php elseif ($status === 'fehler'): ?>
    <div class="small muted">KI-Auswertung fehlgeschlagen: <?= e((string)($auftrag['fehler'] ?? '')) ?></div>
  <?php endif; ?>
  <?php if ($darf && ($aktiv || $ki)): ?>
    <details class="ki__edit">
      <summary class="small muted"><?= $ki ? 'KI-Auswertung bearbeiten' : 'KI-Auswertung' ?></summary>
      <form method="post" action="<?= e(url('ki_action')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="typ" value="<?= e($typ) ?>">
        <input type="hidden" name="datei_id" value="<?= (int)$datei['id'] ?>">
        <div class="field"><label>Zusammenfassung</label>
          <textarea name="zusammenfassung" rows="3" maxlength="2000"><?= e((string)($ki['zusammenfassung'] ?? '')) ?></textarea></div>
        <div class="field"><label>Schlagwörter (mit Komma getrennt)</label>
          <input type="text" name="tags" maxlength="500" value="<?= e(ki_tags_text($ki['tags'] ?? [])) ?>"></div>
        <div class="btnrow">
          <button class="btn btn--sm" type="submit" name="action" value="speichern">Speichern</button>
          <?php if ($aktiv): ?>
            <button class="btn btn--sec btn--sm" type="submit" name="action" value="neu" title="Die Datei noch einmal vom Modell auswerten lassen">Neu auswerten</button>
          <?php endif; ?>
          <?php if ($ki): ?>
            <button class="btn btn--sec btn--sm" type="submit" name="action" value="loeschen" data-confirm="Zusammenfassung und Schlagwörter dieser Datei entfernen?">Entfernen</button>
          <?php endif; ?>
        </div>
      </form>
    </details>
  <?php endif; ?>
</div>
