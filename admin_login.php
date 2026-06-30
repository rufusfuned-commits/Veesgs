<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  if ($username === 'rufus' && $password === 'sheffield') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'rufus';
    $_SESSION['username'] = 'rufus';
    $_SESSION['account_key'] = 'rufus';
    $_SESSION['logged_in'] = true;
    header('Location: /admin.php');
    exit;
  } else {
    $error = 'Invalid username or password';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: Inter, Arial, sans-serif;
      background: #080d14;
      color: #eaf0fd;
    }
    .login-box {
      width: min(400px, 90vw);
      padding: 30px;
      border-radius: 20px;
      background: rgba(24,30,41,.94);
      border: 1px solid rgba(255,255,255,.13);
      box-shadow: 0 18px 60px rgba(0,0,0,.38);
      text-align: center;
    }
    h1 {
      font-size: 1.8rem;
      margin-bottom: 20px;
      letter-spacing: -.06em;
    }
    input {
      width: 100%;
      margin-top: 15px;
      padding: 14px 15px;
      border-radius: 15px;
      border: 1.5px solid rgba(160,195,255,.32);
      background: rgba(10,15,24,.72);
      color: #eef3ff;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 850;
      outline: none;
      text-align: center;
    }
    input:focus {
      border-color: rgba(160,195,255,.82);
    }
    button {
      width: 100%;
      margin-top: 20px;
      border: none;
      border-radius: 14px;
      padding: 13px 16px;
      font-weight: 950;
      cursor: pointer;
      font-family: inherit;
      background: linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);
      color: #111;
    }
    .error {
      margin-top: 15px;
      color: #ffd0d0;
      font-weight: 850;
      min-height: 22px;
    }
    .back-link {
      margin-top: 20px;
      display: block;
      color: rgba(234,240,253,.68);
      text-decoration: none;
      font-weight: 800;
    }
    .back-link:hover {
      color: #eaf0fd;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h1>Admin Login</h1>
    <form method="POST">
      <input name="username" type="text" placeholder="Username" required>
      <input name="password" type="password" placeholder="Password" required>
      <button type="submit">Login</button>
    </form>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <a href="/" class="back-link">← Back to Home</a>
  </div>
</body>
</html>
