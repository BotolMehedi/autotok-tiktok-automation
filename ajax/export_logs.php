<?php
require_once '../config.php';
requireLogin();

// Get logs
$stmt = $pdo->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$logs = $stmt->fetchAll();

// gen text file
$content = "Activity Logs - " . $_SESSION['username'] . "\n";
$content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
$content .= str_repeat("=", 80) . "\n\n";

foreach ($logs as $log) {
    $content .= "Time: " . $log['created_at'] . "\n";
    $content .= "Action: " . $log['action'] . "\n";
    $content .= "Status: " . strtoupper($log['status']) . "\n";
    if ($log['video_name']) $content .= "Video: " . $log['video_name'] . "\n";
    if ($log['message']) $content .= "Message: " . $log['message'] . "\n";
    $content .= str_repeat("-", 80) . "\n";
}

// down file
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.txt"');
echo $content;
?>
