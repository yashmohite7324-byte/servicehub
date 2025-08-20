<!-- api/llrexamcallback.php -->

<?php
// api/callbackexam.php
// This file handles callbacks from the external API to update exam status

// Set error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Log all incoming requests for debugging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET,
    'post_params' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders()
];

// Log to file for debugging (optional)
file_put_contents(__DIR__ . '/callback_logs.txt', json_encode($logData) . "\n", FILE_APPEND);

try {
    // Get token from GET parameter (as mentioned in API docs)
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token parameter is required']);
        exit;
    }
    
    // Validate token format
    if (!preg_match('/^LLR\d+$/', $token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid token format']);
        exit;
    }
    
    // Check if token exists in database
    $stmt = $pdo->prepare("SELECT * FROM llr_tokens WHERE token = ?");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Token not found']);
        exit;
    }
    
    // Don't process if already completed or refunded
    if (in_array($tokenData['status'], ['completed', 'refunded'])) {
        echo json_encode(['success' => true, 'message' => 'Token already processed', 'status' => $tokenData['status']]);
        exit;
    }
    
    // Now call the check API to get current status
    $apiUrl = "https://jazainc.com/api/v2/llexam/checkexam.php";
    $apiData = [
        "token" => $tokenData['api_token'] ?? $token
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($apiResponse === false) {
        throw new Exception('API call failed');
    }
    
    $apiResponse = trim($apiResponse);
    $apiResponseData = json_decode($apiResponse, true);
    
    if (!$apiResponseData || !isset($apiResponseData['status'])) {
        throw new Exception('Invalid API response format');
    }
    
    $apiStatus = $apiResponseData['status'];
    $apiMessage = $apiResponseData['message'] ?? '';
    $apiRemarks = $apiResponseData['remarks'] ?? '';
    $apiQueue = $apiResponseData['queue'] ?? '';
    $apiFilename = $apiResponseData['filename'] ?? '';
    
    // Update last callback time
    $stmt = $pdo->prepare("UPDATE llr_tokens SET last_callback = NOW() WHERE token = ?");
    $stmt->execute([$token]);
    
    // Process different status codes
    switch ($apiStatus) {
        case '200':
            // Exam completed successfully
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET status = 'completed',
                    remarks = ?,
                    filename = ?,
                    queue = '',
                    completed_at = NOW(),
                    api_response = ?,
                    callback_count = callback_count + 1
                WHERE token = ?
            ");
            $stmt->execute([$apiMessage, $apiFilename, $apiResponse, $token]);
            
            $responseData = [
                'success' => true,
                'message' => 'Exam completed successfully',
                'status' => 'completed',
                'token' => $token,
                'filename' => $apiFilename,
                'api_message' => $apiMessage
            ];
            break;
            
        case '300':
            // Exam failed/refunded
            $pdo->beginTransaction();
            
            try {
                $serviceFee = floatval($tokenData['service_price']);
                
                // Add refund amount to user wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$serviceFee, $tokenData['user_id']]);
                
                // Get new balance
                $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $stmt->execute([$tokenData['user_id']]);
                $newBalance = $stmt->fetchColumn();
                
                // Add refund to payment history
                $stmt = $pdo->prepare("
                    INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after) 
                    VALUES (?, 'refund', ?, 'LLR Exam Refund - Callback', ?, ?)
                ");
                $stmt->execute([$tokenData['user_id'], $serviceFee, $token, $newBalance]);
                
                // Update token status
                $stmt = $pdo->prepare("
                    UPDATE llr_tokens 
                    SET status = 'refunded',
                        remarks = ?,
                        queue = '',
                        refund_reason = ?,
                        api_response = ?,
                        completed_at = NOW(),
                        callback_count = callback_count + 1
                    WHERE token = ?
                ");
                $stmt->execute([$apiMessage, $apiMessage, $apiResponse, $token]);
                
                $pdo->commit();
                
                $responseData = [
                    'success' => true,
                    'message' => 'Exam refunded',
                    'status' => 'refunded',
                    'token' => $token,
                    'refund_amount' => $serviceFee,
                    'api_message' => $apiMessage
                ];
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case '500':
            // Still processing - update queue and remarks
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET remarks = ?,
                    queue = ?,
                    api_response = ?,
                    callback_count = callback_count + 1
                WHERE token = ?
            ");
            $stmt->execute([$apiRemarks, $apiQueue, $apiResponse, $token]);
            
            $responseData = [
                'success' => true,
                'message' => 'Status updated - still processing',
                'status' => 'processing',
                'token' => $token,
                'queue' => $apiQueue,
                'remarks' => $apiRemarks,
                'api_message' => $apiMessage
            ];
            break;
            
        case '404':
            // Token not found on API side
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET remarks = ?,
                    error_count = error_count + 1,
                    api_response = ?,
                    callback_count = callback_count + 1
                WHERE token = ?
            ");
            $stmt->execute([$apiMessage, $apiResponse, $token]);
            
            $responseData = [
                'success' => true,
                'message' => 'Token not found on API',
                'status' => $tokenData['status'],
                'token' => $token,
                'api_message' => $apiMessage
            ];
            break;
            
        default:
            // Unknown status
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET remarks = ?,
                    error_count = error_count + 1,
                    api_response = ?,
                    callback_count = callback_count + 1
                WHERE token = ?
            ");
            $stmt->execute([$apiMessage, $apiResponse, $token]);
            
            $responseData = [
                'success' => true,
                'message' => 'Unknown status received',
                'status' => $tokenData['status'],
                'token' => $token,
                'api_status' => $apiStatus,
                'api_message' => $apiMessage
            ];
            break;
    }
    
    // Log successful processing
    $logSuccess = [
        'timestamp' => date('Y-m-d H:i:s'),
        'token' => $token,
        'api_status' => $apiStatus,
        'processed_status' => $responseData['status'],
        'message' => $apiMessage,
        'success' => true
    ];
    file_put_contents(__DIR__ . '/callback_success.txt', json_encode($logSuccess) . "\n", FILE_APPEND);
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    // Log error
    $errorLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'token' => $token ?? 'unknown',
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ];
    file_put_contents(__DIR__ . '/callback_errors.txt', json_encode($errorLog) . "\n", FILE_APPEND);
    
    // Update error count if token exists
    if (!empty($token)) {
        try {
            $stmt = $pdo->prepare("UPDATE llr_tokens SET error_count = error_count + 1 WHERE token = ?");
            $stmt->execute([$token]);
        } catch (Exception $updateError) {
            // Ignore update errors
        }
    }
    
    error_log("Callback Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?>