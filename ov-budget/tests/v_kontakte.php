<?php
declare(strict_types=1);
session_start();
$GLOBALS['settings'] = ['kontakte_modul_name' => 'Kontakte', 'kontakte_intro' => '', 'telefon_landesvorwahl' => '+49', 'waehrung' => 'EUR'];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'contacts'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
function k(array $x): array { return $x + ['id'=>1,'anrede'=>'','titel'=>'','vorname'=>'','nachname'=>'','organisation'=>'','position'=>'','strasse'=>'','plz'=>'','ort'=>'','email'=>'','telefon'=>'','mobil'=>'','kategorie_label'=>null,'kategorie_color'=>null,'is_active'=>1,'verteiler'=>0]; }
$rows = [
  k(['vorname'=>'Anna','nachname'=>'Beispiel','organisation'=>'Stadt Musterstadt – Ordnungsamt','ort'=>'Musterstadt','email'=>'anna.beispiel@musterstadt-verwaltung.example.de','telefon'=>'0221 1234567','mobil'=>'0171 2345678','kategorie_label'=>'Behörde','kategorie_color'=>'#2563eb','verteiler'=>2]),
  k(['vorname'=>'Bernd','nachname'=>'Muster','organisation'=>'Feuerwehr','ort'=>'Beispieldorf','mobil'=>'0160 9876543','kategorie_label'=>'BOS','kategorie_color'=>'#dc2626']),
  k(['nachname'=>'Klein','organisation'=>'','email'=>'info@example.org','is_active'=>0]),
];
$filters = ['q'=>'','kategorie_id'=>0,'sort'=>'name','nur_mit_email'=>false,'aktiv'=>''];
$verteiler = [];
ob_start(); require $app . '/views/contacts.php'; $body = ob_get_clean();
echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="app.css"></head><body><main class="container" style="padding:1rem">' . $body . '</main></body></html>';
