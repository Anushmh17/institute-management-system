<?php
/**
 * Global Footer - closes HTML body
 */
?>
    </main><!-- /content-area -->
  </div><!-- /main-content -->
</div><!-- /layout-wrapper -->

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal" style="display:none;">
  <div class="modal confirm-modal">
    <div class="modal-icon danger"><i class="ri-error-warning-line"></i></div>
    <h3 class="modal-title" id="confirmTitle">Are you sure?</h3>
    <p class="modal-body" id="confirmBody">This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn btn-outline" id="confirmCancel">Cancel</button>
      <button class="btn btn-danger" id="confirmOk">Delete</button>
    </div>
  </div>
</div>

<!-- Loading Spinner -->
<div class="loading-overlay" id="loadingOverlay" style="display:none;">
  <div class="spinner"></div>
</div>

<!-- Main JS -->
<script src="<?= IMS_URL ?>/assets/js/main.js"></script>
</body>
</html>
