<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userId = $_SESSION['user']['id'];

try {
    // Get DL PDF history for the user
    $stmt = $pdo->prepare("SELECT id, dlno, pdf_type, status, created_at FROM dl_pdfs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_dl_history: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}