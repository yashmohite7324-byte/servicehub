<?php
// includes/functions.php

/**
 * Sanitize user input
 * @param string $data The input to sanitize
 * @return string Sanitized output
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate DL number format
 * @param string $dlno Driving License number
 * @return bool True if valid, false otherwise
 */
function validateDLNumber($dlno) {
    // Basic validation - adjust according to your country's DL format
    return preg_match('/^[A-Z0-9]{8,20}$/', $dlno);
}

/**
 * Validate date in DD-MM-YYYY format
 * @param string $date Date string
 * @return bool True if valid, false otherwise
 */
function validateDob($date) {
    if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
        return false;
    }
    
    list($day, $month, $year) = explode('-', $date);
    return checkdate($month, $day, $year);
}

/**
 * Generate a unique transaction ID
 * @param string $prefix Prefix for the ID
 * @return string Generated transaction ID
 */
function generateTransactionId($prefix = 'TXN') {
    return $prefix . time() . substr(md5(mt_rand()), 0, 5);
}

/**
 * Log errors to a file
 * @param string $message Error message
 * @param string $file File where error occurred
 * @param int $line Line number
 */
function logError($message, $file = '', $line = 0) {
    $logMessage = date('[Y-m-d H:i:s]') . " - ";
    $logMessage .= "File: " . basename($file) . " - ";
    $logMessage .= "Line: $line - ";
    $logMessage .= "Error: $message" . PHP_EOL;
    
    error_log($logMessage, 3, __DIR__ . '/../logs/error.log');
}

/**
 * Verify user has sufficient wallet balance
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param float $amount Amount to check
 * @return bool True if sufficient balance, false otherwise
 */
function hasSufficientBalance($pdo, $userId, $amount) {
    try {
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['wallet_balance'] >= $amount;
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

/**
 * Format currency for display
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Generate a random token for requests
 * @param string $prefix Token prefix
 * @return string Generated token
 */
function generateToken($prefix = 'DL') {
    return $prefix . substr(md5(uniqid(mt_rand(), true)), 0, 10);
}

/**
 * Validate API response
 * @param mixed $response API response
 * @return bool True if valid, false otherwise
 */
function validateApiResponse($response) {
    if (!is_array($response)) {
        return false;
    }
    
    return isset($response['status']) && $response['status'] === '200';
}

/**
 * Get user's DL price
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return float DL price
 */
function getUserDlPrice($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT dl_price FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user['dl_price'] ?? 150.00; // Default price if not set
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return 150.00; // Fallback default price
    }
}

/**
 * Call Jaza API to generate DL PDF
 * @param array $data Request data
 * @return array API response
 */
function callJazaApi($data) {
    $url = "https://jazainc.com/api/v2/dlpdfapi.php";
    
    $postData = [
        "apikey" => "72ba3bd0183631753f45720bf6a2a5a5",
        "dlno" => $data['dlno'],
        "type" => $data['pdf_type'],
        "blood" => $data['blood_group'],
        "addrtype" => $data['address_type']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Create logs directory if it doesn't exist
 */
function ensureLogsDirectoryExists() {
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
}

// Initialize logs directory when this file is included
ensureLogsDirectoryExists();