<?php
// events.php
require_once 'includes/header.php';

$upcoming = [];
$past = [];

foreach ($events as $e) {
    if ($e['type'] === 'upcoming') {
        $upcoming[] = $e;
    } else {
        $past[] = $e;
    }
}
?>

<div class="mx-auto max-w-[1440px] px-4 sm:px-6 pb-24 pt-8 lg:px-8">
    <!-- Breadcrumbs -->
    <div class="mb-4 text-[9px] font-black uppercase tracking-[0.24em] text-slate-400 dark:text-zinc-400">
        <span>AWS Student Builders</span>
        <span class="mx-2">/</span>
        <span class="text-purple-650 dark:text-purple-400">Events Directory</span>
    </div>

    <!-- Page Header -->
    <div class="mb-10">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-purple-605 dark:text-purple-405">Chapter Sessions</p>
        <h2 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white leading-none font-space">Upcoming <span class="text-glow-gradient">Sessions</span> & Past Gallery</h2>
        <p class="mt-4 max-w-xl text-xs sm:text-sm leading-relaxed text-slate-500 dark:text-zinc-400 font-medium">Join our hands-on workshops, cloud certification sessions, meetups, and check out photos from completed events.</p>
    </div>

    <!-- UPCOMING EVENTS -->
    <section class="mt-12">
        <div class="flex items-end justify-between border-b border-slate-200 dark:border-white/10 pb-5 mb-8">
            <div>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-space">Upcoming Events</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-450 mt-1">Get registered and book your slots.</p>
            </div>
            <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-350 shrink-0">
                <?php echo count($upcoming); ?> upcoming session
            </span>
        </div>

        <?php if (empty($upcoming)): ?>
            <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-8 text-center shadow-sm">
                <p class="font-black text-slate-900 dark:text-white">No upcoming sessions scheduled.</p>
                <p class="text-xs text-slate-500 dark:text-zinc-550 mt-1">Check back later or follow our social accounts for announcements.</p>
            </div>
        <?php else: ?>
            <div class="grid gap-6 lg:grid-cols-2">
                <?php foreach ($upcoming as $e): 
                    $photos = [];
                    if (!empty($e['image'])) {
                        $photos[] = $e['image'];
                    }
                    if (!empty($e['gallery']) && is_array($e['gallery'])) {
                        foreach ($e['gallery'] as $p) {
                            if (!empty($p) && !in_array($p, $photos)) {
                                $photos[] = $p;
                            }
                        }
                    }
                ?>
                    <article class="overflow-hidden rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] flex flex-col justify-between shadow-sm hover-glow-card">
                        <!-- Event Image Header -->
                        <?php if (count($photos) > 1): ?>
                            <div class="gallery-slider group relative h-56 w-full overflow-hidden bg-black">
                                <div class="absolute inset-0 z-0 overflow-hidden">
                                    <?php foreach ($photos as $idx => $img): ?>
                                        <div class="slider-slide absolute inset-0 transition-all duration-700 <?php echo $idx === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none'; ?>">
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($e['title']); ?>" class="h-full w-full object-cover">
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
                                </div>
                                <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 z-20 flex justify-between opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <button class="slider-prev cursor-pointer flex h-8 w-8 items-center justify-center rounded-full bg-black/60 border border-white/10 text-white hover:bg-black">
                                        ◀
                                    </button>
                                    <button class="slider-next cursor-pointer flex h-8 w-8 items-center justify-center rounded-full bg-black/60 border border-white/10 text-white hover:bg-black">
                                        ▶
                                    </button>
                                </div>
                                <div class="absolute top-3 right-3 z-10 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 px-2.5 py-1 text-[9px] font-black text-white/90">
                                    📷 <?php echo count($photos); ?> photos
                                </div>
                            </div>
                        <?php elseif (count($photos) === 1): ?>
                            <div class="relative h-56 w-full overflow-hidden">
                                <img src="<?php echo htmlspecialchars($photos[0]); ?>" alt="<?php echo htmlspecialchars($e['title']); ?>" class="h-full w-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
                            </div>
                        <?php else: ?>
                            <div class="h-56 w-full bg-gradient-to-br from-purple-950 via-purple-900/40 to-black relative">
                                <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent"></div>
                            </div>
                        <?php endif; ?>

                        <!-- Details Body -->
                        <div class="p-6 flex-grow">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <p class="text-[9px] font-black uppercase tracking-widest text-purple-650 dark:text-purple-400">
                                        📅 <?php echo $e['date']; ?>
                                    </p>
                                    <h4 class="mt-2 text-xl font-black text-slate-900 dark:text-white leading-tight font-space"><?php echo $e['title']; ?></h4>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1.5">📍 <?php echo $e['location']; ?></p>
                                </div>
                                <span class="rounded-full bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-350 shrink-0">
                                    Upcoming
                                </span>
                            </div>

                            <p class="text-xs sm:text-sm leading-relaxed text-slate-600 dark:text-zinc-400 font-medium">
                                <?php echo $e['description']; ?>
                            </p>

                            <?php if (isset($e['link']) && !empty($e['link'])): ?>
                                <a href="<?php echo $e['link']; ?>" target="_blank" rel="noopener noreferrer" class="mt-6 inline-flex items-center gap-2 rounded-full bg-purple-600 px-6 py-3 text-xs font-black uppercase tracking-widest text-white hover:bg-purple-500 transition-colors">
                                    Open Link →
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- PAST EVENTS GALLERY -->
    <section class="mt-24">
        <div class="flex items-end justify-between border-b border-slate-200 dark:border-white/10 pb-5 mb-8">
            <div>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-space">Completed Events</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-450 mt-1">Explore highlights and recaps from past programs.</p>
            </div>
            <span class="rounded-full bg-slate-100 dark:bg-white/5 px-4 py-2 text-[10px] font-black uppercase tracking-widest shrink-0 text-slate-700 dark:text-white">
                <?php echo count($past); ?> completed sessions
            </span>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($past as $e): 
                $gallery = [];
                if (!empty($e['image'])) {
                    $gallery[] = $e['image'];
                }
                if (!empty($e['gallery']) && is_array($e['gallery'])) {
                    foreach ($e['gallery'] as $p) {
                        if (!empty($p) && !in_array($p, $gallery)) {
                            $gallery[] = $p;
                        }
                    }
                }
            ?>
                <!-- Past Event Card -->
                <div class="gallery-slider group relative overflow-hidden rounded-3xl border border-slate-200 dark:border-white/10 bg-black h-72 shadow-lg hover-glow-card">
                    
                    <!-- Carousel Slide Container -->
                    <div class="absolute inset-0 z-0 overflow-hidden">
                        <?php if (count($gallery) > 0): ?>
                            <?php foreach ($gallery as $idx => $img): ?>
                                <div class="slider-slide absolute inset-0 transition-all duration-700 <?php echo $idx === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none'; ?>">
                                    <img src="<?php echo $img; ?>" alt="<?php echo $e['title']; ?>" class="h-full w-full object-cover">
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="absolute inset-0 bg-gradient-to-br from-[#0c0618] via-black to-zinc-900"></div>
                        <?php endif; ?>
                        <!-- Dark gradient overlay for text readability -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent"></div>
                    </div>

                    <!-- Slide Navigation Controls (Only visible if multi-image) -->
                    <?php if (count($gallery) > 1): ?>
                        <div class="absolute top-3 right-3 z-20 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 px-2.5 py-1 text-[9px] font-black text-white/90">
                            📷 <?php echo count($gallery); ?> photos
                        </div>
                        <div class="absolute inset-x-4 top-1/2 -translate-y-1/2 z-20 flex justify-between opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <button class="slider-prev cursor-pointer flex h-8 w-8 items-center justify-center rounded-full bg-black/60 border border-white/10 text-white hover:bg-black">
                                ◀
                            </button>
                            <button class="slider-next cursor-pointer flex h-8 w-8 items-center justify-center rounded-full bg-black/60 border border-white/10 text-white hover:bg-black">
                                ▶
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Text Info Overlay -->
                    <div class="absolute bottom-0 inset-x-0 p-5 z-10">
                        <p class="text-sm font-black text-white leading-snug font-space"><?php echo $e['title']; ?></p>
                        <p class="mt-1.5 text-[9px] font-black uppercase tracking-widest text-purple-300">
                            📅 <?php echo $e['date']; ?> • 📍 <?php echo $e['location']; ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?>
