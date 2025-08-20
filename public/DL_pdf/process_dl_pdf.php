<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$dlno = $_POST['dlno'] ?? '';
$dob = $_POST['dob'] ?? '';
$pdf_type = $_POST['pdf_type'] ?? 'type1';
$blood_group = $_POST['blood_group'] ?? 'O+';
$address_type = $_POST['address_type'] ?? 'perm';

// Validate required fields
if (empty($dlno) || empty($dob)) {
    echo json_encode(['success' => false, 'message' => 'DL number and Date of Birth are required']);
    exit;
}

// Validate DOB format
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date of birth format. Use DD-MM-YYYY']);
    exit;
}

$userId = $_SESSION['user']['id'];
$dlPrice = $_SESSION['user']['dl_price'] ?? 150;

try {
    $pdo->beginTransaction();
    
    // Check wallet balance
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['wallet_balance'] < $dlPrice) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Insufficient wallet balance']);
        exit;
    }
    
    // Deduct amount from wallet
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$dlPrice, $userId]);
    
    // Add to payment history
    $stmt = $pdo->prepare("INSERT INTO payment_history 
                          (user_id, transaction_type, amount, description, reference_id, balance_after) 
                          VALUES (?, 'debit', ?, 'DL PDF Generation Service', ?, ?)");
    $referenceId = 'DLPDF-' . time() . '-' . $userId;
    $stmt->execute([
        $userId, 
        $dlPrice, 
        $referenceId,
        $user['wallet_balance'] - $dlPrice
    ]);
    
    // Call Jaza API to generate PDF
    $apiData = [
        "apikey" => "72ba3bd0183631753f45720bf6a2a5a5",
        "dlno" => strtoupper($dlno),
        "type" => $pdf_type,
        "blood" => $blood_group,
        "addrtype" => $address_type
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://jazainc.com/api/v2/dlpdfapi.php");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }
    
    // Check if response is valid JSON
    $apiResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON response: " . $response);
        throw new Exception("Invalid response from PDF service");
    }
    
    if ($apiResponse && isset($apiResponse['status']) && $apiResponse['status'] === '200') {
        // Verify that we have a PDF
        if (!isset($apiResponse['pdf']) || empty($apiResponse['pdf'])) {
            throw new Exception("No PDF data received from API");
        }
        
        // Extract and decode the PDF
        $pdfBase64 = $apiResponse['pdf'];
        if (strpos($pdfBase64, 'base64,') !== false) {
            $pdfBase64 = substr($pdfBase64, strpos($pdfBase64, 'base64,') + 7);
        }
        
        $pdfData = base64_decode($pdfBase64);
        
        // Verify this is actually a PDF
        if (substr($pdfData, 0, 4) !== '%PDF') {
            error_log("Received data is not a PDF: " . substr($pdfData, 0, 100));
            throw new Exception("Invalid PDF data received");
        }
        
        // Store the PDF in database for record keeping
        $stmt = $pdo->prepare("INSERT INTO dl_pdfs 
                              (user_id, service_id, service_price, dlno, pdf_type, blood_group, address_type, 
                               status, name, dob, pdf_data, api_response) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            1, // Assuming service_id 1 is for DL PDF
            $dlPrice,
            strtoupper($dlno),
            $pdf_type,
            $blood_group,
            $address_type,
            'completed',
            $apiResponse['name'] ?? null,
            $apiResponse['dob'] ?? null,
            $pdfData,
            json_encode($apiResponse)
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Return the PDF data directly as base64 for immediate download
        echo json_encode([
            'success' => true,
            'message' => 'DL PDF generated successfully',
            'pdf' => 'data:application/pdf;base64,' . base64_encode($pdfData),
            'filename' => 'DL_' . strtoupper($dlno) . '_' . ($pdf_type === 'type1' ? 'BlueFormat' : 'LatestFormat') . '.pdf'
        ]);
    } else {
        // Failed - rollback and refund
        $pdo->rollBack();
        
        // Refund the amount
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$dlPrice, $userId]);
        
        // Add to payment history
        $stmt = $pdo->prepare("INSERT INTO payment_history 
                              (user_id, transaction_type, amount, description, reference_id, balance_after) 
                              VALUES (?, 'refund', ?, 'Refund for failed DL PDF generation', ?, ?)");
        $stmt->execute([
            $userId, 
            $dlPrice, 
            'REFUND-' . $referenceId,
            $user['wallet_balance'] // Original balance
        ]);
        
        $errorMsg = $apiResponse['message'] ?? 'DL PDF generation failed. Please check your DL number and date of birth.';
        echo json_encode(['success' => false, 'message' => $errorMsg]);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in process_dl_pdf: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in process_dl_pdf: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}