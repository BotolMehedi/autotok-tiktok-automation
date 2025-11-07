<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $username = sanitizeInput($_POST['username']);
    $captions = $_POST['captions'];
    $times = json_decode($_POST['times'], true);
    
    // Validate inputs
    if (empty($username) || empty($captions) || empty($times)) {
        throw new Exception('All fields are required');
    }

    // Validate JSON
    $captionsArray = json_decode($captions, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format for captions');
    }

    // Validate files
    if (!isset($_FILES['videos']) || empty($_FILES['videos']['name'][0])) {
        throw new Exception('Please upload at least one video');
    }

    // Create directories
    $videosDir = __DIR__ . "/../videos/{$username}_videos";
    $captionsDir = __DIR__ . "/../captions";
    
    if (!is_dir($videosDir)) {
        mkdir($videosDir, 0755, true);
    }
    if (!is_dir($captionsDir)) {
        mkdir($captionsDir, 0755, true);
    }

    // Upload videos
    $uploadedCount = 0;
    $files = $_FILES['videos'];
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = $files['name'][$i];
            $tmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file type
            if ($fileExt !== 'mp4') {
                continue;
            }
            
            // Validate file size
            if ($fileSize > MAX_VIDEO_SIZE) {
                continue;
            }
            
            // Generate unique filename
            $newFileName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME)) . '.mp4';
            $destination = $videosDir . '/' . $newFileName;
            
            if (move_uploaded_file($tmpName, $destination)) {
                $uploadedCount++;
            }
        }
    }

    if ($uploadedCount === 0) {
        throw new Exception('No videos were uploaded successfully');
    }

    // Save captions JSON
    $captionsFile = $captionsDir . '/' . $username . '_title.json';
    file_put_contents($captionsFile, $captions);

    // Calculate next scheduled time
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

    // Insert schedule into database
    $stmt = $pdo->prepare("INSERT INTO schedules (user_id, username, schedule_times, videos_count, captions_count, next_scheduled_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $username,
        json_encode($times),
        $uploadedCount,
        count($captionsArray),
        $nextScheduled
    ]);

    // Log activity
    logActivity($pdo, $_SESSION['user_id'], $username, 'schedule_created', null, null, 'success', "$uploadedCount videos uploaded");

    // Update stats
    updateStats($pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Schedule created successfully',
        'uploaded_count' => $uploadedCount
    ]);

} catch (Exception $e) {
    logActivity($pdo, $_SESSION['user_id'] ?? 0, $_POST['username'] ?? 'unknown', 'schedule_create_failed', null, null, 'failed', $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
