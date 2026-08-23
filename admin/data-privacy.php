<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-privacy.php';

$admin = require_admin_menu('admin-data-privacy');
$pageTitle = 'Data Privacy';
$active = 'admin-data-privacy';
$pdo = db();
data_privacy_ensure_table($pdo);

$message = null;
$error = null;
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!admin_can_manage_operations($admin)) {
            throw new RuntimeException('Admin or Super Admin permission required.');
        }

        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Choose a policy to delete.');
            }
            $stmt = $pdo->prepare('DELETE FROM data_privacy_policies WHERE id = ?');
            $stmt->execute([$id]);
            $message = 'Policy deleted.';
            $editId = 0;
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $version = trim((string) ($_POST['version'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'Draft'));
            $content = data_privacy_sanitize_html((string) ($_POST['contentHtml'] ?? ''));

            if ($title === '' || strlen($title) > 160) {
                throw new RuntimeException('Enter a policy title up to 160 characters.');
            }
            if ($version === '' || strlen($version) > 40 || !preg_match('/^[A-Za-z0-9._-]+$/', $version)) {
                throw new RuntimeException('Use a short version like 2026-08 or v1.0.');
            }
            if (!in_array($status, ['Draft', 'Active', 'Archived'], true)) {
                throw new RuntimeException('Choose a valid policy status.');
            }
            if ($content === '') {
                throw new RuntimeException('Policy content is required.');
            }

            $pdo->beginTransaction();
            if ($status === 'Active') {
                $pdo->exec("UPDATE data_privacy_policies SET status = 'Archived' WHERE status = 'Active'");
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE data_privacy_policies
                     SET title = ?, version = ?, content_html = ?, status = ?, updated_by = ?
                     WHERE id = ?'
                );
                $stmt->execute([$title, $version, $content, $status, (int) $admin['id'], $id]);
                $message = 'Policy updated.';
                $editId = $id;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO data_privacy_policies
                     (title, version, content_html, status, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$title, $version, $content, $status, (int) $admin['id'], (int) $admin['id']]);
                $editId = (int) $pdo->lastInsertId();
                $message = 'Policy created.';
            }
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$policies = data_privacy_policies($pdo);
$editing = null;
foreach ($policies as $policy) {
    if ((int) $policy['id'] === $editId) {
        $editing = $policy;
        break;
    }
}

if ($editing === null) {
    $editing = [
        'id' => 0,
        'title' => 'Data Privacy Policy',
        'version' => date('Y-m'),
        'contentHtml' => data_privacy_default_policy()['contentHtml'],
        'status' => 'Draft',
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Data Privacy</span>
                <h2 class="mt-1 mb-1 fw-black">Policy content</h2>
                <p class="mb-0 small text-secondary fw-semibold">Create, edit, activate, archive, or delete Data Privacy Policy content shown in member consent modals.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('admin/members.php')); ?>" class="btn btn-outline-primary btn-sm">Members</a>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert alert-primary"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="row g-3">
        <div class="col-xl-7">
            <form method="post" class="app-card" id="privacyPolicyForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
                <textarea hidden name="contentHtml" id="privacyPolicyContentInput"><?php echo htmlspecialchars((string) $editing['contentHtml']); ?></textarea>

                <div class="row g-3">
                    <label class="col-md-6 small fw-bold">Title
                        <input required name="title" class="form-input mt-1" maxlength="160" value="<?php echo htmlspecialchars((string) $editing['title']); ?>">
                    </label>
                    <label class="col-md-3 small fw-bold">Version
                        <input required name="version" class="form-input mt-1" maxlength="40" value="<?php echo htmlspecialchars((string) $editing['version']); ?>" placeholder="2026-08">
                    </label>
                    <label class="col-md-3 small fw-bold">Status
                        <select required name="status" class="form-select mt-1">
                            <?php foreach (['Draft', 'Active', 'Archived'] as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $editing['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="mt-3">
                    <div class="privacy-editor-toolbar" aria-label="Editor toolbar">
                        <button type="button" data-editor-command="bold"><strong>B</strong></button>
                        <button type="button" data-editor-command="italic"><em>I</em></button>
                        <button type="button" data-editor-command="underline"><u>U</u></button>
                        <button type="button" data-editor-command="insertUnorderedList">Bullets</button>
                        <button type="button" data-editor-command="insertOrderedList">Numbers</button>
                        <button type="button" data-editor-block="h3">Heading</button>
                        <button type="button" data-editor-command="createLink">Link</button>
                        <button type="button" data-editor-command="removeFormat">Clear</button>
                    </div>
                    <div id="privacyPolicyEditor" class="privacy-wysiwyg-editor" contenteditable="true"><?php echo (string) $editing['contentHtml']; ?></div>
                </div>

                <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
                    <a href="<?php echo htmlspecialchars(app_url('admin/data-privacy.php')); ?>" class="btn btn-outline-secondary btn-sm">New Draft</a>
                    <button class="btn btn-primary btn-sm" type="submit">Save Policy</button>
                </div>
            </form>
        </div>

        <div class="col-xl-5">
            <section class="app-card">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <span class="section-kicker">Versions</span>
                        <h3 class="mt-1 mb-0 fw-black">Saved policies</h3>
                    </div>
                </div>

                <?php if (empty($policies)): ?>
                    <div class="rounded-lg border border-dashed border-line p-3 small fw-semibold text-secondary">No saved policies yet.</div>
                <?php else: ?>
                    <div class="grid gap-2">
                        <?php foreach ($policies as $policy): ?>
                            <article class="privacy-policy-row">
                                <div>
                                    <p class="mb-1 fw-black"><?php echo htmlspecialchars($policy['title']); ?></p>
                                    <p class="mb-0 text-xs text-secondary">
                                        Version <?php echo htmlspecialchars($policy['version']); ?>
                                        <?php if (!empty($policy['updatedAt'])): ?>
                                            · <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime((string) $policy['updatedAt']))); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-end gap-1">
                                    <span class="status-badge <?php echo $policy['status'] === 'Active' ? 'status-badge-booked' : ($policy['status'] === 'Archived' ? 'status-badge-cancelled' : 'status-badge-pending'); ?>">
                                        <?php echo htmlspecialchars($policy['status']); ?>
                                    </span>
                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(app_url('admin/data-privacy.php?edit=' . (int) $policy['id'])); ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this policy version?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $policy['id']; ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('privacyPolicyForm');
    const editor = document.getElementById('privacyPolicyEditor');
    const input = document.getElementById('privacyPolicyContentInput');
    if (!form || !editor || !input) return;

    document.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('click', () => {
            const command = button.dataset.editorCommand;
            if (command === 'createLink') {
                const url = window.prompt('Enter link URL');
                if (!url) return;
                document.execCommand(command, false, url);
            } else {
                document.execCommand(command, false, null);
            }
            editor.focus();
        });
    });

    document.querySelectorAll('[data-editor-block]').forEach(button => {
        button.addEventListener('click', () => {
            document.execCommand('formatBlock', false, button.dataset.editorBlock);
            editor.focus();
        });
    });

    form.addEventListener('submit', () => {
        input.value = editor.innerHTML.trim();
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
