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

<div id="loading-splash" class="fixed inset-0 flex flex-col items-center justify-center bg-white shadow-2xl">
    <div class="flex flex-col items-center">
        <!-- Logo Section -->
        <div class="relative w-32 h-32 flex items-center justify-center mb-8">
            <div id="prime-logo-container" class="w-full h-full flex items-center justify-center relative z-10">
                <img src="../../public/assets/img/favIcon.png"
                    alt="Prime Concept"
                    class="w-24 h-24 object-contain animate-logo-pulse drop-shadow-xl">
            </div>
        </div>

        <!-- Text Section: SYNCHRONIZING PRIME -->
        <div class="text-center mb-4">
            <h2 class="text-xl font-medium text-slate-800 uppercase tracking-[0.15em] antialiased">
                <span id="loading-text" class="inline-block">Synchronizing Prime</span>
            </h2>
        </div>

        <!-- Progress Bar Section -->
        <div class="w-48 h-1.5 bg-slate-100 rounded-full overflow-hidden shadow-inner">
            <div class="h-full bg-red-600 animate-loading-bar rounded-full shadow-lg shadow-red-500/20"></div>
        </div>
    </div>
</div>

<?php
$role = $_SESSION['role'] ?? 'staff';
$roleMsg = "Readying Account Modules";
if ($role === 'admin') $roleMsg = "Initializing Admin Terminal";
elseif ($role === 'showroom') $roleMsg = "Syncing Showroom Database";
?>

<script>
    (function() {
        const text = document.getElementById('loading-text');
        const splash = document.getElementById('loading-splash');
        
        // Sequence for professional feel
        const statusMessages = ["Synchronizing Prime", "<?= $roleMsg ?>", "Optimizing Modules", "Finalizing Interface"];
        let index = 0;

        function cycleText() {
            if (!text) return;
            
            // Subtle Fade effect for text transitions
            text.style.opacity = '0';
            setTimeout(() => {
                index = (index + 1) % statusMessages.length;
                text.innerText = statusMessages[index];
                text.style.opacity = '1';
            }, 600);
        }

        // Cycle slower for premium feel
        const interval = setInterval(cycleText, 2500);
        
        // Text initial style
        if(text) {
            text.style.transition = 'opacity 0.6s ease-in-out';
            text.innerText = statusMessages[0];
            text.style.opacity = '1';
        }

        window.addEventListener('load', () => {
            // Allow user to see the splash for a short moment
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