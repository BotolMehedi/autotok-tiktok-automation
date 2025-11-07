<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');
set_time_limit(600);
ini_set('memory_limit', '1024M');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $scheduleId = intval($data['schedule_id'] ?? 0);

    if (!$scheduleId) throw new Exception('Invalid schedule ID');

    // Load schedule + token
    $stmt = $pdo->prepare("
        SELECT s.*, 
               COALESCE(p.access_token, u.access_token) AS access_token,
               COALESCE(p.token_expires_at, u.token_expires_at) AS token_expires_at,
               COALESCE(p.refresh_token, u.refresh_token) AS refresh_token,
               u.id AS user_id, u.username
        FROM schedules s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN profile_tokens p ON p.schedule_id = s.id
        WHERE s.id = ? AND s.user_id = ?
    ");
    $stmt->execute([$scheduleId, $_SESSION['user_id']]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) throw new Exception('Schedule not found');
    if (empty($schedule['access_token'])) throw new Exception('Profile not authorized.');

    // Refresh if expired
    if (strtotime($schedule['token_expires_at']) < time()) {
        $accessToken = refreshAccessToken($pdo, $schedule['user_id'], $scheduleId, $schedule['refresh_token']);
    } else {
        $accessToken = $schedule['access_token'];
    }

    // Get random video
    $videosDir = __DIR__ . "/../videos/{$schedule['username']}_videos";
    if (!is_dir($videosDir)) throw new Exception('Videos directory not found');

    $videos = glob($videosDir . '/*.mp4');
    if (empty($videos)) throw new Exception('No videos available');

    $randomVideo = $videos[array_rand($videos)];
    $videoName = basename($randomVideo);

    // Remote URL for TikTok pull
    
    $videoUrl = APP_URL . "/videos/" . $schedule['username'] . "_videos/" . $videoName;


    // Get random caption
    $captionsFile = __DIR__ . "/../captions/{$schedule['username']}_title.json";
    if (!file_exists($captionsFile)) throw new Exception('Captions file not found');

    $captions = json_decode(file_get_contents($captionsFile), true);
    if (empty($captions) || !is_array($captions)) throw new Exception('No captions available');

    $randomCaption = $captions[array_rand($captions)];

    // Upload to TikTok (PULL_FROM_URL)
    $result = uploadVideoToTikTok($accessToken, $videoUrl, $randomCaption);

    if ($result['success']) {
        @unlink($randomVideo);
        $stmt = $pdo->prepare("UPDATE schedules SET last_uploaded_at = NOW(), videos_count = videos_count - 1 WHERE id = ?");
        $stmt->execute([$scheduleId]);

        logActivity($pdo, $_SESSION['user_id'], $schedule['username'], 'video_uploaded', $videoName, $randomCaption, 'success', 'Manual upload');
        updateStats($pdo);

        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully to TikTok!',
            'video' => $videoName,
            'publish_id' => $result['publish_id'] ?? null
        ]);
    } else {
        throw new Exception($result['message']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/* =====================
   Helper Functions
===================== */

function refreshAccessToken($pdo, $userId, $scheduleId, $refreshToken) {
    $url = "https://open.tiktokapis.com/v2/oauth/token/";
    $postData = [
        'client_key' => TIKTOK_CLIENT_KEY,
        'client_secret' => TIKTOK_CLIENT_SECRET,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) throw new Exception("Token refresh failed (HTTP $code)");

    $data = json_decode($res, true);
    if (empty($data['access_token'])) throw new Exception('Invalid token response');

    $exp = date('Y-m-d H:i:s', time() + $data['expires_in']);
    $refExp = date('Y-m-d H:i:s', time() + $data['refresh_expires_in']);

    $stmt = $pdo->prepare("UPDATE users SET access_token=?, refresh_token=?, token_expires_at=?, refresh_expires_at=? WHERE id=?");
    $stmt->execute([$data['access_token'], $data['refresh_token'], $exp, $refExp, $userId]);

    return $data['access_token'];
}

function uploadVideoToTikTok($accessToken, $videoUrl, $caption) {
    $initUrl = "https://open.tiktokapis.com/v2/post/publish/video/init/";
    $payload = [
        'post_info' => [
            'title' => substr($caption, 0, 150),
            'privacy_level' => 'SELF_ONLY', //PRODUCTION : PUBLIC_TO_EVERYONE, SANDBOX : SELF_ONLY
            'disable_comment' => false,
            'disable_duet' => false,
            'disable_stitch' => false,
            'video_cover_timestamp_ms' => 1000
        ],
        'source_info' => [
            'source' => 'PULL_FROM_URL',
            'video_url' => $videoUrl
        ]
    ];

    $ch = curl_init($initUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ]
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return ['success' => false, 'message' => "Init connection error: $err"];
    $data = json_decode($res, true);

    if ($code !== 200 || empty($data['data']['publish_id'])) {
        $msg = $data['error']['message'] ?? 'Unknown TikTok error';
        return ['success' => false, 'message' => "Init failed: $msg (HTTP $code)", 'debug' => $data];
    }

    return [
        'success' => true,
        'publish_id' => $data['data']['publish_id'],
        'message' => 'Video successfully sent to TikTok (PULL_FROM_URL)'
    ];
}
?> 