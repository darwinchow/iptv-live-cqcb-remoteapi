<?php

// Constant definition
$cacheDuration = 1800; // unit: seconds

$channelId = isset($_GET['channelId']) ? $_GET['channelId'] : 'cctv1HD';
$cacheChannelId = strtolower($channelId);

// Get cached data
$cacheFileName = 'cache.json';
if (file_exists($cacheFileName)) {
    $cachedUrls = json_decode(file_get_contents($cacheFileName), true);
    if (isset($cachedUrls[$cacheChannelId])) {
        $cachedData = $cachedUrls[$cacheChannelId];
        $cacheValidLeft = $cacheDuration - (time() - $cachedData['timestamp']);
        if (isset($cachedData['url']) && isset($cachedData['timestamp']) && $cacheValidLeft > 0) {
            $liveUrl = $cachedData['url'];
            header("Content-Type: application/vnd.apple.mpegurl");
            header('Location: ' . $liveUrl);
            // Set cache headers for CDN
            header('Cache-Control: s-maxage=' . $cacheValidLeft);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $cachedData['timestamp']) . ' GMT');
            exit;
        } else {
            unset($cachedUrls[$cacheChannelId]);
            file_put_contents($cacheFileName, json_encode($cachedUrls));
        }
    }
}

$requestBody = [
    'cityId' => '5A',
    'playId' => $channelId,
    'relativeId' => $channelId,
    'type' => 1,
];

$secretKey = 'aIErXY1rYjSpjQs7pq2Gp5P8k2W7P^Y@';
$timestamp = time() . "000";

$signatureBody = array_merge($requestBody, [
    'appId' => 'kdds-chongqingdemo',
    'timestamps' => $timestamp,
]);

ksort($signatureBody);
$sortedSignatureBodyStr = implode('', array_map(
    function ($key, $value) {
        return $key . $value;
    },
    array_keys($signatureBody),
    $signatureBody
));

$signature = md5($secretKey . $sortedSignatureBodyStr);

$headers = [
    'appId: kdds-chongqingdemo',
    'sign: ' . $signature,
    'timestamps: ' . $timestamp,
];

$url = "https://portal.centre.live.cbncdn.cn/others/common/playUrlNoAuth?" . http_build_query($requestBody);


$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    die("cURL Error: " . $error);
}

curl_close($ch);

$responseData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON Decode Error: " . json_last_error_msg());
}

if (isset($responseData['data']['result']['protocol'][0]['transcode'][0]['url'])) {
    $liveUrl = $responseData['data']['result']['protocol'][0]['transcode'][0]['url'];
} else {
    die("Invalid response structure.");
}

// If liveUrl is a 30X redirect URL, follow it until the final streaming URL is obtained (stop if HTTP status is not 30X)
$liveUrl = getFinalRedirectUrl($liveUrl);

$cachedUrls[$cacheChannelId] = [
    'url' => $liveUrl,
    'timestamp' => time(), // Cache timestamp
];
file_put_contents($cacheFileName, json_encode($cachedUrls));

header("Content-Type: application/vnd.apple.mpegurl");
header('Location: ' . $liveUrl);
header('Cache-Control: s-maxage=' . $cacheDuration);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
exit;

function getFinalRedirectUrl($url, $maxRedirects = 5) {
    $redirectCount = 0;
    $finalUrl = $url;

    while ($redirectCount < $maxRedirects) {
        $headers = get_headers($finalUrl, 1);
        if (isset($headers['Location'])) {
            $finalUrl = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
            $redirectCount++;
        } else {
            break;
        }
    }

    return $finalUrl;
}

?>