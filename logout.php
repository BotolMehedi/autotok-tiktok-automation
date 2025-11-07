<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}
session_unset();
session_destroy();

header('Location: index.php');
exit;
?>
