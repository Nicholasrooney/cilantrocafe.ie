<?php
require_once __DIR__ . '/../includes/auth.php';
staff_logout();
header('Location: login.php', true, 303);
