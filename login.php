<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Form</title>
     <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
        }
        .login-container {
            width: 300px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            text-align: center;
        }
        .login-container img {
            max-width: 100%;
            height: 80px;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .input-group {
            margin: 15px 0;
            text-align: left;
        }
        label {
            display: block;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            width: 100%;
            padding: 10px;
            background-color: #fff;
            color: #333;
            border: 2px solid #333;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
            transition: background-color 0.3s, color 0.3s;
        }
        .btn:hover {
            background-color: #333;
            color: #fff;
        }
        .forgot-link {
            display: block;
            margin-top: 15px;
            color: #666;
            text-decoration: none;
            font-size: 13px;
        }
        .forgot-link:hover {
            color: #333;
            text-decoration: underline;
        }
        .error, .success {
            margin-top: 10px;
        }
        .error {
            color: #d9534f;
        }
        .success {
            color: #5cb85c;
        }
    </style>
</head>
<body>
<div class="login-container">
    <img src="https://vantageafricaleaders.com/admin/assets/img/logo.png" alt="Company Logo">
    <h2>Login</h2>
    <form action="create_session.php" method="POST">
        <div class="input-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="email" required>
        </div>
        <div class="input-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Login</button>
    </form>
    <a href="forgot_password.php" class="forgot-link">Forgot your password?</a>
</div>
</body>
</html>