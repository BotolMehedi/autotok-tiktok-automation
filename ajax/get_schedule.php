<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $username = sanitizeInput($_GET['username'] ?? '');
    if (empty($username)) {
        throw new Exception('Username required');
    }

    $videosDir = __DIR__ . "/../videos/{$username}_videos";
    $captionsFile = __DIR__ . "/../captions/{$username}_title.json";

    $videos = [];
    if (is_dir($videosDir)) {
        foreach (glob($videosDir . '/*.mp4') as $filePath) {
            $videos[] = basename($filePath);
        }
    }

    $captionsJson = '';
    if (file_exists($captionsFile)) {
        $captionsJson = file_get_contents($captionsFile);
    }

    echo json_encode([
        'success' => true,
        'captions' => $captionsJson,
        'videos' => $videos
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
