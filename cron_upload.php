<?php
require_once __DIR__ . '/config.php';

// run every 5 min

$currentTime = date('H:i');
$tolerance = CRON_TIME_TOLERANCE;

echo "[" . date('Y-m-d H:i:s') . "] Cron job started\n";

try {
    // Get all schedules
    $stmt = $pdo->query("SELECT s.*, u.access_token, u.token_expires_at, u.refresh_token, u.id as user_id FROM schedules s JOIN users u ON s.user_id = u.id WHERE s.status = 'active' AND s.videos_count > 0");
    $schedules = $stmt->fetchAll();

    echo "Found " . count($schedules) . " active schedules\n";

    foreach ($schedules as $schedule) {
        $scheduleTimes = json_decode($schedule['schedule_times'], true);
        $shouldUpload = false;

// check
        foreach ($scheduleTimes as $scheduleTime) {
            $scheduleTimestamp = strtotime(date('Y-m-d') . ' ' . $scheduleTime);
            $currentTimestamp = strtotime(date('Y-m-d H:i'));
            $diff = abs($currentTimestamp - $scheduleTimestamp) / 60;

            if ($diff <= $tolerance) {
                $shouldUpload = true;
                break;
            }
        }

        if (!$shouldUpload) {
            continue;
        }

        echo "Processing schedule #{$schedule['id']} for {$schedule['username']}\n";

        // check token
        if (strtotime($schedule['token_expires_at']) < time()) {
            echo "Refreshing access token...\n";
            try {
                $accessToken = refreshAccessToken($pdo, $schedule['user_id'], $schedule['refresh_token']);
            } catch (Exception $e) {
                echo "Token refresh failed: " . $e->getMessage() . "\n";
                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'token_refresh_failed', null, null, 'failed', $e->getMessage());
                continue;
            }
        } else {
            $accessToken = $schedule['access_token'];
        }

        // Get random video
        $videosDir = __DIR__ . "/videos/{$schedule['username']}_videos";
        $videos = glob($videosDir . '/*.mp4');
        
        if (empty($videos)) {
            echo "No videos available\n";
            logActivity($pdo, $schedule['user_id'], $schedule['username'], 'no_videos_available', null, null, 'failed', 'Videos directory is empty');
            continue;
        }
        
        $randomVideo = $videos[array_rand($videos)];
        $videoName = basename($randomVideo);

        echo "Selected video: $videoName\n";

        // Get random caption
        $captionsFile = __DIR__ . "/captions/{$schedule['username']}_title.json";
        if (!file_exists($captionsFile)) {
            echo "Captions file not found\n";
            logActivity($pdo, $schedule['user_id'], $schedule['username'], 'captions_not_found', $videoName, null, 'failed', 'Captions file does not exist');
            continue;
        }

        $captions = json_decode(file_get_contents($captionsFile), true);
        if (empty($captions)) {
            echo "No captions available\n";
            logActivity($pdo, $schedule['user_id'], $schedule['username'], 'no_captions_available', $videoName, null, 'failed', 'Captions array is empty');
            continue;
        }

        $randomCaption = $captions[array_rand($captions)];
        echo "Selected caption: $randomCaption\n";

        // Upload to TikTok
        try {
            $result = uploadToTikTok($accessToken, $randomVideo, $randomCaption);
            
            if ($result['success']) {
                echo "Upload successful!\n";
                
                // Delete uploaded video
                unlink($randomVideo);
                echo "Video deleted from server\n";
                
                // Update schedule
                $stmt = $pdo->prepare("UPDATE schedules SET last_uploaded_at = NOW(), videos_count = videos_count - 1 WHERE id = ?");
                $stmt->execute([$schedule['id']]);
                
                updateNextScheduledTime($pdo, $schedule['id'], $scheduleTimes);
                
                // log
                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'video_uploaded', $videoName, $randomCaption, 'success', 'Automatic upload via cron');
                
                echo "Schedule updated\n";
            } else {
                echo "Upload failed: " . $result['message'] . "\n";
                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'upload_failed', $videoName, $randomCaption, 'failed', $result['message']);
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            logActivity($pdo, $schedule['user_id'], $schedule['username'], 'upload_error', $videoName, $randomCaption, 'failed', $e->getMessage());
        }

        echo "---\n";
    }

    // Update stats
    updateStats($pdo);

    echo "[" . date('Y-m-d H:i:s') . "] Cron job completed\n";

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    error_log("Cron error: " . $e->getMessage());
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

    // Update tokens in database
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
        return ['success' => false, 'message' => "Init failed with HTTP code $httpCode"];
    }

    $initResult = json_decode($response, true);
    
    if (!isset($initResult['data']['upload_url'])) {
        return ['success' => false, 'message' => 'No upload URL received'];
    }

    $uploadUrl = $initResult['data']['upload_url'];
    $publishId = $initResult['data']['publish_id'];


    $ch = curl_init($uploadUrl);
    
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
        return ['success' => false, 'message' => "Upload failed with HTTP code $uploadCode"];
    }

    return ['success' => true, 'publish_id' => $publishId];
}

function updateNextScheduledTime($pdo, $scheduleId, $times) {
    $currentTime = date('H:i');
    $nextScheduled = null;
    sort($times);
    
    foreach ($times as $time) {
        if ($time > $currentTime) {
            $nextScheduled = date('Y-m-d') . ' ' . $time . ':00';
            break;
        }
    }
    
    if (!$nextScheduled) {
        $nextScheduled = date('Y-m-d', strtotime('+1 day')) . ' ' . $times[0] . ':00';
    }

    $stmt = $pdo->prepare("UPDATE schedules SET next_scheduled_at = ? WHERE id = ?");
    $stmt->execute([$nextScheduled, $scheduleId]);
}
?>
