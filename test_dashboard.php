<?php
session_start();
$_SESSION['user_id'] = 163;
$_SESSION['user_name'] = 'Test User';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'dashboard.php';
