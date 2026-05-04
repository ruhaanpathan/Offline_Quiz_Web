<?php if (isLoggedIn()): ?>
    </main><!-- /.main-content -->
</div><!-- /.app-layout -->
<?php endif; ?>

<script src="/assets/js/app.js"></script>
<script>
// Force Chart.js charts to fit their containers after rendering
(function resizeCharts() {
    var attempts = 0;
    function tryResize() {
        attempts++;
        if (typeof Chart !== 'undefined' && Object.keys(Chart.instances).length > 0) {
            Object.values(Chart.instances).forEach(function(c) { c.resize(); });
        } else if (attempts < 20) {
            setTimeout(tryResize, 200);
        }
    }
    if (document.readyState === 'complete') tryResize();
    else window.addEventListener('load', function() { setTimeout(tryResize, 150); });
})();
</script>
<?php if (isset($extraScripts))
    echo $extraScripts; ?>
</body>

</html>