<?php
require_once '../src/include/config.php';
require_once '../src/auth/login.view.auth.php';
?>

<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../src/output.css">
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->

</head>

<body class="h-screen bg-gray-100">

    <div class="relative w-full h-full flex items-center justify-center overflow-hidden">
        <!-- Background Layer -->
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat blur-sm opacity-50 z-0"
            style="background-image: url('assets/img/primeBuilding.jpg');">
        </div>

        <!-- Foreground Content -->
        <div class="relative z-10">
            <div class="w-112.5 bg-white border border-gray-300 rounded-xl shadow-md px-6 py-4">

                <!-- Logo + Header the Login Card -->
                <div class="flex flex-col items-center mb-4">
                    <div class="w-24 h-24 overflow-hidden ">
                        <img src="assets/img/primeLogo.ico" alt="Prime Concept Logo" class="object-cover w-full h-full" />
                    </div>
                    <h1 class="mt-4 text-center text-gray-700 text-base font-medium">
                        Login to access your inventory management dashboard
                    </h1>
                </div>

                <!-- Login Form -->
                <form id="loginForm" action="../src/auth/login.auth.php" method="POST" class="flex flex-col gap-4">

                    <div>
                        <p class="text-sm text-red-500"><?php echo get_error_messages(); ?></p>
                    </div>

                    <div>
                        <label for="username" class="block text-sm text-gray-800 font-semibold">Username</label>
                        <input type="text" name="username" id="username"
                            class="text-black w-full h-11 px-4 bg-gray-100 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-black"
                            placeholder="Enter your username" required />
                    </div>

                    <div class="relative">
                        <label for="password" class="block text-sm text-gray-800 font-semibold">Password</label>
                        <div class="relative">
                            <input type="password" name="password" id="password"
                                class="w-full h-11 pl-4 pr-12 bg-gray-100 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-black"
                                placeholder="Enter your password" required />

                            <button type="button" onclick="togglePassword()"
                                class="cursor-pointer absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-black transition-colors focus:outline-none">
                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.67 8.5 7.652 6 12 6c4.348 0 8.332 2.5 9.964 5.678.15.287.15.629 0 .916C20.332 15.5 16.348 18 12 18c-4.348 0-8.332-2.5-9.964-5.678Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="loginBtn"
                        class="w-full h-11 mt-2 text-white bg-black rounded-md hover:bg-gray-800 transition font-semibold flex items-center justify-center gap-2">
                        Login
                    </button>
                </form>

                <style>
                    @keyframes spin-pure { to { transform: rotate(360deg); } }
                    .pure-spinner {
                        width: 18px;
                        height: 18px;
                        border: 3px solid rgba(255,255,255,0.3);
                        border-top-color: #fff;
                        border-radius: 50%;
                        animation: spin-pure 0.8s linear infinite;
                    }
                </style>

                <script>
                    function togglePassword() {
                        const passwordInput = document.getElementById('password');
                        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                        passwordInput.setAttribute('type', type);
                    }

                    document.getElementById('loginForm').addEventListener('submit', function(e) {
                        const btn = document.getElementById('loginBtn');
                        if (btn.disabled) return; // Prevent double click

                        e.preventDefault();
                        
                        // Start Loading
                        btn.disabled = true;
                        btn.classList.add('opacity-80', 'cursor-not-allowed', 'bg-gray-800');
                        btn.innerHTML = `
                            <div class="pure-spinner"></div>
                            <span class="tracking-widest uppercase text-[11px]">Logging in...</span>
                        `;

                        // Wait for 1.2 seconds for dramatic effect before actual submit
                        setTimeout(() => {
                            this.submit();
                        }, 1200);
                    });
                </script>
            </div>
        </div>

    </div>

</body>

</html>