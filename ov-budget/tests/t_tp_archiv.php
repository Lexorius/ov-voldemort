<?php
declare(strict_types=1);
/* Themenarchiv: Abfrage und Aufgaben je Thema */
$GLOBALS['abfragen'] = [];
function db_all(string $sql, array $p = []): array {
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (str_contains($sql, 'FROM todos')) {
        return [['id' => 50, 'titel' => 'Angebot holen', 'talking_point_id' => 2, 'erledigt' => 1]];
    }
    return [['id' => 1, 'titel' => 'A'], ['id' => 2, 'titel' => 'B']];
}
require dirname(__DIR__) . '/src/lib/meetings.php';
$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$rows = tp_archive(['q' => 'Zelt', 'fachgruppe_id' => 4, 'status_id' => 9, 'von' => '2026-01-01', 'bis' => '2026-09-30']);
[$q, $p] = $GLOBALS['abfragen'][0];
$check('nur Themen außerhalb des Speichers', str_contains($q, '(tp.meeting_id IS NOT NULL OR COALESCE(st.is_final, 0) = 1)'));
$check('Suche auch im Ergebnis', str_contains($q, 'tp.ergebnis LIKE ?'));
$check('Parameter in Reihenfolge', $p === ['%Zelt%', '%Zelt%', '%Zelt%', 4, 9, '2026-01-01', '2026-09-30']);
$check('Vorgänger verbunden', str_contains($q, 'LEFT JOIN talking_points vg ON vg.id = tp.vorgaenger_id'));
$check('Joins des Kopfes bleiben', str_contains($q, 'LEFT JOIN list_items st') && str_contains($q, 'ON m.id  = tp.meeting_id'));
$check('Nachfolger', str_contains($q, 'AS nachfolger_meeting_id'));
$check('Obergrenze +1', str_contains($q, 'LIMIT ' . (TP_ARCHIV_LIMIT + 1)));
$check('neueste zuerst', str_contains($q, 'ORDER BY COALESCE(m.datum, DATE(tp.updated_at)) DESC'));
$check('Aufgaben mit ids', $GLOBALS['abfragen'][1][1] === [1, 2]);
$check('Aufgaben zugeordnet', $rows[0]['aufgaben'] === [] && $rows[1]['aufgaben'][0]['titel'] === 'Angebot holen');

$GLOBALS['abfragen'] = [];
tp_archive([]);
$check('ohne Filter keine Parameter', $GLOBALS['abfragen'][0][1] === []);
$check('Ergebnis genau einmal FROM talking_points tp', substr_count($GLOBALS['abfragen'][0][0], 'FROM talking_points tp') === 1);
echo "$ok bestanden, $fail fehlgeschlagen\n";
