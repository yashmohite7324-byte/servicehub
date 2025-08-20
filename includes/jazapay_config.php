<?php
// Jazainc Payment Gateway Configuration
return [
    'api_url' => 'https://jazainc.com/api/v2/pg/orders/pg-create-order.php',
    'status_url' => 'https://jazainc.com/api/v2/pg/orders/pg-order-status.php',
    'api_key' => '72ba3bd0183631753f45720bf6a2a5a5', // Replace with your actual API key
    'callback_url' => 'https://yourdomain.com/jazapay_callback.php',
    'redirect_url' => 'https://yourdomain.com/payment_status.php',
    'qr_code_size' => 300 // Size for QR code image
];