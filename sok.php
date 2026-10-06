<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$phoneNumber = isset($_GET['no']) ? $_GET['no'] : '';

if (empty($phoneNumber)) {
    echo json_encode([
        'author' => '@selfphp',
        'firma' => 'ŞOK Market',
        'sms_durumu' => false,
        'is_success' => false,
        'error_code' => 'MISSING_PARAMETER',
        'message' => 'Telefon numarası eksik. Kullanım: sok.php?no=5555555555',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$apiUrl = 'https://giris.ec.sokmarket.com.tr/api/authentication/otp-registration/generate';

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$deviceId = generateUUID() . '-' . generateUUID();
$sessionId = generateUUID() . '-' . generateUUID();

$postData = json_encode([
    'clientId' => 'buyer-web',
    'phoneNumber' => $phoneNumber,
    'captchaToken' => '',
    'captchaAction' => 'generate_register_otp',
    'reCaptchaV2' => false
]);

$cookies = [
    "X-Ecommerce-Deviceid={$deviceId}",
    "X-Ecommerce-Sid={$sessionId}",
    "X-Platform=WEB",
    "X-Service-Type=MARKET"
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: tr-TR',
        'Origin: https://giris.ec.sokmarket.com.tr',
        'Referer: https://giris.ec.sokmarket.com.tr/otp-register',
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1',
        'x-app-version: v1',
        'x-ecommerce-deviceid: ' . $deviceId,
        'x-ecommerce-sid: ' . $sessionId,
        'x-platform: WEB',
        'x-ui-version: 4ea4b51',
        'sec-ch-ua: "Chromium";v="154", "Google Chrome";v="154", "Not A(Brand";v="99"',
        'sec-ch-ua-mobile: ?1',
        'sec-ch-ua-platform: "iOS"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: same-origin'
    ],
    CURLOPT_COOKIE => implode('; ', $cookies),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_ENCODING => 'gzip, deflate, br',
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($response === false) {
    echo json_encode([
        'author' => '@selfphp',
        'firma' => 'ŞOK Market',
        'sms_durumu' => false,
        'is_success' => false,
        'error_code' => 'CURL_ERROR',
        'message' => 'API bağlantı hatası: ' . $curlError,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($httpCode == 200) {
    $apiResponse = json_decode($response, true);

    $isSuccess = isset($apiResponse['verificationId']) && !empty($apiResponse['verificationId']);

    $customResponse = [
        'author' => '@selfphp',
        'firma' => 'ŞOK Market',
        'sms_durumu' => $isSuccess,
        'telefon' => $phoneNumber,
        'api_response' => $apiResponse
    ];

    if ($isSuccess && isset($apiResponse['verificationId'])) {
        $customResponse['verification_id'] = $apiResponse['verificationId'];
        $customResponse['remaining_count'] = isset($apiResponse['remainingCount']) ? $apiResponse['remainingCount'] : null;
        $customResponse['expires_in'] = isset($apiResponse['expiresIn']) ? $apiResponse['expiresIn'] : null;
    }
    
    echo json_encode($customResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $apiResponse = json_decode($response, true);
    
    echo json_encode([
        'author' => '@selfphp',
        'firma' => 'ŞOK Market',
        'sms_durumu' => false,
        'is_success' => false,
        'error_code' => 'HTTP_ERROR',
        'message' => 'HTTP Hata Kodu: ' . $httpCode,
        'telefon' => $phoneNumber,
        'response' => $apiResponse
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
