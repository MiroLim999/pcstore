<?php
/**
 * footer.php — Closes the HTML document.
 * Includes the main app.js for sidebar toggle, theme switch, etc.
 */
$hide_sidebar = $hide_sidebar ?? false;
?>

<?php if (!$hide_sidebar && is_logged_in()): ?>
</main><!-- /.main-content -->
<?php endif; ?>

<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<?php if (!empty($page_js)): ?>
<?php foreach ((array)$page_js as $js): ?>
<script src="<?= e($js) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
