<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $id = intval($_POST['id']);
    $username = sanitizeInput($_POST['username']);
    $captions = $_POST['captions'];
    $times = json_decode($_POST['times'], true);
    $removedVideos = json_decode($_POST['removed_videos'] ?? '[]', true);

    if (empty($id) || empty($username) || empty($captions) || empty($times)) {
        throw new Exception('All fields are required');
    }

    // Validate captions JSON
    $captionsArray = json_decode($captions, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format for captions');
    }

    // Fetch existing schedule (to locate videos folder)
    $stmt = $pdo->prepare("SELECT username FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    $videosDir = __DIR__ . "/../videos/{$username}_videos";
    $captionsDir = __DIR__ . "/../captions";

    if (!is_dir($videosDir)) {
        mkdir($videosDir, 0755, true);
    }
    if (!is_dir($captionsDir)) {
        mkdir($captionsDir, 0755, true);
    }

    // Handle video deletions
    $removedCount = 0;
    if (!empty($removedVideos)) {
        foreach ($removedVideos as $file) {
            $path = $videosDir . '/' . basename($file);
            if (file_exists($path)) {
                unlink($path);
                $removedCount++;
            }
        }
    }

    // Handle new uploads
    $uploadedCount = 0;
    if (!empty($_FILES['videos']['name'][0])) {
        $files = $_FILES['videos'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $files['name'][$i];
                $tmpName = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExt !== 'mp4') continue;
                if ($fileSize > MAX_VIDEO_SIZE) continue;

                $newFileName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($fileName, PATHINFO_FILENAME)) . '.mp4';
                $destination = $videosDir . '/' . $newFileName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $uploadedCount++;
                }
            }
        }
    }

    // Update captions file
    $captionsFile = $captionsDir . '/' . $username . '_title.json';
    file_put_contents($captionsFile, $captions);

    // Recalculate total video count
    $allVideos = glob($videosDir . '/*.mp4');
    $videosCount = count($allVideos);
    $captionsCount = count($captionsArray);

    // Recalculate next scheduled time
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

    // Update schedule record
    $stmt = $pdo->prepare("UPDATE schedules SET username = ?, schedule_times = ?, videos_count = ?, captions_count = ?, next_scheduled_at = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([
        $username,
        json_encode($times),
        $videosCount,
        $captionsCount,
        $nextScheduled,
        $id,
        $_SESSION['user_id']
    ]);

    // Log activity
    $actionDetails = [];
    if ($uploadedCount > 0) $actionDetails[] = "$uploadedCount new videos uploaded";
    if ($removedCount > 0) $actionDetails[] = "$removedCount videos deleted";
    $details = empty($actionDetails) ? 'Schedule updated' : implode(', ', $actionDetails);

    logActivity($pdo, $_SESSION['user_id'], $username, 'schedule_updated', null, null, 'success', $details);
    updateStats($pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully',
        'uploaded_count' => $uploadedCount,
        'removed_count' => $removedCount
    ]);

} catch (Exception $e) {
    logActivity($pdo, $_SESSION['user_id'] ?? 0, $_POST['username'] ?? 'unknown', 'schedule_update_failed', null, null, 'failed', $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
