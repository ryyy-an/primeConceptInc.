<?php
http_response_code(404);

// Dynamically fetch the logo from the filesystem, regardless of the URL or what folder the project is named
$imgPath = __DIR__ . '/assets/img/primeLogo.ico';
$imgBase64 = '';
if (file_exists($imgPath)) {
    $imgBase64 = 'data:image/x-icon;base64,' . base64_encode(file_get_contents($imgPath));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - Prime-In-Sync</title>
    <link rel="icon" type="image/x-icon" href="assets/img/primeLogo.ico">
    <!-- Basic Internal Styles to ensure it NEVER breaks -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
        
        body {
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f9fafb;
            font-family: 'Outfit', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid #f3f4f6;
            text-align: center;
            max-width: 300px;
            width: 100%;
        }

        .logo {
            width: 80px;
            height: auto;
            margin: 0 auto 24px;
            display: block;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 900;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }

        p {
            margin: 0 0 24px;
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.6;
        }

        button {
            width: 100%;
            background-color: #dc2626;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover {
            background-color: #111827;
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(0);
        }

        .footer {
            margin-top: 24px;
            font-size: 8px;
            font-weight: 700;
            color: #e5e7eb;
            text-transform: uppercase;
            letter-spacing: 0.3em;
        }
    </style>
</head>
<body>
    <div class="card">
        <!-- Logo with forced small size & dynamic base64 generation -->
        <?php if ($imgBase64): ?>
        <img src="<?= $imgBase64 ?>" alt="Prime Logo" class="logo">
        <?php endif; ?>
        
        <h2>404 - Not Found</h2>
        <p>The page you're looking for was moved or doesn't exist.</p>
        
        <button onclick="window.history.back()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Go Back
        </button>

        <div class="footer">Prime Concept</div>
    </div>
</body>
</html>
