<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for JSON response
header('Content-Type: application/json');

// Check if db.php exists and include it
$dbPath = __DIR__ . '/../../includes/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['success' => false, 'message' => 'Database configuration not found']);
    exit;
}

require_once $dbPath;

// Get input data
$action = trim($_POST['action'] ?? '');
$status = trim($_POST['status'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
$token = trim($_POST['token'] ?? '');
$servicePrice = trim($_POST['service_price'] ?? '');

// Validate token
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request token']);
    exit;
}

try {
    // Verify token and get request details with user-specific service price
    $stmt = $pdo->prepare("
        SELECT mcr.*, u.id as user_id, u.name as user_name, u.mobile as user_mobile, 
               COALESCE(u.medical_price, 70.00) as medical_price 
        FROM medical_certificate_requests mcr 
        LEFT JOIN users u ON mcr.user_id = u.id 
        WHERE mcr.access_token = ?
    ");
    $stmt->execute([$token]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token']);
        exit;
    }
    
    if ($action === 'get_details') {
        // Return request details with user-specific service price
        echo json_encode([
            'success' => true, 
            'request' => [
                'id' => $request['id'],
                'application_no' => $request['application_no'],
                'user_name' => $request['user_name'],
                'user_mobile' => $request['user_mobile'],
                'status' => $request['status'],
                'service_price' => $request['medical_price'], // Use user-specific price
                'admin_remarks' => $request['admin_remarks'],
                'created_at' => date('d M Y, h:i A', strtotime($request['created_at']))
            ]
        ]);
        exit;
    }
    
    // Validate inputs for status update
    if (empty($status) || empty($remarks)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate status
    $validStatuses = ['completed', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status selected']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update request status
    $updateStmt = $pdo->prepare("
        UPDATE medical_certificate_requests 
        SET status = ?, admin_remarks = ?, updated_at = NOW() 
        WHERE access_token = ?
    ");
    $result = $updateStmt->execute([$status, $remarks, $token]);
    
    if (!$result) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        exit;
    }
    
    // Process refund if status is rejected
    $refundAmount = 0;
    if ($status === 'rejected') {
        // Use the user-specific service price from the users table
        $refundAmount = $request['medical_price'];
        
        // Get current wallet balance
        $walletStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $walletStmt->execute([$request['user_id']]);
        $userData = $walletStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $newBalance = $userData['wallet_balance'] + $refundAmount;
            
            // Update wallet balance
            $updateWalletStmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $walletResult = $updateWalletStmt->execute([$newBalance, $request['user_id']]);
            
            if (!$walletResult) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to process refund']);
                exit;
            }
            
            // Record refund transaction
            $paymentStmt = $pdo->prepare("
                INSERT INTO payment_history (
                    user_id, transaction_type, amount, description, 
                    reference_id, balance_after, created_at
                ) VALUES (?, 'refund', ?, ?, ?, ?, NOW())
            ");
            $paymentResult = $paymentStmt->execute([
                $request['user_id'], 
                $refundAmount, 
                "Refund for medical certificate request #" . $request['id'] . " - " . $remarks,
                'medical_refund_' . $request['id'], 
                $newBalance
            ]);
            
            if (!$paymentResult) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to record refund transaction']);
                exit;
            }
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Status updated successfully!',
        'request_id' => $request['id'],
        'refund_amount' => $refundAmount
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Medical status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred. Please try again later.'
    ]);
}