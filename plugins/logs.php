<?php

// TODO: Button to clean up all logs
// TODO: Exposed filters (by date range, operation and success)

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';

define('ONCORE_CLIENT_LOGS_MAX_LIST_SIZE', 25);
define('ONCORE_CLIENT_LOGS_MAX_PAGER_SIZE', 10);

$curr_page = empty($_GET['pager']) ? 1 : $_GET['pager'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module->query('DELETE FROM redcap_oncore_client_log');

    // Showing success message.
    echo RCView::div(array(
        'class' => 'darkgreen',
        'style' => 'margin-bottom: 20px;',
    ), RCView::img(array('src' => APP_PATH_IMAGES . 'tick.png')) . ' The logs have been cleared successfully.');
}
else {
    // Getting logs.
    $q = $module->query('
        SELECT id, pid, operation, success, timestamp, request, response, error_msg
        FROM redcap_oncore_client_log
        ORDER BY id DESC
        LIMIT ' . ONCORE_CLIENT_LOGS_MAX_LIST_SIZE . '
        OFFSET ' . ($curr_page - 1) * ONCORE_CLIENT_LOGS_MAX_LIST_SIZE
    );

    $db = new RedCapDB();
    $pager = array();
    $rows = array();
}

if (isset($q) && db_num_rows($q)) {
    $table_header = array('#', 'Project', 'Operation', 'Success', 'Date', '');
    $modal_header = array(
        'request' => 'Request',
        'response' => 'Response',
        'error_msg' => 'Error message',
    );

    $doc = new \DOMDocument('1.0');
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;

    $date = new \DateTime();

    $modals = array();
    while ($row = db_fetch_assoc($q)) {
        foreach (array('request', 'response') as $key) {
            if (empty($row[$key])) {
                continue;
            }

            $doc->loadXML($row[$key]);
            $row[$key] = $doc->saveXML();
            $row[$key] = '<pre>' . REDCap::escapeHtml($row[$key]) . '</pre>';
        }

        if ($project = $db->getProject($row['pid'])) {
            $row['pid'] = '(' . $row['pid'] . ') ' . REDCap::escapeHtml($project->app_title);
        }

        $date->setTimestamp($row['timestamp']);
        $row['timestamp'] = $date->format('m/d/Y - h:i:s a');
        $row['details'] = '<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#oncore-client-log-' . $row['id'] . '">See details</button>';
        $row['success'] = $row['success'] ? 'accept' : 'cross';
        $row['success'] = '<img src="' . APP_PATH_IMAGES . $row['success'] . '.png">';

        $modal = array();
        foreach (array_keys($modal_header) as $key) {
            $modal[$key] = $row[$key] ? $row[$key] : '-';
            unset($row[$key]);
        }

        $modals[$row['id']] = $modal;
        $rows[] = $row;
    }

    $q = $module->query('SELECT COUNT(id) as total_rows FROM redcap_oncore_client_log');
    $total_rows = db_fetch_assoc($q);
    $total_rows = $total_rows['total_rows'];

    // Setting up pager.
    if ($total_rows > ONCORE_CLIENT_LOGS_MAX_LIST_SIZE) {
        $base_path = $module->getUrl('plugins/logs.php') . '&pager=';

        // Including current page on pager.
        $pager[] = array(
            'url' => $module->getUrl('plugins/logs.php?pager=' . $curr_page),
            'title' => $curr_page,
            'class' => 'active',
        );

        // Calculating the total number of pages.
        $num_pages = (int) ($total_rows / ONCORE_CLIENT_LOGS_MAX_LIST_SIZE);
        if ($total_rows % ONCORE_CLIENT_LOGS_MAX_LIST_SIZE) {
            $num_pages++;
        }

        // Calculating the pager size.
        $pager_size = ONCORE_CLIENT_LOGS_MAX_PAGER_SIZE;
        if ($num_pages < $pager_size) {
            $pager_size = $num_pages;
        }

        // Creating queue of items to prepend.
        $start = $curr_page - $pager_size > 1 ? $curr_page - $pager_size : 1;
        $end = $curr_page - 1;
        $queue_prev = $end >= $start ? range($start, $end) : array();

        // Creating queue of items to append.
        $start = $curr_page + 1;
        $end = $curr_page + $pager_size < $num_pages ? $curr_page + $pager_size : $num_pages;
        $queue_next = $end >= $start ? range($start, $end) : array();

        // Prepending and appending items until we reach the pager size.
        $remaining_items = $pager_size - 1;
        while ($remaining_items) {
            if (!empty($queue_next)) {
                $page_num = array_shift($queue_next);
                $pager[] = array(
                    'title' => $page_num,
                    'url' => $base_path . $page_num,
                );

                $remaining_items--;
            }

            if (!$remaining_items) {
                break;
            }

            if (!empty($queue_prev)) {
                $page_num = array_pop($queue_prev);
                array_unshift($pager, array(
                    'title' => $page_num,
                    'url' => $base_path . $page_num,
                ));

                $remaining_items--;
            }
        }

        $item = array(
            'title' => '...',
            'class' => 'disabled',
            'url' => '#',
        );

        if (!empty($queue_prev)) {
            array_unshift($pager, $item);
        }

        if (!empty($queue_next)) {
            $pager[] = $item;
        }

        // Adding "First" and "Prev" buttons.
        $prefixes = array(
            array(
                'title' => 'First',
                'url' => $base_path . '1',
            ),
            array(
                'title' => 'Prev',
                'url' => $base_path . ($curr_page - 1),
            ),
        );

        if ($curr_page == 1) {
            foreach (array_keys($prefixes) as $i) {
                $prefixes[$i]['class'] = 'disabled';
                $prefixes[$i]['url'] = '#';
            }
        }

        // Adding "Next" and "Last" buttons.
        $suffixes = array(
            array(
                'title' => 'Next',
                'url' => $base_path . ($curr_page + 1),
            ),
            array(
                'title' => 'Last',
                'url' => $base_path . $num_pages,
            ),
        );

        if ($curr_page == $num_pages) {
            foreach (array_keys($suffixes) as $i) {
                $suffixes[$i]['class'] = 'disabled';
                $suffixes[$i]['url'] = '#';
            }
        }

        $pager = array_merge($prefixes, $pager, $suffixes);
    }
}
?>

<h4><img src="<?php echo APP_PATH_IMAGES; ?>report.png"> OnCore API Logs</h4>

<?php if (empty($rows)): ?>
    <p>There are no logs.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <?php foreach ($table_header as $value): ?>
                        <th><?php echo $value; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $value): ?>
                        <td><?php echo $value; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>

        <form method="post" action="<?php echo $module->getUrl('plugins/logs.php'); ?>">
            <button type="submit" class="btn btn-danger">Clean logs</button>
        </form>
    </div>
    <?php foreach ($modals as $id => $modal): ?>
        <div class="modal fade" id="oncore-client-log-<?php echo $id; ?>" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Request Details</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body" style="overflow-wrap:break-word;word-wrap:break-word;"><form>
                        <?php foreach ($modal_header as $key => $label): ?>
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label"><?php echo $label; ?></label>
                                <div class="col-sm-10"><?php echo $modal[$key]; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($pager)): ?>
        <nav aria-label="OnCore Client Logs Navigation">
            <ul class="pagination">
                <?php foreach ($pager as $page): ?>
                    <li class="page-item<?php echo $page['class'] ? ' ' . $page['class'] : ''; ?>">
                        <a class="page-link" href="<?php echo $page['url']; ?>"><?php echo $page['title']; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>
