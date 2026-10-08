<?php
// ============================================================
//  账号密码登录接口 —— 使用 TXT 文件存储用户数据
//  文件格式：每行 "账号|密码哈希" （密码使用 password_hash 加密）
//  默认预置几个演示账号，首次运行会自动创建 users.txt
// ============================================================

// 配置 TXT 文件路径（建议放在 web 目录之外，或做好权限控制）
define('USER_FILE', __DIR__ . '/users.txt');

// ---------- 1. 初始化：如果文件不存在，创建并写入演示账号 ----------
function initUserFile() {
    if (!file_exists(USER_FILE)) {
        // 演示账号：admin/admin123、user/password123、test/test2024
        $users = [
            'admin' => password_hash('admin123', PASSWORD_DEFAULT),
            'user'  => password_hash('password123', PASSWORD_DEFAULT),
            'test'  => password_hash('test2024', PASSWORD_DEFAULT),
        ];
        $lines = [];
        foreach ($users as $name => $hash) {
            $lines[] = $name . '|' . $hash;
        }
        file_put_contents(USER_FILE, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }
}
initUserFile();

// ---------- 2. 读取所有用户（返回 [账号 => 密码哈希] 数组） ----------
function loadUsers() {
    $users = [];
    if (!file_exists(USER_FILE)) {
        return $users;
    }
    $lines = file(USER_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 跳过注释行
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $username = trim($parts[0]);
            $hash = trim($parts[1]);
            if ($username !== '') {
                $users[$username] = $hash;
            }
        }
    }
    return $users;
}

// ---------- 3. 保存用户到 TXT（全量写入，带锁） ----------
function saveUsers($users) {
    $lines = [];
    foreach ($users as $name => $hash) {
        $lines[] = $name . '|' . $hash;
    }
    // 使用 LOCK_EX 防止并发写入冲突
    file_put_contents(USER_FILE, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
}

// ---------- 4. 注册新用户（示例，可选） ----------
function registerUser($username, $password) {
    $username = trim($username);
    if ($username === '' || $password === '') {
        return '账号和密码不能为空';
    }
    $users = loadUsers();
    if (array_key_exists($username, $users)) {
        return '账号已存在';
    }
    $users[$username] = password_hash($password, PASSWORD_DEFAULT);
    saveUsers($users);
    return true;
}

// ---------- 5. 登录验证 ----------
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($username === '' || $password === '') {
        $message = '账号和密码不能为空';
        $messageType = 'error';
    } else {
        $users = loadUsers();
        if (array_key_exists($username, $users) && password_verify($password, $users[$username])) {
            $message = '登录成功！欢迎回来，' . htmlspecialchars($username) . '。';
            $messageType = 'success';
        } else {
            $message = '账号或密码错误，请重试。';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>登录接口 · TXT 存储</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
    body {
      background: linear-gradient(145deg, #f6f9fc 0%, #e6f0f5 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-card {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(8px);
      border-radius: 2rem;
      box-shadow: 0 25px 40px -12px rgba(0, 20, 40, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 420px;
    }
    h1 { font-size: 2rem; font-weight: 600; color: #0b2b44; letter-spacing: -0.02em; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.6rem; }
    h1 span { background: #1e5b8a; color: white; font-size: 1.1rem; padding: 0.2rem 0.7rem; border-radius: 40px; font-weight: 500; }
    .subhead { color: #446a82; margin-bottom: 2rem; font-size: 0.95rem; border-left: 3px solid #1e5b8a; padding-left: 0.75rem; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; font-size: 0.85rem; font-weight: 500; color: #1b4b6c; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .input-field { width: 100%; padding: 0.9rem 1.2rem; font-size: 1rem; border: 2px solid #cbdde9; border-radius: 14px; background: white; transition: all 0.2s; outline: none; color: #0a1e2b; }
    .input-field:focus { border-color: #1e5b8a; box-shadow: 0 0 0 4px rgba(30, 91, 138, 0.15); }
    .login-btn { width: 100%; background: #1e5b8a; border: none; padding: 1rem 1.5rem; font-size: 1.1rem; font-weight: 600; color: white; border-radius: 40px; cursor: pointer; transition: all 0.2s ease; margin-top: 0.75rem; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 8px 16px -6px rgba(20, 70, 110, 0.3); }
    .login-btn:hover { background: #144a72; transform: translateY(-1px); }
    .login-btn:active { transform: translateY(1px); }
    .message-box { margin-top: 1.5rem; padding: 0.9rem 1.2rem; border-radius: 16px; font-size: 0.95rem; display: flex; align-items: center; gap: 0.6rem; border: 1px solid transparent; }
    .message-box.success { background: #e2f3e6; color: #0d5530; border-color: #b1dfc2; }
    .message-box.error { background: #ffe9e9; color: #b33b3b; border-color: #fbc2c2; }
    .file-info { background: #e7f0f8; border-radius: 14px; padding: 0.8rem 1.2rem; margin-top: 1.8rem; font-size: 0.85rem; color: #1a4b6e; border: 1px dashed #99bdd6; }
    .file-info code { background: #ffffffc0; padding: 0.2rem 0.6rem; border-radius: 30px; font-family: monospace; border: 1px solid #b8d2e5; color: #0b2b44; }
    .api-note { font-size: 0.75rem; text-align: center; margin-top: 1.2rem; color: #698ea8; }
    hr { border: none; border-top: 1px solid #c6dae8; margin: 0.25rem 0 1.5rem; opacity: 0.6; }
  </style>
</head>
<body>
  <div class="login-card">
    <h1>🔐 登录接口 <span>TXT</span></h1>
    <div class="subhead">POST 账号 & 密码 · TXT 文件验证</div>

    <form method="POST" action="">
      <div class="form-group">
        <label for="username">账号</label>
        <input type="text" id="username" name="username" class="input-field" placeholder="例如 admin 或 user" autocomplete="username" required>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input type="password" id="password" name="password" class="input-field" placeholder="········" autocomplete="current-password" required>
      </div>
      <button type="submit" class="login-btn"><span>→</span> 登录</button>
    </form>

    <?php if (!empty($message)): ?>
      <div class="message-box <?php echo $messageType; ?>">
        <i><?php echo $messageType === 'success' ? '✓' : '!'; ?></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="file-info">
      📁 用户数据存储于：<code><?php echo htmlspecialchars(basename(USER_FILE)); ?></code><br>
      🧪 演示账号：<code>admin</code> / <code>admin123</code> &nbsp;|&nbsp; <code>user</code> / <code>password123</code>
    </div>

    <hr>
    <div class="api-note">⚡ 密码以哈希形式存储，TXT 每行格式：账号|哈希</div>
  </div>
</body>
</html>