<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $scheduleId = intval($data['id']);
    
    // Get schedule details
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$scheduleId, $_SESSION['user_id']]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    // Delete
    $stmt = $pdo->prepare("UPDATE schedules SET status = 'deleted' WHERE id = ?");
    $stmt->execute([$scheduleId]);

    // Delete video
    $videosDir = __DIR__ . "/../videos/{$schedule['username']}_videos";
    if (is_dir($videosDir)) {
        $files = glob($videosDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
        rmdir($videosDir);
    }

    // Delete caption file
    $captionsFile = __DIR__ . "/../captions/{$schedule['username']}_title.json";
    if (file_exists($captionsFile)) {
        unlink($captionsFile);
    }

    // Delete log
    $logFile = __DIR__ . "/../logs/{$schedule['username']}.log";
    if (file_exists($logFile)) {
        unlink($logFile);
    }

    // log
    logActivity($pdo, $_SESSION['user_id'], $schedule['username'], 'schedule_deleted', null, null, 'success', 'Schedule and all files deleted');

    // Update stats
    updateStats($pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
