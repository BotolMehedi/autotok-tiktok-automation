<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * { font-family: 'Poppins', sans-serif; scroll-behavior: smooth; }
        body { background: linear-gradient(135deg, #010101 0%, #1a0a1a 100%); color: #fff; }
        .gradient-text { background: linear-gradient(135deg, #EE1D52, #69C9D0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .bar { height:2px; width:24px; background:white; transition:all 0.3s ease; border-radius:1px; }
        .open .bar:nth-child(1){transform:rotate(45deg) translateY(7px);}
        .open .bar:nth-child(2){opacity:0;}
        .open .bar:nth-child(3){transform:rotate(-45deg) translateY(-7px);}
    </style>
</head>
<body class="min-h-screen flex flex-col">

    
    <header class="w-full bg-black/40 backdrop-blur-md border-b border-gray-800 fixed top-0 z-50">
        <div class="max-w-7xl mx-auto flex items-center justify-between px-6 py-4">
            <a href="/" class="text-2xl font-bold gradient-text"><?php echo APP_NAME; ?></a>
            <nav class="hidden md:flex space-x-8 text-sm font-medium">
                <a href="/" class="text-gray-300 hover:text-white">Home</a>
                <a href="/terms.php" class="text-gray-300 hover:text-white">Terms</a>
                <a href="/privacy.php" class="text-gray-300 hover:text-white">Privacy</a>
                <a href="mailto:hello@nexatechstudio.com" class="text-gray-300 hover:text-white">Contact</a>
            </nav>
            <button id="menuToggle" class="flex flex-col md:hidden space-y-1">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
        </div>

        
        <div id="mobileMenu" class="md:hidden hidden bg-[#0a0a0a] border-t border-gray-800">
            <nav class="flex flex-col items-center py-4 space-y-3 text-sm">
                <a href="/" class="text-gray-300 hover:text-white">Home</a>
                <a href="/terms.php" class="text-gray-300 hover:text-white">Terms</a>
                <a href="/privacy.php" class="text-gray-300 hover:text-white">Privacy</a>
                <a href="mailto:hello@nexatechstudio.com" class="text-gray-300 hover:text-white">Contact</a>
            </nav>
        </div>
    </header>

    
    <main class="flex-grow pt-28 pb-16 px-6">
        <div class="max-w-4xl mx-auto bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl p-8 border border-gray-700">
            <h1 class="text-4xl font-bold mb-6 gradient-text text-center">Terms of Service</h1>
            <p class="text-gray-400 mb-6 text-sm">Last updated: November 5, 2025</p>

            <div class="space-y-6 text-gray-300 text-base leading-relaxed">
                <p>Welcome to <?php echo APP_NAME; ?>. By accessing or using our website and services, you agree to comply with and be bound by these Terms of Service.</p>

                <h2 class="text-2xl font-semibold mt-6">1. Use of the Service</h2>
                <p>Our platform provides automated TikTok content scheduling and publishing. You agree to use the service responsibly and in accordance with TikTok’s policies.</p>

                <h2 class="text-2xl font-semibold mt-6">2. Accounts & Security</h2>
                <p>You are responsible for maintaining the confidentiality of your TikTok account authorization. We never store your password or sensitive login credentials.</p>

                <h2 class="text-2xl font-semibold mt-6">3. Acceptable Use</h2>
                <p>You may not use <?php echo APP_NAME; ?> for spam, abusive, or unauthorized activities that violate TikTok’s terms or any applicable laws.</p>

                <h2 class="text-2xl font-semibold mt-6">4. Limitation of Liability</h2>
                <p><?php echo APP_NAME; ?> is provided “as-is.” We are not liable for any damages resulting from service interruptions, data loss, or TikTok API limitations.</p>

                <h2 class="text-2xl font-semibold mt-6">5. Modifications</h2>
                <p>We may revise these terms at any time. Continued use of the service means you accept any new or modified terms.</p>

                <h2 class="text-2xl font-semibold mt-6">6. Contact</h2>
                <p>If you have any questions about these Terms, please contact us at <a href="mailto:<?php echo APP_EMAIL; ?>" class="text-pink-500 hover:underline"><?php echo APP_EMAIL; ?></a>.</p>
            </div>
        </div>
    </main>

    
    <footer class="text-center py-6 text-gray-500 text-sm border-t border-gray-800">
        <p>&copy; 2025 <?php echo APP_NAME; ?>. All rights reserved.</p>
    </footer>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        menuToggle.addEventListener('click', () => {
            menuToggle.classList.toggle('open');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>
