<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Enhanced logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');
file_put_contents(__DIR__ . '/gateway_responses.log', "\n[" . date('Y-m-d H:i:s') . "] New request for TXN: " . ($_POST['txn_id'] ?? 'UNKNOWN'), FILE_APPEND);

function logResponse($response) {
    file_put_contents(__DIR__ . '/gateway_responses.log', "\nResponse: " . substr($response, 0, 2000), FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['txn_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$gateway_txn_id = $_POST['txn_id'];

try {
    // Get payment record
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE gateway_transaction_id = ?");
    $stmt->execute([$gateway_txn_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("Payment record not found");
    }

    // Check if already completed
    if ($payment['status'] === 'success') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment already completed',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Check for timeout (10 minutes)
    if (time() - strtotime($payment['created_at']) > 600) {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE gateway_transaction_id = ?");
        $stmt->execute([$gateway_txn_id]);
        throw new Exception("Payment timeout - transaction expired");
    }

    // Call payment gateway
    $api_url = 'https://jazainc.com/api/v2/pg/orders/pg-order-status.php';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['txnid' => $gateway_txn_id]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log the raw response
    logResponse($response);

    if ($curlError) {
        throw new Exception("Gateway connection failed: " . $curlError);
    }

    if ($httpCode !== 200) {
        throw new Exception("Gateway returned HTTP code $httpCode");
    }

    // Parse the response (handles HTML, JSON, or plain text)
    $responseData = parsePaymentResponse($response);

    // Process the payment status
    processPaymentStatus($responseData, $payment, $pdo, $gateway_txn_id, $response);

} catch (Exception $e) {
    error_log("Payment error [TXN: $gateway_txn_id]: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Parses gateway response in multiple formats
 */
function parsePaymentResponse($response) {
    // First try JSON parsing
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }

    // If response starts with <, it's likely HTML
    if (strpos(trim($response), '<') === 0) {
        return parseHtmlResponse($response);
    }

    // Try to extract status from plain text
    return parsePlainTextResponse($response);
}

/**
 * Parses HTML responses from gateway
 */
function parseHtmlResponse($html) {
    // Check for common patterns in HTML
    $htmlLower = strtolower($html);
    
    if (strpos($htmlLower, 'success') !== false || strpos($htmlLower, '200') !== false) {
        return ['status' => '200', 'message' => 'Payment successful (HTML response)'];
    }
    
    if (strpos($htmlLower, 'pending') !== false || strpos($htmlLower, 'processing') !== false || strpos($htmlLower, '500') !== false) {
        return ['status' => '500', 'message' => 'Payment processing (HTML response)'];
    }
    
    if (strpos($htmlLower, 'fail') !== false || strpos($htmlLower, 'error') !== false || strpos($htmlLower, '404') !== false) {
        return ['status' => '404', 'message' => 'Payment failed (HTML response)'];
    }

    // Try to find JSON in script tags
    if (preg_match(`/<script[^>]*>\s*(var\s+data\s*=\s*({.+?})\s*;?\s*<\/script>/is`, $html, $matches)) {
        $jsonData = json_decode($matches[2], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonData;
        }
    }

    throw new Exception("Could not parse HTML response from gateway");
}

/**
 * Parses plain text responses
 */
function parsePlainTextResponse($text) {
    $textLower = strtolower($text);
    
    if (strpos($textLower, 'success') !== false || strpos($textLower, '200') !== false) {
        return ['status' => '200', 'message' => 'Payment successful'];
    }
    
    if (strpos($textLower, 'pending') !== false || strpos($textLower, '500') !== false) {
        return ['status' => '500', 'message' => 'Payment processing'];
    }
    
    if (strpos($textLower, 'fail') !== false || strpos($textLower, '404') !== false) {
        return ['status' => '404', 'message' => 'Payment failed'];
    }

    throw new Exception("Could not interpret plain text response");
}

/**
 * Processes payment status and updates database
 */
function processPaymentStatus($responseData, $payment, $pdo, $gateway_txn_id, $rawResponse) {
    if (!isset($responseData['status'])) {
        throw new Exception("Missing status in gateway response");
    }

    $pdo->beginTransaction();
    try {
        switch ($responseData['status']) {
            case '200': // Success
                // Update payment record
                $stmt = $pdo->prepare("UPDATE payments SET 
                    status = 'success',
                    updated_at = NOW(),
                    gateway_response = ?
                    WHERE gateway_transaction_id = ?");
                $stmt->execute([$rawResponse, $gateway_txn_id]);

                // Update user wallet
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$payment['amount'], $payment['user_id']]);

                // Add transaction history
                $stmt = $pdo->prepare("INSERT INTO payment_history 
                    (user_id, transaction_type, amount, description, reference_id, balance_after)
                    VALUES (?, 'credit', ?, 'Wallet top-up via payment gateway', ?, ?)");
                $stmt->execute([
                    $payment['user_id'],
                    $payment['amount'],
                    $payment['transaction_id'],
                    $payment['amount'] + ($payment['wallet_balance'] ?? 0)
                ]);

                $pdo->commit();

                echo json_encode([
                    'status' => 'success',
                    'message' => $responseData['message'] ?? 'Payment completed successfully',
                    'utr' => $responseData['utrid'] ?? null,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;

            case '500': // Pending
                $pdo->rollBack(); // No database changes for pending status
                echo json_encode([
                    'status' => 'pending',
                    'message' => $responseData['message'] ?? 'Payment processing',
                    'queue' => $responseData['queue'] ?? null,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;

            default: // Failed or unknown status
                $stmt = $pdo->prepare("UPDATE payments SET 
                    status = 'failed',
                    updated_at = NOW(),
                    gateway_response = ?
                    WHERE gateway_transaction_id = ?");
                $stmt->execute([$rawResponse, $gateway_txn_id]);
                $pdo->commit();

                echo json_encode([
                    'status' => 'failed',
                    'message' => $responseData['message'] ?? 'Payment failed',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception("Database update failed: " . $e->getMessage());
    }
}