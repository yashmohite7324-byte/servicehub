<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_GET['id'])) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$pdfId = $_GET['id'];
$userId = $_SESSION['user']['id'] ?? 0;

$stmt = $pdo->prepare("SELECT pdf_data, dlno, created_at FROM dl_pdfs WHERE id = ? AND user_id = ?");
$stmt->execute([$pdfId, $userId]);
$pdf = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pdf) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="DL_' . $pdf['dlno'] . '_' . date('Ymd', strtotime($pdf['created_at'])) . '.pdf"');
echo $pdf['pdf_data'];
exit;