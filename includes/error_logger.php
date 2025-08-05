<?php
function logError($message, $context = []) {
    $logEntry = sprintf(
        "[%s] ERROR: %s %s%s",
        date('Y-m-d H:i:s'),
        $message,
        json_encode($context),
        PHP_EOL
    );
    
    file_put_contents(__DIR__ . '/../logs/application.log', $logEntry, FILE_APPEND);
}

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
?>