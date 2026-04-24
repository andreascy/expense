<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
if ($_SESSION['user']['role'] !== 'admin') { header('Location: /index.php'); exit; }
$pageTitle    = 'Users';
$pageSubtitle = 'Manage system users and permissions';
$activePage   = 'users';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php
$topbarActions = '<button class="btn btn-primary btn-sm" id="addUserBtn"><i class="bi bi-person-plus me-1"></i>Add User</button>';
include __DIR__ . '/../includes/topbar.php';
?>
<div class="page-body">

<div class="card">
  <div class="card-header">
    <i class="bi bi-people" style="color:var(--primary)"></i>
    All Users
    <span id="userCount" class="badge badge-secondary ms-auto">—</span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0" id="usersTable">
      <thead>
        <tr>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="usersBody">
        <tr><td colspan="6" class="text-center py-5" style="color:var(--text-3)">
          <span class="spinner-border spinner-border-sm me-1"></span>Loading…
        </td></tr>
      </tbody>
    </table>
  </div>
</div>

</div>
</div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="userForm">
          <input type="hidden" id="userId">
          <div class="mb-3">
            <label class="form-label">Full Name *</label>
            <input type="text" id="userName" class="form-control" placeholder="John Smith" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email *</label>
            <input type="email" id="userEmail" class="form-control" placeholder="john@company.com" required>
          </div>
          <div class="mb-3">
            <label class="form-label" id="passLabel">Password *</label>
            <input type="password" id="userPassword" class="form-control" placeholder="Min 6 characters">
            <div class="form-text" id="passHint"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select id="userRole" class="form-select">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveUserBtn">
          <i class="bi bi-check-lg me-1"></i>Save User
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div style="font-size:2.5rem;color:var(--red)" class="mb-3"><i class="bi bi-trash3"></i></div>
        <h5 class="mb-1">Delete User?</h5>
        <p style="color:var(--text-3);font-size:.875rem" class="mb-4" id="deleteUserName">This cannot be undone.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" id="confirmDeleteBtn"><i class="bi bi-trash3 me-1"></i>Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const userModal   = new bootstrap.Modal(document.getElementById('userModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
let deleteId = null;
let editMode = false;

const initials = n => n.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
const roleHtml = r => r === 'admin'
    ? '<span class="badge badge-primary"><i class="bi bi-shield-check me-1"></i>Admin</span>'
    : '<span class="badge badge-secondary">User</span>';
const statusHtml = (active, id) => `
    <div class="form-check form-switch mb-0">
        <input class="form-check-input toggle" type="checkbox" ${active==1?'checked':''} onchange="toggleActive(${id},this.checked)" style="cursor:pointer">
    </div>`;

async function loadUsers() {
    const res  = await fetch('/api/users.php');
    const data = await res.json();
    const rows = data.data || [];
    document.getElementById('userCount').textContent = rows.length;
    document.getElementById('usersBody').innerHTML = rows.length ? rows.map(u => `
        <tr>
            <td><div class="d-flex align-items-center gap-2">
                <div class="user-row-avatar">${initials(u.name)}</div>
                <span style="font-weight:500">${escHtml(u.name)}</span>
            </div></td>
            <td style="color:var(--text-2)">${escHtml(u.email)}</td>
            <td>${roleHtml(u.role)}</td>
            <td>${statusHtml(u.active, u.id)}</td>
            <td style="color:var(--text-3);font-size:.8rem">${u.created_at.split(' ')[0]}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary me-1" onclick="editUser(${u.id},'${escHtml(u.name)}','${escHtml(u.email)}','${u.role}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="showDelete(${u.id},'${escHtml(u.name)}')">
                    <i class="bi bi-trash3"></i>
                </button>
            </td>
        </tr>`).join('')
    : '<tr><td colspan="6" class="text-center py-5" style="color:var(--text-3)">No users found.</td></tr>';
}

document.getElementById('addUserBtn').addEventListener('click', () => {
    editMode = false;
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('passLabel').textContent = 'Password *';
    document.getElementById('passHint').textContent = '';
    document.getElementById('userPassword').required = true;
    userModal.show();
});

function editUser(id, name, email, role) {
    editMode = true;
    document.getElementById('userId').value    = id;
    document.getElementById('userName').value  = name;
    document.getElementById('userEmail').value = email;
    document.getElementById('userRole').value  = role;
    document.getElementById('userPassword').value = '';
    document.getElementById('userPassword').required = false;
    document.getElementById('passLabel').textContent = 'New Password';
    document.getElementById('passHint').textContent  = 'Leave blank to keep current password.';
    document.getElementById('userModalTitle').textContent = 'Edit User';
    userModal.show();
}

document.getElementById('saveUserBtn').addEventListener('click', async () => {
    const name     = document.getElementById('userName').value.trim();
    const email    = document.getElementById('userEmail').value.trim();
    const password = document.getElementById('userPassword').value;
    const role     = document.getElementById('userRole').value;
    const id       = document.getElementById('userId').value;

    if (!name || !email) return showToast('Name and email are required.', 'error');
    if (!editMode && !password) return showToast('Password is required.', 'error');

    const body   = { name, email, role, ...(password ? { password } : {}) };
    const method = editMode ? 'PATCH' : 'POST';
    if (editMode) body.id = parseInt(id);

    const res  = await fetch('/api/users.php', { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const data = await res.json();
    if (data.success) { userModal.hide(); loadUsers(); showToast(editMode ? 'User updated.' : 'User created.'); }
    else showToast(data.error || 'Error saving user.', 'error');
});

function showDelete(id, name) {
    deleteId = id;
    document.getElementById('deleteUserName').textContent = `Delete "${name}"? This cannot be undone.`;
    deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
    const res  = await fetch('/api/users.php?id=' + deleteId, { method: 'DELETE' });
    const data = await res.json();
    deleteModal.hide();
    if (data.success) { loadUsers(); showToast('User deleted.'); }
    else showToast(data.error || 'Error.', 'error');
});

async function toggleActive(id, active) {
    const res = await fetch('/api/users.php', { method: 'PATCH', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id, active }) });
    const data = await res.json();
    if (!data.success) { showToast(data.error || 'Error.', 'error'); loadUsers(); }
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadUsers();
</script>
</body></html>
