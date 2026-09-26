<?php
// account.php - Member self-service account settings
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

check_auth();

if (!is_member()) {
    header('Location: index.php');
    exit;
}

$member = get_logged_member();
if (!$member) {
    header('Location: logout.php');
    exit;
}

$success = '';
$error = '';

$uploadDir = __DIR__ . '/public/images/AWS-MembersPics/';
$relativeUploadDir = 'public/images/AWS-MembersPics/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Please fill in all password fields.';
        } elseif (empty($member['password']) || !password_verify($currentPassword, $member['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif (password_verify($newPassword, $member['password'])) {
            $error = 'New password must be different from your current password.';
        } else {
            $stmt = $db->prepare("UPDATE members SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $member['id']]);
            $success = 'Your password has been changed successfully.';
            $member = get_logged_member();
        }
    }

    if ($action === 'change_photo') {
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a profile photo to upload.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Profile photo must be 5 MB or smaller.';
        } else {
            $tmp = $_FILES['profile_photo']['tmp_name'];
            $imageInfo = @getimagesize($tmp);
            $allowed = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp'
            ];

            if (!$imageInfo || !isset($allowed[$imageInfo[2]])) {
                $error = 'Please upload a valid JPG, PNG, or WebP image.';
            } else {
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                    $error = 'Unable to prepare the profile photo directory.';
                } else {
                    $extension = $allowed[$imageInfo[2]];
                    $filename = 'member_' . (int)$member['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                    $destination = $uploadDir . $filename;

                    if (move_uploaded_file($tmp, $destination)) {
                        $newRelativePath = $relativeUploadDir . $filename;
                        $oldImage = $member['image'] ?? '';

                        $stmt = $db->prepare("UPDATE members SET image = ? WHERE id = ?");
                        $stmt->execute([$newRelativePath, $member['id']]);

                        if ($oldImage && strpos($oldImage, $relativeUploadDir) === 0 && basename($oldImage) !== 'default.png') {
                            $oldPath = __DIR__ . '/' . ltrim($oldImage, '/');
                            if (is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }

                        $success = 'Your profile photo has been updated successfully.';
                        $member = get_logged_member();
                    } else {
                        $error = 'Unable to save the uploaded profile photo.';
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mx-auto max-w-5xl px-4 sm:px-6 pb-24 pt-8 lg:px-8">
    <div class="mb-8">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-purple-600 dark:text-purple-400">Member Account</p>
        <h1 class="mt-2 text-3xl sm:text-5xl font-black text-slate-900 dark:text-white font-space">My <span class="text-glow-gradient">Profile</span></h1>
        <p class="mt-3 text-sm text-slate-500 dark:text-zinc-400">Update your profile photo and password whenever you need to.</p>
    </div>

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

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 sm:p-8 shadow-md">
            <div class="flex items-center gap-4 mb-7">
                <img src="<?php echo htmlspecialchars($member['image'] ?: 'public/images/AWS-MembersPics/default.png'); ?>?v=<?php echo time(); ?>"
                     alt="Profile photo"
                     class="h-24 w-24 rounded-2xl object-cover border border-purple-500/30 shadow-lg">
                <div>
                    <h2 class="text-xl font-black text-slate-900 dark:text-white font-space"><?php echo htmlspecialchars($member['name']); ?></h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400"><?php echo htmlspecialchars($member['role'] ?? 'Builder'); ?></p>
                    <p class="text-xs text-slate-500 dark:text-zinc-400"><?php echo htmlspecialchars($member['team'] ?? ''); ?></p>
                </div>
            </div>

            <h3 class="text-sm font-black uppercase tracking-wider text-slate-700 dark:text-zinc-200 mb-3">Change Profile Photo</h3>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-5">JPG, PNG, or WebP. Maximum size: 5 MB.</p>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="change_photo">
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required
                       class="block w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-3 text-xs text-slate-700 dark:text-zinc-300 file:mr-4 file:rounded-full file:border-0 file:bg-purple-600 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                <button type="submit" class="w-full rounded-full bg-purple-600 hover:bg-purple-500 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition-all shadow-md shadow-purple-600/20">
                    Update Profile Photo
                </button>
            </form>
        </section>

        <section class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] p-6 sm:p-8 shadow-md">
            <h2 class="text-xl font-black text-slate-900 dark:text-white font-space mb-2">Change Password</h2>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6">Use your current password to set a new one. Minimum 8 characters.</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">

                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-zinc-400">Current Password</span>
                    <input type="password" name="current_password" required autocomplete="current-password"
                           class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </label>

                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-zinc-400">New Password</span>
                    <input type="password" name="new_password" minlength="8" required autocomplete="new-password"
                           class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </label>

                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-zinc-400">Confirm New Password</span>
                    <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"
                           class="w-full rounded-2xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-purple-500">
                </label>

                <button type="submit" class="w-full rounded-full bg-slate-900 dark:bg-white/10 hover:bg-slate-800 dark:hover:bg-white/15 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition-all">
                    Change Password
                </button>
            </form>
        </section>
    </div>
</div>
