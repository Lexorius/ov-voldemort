<?php
/**
 * Plakette auf der Seite eines Termins, einer Veranstaltung oder Besprechung:
 * steht er in Divera, und wie viele haben gelesen und geantwortet?
 * @var string $typ @var int $refId
 */
if (!function_exists('divera_termine_aktiv') || !divera_termine_aktiv()) {
    return;
}
$eintrag = divera_termin_eintrag($typ, (int)$refId);
if (!$eintrag) {
    return;
}
$stand = is_array($eintrag['stand'] ?? null) ? $eintrag['stand'] : [];
$status = (string)$eintrag['status'];
[$farbe, $text] = match ($status) {
    'gesendet'   => ['#0284c7', 'in Divera'],
    'fehler'     => ['#b45309', 'Divera: noch nicht angekommen'],
    'archiviert' => ['#64748b', 'in Divera archiviert'],
    default      => ['#64748b', 'Divera: wird angelegt'],
};
?>
<span class="badge" style="background:<?= e($farbe) ?>" title="<?= e($status === 'fehler' ? (string)$eintrag['fehler'] : ('Divera-Termin #' . (int)($eintrag['divera_id'] ?? 0))) ?>"><?= e($text) ?></span>
<?php if ($stand && $status === 'gesendet'): ?><span class="small muted"><?= e(divera_termin_stand_text($stand)) ?></span><?php endif; ?>
