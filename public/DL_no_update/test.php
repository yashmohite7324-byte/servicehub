<?php
// Test script for NineDigit API with better error handling
$apiKey = "YfP6Riq9jqfZ4nnlenvFPU3PPmLspd";
$senderNumber = "919604610640";
$adminNumber = "917517458787";

$postData = [
    'api_key' => $apiKey,
    'sender' => $senderNumber,
    'number' => $adminNumber,
    'message' => 'Test message from NineDigit API',
    'footer' => 'Test Footer'
];

echo "<h3>Testing NineDigit API</h3>";
echo "API Key: " . substr($apiKey, 0, 5) . "..." . substr($apiKey, -5) . "<br>";
echo "Sender: $senderNumber<br>";
echo "Receiver: $adminNumber<br>";
echo "Message: Test message from NineDigit API<br>";
echo "Footer: Test Footer<br><br>";

$ch = curl_init('https://niyowp.ninedigit.xyz/send-message');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For testing only

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
if ($error) {
    echo "cURL Error: $error<br>";
}
echo "Response: $response<br>";
echo "Request: " . json_encode($postData) . "<br>";

// Test alternative approach (GET request)
echo "<h3>Testing GET Request</h3>";
$getUrl = "https://niyowp.ninedigit.xyz/send-message?" . http_build_query($postData);
echo "GET URL: " . htmlspecialchars($getUrl) . "<br>";

$ch2 = curl_init($getUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "GET Response Code: $httpCode2<br>";
echo "GET Response: $response2<br>";
?>