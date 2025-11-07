<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $scheduleId = intval($data['schedule_id']);
    
    // Get schedule
    $stmt = $pdo->prepare("SELECT s.*, u.access_token, u.token_expires_at, u.refresh_token FROM schedules s JOIN users u ON s.user_id = u.id WHERE s.id = ? AND s.user_id = ?");
    $stmt->execute([$scheduleId, $_SESSION['user_id']]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    // chk token
    if (strtotime($schedule['token_expires_at']) < time()) {
        $accessToken = refreshAccessToken($pdo, $_SESSION['user_id'], $schedule['refresh_token']);
    } else {
        $accessToken = $schedule['access_token'];
    }

    // random video
    $videosDir = __DIR__ . "/../videos/{$schedule['username']}_videos";
    $videos = glob($videosDir . '/*.mp4');
    
    if (empty($videos)) {
        throw new Exception('No videos available');
    }
    
    $randomVideo = $videos[array_rand($videos)];
    $videoName = basename($randomVideo);

    // Get random caption
    $captionsFile = __DIR__ . "/../captions/{$schedule['username']}_title.json";
    $captions = json_decode(file_get_contents($captionsFile), true);
    $randomCaption = $captions[array_rand($captions)];

    // Upload TikTok
    $result = uploadToTikTok($accessToken, $randomVideo, $randomCaption);
    
    if ($result['success']) {
        // Delete uploaded video
        unlink($randomVideo);
        
        // Update schedule
        $stmt = $pdo->prepare("UPDATE schedules SET last_uploaded_at = NOW(), videos_count = videos_count - 1 WHERE id = ?");
        $stmt->execute([$scheduleId]);
        
        // log
        logActivity($pdo, $_SESSION['user_id'], $schedule['username'], 'video_uploaded', $videoName, $randomCaption, 'success', 'Manual upload');
        
        // Update stats
        updateStats($pdo);
        
        echo json_encode([
            'success' => true,
            'message' => 'Video uploaded successfully'
        ]);
    } else {
        throw new Exception($result['message']);
    }

} catch (Exception $e) {
    logActivity($pdo, $_SESSION['user_id'] ?? 0, 'unknown', 'upload_failed', null, null, 'failed', $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function refreshAccessToken($pdo, $userId, $refreshToken) {
    $tokenUrl = "https://open.tiktokapis.com/v2/oauth/token/";
    $postData = [
        'client_key' => TIKTOK_CLIENT_KEY,
        'client_secret' => TIKTOK_CLIENT_SECRET,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to refresh access token');
    }

    // Update token
    $tokenExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
    $refreshExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['refresh_expires_in']);
    
    $stmt = $pdo->prepare("UPDATE users SET access_token = ?, refresh_token = ?, token_expires_at = ?, refresh_expires_at = ? WHERE id = ?");
    $stmt->execute([$tokenData['access_token'], $tokenData['refresh_token'], $tokenExpiresAt, $refreshExpiresAt, $userId]);

    return $tokenData['access_token'];
}

function uploadToTikTok($accessToken, $videoPath, $caption) {
    
    $initUrl = "https://open.tiktokapis.com/v2/post/publish/video/init/";
    
    $postInfo = [
        'title' => $caption,
        'privacy_level' => 'SELF_ONLY',
        'disable_comment' => false,
        'disable_duet' => false,
        'disable_stitch' => false,
        'video_cover_timestamp_ms' => 1000
    ];

    $sourceInfo = [
        'source' => 'FILE_UPLOAD',
        'video_size' => filesize($videoPath),
        'chunk_size' => filesize($videoPath),
        'total_chunk_count' => 1
    ];

    $initData = [
        'post_info' => $postInfo,
        'source_info' => $sourceInfo
    ];

    $ch = curl_init($initUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($initData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json; charset=UTF-8'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to initialize upload'];
    }

    $initResult = json_decode($response, true);
    
    if (!isset($initResult['data']['upload_url'])) {
        return ['success' => false, 'message' => 'No upload URL received'];
    }

    $uploadUrl = $initResult['data']['upload_url'];
    $publishId = $initResult['data']['publish_id'];

    
    $ch = curl_init($uploadUrl);
    $videoData = file_get_contents($videoPath);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, fopen($videoPath, 'r'));
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($videoPath));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: video/mp4',
        'Content-Length: ' . filesize($videoPath)
    ]);

    $uploadResponse = curl_exec($ch);
    $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($uploadCode !== 200) {
        return ['success' => false, 'message' => 'Failed to upload video file'];
    }

    return ['success' => true, 'publish_id' => $publishId];
}
?>
