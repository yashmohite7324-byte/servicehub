<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$applno = trim($input['applno'] ?? '');
$dob = trim($input['dob'] ?? '01-01-2000');
$password = trim($input['password'] ?? 'default123');
$exam_pin = trim($input['exam_pin'] ?? '');
$exam_type = 'day'; // Always day exam

if (empty($applno)) {
    echo json_encode(['success' => false, 'message' => 'Application number is required']);
    exit;
}

try {
    // Get user wallet balance and service price
    $stmt = $pdo->prepare("SELECT wallet_balance, llr_price FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    $walletBalance = floatval($userData['wallet_balance']);
    $serviceFee = floatval($userData['llr_price']);

    if ($walletBalance < $serviceFee) {
        echo json_encode([
            'success' => false,
            'message' => "Insufficient wallet balance. Required: ₹{$serviceFee}, Available: ₹{$walletBalance}"
        ]);
        exit;
    }

    // Check for duplicate application
    $stmt = $pdo->prepare("SELECT token FROM llr_tokens WHERE user_id = ? AND applno = ? AND status NOT IN ('refunded')");
    $stmt->execute([$user['id'], $applno]);
    $existingToken = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingToken) {
        echo json_encode([
            'success' => false,
            'message' => 'This application number has already been submitted. Token: ' . $existingToken['token']
        ]);
        exit;
    }

    // Generate unique token
    $token = 'LLR' . time() . rand(100, 999);

    // Start transaction
    $pdo->beginTransaction();

    // Deduct amount from wallet
    $newBalance = $walletBalance - $serviceFee;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $user['id']]);

    // Add payment history
    $stmt = $pdo->prepare("
        INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after) 
        VALUES (?, 'debit', ?, 'LLR Exam Application', ?, ?)
    ");
    $stmt->execute([$user['id'], $serviceFee, $token, $newBalance]);

    // Insert into llr_tokens table
    $stmt = $pdo->prepare("
        INSERT INTO llr_tokens 
        (user_id, token, applno, dob, password, exam_pin, exam_type, service_price, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())
    ");
    $stmt->execute([
        $user['id'],
        $token,
        $applno,
        $dob,
        $password,
        $exam_pin ?: null,
        $exam_type,
        $serviceFee
    ]);

    $pdo->commit();

    // Call the API
    $apiUrl = "https://jazainc.com/api/v2/llexam/doexam.php";
    $apiData = [
        "apikey" => "72ba3bd0183631753f45720bf6a2a5a5",
        "applno" => $applno,
        "dob" => $dob,
        "pass" => $password,
        "pin" => $exam_pin ?: "",
        "type" => $exam_type,
        "callback" => "https://" . $_SERVER['HTTP_HOST'] . "/api/callbackexam.php"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Update with API response
    if ($apiResponse !== false && empty($curlError)) {
        $apiResponse = trim($apiResponse);
        $apiResponseData = json_decode($apiResponse, true);

        if ($apiResponseData && isset($apiResponseData['status'])) {
            if ($apiResponseData['status'] == '200') {
                // Success response
                $stmt = $pdo->prepare("
                    UPDATE llr_tokens 
                    SET status = 'processing', 
                        api_token = ?, 
                        applname = ?, 
                        remarks = ?, 
                        queue = ?,
                        rtocode = ?, 
                        rtoname = ?, 
                        statecode = ?, 
                        statename = ?, 
                        api_response = ?,
                        last_api_response = NOW()
                    WHERE token = ?
                ");
                $stmt->execute([
                    $apiResponseData['token'] ?? null,
                    $apiResponseData['applname'] ?? null,
                    $apiResponseData['remarks'] ?? 'Exam started successfully',
                    $apiResponseData['queue'] ?? null,
                    $apiResponseData['rtocode'] ?? null,
                    $apiResponseData['rtoname'] ?? null,
                    $apiResponseData['statecode'] ?? null,
                    $apiResponseData['statename'] ?? null,
                    $apiResponse,
                    $token
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'LLR Exam started successfully!',
                    'token' => $token,
                    'new_balance' => $newBalance,
                    'queue' => $apiResponseData['queue'] ?? null,
                    'remarks' => $apiResponseData['remarks'] ?? null,
                    'applname' => $apiResponseData['applname'] ?? null // Make sure this is included
                ]);
            } else {
                // API returned error - refund
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                $stmt->execute([$serviceFee, $user['id']]);

                $stmt = $pdo->prepare("
                    INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after) 
                    VALUES (?, 'refund', ?, 'LLR Exam Application Refund - API Error', ?, ?)
                ");
                $stmt->execute([$user['id'], $serviceFee, $token, $walletBalance]);

                $stmt = $pdo->prepare("
                    UPDATE llr_tokens 
                    SET status = 'refunded', 
                        remarks = ?, 
                        api_response = ?,
                        refund_reason = ?,
                        last_api_response = NOW()
                    WHERE token = ?
                ");
                $stmt->execute([
                    $apiResponseData['message'] ?? 'API Error',
                    $apiResponse,
                    $apiResponseData['message'] ?? 'API returned error status',
                    $token
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => false,
                    'message' => $apiResponseData['message'] ?? 'API returned an error. Amount refunded.',
                    'refunded' => true,
                    'new_balance' => $walletBalance
                ]);
            }
        } else {
            // Invalid API response but keep processing
            $stmt = $pdo->prepare("
                UPDATE llr_tokens 
                SET status = 'processing', 
                    remarks = 'Submitted to API - processing...',
                    api_response = ?,
                    last_api_response = NOW()
                WHERE token = ?
            ");
            $stmt->execute([$apiResponse, $token]);

            echo json_encode([
                'success' => true,
                'message' => 'Application submitted. Processing your exam...',
                'token' => $token,
                'new_balance' => $newBalance
            ]);
        }
    } else {
        // API call failed but keep processing
        $stmt = $pdo->prepare("
            UPDATE llr_tokens 
            SET status = 'processing', 
                remarks = 'Submitted - API processing...',
                error_count = error_count + 1,
                last_api_response = NOW()
            WHERE token = ?
        ");
        $stmt->execute([$token]);

        echo json_encode([
            'success' => true,
            'message' => 'Application submitted. Your exam is being processed...',
            'token' => $token,
            'new_balance' => $newBalance
        ]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    error_log("LLR Process Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
