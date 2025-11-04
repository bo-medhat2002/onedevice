<?php
// process.php
include 'config.php';
include 'functions.php';
$action = $_GET['action'] ?? '';
if (!isset($_SESSION['username'])) die("Unauthorized");
$username = $_SESSION['username'];
$role = $_SESSION['role'];
if ($action === "create_subadmin" && $role === "owner") {
    $new_username = trim($_POST['username'] ?? '');
    if (firebase_get("accounts/" . $new_username)) die("Username exists");
    $password_hash = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $max_users = (int)($_POST['max_users'] ?? 0);
    $data = [
        "role" => "subadmin",
        "password_hash" => $password_hash,
        "max_users" => $max_users,
        "current_users" => 0,
        "active" => true
    ];
    firebase_put("accounts/" . $new_username, $data);
    header('Location: dashboard.php?page=subadmins');
} elseif ($action === "deactivate_subadmin" && $role === "owner") {
    $target = $_GET['username'] ?? '';
    $acc = firebase_get("accounts/" . $target);
    if ($acc && isset($acc['role']) && $acc['role'] === "subadmin") {
        firebase_patch("accounts/" . $target, ["active" => false]);
        $all_accounts = firebase_get("accounts");
        foreach ($all_accounts ?? [] as $key => $uacc) {
            if (isset($uacc['role']) && $uacc['role'] === "user" && isset($uacc['created_by']) && $uacc['created_by'] === $target) {
                firebase_patch("accounts/" . $key, ["active" => false]);
            }
        }
    }
    header('Location: dashboard.php?page=subadmins');
} elseif ($action === "activate_subadmin" && $role === "owner") {
    $target = $_GET['username'] ?? '';
    $acc = firebase_get("accounts/" . $target);
    if ($acc && isset($acc['role']) && $acc['role'] === "subadmin") {
        firebase_patch("accounts/" . $target, ["active" => true]);
    }
    header('Location: dashboard.php?page=subadmins');
} elseif ($action === "create_user" && $role === "subadmin") {
    $creator_acc = firebase_get("accounts/" . $username);
    if (($creator_acc['current_users'] ?? 0) >= ($creator_acc['max_users'] ?? 0)) die("Limit reached");
    $new_username = trim($_POST['username'] ?? '');
    if (firebase_get("accounts/" . $new_username)) die("Username exists");
    $password_hash = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $allowed_devices = (int)($_POST['allowed_devices'] ?? 0);
    $subscription_days = (int)($_POST['subscription_days'] ?? 0);
    $subscription_end = time() + $subscription_days * 86400;
    $data = [
        "role" => "user",
        "password_hash" => $password_hash,
        "created_by" => $username,
        "active" => true,
        "allowed_devices" => $allowed_devices,
        "subscription_end" => $subscription_end,
        "devices" => []
    ];
    firebase_put("accounts/" . $new_username, $data);
    firebase_patch("accounts/" . $username, ["current_users" => ($creator_acc['current_users'] ?? 0) + 1]);
    header('Location: dashboard.php?page=users');
} elseif ($action === "deactivate_user" && ($role === "owner" || $role === "subadmin")) {
    $target = $_GET['username'] ?? '';
    $acc = firebase_get("accounts/" . $target);
    if ($acc && isset($acc['role']) && $acc['role'] === "user" && ($role === "owner" || (isset($acc['created_by']) && $acc['created_by'] === $username))) {
        firebase_patch("accounts/" . $target, ["active" => false]);
    }
    header('Location: dashboard.php?page=users');
} elseif ($action === "activate_user" && ($role === "owner" || $role === "subadmin")) {
    $target = $_GET['username'] ?? '';
    $acc = firebase_get("accounts/" . $target);
    if ($acc && isset($acc['role']) && $acc['role'] === "user" && ($role === "owner" || (isset($acc['created_by']) && $acc['created_by'] === $username))) {
        firebase_patch("accounts/" . $target, ["active" => true]);
    }
    header('Location: dashboard.php?page=users');
} elseif ($action === "edit_user" && ($role === "owner" || $role === "subadmin")) {
    $target = $_GET['username'] ?? '';
    $acc = firebase_get("accounts/" . $target);
    if ($acc && isset($acc['role']) && $acc['role'] === "user" && ($role === "owner" || (isset($acc['created_by']) && $acc['created_by'] === $username))) {
        $updates = [];
        if (isset($_POST['extend_days']) && (int)$_POST['extend_days'] > 0) {
            $updates['subscription_end'] = ($acc['subscription_end'] ?? time()) + (int)$_POST['extend_days'] * 86400;
        }
        if (isset($_POST['allowed_devices'])) {
            $updates['allowed_devices'] = (int)$_POST['allowed_devices'];
        }
        if (!empty($updates)) {
            firebase_patch("accounts/" . $target, $updates);
        }
    }
    header('Location: dashboard.php?page=users');
} elseif ($action === "add_file" && $role === "owner") {
    $data = [
        "name" => $_POST['name'] ?? '',
        "url" => $_POST['url'] ?? ''
    ];
    firebase_post("files", $data);
    header('Location: dashboard.php?page=files');
} elseif ($action === "delete_file" && $role === "owner") {
    $fid = $_GET['fid'] ?? '';
    firebase_delete("files/" . $fid);
    header('Location: dashboard.php?page=files');
} elseif ($action === "send_notification" && ($role === "owner" || $role === "subadmin")) {
    $message = $_POST['message'] ?? '';
    $to_type = $_POST['to_type'] ?? '';
    $to_ids = [];
    if ($role === "subadmin") {
        if ($to_type === "specific") {
            $to_ids = $_POST['to_users'] ?? [];
        } elseif ($to_type === "all_my_users") {
            $all_accounts = firebase_get("accounts");
            foreach ($all_accounts ?? [] as $key => $acc) {
                if (isset($acc['role']) && $acc['role'] === "user" && isset($acc['created_by']) && $acc['created_by'] === $username) {
                    $to_ids[] = $key;
                }
            }
        }
        $to_type = "specific"; // for subadmin, always specific even for all
    }
    // for owner, to_type remains admins/users/both, no to_ids
    $data = [
        "from" => $username,
        "to_type" => $to_type,
        "to_ids" => $to_ids,
        "message" => $message,
        "timestamp" => time()
    ];
    firebase_post("notifications", $data);
    header('Location: dashboard.php?page=notifications');
} elseif ($action === "toggle_server" && $role === "owner") {
    $current = firebase_get("server/status") ?? "on";
    $new = $current === "on" ? "off" : "on";
    firebase_put("server/status", $new);
    header('Location: dashboard.php?page=server');
} elseif ($action === "delete_device" && $role === "user") {
    $index = (int)($_GET['index'] ?? -1);
    $acc = firebase_get("accounts/" . $username);
    if (isset($acc['devices'][$index])) {
        unset($acc['devices'][$index]);
        $acc['devices'] = array_values($acc['devices']);
        firebase_patch("accounts/" . $username, ["devices" => $acc['devices']]);
    }
    header('Location: dashboard.php?page=devices');
} elseif ($action === "add_device" && $role === "user") {
    $device_name = trim($_POST['device_name'] ?? '');
    if ($device_name) {
        $acc = firebase_get("accounts/" . $username);
        $devices = $acc['devices'] ?? [];
        if (count($devices) < ($acc['allowed_devices'] ?? 0)) {
            $devices[] = $device_name;
            firebase_patch("accounts/" . $username, ["devices" => $devices]);
        }
    }
    header('Location: dashboard.php?page=devices');
}
