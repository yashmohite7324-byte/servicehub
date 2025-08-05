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
    
    if ($application['status'] !== 'completed' || empty($application['filename'])) {
        throw new Exception('PDF not available for download');
    }
    
    // In a real implementation, you would fetch the PDF from the API or your storage
    // For now, we'll just return the filename
    echo json_encode([
        'success' => true,
        'filename' => $application['filename'],
        'message' => 'PDF ready for download'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}