<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - <?php echo APP_NAME; ?></title>
    
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
            <h1 class="text-4xl font-bold mb-6 gradient-text text-center">Privacy Policy</h1>
            <p class="text-gray-400 mb-6 text-sm">Last updated: November 5, 2025</p>

            <div class="space-y-6 text-gray-300 text-base leading-relaxed">
                <p>At <?php echo APP_NAME; ?>, we respect your privacy and are committed to protecting your personal data. This policy explains how we collect, use, and safeguard your information.</p>

                <h2 class="text-2xl font-semibold mt-6">1. Information We Collect</h2>
                <p>We collect minimal information necessary to provide our services, such as TikTok user ID and access tokens (via TikTok OAuth). We never store passwords or sensitive login data.</p>

                <h2 class="text-2xl font-semibold mt-6">2. How We Use Your Information</h2>
                <p>We use your TikTok data strictly to enable features like posting videos, managing captions, and scheduling uploads. We do not sell or share your data with third parties.</p>

                <h2 class="text-2xl font-semibold mt-6">3. Data Storage & Security</h2>
                <p>All data is securely stored with encryption and industry-standard security practices. Access is restricted to authorized personnel only.</p>

                <h2 class="text-2xl font-semibold mt-6">4. Third-Party Services</h2>
                <p>We rely on TikTok’s official API for all authentication and publishing functions. Please review TikTok’s privacy policy for additional details.</p>

                <h2 class="text-2xl font-semibold mt-6">5. Your Rights</h2>
                <p>You can revoke our app’s access to your TikTok account at any time from your TikTok settings. You may also contact us to request data deletion.</p>

                <h2 class="text-2xl font-semibold mt-6">6. Updates to This Policy</h2>
                <p>We may update this policy periodically. All updates will be reflected here with a revised “Last updated” date.</p>

                <h2 class="text-2xl font-semibold mt-6">7. Contact</h2>
                <p>For questions or concerns about this Privacy Policy, email us at <a href="mailto:<?php echo APP_EMAIL; ?>" class="text-pink-500 hover:underline"><?php echo APP_EMAIL; ?></a>.</p>
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
