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
        SELECT r.*, u.id as user_id, COALESCE(u.dl_update_price, 100.00) as dl_update_price 
        FROM dl_update_requests r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.access_token = ?
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
                'document_type' => $request['document_type'],
                'dl_number' => $request['dl_number'],
                'dob' => $request['dob'],
                'mobile' => $request['mobile'],
                'status' => $request['status'],
                'service_price' => $request['dl_update_price'], // Use user-specific price
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
        UPDATE dl_update_requests 
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
        $refundAmount = $request['dl_update_price'];
        
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
                "Refund for DL update request #" . $request['id'] . " - " . $remarks,
                'dlupdate_refund_' . $request['id'], 
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
    error_log("Status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred. Please try again later.'
    ]);
}