<?php
/**
 * admin/manage_users.php — User Management
 *
 * Security:
 *  - require_admin() + admin_session guard
 *  - CSRF on every POST
 *  - clean_int(), clean_string(), validate_username() on all inputs
 *  - PDO prepared statements
 *  - Ownership protection: admins cannot delete/demote themselves
 *  - password_hash(bcrypt) when resetting passwords
 *  - e() on all output
 */

require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) { destroy_session(); redirect('admin_login.php'); }

$page_title = 'Manage Users';
$me         = (int)$_SESSION['user_id'];

// ── POST: delete user ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    require_csrf();
    $del_id = clean_int($_POST['user_id'] ?? 0);

    if ($del_id === $me) {
        set_flash('error', 'You cannot delete your own account.');
    } elseif ($del_id > 0) {
        try {
            $db   = get_db();
            // Safety: never delete other admins
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$del_id]);
            log_activity($me, 'admin_user_deleted');
            set_flash('success', 'User deleted.');
        } catch (PDOException $e) {
            error_log('[ADMIN DELETE USER] ' . $e->getMessage());
            set_flash('error', 'Could not delete user.');
        }
    }
    redirect('admin/manage_users.php');
}

// ── POST: edit user (username / role / optional new password) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    require_csrf();

    $edit_id      = clean_int($_POST['user_id']  ?? 0);
    $new_username = clean_string($_POST['username'] ?? '', 50);
    $new_role     = in_array($_POST['role'] ?? '', ['user', 'admin']) ? $_POST['role'] : 'user';
    $new_password = $_POST['new_password'] ?? '';

    $errors = [];

    if ($edit_id <= 0) {
        $errors[] = 'Invalid user.';
    }
    if (!validate_username($new_username)) {
        $errors[] = 'Invalid username format.';
    }
    // Prevent removing admin rights from yourself
    if ($edit_id === $me && $new_role !== 'admin') {
        $errors[] = 'You cannot remove your own admin role.';
    }

    if (empty($errors)) {
        try {
            $db = get_db();

            // Check username uniqueness (exclude current user)
            $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $chk->execute([$new_username, $edit_id]);
            if ($chk->fetch()) {
                $errors[] = 'Username already taken.';
            }
        } catch (PDOException $e) {
            error_log('[ADMIN EDIT USER CHECK] ' . $e->getMessage());
            $errors[] = 'Server error.';
        }
    }

    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                $pw_errors = validate_password($new_password);
                if (!empty($pw_errors)) {
                    $errors = array_merge($errors, $pw_errors);
                } else {
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt   = $db->prepare(
                        'UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?'
                    );
                    $stmt->execute([$new_username, $new_role, $hashed, $edit_id]);
                }
            } else {
                $stmt = $db->prepare('UPDATE users SET username = ?, role = ? WHERE id = ?');
                $stmt->execute([$new_username, $new_role, $edit_id]);
            }

            if (empty($errors)) {
                set_flash('success', 'User updated successfully.');
                redirect('admin/manage_users.php');
            }
        } catch (PDOException $e) {
            error_log('[ADMIN EDIT USER] ' . $e->getMessage());
            $errors[] = 'Could not save changes.';
        }
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect('admin/manage_users.php');
    }
}

// ── Fetch users ───────────────────────────────────────────────────────────
$users = [];
$search = clean_string($_GET['search'] ?? '', 50);

try {
    $db = get_db();
    if (!empty($search)) {
        $stmt = $db->prepare(
            "SELECT u.id, u.username, u.email, u.role, u.created_at,
                    COUNT(DISTINCT c.id) AS comments,
                    COUNT(DISTINCT b.id) AS bookings
             FROM   users u
             LEFT   JOIN comments c ON c.user_id = u.id
             LEFT   JOIN bookings b ON b.user_id = u.id
             WHERE  u.username LIKE ? OR u.email LIKE ?
             GROUP  BY u.id ORDER BY u.created_at DESC"
        );
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $db->prepare(
            "SELECT u.id, u.username, u.email, u.role, u.created_at,
                    COUNT(DISTINCT c.id) AS comments,
                    COUNT(DISTINCT b.id) AS bookings
             FROM   users u
             LEFT   JOIN comments c ON c.user_id = u.id
             LEFT   JOIN bookings b ON b.user_id = u.id
             GROUP  BY u.id ORDER BY u.created_at DESC"
        );
        $stmt->execute();
    }
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[ADMIN LIST USERS] ' . $e->getMessage());
}

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<!-- Search -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <form method="GET" action="manage_users.php" class="d-flex gap-2">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search username or email…"
               value="<?= eAttr($search) ?>" maxlength="50" style="width:220px;">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
        <?php if ($search): ?>
        <a href="manage_users.php" class="btn btn-sm btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>
    <span style="font-size:.82rem;color:var(--admin-muted);">
        <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?>
    </span>
</div>

<div class="stat-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 admin-table">
    <thead>
        <tr>
            <th class="px-3">Username</th>
            <th>Email</th>
            <th>Role</th>
            <th class="text-center">Comments</th>
            <th class="text-center">Bookings</th>
            <th>Joined</th>
            <th class="text-end pe-3">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
        <td class="px-3 fw-semibold"><?= e($u['username']) ?></td>
        <td style="color:var(--admin-muted);"><?= e($u['email']) ?></td>
        <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user' ?>"
                  style="font-size:.72rem;font-weight:700;padding:.3em .7em;border-radius:999px;">
                <?= e(ucfirst($u['role'])) ?>
            </span>
        </td>
        <td class="text-center"><?= (int)$u['comments'] ?></td>
        <td class="text-center"><?= (int)$u['bookings'] ?></td>
        <td style="color:var(--admin-muted);font-size:.82rem;">
            <?= e(date('M j, Y', strtotime($u['created_at']))) ?>
        </td>
        <td class="text-end pe-3">
            <div class="d-flex gap-1 justify-content-end">
                <!-- Edit button → modal -->
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#editModal"
                        data-uid="<?= (int)$u['id'] ?>"
                        data-username="<?= eAttr($u['username']) ?>"
                        data-role="<?= eAttr($u['role']) ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <?php if ($u['role'] !== 'admin' && (int)$u['id'] !== $me): ?>
                <form method="POST" action="manage_users.php"
                      onsubmit="return confirm('Delete <?= eAttr($u['username']) ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="user_id"    value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- Edit user modal -->
<div class="modal fade" id="editModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content" style="border-radius:14px;border:none;">
    <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" style="font-weight:700;">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="manage_users.php">
        <?= csrf_field() ?>
        <input type="hidden" name="edit_user" value="1">
        <input type="hidden" name="user_id"   id="edit-uid">
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <input type="text" name="username" id="edit-username"
                       class="form-control" maxlength="50" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Role</label>
                <select name="role" id="edit-role" class="form-select">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-1">
                <label class="form-label fw-semibold">
                    New Password
                    <span class="text-muted fw-normal" style="font-size:.8rem;">
                        — leave blank to keep current
                    </span>
                </label>
                <input type="password" name="new_password"
                       class="form-control" maxlength="255"
                       autocomplete="new-password">
            </div>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm"
                    style="background:#c0392b;color:#fff;">Save Changes</button>
        </div>
    </form>
</div>
</div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('edit-uid').value      = btn.dataset.uid;
    document.getElementById('edit-username').value = btn.dataset.username;
    document.getElementById('edit-role').value     = btn.dataset.role;
    document.querySelector('#editModal input[name="new_password"]').value = '';
});
</script>

<?php require_once 'admin_footer.php'; ?>
