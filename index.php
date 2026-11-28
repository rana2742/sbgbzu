<?php
// index.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Handle Community Day Event Signup Form Submission
$signup_error = '';
$signup_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'event_signup') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $university = trim($_POST['university'] ?? '');
    $eventName = trim($_POST['event_name'] ?? 'AWS Student Community Day Multan 2026');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['is_ajax']);

    if (empty($name) || empty($email) || empty($university)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Please provide your Full Name, Email, and University.']);
            exit;
        }
        $signup_error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
            exit;
        }
        $signup_error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO `event_signups` (`name`, `email`, `university`, `event_name`, `ip_address`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $university, $eventName, $ip]);

            // Query total registrations (base 582 + count)
            $dbSignups = (int)$db->query("SELECT COUNT(*) FROM `event_signups` WHERE `event_name` = 'AWS Student Community Day Multan 2026'")->fetchColumn();
            $totalCount = 582 + $dbSignups;

            $_SESSION['community_day_registered'] = true;
            $_SESSION['community_day_name'] = $name;

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total_count' => $totalCount,
                    'meetup_url' => 'https://meetu.ps/e/QljqK/1fyQWj/i',
                    'message' => 'Registration successful! Your spot is reserved. Now you can click Join Us to complete your Meetup RSVP.'
                ]);
                exit;
            }
            $signup_success = 'Registration successful! Your spot is reserved.';
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
            $signup_error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle verified attendee card generation.
$card_app_error = '';
$card_app_success = '';
if (empty($_SESSION['attendee_form_csrf'])) {
    $_SESSION['attendee_form_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_attendee_card') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['is_ajax']);
    $respond = static function (array $payload) use ($isAjax): void {
        if ($isAjax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload); exit; }
    };
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['attendee_form_csrf'], $csrf)) {
        $msg = 'Your form session expired. Refresh the page and try again.';
        $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
    } elseif ($name === '' || strlen($name) > 210 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $msg = 'Please enter a valid name and email address.';
        $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Please upload a JPG, PNG or WEBP photo (maximum 5MB).';
        $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
    } else {
        $file = $_FILES['image'];
        $tmp = $file['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $imageInfo = @getimagesize($tmp);
        if ($file['size'] > 5 * 1024 * 1024 || !in_array($mime, $allowedMimes, true) || !$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $msg = 'The uploaded file is not a supported image or exceeds 5MB.';
            $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
        } elseif (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            $msg = 'Card generation is temporarily unavailable. Please contact the event team.';
            $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
        } else {
            try {
                // Lock the verified email row so concurrent requests cannot claim different names.
                $db->beginTransaction();
                $check = $db->prepare("SELECT id, attendee_name FROM attendee_eligibility WHERE email = ? AND status = 'verified' LIMIT 1 FOR UPDATE");
                $check->execute([$email]);
                $eligibility = $check->fetch(PDO::FETCH_ASSOC);
                if (!$eligibility) {
                    $db->rollBack();
                    $msg = 'We could not verify a paid registration for this email. Please contact the event administration team.';
                    $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
                } else {
                    $normalizeName = static function (string $value): string {
                        $value = preg_replace('/\\s+/u', ' ', trim($value));
                        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
                    };
                    $savedName = trim((string) ($eligibility['attendee_name'] ?? ''));
                    if ($savedName !== '' && $normalizeName($savedName) !== $normalizeName($name)) {
                        $db->rollBack();
                        $msg = 'You are trying to cheat! This verified email is already locked to a different attendee name. Please use your own verified registration email and details.';
                        $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
                    }
                    if ($savedName === '') {
                        $lockName = $db->prepare("UPDATE attendee_eligibility SET attendee_name = ? WHERE id = ? AND (attendee_name IS NULL OR attendee_name = '')");
                        $lockName->execute([$name, $eligibility['id']]);
                    }
                    $photo = @imagecreatefromstring(file_get_contents($tmp));
                    $templatePath = __DIR__ . '/public/images/attendee_cards/card_template.jpg';
                    if (!$photo || !is_file($templatePath)) throw new RuntimeException('The attendee card template is not installed on the server.');
                    $card = @imagecreatefromjpeg($templatePath);
                    if (!$card) throw new RuntimeException('Card template is invalid.');


                    // Fit the uploaded portrait inside the upper photo window.
                    // Extend the photo to meet the lower name/QR panel with no visible gap.
                    // Coordinates are for the current 1080x1170 card template.
                    // Place the attendee photo only inside the purple front-card photo window.
                    $photoX = 382; $photoY = 325; $photoW = 379; $photoH = 342;
                    $srcW = imagesx($photo); $srcH = imagesy($photo);
                    $scale = max($photoW / $srcW, $photoH / $srcH);
                    $cropW = (int) round($photoW / $scale); $cropH = (int) round($photoH / $scale);
                    $srcX = (int) max(0, ($srcW - $cropW) / 2); $srcY = (int) max(0, ($srcH - $cropH) / 2);
                    $photoLayer = imagecreatetruecolor($photoW, $photoH);
                    imagecopyresampled($photoLayer, $photo, 0, 0, $srcX, $srcY, $photoW, $photoH, $cropW, $cropH);
                    // Make the photo layer transparent outside rounded top corners.
                    // This reveals the original card template beneath the photo (not white patches).
                    $cornerRadius = 34;
                    $transparent = imagecolorallocatealpha($photoLayer, 0, 0, 0, 127);
                    imagealphablending($photoLayer, false);
                    imagesavealpha($photoLayer, true);

                    for ($y = 0; $y < $cornerRadius; $y++) {
                        for ($x = 0; $x < $cornerRadius; $x++) {
                            $dy = $cornerRadius - $y;

                            $leftDx = $cornerRadius - $x;
                            $leftDistance = sqrt(($leftDx * $leftDx) + ($dy * $dy));
                            if ($leftDistance > $cornerRadius - 1) {
                                $alpha = (int) min(127, max(0, round(127 * ($leftDistance - ($cornerRadius - 1)))));
                                $pixel = imagecolorallocatealpha($photoLayer, 0, 0, 0, $alpha);
                                imagesetpixel($photoLayer, $x, $y, $pixel);
                            }

                            $rightX = $photoW - $cornerRadius + $x;
                            $rightDistance = sqrt(($x * $x) + ($dy * $dy));
                            if ($rightDistance > $cornerRadius - 1) {
                                $alpha = (int) min(127, max(0, round(127 * ($rightDistance - ($cornerRadius - 1)))));
                                $pixel = imagecolorallocatealpha($photoLayer, 0, 0, 0, $alpha);
                                imagesetpixel($photoLayer, $rightX, $y, $pixel);
                            }
                        }
                    }

                    imagealphablending($photoLayer, true);
                    imagealphablending($card, true);
                    imagecopy($card, $photoLayer, $photoX, $photoY, 0, 0, $photoW, $photoH);

                    // Replace the website URL with the attendee name in the left panel beside the QR code.
                    // Keep the QR code and its vertical divider clear.
                    $namePanelWhite = imagecolorallocate($card, 255, 255, 255);
                    imagefilledrectangle($card, 382, 668, 635, 795, $namePanelWhite);

                    $ink = imagecolorallocate($card, 55, 0, 125);
                    $font = null;
                    foreach (['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf'] as $candidate) {
                        if (is_file($candidate)) { $font = $candidate; break; }
                    }
                    $cleanName = preg_replace('/\\s+/u', ' ', $name);

                    if ($font && function_exists('imagettftext')) {
                        $fontSize = 28;
                        $maxTextWidth = 228;
                        $box = imagettfbbox($fontSize, 0, $font, $cleanName);
                        while ($fontSize > 14 && ($box[2] - $box[0]) > $maxTextWidth) {
                            $fontSize--;
                            $box = imagettfbbox($fontSize, 0, $font, $cleanName);
                        }
                        if (($box[2] - $box[0]) <= $maxTextWidth) {
                            $textX = (int) round(508 - (($box[2] + $box[0]) / 2));
                            imagettftext($card, $fontSize, 0, $textX, 758, $ink, $font, $cleanName);
                        } else {
                            $words = preg_split('/\\s+/u', $cleanName);
                            $lines = []; $line = '';
                            foreach ($words as $word) {
                                $candidate = $line === '' ? $word : $line . ' ' . $word;
                                $candidateBox = imagettfbbox(18, 0, $font, $candidate);
                                if ($line !== '' && ($candidateBox[2] - $candidateBox[0]) > $maxTextWidth) {
                                    $lines[] = $line;
                                    $line = $word;
                                } else {
                                    $line = $candidate;
                                }
                            }
                            if ($line !== '') $lines[] = $line;
                            foreach (array_slice($lines, 0, 3) as $i => $lineText) {
                                $lineBox = imagettfbbox(18, 0, $font, $lineText);
                                $textX = (int) round(508 - (($lineBox[2] + $lineBox[0]) / 2));
                                imagettftext($card, 18, 0, $textX, 724 + ($i * 25), $ink, $font, $lineText);
                            }
                        }
                    } else {
                        imagestring($card, 4, 420, 758, substr($cleanName, 0, 30), $ink);
                    }

                    // Add a fresh QR code whose payload is always the SBGZU homepage.
                    $qrPath = __DIR__ . '/public/images/attendee_cards/homepage_qr.png';
                    if (!is_file($qrPath)) throw new RuntimeException('The homepage QR code asset is missing.');
                    $qrImage = @imagecreatefrompng($qrPath);
                    if (!$qrImage) throw new RuntimeException('The homepage QR code image is invalid.');
                    // The template already contains a printed sample QR. Clear that entire QR area first,
                    // otherwise its lower portion remains visible beneath the generated attendee QR.
                    $qrWhite = imagecolorallocate($card, 255, 255, 255);
                    // Erase only the original QR artwork; stop above the printed caption.
                    // Clear the full printed sample QR, including its lower modules, while stopping above the caption.
                    imagefilledrectangle($card, 662, 680, 746, 752, $qrWhite);

                    // Draw exactly one crisp QR inside the template's QR square.
                    // Keep the original "Get Yours!" caption below it untouched.
                    // Keep a clear gap above the original caption so 'Get Yours!' remains fully visible.
                    // Keep the replacement QR fully above the caption baseline.
                    // Render a larger QR with nearest-neighbour scaling to keep module edges crisp.
                    imagecopyresized($card, $qrImage, 670, 674, 0, 0, 68, 68, imagesx($qrImage), imagesy($qrImage));
                    imagedestroy($qrImage);

                    $privateDir = dirname(__DIR__) . '/sbg-private/attendee_cards';
                    if (!is_dir($privateDir) && !mkdir($privateDir, 0750, true) && !is_dir($privateDir)) throw new RuntimeException('Could not create private card storage.');
                    $token = bin2hex(random_bytes(32));
                    $filename = $token . '.png';
                    $outputPath = $privateDir . '/' . $filename;
                    // Lossless PNG avoids JPEG artifacts around text, photo edges, and QR modules.
                    // Compression level 1 is lossless and keeps generation fast.
                    if (!imagepng($card, $outputPath, 1, PNG_ALL_FILTERS)) throw new RuntimeException('Could not save generated attendee card.');
                    chmod($outputPath, 0640);
                    $_SESSION['attendee_card_token'] = $token;
                    $_SESSION['attendee_card_file'] = $filename;
                    imagedestroy($photo); imagedestroy($photoLayer); imagedestroy($card);
                    $db->commit();
                    $respond([
                        'success' => true,
                        'message' => 'Your payment is verified. Your attendee card is ready!',
                        'preview_url' => 'attendee_card_file.php?token=' . rawurlencode($token),
                        'download_url' => 'attendee_card_file.php?token=' . rawurlencode($token) . '&download=1'
                    ]);
                    $card_app_success = 'Your attendee card is ready.';
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('Attendee card generation failed: ' . $e->getMessage());
                $msg = 'We could not generate your card right now. Please try again later.';
                $respond(['success' => false, 'error' => $msg]); $card_app_error = $msg;
            }
        }
    }
}

// Fetch current count for Community Day (Base: 582)
$dbSignups = 0;
try {
    $dbSignups = (int)$db->query("SELECT COUNT(*) FROM `event_signups` WHERE `event_name` = 'AWS Student Community Day Multan 2026'")->fetchColumn();
} catch (Exception $e) {}
$communityDayCount = 582 + $dbSignups;
$isRegistered = !empty($_SESSION['community_day_registered']);

require_once 'includes/header.php';

// Find Star of the Month details
$star_member = null;
if (isset($highlights['starOfMonthId'])) {
    foreach ($participants as $p) {
        if ($p['id'] == $highlights['starOfMonthId']) {
            $star_member = $p;
            break;
        }
    }
}

// Find Monthly Grinders details
$grinders = [];
if (isset($highlights['monthlyGrinders'])) {
    foreach ($highlights['monthlyGrinders'] as $gid) {
        foreach ($participants as $p) {
            if ($p['id'] == $gid) {
                $grinders[] = $p;
                break;
            }
        }
    }
}

// Calculate some insights
$total_points = 0;
$team_set = [];
foreach ($participants as $p) {
    $total_points += $p['points'];
    $team_set[$p['team']] = true;
}
$active_teams = count($team_set);

$stat_rows = [
    [
        'label' => 'Student Builders',
        'value' => count($participants),
        'max' => 40,
        'color' => 'from-purple-500 to-fuchsia-500',
        'display' => count($participants) . '+'
    ],
    [
        'label' => 'Monthly Grinders',
        'value' => count($grinders),
        'max' => 5,
        'color' => 'from-purple-500 to-indigo-500',
        'display' => count($grinders)
    ],
    [
        'label' => 'Partners Collaboration',
        'value' => 4,
        'max' => 10,
        'color' => 'from-indigo-400 to-purple-300',
        'display' => '4'
    ],
    [
        'label' => 'Campus Chapters',
        'value' => 2,
        'max' => 5,
        'color' => 'from-purple-300 to-purple-600',
        'display' => '2'
    ],
    [
        'label' => 'Active Teams',
        'value' => $active_teams,
        'max' => 10,
        'color' => 'from-indigo-400 to-fuchsia-500',
        'display' => $active_teams
    ]
];
?>

<?php if (!empty($announcements)): ?>
<style>
    .announce-sticky-wrap {
        position: sticky;
        top: 76px;
        z-index: 35;
        width: 100%;
        max-width: 1440px;
        margin: 8px auto 16px auto;
        padding: 0 16px;
        pointer-events: auto;
    }
    @media (min-width: 1024px) {
        .announce-sticky-wrap {
            top: 84px;
            padding: 0 24px;
        }
    }
    .announce-bar {
        overflow: hidden;
        white-space: nowrap;
        background: linear-gradient(90deg, #dc2626 0%, #ea580c 50%, #f97316 100%);
        color: #ffffff;
        padding: 8px 14px;
        border-radius: 16px;
        box-shadow: 0 6px 25px -4px rgba(220, 38, 38, 0.45), 0 2px 10px rgba(234, 88, 12, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .announce-badge {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(0, 0, 0, 0.35);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #ffffff;
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        padding: 4px 10px;
        border-radius: 9999px;
    }
    .announce-pulse-dot {
        width: 6px;
        height: 6px;
        border-radius: 9999px;
        background: #fef08a;
        box-shadow: 0 0 8px #fde047;
        animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    .announce-track-wrap {
        flex: 1;
        overflow: hidden;
        position: relative;
        mask-image: linear-gradient(90deg, transparent 0%, black 2%, black 98%, transparent 100%);
        -webkit-mask-image: linear-gradient(90deg, transparent 0%, black 2%, black 98%, transparent 100%);
    }
    .announce-track {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        animation: announce-scroll 24s linear infinite;
        will-change: transform;
    }
    .announce-sticky-wrap:hover .announce-track {
        animation-play-state: paused;
    }
    .announce-item {
        display: inline-flex;
        align-items: center;
        padding: 0 20px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #ffffff;
    }
    .announce-sep {
        color: rgba(255, 255, 255, 0.6);
        font-weight: 900;
        font-size: 11px;
    }
    @keyframes announce-scroll {
        0% { transform: translateX(0%); }
        100% { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
        .announce-track { animation: none; }
    }
</style>
<div class="announce-sticky-wrap" role="region" aria-label="Announcements">
    <div class="announce-bar">
        <div class="announce-badge">
            <span class="announce-pulse-dot"></span>
            <span>Announcement</span>
        </div>
        <div class="announce-track-wrap">
            <div class="announce-track">
                <?php for ($rep = 0; $rep < 4; $rep++): ?>
                    <?php foreach ($announcements as $a): ?>
                        <span class="announce-item"><?= htmlspecialchars($a['message']) ?></span>
                        <span class="announce-sep">•</span>
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ========================================================================= -->
<!-- FULL-SCREEN EVENT POST: AWS STUDENT COMMUNITY DAY MULTAN 2026              -->
<!-- ========================================================================= -->
<section id="community-day-post" class="relative w-full min-h-[calc(100vh-80px)] lg:min-h-screen flex items-center justify-center py-10 lg:py-16 px-4 sm:px-6 lg:px-10 overflow-hidden bg-gradient-to-br from-[#12072b] via-[#1b0a3b] to-[#0c041c] text-white border-b border-purple-500/20">

    <!-- Ambient glowing backdrop lighting -->
    <div class="pointer-events-none absolute -top-32 -left-32 w-96 h-96 rounded-full bg-purple-600/30 blur-[120px]"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-32 w-96 h-96 rounded-full bg-amber-500/25 blur-[120px]"></div>
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(168,85,247,0.12),transparent_70%)]"></div>

    <div class="mx-auto max-w-[1440px] w-full relative z-10">
        <div class="grid gap-10 lg:grid-cols-12 items-center">
            
            <!-- Left Column: Poster, Story, Perks & Details (7 cols) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Ribbon & Eyebrow Badge -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/15 border border-amber-500/30 px-3.5 py-1.5 text-[10px] font-black uppercase tracking-widest text-amber-300 shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-amber-400 animate-pulse"></span>
                        Biggest Community Event in Multan!
                    </span>
                    <span class="rounded-full bg-purple-500/20 border border-purple-500/30 px-3 py-1 text-[10px] font-bold text-purple-200">
                        15 DEC 2026 • BZU Multan
                    </span>
                </div>

                <!-- Main Post Title -->
                <div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-[1.15] font-space text-white">
                        Multan, Are You Ready? <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-pink-400 to-purple-300">
                            The Biggest Cloud Event Is Coming!
                        </span>
                    </h1>
                    <p class="mt-3 text-sm sm:text-base font-bold text-purple-200 leading-relaxed">
                        The wait is finally over!
                    </p>
                    <p class="mt-1 text-xs sm:text-sm text-slate-300 leading-relaxed font-medium">
                        AWS Student Builder Group BZU is proudly bringing <strong class="text-white font-bold">AWS Student Community Day Multan 2026</strong> to Bahauddin Zakariya University! This is not just an event, it's a full day of learning, networking, innovation, and fun with the AWS Community.
                    </p>
                </div>

                <!-- Poster Preview + Highlights Grid -->
                <div class="flex flex-col sm:flex-row gap-5 items-center sm:items-start p-4 sm:p-5 rounded-3xl bg-white/[0.03] border border-white/10 backdrop-blur-md">
                    
                    <!-- Poster Thumbnail -->
                    <div class="relative group rounded-2xl overflow-hidden border border-purple-500/30 bg-purple-950/40 shadow-xl w-44 sm:w-48 shrink-0 cursor-pointer" onclick="openCommunityPosterModal()">
                        <img src="public/images/community_day_2026.jpg" alt="AWS Student Community Day Multan 2026 Poster" class="w-full h-auto object-cover group-hover:scale-105 transition-all duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center p-2.5">
                            <span class="text-[9px] font-black uppercase text-amber-300 bg-black/60 px-2 py-1 rounded-full border border-amber-400/30">
                                Click to Enlarge
                            </span>
                        </div>
                    </div>

                    <!-- What is waiting for you (List) -->
                    <div class="flex-1 space-y-2.5 min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-widest text-amber-400 font-space">
                            Here is what is waiting for you:
                        </p>
                        <div class="grid grid-cols-1 gap-2">
                            <div class="flex items-center gap-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 px-3.5 py-2 text-xs font-semibold text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span>Exclusive AWS Swag Pack</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 px-3.5 py-2 text-xs font-semibold text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span>Inspiring Tech Talks by Industry Experts</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 px-3.5 py-2 text-xs font-semibold text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span>Networking Opportunities with Developers & Students</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 px-3.5 py-2 text-xs font-semibold text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span>Fun Activities & Contests</span>
                            </div>
                            <div class="flex items-center gap-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 px-3.5 py-2 text-xs font-semibold text-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span>Tea & Refreshments</span>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-purple-200 font-medium italic">
                    Whether you are a beginner in Cloud Computing or already building on AWS, this event is for YOU!
                </p>

                <!-- Event Details: Date & Venue -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400 text-xs font-black uppercase border border-amber-500/30">
                            DATE
                        </div>
                        <div>
                            <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Date</span>
                            <span class="text-xs sm:text-sm font-black text-white font-space">15 December 2026</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3.5 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-purple-500/20 text-purple-400 text-xs font-black uppercase border border-purple-500/30">
                            VENUE
                        </div>
                        <div>
                            <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400">Venue</span>
                            <span class="text-xs sm:text-sm font-black text-white font-space">Bahauddin Zakariya University (BZU), Multan</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Compulsory Registration Form & Join Us Action (5 cols) -->
            <div class="lg:col-span-5">
                <div id="signup-card" class="rounded-3xl border-2 border-purple-500/30 bg-slate-900/95 dark:bg-[#0e0821]/95 backdrop-blur-2xl p-6 sm:p-8 shadow-2xl relative transition-all duration-300">
                    
                    <!-- Seat Availability Progress Bar (Matches Image 1) -->
                    <div class="pb-5 border-b border-white/10 mb-5">
                        <div class="flex items-center justify-between text-[11px] sm:text-xs font-black uppercase tracking-wider mb-2.5">
                            <span class="text-slate-400 tracking-[0.16em]">Seat Availability</span>
                            <span class="text-amber-400 font-space tracking-wide">Filling Fast (<span id="live-reg-count"><?= $communityDayCount ?></span>+ Registered)</span>
                        </div>
                        <div class="w-full h-2 sm:h-2.5 rounded-full bg-slate-800/90 dark:bg-white/10 overflow-hidden p-0.5 shadow-inner">
                            <div id="seat-progress-bar" class="h-full rounded-full bg-gradient-to-r from-purple-600 via-pink-500 to-amber-500 transition-all duration-700" style="width: <?= min(96, max(20, round(($communityDayCount / 650) * 100))) ?>%;"></div>
                        </div>
                    </div>

                    <!-- Form Header -->
                    <div class="mb-4">
                        <h3 class="text-xl font-black text-white font-space">Register for Community Day</h3>
                        <p class="text-xs text-slate-300 mt-1">
                            Enter your Name, Email, and University to connect to the SignUp database and unlock the Meetup RSVP button.
                        </p>
                    </div>

                    <!-- Feedback Alert Container -->
                    <div id="signup-alert-box" class="hidden rounded-2xl p-3.5 mb-4 text-xs font-bold"></div>

                    <!-- Registration Form -->
                    <form id="community-day-form" method="POST" onsubmit="handleCommunityDaySignup(event)" class="space-y-4">
                        <input type="hidden" name="action" value="event_signup">
                        <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['attendee_form_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['attendee_form_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="event_name" value="AWS Student Community Day Multan 2026">

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Full Name
                            </label>
                            <input type="text" name="name" id="reg-name" required placeholder="Enter your full name" class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-400 outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/20 transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                Email Address
                            </label>
                            <input type="email" name="email" id="reg-email" required placeholder="Enter your email address" class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-400 outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/20 transition-all">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                                University Name
                            </label>
                            <input type="text" name="university" id="reg-university" required placeholder="Enter your university name" class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-400 outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/20 transition-all">
                        </div>

                        <button type="submit" id="reg-submit-btn" class="w-full rounded-2xl bg-gradient-to-r from-purple-600 to-fuchsia-600 hover:from-purple-500 hover:to-fuchsia-500 py-3.5 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-purple-600/30 transition-all cursor-pointer flex items-center justify-center gap-2">
                            <span>Submit Registration</span>
                        </button>
                    </form>

                    <!-- Join Us Action Scenario -->
                    <div class="mt-6 pt-5 border-t border-white/10 space-y-3">
                        <!-- Locked Join Us Button (Default before submission) -->
                        <button type="button" id="join-us-locked-btn" onclick="triggerLockedJoinPrompt()" class="w-full py-4 rounded-2xl bg-slate-800 border border-white/10 text-slate-400 font-black text-xs uppercase tracking-widest cursor-not-allowed flex items-center justify-center gap-2 transition-all hover:bg-slate-750">
                            <span>Join Us</span>
                        </button>

                        <!-- Unlocked Join Us Button (Active after submission) -->
                        <a id="join-us-unlocked-btn" href="https://meetu.ps/e/QljqK/1fyQWj/i" target="_blank" rel="noopener noreferrer" class="hidden w-full py-4 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:scale-[1.02] text-white font-black text-xs uppercase tracking-widest shadow-xl shadow-orange-500/40 flex items-center justify-center gap-2 transition-all text-center">
                            <span>Join Us on Meetup &rarr;</span>
                        </a>

                        <!-- Beside / Underneath Meetup Link -->
                        <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/5 flex flex-col sm:flex-row items-center justify-between gap-1 text-center sm:text-left">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Meetup Link:</span>
                            <a href="https://meetu.ps/e/QljqK/1fyQWj/i" id="meetup-url-display" class="text-[11px] font-mono text-purple-300 hover:text-amber-300 underline break-all transition-colors" onclick="return handleRawLinkClick(event)">https://meetu.ps/e/QljqK/1fyQWj/i</a>
                        </div>

                        <!-- Get your Attendee Card Action -->
                        <div class="pt-2 border-t border-white/10">
                            <button type="button" onclick="openAttendeeCardModal()" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-purple-600 via-indigo-600 to-fuchsia-600 hover:from-purple-500 hover:to-fuchsia-500 text-white font-black text-xs uppercase tracking-widest shadow-lg shadow-purple-600/30 transition-all cursor-pointer flex items-center justify-center gap-2 border border-purple-400/30 hover:scale-[1.01]">
                                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.169.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.337 0z"/></svg>
                                <span>Get your Attendee Card</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- Poster Zoom Lightbox Modal -->
<div id="poster-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md hidden opacity-0 transition-opacity duration-300" onclick="closeCommunityPosterModal()">
    <div class="relative max-w-lg w-full bg-slate-900 rounded-3xl overflow-hidden border border-purple-500/40 shadow-2xl" onclick="event.stopPropagation()">
        <button onclick="closeCommunityPosterModal()" class="absolute top-3 right-3 h-8 w-8 rounded-full bg-black/60 text-white font-bold flex items-center justify-center hover:bg-black/90 border border-white/20 z-10 cursor-pointer">✕</button>
        <img src="public/images/community_day_2026.jpg" alt="AWS Student Community Day Multan 2026 Full Poster" class="w-full h-auto object-contain max-h-[85vh]">
    </div>
</div>

<!-- Attendee Card Application Modal -->
<div id="attendee-card-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md hidden opacity-0 transition-opacity duration-300" onclick="closeAttendeeCardModal()">
    <div class="relative max-w-3xl w-full max-h-[92vh] overflow-y-auto bg-slate-900 border-2 border-purple-500/40 rounded-3xl p-6 sm:p-8 shadow-2xl" onclick="event.stopPropagation()">
        
        <!-- Glowing ambient background -->
        <div class="pointer-events-none absolute -top-20 -right-20 w-48 h-48 rounded-full bg-purple-600/30 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-20 w-48 h-48 rounded-full bg-amber-500/20 blur-3xl"></div>

        <!-- Close Button -->
        <button type="button" onclick="closeAttendeeCardModal()" class="absolute top-4 right-4 h-8 w-8 rounded-full bg-white/10 text-white font-bold flex items-center justify-center hover:bg-white/20 border border-white/20 z-10 cursor-pointer transition-all">✕</button>

        <div class="relative z-10 space-y-4">
            
            <!-- Modal Header -->
            <div class="border-b border-white/10 pb-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-[9px] font-black uppercase tracking-widest mb-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    Attendee Card
                </span>
                <h3 class="text-xl sm:text-2xl font-black text-white font-space">
                    Get your Attendee Card
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Enter your name, verified registration email and photo. Your email must be marked as paid by the event team.
                </p>
            </div>

            <!-- Feedback Alert Box -->
            <div id="card-app-alert" class="hidden rounded-2xl p-4 text-xs font-bold leading-relaxed" role="status" aria-live="polite"></div>
            <div id="attendee-card-result" class="hidden space-y-3">
                <h4 class="text-sm font-black text-white">Your generated attendee card</h4>
                <img id="attendee-card-result-image" src="" alt="Generated attendee card preview" class="w-full max-h-[65vh] object-contain rounded-xl border border-purple-400/30 bg-black/20">
                <a id="attendee-card-download" href="#" class="block w-full rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 px-5 py-3.5 text-center text-xs font-black uppercase tracking-widest text-white shadow-lg">Download Attendee Card</a>
            </div>

            <!-- Application Form -->
            <form id="attendee-card-form" method="POST" enctype="multipart/form-data" onsubmit="handleAttendeeCardSubmit(event)" class="space-y-4">
                <input type="hidden" name="action" value="apply_attendee_card">
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['attendee_form_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Full Name
                    </label>
                    <input type="text" name="name" id="card-app-name" required placeholder="Enter same name as meetup" class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-400 outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Email Address
                    </label>
                    <input type="email" name="email" id="card-app-email" required placeholder="Enter same email address as meetup" class="w-full rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-400 outline-none focus:border-purple-400 focus:ring-2 focus:ring-purple-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-300 mb-1.5">
                        Your Photo / Image
                    </label>
                    <div class="flex items-center gap-3">
                        <div id="card-photo-preview-wrap" class="h-12 w-12 rounded-xl border border-white/20 bg-black/40 overflow-hidden shrink-0 flex items-center justify-center">
                            <img id="card-photo-preview" src="" class="hidden h-full w-full object-cover">
                            <span id="card-photo-placeholder" class="text-[10px] text-slate-500">Photo</span>
                        </div>
                        <input type="file" name="image" id="card-app-photo" required accept="image/png,image/jpeg,image/webp" onchange="previewCardPhoto(event)" class="w-full text-xs text-slate-400 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-purple-600 file:text-white hover:file:bg-purple-500 cursor-pointer">
                    </div>
                    <span class="block text-[9px] text-slate-400 mt-1">Accepts PNG, JPG, JPEG, WEBP (Max 5MB).</span>
                </div>

                <button type="submit" id="card-app-submit-btn" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-purple-600 via-fuchsia-600 to-amber-500 hover:opacity-95 text-white font-black text-xs uppercase tracking-widest shadow-xl shadow-purple-600/30 transition-all cursor-pointer flex items-center justify-center gap-2">
                    <span>Verify & Generate Card</span>
                </button>
            </form>

        </div>

    </div>
</div>

<!-- 1. HERO GREETING BLOCK -->
<section class="relative px-4 sm:px-6 pt-12 pb-8 overflow-hidden">
    <div class="mx-auto max-w-[1440px] relative z-10">
        <div class="grid gap-12 lg:grid-cols-12 items-center">
            
            <!-- Left content: Text & Badge -->
            <div class="lg:col-span-7 space-y-6">
                <div class="flex flex-col gap-3">
                    <div class="inline-flex items-center gap-2">
                        <span class="rounded-full bg-purple-500/10 border border-purple-500/20 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-350">
                            AWS Student Builder Group • BZU Multan
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 px-3 py-1 text-[9px] font-bold text-slate-600 dark:text-zinc-400">
                            First Ever SBG Chapter in Multan
                        </span>
                        <span class="rounded-full bg-purple-500/10 border border-purple-500/20 px-3 py-1 text-[9px] font-black text-purple-600 dark:text-purple-350">
                            First Ever Silver Community
                        </span>
                    </div>
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.1] text-slate-900 dark:text-white font-space">
                    Empowering innovation <br class="hidden sm:block">through the power of <span class="text-glow-gradient">AWS.</span>
                </h1>
                <p class="text-xs sm:text-sm leading-relaxed text-slate-650 dark:text-zinc-400 max-w-xl font-medium">
                    A student-led platform for team profiles, leaderboards, and collaboration portfolios. We bridge post-secondary learning with modern cloud infrastructure to build cloud careers.
                </p>
                <div class="flex flex-wrap gap-3 pt-2">
                    <a href="team.php" class="reflective-card rounded-full bg-purple-600 hover:bg-purple-500 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:scale-[1.02] transform transition-all shadow-lg shadow-purple-500/25">
                        Meet the Builders
                    </a>
                    <a href="events.php" class="reflective-card rounded-full border border-slate-300 dark:border-white/10 bg-white/5 hover:bg-white/10 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-white hover:scale-[1.02] transform transition-all">
                        Explore Events
                    </a>
                </div>
            </div>

            <!-- Right content: Mehdi Hassan (Leader) Photo Block -->
            <div class="lg:col-span-5 flex justify-center">
                <div class="relative">
                    <div class="absolute -inset-6 rounded-full bg-purple-500/10 blur-2xl pointer-events-none"></div>
                    <div class="relative">
                        <!-- rotating border decal -->
                        <div class="absolute -inset-4 rounded-full border-2 border-dashed border-purple-500/20 animate-[spin_35s_linear_infinite] pointer-events-none"></div>
                        <img src="public/images/AWS-MembersPics/Mehdi Hassan.jpeg" alt="Mehdi Hassan" class="h-64 w-64 sm:h-72 sm:w-72 rounded-full border-4 border-slate-200 dark:border-purple-650 object-cover shadow-xl">
                        
                        <!-- Top-right BZU Multan badge -->
                        <div class="absolute -right-4 top-8 rounded-full bg-white border border-slate-200/50 px-4 py-2 flex items-center gap-1.5 shadow-lg z-20">
                            <span class="flex items-center justify-center bg-purple-600 rounded-full w-4 h-4 p-0.5 shadow-sm">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span class="text-[10px] sm:text-[11px] font-black tracking-wide text-purple-700">BZU Multan</span>
                        </div>

                        <!-- Left-stacked glassmorphic stickers -->
                        <div class="absolute -left-8 sm:-left-12 top-10 sm:top-14 h-16 w-16 sm:h-20 sm:w-20 rounded-2xl backdrop-blur-xl bg-white/10 dark:bg-white/5 border border-white/20 shadow-lg flex items-center justify-center p-3 z-20 animate-float">
                            <img src="public/images/stickers/cloud.png" alt="Cloud Sticker" class="w-full h-full object-contain">
                        </div>
                        <div class="absolute -left-6 sm:-left-10 top-[110px] sm:top-[160px] h-16 w-16 sm:h-20 sm:w-20 rounded-2xl backdrop-blur-xl bg-white/10 dark:bg-white/5 border border-white/20 shadow-lg flex items-center justify-center p-3.5 z-20 animate-float" style="animation-delay: 2s;">
                            <img src="public/images/stickers/aws.png" alt="AWS Sticker" class="w-full h-full object-contain">
                        </div>

                        <!-- Right-side 1st In Multan floating card -->
                        <div class="absolute -right-8 top-[110px] sm:top-[150px] bg-white border border-slate-200/50 rounded-2xl shadow-xl px-5 py-4 text-center z-20 w-24 sm:w-28">
                            <span class="block text-2xl sm:text-3xl font-black text-fuchsia-600 leading-none">1st</span>
                            <span class="block text-[9px] sm:text-[10px] font-black text-slate-700 uppercase tracking-wider mt-1.5 leading-none">In Multan</span>
                        </div>

                        <!-- Bottom-left Leader pill badge -->
                        <div class="absolute -bottom-4 -left-4 rounded-full bg-gradient-to-r from-purple-600 to-fuchsia-600 px-5 py-2.5 flex items-center gap-1.5 shadow-lg text-white z-20">
                            <svg class="w-3 h-3 fill-current text-white animate-pulse" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="text-[10px] font-black uppercase tracking-widest leading-none">Leader</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- 2. COMMUNITY BADGES -->
<section class="px-4 sm:px-6 py-6">
    <div class="mx-auto max-w-[1440px]">
        <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-8 shadow-lg neon-glow-active">
            <div class="text-center sm:text-left mb-8">
                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-purple-600 dark:text-purple-400">Official Status Recognition</span>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white font-space mt-1">Community Recognition Badges</h3>
            </div>
            
            <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <?php foreach ($badges as $badge): ?>
                    <div class="flex flex-col items-center text-center p-6 rounded-2xl bg-slate-50/30 dark:bg-white/[0.01] border border-slate-200/50 dark:border-white/5 hover:border-purple-500/30 transition-all duration-300 group hover:-translate-y-1">
                        <div class="relative w-28 h-28 mb-4 flex items-center justify-center shrink-0">
                            <div class="absolute -inset-4 rounded-full bg-purple-500/10 blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                            <div class="animate-[float_4s_ease-in-out_infinite] w-24 h-24 flex items-center justify-center text-slate-350">
                                <?php 
                                    $isSvg = pathinfo($badge['image'], PATHINFO_EXTENSION) === 'svg';
                                    if ($isSvg && file_exists($badge['image'])) {
                                        echo file_get_contents($badge['image']);
                                    } else {
                                        echo '<img src="' . htmlspecialchars($badge['image']) . '" alt="' . htmlspecialchars($badge['name']) . '" class="w-full h-full object-contain">';
                                    }
                                ?>
                            </div>
                        </div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white font-space leading-tight mt-2"><?php echo htmlspecialchars($badge['name']); ?></h4>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- 2. BUILDERS SPOTLIGHT PANEL (STAR & GRINDERS HIGHLIGHT AT THE TOP) -->
<section class="px-4 sm:px-6 py-4">
    <div class="mx-auto max-w-[1440px]">
        <div class="grid gap-6 lg:grid-cols-12 items-stretch">
            
            <!-- Star of the Month Card (Glow card) -->
            <div class="lg:col-span-7 rounded-3xl p-6 relative overflow-hidden flex flex-col justify-between shadow-lg tier-gold reflective-card transition-all duration-300">
                <!-- Aligned crown badge in top-right corner -->
                <div class="absolute top-5 right-5 h-10 w-10 rounded-2xl bg-amber-500/15 border border-amber-500/30 text-amber-500 shadow-sm flex items-center justify-center shrink-0 pointer-events-none">
                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>
                </div>
                
                <div>
                    <span class="text-[9px] font-black uppercase tracking-[0.28em] text-amber-600 dark:text-amber-400 flex items-center gap-1.5 mb-6">
                        <svg class="h-3 w-3 fill-current text-amber-500 shrink-0" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        STAR OF THE MONTH (<?php echo $highlights['monthLabel']; ?>)
                    </span>
                    
                    <?php if ($star_member): ?>
                        <a href="#" class="member-modal-trigger flex flex-col sm:flex-row gap-5 items-center sm:items-start text-center sm:text-left group"
                           data-id="<?php echo $star_member['id']; ?>"
                           data-name="<?php echo htmlspecialchars($star_member['name']); ?>"
                           data-role="<?php echo htmlspecialchars($star_member['role']); ?>"
                           data-team="<?php echo htmlspecialchars($star_member['team']); ?>"
                           data-level="<?php echo htmlspecialchars($star_member['level']); ?>"
                           data-campus="<?php echo htmlspecialchars($star_member['campus']); ?>"
                           data-points="<?php echo htmlspecialchars($star_member['points']); ?>"
                           data-responsibilities="<?php echo htmlspecialchars($star_member['responsibilities']); ?>"
                           data-img="<?php echo htmlspecialchars($star_member['image']); ?>"
                           data-rank="0">
                            
                            <img src="<?php echo $star_member['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" alt="<?php echo $star_member['name']; ?>" class="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl object-cover border border-amber-500 shadow-md group-hover:scale-105 transition-transform duration-300">
                            
                            <div class="min-w-0 flex-grow pt-1">
                                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white group-hover:text-purple-650 dark:group-hover:text-amber-400 transition-colors font-space"><?php echo $star_member['name']; ?></h3>
                                <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1"><?php echo $star_member['role']; ?></p>
                                <div class="mt-4 flex flex-wrap gap-1.5 justify-center sm:justify-start">
                                    <span class="rounded-full bg-purple-500/10 border border-purple-500/20 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-300">
                                        <?php echo $star_member['team']; ?> Team
                                    </span>
                                    <span class="rounded-full bg-slate-200 dark:bg-white/5 border border-slate-300 dark:border-white/10 px-2.5 py-0.5 text-[8px] font-black uppercase tracking-widest text-slate-700 dark:text-white/80">
                                        <?php echo $star_member['level']; ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php else: ?>
                        <p class="text-xs font-medium text-zinc-500 italic">Not announced yet.</p>
                    <?php endif; ?>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-200 dark:border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium">Honoring outstanding commitment to student orientation.</p>
                    <a href="leaderboard.php" class="rounded-full bg-purple-600 hover:bg-purple-500 px-5 py-2.5 text-[9px] font-black uppercase tracking-widest text-white hover:scale-[1.02] transform transition-all shrink-0">
                        View leaderboard →
                    </a>
                </div>
            </div>

            <!-- Monthly Grinders Card -->
            <div class="lg:col-span-5 rounded-3xl p-6 flex flex-col justify-between shadow-lg tier-silver reflective-card transition-all duration-300">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-300 flex items-center gap-1.5 mb-5">
                        <svg class="h-3.5 w-3.5 text-purple-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>
                        MONTHLY GRINDERS LIST
                    </span>
                    
                    <div class="grid gap-2.5">
                        <?php foreach ($grinders as $idx => $g): ?>
                            <a href="#" class="member-modal-trigger flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] px-4 py-3 hover:bg-slate-100 dark:hover:bg-white/[0.06] transition-all duration-300 group"
                               data-id="<?php echo $g['id']; ?>"
                               data-name="<?php echo htmlspecialchars($g['name']); ?>"
                               data-role="<?php echo htmlspecialchars($g['role']); ?>"
                               data-team="<?php echo htmlspecialchars($g['team']); ?>"
                               data-level="<?php echo htmlspecialchars($g['level']); ?>"
                               data-campus="<?php echo htmlspecialchars($g['campus']); ?>"
                               data-points="<?php echo htmlspecialchars($g['points']); ?>"
                               data-responsibilities="<?php echo htmlspecialchars($g['responsibilities']); ?>"
                               data-img="<?php echo htmlspecialchars($g['image']); ?>"
                               data-rank="0">
                                
                                <span class="text-xs font-black text-purple-500 dark:text-purple-550/50 w-4"><?php echo $idx + 1; ?></span>
                                <img src="<?php echo $g['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" class="h-10 w-10 rounded-xl object-cover border border-slate-200 dark:border-white/10" alt="">
                                <div class="min-w-0 flex-grow">
                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors"><?php echo $g['name']; ?></p>
                                    <p class="truncate text-[9px] text-slate-505 dark:text-zinc-550 mt-0.5"><?php echo $g['role']; ?></p>
                                </div>
                                <span class="text-sm font-black text-slate-900 dark:text-white"><?php echo number_format($g['points']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-5 border-t border-slate-200 dark:border-white/5 mt-6">
                    <p class="text-[9px] text-slate-450 dark:text-zinc-500 uppercase tracking-widest text-center">Top grinders updated weekly</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- 3. HORIZONTAL DYNAMIC INSIGHT STATS (MOBILE-FRIENDLY SWIPE DECK) -->
<section class="px-4 sm:px-6 py-4">
    <div class="mx-auto max-w-[1440px]">
        <div class="flex items-center justify-between mb-4">
            <span class="text-[10px] font-black uppercase tracking-[0.24em] text-purple-600 dark:text-purple-400"> Ecosystem Metrics</span>
            <span class="text-[9px] font-bold text-slate-450 uppercase tracking-wider hidden sm:inline-block">Swipe horizontally ↔</span>
        </div>
        
        <!-- Swipeable Row deck (WITH VERTICAL ROOM FOR SMOOTH HOVER TRANSITION) -->
        <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory no-scrollbar py-3 px-1">
            <?php foreach ($stat_rows as $row): 
                $percentage = min(100, ($row['value'] / $row['max']) * 100);
            ?>
                <div class="snap-start shrink-0 w-[240px] sm:w-[280px] rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-5 shadow-md relative overflow-hidden hover-glow-card transition-all duration-300 ease-out">
                    <div class="flex justify-between items-end mb-4">
                        <span class="text-[10px] font-black text-slate-500 dark:text-zinc-400 uppercase tracking-widest"><?php echo $row['label']; ?></span>
                        <span class="text-xl font-black text-slate-900 dark:text-white"><?php echo $row['display']; ?></span>
                    </div>
                    <!-- custom progress bar inside glass panel -->
                    <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r <?php echo $row['color']; ?>" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 4. COMMUNITY LEADERSHIP & VISION -->
<section class="relative overflow-hidden px-4 sm:px-6 py-12">
    <div class="mx-auto max-w-[1440px]">
        <div class="grid gap-12 lg:grid-cols-2 items-center">
            
            <!-- Tech Illustration (Aligned to Left) -->
            <div class="flex justify-start order-2 lg:order-1">
                <div class="relative w-full max-w-lg">
                    <!-- Glowing backgrounds -->
                    <div class="absolute -inset-4 rounded-3xl bg-purple-500/10 blur-xl pointer-events-none"></div>
                    <div class="relative rounded-3xl border border-slate-200 dark:border-white/10 overflow-hidden bg-slate-950 shadow-2xl">
                        <img src="public/images/cloud_architecture_illustration.png" alt="AWS Cloud Infrastructure Illustration" class="w-full h-auto object-cover opacity-90 hover:scale-105 transition-transform duration-500">
                    </div>
                </div>
            </div>

            <!-- Vision text block -->
            <div class="order-1 lg:order-2 space-y-6">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">Chapter Vision</span>
                    <h2 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white leading-tight font-space">First <span class="text-glow-gradient">AWS Student Builder</span> Chapter in Multan</h2>
                    <p class="mt-4 text-sm sm:text-base md:text-lg leading-relaxed text-slate-650 dark:text-zinc-350 font-semibold">
                        Bridging post-secondary learning with modern cloud infrastructure. Our chapter fosters builder collaboration, and prepares students for cloud careers.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.01] p-6 shadow-sm hover-glow-card">
                    <h4 class="text-base sm:text-lg font-black text-slate-900 dark:text-white mb-3 font-space flex items-center gap-2">
                        <svg class="h-5 w-5 text-purple-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z" /></svg>
                        Real Hands-on Development
                    </h4>
                    <p class="text-xs sm:text-sm md:text-base text-slate-600 dark:text-zinc-350 leading-relaxed font-semibold">
                        We skip tutorial-only approaches to construct production-ready configurations using S3, CloudFormation, Cognito, and API Gateway.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- 5. LATEST BLOG NOTICES (BUTTONS COMPLETELY REMOVED!) -->
<section class="px-4 sm:px-6 py-12">
    <div class="mx-auto max-w-[1440px]">
        <div class="flex items-end justify-between border-b border-slate-200 dark:border-white/10 pb-5 mb-8">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">Bulletin Updates</p>
                <h3 class="text-2xl font-black text-slate-900 dark:text-white font-space">Latest Notices</h3>
            </div>
            <a href="blog.php" class="text-xs font-bold text-purple-600 dark:text-purple-300 hover:underline">
                All announcements →
            </a>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php
            $notices = array_slice($posts, 0, 3);
            foreach ($notices as $post):
            ?>
                <article class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-sm hover-glow-card">
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400">
                            <?php echo $post['category']; ?> / <?php echo $post['date']; ?>
                        </span>
                        <h4 class="text-lg font-black text-slate-900 dark:text-white font-space mt-3 mb-2 leading-tight"><?php echo $post['title']; ?></h4>
                        <p class="text-xs text-slate-600 dark:text-zinc-400 leading-relaxed font-medium">
                            <?php echo $post['excerpt']; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 6. PARTNERS MARQUEE LOOP (AT THE BOTTOM) -->
<section class="border-t border-slate-200 dark:border-white/10 py-12 overflow-hidden bg-slate-100/50 dark:bg-black/20">
    <div class="mx-auto max-w-[1440px] px-4 sm:px-6">
        <div class="mb-6">
            <p class="text-center text-[10px] font-black uppercase tracking-[0.24em] text-slate-500">Campus Chapter Partners</p>
        </div>
        
        <!-- Marquee loop container (CONSTRAINED TO SITE WIDTH) -->
        <div class="w-full overflow-hidden relative py-4 rounded-3xl">
            <div class="flex gap-6 w-max animate-marquee items-center">
                <?php
                if (!empty($partners)) {
                    // Loop array duplication to ensure seamless visual loop
                    $marquee_loop = array_merge($partners, $partners, $partners);
                    foreach ($marquee_loop as $partner):
                    ?>
                        <div class="flex items-center gap-4 rounded-3xl border border-slate-200 dark:border-white/10 bg-white/90 dark:bg-white/[0.03] px-6 py-4 min-w-[280px] sm:min-w-[320px] shrink-0 shadow-md backdrop-blur-md hover:border-purple-500/40 transition-all">
                            <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-white dark:bg-white p-2 flex items-center justify-center shrink-0 border border-slate-200/80 dark:border-white/20 shadow-sm">
                                <img src="<?php echo htmlspecialchars($partner['logo']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" class="max-h-full max-w-full object-contain">
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm sm:text-base font-black text-slate-900 dark:text-white font-space truncate"><?php echo htmlspecialchars($partner['name']); ?></p>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 mt-0.5">Official Partner</p>
                            </div>
                        </div>
                    <?php 
                    endforeach;
                } else {
                    echo '<p class="text-xs text-slate-500 italic py-2 text-center w-full">No active partners listed.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</section>

<!-- 5.5 CAMPUS VOLUNTEERS CAROUSEL (center spotlight) -->
<?php if (!empty($volunteers)): ?>
<style>
    .vc-track { transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1); will-change: transform; }
    .vc-item { opacity: 0.45; transform: translateY(0) scale(0.82); transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.5s ease; cursor: pointer; }
    .vc-item.is-active { opacity: 1; transform: translateY(-10px) scale(1.06); cursor: default; z-index: 5; }
    .vc-item .vc-photo { transition: box-shadow 0.5s ease, border-color 0.5s ease; }
    .vc-item.is-active .vc-photo { border-color: rgba(168, 85, 247, 0.7); box-shadow: 0 20px 45px -12px rgba(147, 51, 234, 0.55); }
    .vc-item .vc-info { opacity: 0; transform: translateY(8px); transition: opacity 0.5s ease 0.1s, transform 0.5s ease 0.1s; }
    .vc-item.is-active .vc-info { opacity: 1; transform: translateY(0); }
    @media (prefers-reduced-motion: reduce) {
        .vc-track, .vc-item, .vc-item .vc-photo, .vc-item .vc-info { transition: none; }
    }
</style>
<section class="border-t border-slate-200 dark:border-white/10 py-8 bg-slate-100/50 dark:bg-black/20 overflow-hidden">
    <div class="mx-auto max-w-[1440px] px-4 sm:px-6">
        <div class="text-center mb-2">
            <p class="text-[9px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">The People Behind The Scenes</p>
            <h3 class="mt-1 text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-space">Campus Volunteers</h3>
        </div>

        <div id="vol-carousel" class="relative" tabindex="0" aria-label="Campus volunteers carousel">
            <?php if (count($volunteers) > 1): ?>
                <button type="button" id="vc-prev" aria-label="Previous volunteer" class="absolute left-0 sm:left-4 top-[34%] z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full border border-purple-500/50 bg-purple-600/10 hover:bg-purple-600 hover:text-white text-purple-600 dark:text-purple-300 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button type="button" id="vc-next" aria-label="Next volunteer" class="absolute right-0 sm:right-4 top-[34%] z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full border border-purple-500/50 bg-purple-600/10 hover:bg-purple-600 hover:text-white text-purple-600 dark:text-purple-300 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            <?php endif; ?>

            <div class="vc-viewport overflow-hidden pt-8 pb-2 [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                <div class="vc-track relative flex w-max">
                    <?php foreach ($volunteers as $vol): ?>
                        <div class="vc-item shrink-0 w-[110px] sm:w-[130px] md:w-[150px] mx-2 sm:mx-3">
                            <div class="relative">
                                <!-- decorative circles -->
                                <span class="absolute -left-4 top-2 h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-purple-300/60 dark:bg-purple-900/60"></span>
                                <span class="absolute -right-2 -top-2 h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-purple-400/80"></span>
                                <span class="absolute -right-3 bottom-3 h-7 w-7 sm:h-9 sm:w-9 rounded-full bg-purple-500/70"></span>

                                <div class="vc-photo relative aspect-[3/4] w-full overflow-hidden rounded-[999px] border-2 border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-[#0c0817]">
                                    <img src="<?php echo htmlspecialchars($vol['photo']); ?>" alt="<?php echo htmlspecialchars($vol['name']); ?>" loading="lazy" draggable="false" class="h-full w-full object-cover object-top">
                                </div>
                            </div>
                            <div class="vc-info mt-4 min-h-[84px] px-1 text-center">
                                <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white font-space leading-tight"><?php echo htmlspecialchars($vol['name']); ?></h4>
                                <span class="mt-2 inline-block rounded-full bg-purple-500/10 border border-purple-500/20 px-3 py-1 text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-300"><?php echo htmlspecialchars($vol['team']); ?></span>
                                                                <?php if (!empty($vol['university'])): ?>
                                    <p class="mt-2 text-[10px] sm:text-xs font-semibold text-slate-500 dark:text-zinc-400 leading-tight"><?php echo htmlspecialchars($vol['university']); ?></p>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const root = document.getElementById('vol-carousel');
    if (!root) return;
    const viewport = root.querySelector('.vc-viewport');
    const track = root.querySelector('.vc-track');
    const items = Array.prototype.slice.call(track.querySelectorAll('.vc-item'));
    const prev = document.getElementById('vc-prev');
    const next = document.getElementById('vc-next');
    const n = items.length;
    let i = Math.floor((n - 1) / 2); // start on the middle volunteer

    function update() {
        const el = items[i];
        const center = el.offsetLeft + el.offsetWidth / 2;
        track.style.transform = 'translateX(' + (viewport.clientWidth / 2 - center) + 'px)';
        items.forEach(function (item, k) {
            item.classList.toggle('is-active', k === i);
        });
    }
    function go(k) {
        i = (k + n) % n;
        update();
    }

    if (prev) prev.addEventListener('click', function () { go(i - 1); });
    if (next) next.addEventListener('click', function () { go(i + 1); });
    items.forEach(function (item, k) {
        item.addEventListener('click', function () { if (k !== i) go(k); });
    });

    // Keyboard arrows
    root.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') go(i - 1);
        if (e.key === 'ArrowRight') go(i + 1);
    });

    // Touch swipe
    let startX = null;
    viewport.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    viewport.addEventListener('touchend', function (e) {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) go(dx < 0 ? i + 1 : i - 1);
        startX = null;
    }, { passive: true });

    window.addEventListener('resize', update);
    window.addEventListener('load', update);
    update();
})();
</script>
<?php endif; ?>

<!-- 5.6 MEETUP GUESTS CAROUSEL (center spotlight, compact) -->
<?php if (!empty($meetup_guests)): ?>
<style>
    .mgc-track { transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1); will-change: transform; }
    .mgc-item { opacity: 0.45; transform: translateY(0) scale(0.82); transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.5s ease; cursor: pointer; }
    .mgc-item.is-active { opacity: 1; transform: translateY(-10px) scale(1.06); cursor: default; z-index: 5; }
    .mgc-item .mgc-photo { transition: box-shadow 0.5s ease, border-color 0.5s ease; }
    .mgc-item.is-active .mgc-photo { border-color: rgba(168, 85, 247, 0.7); box-shadow: 0 20px 45px -12px rgba(147, 51, 234, 0.55); }
    .mgc-item .mgc-info { opacity: 0; transform: translateY(8px); transition: opacity 0.5s ease 0.1s, transform 0.5s ease 0.1s; }
    .mgc-item.is-active .mgc-info { opacity: 1; transform: translateY(0); }
    @media (prefers-reduced-motion: reduce) {
        .mgc-track, .mgc-item, .mgc-item .mgc-photo, .mgc-item .mgc-info { transition: none; }
    }
</style>
<section class="border-t border-slate-200 dark:border-white/10 py-8 bg-slate-100/50 dark:bg-black/20 overflow-hidden">
    <div class="mx-auto max-w-[1440px] px-4 sm:px-6">
        <div class="text-center mb-2">
            <p class="text-[9px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">Voices From Our Meetups</p>
            <h3 class="mt-1 text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-space">Meet Our Speakers</h3>
        </div>

        <div id="guest-carousel" class="relative" tabindex="0" aria-label="Meetup guests carousel">
            <?php if (count($meetup_guests) > 1): ?>
                <button type="button" id="mgc-prev" aria-label="Previous guest" class="absolute left-0 sm:left-4 top-[34%] z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full border border-purple-500/50 bg-purple-600/10 hover:bg-purple-600 hover:text-white text-purple-600 dark:text-purple-300 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button type="button" id="mgc-next" aria-label="Next guest" class="absolute right-0 sm:right-4 top-[34%] z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full border border-purple-500/50 bg-purple-600/10 hover:bg-purple-600 hover:text-white text-purple-600 dark:text-purple-300 flex items-center justify-center backdrop-blur-md transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
            <?php endif; ?>

            <div class="mgc-viewport overflow-hidden pt-8 pb-2 [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                <div class="mgc-track relative flex w-max">
                    <?php foreach ($meetup_guests as $guest): ?>
                        <div class="mgc-item shrink-0 w-[110px] sm:w-[130px] md:w-[150px] mx-2 sm:mx-3">
                            <div class="relative">
                                <span class="absolute -left-4 top-2 h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-purple-300/60 dark:bg-purple-900/60"></span>
                                <span class="absolute -right-2 -top-2 h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-purple-400/80"></span>
                                <span class="absolute -right-3 bottom-3 h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-purple-500/70"></span>

                                <div class="mgc-photo relative aspect-[3/4] w-full overflow-hidden rounded-[999px] border-2 border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-[#0c0817]">
                                    <img src="<?php echo htmlspecialchars($guest['photo']); ?>" alt="<?php echo htmlspecialchars($guest['name']); ?>" loading="lazy" draggable="false" class="h-full w-full object-cover object-top">
                                </div>
                            </div>
                            <div class="mgc-info mt-4 min-h-[60px] px-1 text-center">
                                <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white font-space leading-tight"><?php echo htmlspecialchars($guest['name']); ?></h4>
                                <span class="mt-2 inline-block rounded-full bg-purple-500/10 border border-purple-500/20 px-3 py-1 text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-300"><?php echo htmlspecialchars($guest['designation']); ?></span>
                                <?php if (!empty($guest['company'])): ?>
                                    <p class="mt-1 text-[10px] sm:text-xs font-semibold text-slate-500 dark:text-zinc-400 leading-tight"><?php echo htmlspecialchars($guest['company']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const root = document.getElementById('guest-carousel');
    if (!root) return;
    const viewport = root.querySelector('.mgc-viewport');
    const track = root.querySelector('.mgc-track');
    const items = Array.prototype.slice.call(track.querySelectorAll('.mgc-item'));
    const prev = document.getElementById('mgc-prev');
    const next = document.getElementById('mgc-next');
    const n = items.length;
    let i = Math.floor((n - 1) / 2); // start on the middle guest

    function update() {
        const el = items[i];
        const center = el.offsetLeft + el.offsetWidth / 2;
        track.style.transform = 'translateX(' + (viewport.clientWidth / 2 - center) + 'px)';
        items.forEach(function (item, k) {
            item.classList.toggle('is-active', k === i);
        });
    }
    function go(k) {
        i = (k + n) % n;
        update();
    }

    if (prev) prev.addEventListener('click', function () { go(i - 1); });
    if (next) next.addEventListener('click', function () { go(i + 1); });
    items.forEach(function (item, k) {
        item.addEventListener('click', function () { if (k !== i) go(k); });
    });

    root.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') go(i - 1);
        if (e.key === 'ArrowRight') go(i + 1);
    });

    let startX = null;
    viewport.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    viewport.addEventListener('touchend', function (e) {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) go(dx < 0 ? i + 1 : i - 1);
        startX = null;
    }, { passive: true });

    window.addEventListener('resize', update);
    window.addEventListener('load', update);
    update();
})();
</script>
<?php endif; ?>
<!-- Client-Side Scripts for Community Day Post & Registration Arena -->
<script>
let hasRegisteredForCommunityDay = <?= $isRegistered ? 'true' : 'false' ?> || localStorage.getItem('sbg_community_day_registered') === '1';

function updateCommunityDayUIState() {
    const lockedBtn = document.getElementById('join-us-locked-btn');
    const unlockedBtn = document.getElementById('join-us-unlocked-btn');
    const notice = document.getElementById('join-us-notice');

    if (hasRegisteredForCommunityDay) {
        if (lockedBtn) lockedBtn.classList.add('hidden');
        if (unlockedBtn) unlockedBtn.classList.remove('hidden');
        if (notice) {
            notice.className = 'rounded-2xl bg-emerald-500/15 border border-emerald-500/30 p-3 text-[11px] text-emerald-300 leading-snug flex items-start gap-2.5';
            notice.innerHTML = '<span><strong>Spot Reserved:</strong> You have filled the form. Click the <em>Join Us</em> button above to complete your Meetup RSVP.</span>';
        }
    } else {
        if (lockedBtn) lockedBtn.classList.remove('hidden');
        if (unlockedBtn) unlockedBtn.classList.add('hidden');
    }
}

function triggerLockedJoinPrompt() {
    const alertBox = document.getElementById('signup-alert-box');
    const card = document.getElementById('signup-card');
    const nameInput = document.getElementById('reg-name');

    if (alertBox) {
        alertBox.className = 'rounded-2xl p-3.5 mb-4 text-xs font-bold bg-amber-500/20 border border-amber-500/40 text-amber-300 animate-pulse';
        alertBox.innerHTML = '<strong>Registration Required:</strong> Please enter your Full Name, Email, and University to register and access Join Us.';
        alertBox.classList.remove('hidden');
    }

    if (card) {
        card.classList.add('ring-4', 'ring-amber-500/50');
        setTimeout(() => card.classList.remove('ring-4', 'ring-amber-500/50'), 1500);
    }

    if (nameInput) {
        nameInput.focus();
        nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function handleRawLinkClick(e) {
    if (!hasRegisteredForCommunityDay) {
        e.preventDefault();
        triggerLockedJoinPrompt();
        return false;
    }
    return true;
}

function handleCommunityDaySignup(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('reg-submit-btn');
    const alertBox = document.getElementById('signup-alert-box');
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const university = form.university.value.trim();

    if (!name || !email || !university) {
        triggerLockedJoinPrompt();
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Connecting to SignUp Database...</span>';

    const formData = new FormData(form);

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Submit Registration</span>';

        if (data.success) {
            hasRegisteredForCommunityDay = true;
            localStorage.setItem('sbg_community_day_registered', '1');

            // Increment and animate counter smoothly
            const countEl = document.getElementById('live-reg-count');
            if (countEl && data.total_count) {
                countEl.innerText = data.total_count;
                countEl.classList.add('text-emerald-400', 'scale-125');
                setTimeout(() => countEl.classList.remove('scale-125'), 600);
            }
            const progressBar = document.getElementById('seat-progress-bar');
            if (progressBar && data.total_count) {
                const pct = Math.min(96, Math.max(20, Math.round((data.total_count / 650) * 100)));
                progressBar.style.width = pct + '%';
            }

            if (alertBox) {
                alertBox.className = 'rounded-2xl p-3.5 mb-4 text-xs font-bold bg-emerald-500/20 border border-emerald-500/40 text-emerald-300';
                alertBox.innerHTML = '<strong>Registration Successful!</strong> Your spot is secured in SignUp database. Opening Meetup now...';
                alertBox.classList.remove('hidden');
            }

            // Unlock the Join Us button
            updateCommunityDayUIState();

            // Open Meetup URL automatically
            window.open('https://meetu.ps/e/QljqK/1fyQWj/i', '_blank');
        } else {
            if (alertBox) {
                alertBox.className = 'rounded-2xl p-3.5 mb-4 text-xs font-bold bg-red-500/20 border border-red-500/40 text-red-300';
                alertBox.innerHTML = data.error || 'Failed to submit registration. Please try again.';
                alertBox.classList.remove('hidden');
            }
        }
    })
    .catch(err => {
        console.error(err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Submit Registration</span>';
        if (alertBox) {
            alertBox.className = 'rounded-2xl p-3.5 mb-4 text-xs font-bold bg-red-500/20 border border-red-500/40 text-red-300';
            alertBox.innerHTML = 'Network error. Please try again.';
            alertBox.classList.remove('hidden');
        }
    });
}

function openCommunityPosterModal() {
    const modal = document.getElementById('poster-modal');
    if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.remove('opacity-0'), 10);
    }
}

function closeCommunityPosterModal() {
    const modal = document.getElementById('poster-modal');
    if (modal) {
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCommunityPosterModal();
        closeAttendeeCardModal();
    }
});

function openAttendeeCardModal() {
    const modal = document.getElementById('attendee-card-modal');
    if (modal) {
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.remove('opacity-0'), 10);
    }
}

function closeAttendeeCardModal() {
    const modal = document.getElementById('attendee-card-modal');
    if (modal) {
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
}

function previewCardPhoto(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('card-photo-preview');
    const placeholder = document.getElementById('card-photo-placeholder');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            if (preview && placeholder) {
                preview.src = evt.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            }
        };
        reader.readAsDataURL(file);
    }
}

function handleAttendeeCardSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('card-app-submit-btn');
    const alertBox = document.getElementById('card-app-alert');
    const result = document.getElementById('attendee-card-result');
    const preview = document.getElementById('attendee-card-result-image');
    const download = document.getElementById('attendee-card-download');
    if (result) result.classList.add('hidden');
    if (alertBox) alertBox.classList.add('hidden');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Checking payment & generating card...</span>';
    fetch('index.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Verify & Generate Card</span>';
        if (data.success) {
            if (alertBox) {
                alertBox.className = 'rounded-2xl p-4 text-xs font-bold bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 leading-relaxed';
                alertBox.textContent = data.message;
                alertBox.classList.remove('hidden');
            }
            if (preview && download && result) {
                preview.src = data.preview_url + '&v=' + Date.now();
                download.href = data.download_url;
                result.classList.remove('hidden');
                form.classList.add('hidden');
                result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } else if (alertBox) {
            form.classList.remove('hidden');
            alertBox.className = 'rounded-2xl p-4 text-xs font-bold bg-red-500/20 border border-red-500/40 text-red-300 leading-relaxed';
            alertBox.textContent = data.error || 'We could not verify your registration.';
            alertBox.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error(error);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Verify & Generate Card</span>';
        form.classList.remove('hidden');
        if (alertBox) {
            alertBox.className = 'rounded-2xl p-4 text-xs font-bold bg-red-500/20 border border-red-500/40 text-red-300 leading-relaxed';
            alertBox.textContent = 'Network error. Please try again.';
            alertBox.classList.remove('hidden');
        }
    });
}

document.addEventListener('DOMContentLoaded', updateCommunityDayUIState);
</script>

<?php
require_once 'includes/footer.php';
?>
