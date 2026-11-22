<?php
define('IPAY_ENVIRONMENT', 'sandbox');   // 'sandbox' (tests, no real money) or 'live' (real money)
define('IPAY_SECRET_KEY', '');           // sk_sandbox_… or sk_live_…  (server only, NEVER in a screenshot or on GitHub)
define('IPAY_PUBLIC_KEY', '');           // pk_… not needed by BridalCycle (only for iPay's JavaScript SDK)
define('IPAY_SECRET_HASH', '');          // Développeurs → Webhooks: the "Secret-Hash", to check iPay's notifications
