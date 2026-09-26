<?php
// admin.php
require_once 'includes/auth.php';
check_auth(true); // Forces admin auth before any HTML output to avoid "Headers already sent" runtime warning

require_once 'includes/header.php';

$success = '';
$error = '';

// Check and create upload directories
$uploadDir = 'public/images/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$badgeUploadDir = 'public/images/badges/';
if (!is_dir($badgeUploadDir)) {
    mkdir($badgeUploadDir, 0755, true);
}
$partnerUploadDir = 'public/images/partners/';
if (!is_dir($partnerUploadDir)) {
    mkdir($partnerUploadDir, 0755, true);
}
$swagUploadDir = 'public/images/swags/';
if (!is_dir($swagUploadDir)) {
    mkdir($swagUploadDir, 0755, true);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add_member') {
            $name = trim($_POST['name'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $team = $_POST['team'] ?? 'Technical';
            $allowed_levels = ['Core Team', 'Directorate', 'Manager', 'Lead', 'Member'];
            $level = in_array($_POST['level'] ?? '', $allowed_levels) ? $_POST['level'] : 'Member';
            $points = floatval($_POST['points'] ?? 0);
            $campus = trim($_POST['campus'] ?? '') ?: 'BZU';
            $responsibilities = trim($_POST['responsibilities'] ?? '');
            
            if (empty($name) || empty($password)) {
                $error = "Full Name and Password are required to create a builder account.";
            } else {
                // Automatically assign the lowest available reusable Member ID (SBG-001, SBG-002, ...).
                $usedCodes = $db->query("SELECT `member_code` FROM `members` WHERE `member_code` IS NOT NULL AND `member_code` <> ''")->fetchAll(PDO::FETCH_COLUMN);
                $usedIds = [];
                foreach ($usedCodes as $code) {
                    if (preg_match('/^SBG-(\\d+)$/i', trim((string)$code), $match)) {
                        $usedIds[(int)$match[1]] = true;
                    }
                }
                $nextMemberNumber = 1;
                while (isset($usedIds[$nextMemberNumber])) {
                    $nextMemberNumber++;
                }
                $member_code = 'SBG-' . str_pad((string)$nextMemberNumber, 3, '0', STR_PAD_LEFT);

                // Handle image upload
                    $imagePath = 'public/images/AWS-MembersPics/default.png';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $filename = time() . '_' . basename($_FILES['image']['name']);
                        $targetFile = 'public/images/AWS-MembersPics/' . $filename;
                        
                        // Ensure directory exists
                        if (!is_dir('public/images/AWS-MembersPics/')) {
                            mkdir('public/images/AWS-MembersPics/', 0755, true);
                        }
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                            $imagePath = $targetFile;
                        }
                    }

                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO `members` (`member_code`, `name`, `role`, `team`, `level`, `points`, `campus`, `responsibilities`, `image`, `password`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$member_code, $name, $role, $team, $level, $points, $campus, $responsibilities, $imagePath, $passHash]);
                    $success = "Builder '$name' (Member ID: <strong>$member_code</strong>) added successfully with automatically assigned login credentials.";
                }
            
        } elseif ($action === 'reset_member_password') {
            $member_id = intval($_POST['member_id']);
            $new_pass = trim($_POST['new_password'] ?? '');
            if (!empty($new_pass)) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE `members` SET `password` = ? WHERE `id` = ?");
                $stmt->execute([$new_hash, $member_id]);
                $success = "Password updated successfully.";
            } else {
                $error = "Please enter a valid password.";
            }
            
        } elseif ($action === 'update_points') {
            $member_id = intval($_POST['member_id']);
            $points = floatval($_POST['points']);
            
            $stmt = $db->prepare("UPDATE `members` SET `points` = ? WHERE `id` = ?");
            $stmt->execute([$points, $member_id]);
            $success = "Points updated successfully.";
            
        } elseif ($action === 'bulk_update_points') {
            $selected_members = isset($_POST['selected_members']) && is_array($_POST['selected_members']) ? $_POST['selected_members'] : [];
            $points_array = isset($_POST['points']) && is_array($_POST['points']) ? $_POST['points'] : [];
            
            if (empty($selected_members)) {
                $error = "No members were selected for bulk update.";
            } else {
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("UPDATE `members` SET `points` = ? WHERE `id` = ?");
                    foreach ($selected_members as $member_id) {
                        $m_id = intval($member_id);
                        if (isset($points_array[$m_id])) {
                            $pts = floatval($points_array[$m_id]);
                            $stmt->execute([$pts, $m_id]);
                        }
                    }
                    $db->commit();
                    $success = "Points updated successfully for the selected members.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to update points in bulk: " . $e->getMessage();
                }
            }
            
        } elseif ($action === 'degrade_member' || $action === 'edit_builder') {
            $member_id = intval($_POST['member_id']);
            $name = trim($_POST['name'] ?? '');
            $member_code = trim($_POST['member_code'] ?? '');
            $allowed_levels = ['Core Team', 'Directorate', 'Manager', 'Lead', 'Member'];
            $level = in_array($_POST['level'] ?? '', $allowed_levels) ? $_POST['level'] : 'Member';
            $role = trim($_POST['role'] ?? '');
            $new_password = trim($_POST['new_password'] ?? '');
            
            // Validate member_code uniqueness if specified
            if (!empty($member_code)) {
                $chk = $db->prepare("SELECT id FROM `members` WHERE LOWER(`member_code`) = LOWER(?) AND `id` != ?");
                $chk->execute([$member_code, $member_id]);
                if ($chk->fetch()) {
                    $error = "Member ID '$member_code' is already assigned to another builder.";
                }
            }
            
            if (empty($error)) {
                $stmt = $db->prepare("UPDATE `members` SET `name` = ?, `member_code` = ?, `level` = ?, `role` = ? WHERE `id` = ?");
                $stmt->execute([$name, $member_code, $level, $role, $member_id]);

                if (!empty($new_password)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE `members` SET `password` = ? WHERE `id` = ?");
                    $stmt->execute([$new_hash, $member_id]);
                }

                $success = "Builder profile (Name, Member ID: $member_code, Role, Level & Credentials) updated successfully.";
            }
            
        } elseif ($action === 'delete_member') {
            $member_id = intval($_POST['member_id']);
            
            $stmt = $db->prepare("DELETE FROM `members` WHERE `id` = ?");
            $stmt->execute([$member_id]);
            $success = "Member removed successfully.";
            
        } elseif ($action === 'update_highlights') {
            $month_label = trim($_POST['month_label']);
            $star_id = intval($_POST['star_of_month_id']) ?: null;
            
            $grinders = isset($_POST['monthly_grinders']) && is_array($_POST['monthly_grinders']) 
                ? array_map('intval', $_POST['monthly_grinders']) 
                : [];
            
            $grindersJson = json_encode($grinders);
            
            $stmt = $db->prepare("UPDATE `highlights` SET `month_label` = ?, `star_of_month_id` = ?, `monthly_grinders` = ? WHERE `id` = 1");
            $stmt->execute([$month_label, $star_id, $grindersJson]);
            $success = "Star of the Month and Grinders highlights updated.";
            
        } elseif ($action === 'add_event') {
            $title = trim($_POST['title']);
            $type = $_POST['type'];
            $date = trim($_POST['date']);
            $location = trim($_POST['location']);
            $description = trim($_POST['description']);
            $link = trim($_POST['link'] ?? '');
            
            $allPhotos = [];
            
            // Optional primary image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_main_' . basename($_FILES['image']['name']);
                $targetFile = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $allPhotos[] = $targetFile;
                }
            }
            
            // Multiple gallery images uploads
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                foreach ($_FILES['gallery_images']['name'] as $key => $name) {
                    if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = time() . '_' . $key . '_' . basename($name);
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $targetFile)) {
                            $allPhotos[] = $targetFile;
                        }
                    }
                }
            }
            
            $mainImage = !empty($allPhotos) ? $allPhotos[0] : null;
            $galleryJson = count($allPhotos) > 0 ? json_encode(array_values($allPhotos)) : null;
            
            $stmt = $db->prepare("INSERT INTO `events` (`title`, `type`, `date`, `location`, `description`, `image`, `link`, `gallery`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $type, $date, $location, $description, $mainImage, $link, $galleryJson]);
            $success = "Event '$title' published successfully.";
            
        } elseif ($action === 'edit_event') {
            $event_id = intval($_POST['event_id']);
            $title = trim($_POST['title']);
            $type = $_POST['type'];
            $date = trim($_POST['date']);
            $location = trim($_POST['location']);
            $description = trim($_POST['description']);
            $link = trim($_POST['link'] ?? '');
            
            // Kept existing photos
            $keptPhotos = [];
            if (!empty($_POST['kept_photos'])) {
                $decoded = json_decode($_POST['kept_photos'], true);
                if (is_array($decoded)) {
                    $keptPhotos = $decoded;
                }
            }
            
            // Additional gallery images uploads
            $newPhotos = [];
            if (isset($_FILES['additional_gallery_images']) && is_array($_FILES['additional_gallery_images']['name'])) {
                foreach ($_FILES['additional_gallery_images']['name'] as $key => $name) {
                    if ($_FILES['additional_gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = time() . '_' . $key . '_' . basename($name);
                        $targetFile = $uploadDir . $filename;
                        if (move_uploaded_file($_FILES['additional_gallery_images']['tmp_name'][$key], $targetFile)) {
                            $newPhotos[] = $targetFile;
                        }
                    }
                }
            }
            
            $allPhotos = array_merge($keptPhotos, $newPhotos);
            $mainImage = !empty($allPhotos) ? $allPhotos[0] : null;
            $galleryJson = count($allPhotos) > 0 ? json_encode(array_values($allPhotos)) : null;
            
            $stmt = $db->prepare("UPDATE `events` SET `title` = ?, `type` = ?, `date` = ?, `location` = ?, `description` = ?, `link` = ?, `image` = ?, `gallery` = ? WHERE `id` = ?");
            $stmt->execute([$title, $type, $date, $location, $description, $link, $mainImage, $galleryJson, $event_id]);
            $success = "Event '$title' updated successfully.";
            
        } elseif ($action === 'delete_event') {
            $event_id = intval($_POST['event_id']);
            $stmt = $db->prepare("DELETE FROM `events` WHERE `id` = ?");
            $stmt->execute([$event_id]);
            $success = "Event removed successfully.";
            
        } elseif ($action === 'add_post') {
            $title = trim($_POST['title']);
            $category = trim($_POST['category']);
            $date = trim($_POST['date']) ?: date('M d, Y');
            $excerpt = trim($_POST['excerpt']);
            
            $stmt = $db->prepare("INSERT INTO `posts` (`title`, `category`, `date`, `excerpt`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $category, $date, $excerpt]);
            $success = "Notice published successfully.";
            
        } elseif ($action === 'edit_post') {
            $post_id = intval($_POST['post_id']);
            $title = trim($_POST['title']);
            $category = trim($_POST['category']);
            $date = trim($_POST['date']) ?: date('M d, Y');
            $excerpt = trim($_POST['excerpt']);
            
            $stmt = $db->prepare("UPDATE `posts` SET `title` = ?, `category` = ?, `date` = ?, `excerpt` = ? WHERE `id` = ?");
            $stmt->execute([$title, $category, $date, $excerpt, $post_id]);
            $success = "Notice / Blog post updated successfully.";
            
        } elseif ($action === 'delete_post') {
            $post_id = intval($_POST['post_id']);
            $stmt = $db->prepare("DELETE FROM `posts` WHERE `id` = ?");
            $stmt->execute([$post_id]);
            $success = "Notice removed.";
            
        } elseif ($action === 'add_badge') {
            $name = trim($_POST['name']);
            
            $imagePath = 'public/images/badges/silver_tier.svg'; // Default fallback
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $badgeUploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = $targetFile;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO `badges` (`name`, `image`) VALUES (?, ?)");
            $stmt->execute([$name, $imagePath]);
            $success = "Badge '$name' added successfully.";
            
        } elseif ($action === 'delete_badge') {
            $badge_id = intval($_POST['badge_id']);
            
            $stmt = $db->prepare("DELETE FROM `badges` WHERE `id` = ?");
            $stmt->execute([$badge_id]);
            $success = "Badge removed successfully.";
            
        } elseif ($action === 'add_partner') {
            $name = trim($_POST['name']);
            
            $logoPath = 'public/images/partners/gocloud.png'; // default fallback
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_' . basename($_FILES['logo']['name']);
                $targetFile = $partnerUploadDir . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
                    $logoPath = $targetFile;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO `partners` (`name`, `logo`) VALUES (?, ?)");
            $stmt->execute([$name, $logoPath]);
            $success = "Partner '$name' added successfully.";
            
        } elseif ($action === 'delete_partner') {
            $partner_id = intval($_POST['partner_id']);
            
            $stmt = $db->prepare("DELETE FROM `partners` WHERE `id` = ?");
            $stmt->execute([$partner_id]);
            $success = "Partner removed successfully.";

        } elseif ($action === 'add_swag') {
            $name = trim($_POST['name']);
            $points = floatval($_POST['points']);
            $description = trim($_POST['description']);
            $stock = intval($_POST['stock']) ?: 10;
            
            $imagePath = 'public/images/cloud_architecture_illustration.png';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $swagUploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = $targetFile;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO `swags` (`name`, `points`, `image`, `description`, `stock`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $points, $imagePath, $description, $stock]);
            $success = "AWS Swag '$name' added to store catalog successfully.";
            
        } elseif ($action === 'edit_swag') {
            $swag_id = intval($_POST['swag_id']);
            $name = trim($_POST['name']);
            $points = floatval($_POST['points']);
            $description = trim($_POST['description']);
            $stock = intval($_POST['stock']);
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $filename = time() . '_' . basename($_FILES['image']['name']);
                $targetFile = $swagUploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $stmt = $db->prepare("UPDATE `swags` SET `name` = ?, `points` = ?, `description` = ?, `stock` = ?, `image` = ? WHERE `id` = ?");
                    $stmt->execute([$name, $points, $description, $stock, $targetFile, $swag_id]);
                } else {
                    $stmt = $db->prepare("UPDATE `swags` SET `name` = ?, `points` = ?, `description` = ?, `stock` = ? WHERE `id` = ?");
                    $stmt->execute([$name, $points, $description, $stock, $swag_id]);
                }
            } else {
                $stmt = $db->prepare("UPDATE `swags` SET `name` = ?, `points` = ?, `description` = ?, `stock` = ? WHERE `id` = ?");
                $stmt->execute([$name, $points, $description, $stock, $swag_id]);
            }
            $success = "AWS Swag '$name' updated successfully.";
            
        } elseif ($action === 'delete_swag') {
            $swag_id = intval($_POST['swag_id']);
            $stmt = $db->prepare("DELETE FROM `swags` WHERE `id` = ?");
            $stmt->execute([$swag_id]);
            $success = "Swag item removed from store.";
            
        } elseif ($action === 'fulfill_swag_request') {
            $request_id = intval($_POST['request_id']);
            
            // Get request details
            $stmt = $db->prepare("SELECT r.*, m.name AS member_name, m.points AS member_points, s.name AS swag_name, s.stock AS swag_stock FROM `swag_requests` r JOIN `members` m ON r.member_id = m.id JOIN `swags` s ON r.swag_id = s.id WHERE r.id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch();
            
            if (!$req) {
                $error = "Swag claim request not found.";
            } elseif ($req['status'] === 'fulfilled') {
                $error = "This swag request has already been fulfilled.";
            } else {
                $db->beginTransaction();
                try {
                    $pts_spent = floatval($req['points_spent']);
                    $member_id = intval($req['member_id']);
                    $swag_id = intval($req['swag_id']);
                    
                    // 1. Deduct points from member
                    $stmt1 = $db->prepare("UPDATE `members` SET `points` = GREATEST(0, `points` - ?) WHERE `id` = ?");
                    $stmt1->execute([$pts_spent, $member_id]);
                    
                    // 2. Decrement swag stock
                    $stmt2 = $db->prepare("UPDATE `swags` SET `stock` = GREATEST(0, `stock` - 1) WHERE `id` = ?");
                    $stmt2->execute([$swag_id]);
                    
                    // 3. Mark request fulfilled
                    $stmt3 = $db->prepare("UPDATE `swag_requests` SET `status` = 'fulfilled' WHERE `id` = ?");
                    $stmt3->execute([$request_id]);
                    
                    $db->commit();
                    $success = "Swag request fulfilled! Successfully deducted " . number_format($pts_spent, 2) . " PTS from " . htmlspecialchars($req['member_name']) . " for '" . htmlspecialchars($req['swag_name']) . "'.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to fulfill swag request: " . $e->getMessage();
                }
            }
            
        } elseif ($action === 'reject_swag_request') {
            $request_id = intval($_POST['request_id']);
            $stmt = $db->prepare("UPDATE `swag_requests` SET `status` = 'rejected' WHERE `id` = ?");
            $stmt->execute([$request_id]);
            $success = "Swag request rejected.";
            
        } elseif ($action === 'direct_award_swag') {
            $member_id = intval($_POST['member_id']);
            $swag_id = intval($_POST['swag_id']);
            
            // Fetch member & swag
            $stmtM = $db->prepare("SELECT * FROM `members` WHERE `id` = ?");
            $stmtM->execute([$member_id]);
            $mb = $stmtM->fetch();
            
            $stmtS = $db->prepare("SELECT * FROM `swags` WHERE `id` = ?");
            $stmtS->execute([$swag_id]);
            $sw = $stmtS->fetch();
            
            if (!$mb || !$sw) {
                $error = "Member or Swag item not found.";
            } else {
                $pts_cost = floatval($sw['points']);
                
                $db->beginTransaction();
                try {
                    // Deduct PTS
                    $stmt1 = $db->prepare("UPDATE `members` SET `points` = GREATEST(0, `points` - ?) WHERE `id` = ?");
                    $stmt1->execute([$pts_cost, $member_id]);
                    
                    // Decrement stock
                    $stmt2 = $db->prepare("UPDATE `swags` SET `stock` = GREATEST(0, `stock` - 1) WHERE `id` = ?");
                    $stmt2->execute([$swag_id]);
                    
                    // Log fulfilled request
                    $stmt3 = $db->prepare("INSERT INTO `swag_requests` (`member_id`, `swag_id`, `points_spent`, `status`) VALUES (?, ?, ?, 'fulfilled')");
                    $stmt3->execute([$member_id, $swag_id, $pts_cost]);
                    
                    $db->commit();
                    $success = "Successfully awarded '" . htmlspecialchars($sw['name']) . "' to " . htmlspecialchars($mb['name']) . " and deducted " . number_format($pts_cost, 2) . " PTS!";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to award swag: " . $e->getMessage();
                }
            }
        }
        
        // Refresh variables by reloading db data
        require 'includes/db.php';
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="mx-auto max-w-[1440px] px-4 sm:px-6 pb-24 pt-8 lg:px-8">
    <div class="mb-4 text-[10px] font-black uppercase tracking-[0.24em] text-slate-400 dark:text-zinc-400">
        <span>AWS Student Builders</span>
        <span class="mx-2">/</span>
        <span class="text-purple-600 dark:text-purple-400">Admin Control Panel</span>
    </div>

    <!-- Page Header -->
    <div class="mb-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-purple-605 dark:text-purple-400">Authorized Portal</p>
            <h2 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white leading-none font-space"><span class="text-glow-gradient">Lead Operations</span> Panel</h2>
            <p class="mt-4 max-w-2xl text-xs sm:text-sm leading-relaxed text-slate-500 dark:text-zinc-400 font-medium">Add members, update points, release upcoming events, publish notices, and announce spotlights.</p>
        </div>
        <a href="logout.php" class="rounded-full bg-red-500/10 border border-red-500/20 px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-all text-center self-start sm:self-center shrink-0">
            Sign Out
        </a>
    </div>

    <!-- Notification Banners -->
    <?php if ($success): ?>
        <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 mb-6 text-sm text-emerald-600 dark:text-emerald-400 font-bold">
            ✓ <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-2xl border border-red-500/20 bg-red-500/10 p-4 mb-6 text-sm text-red-600 dark:text-red-400 font-bold">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- TAB SELECTORS -->
    <div class="flex gap-2 overflow-x-auto no-scrollbar border-b border-slate-200 dark:border-white/10 pb-2 mb-8 relative z-20">
        <button type="button" data-tab="members-tab" onclick="switchTab('members-tab')" id="members-tab-btn" class="tab-btn active-tab rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest cursor-pointer relative z-20 transition-all">
            Members Directory
        </button>
        <button type="button" data-tab="badges-tab" onclick="switchTab('badges-tab')" id="badges-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Community Badges
        </button>
        <button type="button" data-tab="partners-tab" onclick="switchTab('partners-tab')" id="partners-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Campus Partners
        </button>
        <button type="button" data-tab="highlights-tab" onclick="switchTab('highlights-tab')" id="highlights-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Month Spotlights
        </button>
        <button type="button" data-tab="swags-tab" onclick="switchTab('swags-tab')" id="swags-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            AWS Swags Catalog
        </button>
        <button type="button" data-tab="claims-tab" onclick="switchTab('claims-tab')" id="claims-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Member Swag Claims
        </button>
        <button type="button" data-tab="events-tab" onclick="switchTab('events-tab')" id="events-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Sessions/Gallery
        </button>
        <button type="button" data-tab="posts-tab" onclick="switchTab('posts-tab')" id="posts-tab-btn" class="tab-btn rounded-full px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white cursor-pointer relative z-20 transition-all">
            Notices & Blog
        </button>
    </div>

    <!-- 1. MEMBERS DIRECTORY TAB -->
    <div id="members-tab" class="tab-content space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Form: Add Member -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Add New Builder</h3>
                <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_member">
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Ali Ahmed" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Account Password</label>
                            <input type="password" name="password" required placeholder="••••••••" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Role Title</label>
                            <input type="text" name="role" required placeholder="Member, DevOps Lead..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Initial Points</label>
                            <input type="number" step="0.0001" name="points" value="0.0000" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Team Group</label>
                            <select name="team" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                                <option value="Core">Core</option>
                                <option value="Technical" selected>Technical</option>
                                <option value="Media & Design">Media & Design</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Events">Events</option>
                                <option value="Operations">Operations</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Level Tier</label>
                            <select name="level" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                                <option value="Core Team">Core Team</option>
                                <option value="Directorate">Directorate</option>
                                <option value="Manager">Manager</option>
                                <option value="Lead">Lead</option>
                                <option value="Member" selected>Member</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Campus</label>
                        <input type="text" name="campus" value="BZU" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Avatar Image Upload</label>
                        <input type="file" name="image" accept="image/*" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Responsibilities</label>
                        <textarea name="responsibilities" rows="3" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Add to Database
                    </button>
                </form>
            </div>

            <!-- List Members (Manage Points & Status) -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-4 mb-4">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white font-space">Manage Members (<?php echo count($participants); ?>)</h3>
                    <input type="text" id="member-filter" placeholder="Filter names or IDs..." class="rounded-full border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-[10px] text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
                
                <form id="bulk-points-form" action="admin.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="bulk_update_points">
                    
                    <!-- Bulk update control bar -->
                    <div class="flex flex-wrap items-center justify-between gap-4 bg-slate-50 dark:bg-white/[0.01] border border-slate-200 dark:border-white/5 rounded-2xl p-3 mb-2">
                        <div class="flex items-center gap-2.5">
                            <input type="checkbox" id="select-all-members" class="rounded border-slate-300 dark:border-white/15 text-purple-600 focus:ring-purple-500 bg-transparent h-4 w-4 cursor-pointer">
                            <label for="select-all-members" class="text-xs font-bold text-slate-700 dark:text-zinc-350 cursor-pointer">Select All</label>
                        </div>
                        <button type="submit" class="rounded-full bg-purple-600 hover:bg-purple-500 px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white cursor-pointer shadow-md shadow-purple-600/20">
                            Apply Bulk Points Changes
                        </button>
                    </div>

                    <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2 no-scrollbar">
                        <?php foreach ($participants as $p): ?>
                            <?php $p_code = $p['member_code'] ?? ('SBG-' . sprintf('%03d', $p['id'])); ?>
                            <div class="member-list-item flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left" data-name="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $p_code)); ?>">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" name="selected_members[]" value="<?php echo $p['id']; ?>" class="member-checkbox rounded border-slate-300 dark:border-white/15 text-purple-600 focus:ring-purple-500 bg-transparent h-4 w-4 cursor-pointer">
                                    <img src="<?php echo $p['image'] ?: 'public/images/AWS-MembersPics/default.png'; ?>" class="h-10 w-10 rounded-xl object-cover border border-slate-200 dark:border-white/10">
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo $p['name']; ?></p>
                                            <span class="inline-block rounded-md bg-purple-500/10 border border-purple-500/20 px-2 py-0.5 text-[9px] font-mono font-bold text-purple-600 dark:text-purple-400">
                                                ID: <?php echo htmlspecialchars($p_code); ?>
                                            </span>
                                        </div>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400 mt-0.5"><?php echo $p['role']; ?> • <?php echo $p['level']; ?> (<?php echo $p['team']; ?>)</p>
                                    </div>
                                </div>
                                
                                <!-- Operations: Update Points & Actions -->
                                <div class="flex flex-wrap items-center gap-3">
                                    <!-- Points updater input fields -->
                                    <div class="flex items-center gap-1.5 bg-slate-150 dark:bg-white/5 p-1 rounded-full border border-slate-200 dark:border-white/10">
                                        <input type="number" step="0.0001" id="points-input-<?php echo $p['id']; ?>" name="points[<?php echo $p['id']; ?>]" value="<?php echo $p['points']; ?>" class="w-20 bg-transparent text-center text-xs font-bold text-slate-900 dark:text-white outline-none">
                                        <button type="button" onclick="submitIndividualPoints(<?php echo $p['id']; ?>, 'points-input-<?php echo $p['id']; ?>')" class="rounded-full bg-purple-600 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-white cursor-pointer hover:bg-purple-500">
                                            PTS
                                        </button>
                                    </div>

                                    <!-- Edit Builder Profile & Credentials overlay trigger -->
                                    <button type="button" 
                                            data-id="<?php echo $p['id']; ?>" 
                                            data-code="<?php echo htmlspecialchars($p_code, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-level="<?php echo htmlspecialchars($p['level'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-role="<?php echo htmlspecialchars($p['role'], ENT_QUOTES, 'UTF-8'); ?>" 
                                            onclick="toggleDegradeModal(this)" 
                                            class="rounded-full bg-slate-200 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-700 dark:text-zinc-350 cursor-pointer">
                                        Edit Profile
                                    </button>

                                    <!-- Delete member trigger -->
                                    <button type="button" onclick="submitIndividualDelete(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>')" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. MONTH SPOTLIGHTS TAB -->
    <div id="highlights-tab" class="tab-content hidden max-w-2xl mx-auto rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-8 shadow-md">
        <h3 class="text-xl font-black text-slate-900 dark:text-white font-space mb-2">🏆 Announce Spotlights</h3>
        <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6 font-medium">Update the chapter accomplishments and select top monthly stars.</p>
        
        <form action="admin.php" method="POST" class="space-y-5">
            <input type="hidden" name="action" value="update_highlights">
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Month Label</label>
                <input type="text" name="month_label" value="<?php echo htmlspecialchars($highlights['monthLabel']); ?>" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">★ Star of the Month</label>
                <select name="star_of_month_id" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    <option value="">-- Select Star Builder --</option>
                    <?php foreach ($participants as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $highlights['starOfMonthId'] == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo $p['name']; ?> (<?php echo $p['role']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-2">🏆 Monthly Grinders (Select Multiple)</label>
                <div class="grid gap-2 sm:grid-cols-2 max-h-[200px] overflow-y-auto border border-slate-200 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 rounded-2xl no-scrollbar">
                    <?php foreach ($participants as $p): 
                        if ($p['level'] === 'Lead') continue;
                        $is_grinder = in_array($p['id'], $highlights['monthlyGrinders']);
                    ?>
                        <label class="flex items-center gap-2.5 text-xs text-slate-700 dark:text-zinc-350 cursor-pointer">
                            <input type="checkbox" name="monthly_grinders[]" value="<?php echo $p['id']; ?>" <?php echo $is_grinder ? 'checked' : ''; ?> class="rounded border-slate-200 dark:border-white/10 bg-transparent text-purple-600 focus:ring-purple-500">
                            <span><?php echo $p['name']; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                Release Monthly Highlights
            </button>
        </form>
    </div>

    <!-- 3. SESSIONS & PAST GALLERY TAB -->
    <div id="events-tab" class="tab-content hidden space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Form: Publish Event -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-2">📅 Publish Event</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4 font-medium">Add upcoming live meetups or completed workshops with multi-photo galleries.</p>
                
                <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_event">
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Event Title</label>
                        <input type="text" name="title" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Status Type</label>
                            <select name="type" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                                <option value="upcoming">Upcoming</option>
                                <option value="past">Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Date</label>
                            <input type="text" name="date" required placeholder="May 12, 2026" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Location Venue</label>
                            <input type="text" name="location" required placeholder="CS Dept Lab 2" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Registration Link</label>
                            <input type="url" name="link" placeholder="https://..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Event Photos (Select multiple at once)</label>
                        <input type="file" name="gallery_images[]" accept="image/*" multiple class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <p class="text-[10px] text-slate-400 mt-1">Upload multiple photos for this event. They will show in the carousel.</p>
                    </div>

                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Optional Separate Cover Banner</label>
                        <input type="file" name="image" accept="image/*" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Session Description</label>
                        <textarea name="description" rows="3" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
                    </div>

                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Publish Event
                    </button>
                </form>
            </div>

            <!-- List Events (Edit & Delete) -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-black text-slate-900 dark:text-white font-space">Events & Workshops (<?php echo count($events); ?>)</h3>
                </div>
                
                <div class="space-y-4 max-h-[620px] overflow-y-auto pr-2 no-scrollbar">
                    <?php if (empty($events)): ?>
                        <p class="text-xs text-slate-500 italic">No events published yet.</p>
                    <?php else: ?>
                        <?php foreach ($events as $ev): 
                            $evPhotos = [];
                            if (!empty($ev['image'])) $evPhotos[] = $ev['image'];
                            if (!empty($ev['gallery']) && is_array($ev['gallery'])) {
                                foreach ($ev['gallery'] as $gp) {
                                    if (!empty($gp) && !in_array($gp, $evPhotos)) $evPhotos[] = $gp;
                                }
                            }
                        ?>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left">
                                <div class="flex items-start gap-3 min-w-0">
                                    <?php if (!empty($evPhotos)): ?>
                                        <img src="<?php echo htmlspecialchars($evPhotos[0]); ?>" alt="" class="h-14 w-14 rounded-xl object-cover border border-slate-200 dark:border-white/10 shrink-0">
                                    <?php else: ?>
                                        <div class="h-14 w-14 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-lg shrink-0">📅</div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full <?php echo $ev['type'] === 'upcoming' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'; ?>">
                                                <?php echo ucfirst($ev['type']); ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-400">
                                                📅 <?php echo htmlspecialchars($ev['date']); ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-400">
                                                📍 <?php echo htmlspecialchars($ev['location']); ?>
                                            </span>
                                            <span class="rounded-full bg-slate-100 dark:bg-white/5 px-2 py-0.5 text-[9px] font-extrabold text-purple-600 dark:text-purple-400">
                                                📷 <?php echo count($evPhotos); ?> <?php echo count($evPhotos) === 1 ? 'photo' : 'photos'; ?>
                                            </span>
                                        </div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1 truncate"><?php echo htmlspecialchars($ev['title']); ?></h4>
                                        <p class="text-xs text-slate-500 line-clamp-1 mt-0.5"><?php echo htmlspecialchars($ev['description']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                    <button type="button" 
                                        onclick="openEditEventModal(this)"
                                        data-id="<?php echo $ev['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($ev['title'], ENT_QUOTES); ?>"
                                        data-type="<?php echo htmlspecialchars($ev['type'], ENT_QUOTES); ?>"
                                        data-date="<?php echo htmlspecialchars($ev['date'], ENT_QUOTES); ?>"
                                        data-location="<?php echo htmlspecialchars($ev['location'], ENT_QUOTES); ?>"
                                        data-link="<?php echo htmlspecialchars($ev['link'] ?? '', ENT_QUOTES); ?>"
                                        data-description="<?php echo htmlspecialchars($ev['description'], ENT_QUOTES); ?>"
                                        data-photos="<?php echo htmlspecialchars(json_encode($evPhotos), ENT_QUOTES); ?>"
                                        class="rounded-full bg-purple-600/10 border border-purple-500/20 hover:bg-purple-600/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400 cursor-pointer transition-colors">
                                        Edit
                                    </button>
                                    <form action="admin.php" method="POST" onsubmit="return confirm('Delete event \'<?php echo addslashes($ev['title']); ?>\'?');" class="inline">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                        <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. NOTICES & BLOG TAB -->
    <div id="posts-tab" class="tab-content hidden space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Form: Add Notice -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Publish Notice</h3>
                <form action="admin.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_post">
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Notice Title</label>
                        <input type="text" name="title" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Category tag</label>
                            <input type="text" name="category" required placeholder="Announcement, Program..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Date</label>
                            <input type="text" name="date" placeholder="May 09, 2026" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Excerpt / Brief Description</label>
                        <textarea name="excerpt" rows="4" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Broadcast Notice
                    </button>
                </form>
            </div>

            <!-- List Notices (Edit & Delete notices) -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Current Notices Bulletin (<?php echo count($posts); ?>)</h3>
                
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 no-scrollbar">
                    <?php if (empty($posts)): ?>
                        <p class="text-xs text-slate-500 italic">No notices posted yet.</p>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left">
                                <div>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-purple-650 dark:text-purple-400">
                                        <?php echo $post['category']; ?> / <?php echo $post['date']; ?>
                                    </span>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-1"><?php echo $post['title']; ?></h4>
                                    <p class="text-xs text-slate-500 mt-1"><?php echo $post['excerpt']; ?></p>
                                </div>
                                
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" 
                                        onclick="openEditPostModal(this)"
                                        data-id="<?php echo $post['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($post['title'], ENT_QUOTES); ?>"
                                        data-category="<?php echo htmlspecialchars($post['category'], ENT_QUOTES); ?>"
                                        data-date="<?php echo htmlspecialchars($post['date'], ENT_QUOTES); ?>"
                                        data-excerpt="<?php echo htmlspecialchars($post['excerpt'], ENT_QUOTES); ?>"
                                        class="rounded-full bg-purple-600/10 border border-purple-500/20 hover:bg-purple-600/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-purple-600 dark:text-purple-400 cursor-pointer transition-colors">
                                        Edit
                                    </button>
                                    <form action="admin.php" method="POST" onsubmit="return confirm('Delete notice?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. COMMUNITY BADGES TAB -->
    <div id="badges-tab" class="tab-content hidden space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Form: Add Badge -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Add New Badge</h3>
                <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_badge">
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Badge Name</label>
                        <input type="text" name="name" required placeholder="e.g. Silver Tier Community" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Badge Logo/Image Upload</label>
                        <input type="file" name="image" required accept="image/*,.svg" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <span class="block text-[8px] text-slate-500 mt-1">Accepts PNG, JPG, or SVG.</span>
                    </div>
                    
                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Add Badge
                    </button>
                </form>
            </div>

            <!-- List Badges -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Recognition Badges (<?php echo count($badges); ?>)</h3>
                
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 no-scrollbar">
                    <?php if (empty($badges)): ?>
                        <p class="text-xs text-slate-500 italic">No badges added yet.</p>
                    <?php else: ?>
                        <?php foreach ($badges as $badge): ?>
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 flex items-center justify-center shrink-0 border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 rounded-xl overflow-hidden p-1.5">
                                        <?php 
                                            $isSvg = pathinfo($badge['image'], PATHINFO_EXTENSION) === 'svg';
                                            if ($isSvg && file_exists($badge['image'])) {
                                                echo file_get_contents($badge['image']);
                                            } else {
                                                echo '<img src="' . htmlspecialchars($badge['image']) . '" alt="" class="w-full h-full object-contain">';
                                            }
                                        ?>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($badge['name']); ?></h4>
                                        <p class="text-[9px] text-slate-500 font-mono truncate max-w-xs sm:max-w-md"><?php echo htmlspecialchars($badge['image']); ?></p>
                                    </div>
                                </div>
                                
                                <form action="admin.php" method="POST" onsubmit="return confirm('Delete badge \'<?php echo htmlspecialchars(addslashes($badge['name'])); ?>\'?');">
                                    <input type="hidden" name="action" value="delete_badge">
                                    <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                    <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. CAMPUS PARTNERS TAB -->
    <div id="partners-tab" class="tab-content hidden space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Form: Add Partner -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Add New Partner</h3>
                <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_partner">
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Partner Name</label>
                        <input type="text" name="name" required placeholder="e.g. GoClouds" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Partner Logo Upload</label>
                        <input type="file" name="logo" required accept="image/*" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <span class="block text-[8px] text-slate-500 mt-1">Accepts PNG, JPG, JPEG, or SVG.</span>
                    </div>
                    
                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Add Partner
                    </button>
                </form>
            </div>

            <!-- List Partners -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Current Partners (<?php echo count($partners); ?>)</h3>
                
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 no-scrollbar">
                    <?php if (empty($partners)): ?>
                        <p class="text-xs text-slate-500 italic">No partners added yet.</p>
                    <?php else: ?>
                        <?php foreach ($partners as $partner): ?>
                            <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 flex items-center justify-center shrink-0 border border-slate-200 dark:border-white/20 bg-white dark:bg-white rounded-2xl overflow-hidden p-2 shadow-sm">
                                        <img src="<?php echo htmlspecialchars($partner['logo']); ?>" alt="" class="max-w-full max-h-full object-contain">
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($partner['name']); ?></h4>
                                        <p class="text-[9px] text-slate-500 font-mono truncate max-w-xs sm:max-w-md"><?php echo htmlspecialchars($partner['logo']); ?></p>
                                    </div>
                                </div>
                                
                                <form action="admin.php" method="POST" onsubmit="return confirm('Delete partner \'<?php echo htmlspecialchars(addslashes($partner['name'])); ?>\'?');">
                                    <input type="hidden" name="action" value="delete_partner">
                                    <input type="hidden" name="partner_id" value="<?php echo $partner['id']; ?>">
                                    <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2.5 AWS SWAGS CATALOG TAB -->
    <div id="swags-tab" class="tab-content hidden space-y-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Add Swag Form -->
            <div class="lg:col-span-1 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md hover-glow-card">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-2">Add New AWS Swag</h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6">Add merchandise, set required PTS, and upload product photos.</p>
                
                <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_swag">
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Swag Name</label>
                        <input type="text" name="name" required placeholder="AWS T-Shirt, Cloud Bottle..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Required PTS</label>
                            <input type="number" step="0.0001" name="points" required value="25.0000" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Initial Stock</label>
                            <input type="number" name="stock" value="10" min="0" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Product Photo Upload</label>
                        <input type="file" name="image" accept="image/*" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Description</label>
                        <textarea name="description" rows="3" placeholder="Swag details, sizing, material..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                        Add Swag to Catalog
                    </button>
                </form>
            </div>

            <!-- List Swags -->
            <div class="lg:col-span-2 rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
                <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Swags Inventory (<?php echo count($swags ?? []); ?>)</h3>
                
                <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2 no-scrollbar">
                    <?php if (empty($swags)): ?>
                        <p class="text-xs text-slate-500 italic">No swags added to store catalog yet.</p>
                    <?php else: ?>
                        <?php foreach ($swags as $swag): ?>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-4 text-left">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 flex items-center justify-center shrink-0 border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 rounded-xl overflow-hidden p-1.5">
                                        <img src="<?php echo htmlspecialchars($swag['image'] ?? ''); ?>" alt="" class="w-full h-full object-contain">
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($swag['name'] ?? ''); ?></h4>
                                        <p class="text-xs font-black uppercase text-purple-600 dark:text-purple-400">⚡ <?php echo number_format($swag['points'] ?? 0, 2); ?> PTS • Stock: <?php echo intval($swag['stock'] ?? 0); ?></p>
                                        <p class="text-[10px] text-slate-500 font-medium line-clamp-1 mt-0.5"><?php echo htmlspecialchars($swag['description'] ?? ''); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 shrink-0">
                                    <!-- Edit Swag Button -->
                                    <button type="button" 
                                            data-id="<?php echo $swag['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($swag['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-pts="<?php echo floatval($swag['points'] ?? 0); ?>" 
                                            data-stock="<?php echo intval($swag['stock'] ?? 0); ?>" 
                                            data-desc="<?php echo htmlspecialchars($swag['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                            data-img="<?php echo htmlspecialchars($swag['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                            onclick="openEditSwagModal(this)" 
                                            class="rounded-full bg-slate-200 dark:bg-white/10 hover:bg-slate-300 dark:hover:bg-white/20 px-3.5 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-700 dark:text-zinc-350 cursor-pointer">
                                        Edit
                                    </button>

                                    <!-- Delete Swag Form -->
                                    <form action="admin.php" method="POST" onsubmit="return confirm('Delete swag \'<?php echo htmlspecialchars(addslashes($swag['name'])); ?>\' from catalog?');">
                                        <input type="hidden" name="action" value="delete_swag">
                                        <input type="hidden" name="swag_id" value="<?php echo $swag['id']; ?>">
                                        <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-red-500 cursor-pointer">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 2.6 MEMBER SWAG CLAIMS TAB -->
    <div id="claims-tab" class="tab-content hidden space-y-8">
        <!-- Direct Swag Award Box -->
        <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-1">🎁 Direct Swag Award & PTS Deduction</h3>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">Directly award an AWS Swag to a builder. This will automatically deduct the required PTS from their balance.</p>

            <form action="admin.php" method="POST" class="grid gap-4 sm:grid-cols-3 items-end">
                <input type="hidden" name="action" value="direct_award_swag">
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Select Member</label>
                    <select name="member_id" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <option value="">-- Select Member --</option>
                        <?php foreach ($participants as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (Bal: <?php echo number_format($p['points'], 2); ?> PTS)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Select Swag</label>
                    <select name="swag_id" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <option value="">-- Select Swag Item --</option>
                        <?php foreach ($swags as $s): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['name']); ?> (Costs: <?php echo number_format($s['points'], 2); ?> PTS)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" onclick="return confirm('Award swag and deduct points from member balance?');" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20 cursor-pointer">
                    Award & Deduct PTS
                </button>
            </form>
        </div>

        <!-- Pending Swag Claims List -->
        <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Pending Swag Claim Requests</h3>

            <?php 
            $pending_requests = array_filter($swag_requests, function($r) { return $r['status'] === 'pending'; });
            ?>

            <div class="space-y-4">
                <?php if (empty($pending_requests)): ?>
                    <p class="text-xs text-slate-500 italic p-4 text-center">No pending swag claim requests at the moment.</p>
                <?php else: ?>
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-2xl border border-purple-500/20 bg-purple-500/5 p-4">
                            <!-- Member & Swag Info -->
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo (!empty($req['member_image']) ? htmlspecialchars($req['member_image']) : 'public/images/AWS-MembersPics/default.png'); ?>" class="h-10 w-10 rounded-xl object-cover border border-slate-200 dark:border-white/10">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($req['member_name'] ?? ''); ?></p>
                                            <span class="rounded bg-purple-500/10 border border-purple-500/20 px-1.5 py-0.5 text-[9px] font-mono font-bold text-purple-400">
                                                ID: <?php echo htmlspecialchars($req['member_code'] ?? ('SBG-' . sprintf('%03d', $req['member_id']))); ?>
                                            </span>
                                        </div>
                                        <p class="text-[9px] font-black uppercase text-purple-400">Bal: <?php echo number_format($req['member_current_points'] ?? 0, 2); ?> PTS</p>
                                    </div>
                                </div>

                                <div class="text-slate-400 hidden sm:block">➜</div>

                                <div class="flex items-center gap-3">
                                    <img src="<?php echo htmlspecialchars($req['swag_image'] ?? ''); ?>" class="h-10 w-10 rounded-xl object-contain bg-white/10 p-1">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($req['swag_name'] ?? ''); ?></p>
                                        <p class="text-[9px] font-black uppercase text-amber-500">Requires: <?php echo number_format($req['points_spent'] ?? 0, 2); ?> PTS</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <form action="admin.php" method="POST" onsubmit="return confirm('Fulfill request? This will deduct <?php echo number_format($req['points_spent'] ?? 0, 2); ?> PTS from <?php echo htmlspecialchars(addslashes($req['member_name'] ?? '')); ?>.');">
                                    <input type="hidden" name="action" value="fulfill_swag_request">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" class="rounded-full bg-emerald-600 hover:bg-emerald-500 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-md shadow-emerald-600/20 cursor-pointer">
                                        ✓ Fulfill & Deduct PTS
                                    </button>
                                </form>

                                <form action="admin.php" method="POST" onsubmit="return confirm('Reject this swag request?');">
                                    <input type="hidden" name="action" value="reject_swag_request">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" class="rounded-full bg-red-500/10 border border-red-500/20 hover:bg-red-500/20 px-3.5 py-2 text-[10px] font-black uppercase tracking-wider text-red-500 cursor-pointer">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Claims History -->
        <div class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 shadow-md">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Swag Claim Requests History</h3>

            <?php 
            $history_requests = array_filter($swag_requests ?? [], function($r) { return ($r['status'] ?? '') !== 'pending'; });
            ?>

            <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2 no-scrollbar">
                <?php if (empty($history_requests)): ?>
                    <p class="text-xs text-slate-500 italic">No request history yet.</p>
                <?php else: ?>
                    <?php foreach ($history_requests as $req): ?>
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-white/[0.01] p-3 text-xs">
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($req['member_name'] ?? ''); ?></span>
                                <span class="rounded bg-purple-500/10 border border-purple-500/20 px-1.5 py-0.5 text-[8px] font-mono text-purple-400">
                                    ID: <?php echo htmlspecialchars($req['member_code'] ?? ('SBG-' . sprintf('%03d', $req['member_id']))); ?>
                                </span>
                                <span class="text-slate-400">claimed</span>
                                <span class="font-bold text-purple-400"><?php echo htmlspecialchars($req['swag_name'] ?? ''); ?></span>
                                <span class="text-[10px] text-slate-500">(<?php echo number_format($req['points_spent'] ?? 0, 2); ?> PTS)</span>
                            </div>
                            <span class="rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-wider <?php echo ($req['status'] ?? '') === 'fulfilled' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                                <?php echo ucfirst($req['status'] ?? ''); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hidden single-action forms to prevent nested forms -->
<form id="individual-points-form" action="admin.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="update_points">
    <input type="hidden" name="member_id" id="individual-points-member-id">
    <input type="hidden" name="points" id="individual-points-value">
</form>

<form id="individual-delete-form" action="admin.php" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_member">
    <input type="hidden" name="member_id" id="individual-delete-member-id">
</form>

<!-- Edit Builder Profile & Credentials Modal overlay -->
<div id="degrade-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 shadow-2xl relative text-left">
        <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Edit Builder Profile & Credentials</h3>
        <form action="admin.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_builder">
            <input type="hidden" name="member_id" id="degrade-member-id">
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Builder Full Name</label>
                <input type="text" name="name" id="degrade-name" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Member ID (Login ID)</label>
                <input type="text" name="member_code" id="degrade-code" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500 font-mono">
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Role Title</label>
                <input type="text" name="role" id="degrade-role" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Level Tier</label>
                <select name="level" id="degrade-level" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                    <option value="Core Team">Core Team</option>
                    <option value="Directorate">Directorate</option>
                    <option value="Manager">Manager</option>
                    <option value="Lead">Lead</option>
                    <option value="Member">Member</option>
                </select>
            </div>
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Set / Reset Password</label>
                <input type="password" name="new_password" id="degrade-password" placeholder="Leave empty to keep current password" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div class="flex gap-2.5 pt-2">
                <button type="submit" class="flex-grow rounded-full bg-purple-600 hover:bg-purple-500 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all cursor-pointer">
                    Save Builder Profile Changes
                </button>
                <button type="button" onclick="closeDegradeModal()" class="rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-zinc-400 text-center cursor-pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Swag Modal overlay -->
<div id="edit-swag-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 shadow-2xl relative text-left">
        <h3 class="text-lg font-black text-slate-900 dark:text-white font-space mb-4">Edit AWS Swag</h3>
        <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit_swag">
            <input type="hidden" name="swag_id" id="edit-swag-id">
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Swag Name</label>
                <input type="text" name="name" id="edit-swag-name" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Required PTS</label>
                    <input type="number" step="0.0001" name="points" id="edit-swag-pts" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Stock Quantity</label>
                    <input type="number" name="stock" id="edit-swag-stock" min="0" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Description</label>
                <textarea name="description" id="edit-swag-desc" rows="3" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Replace Image (Optional)</label>
                <input type="file" name="image" accept="image/*" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div class="flex gap-2.5 pt-2">
                <button type="submit" class="flex-grow rounded-full bg-purple-600 hover:bg-purple-500 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all cursor-pointer">
                    Save Swag Changes
                </button>
                <button type="button" onclick="closeEditSwagModal()" class="rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-zinc-400 text-center cursor-pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Event Modal overlay -->
<div id="edit-event-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 shadow-2xl relative text-left no-scrollbar">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-space">Edit Event / Workshop</h3>
            <button type="button" onclick="closeEditEventModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
        </div>
        <form action="admin.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="edit_event">
            <input type="hidden" name="event_id" id="edit-event-id">
            <input type="hidden" name="kept_photos" id="edit-event-kept-photos">
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Event Title</label>
                <input type="text" name="title" id="edit-event-title" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Status Type</label>
                    <select name="type" id="edit-event-type" class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#0d0a15] px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Date</label>
                    <input type="text" name="date" id="edit-event-date" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Location Venue</label>
                    <input type="text" name="location" id="edit-event-location" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Registration Link</label>
                    <input type="url" name="link" id="edit-event-link" placeholder="https://..." class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Session Description</label>
                <textarea name="description" id="edit-event-description" rows="3" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
            </div>

            <!-- Current Photos Gallery Manager -->
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1.5">Attached Photos (Click ✕ to remove)</label>
                <div id="edit-event-photos-grid" class="flex flex-wrap gap-2.5 p-3 rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 min-h-[70px] max-h-44 overflow-y-auto"></div>
            </div>

            <!-- Add More Photos Input -->
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Add More Photos (Select multiple files)</label>
                <input type="file" name="additional_gallery_images[]" accept="image/*" multiple class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                <p class="text-[10px] text-slate-400 mt-1">Select one or multiple photos to append to this event.</p>
            </div>

            <div class="flex gap-2.5 pt-2">
                <button type="submit" class="flex-grow rounded-full bg-purple-600 hover:bg-purple-500 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all cursor-pointer shadow-md shadow-purple-600/20">
                    Save Event Changes
                </button>
                <button type="button" onclick="closeEditEventModal()" class="rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-zinc-400 text-center cursor-pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Notice / Blog Modal overlay -->
<div id="edit-post-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity duration-300">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-[#0c0817] p-6 shadow-2xl relative text-left">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-black text-slate-900 dark:text-white font-space">Edit Notice / Blog</h3>
            <button type="button" onclick="closeEditPostModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg cursor-pointer">✕</button>
        </div>
        <form action="admin.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_post">
            <input type="hidden" name="post_id" id="edit-post-id">
            
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Notice Title</label>
                <input type="text" name="title" id="edit-post-title" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Category Tag</label>
                    <input type="text" name="category" id="edit-post-category" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Date</label>
                    <input type="text" name="date" id="edit-post-date" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black uppercase text-slate-500 tracking-wider mb-1">Excerpt / Content</label>
                <textarea name="excerpt" id="edit-post-excerpt" rows="4" required class="form-input w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-purple-500"></textarea>
            </div>

            <div class="flex gap-2.5 pt-2">
                <button type="submit" class="flex-grow rounded-full bg-purple-600 hover:bg-purple-500 py-2.5 text-xs font-black uppercase tracking-wider text-white transition-all cursor-pointer shadow-md shadow-purple-600/20">
                    Save Notice Changes
                </button>
                <button type="button" onclick="closeEditPostModal()" class="rounded-full border border-slate-200 dark:border-white/10 hover:bg-slate-100 dark:hover:bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-zinc-400 text-center cursor-pointer">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Switch tabs logic
function switchTab(tabId) {
    const targetContent = document.getElementById(tabId);
    if (!targetContent) return;

    try {
        localStorage.setItem('admin_active_tab', tabId);
    } catch (e) {}

    // Hide all tab content elements explicitly
    document.querySelectorAll('.tab-content').forEach(el => {
        el.style.setProperty('display', 'none', 'important');
        el.classList.add('hidden');
    });

    // Display target element explicitly
    targetContent.style.setProperty('display', 'block', 'important');
    targetContent.classList.remove('hidden');

    // Reset all tab button styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active-tab', 'text-purple-600', 'dark:text-purple-400', 'font-black');
        btn.classList.add('text-slate-500', 'dark:text-zinc-400');
    });

    // Set active style on current tab button
    const activeBtn = document.getElementById(tabId + '-btn');
    if (activeBtn) {
        activeBtn.classList.add('active-tab', 'text-purple-600', 'dark:text-purple-400', 'font-black');
        activeBtn.classList.remove('text-slate-500', 'dark:text-zinc-400');
    }
}
window.switchTab = switchTab;

// Bind listeners and handle tab restoration
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const tabId = btn.getAttribute('data-tab');
            if (tabId) {
                switchTab(tabId);
            }
        });
    });

    try {
        const savedTab = localStorage.getItem('admin_active_tab');
        if (savedTab && document.getElementById(savedTab)) {
            switchTab(savedTab);
        } else {
            switchTab('members-tab');
        }
    } catch (e) {
        switchTab('members-tab');
    }
});

// Client filtering of members names
const memberFilterInput = document.getElementById('member-filter');
if (memberFilterInput) {
    memberFilterInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.member-list-item').forEach(item => {
            const name = item.getAttribute('data-name');
            if (name.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

// Edit Builder Profile & Credentials Modal
function toggleDegradeModal(btn) {
    const memberId = btn.getAttribute('data-id');
    const memberCode = btn.getAttribute('data-code');
    const memberName = btn.getAttribute('data-name');
    const currentLevel = btn.getAttribute('data-level');
    const currentRole = btn.getAttribute('data-role');
    
    document.getElementById('degrade-member-id').value = memberId;
    if (document.getElementById('degrade-code')) {
        document.getElementById('degrade-code').value = memberCode || '';
    }
    if (document.getElementById('degrade-name')) {
        document.getElementById('degrade-name').value = memberName || '';
    }
    if (document.getElementById('degrade-level')) {
        document.getElementById('degrade-level').value = currentLevel || 'Member';
    }
    if (document.getElementById('degrade-role')) {
        document.getElementById('degrade-role').value = currentRole || '';
    }
    if (document.getElementById('degrade-password')) {
        document.getElementById('degrade-password').value = '';
    }
    
    const modal = document.getElementById('degrade-modal');
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeDegradeModal() {
    const modal = document.getElementById('degrade-modal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

// Single member points update handler
function submitIndividualPoints(memberId, inputId) {
    const pointsVal = document.getElementById(inputId).value;
    document.getElementById('individual-points-member-id').value = memberId;
    document.getElementById('individual-points-value').value = pointsVal;
    document.getElementById('individual-points-form').submit();
}

// Single member delete handler
function submitIndividualDelete(memberId, name) {
    if (confirm('Remove ' + name + ' from the chapter?')) {
        document.getElementById('individual-delete-member-id').value = memberId;
        document.getElementById('individual-delete-form').submit();
    }
}

// Select All members logic
const selectAllCheckbox = document.getElementById('select-all-members');
if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', (e) => {
        const checked = e.target.checked;
        document.querySelectorAll('.member-checkbox').forEach(cb => {
            const item = cb.closest('.member-list-item');
            if (item && item.style.display !== 'none') {
                cb.checked = checked;
            }
        });
    });
}

// Edit Swag Modal Handlers
function openEditSwagModal(btn) {
    document.getElementById('edit-swag-id').value = btn.getAttribute('data-id');
    document.getElementById('edit-swag-name').value = btn.getAttribute('data-name');
    document.getElementById('edit-swag-pts').value = btn.getAttribute('data-pts');
    document.getElementById('edit-swag-stock').value = btn.getAttribute('data-stock');
    document.getElementById('edit-swag-desc').value = btn.getAttribute('data-desc');

    const modal = document.getElementById('edit-swag-modal');
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
}

function closeEditSwagModal() {
    const modal = document.getElementById('edit-swag-modal');
    modal.style.display = 'none';
    modal.classList.add('hidden');
}

// Edit Event Modal Handlers
let currentEditPhotos = [];

function openEditEventModal(btn) {
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    const type = btn.getAttribute('data-type');
    const date = btn.getAttribute('data-date');
    const location = btn.getAttribute('data-location');
    const link = btn.getAttribute('data-link');
    const description = btn.getAttribute('data-description');
    const photosJson = btn.getAttribute('data-photos');

    document.getElementById('edit-event-id').value = id;
    document.getElementById('edit-event-title').value = title;
    document.getElementById('edit-event-type').value = type;
    document.getElementById('edit-event-date').value = date;
    document.getElementById('edit-event-location').value = location;
    document.getElementById('edit-event-link').value = link || '';
    document.getElementById('edit-event-description').value = description;

    try {
        currentEditPhotos = JSON.parse(photosJson) || [];
    } catch (e) {
        currentEditPhotos = [];
    }
    renderEditPhotosList();

    const modal = document.getElementById('edit-event-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function renderEditPhotosList() {
    const container = document.getElementById('edit-event-photos-grid');
    const keptInput = document.getElementById('edit-event-kept-photos');
    if (!container || !keptInput) return;

    keptInput.value = JSON.stringify(currentEditPhotos);
    container.innerHTML = '';

    if (currentEditPhotos.length === 0) {
        container.innerHTML = '<p class="text-xs text-slate-400 italic py-2">No photos currently attached to this event.</p>';
        return;
    }

    currentEditPhotos.forEach((photoUrl, idx) => {
        const item = document.createElement('div');
        item.className = 'relative group w-16 h-16 rounded-xl overflow-hidden border border-slate-200 dark:border-white/10 shrink-0';
        item.innerHTML = `
            <img src="${photoUrl}" class="w-full h-full object-cover">
            <button type="button" onclick="removeEditPhoto(${idx})" title="Remove photo" class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px] font-bold shadow hover:bg-red-700 cursor-pointer">
                ✕
            </button>
            ${idx === 0 ? '<span class="absolute bottom-0 inset-x-0 bg-purple-600/90 text-white text-[7px] font-bold text-center py-0.5 uppercase tracking-wider">Main</span>' : ''}
        `;
        container.appendChild(item);
    });
}

function removeEditPhoto(index) {
    currentEditPhotos.splice(index, 1);
    renderEditPhotosList();
}

function closeEditEventModal() {
    const modal = document.getElementById('edit-event-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

// Edit Notice / Blog Modal Handlers
function openEditPostModal(btn) {
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    const category = btn.getAttribute('data-category');
    const date = btn.getAttribute('data-date');
    const excerpt = btn.getAttribute('data-excerpt');

    document.getElementById('edit-post-id').value = id;
    document.getElementById('edit-post-title').value = title;
    document.getElementById('edit-post-category').value = category;
    document.getElementById('edit-post-date').value = date;
    document.getElementById('edit-post-excerpt').value = excerpt;

    const modal = document.getElementById('edit-post-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }
}

function closeEditPostModal() {
    const modal = document.getElementById('edit-post-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

// Active Tab buttons styling injected via class
const style = document.createElement('style');
style.textContent = `
    .hidden {
        display: none !important;
    }
    .active-tab {
        background: rgba(147, 51, 234, 0.1) !important;
        border: 1px solid rgba(147, 51, 234, 0.25) !important;
        color: #a855f7 !important;
    }
    .dark .active-tab {
        color: #c084fc !important;
    }
`;
document.head.appendChild(style);
</script>

<?php
require_once 'includes/footer.php';
?>
