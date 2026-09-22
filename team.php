<?php
// team.php
require_once 'includes/header.php';

// Group leads vs other members
$core_members = get_team_members('Core', $participants);
$leader_spotlight = null;
$other_leads = [];

foreach ($core_members as $c) {
    if ($c['level'] === 'Lead') {
        $leader_spotlight = $c;
    } else {
        $other_leads[] = $c;
    }
}

if (!function_exists('get_team_badge_class')) {
    function get_team_badge_class($team_name) {
        switch ($team_name) {
            case 'Core':
                return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
            case 'Technical':
                return 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20';
            case 'Media & Design':
                return 'bg-fuchsia-500/10 text-fuchsia-600 dark:text-fuchsia-400 border border-fuchsia-500/20';
            case 'Marketing':
                return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20';
            case 'Events':
                return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-450 border border-emerald-505/20';
            case 'Operations':
                return 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/20';
            default:
                return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20';
        }
    }
}

if (!function_exists('get_member_tier_class')) {
    function get_member_tier_class($points) {
        $pts = floatval($points);
        if ($pts >= 4000) {
            return 'tier-platinum';
        } elseif ($pts >= 2500) {
            return 'tier-gold';
        } elseif ($pts >= 1000) {
            return 'tier-silver';
        } else {
            return 'tier-bronze';
        }
    }
}
?>

<div class="mx-auto max-w-[1440px] px-4 sm:px-6 pb-24 pt-8 lg:px-8">
    <!-- Breadcrumbs -->
    <div class="mb-4 text-[9px] font-black uppercase tracking-[0.24em] text-slate-400 dark:text-zinc-400">
        <span>AWS Student Builders</span>
        <span class="mx-2">/</span>
        <span class="text-purple-650 dark:text-purple-400">Our Teams</span>
    </div>

    <!-- Page Header -->
    <div class="mb-10">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-purple-605 dark:text-purple-405">Our Ambassadors</p>
        <h2 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white leading-none font-space">Meet the <span class="text-glow-gradient">Chapter Leadership & Teams</span></h2>
        <p class="mt-4 max-w-2xl text-xs sm:text-sm leading-relaxed text-slate-550 dark:text-zinc-400 font-medium">A comprehensive directory of our student builder chapter organized by committees, skills, and technical contributions.</p>
    </div>

    <!-- CORE / LEADERSHIP SECTION -->
    <section class="mt-10">
        <div class="relative overflow-hidden rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-8 shadow-md">
            <!-- Subtle tint glow -->
            <div class="pointer-events-none absolute inset-0 opacity-40 bg-[radial-gradient(1200px_220px_at_20%_10%,rgba(139,92,246,0.12),transparent_60%)]"></div>

            <div class="relative z-10 flex items-end justify-between gap-6">
                <div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white font-space">Core Leadership Team</h3>
                    <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-zinc-400 font-medium">Chapter leaders and core committee organizers.</p>
                </div>
                <span class="rounded-full px-4 py-2 text-[10px] font-black uppercase tracking-widest shrink-0 <?php echo get_team_badge_class('Core'); ?>">
                    <?php echo count($core_members); ?> members
                </span>
            </div>
        </div>

        <!-- HIERARCHY TREE LAYOUT (DRAFT ALIGNMENT) -->
        <div class="mt-8 rounded-3xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/[0.01] p-6 sm:p-10 shadow-sm backdrop-blur-md">
            
            <!-- Core Chapter Leader (Top Node - Centered) -->
            <?php if ($leader_spotlight): 
                $tier_class = get_member_tier_class($leader_spotlight['points']);
            ?>
                <div class="flex justify-center">
                    <a href="#" class="member-modal-trigger group relative overflow-hidden rounded-3xl p-6 tier-gold reflective-card shadow-lg flex flex-col items-center text-center w-full max-w-[280px] hover:scale-105 transition-all duration-300"
                       data-id="<?php echo $leader_spotlight['id']; ?>"
                       data-name="<?php echo htmlspecialchars($leader_spotlight['name']); ?>"
                       data-role="<?php echo htmlspecialchars($leader_spotlight['role']); ?>"
                       data-team="<?php echo htmlspecialchars($leader_spotlight['team']); ?>"
                       data-level="<?php echo htmlspecialchars($leader_spotlight['level']); ?>"
                       data-campus="<?php echo htmlspecialchars($leader_spotlight['campus']); ?>"
                       data-points="<?php echo htmlspecialchars($leader_spotlight['points']); ?>"
                       data-responsibilities="<?php echo htmlspecialchars($leader_spotlight['responsibilities']); ?>"
                       data-img="<?php echo htmlspecialchars($leader_spotlight['image']); ?>"
                       data-rank="0">
                        
                        <div class="relative mb-3">
                            <img src="<?php echo $leader_spotlight['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" 
                                 alt="<?php echo $leader_spotlight['name']; ?>" 
                                 class="h-20 w-20 sm:h-24 sm:w-24 rounded-full object-cover border-2 border-amber-500/50 shadow-md ring-4 ring-amber-500/15 group-hover:ring-amber-500/30 transition-all">
                            <span class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 rounded-full bg-amber-500 px-3 py-0.5 text-[8px] font-black uppercase tracking-wider text-black shadow whitespace-nowrap">
                                <?php echo $leader_spotlight['level']; ?>
                            </span>
                        </div>
                        
                        <p class="text-lg sm:text-xl font-black text-slate-900 dark:text-white group-hover:text-purple-650 dark:group-hover:text-purple-400 transition-colors font-space leading-tight">
                            <?php echo $leader_spotlight['name']; ?>
                        </p>
                        <p class="text-xs sm:text-sm font-semibold text-purple-600 dark:text-purple-400 mt-1">
                            <?php echo $leader_spotlight['role']; ?>
                        </p>
                        <p class="text-xs font-black text-purple-650 dark:text-purple-400 mt-2">
                            <?php echo number_format($leader_spotlight['points']); ?> <span class="text-[9px] uppercase font-bold text-slate-400">PTS</span>
                        </p>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Connector Stem (Vertical Line) -->
            <?php if (!empty($other_leads)): ?>
                <div class="flex justify-center my-4">
                    <div class="w-0.5 h-8 sm:h-10 bg-gradient-to-b from-amber-500/50 to-slate-300 dark:to-white/20 rounded-full"></div>
                </div>

                <!-- Other Core Leaders Grid (Horizontal Row Below Lead) -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6 justify-center max-w-5xl mx-auto">
                    <?php foreach ($other_leads as $lead): 
                        $tier_class = get_member_tier_class($lead['points']);
                    ?>
                        <a href="#" class="member-modal-trigger group flex flex-col items-center text-center p-4 rounded-2xl reflective-card shadow-sm hover:scale-105 transition-all duration-300 <?php echo $tier_class; ?>"
                           data-id="<?php echo $lead['id']; ?>"
                           data-name="<?php echo htmlspecialchars($lead['name']); ?>"
                           data-role="<?php echo htmlspecialchars($lead['role']); ?>"
                           data-team="<?php echo htmlspecialchars($lead['team']); ?>"
                           data-level="<?php echo htmlspecialchars($lead['level']); ?>"
                           data-campus="<?php echo htmlspecialchars($lead['campus']); ?>"
                           data-points="<?php echo htmlspecialchars($lead['points']); ?>"
                           data-responsibilities="<?php echo htmlspecialchars($lead['responsibilities']); ?>"
                           data-img="<?php echo htmlspecialchars($lead['image']); ?>"
                           data-rank="0">
                            
                            <img src="<?php echo $lead['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" 
                                 alt="<?php echo $lead['name']; ?>" 
                                 class="h-14 w-14 sm:h-16 sm:w-16 rounded-full object-cover border border-slate-200 dark:border-white/10 shadow-sm ring-2 ring-transparent group-hover:ring-purple-500/30 transition-all">
                            
                            <p class="truncate w-full text-xs sm:text-sm font-black text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors font-space mt-2.5">
                                <?php echo $lead['name']; ?>
                            </p>
                            <p class="truncate w-full text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5 font-medium">
                                <?php echo $lead['role']; ?>
                            </p>
                            <p class="text-[10px] font-black text-purple-600 dark:text-purple-400 mt-1.5">
                                <?php echo number_format($lead['points']); ?> <span class="text-[8px] font-bold text-slate-400">PTS</span>
                            </p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- OTHER TEAMS (TECHNICAL, DESIGN, OPERATIONS, ETC) -->
    <section class="mt-16 space-y-12">
        <?php
        foreach ($team_order as $team_name):
            if ($team_name === 'Core') continue; // Core already rendered above
            
            $team_members = get_team_members($team_name, $participants);
            if (empty($team_members)) continue;
            
            $meta = $team_meta[$team_name] ?: ['title' => $team_name, 'blurb' => ''];
            
            // Team Spotlight logic: Leaders always display at the left side
            $spotlights = [];
            $rest = [];
            
            foreach ($team_members as $m) {
                if (in_array($m['level'], ['Lead', 'Directorate', 'Manager', 'Core Team'])) {
                    $spotlights[] = $m;
                } else {
                    $rest[] = $m;
                }
            }
            
            // Fallback: If no leader level exists, pick the top member as spotlight
            if (empty($spotlights) && !empty($team_members)) {
                $spotlights = array_slice($team_members, 0, 1);
                $rest = array_slice($team_members, 1);
            }
            
            $num_spotlights = count($spotlights);
        ?>
            <div>
                <!-- Header panel -->
                <div class="relative border-b border-slate-200 dark:border-white/10 pb-4 mb-5">
                    <div class="flex items-end justify-between gap-6">
                        <div>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-space"><?php echo $meta['title']; ?></h3>
                            <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-zinc-400 font-medium"><?php echo $meta['blurb']; ?></p>
                        </div>
                        <span class="rounded-full px-4 py-2 text-[9px] font-black uppercase tracking-widest shrink-0 <?php echo get_team_badge_class($team_name); ?>">
                            <?php echo count($team_members); ?> members
                        </span>
                    </div>
                </div>

                <!-- Tree Hierarchy Layout Container (Matching Draft Image) -->
                <div class="relative overflow-hidden rounded-3xl border border-slate-200 dark:border-white/10 bg-white/60 dark:bg-white/[0.01] p-6 sm:p-10 shadow-sm backdrop-blur-md">
                    
                    <!-- Team Lead(s) (Top Node - Centered) -->
                    <?php if ($num_spotlights > 0): ?>
                        <div class="flex justify-center items-center gap-6 sm:gap-8 flex-wrap">
                            <?php foreach ($spotlights as $spot): 
                                $tier_class = get_member_tier_class($spot['points']);
                            ?>
                                <a href="#" class="member-modal-trigger group flex flex-col items-center text-center p-5 rounded-3xl reflective-card shadow-md transition-all duration-300 hover:scale-105 w-full max-w-[260px] <?php echo $tier_class; ?>"
                                   data-id="<?php echo $spot['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($spot['name']); ?>"
                                   data-role="<?php echo htmlspecialchars($spot['role']); ?>"
                                   data-team="<?php echo htmlspecialchars($spot['team']); ?>"
                                   data-level="<?php echo htmlspecialchars($spot['level']); ?>"
                                   data-campus="<?php echo htmlspecialchars($spot['campus']); ?>"
                                   data-points="<?php echo htmlspecialchars($spot['points']); ?>"
                                   data-responsibilities="<?php echo htmlspecialchars($spot['responsibilities']); ?>"
                                   data-img="<?php echo htmlspecialchars($spot['image']); ?>"
                                   data-rank="0">
                                    
                                    <div class="relative mb-3">
                                        <img src="<?php echo $spot['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" 
                                             alt="<?php echo $spot['name']; ?>" 
                                             class="h-20 w-20 sm:h-22 sm:w-22 rounded-full object-cover border-2 border-purple-500/30 dark:border-purple-400/40 shadow-lg ring-4 ring-purple-500/10 group-hover:ring-purple-500/30 transition-all">
                                        <span class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 rounded-full bg-purple-600 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-wider text-white shadow whitespace-nowrap">
                                            <?php echo $spot['level']; ?>
                                        </span>
                                    </div>
                                    
                                    <h4 class="text-base sm:text-lg font-black text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors font-space leading-tight">
                                        <?php echo $spot['name']; ?>
                                    </h4>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 font-medium">
                                        <?php echo $spot['role']; ?>
                                    </p>
                                    <p class="text-xs font-black text-purple-650 dark:text-purple-400 mt-2">
                                        <?php echo number_format($spot['points']); ?> <span class="text-[8px] font-bold text-slate-400">PTS</span>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Connector Stem (Vertical Line) -->
                    <?php if (!empty($rest)): ?>
                        <div class="flex justify-center my-4">
                            <div class="w-0.5 h-8 sm:h-10 bg-gradient-to-b from-purple-500/40 to-slate-300 dark:to-white/20 rounded-full"></div>
                        </div>

                        <!-- Team Members Row / Grid (Horizontally Aligned Below Lead) -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6 justify-center max-w-5xl mx-auto">
                            <?php foreach ($rest as $m): 
                                $tier_class = get_member_tier_class($m['points']);
                            ?>
                                <a href="#" class="member-modal-trigger group flex flex-col items-center text-center p-4 rounded-2xl reflective-card shadow-sm transition-all duration-300 hover:scale-105 <?php echo $tier_class; ?>"
                                   data-id="<?php echo $m['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($m['name']); ?>"
                                   data-role="<?php echo htmlspecialchars($m['role']); ?>"
                                   data-team="<?php echo htmlspecialchars($m['team']); ?>"
                                   data-level="<?php echo htmlspecialchars($m['level']); ?>"
                                   data-campus="<?php echo htmlspecialchars($m['campus']); ?>"
                                   data-points="<?php echo htmlspecialchars($m['points']); ?>"
                                   data-responsibilities="<?php echo htmlspecialchars($m['responsibilities']); ?>"
                                   data-img="<?php echo htmlspecialchars($m['image']); ?>"
                                   data-rank="0">
                                    
                                    <img src="<?php echo $m['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" 
                                         alt="<?php echo $m['name']; ?>" 
                                         class="h-14 w-14 sm:h-16 sm:w-16 rounded-full object-cover border border-slate-200 dark:border-white/10 shadow-sm ring-2 ring-transparent group-hover:ring-purple-500/30 transition-all">
                                    
                                    <p class="truncate w-full text-xs sm:text-sm font-black text-slate-900 dark:text-white group-hover:text-purple-650 dark:group-hover:text-purple-400 transition-colors font-space mt-2.5">
                                        <?php echo $m['name']; ?>
                                    </p>
                                    <p class="truncate w-full text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5 font-medium">
                                        <?php echo $m['role']; ?>
                                    </p>
                                    <p class="text-[10px] font-black text-purple-600 dark:text-purple-400 mt-1.5">
                                        <?php echo number_format($m['points']); ?> <span class="text-[8px] font-bold text-slate-400">PTS</span>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?>
