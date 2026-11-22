<?php
define('MAIL_ENABLED', false);                 // true when the Gmail app password is filled in below
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);                      // 587 = STARTTLS (encrypted)
define('MAIL_USERNAME', '');                   // the Gmail address that sends, e.g. bridalcycle.niger@gmail.com
define('MAIL_APP_PASSWORD', '');               // 16-letter Google "App password" (spaces are fine)
define('MAIL_FROM_NAME', 'BridalCycle');

// BridalCycle's own inbox: receives a copy of every payment and payout receipt
define('BUSINESS_EMAIL', 'ladyboss1700@gmail.com');
