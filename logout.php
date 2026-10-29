<?php
require_once __DIR__ . '/bootstrap.php';
$slug = ($_SESSION['role'] ?? '') === 'customer' ? ($_SESSION['slug'] ?? '') : '';
$_SESSION = [];
session_destroy();
redirect($slug !== '' ? 'welcome.php?r=' . urlencode($slug) : 'index.php');
