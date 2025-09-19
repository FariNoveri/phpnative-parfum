<?php
require_once 'config/database.php';

// Destroy session
session_destroy();

// Start new session for message
session_start();
$_SESSION['message'] = 'Anda telah berhasil logout';
$_SESSION['message_type'] = 'success';

redirect('index.php');
?>