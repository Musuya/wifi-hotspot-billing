<?php
require_once __DIR__ . '/../includes/helpers.php';

session_start();
session_destroy();
redirect('login.php');