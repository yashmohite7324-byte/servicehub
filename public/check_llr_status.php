<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token parameter is required']);
    exit;
}

$token = $_GET['token'];
$user = $_SESSION['user'] ?? null;

try {
    // Get application from database
    $stmt = $pdo->prepare("SELECT * FROM llr_tokens WHERE token = ? AND (user_id = ? OR ? IS NULL)");
    $stmt->execute([$token, $user['id'] ?? null, $user['id'] ?? null]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        throw new Exception('Application not found');
    }
    
    // If already completed or refunded, return current status
    if (in_array($application['status'], ['completed', 'refunded'])) {
        echo json_encode([
            'success' => true,
            'status' => $application['status'],
            'message' => $application['remarks'],
            'queue' => $application['queue'],
            'filename' => $application['filename'],
            'status_changed' => false
        ]);
        exit;
    }
    
    // Get API token
    $apiToken = $application['api_token'] ?? $token;
    
    // Call API to check status
    $apiResponse = checkExamStatus($apiToken);
    
    if (!$apiResponse) {
        throw new Exception('Failed to check exam status');
    }
    
    $apiData = json_decode($apiResponse, true);
    if (!$apiData || !isset($apiData['status'])) {
        throw new Exception('Invalid API response');
    }
    
    // Map API status to our status
    $newStatus = mapApiStatus($apiData['status']);
    $statusChanged = $application['status'] !== $newStatus;
    
    // Update database
    $pdo->beginTransaction();
    
    try {
        // Record status check
        $stmt = $pdo->prepare("INSERT INTO llr_status_checks (token, status, message, checked_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$token, $apiData['status'], $apiData['message'] ?? '']);
        
        // Update application
        $updateFields = [
            'status' => $newStatus,
            'remarks' => $apiData['message'] ?? '',
            'queue' => $apiData['queue'] ?? null,
            'api_response' => $apiResponse,
            'last_checked' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (isset($apiData['filename'])) {
            $updateFields['filename'] = $apiData['filename'];
        }
        
        if ($newStatus === 'completed') {
            $updateFields['completed_at'] = date('Y-m-d H:i:s');
        }
        
        // Handle refunds
        if ($newStatus === 'refunded' && $application['status'] !== 'refunded') {
            $updateFields['refund_reason'] = $apiData['message'] ?? 'Exam refunded';
            
            // Process refund
            $refundAmount = $application['service_price'];
            
            // Update user wallet
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$refundAmount, $application['user_id']]);
            
            // Add refund to payment history
            $stmt = $pdo->prepare("INSERT INTO payment_history 
                (user_id, transaction_type, amount, description, reference_id, created_at) 
                VALUES (?, 'refund', ?, 'LLR Exam Refund', ?, NOW())");
            
            $stmt->execute([
                $application['user_id'],
                $refundAmount,
                $token
            ]);
            
            // Update session if current user
            if ($user && $user['id'] == $application['user_id']) {
                $_SESSION['user']['wallet_balance'] += $refundAmount;
            }
        }
        
        // Build update query
        $setClause = implode(', ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($updateFields)));
        
        $stmt = $pdo->prepare("UPDATE llr_tokens SET $setClause WHERE token = ?");
        $stmt->execute([...array_values($updateFields), $token]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'status' => $newStatus,
            'message' => $apiData['message'] ?? '',
            'queue' => $apiData['queue'] ?? null,
            'filename' => $apiData['filename'] ?? null,
            'status_changed' => $statusChanged,
            'api_response' => $apiData
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function checkExamStatus($apiToken) {
    $apiUrl = "https://jazainc.com/api/v2/llexam/checkexam.php";
    
    $postData = ["token" => $apiToken];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        return trim($response);
    }
    
    return null;
}

function mapApiStatus($apiStatus) {
    switch ($apiStatus) {
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