<?php
session_start();
function require_admin_login() {
    if (!isset($_SESSION['admin'])) {
        header('Location: ../admin_login.php');
        exit;
    }
}
function get_admin() {
    return $_SESSION['admin'] ?? null;
} 