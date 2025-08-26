<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check user authentication
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Get user's wallet balance and DL update price
$stmt = $pdo->prepare("SELECT wallet_balance, dl_update_price, name FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User data not found']);
    exit;
}

$walletBalance = $userData['wallet_balance'] ?? 0;
$servicePrice = $userData['dl_update_price'] ?? 100;
$userName = $userData['name'] ?? 'Customer';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data
    $dlNumber = trim($_POST['dl_number'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $documentType = trim($_POST['document_type'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    
    // Validate inputs
    $errors = [];
    
    if (empty($documentType)) {
        $errors[] = "Document type is required";
    }
    
    if (empty($dlNumber)) {
        $errors[] = "Document number is required";
    }
    
    if (empty($dob) || !preg_match('/^(0[1-9]|[12][0-9]|3[01])-(0[1-9]|1[012])-\d{4}$/', $dob)) {
        $errors[] = "Valid date of birth in DD-MM-YYYY format is required";
    }
    
    if (empty($mobile) || !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        $errors[] = "Valid 10-digit mobile number is required";
    }
    
    // Check wallet balance
    if ($walletBalance < $servicePrice) {
        $errors[] = "Insufficient wallet balance. Required: ₹" . number_format($servicePrice, 2) . ", Available: ₹" . number_format($walletBalance, 2);
    }
    
    // Return errors if any
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate a unique access token for the request
        $accessToken = bin2hex(random_bytes(32));
        
        // Create DL update request
        $stmt = $pdo->prepare("
            INSERT INTO dl_update_requests (
                user_id, dl_number, dob, document_type, mobile, 
                status, access_token, service_price, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $user['id'], 
            $dlNumber, 
            $dob, 
            $documentType, 
            $mobile, 
            $accessToken, 
            $servicePrice
        ]);
        
        if (!$result) {
            throw new Exception("Failed to create request");
        }
        
        $requestId = $pdo->lastInsertId();
        
        // Deduct amount from wallet
        $newBalance = $walletBalance - $servicePrice;
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user['id']]);
        
        // Add to payment history
        $stmt = $pdo->prepare("
            INSERT INTO payment_history (
                user_id, transaction_type, amount, description, 
                reference_id, balance_after, created_at
            ) VALUES (?, 'debit', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user['id'], 
            $servicePrice, 
            'DL update service charge - Request #' . $requestId, 
            'dlupdate_' . $requestId, 
            $newBalance
        ]);
        
        $pdo->commit();
        
        // Send WhatsApp message to admin using NineDigit API
        $apiResponse = sendWhatsAppMessageViaAPI($requestId, $accessToken, $dlNumber, $dob, $documentType, $mobile, $userName);
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => "DL update request submitted successfully! Request ID: #" . $requestId,
            'request_id' => $requestId
        ];
        
        // Add WhatsApp API response if needed for debugging
        if (isset($apiResponse['status']) && $apiResponse['status'] === true) {
            $response['whatsapp_status'] = 'Message sent successfully';
        } else {
            $response['whatsapp_status'] = 'Message sending might have failed';
            error_log("WhatsApp API error for request #$requestId: " . json_encode($apiResponse));
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("DL update error for user {$user['id']}: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred. Please try again later.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

/**
 * Function to send WhatsApp message to admin using NineDigit API
 */
function sendWhatsAppMessageViaAPI($requestId, $accessToken, $dlNumber, $dob, $documentType, $mobile, $userName) {
    // API Configuration - Update these values according to your NineDigit account
    $apiKey = "YfP6Riq9jqfZ4nnlenvFPU3PPmLspd"; // Replace with your actual API key
    $senderNumber = "919604610640"; // The number registered with your NineDigit account
    $adminNumber = "917517458787"; // Admin WhatsApp number (without country code)
    
    // Create the update URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $updateUrl = $protocol . '://' . $host . '/public/DL_no_update/update_request.php?token=' . $accessToken;
    
    // Create WhatsApp message
    $message = "🚗 NEW DL UPDATE REQUEST 🚗\n\n";
    $message .= "📋 Request Details:\n";
    $message .= "• Request ID: #$requestId\n";
    $message .= "• Document Type: $documentType\n";
    $message .= "• Document Number: $dlNumber\n";
    $message .= "• Date of Birth: $dob\n";
    $message .= "• Mobile Number: $mobile\n";
    $message .= "• Status: Pending\n";
    $message .= "• Submitted: " . date('d M Y, h:i A') . "\n\n";
    $message .= "🔗 Update Status:\n";
    $message .= "$updateUrl\n\n";
    $message .= "⚠️ Click the link to update request status";
    
    $footer = "servicehub.site /DL Update Service";
    
    // Prepare API request data
    $postData = [
        'api_key' => $apiKey,
        'sender' => $senderNumber,
        'number' => $adminNumber,
        'message' => $message,
        'footer' => $footer
    ];
    
    // API endpoint
    $apiUrl = 'https://niyowp.ninedigit.xyz/send-message';
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    error_log("NineDigit API Call - Request: " . json_encode($postData));
    error_log("NineDigit API Response - Code: $httpCode, Response: $response");
    
    if ($response === false) {
        error_log("cURL Error: $curlError");
        return ['status' => false, 'error' => $curlError];
    }
    
    // Parse the response
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === true) {
        error_log("WhatsApp Message sent successfully for DL Update Request #$requestId");
        return $responseData;
    } else {
        error_log("Failed to send WhatsApp message for request #$requestId: " . $response);
        return ['status' => false, 'error' => $responseData['msg'] ?? 'Unknown error'];
    }
}