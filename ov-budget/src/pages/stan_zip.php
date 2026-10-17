<?php
declare(strict_types=1);

/** Das ganze Paket einer Version als ZIP herunterladen. ?id=… */
require_login();
$version = stan_version_find(get_int('id'));
$pfad = $version ? stan_zip_pfad($version) : null;
if (!$version || $pfad === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Zu dieser Version liegt kein Paket (mehr) in der Ablage.']);
    return;
}
$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', 'STAN_Version_' . (int)$version['nummer'] . '.zip') ?: 'stan.zip';
header('Content-Type: application/zip');
header('Content-Length: ' . (string)filesize($pfad));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($pfad);
exit;
