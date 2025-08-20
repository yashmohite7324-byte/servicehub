<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/jazapay_config.php';

// Set headers for SSE (Server-Sent Events)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$txnid = $_GET['txnid'] ?? null;
$userId = $_GET['user_id'] ?? null;

if (!$txnid || !$userId) {
    die("event: error\ndata: Missing parameters\n\n");
}

// Function to send SSE message
function sendMessage($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

try {
    $maxAttempts = 30; // 30 attempts (30 seconds)
    $attempt = 0;
    $verified = false;
    
    while ($attempt < $maxAttempts && !$verified) {
        $attempt++;
        
        // Check database first
        $stmt = $pdo->prepare("SELECT status FROM payments 
                              WHERE transaction_id = ? AND user_id = ?");
        $stmt->execute([$txnid, $userId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            if ($payment['status'] === 'success') {
                sendMessage('success', ['message' => 'Payment verified']);
                $verified = true;
                break;
            } elseif ($payment['status'] === 'failed') {
                sendMessage('failed', ['message' => 'Payment failed']);
                break;
            }
        }
        
        // If not found in DB or still pending, check with gateway
        $url = $config['status_url'] . "?txnid=" . urlencode($txnid);
        $response = file_get_contents($url);
        $responseData = json_decode($response, true);
        
        if ($responseData && $responseData['status'] === "200") {
            // Update payment status in database
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE payments SET 
                                  status = 'success',
                                  gateway_response = ?,
                                  updated_at = NOW()
                                  WHERE transaction_id = ? AND user_id = ?");
            $stmt->execute([json_encode($responseData), $txnid, $userId]);
            $pdo->commit();
            
            sendMessage('success', ['message' => 'Payment verified']);
            $verified = true;
            break;
        } elseif ($responseData && $responseData['status'] !== "500") {
            // If status is not "pending" (500), mark as failed
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE payments SET 
                                  status = 'failed',
                                  gateway_response = ?,
                                  updated_at = NOW()
                                  WHERE transaction_id = ? AND user_id = ?");
            $stmt->execute([json_encode($responseData), $txnid, $userId]);
            $pdo->commit();
            
            sendMessage('failed', ['message' => $responseData['message'] ?? 'Payment failed']);
            break;
        }
        
        // If still pending, wait and try again
        if ($attempt < $maxAttempts) {
            sleep(1); // Wait 1 second before next attempt
        }
    }
    
    if (!$verified) {
        sendMessage('timeout', ['message' => 'Payment verification timeout']);
    }
} catch (Exception $e) {
    sendMessage('error', ['message' => 'Error verifying payment: ' . $e->getMessage()]);
}
?>