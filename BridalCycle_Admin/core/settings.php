<?php
// Address of the project folder in the browser (no slash at the end)
define('BASE_URL', 'http://localhost/xampp/E_commerce_class/e-commerce-labs/BridalCycle_Group6');

// true = show error details (your laptop only). Put false on the live server!
define('APP_DEBUG', false);

define('APP_NAME', 'BridalCycle');
define('APP_TIMEZONE', 'Africa/Niamey');
define('CURRENCY', 'XOF');

// Security rules
define('PASSWORD_MIN_LENGTH', 10);   // users choose at least 10 characters
define('MAX_LOGIN_ATTEMPTS', 5);     // wrong passwords before the account is locked
define('LOCK_MINUTES', 15);          // how long it stays locked
define('MAX_ATTEMPTS_PER_IP', 20);   // per IP address per 15 minutes
define('SESSION_IDLE_MINUTES', 30);  // logged out after 30 minutes without activity
define('ADMIN_IDLE_MINUTES', 15);    // the admin is logged out faster

// Borrowing rules
define('RENT_MIN_DAYS', 1);
define('RENT_MAX_DAYS', 14);
define('RENT_MIN_NOTICE_DAYS', 1);

// Photo uploads
define('UPLOAD_MAX_BYTES', 3 * 1024 * 1024);  // 3 MB per photo
define('UPLOAD_MIN_PER_OUTFIT', 2);   // at least 2: the whole outfit + a close-up of the fabric
define('UPLOAD_MAX_PER_OUTFIT', 6);

// Revenue: commission on sales and rentals, featured outfits, delivery
define('COMMISSION_PERCENT', 5);     // % on sales and rentals (not on the deposit)
define('FEATURE_PRICE', 1000);       // XOF
define('FEATURE_DAYS', 7);           // days at the top
define('DELIVERY_FEE', 2000);        // XOF

// Contact and social media
define('CONTACT_EMAIL', 'bridalcycle@gmail.com');
define('SOCIAL_WHATSAPP', '');       // e.g. '22790000000'
define('SOCIAL_INSTAGRAM', '');      // e.g. 'https://www.instagram.com/your_page'
define('SOCIAL_TIKTOK', '');         // e.g. 'https://www.tiktok.com/@your_page'
define('SOCIAL_FACEBOOK', '');       // e.g. 'https://www.facebook.com/your_page'

// Payments: 'simulation' (test mode, no real money) or 'ipaymoney' (real payments)
define('PAYMENT_MODE', 'simulation');

// Languages: English and French
define('LANGUAGES', ['en' => 'English', 'fr' => 'Français']);
define('DEFAULT_LANG', 'en');
