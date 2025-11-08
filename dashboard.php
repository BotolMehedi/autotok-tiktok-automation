<?php
require_once 'config.php';
requireLogin();

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch stats
$stmt = $pdo->query("SELECT * FROM stats WHERE id = 1");
$stats = $stmt->fetch();

// Fetch user's schedules with authorization status
$stmt = $pdo->prepare("SELECT s.*, p.access_token as profile_token, p.token_expires_at as profile_expires 
    FROM schedules s 
    LEFT JOIN profile_tokens p ON s.id = p.schedule_id 
    WHERE s.user_id = ? AND s.status = 'active' 
    ORDER BY s.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$schedules = $stmt->fetchAll();

// Fetch recent logs
$stmt = $pdo->prepare("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$_SESSION['user_id']]);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background: #010101; }
        .gradient-bg { background: linear-gradient(135deg, #1a0a1a 0%, #2a1a2a 100%); }
        .gradient-text { background: linear-gradient(135deg, #EE1D52 0%, #69C9D0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 40px rgba(238, 29, 82, 0.3); }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #1a0a1a 0%, #0a0a0a 100%); transition: transform 0.3s ease; }
        .nav-item { transition: all 0.3s ease; }
        .nav-item:hover { background: rgba(238, 29, 82, 0.1); border-left: 4px solid #EE1D52; }
        .nav-item.active { background: rgba(238, 29, 82, 0.2); border-left: 4px solid #EE1D52; }
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); overflow-y: auto; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .toast { position: fixed; top: 20px; right: 20px; z-index: 100; padding: 16px 24px; border-radius: 12px; color: white; font-weight: 500; opacity: 0; transform: translateX(400px); transition: all 0.3s ease; }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .toast.error { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .progress-bar { height: 8px; background: #2a2a2a; border-radius: 999px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #EE1D52 0%, #69C9D0 100%); transition: width 0.3s ease; }
        
        /* Mobile Menu Styles */
        .mobile-menu-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; transition: opacity 0.3s ease; }
        .mobile-menu-overlay.show { display: block; opacity: 1; }
        
        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 50; transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.5); }
            .main-content { margin-left: 0 !important; }
        }
        
        /* Hamburger Animation */
        .hamburger-line { transition: all 0.3s ease; }
        .hamburger.open .line1 { transform: rotate(45deg) translate(6px, 6px); }
        .hamburger.open .line2 { opacity: 0; }
        .hamburger.open .line3 { transform: rotate(-45deg) translate(7px, -7px); }
        
        /* Mobile Header */
        .mobile-header { display: none; background: linear-gradient(135deg, #1a0a1a 0%, #2a1a2a 100%); border-bottom: 1px solid #333; }
        @media (max-width: 768px) {
            .mobile-header { display: flex; }
            .desktop-sidebar { display: block; }
        }
        
        /* Badge Styles */
        .badge-authorized { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-unauthorized { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    </style>
</head>
<body class="flex flex-col md:flex-row">
    <!-- Mobile Header -->

<header class="bg-gradient-to-r from-[#1a0a1a] to-[#2a1a2a] border-b border-gray-800 md:hidden sticky top-0 z-30 flex items-center justify-between px-4 py-3">
    <div>
        <h1 class="text-xl font-bold gradient-text"><?php echo APP_NAME; ?></h1>
        <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($user['username']); ?></p>
    </div>
    <button id="navToggle" class="relative w-8 h-6 focus:outline-none" aria-label="Toggle menu">
        <span class="absolute block w-8 h-0.5 bg-white transition-all duration-300 top-0"></span>
        <span class="absolute block w-8 h-0.5 bg-white transition-all duration-300 top-1/2 -translate-y-1/2"></span>
        <span class="absolute block w-8 h-0.5 bg-white transition-all duration-300 bottom-0"></span>
    </button>
</header>

<!-- Overlay -->
<div id="mobileOverlay" class="fixed inset-0 bg-black/60 hidden z-40 transition-opacity"></div>

<!-- Sidebar -->
<aside id="sidebarNav" class="fixed md:static inset-y-0 left-0 w-[80%] max-w-xs md:w-64 bg-gradient-to-b from-[#1a0a1a] to-[#0a0a0a] border-r border-gray-800 transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 rounded-r-2xl shadow-2xl">
    <div class="flex items-center justify-between p-6 border-b border-gray-700 md:hidden">
        <div>
            <h1 class="text-xl font-bold gradient-text"><?php echo APP_NAME; ?></h1>
            <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($user['username']); ?></p>
        </div>
        <button id="closeBtn" class="text-gray-300 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="p-6 hidden md:block">
        <h1 class="text-2xl font-bold gradient-text"><?php echo APP_NAME; ?></h1>
        <p class="text-gray-400 text-sm mt-1">by <?php echo htmlspecialchars($user['username']); ?></p>
    </div>

    <nav class="mt-2 space-y-1">
        <a href="dashboard.php" class="nav-item active flex items-center px-6 py-3 text-white">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
            </svg>
            Dashboard
        </a>
        <a href="#" onclick="openModal('addScheduleModal'); closeMenu(); return false;" class="nav-item flex items-center px-6 py-3 text-gray-400 hover:text-white">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
            </svg>
            Add New
        </a>
        <a href="#logs-section" onclick="closeMenu()" class="nav-item flex items-center px-6 py-3 text-gray-400 hover:text-white">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            Logs
        </a>
        <a href="logout.php" class="nav-item flex items-center px-6 py-3 text-gray-400 hover:text-white mt-4">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
            </svg>
            Logout
        </a>
    </nav>
</aside>



    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto main-content">
        <div class="p-4 md:p-8">
            <!-- Header -->
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-white">Dashboard</h2>
                <p class="text-gray-400 mt-1 text-sm md:text-base">Manage your automated TikTok posts</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6 md:mb-8">
                <div class="gradient-bg rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 card-hover">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Total Profiles</p>
                            <p class="text-2xl md:text-3xl font-bold text-white mt-1 md:mt-2"><?php echo $stats['total_profiles']; ?></p>
                        </div>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-pink-500 bg-opacity-20 flex items-center justify-center mt-2 md:mt-0">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-pink-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="gradient-bg rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 card-hover">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Total Videos</p>
                            <p class="text-2xl md:text-3xl font-bold text-white mt-1 md:mt-2"><?php echo $stats['total_videos_scheduled']; ?></p>
                        </div>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-purple-500 bg-opacity-20 flex items-center justify-center mt-2 md:mt-0">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="gradient-bg rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 card-hover">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Last Uploaded</p>
                            <p class="text-sm md:text-lg font-bold text-white mt-1 md:mt-2">
                                <?php echo $stats['last_video_uploaded_at'] ? date('M d, H:i', strtotime($stats['last_video_uploaded_at'])) : 'Never'; ?>
                            </p>
                        </div>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-cyan-500 bg-opacity-20 flex items-center justify-center mt-2 md:mt-0">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-cyan-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="gradient-bg rounded-xl md:rounded-2xl p-4 md:p-6 border border-gray-700 card-hover">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Active Today</p>
                            <p class="text-2xl md:text-3xl font-bold text-white mt-1 md:mt-2"><?php echo $stats['active_schedules_today']; ?></p>
                        </div>
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center mt-2 md:mt-0">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add New Button (Desktop) -->
            <div class="mb-6 hidden md:block">
                <button onclick="openModal('addScheduleModal')" class="bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold px-6 py-3 rounded-xl flex items-center hover:shadow-lg transition">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                    </svg>
                    Add New Schedule
                </button>
            </div>

            <!-- Schedules Table -->
            <div class="gradient-bg rounded-xl md:rounded-2xl border border-gray-700 overflow-hidden mb-6 md:mb-8">
                <div class="p-4 md:p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg md:text-xl font-bold text-white">All Profiles</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800 bg-opacity-50">
                            <tr>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase">Username</th>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Videos</th>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Captions</th>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase hidden lg:table-cell">Last Upload</th>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase">Status</th>
                                <th class="px-3 md:px-6 py-3 md:py-4 text-left text-xs font-semibold text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($schedules)): ?>
                            <tr>
                                <td colspan="6" class="px-4 md:px-6 py-6 md:py-8 text-center text-gray-400">
                                    <p class="mb-2 text-sm md:text-base">No schedules yet</p>
                                    <button onclick="openModal('addScheduleModal')" class="text-pink-500 hover:text-pink-400 text-xs md:text-sm font-semibold">
                                        Create your first schedule →
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $schedule): 
                                    $isAuthorized = !empty($schedule['profile_token']) && strtotime($schedule['profile_expires']) > time();
                                ?>
                                <tr class="hover:bg-gray-800 hover:bg-opacity-30 transition">
                                    <td class="px-3 md:px-6 py-3 md:py-4">
                                        <div class="text-white font-medium text-sm md:text-base"><?php echo htmlspecialchars($schedule['username']); ?></div>
                                        <div class="text-xs text-gray-500 md:hidden">
                                            <?php echo $schedule['videos_count']; ?> videos • <?php echo $schedule['captions_count']; ?> captions
                                        </div>
                                    </td>
                                    <td class="px-3 md:px-6 py-3 md:py-4 text-gray-300 hidden md:table-cell"><?php echo $schedule['videos_count']; ?></td>
                                    <td class="px-3 md:px-6 py-3 md:py-4 text-gray-300 hidden md:table-cell"><?php echo $schedule['captions_count']; ?></td>
                                    <td class="px-3 md:px-6 py-3 md:py-4 text-gray-400 text-xs md:text-sm hidden lg:table-cell">
                                        <?php echo $schedule['last_uploaded_at'] ? date('M d, H:i', strtotime($schedule['last_uploaded_at'])) : 'Never'; ?>
                                    </td>
                                    <td class="px-3 md:px-6 py-3 md:py-4 text-center">
    <?php if ($isAuthorized): ?>
        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-600/20 text-green-400 border border-green-500/30">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M5 13l4 4L19 7" />
            </svg>
            Allowed
        </span>
    <?php else: ?>
        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-600/20 text-red-400 border border-red-500/30">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" />
            </svg>
            Restricted
        </span>
    <?php endif; ?>
</td>

                                    <td class="px-3 md:px-6 py-3 md:py-4">
                                        <div class="flex flex-col md:flex-row space-y-1 md:space-y-0 md:space-x-2">
                                            <?php if (!$isAuthorized): ?>
                                                <button onclick="authorizeProfile(<?php echo $schedule['id']; ?>)" class="bg-green-600 hover:bg-green-700 text-white px-2 md:px-3 py-1 rounded text-xs whitespace-nowrap" title="Authorize Profile">
                                                    Authorize
                                                </button>
                                            <?php else: ?>
                                                <button onclick="reauthorizeProfile(<?php echo $schedule['id']; ?>)" class="bg-yellow-600 hover:bg-yellow-700 text-white px-2 md:px-3 py-1 rounded text-xs whitespace-nowrap" title="Reauthorize">
                                                    Reauth
                                                </button>
                                                <button 
  onclick="openEditModal(<?php echo htmlspecialchars(json_encode($schedule)); ?>)" 
  class="bg-purple-600 hover:bg-purple-700 text-white px-2 md:px-3 py-1 rounded text-xs" 
  title="Edit Schedule">
  Edit
</button>

                                                <button onclick="uploadNow(<?php echo $schedule['id']; ?>)" class="bg-blue-600 hover:bg-blue-700 text-white px-2 md:px-3 py-1 rounded text-xs" title="Upload Now">
                                                    ▶
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteSchedule(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['username']); ?>')" class="bg-red-600 hover:bg-red-700 text-white px-2 md:px-3 py-1 rounded text-xs" title="Delete">
                                                ✕
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Logs Section -->
            <div id="logs-section" class="gradient-bg rounded-xl md:rounded-2xl border border-gray-700 overflow-hidden">
                <div class="p-4 md:p-6 border-b border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg md:text-xl font-bold text-white">Activity Logs</h3>
                    <button onclick="exportLogs()" class="bg-gray-700 hover:bg-gray-600 text-white px-3 md:px-4 py-2 rounded-lg text-xs md:text-sm">
                        Export
                    </button>
                </div>
                <div class="overflow-x-auto max-h-96">
                    <table class="w-full">
                        <thead class="bg-gray-800 bg-opacity-50 sticky top-0">
                            <tr>
                                <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-semibold text-gray-400 uppercase">Time</th>
                                <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-semibold text-gray-400 uppercase">Action</th>
                                <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-semibold text-gray-400 uppercase hidden md:table-cell">Video</th>
                                <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-semibold text-gray-400 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-4 md:px-6 py-6 md:py-8 text-center text-gray-400 text-sm">No activity logs yet</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-800 hover:bg-opacity-30">
                                    <td class="px-3 md:px-6 py-2 md:py-3 text-gray-400 text-xs md:text-sm"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                                    <td class="px-3 md:px-6 py-2 md:py-3 text-gray-300 text-xs md:text-sm"><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td class="px-3 md:px-6 py-2 md:py-3 text-gray-400 text-xs hidden md:table-cell"><?php echo $log['video_name'] ? htmlspecialchars(substr($log['video_name'], 0, 20)) : '-'; ?></td>
                                    <td class="px-3 md:px-6 py-2 md:py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php 
                                            echo $log['status'] === 'success' ? 'bg-green-900 text-green-300' : 
                                                ($log['status'] === 'failed' ? 'bg-red-900 text-red-300' : 'bg-yellow-900 text-yellow-300'); 
                                        ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    
<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="modal">
  <div class="bg-gray-900 rounded-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-gray-700">
    <div class="p-4 border-b border-gray-700 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
      <h3 class="text-xl font-bold text-white">Edit Schedule</h3>
      <button onclick="closeModal('editScheduleModal')" class="text-gray-400 hover:text-white">
        ✕
      </button>
    </div>

    <form id="editScheduleForm" class="p-4 space-y-4" enctype="multipart/form-data">
      <input type="hidden" name="id" id="edit_schedule_id">

      <!-- Username -->
      <div>
        <label class="block text-white font-semibold mb-2">TikTok Username</label>
        <input type="text" id="edit_username" name="username" required
          class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3">
      </div>

      <!-- Captions -->
      <div>
        <label class="block text-white font-semibold mb-2">Captions (JSON Format)</label>
        <textarea id="edit_captions" name="captions" rows="6" required
          class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3"></textarea>
      </div>

      <!-- Times -->
      <div>
        <label class="block text-white font-semibold mb-2">Schedule Times (Up to 3)</label>
        <div class="space-y-2">
          <input type="time" id="edit_time1" name="time1"
            class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3">
          <input type="time" id="edit_time2" name="time2"
            class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3">
          <input type="time" id="edit_time3" name="time3"
            class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3">
        </div>
      </div>

      <!-- Video Section -->
      <div>
        <label class="block text-white font-semibold mb-2">Existing Videos</label>
        <div id="editVideoPreview" class="grid grid-cols-2 gap-3"></div>
      </div>

      <div>
        <label class="block text-white font-semibold mb-2">Upload New Videos (.mp4)</label>
        <input type="file" id="edit_videos" name="videos[]" accept="video/mp4" multiple
          class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3">
      </div>

      <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-4">
        <button type="submit"
          class="flex-1 bg-gradient-to-r from-purple-600 to-pink-500 text-white font-semibold px-6 py-3 rounded-lg hover:shadow-lg transition">
          Save Changes
        </button>
        <button type="button" onclick="closeModal('editScheduleModal')"
          class="flex-1 bg-gray-700 text-white font-semibold px-6 py-3 rounded-lg hover:bg-gray-600 transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>


    <!-- Add Schedule Modal -->
    <div id="addScheduleModal" class="modal">
        <div class="bg-gray-900 rounded-xl md:rounded-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-gray-700">
            <div class="p-4 md:p-6 border-b border-gray-700 flex items-center justify-between sticky top-0 bg-gray-900 z-10">
                <h3 class="text-xl md:text-2xl font-bold text-white">Add New Schedule</h3>
                <button onclick="closeModal('addScheduleModal')" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <form id="scheduleForm" class="p-4 md:p-6 space-y-4 md:space-y-6">
                <div>
                    <label class="block text-white font-semibold mb-2 text-sm md:text-base">TikTok Username</label>
                    <input type="text" id="username" name="username" required class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500 text-sm md:text-base" placeholder="Enter TikTok username">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm md:text-base">Upload Videos (MP4 only)</label>
                    <input type="file" id="videos" name="videos[]" multiple accept=".mp4" required class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500 text-sm">
                    <p class="text-gray-400 text-xs md:text-sm mt-2">You can select multiple videos (Max 500MB each)</p>
                </div>

                <div id="uploadProgress" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-white text-sm">Uploading...</span>
                        <span id="progressText" class="text-gray-400 text-sm">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div id="progressFill" class="progress-fill" style="width: 0%"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm md:text-base">Captions (JSON Format)</label>
                    <textarea id="captions" name="captions" rows="6" required class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500 text-sm" placeholder='["Caption 1", "Caption 2", "Caption 3"]'></textarea>
                    <p class="text-gray-400 text-xs md:text-sm mt-2">Enter captions as a JSON array</p>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm md:text-base">Schedule Times (Up to 3)</label>
                    <div class="space-y-3">
                        <input type="time" name="time1" class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500">
                        <input type="time" name="time2" class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500">
                        <input type="time" name="time3" class="w-full bg-gray-800 text-white border border-gray-700 rounded-lg px-4 py-3 focus:outline-none focus:border-pink-500">
                    </div>
                    <p class="text-gray-400 text-xs md:text-sm mt-2">Leave empty if you don't need all 3 times</p>
                </div>

                <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-semibold px-6 py-3 rounded-lg hover:shadow-lg transition text-sm md:text-base">
                        Create Schedule
                    </button>
                    <button type="button" onclick="closeModal('addScheduleModal')" class="flex-1 bg-gray-700 text-white font-semibold px-6 py-3 rounded-lg hover:bg-gray-600 transition text-sm md:text-base">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        const toggleBtn = document.getElementById('navToggle');
const closeBtn = document.getElementById('closeBtn');
const sidebar = document.getElementById('sidebarNav');
const overlay = document.getElementById('mobileOverlay');

toggleBtn.addEventListener('click', toggleMenu);
closeBtn.addEventListener('click', closeMenu);
overlay.addEventListener('click', closeMenu);

function toggleMenu() {
    const isOpen = sidebar.classList.contains('translate-x-0');
    sidebar.classList.toggle('-translate-x-full', isOpen);
    sidebar.classList.toggle('translate-x-0', !isOpen);
    overlay.classList.toggle('hidden', isOpen);
    document.body.style.overflow = isOpen ? 'auto' : 'hidden';
    toggleBtn.classList.toggle('open', !isOpen);
    animateHamburger(!isOpen);
}

function closeMenu() {
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');
    overlay.classList.add('hidden');
    document.body.style.overflow = 'auto';
    toggleBtn.classList.remove('open');
    animateHamburger(false);
}

function animateHamburger(open) {
    const bars = toggleBtn.querySelectorAll('span');
    if (open) {
        bars[0].style.transform = 'translateY(10px) rotate(45deg)';
        bars[1].style.opacity = '0';
        bars[2].style.transform = 'translateY(-10px) rotate(-45deg)';
    } else {
        bars[0].style.transform = '';
        bars[1].style.opacity = '1';
        bars[2].style.transform = '';
    }
}

        function openModal(id) {
            document.getElementById(id).classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Authorization Functions
        function authorizeProfile(scheduleId) {
            const state = btoa(JSON.stringify({schedule_id: scheduleId, action: 'authorize'}));
            const authUrl = `https://www.tiktok.com/v2/auth/authorize/?client_key=<?php echo TIKTOK_CLIENT_KEY; ?>&scope=user.info.basic,video.upload,video.publish&response_type=code&redirect_uri=<?php echo urlencode(TIKTOK_REDIRECT_URI); ?>&state=${state}`;
            window.location.href = authUrl;
        }

        function reauthorizeProfile(scheduleId) {
            if (!confirm('Reauthorize this profile with TikTok?')) return;
            authorizeProfile(scheduleId);
        }

        document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const username = document.getElementById('username').value;
            const captions = document.getElementById('captions').value;
            const videos = document.getElementById('videos').files;
            
            if (videos.length === 0) {
                showToast('Please select at least one video', 'error');
                return;
            }

            try {
                JSON.parse(captions);
            } catch (e) {
                showToast('Invalid JSON format for captions', 'error');
                return;
            }

            const times = [];
            for (let i = 1; i <= 3; i++) {
                const time = document.querySelector(`input[name="time${i}"]`).value;
                if (time) times.push(time);
            }

            if (times.length === 0) {
                showToast('Please set at least one schedule time', 'error');
                return;
            }

            formData.append('username', username);
            formData.append('captions', captions);
            formData.append('times', JSON.stringify(times));
            
            for (let i = 0; i < videos.length; i++) {
                formData.append('videos[]', videos[i]);
            }

            document.getElementById('uploadProgress').classList.remove('hidden');
            
            try {
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        document.getElementById('progressFill').style.width = percent + '%';
                        document.getElementById('progressText').textContent = percent + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showToast('Schedule created successfully!', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast(response.message || 'Failed to create schedule', 'error');
                            document.getElementById('uploadProgress').classList.add('hidden');
                        }
                    } else {
                        showToast('Upload failed. Please try again.', 'error');
                        document.getElementById('uploadProgress').classList.add('hidden');
                    }
                });

                xhr.open('POST', 'ajax/create_schedule.php', true);
                xhr.send(formData);

            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
                document.getElementById('uploadProgress').classList.add('hidden');
            }
        });

        function deleteSchedule(id, username) {
            if (!confirm(`Are you sure you want to delete the schedule for ${username}?`)) {
                return;
            }

            fetch('ajax/delete_schedule.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Schedule deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Failed to delete schedule', 'error');
                }
            })
            .catch(err => {
                showToast('An error occurred', 'error');
            });
        }

        function uploadNow(id) {
            if (!confirm('Upload a video immediately?')) return;

            fetch('ajax/upload_now.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ schedule_id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Video uploaded successfully!', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message || 'Upload failed', 'error');
                }
            })
            .catch(err => {
                showToast('An error occurred', 'error');
            });
        }

        function exportLogs() {
            window.location.href = 'ajax/export_logs.php';
        }

        // Close mobile menu when window is resized to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                closeMobileMenu();
            }
        });
        
        
        
        let removedVideos = [];

async function openEditModal(schedule) {
  removedVideos = [];

  document.getElementById('edit_schedule_id').value = schedule.id;
  document.getElementById('edit_username').value = schedule.username;
  document.getElementById('edit_captions').value = '';

  const times = JSON.parse(schedule.schedule_times || '[]');
  document.getElementById('edit_time1').value = times[0] || '';
  document.getElementById('edit_time2').value = times[1] || '';
  document.getElementById('edit_time3').value = times[2] || '';

  const container = document.getElementById('editVideoPreview');
  container.innerHTML = '<p class="text-gray-400 text-sm">Loading...</p>';

  try {
    const res = await fetch(`ajax/get_schedule.php?username=${encodeURIComponent(schedule.username)}`);
    const data = await res.json();

    if (!data.success) throw new Error(data.message || 'Failed to load assets');

    // 🧾 Fill captions JSON
    document.getElementById('edit_captions').value = data.captions || '';

    // 🎬 Load videos
    container.innerHTML = '';
    if (data.videos && data.videos.length > 0) {
      data.videos.forEach(fileName => {
        const videoUrl = `videos/${schedule.username}_videos/${fileName}`;
        const wrapper = document.createElement('div');
        wrapper.className = 'relative border border-gray-700 rounded-lg overflow-hidden';
        wrapper.innerHTML = `
          <video src="${videoUrl}" controls class="w-full h-32 object-cover"></video>
          <button type="button" onclick="removeVideo('${fileName}', this)" 
            class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1 text-xs hover:bg-red-700">✕</button>
        `;
        container.appendChild(wrapper);
      });
    } else {
      container.innerHTML = '<p class="text-gray-400 text-sm">No existing videos found.</p>';
    }
  } catch (err) {
    console.error(err);
    container.innerHTML = '<p class="text-red-500 text-sm">Failed to load videos.</p>';
  }

  openModal('editScheduleModal');
}


function removeVideo(filename, btn) {
  removedVideos.push(filename);
  btn.closest('div').remove();
}

document.getElementById('editScheduleForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const id = document.getElementById('edit_schedule_id').value;
  const username = document.getElementById('edit_username').value.trim();
  const captions = document.getElementById('edit_captions').value.trim();
  const times = [document.getElementById('edit_time1').value, document.getElementById('edit_time2').value, document.getElementById('edit_time3').value].filter(Boolean);

  if (!username || !captions || times.length === 0) {
    showToast('All fields are required', 'error');
    return;
  }

  try { JSON.parse(captions); } 
  catch { showToast('Invalid JSON format for captions', 'error'); return; }

  const formData = new FormData();
  formData.append('id', id);
  formData.append('username', username);
  formData.append('captions', captions);
  formData.append('times', JSON.stringify(times));
  formData.append('removed_videos', JSON.stringify(removedVideos));

  const files = document.getElementById('edit_videos').files;
  for (let file of files) {
    formData.append('videos[]', file);
  }

  const response = await fetch('ajax/edit_schedule.php', { method: 'POST', body: formData });
  const data = await response.json();

  if (data.success) {
    showToast('Schedule updated successfully', 'success');
    closeModal('editScheduleModal');
    setTimeout(() => location.reload(), 1500);
  } else {
    showToast(data.message || 'Update failed', 'error');
  }
});

    </script>
</body>
</html>
