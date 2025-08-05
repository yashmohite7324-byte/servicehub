<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get application with user data
    $stmt = $pdo->prepare("SELECT lt.*, u.id as user_id, u.wallet_balance 
                          FROM llr_tokens lt
                          JOIN users u ON lt.user_id = u.id
                          WHERE lt.token = ? FOR UPDATE");
    $stmt->execute([$token]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        throw new Exception('Application not found');
    }

    $json = file_get_contents('php://input');
    $callbackData = json_decode($json, true) ?: $_POST;
    
    if (!$callbackData || !isset($callbackData['status'])) {
        throw new Exception('Invalid callback data');
    }

    // Record status check
    $stmt = $pdo->prepare("INSERT INTO llr_status_checks 
                         (token, status, message, checked_at) 
                         VALUES (?, ?, ?, NOW())");
    $stmt->execute([$token, $callbackData['status'], $callbackData['message'] ?? '']);

    $updateData = [
        'status' => mapStatus($callbackData['status']),
        'remarks' => $callbackData['message'] ?? '',
        'queue' => $callbackData['queue'] ?? null,
        'api_response' => json_encode($callbackData),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Handle refund case
    if ($callbackData['status'] == '300') {
        $refundAmount = $application['service_price'];
        $newBalance = $application['wallet_balance'] + $refundAmount;
        
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $application['user_id']]);
        
        $stmt = $pdo->prepare("INSERT INTO payment_history 
                             (user_id, transaction_type, amount, description, 
                              reference_id, balance_after, created_at) 
                             VALUES (?, 'refund', ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $application['user_id'],
            $refundAmount,
            'LLR Exam Refund',
            $token,
            $newBalance
        ]);
        
        $updateData['refund_reason'] = $callbackData['message'] ?? 'Refunded by system';
        $updateData['completed_at'] = date('Y-m-d H:i:s');
    }
    
    // Handle completion
    if ($callbackData['status'] == '200') {
        $updateData['completed_at'] = date('Y-m-d H:i:s');
        $updateData['filename'] = $callbackData['filename'] ?? null;
    }

    // Build update query
    $setParts = [];
    $values = [];
    foreach ($updateData as $key => $value) {
        $setParts[] = "$key = ?";
        $values[] = $value;
    }
    $values[] = $token;
    
    $stmt = $pdo->prepare("UPDATE llr_tokens SET " . implode(', ', $setParts) . " WHERE token = ?");
    $stmt->execute($values);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Callback processed',
        'token' => $token,
        'new_status' => mapStatus($callbackData['status'])
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function mapStatus($apiStatus) {
    $statusMap = [
        '200' => 'completed',
        '300' => 'refunded',
        '500' => 'processing',
        '404' => 'failed'
    ];
    return $statusMap[strval($apiStatus)] ?? 'processing';
}