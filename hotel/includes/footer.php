<?php
/**
 * Shared Footer Template
 * 
 * Closes body and HTML tags. Loads Bootstrap bundle JavaScript (with Popper)
 * and our custom JS file.
 */
?>
    <footer class="text-center py-4 bg-white mt-auto">
        <div class="container">
            <p class="mb-0 text-dark fw-semibold">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(get_setting('hotel_name', 'Josil')); ?>. All Rights Reserved.</p>
        </div>
    </footer>

<?php if (is_logged_in()): ?>
    </main>
</div>
<?php endif; ?>

    <!-- Bootstrap 5.3 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Application JavaScript -->
    <script src="/hotel/assets/js/main.js"></script>
</body>
</html>
