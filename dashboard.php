<?php
// dashboard.php
include 'config.php';
include 'functions.php';
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
}
$role = $_SESSION['role'];
$username = $_SESSION['username'];
$account = firebase_get("accounts/" . $username);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f4;
        }

        .sidebar {
            width: 250px;
            background: #343a40;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar h2 {
            padding: 20px;
            margin: 0;
            background: #23282d;
        }

        .sidebar a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background: #495057;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        h1 {
            color: #333;
        }

        form {
            max-width: 500px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            color: #333;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        ul li {
            padding: 10px;
            background: white;
            margin: 10px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        p {
            color: #555;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Control Panel</h2>
        <?php if ($role === "owner") { ?>
            <a href="dashboard.php?page=dashboard">Dashboard</a>
            <a href="dashboard.php?page=subadmins">Sub-Admins</a>
            <a href="dashboard.php?page=users">Users</a>
            <a href="dashboard.php?page=files">Files</a>
            <a href="dashboard.php?page=notifications">Notifications</a>
            <a href="dashboard.php?page=server">Server Control</a>
        <?php } elseif ($role === "subadmin") { ?>
            <a href="dashboard.php?page=dashboard">Dashboard</a>
            <a href="dashboard.php?page=users">My Users</a>
            <a href="dashboard.php?page=notifications">Notifications</a>
        <?php } elseif ($role === "user") { ?>
            <a href="dashboard.php?page=dashboard">Dashboard</a>
            <a href="dashboard.php?page=notifications">Notifications</a>
            <a href="dashboard.php?page=devices">Devices</a>
            <a href="dashboard.php?page=files">Files</a>
        <?php } ?>
        <a href="logout.php">Logout</a>
    </div>
    <div class="main">
        <?php
        $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
        $all_accounts = firebase_get("accounts");
        if ($page === 'dashboard') {
            echo "<h1>Welcome, $username</h1>";
            if ($role === "owner") {
                $subadmins = 0;
                $users = 0;
                foreach ($all_accounts ?? [] as $acc) {
                    if (isset($acc['role']) && $acc['role'] === "subadmin") $subadmins++;
                    if (isset($acc['role']) && $acc['role'] === "user") $users++;
                }
                echo "<p>Sub-Admins: $subadmins</p>";
                echo "<p>Users: $users</p>";
            } elseif ($role === "subadmin") {
                $my_users = 0;
                foreach ($all_accounts ?? [] as $acc) {
                    if (isset($acc['role']) && $acc['role'] === "user" && isset($acc['created_by']) && $acc['created_by'] === $username) $my_users++;
                }
                echo "<p>Your Users: $my_users / " . ($account['max_users'] ?? 0) . "</p>";
            } elseif ($role === "user") {
                echo "<p>Subscription End: " . (isset($account['subscription_end']) ? date("Y-m-d", $account['subscription_end']) : 'N/A') . "</p>";
                echo "<p>Active: " . (isset($account['active']) && $account['active'] ? "Yes" : "No") . "</p>";
                echo "<p>Allowed Devices: " . ($account['allowed_devices'] ?? 0) . "</p>";
            }
        } elseif ($page === 'subadmins' && $role === "owner") {
            echo "<h1>Sub-Admins</h1>";
            echo "<form action='process.php?action=create_subadmin' method='post'>";
            echo "<input type='text' name='username' placeholder='Username' required>";
            echo "<input type='password' name='password' placeholder='Password' required>";
            echo "<input type='number' name='max_users' placeholder='Max Users' required>";
            echo "<button type='submit'>Create Sub-Admin</button>";
            echo "</form>";
            echo "<table><tr><th>Username</th><th>Max Users</th><th>Current Users</th><th>Active</th><th>Actions</th></tr>";
            foreach ($all_accounts ?? [] as $key => $acc) {
                if (isset($acc['role']) && $acc['role'] === "subadmin") {
                    echo "<tr><td>$key</td><td>" . ($acc['max_users'] ?? 0) . "</td><td>" . ($acc['current_users'] ?? 0) . "</td><td>" . (isset($acc['active']) && $acc['active'] ? "Yes" : "No") . "</td>";
                    echo "<td><a href='process.php?action=deactivate_subadmin&username=$key'>Deactivate</a> | <a href='process.php?action=activate_subadmin&username=$key'>Activate</a></td></tr>";
                }
            }
            echo "</table>";
        } elseif ($page === 'users' && ($role === "owner" || $role === "subadmin")) {
            echo "<h1>" . ($role === "subadmin" ? "My Users" : "All Users") . "</h1>";
            if ($role === "subadmin") {
                if (($account['current_users'] ?? 0) < ($account['max_users'] ?? 0)) {
                    echo "<form action='process.php?action=create_user' method='post'>";
                    echo "<input type='text' name='username' placeholder='Username' required>";
                    echo "<input type='password' name='password' placeholder='Password' required>";
                    echo "<input type='number' name='allowed_devices' placeholder='Allowed Devices' required>";
                    echo "<input type='number' name='subscription_days' placeholder='Subscription Days' required>";
                    echo "<button type='submit'>Create User</button>";
                    echo "</form>";
                } else {
                    echo "<p>You have reached your max users limit.</p>";
                }
            }
            echo "<table><tr><th>Username</th><th>Created By</th><th>Active</th><th>Subscription End</th><th>Allowed Devices</th><th>Actions</th></tr>";
            foreach ($all_accounts ?? [] as $key => $acc) {
                if (isset($acc['role']) && $acc['role'] === "user" && ($role === "owner" || (isset($acc['created_by']) && $acc['created_by'] === $username))) {
                    $sub_end = isset($acc['subscription_end']) ? date("Y-m-d", $acc['subscription_end']) : 'N/A';
                    echo "<tr><td>$key</td><td>" . ($acc['created_by'] ?? 'N/A') . "</td><td>" . (isset($acc['active']) && $acc['active'] ? "Yes" : "No") . "</td><td>$sub_end</td><td>" . ($acc['allowed_devices'] ?? 0) . "</td>";
                    echo "<td><a href='process.php?action=deactivate_user&username=$key'>Deactivate</a> | <a href='process.php?action=activate_user&username=$key'>Activate</a> | <a href='dashboard.php?page=edit_user&username=$key'>Edit</a></td></tr>";
                }
            }
            echo "</table>";
        } elseif ($page === 'edit_user' && ($role === "owner" || $role === "subadmin")) {
            $edit_username = $_GET['username'] ?? '';
            $edit_account = firebase_get("accounts/" . $edit_username);
            if ($edit_account && isset($edit_account['role']) && $edit_account['role'] === "user" && ($role === "owner" || (isset($edit_account['created_by']) && $edit_account['created_by'] === $username))) {
                echo "<h1>Edit User $edit_username</h1>";
                echo "<form action='process.php?action=edit_user&username=$edit_username' method='post'>";
                echo "<input type='number' name='extend_days' placeholder='Extend Subscription Days'>";
                echo "<input type='number' name='allowed_devices' placeholder='Allowed Devices' value='" . ($edit_account['allowed_devices'] ?? 0) . "'>";
                echo "<button type='submit'>Save Changes</button>";
                echo "</form>";
            } else {
                echo "<p>Invalid user or permission denied.</p>";
            }
        } elseif ($page === 'files' && $role === "owner") {
            echo "<h1>Manage Files</h1>";
            echo "<form action='process.php?action=add_file' method='post'>";
            echo "<input type='text' name='name' placeholder='File Name' required>";
            echo "<input type='text' name='url' placeholder='Download URL' required>";
            echo "<button type='submit'>Add File</button>";
            echo "</form>";
            $files = firebase_get("files");
            if ($files) {
                echo "<table><tr><th>Name</th><th>URL</th><th>Actions</th></tr>";
                foreach ($files as $fid => $file) {
                    echo "<tr><td>" . ($file['name'] ?? '') . "</td><td>" . ($file['url'] ?? '') . "</td><td><a href='process.php?action=delete_file&fid=$fid'>Delete</a></td></tr>";
                }
                echo "</table>";
            }
        } elseif ($page === 'files' && $role === "user") {
            echo "<h1>Download Files</h1>";
            $files = firebase_get("files");
            if ($files) {
                echo "<ul>";
                foreach ($files as $file) {
                    echo "<li><a href='" . ($file['url'] ?? '#') . "' target='_blank'>" . ($file['name'] ?? 'Unnamed') . "</a></li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No files available.</p>";
            }
        } elseif ($page === 'notifications') {
            echo "<h1>Notifications</h1>";
            if ($role === "owner") {
                echo "<form action='process.php?action=send_notification' method='post'>";
                echo "<textarea name='message' placeholder='Message' required></textarea>";
                echo "<select name='to_type'>";
                echo "<option value='admins'>To Sub-Admins</option>";
                echo "<option value='users'>To Users</option>";
                echo "<option value='both'>To Both</option>";
                echo "</select>";
                echo "<button type='submit'>Send</button>";
                echo "</form>";
            } elseif ($role === "subadmin") {
                echo "<form action='process.php?action=send_notification' method='post'>";
                echo "<textarea name='message' placeholder='Message' required></textarea>";
                echo "<select name='to_type' id='to_type'>";
                echo "<option value='all_my_users'>To All My Users</option>";
                echo "<option value='specific'>To Specific Users</option>";
                echo "</select>";
                echo "<div id='specific_users' style='display: none;'>";
                foreach ($all_accounts ?? [] as $key => $acc) {
                    if (isset($acc['role']) && $acc['role'] === "user" && isset($acc['created_by']) && $acc['created_by'] === $username) {
                        echo "<label><input type='checkbox' name='to_users[]' value='$key'> $key</label><br>";
                    }
                }
                echo "</div>";
                echo "<button type='submit'>Send</button>";
                echo "</form>";
                echo "<script>
          document.getElementById('to_type').addEventListener('change', function() {
            document.getElementById('specific_users').style.display = this.value === 'specific' ? 'block' : 'none';
          });
        </script>";
            }
            echo "<h2>Received Notifications</h2>";
            $nots = firebase_get("notifications");
            if ($nots) {
                krsort($nots);
                echo "<ul>";
                foreach ($nots as $not) {
                    $show = false;
                    if ($role === "owner" || $role === "subadmin") {
                        if (isset($not['to_type']) && ($not['to_type'] === "admins" || $not['to_type'] === "both")) $show = true;
                    } elseif ($role === "user") {
                        if (isset($not['to_type']) && ($not['to_type'] === "users" || $not['to_type'] === "both" || ($not['to_type'] === "specific" && isset($not['to_ids']) && in_array($username, $not['to_ids'])))) $show = true;
                    }
                    if ($show) {
                        echo "<li>" . ($not['message'] ?? '') . " - From: " . ($not['from'] ?? 'Unknown') . " at " . (isset($not['timestamp']) ? date("Y-m-d H:i", $not['timestamp']) : 'N/A') . "</li>";
                    }
                }
                echo "</ul>";
            } else {
                echo "<p>No notifications.</p>";
            }
        } elseif ($page === 'server' && $role === "owner") {
            echo "<h1>Server Control</h1>";
            $status = firebase_get("server/status") ?? "on";
            echo "<p>Current Status: " . ucfirst($status) . "</p>";
            echo "<a href='process.php?action=toggle_server'><button>Toggle Status</button></a>";
        } elseif ($page === 'devices' && $role === "user") {
            echo "<h1>Manage Devices</h1>";
            $devices = $account['devices'] ?? [];
            if (!empty($devices)) {
                echo "<ul>";
                foreach ($devices as $index => $device) {
                    echo "<li>$device <a href='process.php?action=delete_device&index=$index'>Delete</a></li>";
                }
                echo "</ul>";
            } else {
                echo "<p>No devices added.</p>";
            }
            echo "<form action='process.php?action=add_device' method='post'>";
            echo "<input type='text' name='device_name' placeholder='Device Name' required>";
            echo "<button type='submit'>Add Device</button>";
            echo "</form>";
        }
        ?>
    </div>
</body>

</html>