<?php
// store.php
require_once 'includes/auth.php';
require_once 'includes/header.php';

$success = '';
$error = '';

$current_member = get_logged_member();
$is_member_logged_in = is_member();

// Handle Swag Claim Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_swag') {
    if (!$is_member_logged_in || !$current_member) {
        $error = "Please sign in with your Builder account to claim swags.";
    } else {
        $member_id = intval($current_member['id']);
        $swag_id = intval($_POST['swag_id']);

        if (!$swag_id) {
            $error = "Please select a valid swag item.";
        } else {
            $target_member = $current_member;
            $target_swag = null;
            foreach ($swags as $s) {
                if ($s['id'] == $swag_id) {
                    $target_swag = $s;
                    break;
                }
            }

            if (!$target_swag) {
                $error = "Selected AWS Swag not found.";
            } elseif ($target_swag['stock'] <= 0) {
                $error = "Sorry, this swag item is currently out of stock!";
            } elseif (floatval($target_member['points']) < floatval($target_swag['points'])) {
                $error = "Insufficient points! You have " . number_format($target_member['points'], 2) . " PTS, but this swag requires " . number_format($target_swag['points'], 2) . " PTS.";
            } else {
                try {
                    // Check if a pending request already exists for this member and swag
                    $checkStmt = $db->prepare("SELECT id FROM `swag_requests` WHERE `member_id` = ? AND `swag_id` = ? AND `status` = 'pending'");
                    $checkStmt->execute([$member_id, $swag_id]);
                    if ($checkStmt->rowCount() > 0) {
                        $error = "You already have a pending claim request for this swag item!";
                    } else {
                        $stmt = $db->prepare("INSERT INTO `swag_requests` (`member_id`, `swag_id`, `points_spent`, `status`) VALUES (?, ?, ?, 'pending')");
                        $stmt->execute([$member_id, $swag_id, floatval($target_swag['points'])]);
                        $success = "Claim request for '" . htmlspecialchars($target_swag['name']) . "' submitted successfully! Lead Admin will review and fulfill your request.";
                        
                        // Refresh data
                        require 'includes/db.php';
                        $current_member = get_logged_member();
                    }
                } catch (Exception $e) {
                    $error = "Error submitting claim: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="mx-auto max-w-[1440px] px-4 sm:px-6 pb-24 pt-8 lg:px-8">
    <!-- Breadcrumbs -->
    <div class="mb-4 text-[10px] font-black uppercase tracking-[0.24em] text-slate-400 dark:text-zinc-400">
        <a href="index.php" class="hover:text-purple-500 transition-colors">AWS Student Builders</a>
        <span class="mx-2">/</span>
        <span class="text-purple-600 dark:text-purple-400">Swags Store</span>
    </div>

    <!-- Logged-In Member Profile Banner (Enhancement B) -->
    <?php if ($is_member_logged_in && $current_member): ?>
        <?php $mb_code = $current_member['member_code'] ?? ('SBG-' . sprintf('%03d', $current_member['id'])); ?>
        <div class="mb-8 rounded-3xl border border-purple-500/20 bg-purple-500/5 p-6 backdrop-blur-md flex flex-col sm:flex-row items-center justify-between gap-6 shadow-xl">
            <div class="flex items-center gap-4 text-left">
                <img src="<?php echo $current_member['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" class="h-16 w-16 rounded-2xl object-cover border border-purple-500/30 shadow-lg">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xl font-black text-slate-900 dark:text-white font-space"><?php echo htmlspecialchars($current_member['name']); ?></h2>
                        <span class="rounded-md bg-purple-500/10 border border-purple-500/20 px-2 py-0.5 text-[9px] font-mono font-bold text-purple-400">
                            ID: <?php echo htmlspecialchars($mb_code); ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5"><?php echo htmlspecialchars($current_member['role']); ?> • <?php echo htmlspecialchars($current_member['team']); ?> Team</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 shrink-0">
                <div class="text-right">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Your PTS Balance</span>
                    <span class="text-2xl font-black text-amber-500 font-space">⚡ <?php echo number_format($current_member['points'], 2); ?> <span class="text-xs text-slate-400 font-sans">PTS</span></span>
                </div>
                <a href="account.php" class="rounded-full bg-purple-600 hover:bg-purple-500 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                    ✏️ Edit Profile
                </a>
                <button type="button" onclick="openMyClaimsModal()" class="rounded-full bg-slate-900 dark:bg-white/10 hover:bg-slate-800 dark:hover:bg-white/20 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md cursor-pointer">
                    📜 My Claims History
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="mb-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">Official Rewards Catalog</p>
            <h1 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white leading-none font-space">
                AWS <span class="text-glow-gradient">Swags Store</span>
            </h1>
            <p class="mt-4 max-w-2xl text-xs sm:text-sm leading-relaxed text-slate-500 dark:text-zinc-400 font-medium">
                Redeem your hard-earned Builder PTS for exclusive AWS gear, apparel, water bottles, and accessories! Select a swag to request a claim.
            </p>
        </div>

        <!-- Store Stats Pills -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] px-4 py-3 backdrop-blur-md">
                <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Available Swags</span>
                <span class="text-lg font-black text-purple-600 dark:text-purple-400 font-space"><?php echo count($swags ?? []); ?> Items</span>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] px-4 py-3 backdrop-blur-md">
                <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Community Builders</span>
                <span class="text-lg font-black text-amber-500 font-space"><?php echo count($participants ?? []); ?> Members</span>
            </div>
        </div>
    </div>

    <!-- Feedback Banners -->
    <?php if ($success): ?>
        <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 mb-8 text-sm text-emerald-600 dark:text-emerald-400 font-bold">
            ✓ <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-2xl border border-red-500/20 bg-red-500/10 p-4 mb-8 text-sm text-red-600 dark:text-red-400 font-bold">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Controls Bar: Live Search & Sort -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-4 mb-8 shadow-sm">
        <div class="w-full sm:w-80">
            <input type="text" id="swag-search" placeholder="Search swags by name..." class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
        </div>
        
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sort:</span>
            <select id="swag-sort" class="rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0c0817] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500 cursor-pointer">
                <option value="points-asc">Required PTS (Low to High)</option>
                <option value="points-desc">Required PTS (High to Low)</option>
                <option value="name">Swag Name (A-Z)</option>
            </select>
        </div>
    </div>

    <!-- Swags Grid -->
    <?php if (empty($swags)): ?>
        <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-12 text-center">
            <p class="text-sm text-slate-500 italic">No AWS swags available in the catalog right now. Check back soon!</p>
        </div>
    <?php else: ?>
        <div id="swags-container" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($swags as $swag): ?>
                <div class="swag-card rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card flex flex-col justify-between"
                     data-name="<?php echo htmlspecialchars(strtolower($swag['name'] ?? '')); ?>"
                     data-pts="<?php echo floatval($swag['points'] ?? 0); ?>">
                    
                    <div>
                        <!-- Swag Image Header -->
                        <div class="relative w-full h-48 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 overflow-hidden flex items-center justify-center p-4 mb-5">
                            <img src="<?php echo htmlspecialchars($swag['image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($swag['name'] ?? ''); ?>" class="h-full w-full object-contain">
                            
                            <span class="absolute top-3 right-3 rounded-full bg-purple-600/90 backdrop-blur-md px-3 py-1 text-[10px] font-black uppercase tracking-wider text-white shadow-lg">
                                ⚡ <?php echo number_format($swag['points'] ?? 0, 2); ?> PTS
                            </span>
                        </div>

                        <!-- Info -->
                        <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-2">
                            <?php echo htmlspecialchars($swag['name'] ?? ''); ?>
                        </h3>
                        <p class="text-xs leading-relaxed text-slate-500 dark:text-zinc-400 mb-6">
                            <?php echo htmlspecialchars($swag['description'] ?? ''); ?>
                        </p>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-white/5">
                        <div>
                            <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Stock Status</span>
                            <span class="text-xs font-bold <?php echo ($swag['stock'] ?? 0) > 0 ? 'text-emerald-500' : 'text-red-500'; ?>">
                                <?php echo ($swag['stock'] ?? 0) > 0 ? intval($swag['stock']) . ' Available' : 'Out of Stock'; ?>
                            </span>
                        </div>

                        <?php if (($swag['stock'] ?? 0) > 0): ?>
                            <?php if ($is_member_logged_in): ?>
                                <button type="button" 
                                        data-id="<?php echo $swag['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($swag['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-pts="<?php echo floatval($swag['points'] ?? 0); ?>" 
                                        data-img="<?php echo htmlspecialchars($swag['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                        onclick="openClaimModal(this)" 
                                        class="rounded-full bg-purple-600 hover:bg-purple-500 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                                    Cart / Claim Swag
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="openGuestLoginModal()" class="rounded-full bg-purple-600/20 border border-purple-500/30 hover:bg-purple-600/30 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-purple-300 transition-all cursor-pointer">
                                    🔒 Log in to Claim
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button disabled class="rounded-full bg-slate-200 dark:bg-white/5 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 cursor-not-allowed">
                                Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- AUTHENTICATED CLAIM SWAG MODAL (Enhancement C - No Member Dropdown) -->
<div id="claim-swag-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 sm:p-8 shadow-2xl relative text-left">
        <button onclick="closeClaimModal()" class="absolute top-5 right-5 h-8 w-8 rounded-full border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
            ✕
        </button>

        <h3 class="text-xl font-black text-slate-900 dark:text-white font-space mb-1 flex items-center gap-2">
            <svg class="h-5 w-5 text-purple-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V3.5a2.5 2.5 0 115 0V8h-5zm0 0H7a2.5 2.5 0 115 0V8zm-7 0h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V10a2 2 0 012-2z"/></svg>
            Confirm Swag Claim
        </h3>
        <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6">Review your order details below. Claims are locked to your authenticated account.</p>

        <form action="store.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="claim_swag">
            <input type="hidden" name="swag_id" id="modal-swag-id">

            <!-- Swag Summary Box -->
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 p-4">
                <img id="modal-swag-img" src="" class="h-14 w-14 object-contain rounded-xl bg-white/10 p-1">
                <div>
                    <h4 id="modal-swag-name" class="text-sm font-bold text-slate-900 dark:text-white font-space"></h4>
                    <p class="text-xs font-black uppercase text-purple-600 dark:text-purple-400 mt-0.5">Costs: <span id="modal-swag-pts"></span> PTS</p>
                </div>
            </div>

            <!-- Authenticated Member Info Box -->
            <?php if ($current_member): ?>
                <?php $mb_code = $current_member['member_code'] ?? ('SBG-' . sprintf('%03d', $current_member['id'])); ?>
                <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 p-4 space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-medium">Claiming Builder:</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($current_member['name']); ?></span>
                            <span class="rounded bg-purple-500/10 border border-purple-500/20 px-1.5 py-0.5 text-[9px] font-mono font-bold text-purple-400">
                                ID: <?php echo htmlspecialchars($mb_code); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400 font-medium">Current PTS Balance:</span>
                        <span class="font-bold text-amber-500">⚡ <?php echo number_format($current_member['points'], 2); ?> PTS</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Eligibility Alert Box -->
            <div id="points-status-box" class="rounded-2xl p-3.5 text-xs font-bold border hidden">
                <span id="points-status-text"></span>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" id="submit-claim-btn" class="flex-grow rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                    Submit Claim Request
                </button>
                <button type="button" onclick="closeClaimModal()" class="rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 px-5 py-3 text-xs font-bold text-slate-600 dark:text-zinc-400 cursor-pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- GUEST LOGIN PROMPT MODAL (Enhancement C) -->
<div id="guest-prompt-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 sm:p-8 shadow-2xl relative text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 text-2xl">
            🔒
        </div>
        <h3 class="text-xl font-black text-slate-900 dark:text-white font-space mb-2">Builder Account Required</h3>
        <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6 leading-relaxed">
            You must sign in with your AWS Student Builder Group member account to claim swags using your earned PTS points.
        </p>
        <div class="flex flex-col gap-2.5">
            <a href="login.php" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 text-center">
                Sign In with Builder Account →
            </a>
            <button type="button" onclick="closeGuestLoginModal()" class="w-full rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 py-2.5 text-xs font-bold text-slate-600 dark:text-zinc-400 cursor-pointer">
                Continue Browsing
            </button>
        </div>
    </div>
</div>

<!-- MY CLAIMS HISTORY MODAL (Enhancement B) -->
<?php if ($is_member_logged_in && $current_member): ?>
    <?php 
    $my_requests = array_filter($swag_requests ?? [], function($r) use ($current_member) {
        return $r['member_id'] == $current_member['id'];
    });
    ?>
    <div id="my-claims-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm transition-opacity duration-300">
        <div class="w-full max-w-xl rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 sm:p-8 shadow-2xl relative text-left">
            <button onclick="closeMyClaimsModal()" class="absolute top-5 right-5 h-8 w-8 rounded-full border border-slate-200 dark:border-white/10 flex items-center justify-center text-slate-500 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                ✕
            </button>

            <h3 class="text-xl font-black text-slate-900 dark:text-white font-space mb-1">📜 My Swag Claim Requests</h3>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6">Track the status of all swag redemption requests submitted from your account.</p>

            <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2 no-scrollbar">
                <?php if (empty($my_requests)): ?>
                    <p class="text-xs text-slate-500 italic p-6 text-center">You haven't requested any swags yet. Browse the catalog and claim your gear!</p>
                <?php else: ?>
                    <?php foreach ($my_requests as $req): ?>
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.02] p-4 text-xs">
                            <div class="flex items-center gap-3">
                                <img src="<?php echo htmlspecialchars($req['swag_image'] ?? ''); ?>" class="h-10 w-10 rounded-xl object-contain bg-white/10 p-1">
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($req['swag_name'] ?? ''); ?></h4>
                                    <p class="text-[10px] text-slate-500">Points Spent: <span class="text-amber-500 font-bold"><?php echo number_format($req['points_spent'] ?? 0, 2); ?> PTS</span></p>
                                </div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-wider <?php echo ($req['status'] ?? '') === 'fulfilled' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : (($req['status'] ?? '') === 'rejected' ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'); ?>">
                                <?php echo ucfirst($req['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
const currentMemberPts = <?php echo floatval($current_member['points'] ?? 0); ?>;

function openClaimModal(btn) {
    const swagId = btn.getAttribute('data-id');
    const swagName = btn.getAttribute('data-name');
    const swagPts = parseFloat(btn.getAttribute('data-pts'));
    const swagImg = btn.getAttribute('data-img');

    document.getElementById('modal-swag-id').value = swagId;
    document.getElementById('modal-swag-name').innerText = swagName;
    document.getElementById('modal-swag-pts').innerText = swagPts.toFixed(2);
    document.getElementById('modal-swag-img').src = swagImg;

    const statusBox = document.getElementById('points-status-box');
    const statusText = document.getElementById('points-status-text');
    const submitBtn = document.getElementById('submit-claim-btn');

    statusBox.classList.remove('hidden', 'bg-emerald-500/10', 'border-emerald-500/30', 'text-emerald-400', 'bg-red-500/10', 'border-red-500/30', 'text-red-400');

    if (currentMemberPts >= swagPts) {
        statusBox.classList.add('bg-emerald-500/10', 'border-emerald-500/30', 'text-emerald-400');
        statusText.innerHTML = `✓ Eligible! Your balance: <strong>${currentMemberPts.toFixed(2)} PTS</strong>. Remaining after claim: <strong>${(currentMemberPts - swagPts).toFixed(2)} PTS</strong>.`;
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        statusBox.classList.add('bg-red-500/10', 'border-red-500/30', 'text-red-400');
        statusText.innerHTML = `⚠️ Insufficient points! Your balance: <strong>${currentMemberPts.toFixed(2)} PTS</strong>. You need <strong>${(swagPts - currentMemberPts).toFixed(2)} PTS</strong> more.`;
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }

    const modal = document.getElementById('claim-swag-modal');
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeClaimModal() {
    const modal = document.getElementById('claim-swag-modal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

function openGuestLoginModal() {
    const modal = document.getElementById('guest-prompt-modal');
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeGuestLoginModal() {
    const modal = document.getElementById('guest-prompt-modal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

function openMyClaimsModal() {
    const modal = document.getElementById('my-claims-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeMyClaimsModal() {
    const modal = document.getElementById('my-claims-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

// Live Search & Sort
const searchInput = document.getElementById('swag-search');
const sortSelect = document.getElementById('swag-sort');

if (searchInput) {
    searchInput.addEventListener('input', filterAndSortSwags);
}
if (sortSelect) {
    sortSelect.addEventListener('change', filterAndSortSwags);
}

function filterAndSortSwags() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const sortBy = sortSelect ? sortSelect.value : 'points-asc';
    const container = document.getElementById('swags-container');
    const cards = Array.from(document.querySelectorAll('.swag-card'));

    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });

    cards.sort((a, b) => {
        const ptsA = parseFloat(a.getAttribute('data-pts'));
        const ptsB = parseFloat(b.getAttribute('data-pts'));
        const nameA = a.getAttribute('data-name');
        const nameB = b.getAttribute('data-name');

        if (sortBy === 'points-asc') return ptsA - ptsB;
        if (sortBy === 'points-desc') return ptsB - ptsA;
        if (sortBy === 'name') return nameA.localeCompare(nameB);
        return 0;
    });

    if (container) {
        cards.forEach(card => container.appendChild(card));
    }
}
</script>

<?php
require_once 'includes/footer.php';
?>
