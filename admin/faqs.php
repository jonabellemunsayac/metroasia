<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/faqs.php';
$admin = require_admin_menu('admin-faqs');
$pageTitle = 'FAQs';
$active = 'admin-faqs';
$pdo = db();
faqs_ensure_table($pdo);
$_SESSION['page_content_csrf'] ??= bin2hex(random_bytes(32));
$error = null;
$editId = (int) ($_GET['edit'] ?? 0);
$editing = ['id' => 0, 'question' => '', 'answer_html' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        page_content_verify_csrf();
        if (!admin_can_manage_operations($admin)) throw new RuntimeException('Admin or Super Admin permission required.');
        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'save');
        $pdo->beginTransaction();
        // Lock the ordered set so concurrent moves cannot duplicate positions.
        $rows = $pdo->query('SELECT * FROM faqs ORDER BY sort_order, id FOR UPDATE')->fetchAll();
        $ids = array_map('intval', array_column($rows, 'id'));
        if ($id && !in_array($id, $ids, true)) throw new RuntimeException('This FAQ no longer exists. Reload the page.');
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM faqs WHERE id = ?')->execute([$id]);
        } elseif (in_array($action, ['up', 'down'], true)) {
            $index = array_search($id, $ids, true);
            if ($index === false) throw new RuntimeException('Choose a FAQ to reorder.');
            $other = $index + ($action === 'up' ? -1 : 1);
            if (isset($ids[$other])) [$ids[$index], $ids[$other]] = [$ids[$other], $ids[$index]];
            $update = $pdo->prepare('UPDATE faqs SET sort_order = ? WHERE id = ?');
            foreach ($ids as $position => $faqId) $update->execute([$position, $faqId]);
        } elseif ($action === 'save') {
            $question = trim((string) ($_POST['question'] ?? ''));
            $answer = page_content_sanitize_html((string) ($_POST['contentHtml'] ?? ''));
            $editing = ['id' => $id, 'question' => $question, 'answer_html' => $answer];
            if ($question === '' || mb_strlen($question) > 500) throw new RuntimeException('Enter a question up to 500 characters.');
            if (trim(strip_tags($answer)) === '') throw new RuntimeException('Enter an answer.');
            if ($id) $pdo->prepare('UPDATE faqs SET question = ?, answer_html = ? WHERE id = ?')->execute([$question, $answer, $id]);
            else $pdo->prepare('INSERT INTO faqs (question, answer_html, sort_order) VALUES (?, ?, ?)')->execute([$question, $answer, $rows ? max(array_column($rows, 'sort_order')) + 1 : 0]);
        } else throw new RuntimeException('Unknown action.');
        $pdo->commit();
        header('Location: ' . app_url('admin/faqs.php?saved=1'));
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $exception->getMessage();
    }
}
$faqs = faqs_all($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') foreach ($faqs as $faq) if ((int) $faq['id'] === $editId) $editing = $faq;
include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card mb-3"><span class="section-kicker">Quick Links</span><h2>FAQs</h2><p>Add questions and rich text answers. Use Move up and Move down to change their public order.</p></section>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-primary">FAQs updated.</div><?php endif; ?>
    <div class="row g-3">
        <div class="col-xl-7"><form method="post" class="app-card">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['page_content_csrf']); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $editing['id']; ?>">
            <input type="hidden" name="action" value="save">
            <label class="d-block fw-bold">Question<input class="form-input mt-1" name="question" maxlength="500" required value="<?php echo htmlspecialchars($editing['question']); ?>"></label>
            <p class="fw-bold mt-3 mb-1" id="faqAnswerLabel">Answer</p>
            <textarea hidden name="contentHtml"><?php echo htmlspecialchars($editing['answer_html']); ?></textarea>
            <div class="privacy-editor-toolbar" aria-label="Answer formatting">
                <button type="button" data-editor-command="bold">Bold</button>
                <button type="button" data-editor-block="h2">Heading 2</button>
                <button type="button" data-editor-block="h3">Heading 3</button>
                <button type="button" data-editor-block="p">Paragraph</button>
                <button type="button" data-editor-command="insertUnorderedList">Bullets</button>
                <button type="button" data-editor-command="insertOrderedList">Numbers</button>
                <button type="button" data-editor-command="createLink">Link</button>
                <button type="button" data-editor-command="removeFormat">Clear</button>
            </div>
            <div contenteditable="true" role="textbox" aria-labelledby="faqAnswerLabel" aria-multiline="true" class="privacy-wysiwyg-editor"><?php echo page_content_sanitize_html($editing['answer_html']); ?></div>
            <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary" type="submit">Save FAQ</button><a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(app_url('admin/faqs.php')); ?>">New FAQ</a></div>
        </form></div>
        <div class="col-xl-5"><section class="app-card"><h3>Saved FAQs</h3>
            <?php if (!$faqs): ?><p>No FAQs yet.</p><?php endif; ?>
            <?php foreach ($faqs as $position => $faq): ?>
                <article class="privacy-policy-row mb-2 flex-wrap">
                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($faq['question']); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="?edit=<?php echo (int) $faq['id']; ?>">Edit</a>
                        <form method="post" class="d-flex flex-wrap gap-2">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['page_content_csrf']); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $faq['id']; ?>">
                            <button name="action" value="up" class="btn btn-outline-secondary btn-sm" <?php echo $position === 0 ? 'disabled' : ''; ?>>Move up</button>
                            <button name="action" value="down" class="btn btn-outline-secondary btn-sm" <?php echo $position === count($faqs) - 1 ? 'disabled' : ''; ?>>Move down</button>
                            <button name="action" value="delete" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this FAQ?');">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section></div>
    </div>
</main>
<script src="<?php echo htmlspecialchars(app_url('assets/js/page-content-editor.js')); ?>" defer></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
