<?php
require_once __DIR__ . '/config.php';

$currentTime = date('H:i');
$tolerance = CRON_TIME_TOLERANCE;

echo "[" . date('Y-m-d H:i:s') . "] Cron job started\n";

try {
    $stmt = $pdo->query("
        SELECT s.*, 
               COALESCE(p.access_token, u.access_token) AS access_token,
               COALESCE(p.token_expires_at, u.token_expires_at) AS token_expires_at,
               COALESCE(p.refresh_token, u.refresh_token) AS refresh_token,
               u.id AS user_id,
               u.username
        FROM schedules s
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN profile_tokens p ON p.schedule_id = s.id
        WHERE s.status = 'active' AND s.videos_count > 0
    ");
    $schedules = $stmt->fetchAll();

    echo "Found " . count($schedules) . " active schedules\n";

    foreach ($schedules as $schedule) {
        if (empty($schedule['access_token'])) {
            echo "❌ Profile #{$schedule['id']} ({$schedule['username']}) not authorized, skipping...\n";
            continue;
        }

        $scheduleTimes = json_decode($schedule['schedule_times'], true);
        $shouldUpload = false;

        foreach ($scheduleTimes as $scheduleTime) {
            $scheduleTimestamp = strtotime(date('Y-m-d') . ' ' . $scheduleTime);
            $currentTimestamp = strtotime(date('Y-m-d H:i'));
            $diff = abs($currentTimestamp - $scheduleTimestamp) / 60;

            if ($diff <= $tolerance) {
                $shouldUpload = true;
                break;
            }
        }

        if (!$shouldUpload) continue;

        echo "\n⏰ Processing schedule #{$schedule['id']} for {$schedule['username']}\n";

        // Refresh token if expired
        if (strtotime($schedule['token_expires_at']) < time()) {
            echo "🔄 Refreshing access token...\n";
            try {
                $accessToken = refreshAccessToken($pdo, $schedule['user_id'], $schedule['id'], $schedule['refresh_token']);
            } catch (Exception $e) {
                echo "⚠️ Token refresh failed: " . $e->getMessage() . "\n";
                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'token_refresh_failed', null, null, 'failed', $e->getMessage());
                continue;
            }
        } else {
            $accessToken = $schedule['access_token'];
        }

        // Video selection
        $videosDir = __DIR__ . "/videos/{$schedule['username']}_videos";
        if (!is_dir($videosDir)) {
            echo "❌ Videos directory not found: $videosDir\n";
            continue;
        }

        $videos = glob($videosDir . '/*.mp4');
        if (empty($videos)) {
            echo "⚠️ No videos found for {$schedule['username']}\n";
            continue;
        }

        $randomVideo = $videos[array_rand($videos)];
        $videoName = basename($randomVideo);
        $videoUrl = APP_URL . "/videos/" . $schedule['username'] . "_videos/" . $videoName;

        echo "🎬 Selected video: $videoName\n";

        // Caption selection
        $captionsFile = __DIR__ . "/captions/{$schedule['username']}_title.json";
        if (!file_exists($captionsFile)) {
            echo "⚠️ Captions file not found\n";
            continue;
        }

        $captions = json_decode(file_get_contents($captionsFile), true);
        if (empty($captions)) {
            echo "⚠️ No captions available\n";
            continue;
        }

        $randomCaption = $captions[array_rand($captions)];
        echo "📝 Caption: $randomCaption\n";

        // Upload video to TikTok
        try {
            $result = uploadToTikTok($accessToken, $videoUrl, $randomCaption);

            if ($result['success']) {
                echo "✅ Upload successful!\n";

                if (@unlink($randomVideo)) {
                    echo "🧹 Deleted video from server\n";
                }

                $stmt = $pdo->prepare("UPDATE schedules SET last_uploaded_at = NOW(), videos_count = videos_count - 1 WHERE id = ?");
                $stmt->execute([$schedule['id']]);
                updateNextScheduledTime($pdo, $schedule['id'], $scheduleTimes);

                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'video_uploaded', $videoName, $randomCaption, 'success', 'Automatic upload via cron');
                echo "✅ Schedule updated successfully\n";
            } else {
                echo "❌ Upload failed: " . $result['message'] . "\n";
                logActivity($pdo, $schedule['user_id'], $schedule['username'], 'upload_failed', $videoName, $randomCaption, 'failed', $result['message']);
            }
        } catch (Exception $e) {
            echo "💥 Error: " . $e->getMessage() . "\n";
            logActivity($pdo, $schedule['user_id'], $schedule['username'], 'upload_error', $videoName, $randomCaption, 'failed', $e->getMessage());
        }

        echo "--------------------------------------\n";
    }

    updateStats($pdo);
    echo "[" . date('Y-m-d H:i:s') . "] Cron job completed\n";

} catch (Exception $e) {
    echo "💣 Fatal error: " . $e->getMessage() . "\n";
    error_log("Cron error: " . $e->getMessage());
}

/* ==============================
   Helper func
============================== */

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
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) throw new Exception("Token refresh failed (HTTP $code)");

    $data = json_decode($response, true);
    if (empty($data['access_token'])) throw new Exception('Invalid token response');

    $tokenExpiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
    $refreshExpiresAt = date('Y-m-d H:i:s', time() + $data['refresh_expires_in']);

    $stmt = $pdo->prepare("UPDATE users SET access_token=?, refresh_token=?, token_expires_at=?, refresh_expires_at=? WHERE id=?");
    $stmt->execute([$data['access_token'], $data['refresh_token'], $tokenExpiresAt, $refreshExpiresAt, $userId]);

    return $data['access_token'];
}

function uploadToTikTok($accessToken, $videoUrl, $caption) {
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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['success' => false, 'message' => "Init connection error: $error"];

    $data = json_decode($response, true);
    if ($httpCode !== 200 || empty($data['data']['publish_id'])) {
        $msg = $data['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'message' => "Init failed: $msg"];
    }

    return [
        'success' => true,
        'publish_id' => $data['data']['publish_id'],
        'message' => 'Video uploaded successfully to TikTok'
    ];
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
