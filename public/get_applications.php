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
    // Get all applications for this user, newest first
    $stmt = $pdo->prepare("SELECT 
        token, applno, dob, status, queue, remarks, filename,
        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
        DATE_FORMAT(completed_at, '%Y-%m-%d %H:%i:%s') as completed_at
        FROM llr_tokens 
        WHERE user_id = ?
        ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'applications' => $applications
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load applications: ' . $e->getMessage()
    ]);
}