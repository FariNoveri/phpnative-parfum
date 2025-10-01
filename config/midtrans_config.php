<?php
// Midtrans Configuration
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-NRXzGHcOkFEGWTTDufoVh3e6'); // Dari dashboard Midtrans
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-Vvw9zJbho9SllsaX'); // Dari dashboard Midtrans
define('MIDTRANS_IS_PRODUCTION', false); // false untuk sandbox, true untuk production
define('MIDTRANS_IS_SANITIZED', true);
define('MIDTRANS_IS_3DS', true);

// Midtrans API URL
if (MIDTRANS_IS_PRODUCTION) {
    define('MIDTRANS_API_URL', 'https://app.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_SNAP_URL', 'https://app.midtrans.com/snap/snap.js');
} else {
    define('MIDTRANS_API_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js');
}