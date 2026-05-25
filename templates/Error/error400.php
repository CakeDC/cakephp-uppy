<?php
/**
 * @var \Cake\View\View $this
 * @var \Throwable $error
 */
$this->disableAutoLayout();
?>
<h2><?= htmlspecialchars($error->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h2>
