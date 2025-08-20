<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check user authentication
$user = $_SESSION['user'] ?? null;
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get input data
$dlNumber = $_POST['dl_number'] ?? '';
$dob = $_POST['dob'] ?? '';
$documentType = $_POST['document_type'] ?? '';
$mobile = $_POST['mobile'] ?? '';

// Validate inputs
if (empty($dlNumber) || empty($dob) || empty($documentType) || empty($mobile)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use DD-MM-YYYY']);
    exit;
}

// Validate mobile number
if (!preg_match('/^[0-9]{10}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number. Must be 10 digits']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get user's current balance and service price
    $stmt = $pdo->prepare("SELECT wallet_balance, dl_update_price FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $servicePrice = $userData['dl_update_price'] ?? 100;
    
    // Check if user has sufficient balance
    if ($userData['wallet_balance'] < $servicePrice) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Insufficient wallet balance']);
        exit;
    }
    
    // Deduct amount from wallet
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$servicePrice, $user['id']]);
    
    // Add to payment history
    $stmt = $pdo->prepare("
        INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after)
        VALUES (?, 'debit', ?, 'DL number update service charge', NULL, ?)
    ");
    $stmt->execute([$user['id'], $servicePrice, $userData['wallet_balance'] - $servicePrice]);
    
    // Create DL update request
    $stmt = $pdo->prepare("
        INSERT INTO dl_update_requests (user_id, dl_number, dob, document_type, mobile, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$user['id'], $dlNumber, $dob, $documentType, $mobile]);
    $requestId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Generate WhatsApp message and link
    $whatsappData = generateWhatsAppLink($requestId, $dlNumber, $dob, $mobile, $documentType);
    
    // Return the WhatsApp link to the frontend
    echo json_encode([
        'success' => true, 
        'message' => 'DL update request submitted successfully. Request ID: ' . $requestId,
        'whatsapp_link' => $whatsappData['link'],
        'request_id' => $requestId
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("DL update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// Function to generate WhatsApp link
function generateWhatsAppLink($requestId, $dlNumber, $dob, $mobile, $documentType) {
    // Message content
    $message = 
        "📋 *New DL Update Request* 📋\n\n" .
        "➤ *Request ID:* #$requestId\n" .
        "➤ *Document Type:* $documentType\n" .
        "➤ *Document Number:* $dlNumber\n" .
        "➤ *Date of Birth:* $dob\n" .
        "➤ *Customer Mobile:* $mobile\n\n" .
        "⏰ _Submitted on: " . date('d M Y, h:i A') . "_";
    
    // Encode message for URL
    $encodedMessage = urlencode($message);
    
    // Create WhatsApp link - using API for better reliability
    $whatsappLink = "https://api.whatsapp.com/send?phone=917517458787&text=$encodedMessage";
    
    // Alternative link for direct WhatsApp Web
    $whatsappWebLink = "https://web.whatsapp.com/send?phone=917517458787&text=$encodedMessage";
    
    // Log the link
    error_log("WhatsApp Message Link: " . $whatsappLink);
    
    return [
        'success' => true, 
        'link' => $whatsappLink,
        'web_link' => $whatsappWebLink,
        'message' => $message
    ];
}
?>