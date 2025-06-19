<?php
session_start();
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$auth->logout();

header('Location: index.php');
exit;
?>