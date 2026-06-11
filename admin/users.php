<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$error = getFlashError();
$success = getFlashSuccess();
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashError('Your session expired. Please try again.');
        redirect('users.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_user') {
            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $mobile = trim((string) ($_POST['mobile'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($fullname === '' || $username === '' || $email === '' || $password === '') {
                throw new InvalidArgumentException('Please complete all user fields.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Please enter a valid email address.');
            }

            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Password must be at least 8 characters.');
            }

            createUser($fullname, $username, $email, $password, 'receptionist', $mobile);
            setFlashSuccess('Receptionist account created successfully.');
            redirect('users.php');
        }

        if ($action === 'update_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $mobile = trim((string) ($_POST['mobile'] ?? ''));

            if ($userId <= 0 || $fullname === '' || $username === '' || $email === '') {
                throw new InvalidArgumentException('Please complete all required user fields.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Please enter a valid email address.');
            }

            updateReceptionist($userId, $fullname, $username, $email, $mobile);
            setFlashSuccess('Receptionist details updated successfully.');
            redirect('users.php');
        }

        if ($action === 'reset_password') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = (string) ($_POST['new_password'] ?? '');

            if ($userId <= 0 || strlen($newPassword) < 8) {
                throw new InvalidArgumentException('New password must be at least 8 characters.');
            }

            resetUserPassword($userId, $newPassword);
            setFlashSuccess('Password updated successfully.');
            redirect('users.php');
        }
    } catch (PDOException $exception) {
        setFlashError('Username or email may already exist.');
        redirect('users.php');
    } catch (InvalidArgumentException $exception) {
        setFlashError($exception->getMessage());
        redirect('users.php');
    }
}

$users = listReceptionists();
$resetRequests = listPendingPasswordResetRequests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/admin-users.js" defer></script>
</head>
<body class="dashboard-page">
    <main class="dashboard-shell dashboard-shell-wide">
        <section class="dashboard-panel">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Admin</p>
                    <h1>Manage Users</h1>
                    <p class="muted">Receptionist accounts, contact details, and password recovery.</p>
                </div>
                <a class="button button-light" href="dashboard.php">Dashboard</a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success) ?></div>
            <?php endif; ?>

            <details class="compact-create">
                <summary>Create Receptionist</summary>
                <form class="admin-form" action="users.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullname">Full Name</label>
                            <input type="text" id="fullname" name="fullname" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Gmail / Email</label>
                            <input type="email" id="email" name="email" placeholder="name@gmail.com" required>
                        </div>

                        <div class="form-group">
                            <label for="mobile">Mobile Number</label>
                            <input type="tel" id="mobile" name="mobile" placeholder="09xxxxxxxxx">
                        </div>

                        <div class="form-group">
                            <label for="password">Temporary Password</label>
                            <input type="password" id="password" name="password" minlength="8" required>
                        </div>
                    </div>

                    <button class="button" type="submit">Create User</button>
                </form>
            </details>

            <section class="user-list-panel">
                <div class="list-toolbar">
                    <div>
                        <h2>Receptionists</h2>
                        <p class="muted"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></p>
                    </div>
                    <input
                        class="table-search"
                        type="search"
                        id="userSearch"
                        placeholder="Search users"
                        aria-label="Search receptionists"
                    >
                </div>

                <div class="table-wrap">
                    <table class="compact-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $account): ?>
                                <tr>
                                    <td><?= e($account['fullname']) ?></td>
                                    <td><?= e($account['username']) ?></td>
                                    <td><?= e($account['email']) ?></td>
                                    <td><?= e($account['mobile'] ?? '') ?></td>
                                    <td>
                                        <div class="row-actions">
                                            <button
                                                class="button button-small button-light js-edit-user"
                                                type="button"
                                                data-id="<?= e((string) $account['id']) ?>"
                                                data-fullname="<?= e($account['fullname']) ?>"
                                                data-username="<?= e($account['username']) ?>"
                                                data-email="<?= e($account['email']) ?>"
                                                data-mobile="<?= e($account['mobile'] ?? '') ?>"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                class="button button-small js-reset-user"
                                                type="button"
                                                data-id="<?= e((string) $account['id']) ?>"
                                                data-name="<?= e($account['fullname']) ?>"
                                            >
                                                Reset
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <dialog class="app-dialog" id="editUserDialog">
                <form class="admin-form dialog-form" action="users.php" method="POST">
                    <div class="dialog-header">
                        <div>
                            <p class="eyebrow">Receptionist</p>
                            <h2>Edit User</h2>
                        </div>
                        <button class="icon-button js-close-dialog" type="button" aria-label="Close dialog">x</button>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editFullname">Full Name</label>
                            <input type="text" id="editFullname" name="fullname" required>
                        </div>

                        <div class="form-group">
                            <label for="editUsername">Username</label>
                            <input type="text" id="editUsername" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="editEmail">Gmail / Email</label>
                            <input type="email" id="editEmail" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="editMobile">Mobile Number</label>
                            <input type="tel" id="editMobile" name="mobile">
                        </div>
                    </div>

                    <div class="dialog-actions">
                        <button class="button button-light js-close-dialog" type="button">Cancel</button>
                        <button class="button" type="submit">Save Changes</button>
                    </div>
                </form>
            </dialog>

            <dialog class="app-dialog" id="resetPasswordDialog">
                <form class="admin-form dialog-form" action="users.php" method="POST">
                    <div class="dialog-header">
                        <div>
                            <p class="eyebrow">Security</p>
                            <h2>Reset Password</h2>
                            <p class="muted" id="resetUserName"></p>
                        </div>
                        <button class="icon-button js-close-dialog" type="button" aria-label="Close dialog">x</button>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">

                    <div class="form-group">
                        <label for="resetPassword">New Password</label>
                        <input type="password" id="resetPassword" name="new_password" placeholder="At least 8 characters" minlength="8" required>
                    </div>

                    <div class="dialog-actions">
                        <button class="button button-light js-close-dialog" type="button">Cancel</button>
                        <button class="button" type="submit">Reset Password</button>
                    </div>
                </form>
            </dialog>

            <section>
                <h2>Password Reset Requests</h2>
                <?php if ($resetRequests === []): ?>
                    <p class="muted">No pending password reset requests.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Requested</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resetRequests as $request): ?>
                                    <tr>
                                        <td><?= e($request['fullname']) ?></td>
                                        <td><?= e($request['username']) ?></td>
                                        <td><?= e($request['email']) ?></td>
                                        <td><?= e($request['requested_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
