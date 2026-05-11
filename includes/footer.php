<!-- PAGE CONTENT ENDS HERE -->
        </div><!-- .page-content -->
    </div><!-- .main-content -->
</div><!-- .app-layout -->

<!-- Confirm Dialog -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="confirm-title" id="confirmTitle">Are you sure?</div>
        <div class="confirm-msg" id="confirmMsg">This action cannot be undone.</div>
        <div class="confirm-actions">
            <button class="btn-confirm-no" onclick="closeConfirm()">Cancel</button>
            <button class="btn-confirm-yes" id="confirmYes">Yes, Delete</button>
        </div>
    </div>
</div>

<!--
    IMPORTANT LOAD ORDER:
    1. Leaflet JS  (for maps)
    2. Chart.js    (local file – works offline in XAMPP; CDN fallback if missing)
    3. app.js      (global helpers)
    Page-level <script> blocks in each page run AFTER these because they are
    placed earlier in the HTML — to ensure Chart & L are defined first,
    all chart/map init code is wrapped in window.addEventListener('load', ...).
-->

<!-- 1. Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<!-- 2. Chart.js — local copy bundled in project (no internet needed) -->
<script src="<?= SITE_URL ?>/assets/js/chart.umd.min.js"></script>
<script>
/* If local chart file failed, load from CDN as backup */
if (typeof Chart === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"><\/script>');
}
</script>

<!-- 3. App helpers -->
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>