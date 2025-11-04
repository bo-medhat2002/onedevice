<?php
// api.php - API for External App (Lua, etc.)
header('Content-Type: application/json; charset=utf-8');
include 'config.php';
include 'functions.php';

// === استقبال البيانات من التطبيق ===
$input = json_decode(file_get_contents('php://input'), true);

$username       = $input['username']       ?? '';
$password       = $input['password']       ?? '';
$device_name    = $input['device_name']    ?? '';
$device_id      = $input['device_id']      ?? '';
$client_ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// تحقق من وجود الحقول الأساسية
if (empty($username) || empty($password) || empty($device_name) || empty($device_id)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"], JSON_UNESCAPED_UNICODE);
    exit;
}

// === جلب بيانات المستخدم من Firebase ===
$user = firebase_get("accounts/$username");

if (!$user || !isset($user['password_hash'])) {
    echo json_encode(["success" => false, "message" => "Invalid username"], JSON_UNESCAPED_UNICODE);
    exit;
}

// === التحقق من كلمة السر ===
if (!password_verify($password, $user['password_hash'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'print("Invalid password")';
    exit;
}

// === التحقق من حالة التفعيل ===
if (!($user['active'] ?? false)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'print("Account is deactivated")';
    exit;
}

// === التحقق من انتهاء الاشتراك ===
$now = time();
if (isset($user['subscription_end']) && $user['subscription_end'] < $now) {
    echo json_encode(["success" => false, "message" => "Subscription expired"], JSON_UNESCAPED_UNICODE);
    exit;
}

// === جلب عدد الأجهزة المسموح بها ===
$allowed_devices = $user['allowed_devices'] ?? 0;
$devices = $user['devices'] ?? [];

// === التحقق من وجود الجهاز مسبقًا (بناءً على device_id) ===
$device_exists = false;
$device_index = -1;

foreach ($devices as $index => $saved_device) {
    // نفترض أن كل جهاز محفوظ كـ: "اسم_الجهاز|device_id|آخر_IP"
    $parts = explode('|', $saved_device);
    if (count($parts) >= 2 && $parts[1] === $device_id) {
        $device_exists = true;
        $device_index = $index;
        break;
    }
}

// === إذا كان الجهاز جديد: تحقق من الحد الأقصى ===
if (!$device_exists && count($devices) >= $allowed_devices) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'print("Max devices reached. ")';
    exit;
}

// === تحديث أو إضافة الجهاز ===
$new_device_entry = "$device_name|$device_id|$client_ip";

if ($device_exists) {
    // تحديث IP فقط
    $devices[$device_index] = $new_device_entry;
} else {
    // إضافة جهاز جديد
    $devices[] = $new_device_entry;
}

// حفظ الأجهزة المحدثة
firebase_patch("accounts/$username", ["devices" => $devices]);

// === إذا كل شيء صحيح: جلب ملف Lua عشوائي أو أول ملف ===
$files = firebase_get("files");
if (!$files || empty($files)) {
    echo json_encode(["success" => false, "message" => "No Lua files available"], JSON_UNESCAPED_UNICODE);
    exit;
}

// اختيار ملف عشوائي (أو يمكنك تحديد ملف معين)
$file_entry = $files[array_rand($files)];
$lua_url = "https://raw.githubusercontent.com/bo-medhat2002/Html-js-css/refs/heads/main/c.lua";

if (empty($lua_url)) {
    echo json_encode(["success" => false, "message" => "Invalid file URL"], JSON_UNESCAPED_UNICODE);
    exit;
}

// جلب محتوى الملف
$lua_content = @file_get_contents($lua_url);
if ($lua_content === false) {
    echo json_encode(["success" => false, "message" => "Failed to load Lua file"], JSON_UNESCAPED_UNICODE);
    exit;
}

// === النجاح: إرجاع محتوى Lua ===
header('Content-Type: text/plain; charset=utf-8');
echo trim($lua_content);
exit;
