<?php
require 'app.php';
$_SESSION = [];
setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
redirect('login.php');
