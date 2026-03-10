<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied | BookMyHall</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f5f7fd;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #1f1d2b;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 60px 48px;
            text-align: center;
            box-shadow: 0 18px 40px rgba(112,144,176,.12);
            max-width: 440px;
            width: 90%;
        }
        .icon { font-size: 64px; color: #e74c3c; margin-bottom: 24px; }
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
        p  { color: #6c6f83; margin-bottom: 32px; line-height: 1.6; }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #4d5dfb;
            color: #fff;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }
        .btn:hover { background: #3a4be0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fa-solid fa-lock"></i></div>
        <h1>Access Denied</h1>
        <p>You don't have permission to view this page. Please log in with an authorised account.</p>
        <a href="/BookMyHall/index.php" class="btn">Go to Home</a>
    </div>
</body>
</html>
