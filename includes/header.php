<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page === 'index') {
    $current_page = 'home';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <script>
        // Inline blocking script to set theme before DOM content renders to prevent flicker
        (function() {
            var savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            } else {
                document.documentElement.classList.remove('light');
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AWS Student Builder Group | BZU Multan</title>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind v4 Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        space: ['Space Grotesk', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Custom micro-animations & layout overrides -->
    <style type="text/css">
        html {
            scroll-behavior: smooth;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-text-size-adjust: 100%;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', sans-serif;
        }
        
        /* Ambient Dot Pattern Background */
        body {
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: radial-gradient(circle at 1px 1px, rgba(139, 92, 246, 0.035) 1px, transparent 0);
            background-size: 24px 24px;
            pointer-events: none;
        }
        .dark body::before {
            background-image: radial-gradient(circle at 1px 1px, rgba(168, 85, 247, 0.06) 1px, transparent 0);
        }

        /* Cyberpunk Grid Mesh Overlay */
        body::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            background-image: 
                linear-gradient(to right, rgba(168, 85, 247, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(168, 85, 247, 0.03) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
        }
        .light body::after {
            background-image: 
                linear-gradient(to right, rgba(139, 92, 246, 0.015) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(139, 92, 246, 0.015) 1px, transparent 1px);
        }
        
        /* High-end custom scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(168, 85, 247, 0.2);
            border: 2px solid transparent;
            background-clip: padding-box;
            border-radius: 999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(168, 85, 247, 0.45);
            border: 2px solid transparent;
            background-clip: padding-box;
        }

        /* Selection styling */
        ::selection {
            background: rgba(168, 85, 247, 0.3);
            color: #ffffff;
        }
        .light ::selection {
            background: rgba(139, 92, 246, 0.2);
            color: #0f0728;
        }

        /* Glassmorphism utility card styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.015);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
        .light .glass-card {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(139, 92, 246, 0.12);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        
        /* Premium Glow Borders */
        .neon-border-violet {
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.35);
        }
        .neon-border-amber {
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.45);
        }

        /* Gamified Points Tier styles */
        .tier-gold {
            border: 1px solid rgba(245, 158, 11, 0.35);
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.06);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.02) 0%, rgba(255, 255, 255, 0.005) 100%);
        }
        .dark .tier-gold {
            border: 1px solid rgba(245, 158, 11, 0.45);
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.1);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(0, 0, 0, 0.2) 100%);
        }
        .tier-silver {
            border: 1px solid rgba(148, 163, 184, 0.3);
            box-shadow: 0 0 18px rgba(148, 163, 184, 0.05);
            background: linear-gradient(135deg, rgba(148, 163, 184, 0.02) 0%, rgba(255, 255, 255, 0.005) 100%);
        }
        .dark .tier-silver {
            border: 1px solid rgba(148, 163, 184, 0.4);
            box-shadow: 0 0 22px rgba(148, 163, 184, 0.08);
            background: linear-gradient(135deg, rgba(148, 163, 184, 0.04) 0%, rgba(0, 0, 0, 0.2) 100%);
        }
        .tier-bronze {
            border: 1px solid rgba(180, 83, 9, 0.18);
            box-shadow: 0 0 12px rgba(180, 83, 9, 0.03);
            background: linear-gradient(135deg, rgba(180, 83, 9, 0.01) 0%, rgba(255, 255, 255, 0.005) 100%);
        }
        .dark .tier-bronze {
            border: 1px solid rgba(180, 83, 9, 0.28);
            box-shadow: 0 0 16px rgba(180, 83, 9, 0.05);
            background: linear-gradient(135deg, rgba(180, 83, 9, 0.03) 0%, rgba(0, 0, 0, 0.2) 100%);
        }

        .tier-platinum {
            border: 1px solid rgba(6, 182, 212, 0.35);
            box-shadow: 0 0 25px rgba(6, 182, 212, 0.12);
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.04) 0%, rgba(99, 102, 241, 0.02) 100%);
        }
        .dark .tier-platinum {
            border: 1px solid rgba(6, 182, 212, 0.55);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.2);
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.08) 0%, rgba(15, 23, 42, 0.5) 100%);
        }
        .tier-platinum:hover {
            border-color: rgba(6, 182, 212, 0.85) !important;
            box-shadow: 0 15px 35px -5px rgba(6, 182, 212, 0.35) !important;
            transform: translateY(-4px);
        }

        /* Tier hover interactions */
        .tier-gold:hover {
            border-color: rgba(245, 158, 11, 0.75) !important;
            box-shadow: 0 15px 35px -5px rgba(245, 158, 11, 0.25) !important;
            transform: translateY(-4px);
        }
        .tier-silver:hover {
            border-color: rgba(148, 163, 184, 0.7) !important;
            box-shadow: 0 15px 35px -5px rgba(148, 163, 184, 0.2) !important;
            transform: translateY(-4px);
        }
        .tier-bronze:hover {
            border-color: rgba(180, 83, 9, 0.5) !important;
            box-shadow: 0 12px 25px -5px rgba(180, 83, 9, 0.12) !important;
            transform: translateY(-3px);
        }

        /* Card Interactive Hover Effect */
        .hover-glow-card {
            transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), border-color 0.3s ease, box-shadow 0.3s ease;
            will-change: transform;
        }
        .hover-glow-card:hover {
            transform: translateY(-4px);
            border-color: rgba(168, 85, 247, 0.45) !important;
            box-shadow: 0 15px 35px -10px rgba(168, 85, 247, 0.35) !important;
        }
        .light .hover-glow-card:hover {
            box-shadow: 0 15px 35px -10px rgba(139, 92, 246, 0.22) !important;
        }

        /* Select and Option Dark Mode Visibility Fix */
        select {
            color-scheme: light dark;
        }
        select option {
            background-color: #ffffff;
            color: #0f172a;
        }
        .dark select option,
        html.dark select option {
            background-color: #0d0a15 !important;
            color: #f8fafc !important;
        }

        /* Reflective swipe-shine highlight */
        .reflective-card {
            position: relative;
            overflow: hidden;
        }
        .reflective-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 50%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.08) 30%,
                rgba(255, 255, 255, 0.18) 50%,
                rgba(255, 255, 255, 0.08) 70%,
                transparent
            );
            transform: skewX(-25deg);
            pointer-events: none;
            z-index: 5;
        }
        .reflective-card:hover::after {
            left: 150%;
            transition: all 0.85s cubic-bezier(0.25, 1, 0.5, 1);
        }

        /* Gradient shine text effect */
        .text-glow-gradient {
            background: linear-gradient(135deg, #a855f7 0%, #ec4899 50%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: textShine 6s linear infinite;
        }
        @keyframes textShine {
            to { background-position: 200% center; }
        }
        
        /* Floating sticker animation */
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(2deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        /* Pulsing neon border glow */
        @keyframes borderPulse {
            0% { border-color: rgba(168, 85, 247, 0.15); box-shadow: 0 0 12px rgba(168, 85, 247, 0.05); }
            50% { border-color: rgba(168, 85, 247, 0.4); box-shadow: 0 0 25px rgba(168, 85, 247, 0.25); }
            100% { border-color: rgba(168, 85, 247, 0.15); box-shadow: 0 0 12px rgba(168, 85, 247, 0.05); }
        }
        .neon-glow-active {
            animation: borderPulse 4s infinite ease-in-out;
        }

        /* Animated Progress Bar Gradient */
        @keyframes progressBarShine {
            0% { background-position: 0% center; }
            50% { background-position: 100% center; }
            100% { background-position: 0% center; }
        }
        .progress-bar-shine {
            background-size: 200% auto;
            animation: progressBarShine 3s linear infinite;
        }
        
        /* Custom horizontal scrolling marquee */
        @keyframes marquee {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee {
            animation: marquee 20s linear infinite;
        }
        
        /* Global strict prevention of horizontal scrolling */
        html, body {
            overflow-x: hidden !important;
            max-width: 100vw;
            width: 100%;
            position: relative;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* Smooth touch-scrolling settings */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-[#03000a] dark:text-slate-100 transition-colors duration-300 min-h-screen flex flex-col font-sans pb-24 lg:pb-0 overflow-x-hidden relative w-full">

    <!-- GLOBAL AMBIENT GLOWS (ISOLATED IN OVERFLOW-HIDDEN CONTAINER TO PREVENT HORIZONTAL SCROLL) -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
        <div class="absolute left-0 top-0 h-[600px] w-full bg-gradient-to-b from-purple-900/10 via-transparent to-transparent opacity-60 dark:opacity-100"></div>
        <div class="absolute -left-32 top-32 h-[300px] w-[300px] rounded-full bg-purple-600/5 dark:bg-purple-600/10 blur-3xl animate-pulse"></div>
        <div class="absolute -right-32 top-96 h-[300px] w-[300px] rounded-full bg-fuchsia-600/5 dark:bg-fuchsia-600/10 blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <!-- TOP NAV (DESKTOP) -->
    <header class="fixed inset-x-0 top-0 z-40 px-4 py-3 lg:px-8 pointer-events-none">
        <div class="mx-auto flex max-w-[1440px] items-center justify-between rounded-full px-4 py-2 transition-all duration-300 bg-white/90 dark:bg-black/45 border border-slate-200/80 dark:border-white/10 backdrop-blur-xl shadow-md dark:shadow-black/20 pointer-events-auto">
            
            <!-- Brand Logo & Name -->
            <a href="index.php" class="flex items-center gap-3.5 group">
                <div class="relative overflow-hidden rounded-full h-14 w-14 sm:h-[58px] sm:w-[58px] ring-2 ring-purple-500/30 shadow-md shrink-0 bg-white dark:bg-black/40">
                    <img src="public/images/sbg-logo.png" alt="AWS SBG BZU logo" class="h-full w-full object-cover">
                </div>
                <div class="leading-tight">
                    <span class="block text-xs sm:text-sm font-black uppercase tracking-[0.2em] text-purple-600 dark:text-purple-400">
                        AWS Student Builder Group
                    </span>
                    <span class="block text-[9px] sm:text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-zinc-400 mt-0.5">
                        BZU Multan Campus
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Menu Links -->
            <nav class="hidden lg:flex items-center gap-1 rounded-full border border-slate-200 dark:border-white/5 bg-slate-100/50 dark:bg-white/[0.03] p-1">
                <?php
                $nav_items = [
                    'home' => ['label' => 'Home', 'link' => 'index.php'],
                    'team' => ['label' => 'Teams', 'link' => 'team.php'],
                    'leaderboard' => ['label' => 'Leaderboard', 'link' => 'leaderboard.php'],
                    'store' => ['label' => 'Store', 'link' => 'store.php'],
                    'events' => ['label' => 'Events', 'link' => 'events.php'],
                    'blog' => ['label' => 'Blog', 'link' => 'blog.php'],
                ];
                
                foreach ($nav_items as $key => $item):
                    $is_active = ($current_page === $key);
                    $btn_class = $is_active 
                        ? 'bg-purple-600 text-white font-extrabold shadow-md shadow-purple-600/30' 
                        : 'text-slate-650 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-200/50 dark:hover:bg-white/5';
                ?>
                    <a href="<?php echo $item['link']; ?>" class="rounded-full px-5 py-2 text-[10px] font-black uppercase tracking-[0.16em] transition-all duration-200 <?php echo $btn_class; ?>">
                        <?php echo $item['label']; ?>
                    </a>
                <?php endforeach; ?>
                
                <?php if (is_admin()): ?>
                    <a href="admin.php" class="rounded-full px-5 py-2 text-[10px] font-black uppercase tracking-[0.16em] transition-all duration-200 <?php echo ($current_page === 'admin') ? 'bg-amber-500 text-black font-extrabold shadow-md shadow-amber-500/20' : 'text-slate-600 dark:text-zinc-400 hover:text-white'; ?>">
                        Admin
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Actions (Theme Toggle & Sign In / Sign Out) -->
            <div class="flex items-center gap-2.5">
                <button id="theme-toggle" class="cursor-pointer inline-flex items-center gap-1.5 rounded-full border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 px-3.5 py-2 text-[10px] font-black uppercase tracking-wider text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-white/10 transition-all">
                    <svg class="h-3.5 w-3.5 theme-icon text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <span class="theme-text">Light</span>
                </button>

                <?php if (is_admin() || is_member() || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'member']))): ?>
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-purple-500/10 border border-purple-500/20 px-3 py-1.5 text-[9px] font-bold text-purple-600 dark:text-purple-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? ($_SESSION['role'] === 'admin' ? 'Chapter Lead' : ($_SESSION['member_name'] ?? 'Builder'))); ?>
                        </span>
                        <a href="account.php" class="rounded-full bg-purple-500/10 border border-purple-500/20 hover:bg-purple-500/20 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-300 transition-all shadow-sm">Account</a>
                        <a href="logout.php" class="rounded-full bg-red-500/15 border border-red-500/30 hover:bg-red-500/25 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400 transition-all shadow-sm">
                            Logout
                        </a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="rounded-full border border-purple-500/30 bg-purple-500/10 hover:bg-purple-500/20 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-300 transition-all shadow-sm">
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- MOBILE FLOATING BOTTOM NAV BAR (APP-LIKE DESIGN WITH ICON + TEXT HIGHLIGHT) -->
    <div class="fixed bottom-4 inset-x-4 z-50 lg:hidden rounded-3xl bg-white/95 dark:bg-[#07040f]/90 border border-slate-200 dark:border-white/10 backdrop-blur-xl flex justify-around items-center py-3 px-2 shadow-2xl dark:shadow-purple-900/10">
        
        <!-- Home -->
        <a href="index.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'home' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Home</span>
        </a>

        <!-- Teams -->
        <a href="team.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'team' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Teams</span>
        </a>

        <!-- Store -->
        <a href="store.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'store' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Store</span>
        </a>

        <!-- Leaderboard -->
        <a href="leaderboard.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'leaderboard' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 00.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Ranks</span>
        </a>

        <!-- Events -->
        <a href="events.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'events' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Events</span>
        </a>

        <!-- Blog -->
        <a href="blog.php" class="flex flex-col items-center gap-1 transition-all duration-200 <?php echo $current_page === 'blog' ? 'text-purple-600 dark:text-purple-400 scale-105' : 'text-slate-600 dark:text-zinc-300 hover:text-purple-600 dark:hover:text-purple-400'; ?>">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1M19 20a2 2 0 002-2V8a2 2 0 00-2-2h-5a2 2 0 00-2 2v3m9 11h-9" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Blog</span>
        </a>
    </div>

    <!-- CONTENT WRAPPER -->
    <main class="flex-grow pt-20 lg:pt-24 z-10 relative">
        
        <!-- DYNAMIC LOCAL TIME GREETING BANNER -->
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 pt-4">
            <div class="rounded-2xl border border-slate-200 dark:border-white/5 bg-white/70 dark:bg-white/[0.01] px-4 py-3 flex items-center justify-between gap-4 backdrop-blur-md shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="h-4 w-4 text-purple-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    <span id="local-greet" data-user="<?php echo is_admin() ? 'Chapter Lead' : (is_member() ? htmlspecialchars($_SESSION['member_name'] ?? '') : ''); ?>" class="text-[10px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-300">
                        Hello, Builder!
                    </span>
                </div>
                <!-- Dynamic clock indicator -->
                <span id="local-time" class="text-[9px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest">
                    --:-- --
                </span>
            </div>
        </div>
