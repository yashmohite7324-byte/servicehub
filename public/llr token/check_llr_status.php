<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

try {
    // Get current status from database
    $stmt = $pdo->prepare("SELECT * FROM llr_tokens WHERE token = ? AND user_id = ?");
    $stmt->execute([$token, $user['id']]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        echo json_encode(['success' => false, 'message' => 'Token not found']);
        exit;
    }

    // If already completed/refunded, return current status
    if (in_array($tokenData['status'], ['completed', 'refunded'])) {
        echo json_encode([
            'success' => true,
            'status' => $tokenData['status'],
            'message' => $tokenData['remarks'],
            'queue' => $tokenData['queue'],
            'status_changed' => false
        ]);
        exit;
    }

    // Call external API to check status
    $apiUrl = "https://jazainc.com/api/v2/llexam/checkexam.php";
    $apiData = ["token" => $tokenData['api_token'] ?? $token];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($apiResponse === false || $httpCode != 200) {
        throw new Exception("API connection failed");
    }

    $apiData = json_decode($apiResponse, true);
    if (!$apiData || !isset($apiData['status'])) {
        throw new Exception("Invalid API response");
    }

    $statusChanged = false;
    $currentStatus = $tokenData['status'];
    $newStatus = match($apiData['status']) {
        '200' => 'completed',
        '300' => 'refunded',
        '500' => 'processing',
        default => $currentStatus
    };

    // Update database if status changed
    if ($newStatus !== $currentStatus) {
        $statusChanged = true;
        
        if ($newStatus === 'refunded') {
            // Process refund
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$tokenData['service_price'], $user['id']]);
                
                $stmt = $pdo->prepare("
                    UPDATE llr_tokens 
                    SET status = 'refunded',
                        remarks = ?,
                        queue = '',
                        refund_reason = ?,
                        completed_at = NOW()
                    WHERE token = ?
                ");
                $stmt->execute([
                    $apiData['message'] ?? 'Refunded',
                    $apiData['message'] ?? 'API refund',
                    $token
                ]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($newStatus === 'completed') {
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET status = 'completed',
                    remarks = ?,
                    queue = '',
                    completed_at = NOW()
                WHERE token = ?
            ");
            $stmt->execute([
                $apiData['message'] ?? 'Completed',
                $token
            ]);
        }
    }

    // Always update queue and remarks if provided
    if (isset($apiData['queue']) || isset($apiData['remarks'])) {
        $stmt = $pdo->prepare("
            UPDATE llr_tokens 
            SET queue = ?,
                remarks = ?,
                last_checked = NOW()
            WHERE token = ?
        ");
        $stmt->execute([
            $apiData['queue'] ?? $tokenData['queue'],
            $apiData['remarks'] ?? $tokenData['remarks'],
            $token
        ]);
    }

    // Get updated data
    $stmt = $pdo->prepare("SELECT * FROM llr_tokens WHERE token = ?");
    $stmt->execute([$token]);
    $updatedData = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => $updatedData['status'],
        'message' => $updatedData['remarks'],
        'queue' => $updatedData['queue'],
        'status_changed' => $statusChanged
    ]);

} catch (Exception $e) {
    error_log("Status check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking status'
    ]);
}