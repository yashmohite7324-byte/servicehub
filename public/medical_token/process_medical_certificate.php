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

// Get user's wallet balance and medical certificate price
$stmt = $pdo->prepare("SELECT wallet_balance, medical_price, name, mobile FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'User data not found']);
    exit;
}

$walletBalance = $userData['wallet_balance'] ?? 0;
$servicePrice = $userData['medical_price'] ?? 70;
$userName = $userData['name'] ?? 'Customer';
$userMobile = $userData['mobile'] ?? '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input data
    $applicationNo = trim($_POST['application_no'] ?? '');
    
    // Validate inputs
    $errors = [];
    
    if (empty($applicationNo)) {
        $errors[] = "Application number is required";
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
        
        // Create medical certificate request
        $stmt = $pdo->prepare("
            INSERT INTO medical_certificate_requests (
                user_id, application_no, status, access_token, service_price, created_at
            ) VALUES (?, ?, 'pending', ?, ?, NOW())
        ");
        $result = $stmt->execute([
            $user['id'], 
            $applicationNo, 
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
            'Medical certificate service charge - Request #' . $requestId, 
            'medical_' . $requestId, 
            $newBalance
        ]);
        
        $pdo->commit();
        
        // Send WhatsApp message to admin using NineDigit API
        $apiResponse = sendWhatsAppMessageViaAPI($requestId, $accessToken, $applicationNo, $userName, $userMobile);
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => "Medical certificate request submitted successfully! Request ID: #" . $requestId,
            'request_id' => $requestId
        ];
        
        // Add WhatsApp API response if needed for debugging
        if (isset($apiResponse['status']) && $apiResponse['status'] === true) {
            $response['whatsapp_status'] = 'Message sent successfully';
        } else {
            $response['whatsapp_status'] = 'Message sending might have failed';
            error_log("WhatsApp API error for medical request #$requestId: " . json_encode($apiResponse));
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Medical certificate error for user {$user['id']}: " . $e->getMessage());
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
function sendWhatsAppMessageViaAPI($requestId, $accessToken, $applicationNo, $userName, $userMobile) {
    // API Configuration - Update these values according to your NineDigit account
    $apiKey = "YfP6Riq9jqfZ4nnlenvFPU3PPmLspd"; // Replace with your actual API key
    $senderNumber = "919604610640"; // The number registered with your NineDigit account
    $adminNumber = "917517458787"; // Admin WhatsApp number (without country code)
    
    // Create the update URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $updateUrl = $protocol . '://' . $host . '/public/medical_token/update_medical_request.php?token=' . $accessToken;
    
    // Create WhatsApp message
    $message = "🏥 NEW MEDICAL CERTIFICATE REQUEST 🏥\n\n";
    $message .= "📋 Request Details:\n";
    $message .= "• Request ID: #$requestId\n";
    $message .= "• Application No: $applicationNo\n";
    $message .= "• Status: Pending\n";
    $message .= "• Submitted: " . date('d M Y, h:i A') . "\n\n";
    $message .= "🔗 Update Status:\n";
    $message .= "$updateUrl\n\n";
    $message .= "⚠️ Click the link to update request status";
    
    $footer = "servicehub.site /Medical Certificate Service";
    
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
        error_log("WhatsApp Message sent successfully for Medical Certificate Request #$requestId");
        return $responseData;
    } else {
        error_log("Failed to send WhatsApp message for medical request #$requestId: " . $response);
        return ['status' => false, 'error' => $responseData['msg'] ?? 'Unknown error'];
    }
}