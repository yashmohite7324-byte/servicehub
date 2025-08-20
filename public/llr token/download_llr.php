<!-- download_llr.php -->

<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get token from request
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}

try {
    // Get token data from database
    $stmt = $pdo->prepare("
        SELECT token, applno, applname, filename, pdf_data, status 
        FROM llr_tokens 
        WHERE token = ? AND user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$token, $user['id']]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Certificate not found or not yet completed']);
        exit;
    }
    
    // If PDF data exists in database, serve it directly
    if (!empty($tokenData['pdf_data'])) {
        $filename = $tokenData['filename'] ?: "LLR_Certificate_{$tokenData['applno']}.pdf";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($tokenData['pdf_data']));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $tokenData['pdf_data'];
        exit;
    }
    
    // If no PDF data in database, try to fetch from API
    if (!empty($tokenData['filename'])) {
        $apiUrl = "https://jazainc.com/api/v2/llexam/download.php";
        $apiData = [
            "filename" => $tokenData['filename'],
            "token" => $token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($apiData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $pdfData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($pdfData !== false && $httpCode == 200) {
            // Check if response is actually a PDF
            if (strpos($contentType, 'application/pdf') !== false || 
                substr($pdfData, 0, 4) === '%PDF') {
                
                // Save PDF to database for future use
                $stmt = $pdo->prepare("UPDATE llr_tokens SET pdf_data = ? WHERE token = ?");
                $stmt->execute([$pdfData, $token]);
                
                $filename = $tokenData['filename'] ?: "LLR_Certificate_{$tokenData['applno']}.pdf";
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($pdfData));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                echo $pdfData;
                exit;
            }
        }
        
        // If API call failed, try alternative download URL
        $alternativeUrl = "https://jazainc.com/certificates/" . $tokenData['filename'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $alternativeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $pdfData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($pdfData !== false && $httpCode == 200) {
            // Check if response is actually a PDF
            if (strpos($contentType, 'application/pdf') !== false || 
                substr($pdfData, 0, 4) === '%PDF') {
                
                // Save PDF to database for future use
                $stmt = $pdo->prepare("UPDATE llr_tokens SET pdf_data = ? WHERE token = ?");
                $stmt->execute([$pdfData, $token]);
                
                $filename = $tokenData['filename'] ?: "LLR_Certificate_{$tokenData['applno']}.pdf";
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($pdfData));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                echo $pdfData;
                exit;
            }
        }
    }
    
    // If all download attempts failed
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Certificate download is temporarily unavailable. Please try again later.',
        'error_code' => 'DOWNLOAD_FAILED'
    ]);

} catch (Exception $e) {
    error_log("LLR Download Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while downloading the certificate. Please try again.'
    ]);
}
?>