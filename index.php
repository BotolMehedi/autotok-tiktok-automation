<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$csrfToken = generateCSRFToken();
$authUrl = "https://www.tiktok.com/v2/auth/authorize/"
    . "?client_key=" . urlencode(TIKTOK_CLIENT_KEY)
    . "&scope=user.info.basic,video.upload,video.publish"
    . "&response_type=code"
    . "&redirect_uri=" . urlencode(TIKTOK_REDIRECT_URI)
    . "&state=" . $csrfToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <!-- META | SEO -->
<title>AutoTok | TikTok Video Scheduler & Automation Tool</title>
<meta name="title" content="AutoTok | TikTok Video Scheduler & Automation Tool">
<meta name="description" content="AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads">
<meta name="keywords" content="TikTok automation, TikTok scheduler, TikTok post scheduler, schedule TikTok videos, automate TikTok uploads, AutoPosting, TikTok auto uploader, TikTok content automation, TikTok scheduling tool, TikTok post planner, TikTok automation tool, TikTok upload automation, TikTok video planner, TikTok post automation, AutoPosting TikTok, AutoPosting app, AutoPosting tool, AutoTok">
<meta name="robots" content="index, follow">
<meta name="language" content="English">
<meta name="author" content="AutoTok">
<meta name="theme-color" content="black">
<link rel="canonical" href="https://autotok.pages.dev/">


<meta property="og:type" content="website">
<meta property="og:url" content="https://autotok.pages.dev/">
<meta property="og:title" content="AutoTok | TikTok Video Scheduler & Automation Tool">
<meta property="og:description" content="AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads">
<meta property="og:image" content="assets/cover.png">


<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="https://autotok.pages.dev/">
<meta property="twitter:title" content="AutoTok | TikTok Video Scheduler & Automation Tool">
<meta property="twitter:description" content="AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads">
<meta property="twitter:image" content="assets/cover.png">


<link rel="icon" type="image/png" href="assets/favicon.png">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://autotok.pages.dev/#organization",
      "name": "AutoTok",
      "url": "https://autotok.pages.dev/",
      "logo": "https://autotok.pages.dev/assets/favicon.png",
      "sameAs": [
        "https://twitter.com/autotok",
        "https://facebook.com/autotok",
        "https://pinterest.com/autotok"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://autotok.pages.dev/#website",
      "url": "https://autotok.pages.dev/",
      "name": "AutoTok",
      "description": "AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads",
      "publisher": {
        "@id": "https://autotok.pages.dev/#organization"
      },
      "inLanguage": "en",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://autotok.pages.dev/search?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@type": "WebApplication",
      "@id": "https://autotok.pages.dev/#webapp",
      "name": "AutoTok Automation Tool",
      "url": "https://autotok.pages.dev/",
      "applicationCategory": "AutomationTool",
      "operatingSystem": "All",
      "description": "AUTOTOK is a full-stack web application that automates TikTok video uploads and scheduling through the official TikTok API. Built with PHP, MySQL. Ultimate tool for content creators to schedule multiple daily posts, manage bulk uploads",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      }
    }
  ]
}
</script>
    	 
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * { font-family: 'Poppins', sans-serif; scroll-behavior: smooth; }
        body { background: linear-gradient(135deg, #010101 0%, #1a0a1a 100%); color: #fff; }
        .gradient-text { background: linear-gradient(135deg, #EE1D52, #69C9D0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .glow-button { box-shadow: 0 0 20px rgba(238,29,82,0.5); transition: all 0.3s ease; }
        .glow-button:hover { box-shadow: 0 0 40px rgba(238,29,82,0.8); transform: translateY(-2px); }
        .float-animation { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)}50%{transform:translateY(-15px)} }
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

    
    <section class="flex flex-col items-center justify-center text-center px-6 pt-28 pb-20 md:pb-32">
        <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold gradient-text mb-6">Automate Your TikTok Posts</h1>
        <p class="text-gray-400 text-base sm:text-lg md:text-xl max-w-2xl mx-auto mb-10">Save time and grow your reach with <?php echo APP_NAME; ?> — your smart TikTok automation tool for creators, agencies, and marketers.</p>
        <a href="<?php echo htmlspecialchars($authUrl); ?>" class="glow-button bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold px-8 py-4 rounded-full text-lg">
            Authorize with TikTok
        </a>
    </section>

    
    <section class="max-w-6xl mx-auto px-6 py-16">
        <h2 class="text-3xl md:text-4xl font-bold text-center mb-10">How It Works</h2>
        <div class="grid md:grid-cols-3 gap-8 text-center">
            <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl border border-gray-700 hover:scale-105 transition">
                <div class="text-pink-500 text-4xl mb-4">🔗</div>
                <h3 class="text-xl font-semibold mb-2">1. Connect Account</h3>
                <p class="text-gray-400 text-sm">Authenticate your TikTok securely via OAuth — no passwords needed.</p>
            </div>
            <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl border border-gray-700 hover:scale-105 transition">
                <div class="text-pink-500 text-4xl mb-4">📤</div>
                <h3 class="text-xl font-semibold mb-2">2. Upload Content</h3>
                <p class="text-gray-400 text-sm">Upload single or multiple videos easily, ready for scheduling.</p>
            </div>
            <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl border border-gray-700 hover:scale-105 transition">
                <div class="text-pink-500 text-4xl mb-4">⏰</div>
                <h3 class="text-xl font-semibold mb-2">3. Schedule & Relax</h3>
                <p class="text-gray-400 text-sm">Pick your posting times and let <?php echo APP_NAME; ?> handle the rest.</p>
            </div>
        </div>
    </section>

    
    <section class="max-w-6xl mx-auto px-6 py-16">
        <h2 class="text-3xl md:text-4xl font-bold text-center mb-10">Powerful Features</h2>
        <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-8">
            <?php
            $features = [
                ['⚡', 'Auto Scheduler', 'Post up to 3 times daily automatically.'],
                ['🎞️', 'Bulk Video Uploads', 'Upload multiple TikTok videos at once.'],
                ['💬', 'Smart Captions', 'Randomly use captions from your saved library.'],
                ['📊', 'Activity Tracking', 'View detailed upload and performance logs.'],
                ['🔒', 'Secure Login', 'Powered by TikTok official OAuth 2.0 system.'],
                ['🚀', 'Cloud Automation', 'No installs needed — manage everything online.']
            ];
            foreach ($features as $f): ?>
                <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl border border-gray-700 hover:scale-105 transition">
                    <div class="text-4xl mb-4"><?php echo $f[0]; ?></div>
                    <h3 class="text-lg font-semibold mb-2"><?php echo $f[1]; ?></h3>
                    <p class="text-gray-400 text-sm"><?php echo $f[2]; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    
    <section class="text-center py-20 bg-gradient-to-br from-gray-900 to-gray-950 border-t border-gray-800">
        <h2 class="text-3xl md:text-4xl font-bold mb-6">Start Automating Today</h2>
        <p class="text-gray-400 mb-8 max-w-xl mx-auto">Connect your TikTok and experience effortless automation for your daily content workflow.</p>
        <a href="<?php echo htmlspecialchars($authUrl); ?>" class="glow-button inline-block bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold px-10 py-4 rounded-full text-lg">
            Connect TikTok Now
        </a>
    </section>

    
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
