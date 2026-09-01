<?php
declare(strict_types=1);

if (!can('view_contacts')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Kontakte sind nur für die Leitung sichtbar.']);
    return;
}

render('contact_groups', [
    'title'  => 'Verteiler',
    'rows'   => contact_groups_all(),
]);
