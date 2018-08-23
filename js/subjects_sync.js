$(function() {
    $('select[name="oncore_subject_link_record_id"]').select2();

    $('.oncore-subject-link-btn').click(function() {
        $('input[name="oncore_sync_subject_id"]').val($(this).data('oncore_sync_subject_id'));
    });
});
