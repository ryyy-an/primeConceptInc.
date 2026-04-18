<?php
if (!isset($_SESSION['login_success_splash']) || $_SESSION['login_success_splash'] !== true) {
    return;
}
// Clear flag so it only shows once
$_SESSION['login_success_splash'] = false;
?>
<style>
    @keyframes logo-pulse {
        0%, 100% { transform: scale(1); filter: brightness(1); }
        50% { transform: scale(1.1); filter: brightness(1.2); }
    }

    @keyframes animate-loading-bar {
        0% { transform: translateX(-100%); }
        50% { transform: translateX(0); }
        100% { transform: translateX(100%); }
    }

    .animate-logo-pulse {
        animation: logo-pulse 2s infinite ease-in-out;
    }

    .animate-loading-bar {
        animation: animate-loading-bar 1.5s infinite linear;
    }

    #loading-splash {
        background-color: #ffffff;
        z-index: 999999;
        transition: opacity 0.7s ease-in-out;
    }
</style>

<div id="loading-splash" class="fixed inset-0 flex flex-col items-center justify-center">
    <div class="flex flex-col items-center translate-y-[15%]">
        <div class="relative w-32 h-32 flex items-center justify-center">
            <!-- Official Prime Concept Logo -->
            <div id="prime-logo-container" class="w-full h-full flex items-center justify-center relative z-10">
                <img src="../../public/assets/img/favIcon.png"
                    alt="Prime Concept"
                    class="w-20 h-20 object-contain animate-logo-pulse brightness-110 drop-shadow-2xl">
            </div>

            <!-- Pulse Ring -->
            <div class="absolute inset-0 rounded-full border-4 border-red-500/10 animate-ping"></div>
        </div>

        <div class="mt-4 text-center min-w-[300px]">
            <h2 class="text-xl font-semibold text-gray-800 uppercase overflow-hidden h-8">
                <span id="loading-text" class="inline-block transition-transform duration-500 translate-y-full">Synchronizing...</span>
            </h2>
            <div class="w-40 h-1 bg-gray-100 rounded-full mt-2 mx-auto overflow-hidden">
                <div class="h-full bg-red-600 animate-loading-bar"></div>
            </div>
        </div>
    </div>
</div>

<?php
$role = $_SESSION['role'] ?? 'staff';
if ($role === 'admin') {
    $roleMsg = "Initializing Admin";
} elseif ($role === 'showroom') {
    $roleMsg = "Preparing Showroom Catalog";
} else {
    $roleMsg = "Readying Warehouse Inventory";
}
?>

<script>
    (function() {
        const text = document.getElementById('loading-text');
        const splash = document.getElementById('loading-splash');
        
        // Match the sequence requested: Synchronizing -> Preparing Role
        const statusMessages = ["Synchronizing Prime", "<?= $roleMsg ?>", "Optimizing Inventory", "Finalizing Modules"];
        let index = 0;

        function cycleText() {
            if (!text) return;
            
            // Slide down existing text
            text.style.transform = 'translateY(100%)';
            
            setTimeout(() => {
                index = (index + 1) % statusMessages.length;
                text.innerText = statusMessages[index];
                // Slide up new text
                text.style.transform = 'translateY(0)';
            }, 500);
        }

        // Start cycling every 2 seconds for visibility
        const interval = setInterval(cycleText, 2000);
        
        // Set initial text and state
        if(text) {
            text.innerText = statusMessages[0];
            setTimeout(() => { text.style.transform = 'translateY(0)'; }, 50);
        }

        window.addEventListener('load', () => {
            // Keep it visible for at least 3 seconds so they can see the sequence
            setTimeout(() => {
                clearInterval(interval);
                if (splash) {
                    splash.style.opacity = '0';
                    splash.style.pointerEvents = 'none';
                    setTimeout(() => { splash.style.display = 'none'; }, 800);
                }
            }, 3500);
        });
    })();
</script>