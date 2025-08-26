<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/status_errors.log');

header('Content-Type: application/json');

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['txn_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request parameters'
    ]);
    exit;
}

$gatewayTxnId = $_POST['txn_id'];

try {
    // Get payment record
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE gateway_transaction_id = ?");
    $stmt->execute([$gatewayTxnId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Payment record not found");
    }
    
    // If already successful, return success
    if ($payment['status'] === 'success') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment already processed successfully',
            'utr' => $payment['utr_id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Call JazaPay status API with multiple fallback approaches
    $url = "https://jazainc.com/api/v2/pg/orders/pg-order-status.php";
    $data = ["txnid" => $gatewayTxnId];
    
    // Try multiple approaches to get a response
    $response = null;
    $error = null;
    
    // Approach 1: Regular cURL request
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FAILONERROR => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log the raw response for debugging
    error_log("JazaPay Status Check - HTTP Code: $httpCode, Error: $error, Response: " . 
              ($response ? substr($response, 0, 500) : 'NULL'));
    
    // If we got an empty response, try alternative approach
    if (empty($response)) {
        error_log("Trying alternative approach for JazaPay status check");
        
        // Approach 2: Use file_get_contents with stream context
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 15
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log("File get contents error: " . ($error['message'] ?? 'Unknown error'));
        }
    }
    
    // If still empty, try one more approach with different parameters
    if (empty($response)) {
        error_log("Trying third approach for JazaPay status check");
        
        // Approach 3: Try GET request instead of POST
        $url_with_params = $url . '?' . http_build_query($data);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url_with_params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
    }
    
    // Check if we got a valid response after all attempts
    if (empty($response)) {
        // Check if this might be a temporary gateway issue
        // For pending transactions, the gateway might not respond immediately
        
        // Get the payment creation time
        $createdTime = strtotime($payment['created_at']);
        $currentTime = time();
        $timeDiff = $currentTime - $createdTime;
        
        // If it's been less than 5 minutes, assume it's still processing
        if ($timeDiff < 300) { // 5 minutes
            echo json_encode([
                'status' => 'pending',
                'message' => 'Payment is processing. Please wait...',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        } else {
            throw new Exception("Payment gateway is not responding. Please try again later.");
        }
    }
    
    $responseData = json_decode($response, true);
    
    // If JSON decoding failed, try to extract JSON from response
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        
        // Try to extract JSON from possible HTML response
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $responseData = json_decode($matches[0], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If it's a string that might contain status info
                if (strpos(strtolower($response), 'success') !== false) {
                    $responseData = ['status' => '200', 'message' => 'Payment successful'];
                } elseif (strpos(strtolower($response), 'pending') !== false) {
                    $responseData = ['status' => '500', 'message' => 'Payment pending'];
                } else {
                    throw new Exception("Could not parse gateway response: " . substr($response, 0, 200));
                }
            }
        } else {
            // Check if it's a simple text response
            $lowerResponse = strtolower(trim($response));
            if ($lowerResponse === 'success' || $lowerResponse === 'completed') {
                $responseData = ['status' => '200', 'message' => 'Payment successful'];
            } elseif ($lowerResponse === 'pending' || $lowerResponse === 'processing') {
                $responseData = ['status' => '500', 'message' => 'Payment pending'];
            } else {
                throw new Exception("Invalid gateway response format: " . substr($response, 0, 200));
            }
        }
    }
    
    if (!$responseData || !isset($responseData['status'])) {
        throw new Exception("Invalid API response structure");
    }
    
    $timestamp = date('Y-m-d H:i:s');
    
    // Handle different status responses based on JazaPay documentation
    $statusCode = (string)$responseData['status'];
    
    switch ($statusCode) {
        case '200': // Success
            $utr = $responseData['utrid'] ?? ($responseData['utr'] ?? ($responseData['transaction_id'] ?? 'N/A'));
            
            // Update payment record
            $stmt = $pdo->prepare("UPDATE payments SET 
                status = 'success',
                utr_id = ?,
                updated_at = NOW(),
                gateway_response = ?
                WHERE gateway_transaction_id = ?");
            $stmt->execute([$utr, json_encode($responseData), $gatewayTxnId]);
            
            // Update user wallet (trigger will handle this)
            echo json_encode([
                'status' => 'success',
                'message' => $responseData['message'] ?? 'Payment completed successfully',
                'utr' => $utr,
                'timestamp' => $timestamp
            ]);
            break;
            
        case '500': // Pending
        case 'processing':
            echo json_encode([
                'status' => 'pending',
                'message' => $responseData['message'] ?? 'Transaction in progress',
                'queue' => $responseData['queue'] ?? ($responseData['position'] ?? null),
                'remarks' => $responseData['remarks'] ?? ($responseData['status_text'] ?? null),
                'timestamp' => $timestamp
            ]);
            break;
            
        case '404': // Error
        case 'failed':
        case 'error':
            // Update payment as failed
            $stmt = $pdo->prepare("UPDATE payments SET 
                status = 'failed',
                gateway_response = ?,
                updated_at = NOW()
                WHERE gateway_transaction_id = ?");
            $stmt->execute([json_encode($responseData), $gatewayTxnId]);
            
            echo json_encode([
                'status' => 'failed',
                'message' => $responseData['message'] ?? ($responseData['error'] ?? 'Payment failed'),
                'timestamp' => $timestamp
            ]);
            break;
            
        default:
            // Handle unknown status codes as pending
            echo json_encode([
                'status' => 'pending',
                'message' => $responseData['message'] ?? 'Payment processing',
                'timestamp' => $timestamp
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Status check error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Status check failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>