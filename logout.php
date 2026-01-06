<?php
require_once 'config/app.php';
session_destroy();
header("Location: login.php");
exit;
