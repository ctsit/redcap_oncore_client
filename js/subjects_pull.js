$(function() {
    $('select[name="oncore_link_subject_id"]').select2({ width: '100%' });

    $('.oncore-subject-link-btn').click(function() {
        $('input[name="oncore_link_record_id"]').val($(this).data('record_id'));
    });
});
