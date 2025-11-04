<?php
// create_owner.php
include 'config.php';
include 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        die("اسم المستخدم وكلمة السر مطلوبين");
    }

    // تحقق إذا كان الحساب موجود مسبقًا
    $exists = firebase_get("accounts/$username");
    if ($exists) {
        die("هذا الاسم مستخدم بالفعل!");
    }

    // تشفير كلمة السر تلقائيًا
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // بيانات المالك
    $owner_data = [
        "role"          => "owner",
        "password_hash" => $password_hash,
        "active"        => true
    ];

    // حفظ في Firebase
    firebase_put("accounts/$username", $owner_data);

    // إنشاء حالة السيرفر إذا لم تكن موجودة
    $status = firebase_get("server/status");
    if ($status === null) {
        firebase_put("server/status", "on");
    }

    echo "<h2 style='color:green'>تم إنشاء حساب المالك بنجاح!</h2>";
    echo "<p><strong>اسم المستخدم:</strong> $username</p>";
    echo "<p><strong>كلمة السر (غير مشفرة):</strong> $password</p>";
    echo "<p style='color:red'>احذف هذا الملف فورًا بعد الاستخدام!</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب المالك</title>
    <style>
        body {
            font-family: Arial;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 350px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>إنشاء حساب المالك</h2>
        <form method="post">
            <input type="text" name="username" placeholder="اسم المستخدم" required>
            <input type="password" name="password" placeholder="كلمة السر" required>
            <button type="submit">إنشاء الحساب</button>
        </form>
        <p style="font-size:12px; color:#666; margin-top:20px;">
            بعد الإنشاء: <strong>احذف هذا الملف (create_owner.php)</strong> من السيرفر فورًا!
        </p>
    </div>
</body>

</html>