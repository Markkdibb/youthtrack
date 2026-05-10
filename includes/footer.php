<!-- PAGE CONTENT ENDS HERE -->
        </div><!-- .page-content -->
    </div><!-- .main-content -->
</div><!-- .app-layout -->

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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
</body>
</html>