<?php
require_once __DIR__ . '/../../backend/includes/bootstrap.php';
require_role('admin');

$st = db()->prepare('SELECT * FROM documents WHERE id = ?');
$st->execute([get_int('id')]);
$doc = $st->fetch();

if (!$doc) {
    abort_page(404, 'Không tìm thấy minh chứng này.');
}
send_document_file($doc);
