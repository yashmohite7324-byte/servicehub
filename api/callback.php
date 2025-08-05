<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

// Get token from URL parameter
$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token required']);
    exit;
}

try {
    // Get application data
    $stmt = $pdo->prepare("SELECT * FROM llr_tokens WHERE token = ?");
    $stmt->execute([$token]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Token not found']);
        exit;
    }

    // Get callback data from POST body or GET parameters
    $callbackData = null;
    
    // Try JSON from POST body first
    $json = file_get_contents('php://input');
    if ($json) {
        $callbackData = json_decode($json, true);
    }
    
    // Fallback to POST parameters
    if (!$callbackData && !empty($_POST)) {
        $callbackData = $_POST;
    }
    
    // Fallback to GET parameters (for testing)
    if (!$callbackData && !empty($_GET) && isset($_GET['status'])) {
        $callbackData = $_GET;
    }
    
    if (!$callbackData || !isset($callbackData['status'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid callback data']);
        exit;
    }

    // Log the callback for debugging
    error_log("LLR Callback received for token $token: " . json_encode($callbackData));
    
    // Map API status to our internal status
    $newStatus = mapApiStatusCode($callbackData['status']);
    $statusChanged = $application['status'] !== $newStatus;
    
    $pdo->beginTransaction();
    
    try {
        // Record the callback in status checks
        $stmt = $pdo->prepare("INSERT INTO llr_status_checks (token, status, message, checked_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $token,
            $callbackData['status'],
            $callbackData['message'] ?? ''
        ]);
        
        // Prepare update data
        $updateData = [
            'status' => $newStatus,
            'remarks' => $callbackData['message'] ?? '',
            'queue' => $callbackData['queue'] ?? null,
            'api_response' => json_encode($callbackData),
            'last_api_response' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle filename for completed exams
        if (isset($callbackData['filename']) && !empty($callbackData['filename'])) {
            $updateData['filename'] = $callbackData['filename'];
        }
        
        // Handle completion
        if ($newStatus === 'completed') {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
            $updateData['queue'] = null; // Clear queue when completed
        }
        
        // Handle refunds
        if ($newStatus === 'refunded' && $application['status'] !== 'refunded') {
            $updateData['refund_reason'] = $callbackData['message'] ?? 'Exam refunded by API';
            $updateData['queue'] = null; // Clear queue when refunded
            
            // Process refund
            $refundAmount = $application['service_price'];
            
            // Update user wallet
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$refundAmount, $application['user_id']]);
            
            // Get updated balance for history
            $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
            $stmt->execute([$application['user_id']]);
            $newBalance = $stmt->fetchColumn();
            
            // Add refund to payment history
            $stmt = $pdo->prepare("INSERT INTO payment_history 
                (user_id, transaction_type, amount, description, reference_id, balance_after, created_at) 
                VALUES (?, 'refund', ?, 'LLR Exam Refund - API Callback', ?, ?, NOW())");
            
            $stmt->execute([
                $application['user_id'],
                $refundAmount,
                $token,
                $newBalance
            ]);
        }
        
        // Build and execute update query
        $setClause = implode(', ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($updateData)));
        
        $stmt = $pdo->prepare("UPDATE llr_tokens SET $setClause WHERE token = ?");
        $stmt->execute([...array_values($updateData), $token]);
        
        $pdo->commit();
        
        // Log successful update
        error_log("LLR Callback processed successfully for token $token - Status: $newStatus");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Callback processed successfully',
            'token' => $token,
            'new_status' => $newStatus
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("LLR Callback Error for token $token: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function mapApiStatusCode($apiStatus) {
    switch (strval($apiStatus)) {
        case '200':
            return 'completed';
        case '300':
            return 'refunded';
        case '500':
            return 'processing';
        case '404':
        default:
            return 'processing';
    }
}
?>