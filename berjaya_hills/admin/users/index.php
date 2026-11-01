<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Administrator','Management','IT');
$pageTitle = 'User Management';

$rows = db()->query("
    SELECT
        u.*,
        GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="admin-shell">
    <aside class="sidebar">
        <?php require __DIR__ . '/../sidebar.php'; ?>
    </aside>

    <section class="admin-content">
        <div class="admin-top">
            <div>
                <div class="eyebrow">Access Control</div>
                <h1 class="admin-title">User Management</h1>
                <p class="kicker">Review registered accounts, manage access and approve pending users.</p>
            </div>
            <div class="admin-actions">
                <span class="admin-count"><?= count($rows) ?> Users</span>
            </div>
        </div>

        <div class="user-kpi-grid">
            <?php
            $pending = 0;
            $approved = 0;
            $rejected = 0;
            foreach ($rows as $r) {
                if ($r['account_status'] === 'pending') $pending++;
                elseif ($r['account_status'] === 'approved') $approved++;
                elseif ($r['account_status'] === 'rejected') $rejected++;
            }
            ?>
            <div class="user-kpi"><span>All Users</span><strong><?= count($rows) ?></strong><small>Registered accounts</small></div>
            <div class="user-kpi user-kpi-gold"><span>Pending</span><strong><?= $pending ?></strong><small>Require review</small></div>
            <div class="user-kpi user-kpi-green"><span>Approved</span><strong><?= $approved ?></strong><small>Active accounts</small></div>
            <div class="user-kpi user-kpi-red"><span>Rejected</span><strong><?= $rejected ?></strong><small>Access declined</small></div>
        </div>

        <div class="panel user-management-panel">
            <div class="user-toolbar">
                <div>
                    <h2>Registered users</h2>
                    <p>Account details and current access status.</p>
                </div>
                <div class="user-search-wrap">
                    <span>⌕</span>
                    <input id="userSearch" type="search" placeholder="Search name, email or company..." autocomplete="off">
                </div>
            </div>

            <div class="table-wrap user-table-wrap">
                <table class="table user-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="user-row">
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?= e(strtoupper(substr($r['full_name'] ?? 'U', 0, 1))) ?></div>
                                    <div class="user-primary">
                                        <strong><?= e($r['full_name']) ?></strong>
                                        <span><?= e($r['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($r['company_name'] ?: '—') ?></td>
                            <td><span class="role-pill"><?= e($r['roles'] ?: 'User') ?></span></td>
                            <td>
                                <span class="badge <?= $r['account_status'] === 'approved' ? 'badge-green' : ($r['account_status'] === 'rejected' ? 'badge-red' : 'badge-gold') ?>">
                                    <?= e(ucfirst($r['account_status'])) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td class="action-col">
                                <?php $isAdminAccount = in_array('Administrator', array_map('trim', explode(',', $r['roles'] ?? '')), true); ?>
                                <?php if ($isAdminAccount): ?>
                                    <span class="muted-action">—</span>
                                <?php else: ?>
                                    <div class="user-actions-form">
                                        <a class="btn btn-outline btn-sm user-view-icon" href="<?= url('admin/users/view.php?id='.(int)$r['id']) ?>" aria-label="View user details" title="View details">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <form method="post" action="<?= url('admin/users/action.php') ?>" class="user-actions-form" onsubmit="return this.elements.action.value === 'delete' ? confirm('Delete this user account? This cannot be undone.') : true;">
                                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <select name="action" aria-label="Change user status">
                                                <option value="status_pending" <?= $r['account_status']==='pending'?'selected':'' ?>>Pending</option>
                                                <option value="approve" <?= $r['account_status']==='approved'?'selected':'' ?>>Approved</option>
                                                <option value="reject" <?= $r['account_status']==='rejected'?'selected':'' ?>>Rejected</option>
                                            </select>
                                            <button class="btn btn-gold btn-sm" type="submit">Update</button>
                                            <button class="btn btn-danger btn-sm" name="action" value="delete" type="submit" onclick="this.form.action.value='delete'">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="noUserResults" style="display:none">
                        <td colspan="6" class="empty-table">No users matched your search.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const input = document.getElementById('userSearch');
    const rows = [...document.querySelectorAll('#usersTable .user-row')];
    const empty = document.getElementById('noUserResults');
    if (!input) return;
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(row => {
            const match = row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        empty.style.display = visible ? 'none' : '';
    });
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
