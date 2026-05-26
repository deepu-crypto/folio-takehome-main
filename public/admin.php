<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publishAtInput = trim($_POST['publish_at'] ?? '');
    $publishAt = null;

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } elseif ($publishAtInput !== '') {
        $scheduledAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $publishAtInput,
            new DateTimeZone('America/Chicago')
        );

        $dateErrors = DateTimeImmutable::getLastErrors();

        if (
            $scheduledAt === false ||
            ($dateErrors !== false &&
                ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
        ) {
            $error = 'Please enter a valid publish date and time.';
        } else {
            $publishAt = $scheduledAt
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        }
    }

    if ($error === null) {
    $publicId = generate_document_public_id($title);

    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by, publish_at, public_id)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$title, $body, $staff['id'], $publishAt, $publicId]);
    $docId = (int) db()->lastInsertId();

    audit_log('create', 'document', $docId, [
        'title' => $title,
        'publish_at' => $publishAt,
        'public_id' => $publicId,
    ]);

    if ($publishAt !== null) {
        audit_log('schedule', 'document', $docId, [
            'publish_at' => $publishAt,
        ]);
    }

    header('Location: /admin.php?created=' . rawurlencode($publicId));
    exit;
}
}

$search = trim($_GET['q'] ?? '');
$docs = find_documents($search);

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">
        Document <?= h($_GET['created']) ?> created.
    </div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
    <label for="body">Body</label>
    <textarea id="body" name="body" required></textarea>
</div>

<div class="form-field">
    <label for="publish_at">Publish at (Central Time, optional)</label>
    <input type="datetime-local" id="publish_at" name="publish_at">
    <p class="meta">Leave blank to make this document available immediately.</p>
</div>

<button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Documents</h2>
        <form method="get">
        <div class="form-field">
            <label for="q">Search documents by title or ID</label>
            <input
                type="search"
                id="q"
                name="q"
                value="<?= h($search) ?>"
                placeholder="Search title or doc ID..."
            >
        </div>

        <button type="submit" class="btn">Search</button>

        <?php if ($search !== ''): ?>
            <a href="/admin.php" class="back-link">Clear search</a>
        <?php endif ?>
    </form>
    <?php if (empty($docs)): ?>
    <?php if ($search !== ''): ?>
        <p class="empty">No documents match your search.</p>
    <?php else: ?>
        <p class="empty">No documents yet.</p>
    <?php endif ?>
<?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td class="id">#<?= h($d['public_id']) ?></td>
                        <td><?= h($d['title']) ?></td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td><?= h($d['created_at']) ?></td>
                        <td>
    <a href="/share.php?doc=<?= rawurlencode($d['public_id']) ?>" class="btn-link">
        Create share →
    </a>
</td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>
