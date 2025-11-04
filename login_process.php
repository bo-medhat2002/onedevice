<?php
// login_process.php
include 'config.php';
include 'functions.php';
if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $account = firebase_get("accounts/" . $username);
    if ($account && isset($account['password_hash']) && password_verify($password, $account['password_hash']) && $account['active']) {
        $server_status = firebase_get("server/status") ?? "on";
        if ($server_status === "off" && $account['role'] !== "owner") {
            header('Location: login.php?error=server_off');
            exit;
        }
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $account['role'];
        header('Location: dashboard.php');
    } else {
        header('Location: login.php?error=invalid');
    }
}
