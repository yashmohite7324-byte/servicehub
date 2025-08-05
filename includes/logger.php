<?php
// includes/logger.php

function logTransaction(array $data) {
    $logFile = __DIR__ . '/../logs/llr_transactions.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    // Format the log entry
    $logEntry = sprintf(
        "[%s] %s: %s\n",
        date('Y-m-d H:i:s'),
        $data['processing_stage'] ?? 'unknown',
        json_encode($data)
    );
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function logError(string $message, array $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    // Format the error entry
    $logEntry = sprintf(
        "[%s] ERROR: %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        json_encode($context)
    );
    
    // Write to error log
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}