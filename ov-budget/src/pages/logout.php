<?php
declare(strict_types=1);

audit('logout', 'user', current_user()['id'] ?? null);
auth_logout();
session_boot();
flash('info', 'Du wurdest abgemeldet.');
redirect_route('login');
