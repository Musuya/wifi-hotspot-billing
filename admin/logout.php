<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
redirect('/admin/login.php');
