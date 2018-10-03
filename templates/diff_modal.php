<div class="modal fade" id="oncore-subject-diff-<?php echo $id; ?>" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Diff</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col"></th> 
                            <th scope="col"><strong>REDCap</strong></th> 
                            <th scope="col"><strong>OnCore</strong></th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diff as $key => $values): ?>
                            <tr>
                                <td scope="row"><strong><?php echo REDCap::escapeHtml($key); ?></strong></td>
                                <td><?php echo REDCap::escapeHtml($values[0]); ?></td>
                                <td><?php echo REDCap::escapeHtml($values[1]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
