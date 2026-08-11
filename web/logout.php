<?php
// =====================================================
// LOGOUT HANDLER
// =====================================================

require_once __DIR__ . '/config/auth.php';

doLogout();

header('Location: index.php');
exit;
