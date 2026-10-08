<?php
// ============================================================
//  聊天接口 —— PHP + TXT 存储
//  功能：
//   1. 提交账号密码验证身份（复用 users.txt）
//   2. 发送消息（存储到 chat.txt，格式：时间|账号|消息内容）
//   3. 获取聊天记录（返回 JSON 或 HTML 渲染）
//  文件说明：
//   - users.txt : 用户账号|密码哈希（由登录接口或本文件初始化）
//   - chat.txt  : 聊天记录，每行 "时间戳|账号|消息内容"
// ============================================================

// ---------- 配置 ----------
define('USER_FILE', __DIR__ . '/users.txt');
define('CHAT_FILE', __DIR__ . '/chat.txt');
define('MAX_MESSAGES', 200);       // 最多保留/显示最近 200 条
define('MAX_MSG_LENGTH', 500);     // 单条消息最大长度

// ---------- 初始化用户文件（若不存在） ----------
function initUserFile() {
    if (!file_exists(USER_FILE)) {
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

// ---------- 初始化聊天文件（若不存在） ----------
function initChatFile() {
    if (!file_exists(CHAT_FILE)) {
        file_put_contents(CHAT_FILE, '', LOCK_EX);
    }
}
initChatFile();

// ---------- 读取用户（账号 => 密码哈希） ----------
function loadUsers() {
    $users = [];
    if (!file_exists(USER_FILE)) return $users;
    $lines = file(USER_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('|', $line, 2);
        if (count($parts) === 2) {
            $username = trim($parts[0]);
            $hash = trim($parts[1]);
            if ($username !== '') $users[$username] = $hash;
        }
    }
    return $users;
}

// ---------- 验证账号密码 ----------
function verifyUser($username, $password) {
    $users = loadUsers();
    if (!array_key_exists($username, $users)) return false;
    return password_verify($password, $users[$username]);
}

// ---------- 读取聊天记录（返回数组） ----------
function loadMessages($limit = MAX_MESSAGES) {
    $messages = [];
    if (!file_exists(CHAT_FILE)) return $messages;
    $lines = file(CHAT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // 只取最近 $limit 条
    if (count($lines) > $limit) {
        $lines = array_slice($lines, -$limit);
    }
    foreach ($lines as $line) {
        $parts = explode('|', $line, 3);
        if (count($parts) === 3) {
            $messages[] = [
                'time'    => (int)$parts[0],
                'user'    => $parts[1],
                'content' => $parts[2],
            ];
        }
    }
    return $messages;
}

// ---------- 追加一条聊天记录 ----------
function addMessage($username, $content) {
    $content = trim($content);
    if ($content === '') return false;
    // 限制长度，防止文件过大
    if (mb_strlen($content, 'UTF-8') > MAX_MSG_LENGTH) {
        $content = mb_substr($content, 0, MAX_MSG_LENGTH, 'UTF-8') . '…';
    }
    $line = time() . '|' . $username . '|' . str_replace(["\r", "\n", "|"], [' ', ' ', ' '], $content);
    // 追加写入，使用 LOCK_EX 防止并发冲突
    file_put_contents(CHAT_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    // 若超过 MAX_MESSAGES 条，进行裁剪（保留最新 MAX_MESSAGES 条）
    $all = file(CHAT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($all) > MAX_MESSAGES * 2) { // 写入量不大时不必频繁裁剪
        $all = array_slice($all, -MAX_MESSAGES);
        file_put_contents(CHAT_FILE, implode(PHP_EOL, $all) . PHP_EOL, LOCK_EX);
    }
    return true;
}

// ============================================================
//  接口处理
// ============================================================
header('Content-Type: text/html; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';
$username = isset($_REQUEST['username']) ? trim($_REQUEST['username']) : '';
$password = isset($_REQUEST['password']) ? trim($_REQUEST['password']) : '';
$content  = isset($_POST['content']) ? $_POST['content'] : '';

$message = '';
$messageType = '';
$isLoggedIn = false;

// ---------- 验证身份（所有操作都需验证） ----------
if ($username !== '' && $password !== '') {
    if (verifyUser($username, $password)) {
        $isLoggedIn = true;
    } else {
        $message = '账号或密码错误，无法操作。';
        $messageType = 'error';
    }
} else {
    // 未提交账号密码时，不显示错误，只提示登录
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $action === 'send') {
        $message = '请提供账号和密码进行验证。';
        $messageType = 'error';
    }
}

// ---------- 发送消息 ----------
if ($action === 'send' && $isLoggedIn) {
    if (addMessage($username, $content)) {
        // 发送成功，重定向避免刷新重复提交（可选）
        header('Location: ' . $_SERVER['PHP_SELF'] . '?username=' . urlencode($username) . '&password=' . urlencode($password) . '&sent=1');
        exit;
    } else {
        $message = '消息不能为空或发送失败。';
        $messageType = 'error';
    }
}

// 提示发送成功（通过 URL 参数）
if (isset($_GET['sent']) && $_GET['sent'] == 1) {
    $message = '消息已发送。';
    $messageType = 'success';
}

// ---------- 读取聊天记录（所有访客可见，但发送需登录） ----------
$messages = loadMessages();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>聊天接口 · TXT 存储</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
    body {
      background: linear-gradient(145deg, #f2f7fb 0%, #dce9f2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.2rem;
    }
    .chat-container {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(8px);
      border-radius: 2rem;
      box-shadow: 0 25px 40px -12px rgba(0, 20, 40, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
      width: 100%;
      max-width: 680px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      max-height: 90vh;
    }
    .chat-header {
      padding: 1.5rem 2rem 0.8rem 2rem;
      border-bottom: 1px solid #c6dae8;
    }
    .chat-header h1 {
      font-size: 1.8rem;
      font-weight: 600;
      color: #0b2b44;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .chat-header h1 span {
      background: #1e5b8a;
      color: white;
      font-size: 1rem;
      padding: 0.2rem 0.7rem;
      border-radius: 40px;
      font-weight: 500;
    }
    .subhead {
      color: #446a82;
      font-size: 0.9rem;
      margin-top: 0.3rem;
    }
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 1.2rem 2rem;
      background: #f9fcff;
      min-height: 300px;
      max-height: 480px;
    }
    .msg {
      margin-bottom: 1rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
    }
    .msg.self {
      align-items: flex-end;
    }
    .msg-bubble {
      max-width: 80%;
      background: #ffffff;
      border-radius: 18px 18px 18px 4px;
      padding: 0.7rem 1.1rem;
      box-shadow: 0 4px 10px -4px rgba(0, 20, 40, 0.1);
      border: 1px solid #d6e5f0;
      word-break: break-word;
    }
    .msg.self .msg-bubble {
      background: #1e5b8a;
      color: white;
      border: none;
      border-radius: 18px 18px 4px 18px;
    }
    .msg-meta {
      font-size: 0.7rem;
      color: #6d8ea8;
      margin-bottom: 0.3rem;
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    .msg.self .msg-meta {
      flex-direction: row-reverse;
    }
    .msg-user {
      font-weight: 600;
      color: #1b4b6c;
    }
    .msg.self .msg-user {
      color: #a0c8e8;
    }
    .msg-time {
      font-size: 0.65rem;
      opacity: 0.8;
    }
    .empty-tip {
      text-align: center;
      color: #8aaec8;
      padding: 2rem 0;
      font-style: italic;
    }
    .chat-form {
      padding: 1.2rem 2rem 1.8rem 2rem;
      background: #ffffffd0;
      border-top: 1px solid #c6dae8;
    }
    .form-row {
      display: flex;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
      flex-wrap: wrap;
    }
    .form-row .input-field {
      flex: 1;
      min-width: 140px;
    }
    .input-field {
      padding: 0.75rem 1rem;
      font-size: 0.9rem;
      border: 2px solid #cbdde9;
      border-radius: 14px;
      background: white;
      transition: all 0.2s;
      outline: none;
      color: #0a1e2b;
      width: 100%;
    }
    .input-field:focus {
      border-color: #1e5b8a;
      box-shadow: 0 0 0 3px rgba(30, 91, 138, 0.12);
    }
    .input-field::placeholder {
      color: #9bb7cc;
    }
    .send-btn {
      background: #1e5b8a;
      border: none;
      padding: 0.75rem 1.8rem;
      font-size: 1rem;
      font-weight: 600;
      color: white;
      border-radius: 40px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 6px 14px -6px rgba(20, 70, 110, 0.3);
      white-space: nowrap;
    }
    .send-btn:hover {
      background: #144a72;
      transform: translateY(-1px);
    }
    .send-btn:active {
      transform: translateY(1px);
    }
    .message-box {
      margin: 0 0 1rem 0;
      padding: 0.7rem 1rem;
      border-radius: 14px;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      border: 1px solid transparent;
    }
    .message-box.success {
      background: #e2f3e6;
      color: #0d5530;
      border-color: #b1dfc2;
    }
    .message-box.error {
      background: #ffe9e9;
      color: #b33b3b;
      border-color: #fbc2c2;
    }
    .file-info {
      font-size: 0.7rem;
      text-align: center;
      color: #698ea8;
      margin-top: 0.8rem;
    }
    .file-info code {
      background: #e7f0f8;
      padding: 0.15rem 0.5rem;
      border-radius: 20px;
      font-family: monospace;
    }
    hr {
      border: none;
      border-top: 1px solid #c6dae8;
      margin: 0.5rem 0;
      opacity: 0.5;
    }
  </style>
</head>
<body>
<div class="chat-container">
  <div class="chat-header">
    <h1>💬 聊天接口 <span>TXT</span></h1>
    <div class="subhead">发送消息需验证账号密码 · 账号即昵称</div>
  </div>

  <div class="chat-messages" id="chatMessages">
    <?php if (empty($messages)): ?>
      <div class="empty-tip">✨ 还没有消息，来说第一句吧～</div>
    <?php else: ?>
      <?php foreach ($messages as $msg): ?>
        <?php
          $isSelf = ($msg['user'] === $username && $isLoggedIn);
          $timeStr = date('H:i', $msg['time']);
        ?>
        <div class="msg <?php echo $isSelf ? 'self' : ''; ?>">
          <div class="msg-meta">
            <span class="msg-user"><?php echo htmlspecialchars($msg['user']); ?></span>
            <span class="msg-time"><?php echo $timeStr; ?></span>
          </div>
          <div class="msg-bubble">
            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="chat-form">
    <?php if (!empty($message)): ?>
      <div class="message-box <?php echo $messageType; ?>">
        <span><?php echo $messageType === 'success' ? '✓' : '!'; ?></span>
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="action" value="send">
      <div class="form-row">
        <input type="text" name="username" class="input-field" placeholder="账号" value="<?php echo htmlspecialchars($username); ?>" autocomplete="username" required>
        <input type="password" name="password" class="input-field" placeholder="密码" value="<?php echo htmlspecialchars($password); ?>" autocomplete="current-password" required>
      </div>
      <div class="form-row">
        <input type="text" name="content" class="input-field" placeholder="输入消息…" autocomplete="off" required>
        <button type="submit" class="send-btn"><span>➤</span> 发送</button>
      </div>
    </form>

    <hr>
    <div class="file-info">
      📁 存储文件：<code>users.txt</code> · <code>chat.txt</code> &nbsp;|&nbsp;
      🧪 演示账号：<code>admin</code>/<code>admin123</code>
    </div>
  </div>
</div>

<script>
  // 自动滚动到最新消息
  const chatBox = document.getElementById('chatMessages');
  if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight;
  }
</script>
</body>
</html>