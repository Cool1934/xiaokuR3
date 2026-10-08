
<?php
// 开启会话，用于存储登录状态
session_start();

// 定义存储用户数据的TXT文件路径
define('USER_FILE', __DIR__ . '/users.txt');

// 初始化变量
$error = '';
$username = '';

// 如果已登录则跳转到欢迎页
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: welcome.php');
    exit;
}

/**
 * 从TXT文件读取所有用户
 * 文件格式：每行一个用户，格式为 "用户名:密码哈希"
 * @return array 用户名 => 密码哈希 的关联数组
 */
function loadUsers() {
    $users = [];
    
    // 检查文件是否存在
    if (!file_exists(USER_FILE)) {
        return $users;
    }
    
    // 读取文件内容
    $lines = file(USER_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if ($lines === false) {
        return $users;
    }
    
    foreach ($lines as $line) {
        // 跳过注释行（以 # 开头）
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // 分割用户名和密码哈希（只分割第一个冒号）
        $parts = explode(':', $line, 2);
        
        if (count($parts) === 2) {
            $user = trim($parts[0]);
            $hash = trim($parts[1]);
            
            if (!empty($user) && !empty($hash)) {
                $users[$user] = $hash;
            }
        }
    }
    
    return $users;
}

/**
 * 添加新用户到TXT文件
 * @param string $username 用户名
 * @param string $password 明文密码
 * @return bool 是否成功
 */
function addUser($username, $password) {
    // 生成密码哈希
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 准备要写入的行
    $line = $username . ':' . $hash . PHP_EOL;
    
    // 追加写入文件（使用LOCK_EX防止并发写入冲突）
    $result = file_put_contents(USER_FILE, $line, FILE_APPEND | LOCK_EX);
    
    return $result !== false;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 验证输入
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // 从TXT文件加载用户数据
        $users = loadUsers();
        
        // 检查用户名是否存在
        if (array_key_exists($username, $users)) {
            // 验证密码
            if (password_verify($password, $users[$username])) {
                // 登录成功
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                session_regenerate_id(true);
                header('Location: welcome.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } else {
            $error = '用户名或密码错误';
        }
    }
}

// 首次运行时初始化文件（如果不存在）
if (!file_exists(USER_FILE)) {
    // 创建默认管理员账号：admin / admin123
    addUser('admin', 'admin123');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - TXT存储版</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: #fff;
            padding: 40px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #4a90e2;
            outline: none;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        button {
            width: 100%;
            padding: 14px;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #357abd;
        }
        .demo-info {
            margin-top: 20px;
            padding: 12px;
            background: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 4px;
            font-size: 13px;
            color: #004085;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>登录</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" 
                       value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" 
                       placeholder="请输入用户名" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" 
                       placeholder="请输入密码" autocomplete="current-password" required>
            </div>
            <button type="submit">登录</button>
        </form>

        <div class="demo-info">
            <strong>演示账号:</strong><br>
            用户名: admin / 密码: admin123
        </div>
    </div>
</body>
</html>
