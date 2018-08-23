<div class="modal fade" id="oncore-subject-link" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Link subject to record</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="oncore_sync_subject_id">
                    <div class="form-group">
                        <label for="oncore-subject-link-select">Choose the record to link with</label>
                        <select id="oncore-subject-link-select" class="form-control" name="oncore_subject_link_record_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($records as $id => $label): ?>
                                <option value="<?php echo REDCap::escapeHtml($id); ?>"><?php echo REDCap::escapeHtml($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input id="oncore-subject-link-sync" class="form-check-input" name="oncore_subject_link_sync" type="checkbox" checked="true">
                            <label for="oncore-subject-link-sync" class="form-check-label">Sync data</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link</button>
                </div>
            </form>
        </div>
    </div>
</div>
