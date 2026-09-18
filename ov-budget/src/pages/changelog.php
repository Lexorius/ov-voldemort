<?php
declare(strict_types=1);

require_login();

render('changelog', [
    'title'     => 'Was ist neu',
    'version'   => app_version(),
    'abschnitte' => changelog_sections(get_str('alle') === '1' ? 1000 : 5),
    'alle'      => get_str('alle') === '1',
]);
