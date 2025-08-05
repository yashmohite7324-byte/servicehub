<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user = $_SESSION['user'];

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['userData'])) {
        throw new Exception('Invalid request data');
    }
    
    $userData = trim($data['userData']);
    $lines = array_filter(explode("\n", $userData), function($line) {
        return trim($line) !== '';
    });
    
    // Validate required fields
    if (count($lines) < 3) {
        throw new Exception('Please provide Application Number, Date of Birth, and Password');
    }
    
    $applno = trim($lines[0]);
    $dob = trim($lines[1]);
    $password = trim($lines[2]);
    $examPin = isset($lines[3]) ? trim($lines[3]) : '';
    $examType = isset($lines[4]) && strtolower(trim($lines[4])) === 'night' ? 'night' : 'day';
    
    // Validate formats
    if (!preg_match('/^[A-Za-z0-9]{8,20}$/', $applno)) {
        throw new Exception('Invalid Application Number format');
    }
    
    if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
        throw new Exception('Date of Birth must be in DD-MM-YYYY format');
    }

    // Get user's current balance and LLR price
    $stmt = $pdo->prepare("SELECT wallet_balance, llr_price FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception('User not found');
    }
    
    $currentBalance = $userData['wallet_balance'];
    $llrPrice = $userData['llr_price'] ?? 100.00;
    
    // Check wallet balance
    if ($currentBalance < $llrPrice) {
        throw new Exception(sprintf(
            'Insufficient balance. Required: ₹%.2f, Available: ₹%.2f',
            $llrPrice,
            $currentBalance
        ));
    }

    // Generate unique token
    $token = 'LLR' . time() . bin2hex(random_bytes(4));

    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert into llr_tokens table
        $stmt = $pdo->prepare("INSERT INTO llr_tokens 
            (user_id, service_price, token, applno, dob, password, exam_pin, exam_type, 
             status, remarks, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, NOW())");
        
        $remarks = 'Application submitted, awaiting processing';
        $stmt->execute([
            $user['id'],
            $llrPrice,
            $token,
            $applno,
            $dob,
            $password,
            $examPin,
            $examType,
            $remarks
        ]);

        // Deduct amount from wallet
        $newBalance = $currentBalance - $llrPrice;
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $user['id']]);

        // Record transaction
        $stmt = $pdo->prepare("INSERT INTO payment_history 
            (user_id, transaction_type, amount, description, 
             reference_id, balance_after, created_at) 
            VALUES (?, 'debit', ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $user['id'],
            $llrPrice,
            'LLR Exam Application Fee',
            $token,
            $newBalance
        ]);

        // Call API to submit exam
        $apiResponse = callExamAPI($applno, $dob, $password, $examPin, $examType, $token);
        $apiData = json_decode($apiResponse, true);
        
        // Update with API response if available
        if ($apiData && isset($apiData['status'])) {
            $apiStatus = ($apiData['status'] == '500') ? 'processing' : 'submitted';
            $apiRemarks = $apiData['message'] ?? $remarks;
            
            $stmt = $pdo->prepare("UPDATE llr_tokens SET 
                api_token = ?,
                api_response = ?,
                status = ?,
                remarks = ?,
                updated_at = NOW()
                WHERE token = ?");
            
            $stmt->execute([
                $apiData['token'] ?? $token,
                $apiResponse,
                $apiStatus,
                $apiRemarks,
                $token
            ]);
        }

        // Update session with new balance
        $_SESSION['user']['wallet_balance'] = $newBalance;
        
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'LLR exam submitted successfully',
            'token' => $token,
            'new_balance' => $newBalance,
            'application' => [
                'token' => $token,
                'applno' => $applno,
                'dob' => $dob,
                'status' => 'submitted',
                'created_at' => date('Y-m-d H:i:s'),
                'remarks' => $remarks,
                'exam_type' => $examType
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'PROCESSING_ERROR'
    ]);
}

function callExamAPI($applno, $dob, $password, $examPin, $examType, $callbackToken) {
    $apiUrl = "https://jazainc.com/api/v2/llexam/doexam.php";
    
    $postData = [
        "apikey" => "eb4f7976f79c67a19f95a259d9254446", // Replace with your actual API key
        "applno" => $applno,
        "dob" => $dob,
        "pass" => $password,
        "pin" => $examPin,
        "type" => $examType,
        "callback" => "https://yourdomain.com/callbackexam.php?token=" . $callbackToken
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        // Log error but don't fail the process
        error_log('API Connection Error: ' . curl_error($ch));
        return null;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("API returned HTTP $httpCode");
        return null;
    }
    
    return $response;
}