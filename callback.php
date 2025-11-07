<?php
require_once 'config.php';

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    die("Authorization failed: Missing parameters");
}

$state = $_GET['state'];
$authCode = $_GET['code'];

// profile or main user
try {
    $stateData = json_decode(base64_decode($state), true);
    
    if (isset($stateData['schedule_id']) && isset($stateData['action']) && $stateData['action'] === 'authorize') {
        // profile auth
        requireLogin();
        handleProfileAuthorization($pdo, $authCode, $stateData['schedule_id']);
    } else {
        // main user auth
        if (!isset($_SESSION['csrf_token']) || $state !== $_SESSION['csrf_token']) {
            die("Authorization failed: Invalid state parameter");
        }
        handleUserAuthorization($pdo, $authCode);
    }
} catch (Exception $e) {
    // Fallback
    if (!isset($_SESSION['csrf_token']) || $state !== $_SESSION['csrf_token']) {
        die("Authorization failed: Invalid state parameter");
    }
    handleUserAuthorization($pdo, $authCode);
}

function handleUserAuthorization($pdo, $authCode) {
    // Exchange auth code
    $tokenUrl = "https://open.tiktokapis.com/v2/oauth/token/";
    $postData = [
        'client_key' => TIKTOK_CLIENT_KEY,
        'client_secret' => TIKTOK_CLIENT_SECRET,
        'code' => $authCode,
        'grant_type' => 'authorization_code',
        'redirect_uri' => TIKTOK_REDIRECT_URI
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Cache-Control: no-cache'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("Token exchange failed: HTTP $httpCode");
    }

    $tokenData = json_decode($response, true);

    if (!isset($tokenData['access_token']) || !isset($tokenData['open_id'])) {
        die("Token exchange failed: Invalid response");
    }

    // Get user info
    $userInfoUrl = "https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name";
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token']
    ]);

    $userResponse = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($userResponse, true);
    $username = $userData['data']['user']['display_name'] ?? 'User_' . substr($tokenData['open_id'], 0, 8);

    // cal token expiry
    $tokenExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
    $refreshExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['refresh_expires_in']);

    // Store or update user
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE open_id = ?");
        $stmt->execute([$tokenData['open_id']]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Update user
            $stmt = $pdo->prepare("UPDATE users SET username = ?, access_token = ?, refresh_token = ?, token_expires_at = ?, refresh_expires_at = ?, scope = ? WHERE open_id = ?");
            $stmt->execute([
                $username,
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $tokenExpiresAt,
                $refreshExpiresAt,
                $tokenData['scope'],
                $tokenData['open_id']
            ]);
            $userId = $existingUser['id'];
        } else {
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (open_id, username, access_token, refresh_token, token_expires_at, refresh_expires_at, scope) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tokenData['open_id'],
                $username,
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $tokenExpiresAt,
                $refreshExpiresAt,
                $tokenData['scope']
            ]);
            $userId = $pdo->lastInsertId();
        }

        // crt session
        $sessionId = bin2hex(random_bytes(32));
        $sessionExpires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        $stmt = $pdo->prepare("INSERT INTO sessions (id, user_id, open_id, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $tokenData['open_id'], $sessionExpires]);

        // session
        $_SESSION['user_id'] = $userId;
        $_SESSION['open_id'] = $tokenData['open_id'];
        $_SESSION['username'] = $username;
        $_SESSION['session_id'] = $sessionId;

        // log
        logActivity($pdo, $userId, $username, 'user_login', null, null, 'success', 'User logged in via TikTok OAuth');

        // Update stats
        updateStats($pdo);
        
        header('Location: dashboard.php');
        exit;

    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}

function handleProfileAuthorization($pdo, $authCode, $scheduleId) {
    // Exchange auth code
    $tokenUrl = "https://open.tiktokapis.com/v2/oauth/token/";
    $postData = [
        'client_key' => TIKTOK_CLIENT_KEY,
        'client_secret' => TIKTOK_CLIENT_SECRET,
        'code' => $authCode,
        'grant_type' => 'authorization_code',
        'redirect_uri' => TIKTOK_REDIRECT_URI
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Cache-Control: no-cache'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $_SESSION['error'] = "Profile authorization failed: HTTP $httpCode";
        header('Location: dashboard.php');
        exit;
    }

    $tokenData = json_decode($response, true);

    if (!isset($tokenData['access_token'])) {
        $_SESSION['error'] = "Profile authorization failed: Invalid response";
        header('Location: dashboard.php');
        exit;
    }

    // cal token expiry
    $tokenExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
    $refreshExpiresAt = date('Y-m-d H:i:s', time() + $tokenData['refresh_expires_in']);

    // Store or update token
    try {
        $stmt = $pdo->prepare("SELECT id FROM profile_tokens WHERE schedule_id = ?");
        $stmt->execute([$scheduleId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update token
            $stmt = $pdo->prepare("UPDATE profile_tokens SET access_token = ?, refresh_token = ?, token_expires_at = ?, refresh_expires_at = ?, scope = ? WHERE schedule_id = ?");
            $stmt->execute([
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $tokenExpiresAt,
                $refreshExpiresAt,
                $tokenData['scope'],
                $scheduleId
            ]);
        } else {
            // store new token
            $stmt = $pdo->prepare("INSERT INTO profile_tokens (schedule_id, user_id, access_token, refresh_token, token_expires_at, refresh_expires_at, scope) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $scheduleId,
                $_SESSION['user_id'],
                $tokenData['access_token'],
                $tokenData['refresh_token'],
                $tokenExpiresAt,
                $refreshExpiresAt,
                $tokenData['scope']
            ]);
        }

        // Get schedule
        $stmt = $pdo->prepare("SELECT username FROM schedules WHERE id = ?");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();

        // log
        logActivity($pdo, $_SESSION['user_id'], $schedule['username'], 'profile_authorized', null, null, 'success', 'Profile authorized successfully');

        $_SESSION['success'] = "Profile authorized successfully!";
        header('Location: dashboard.php?authorized=1');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: dashboard.php');
        exit;
    }
}
?>
