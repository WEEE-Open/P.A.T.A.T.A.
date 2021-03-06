<?php
const TYPE_EMOJI = [
    'E' => '💡',
    'I' => '💻',
    'M' => '🔨',
    'D' => '📘',
    'C' => '🧽',
    'V' => '🍀',
    'R' => '💾',
    'S' => '🎮',
];
const TYPE_DESCRIPTION = [
    'E' => 'Elettronica',
    'I' => 'Informatica',
    'M' => 'Meccanica',
    'D' => 'Documentazione',
    'C' => 'Riordino',
    'V' => 'Eventi',
    'R' => 'Retrocomputing',
    'S' => 'Svago',
];

function get_random_quote()
{
    $quotes_file = file_get_contents('quotes.json');
    $quotes = json_decode($quotes_file, true);
    $quote_id = rand(0, count($quotes) - 1);
    return $quotes[$quote_id];
}

function get_max_id()
{
    $db = new MyDB();
    $temp = $db->query("SELECT MAX(ID) ID FROM TASK");
    $temp = $temp->fetchArray(SQLITE3_ASSOC);
    return $temp['ID'];
}

function get_task_number(int $done = 0)
{
    $db = new MyDB();
    $temp = $db->query("SELECT COUNT (ID) ID FROM TASK WHERE DONE = $done");
    $temp = $temp->fetchArray(SQLITE3_ASSOC);
    return $temp['ID'];
}

function print_stats(string $stats)
{
    require_once 'conf.php';

    $curl = get_curl();

    echo '<div class="row" id="stats">';
    if ($stats === '0') {
        print_stat($curl, 'add');
        print_stat($curl, 'update');
    } else {
        print_stat($curl, 'update');
        print_stat($curl, 'move');
    }
    echo '</div>';

    curl_close($curl);
}

function relative_date($time)
{
    $day = date('Y-m-d', $time);
    if($day === date('Y-m-d', strtotime('today'))) {
        return 'Today';
    } elseif($day === date('Y-m-d', strtotime('yesterday'))) {
        return 'Yesterday';
    } else {
        return $day;
    }
}

function e($text)
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5);
}

function print_stat($curl, string $stat)
{
    switch ($stat) {
        case 'add':
        default:
            $url = '/v2/stats/getRecentAuditByType/C/10';
            $title = 'Recently added items';
            break;
        case 'update':
            $url = '/v2/stats/getRecentAuditByType/U/10';
            $title = 'Recently modified items';
            break;
        case 'move':
            $url = '/v2/stats/getRecentAuditByType/M/10';
            $title = 'Recently moved items';
            break;
    }

    $data = get_data_from_tarallo($curl, $url);
    ?>
    <div class='col-md-6'>
        <h6 class='text-center'><?= e($title) ?></h6>
        <table class='table table-striped table-sm text-center'>
            <thead>
            <tr>
                <th>Item</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($data as $item => $timestamp): $timestamp = (int)floatval($timestamp); ?>
                <tr>
                    <td><?= e($item) ?></td>
                    <td><?= relative_date($timestamp) . date(' H:i') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * @param resource $curl CURL
 * @param string $path /v2/whatever
 *
 * @return array
 */
function get_data_from_tarallo($curl, string $path): array
{
    $url = TARALLO_URL . $path;
    curl_setopt($curl, CURLOPT_URL, $url);
    $result = curl_exec($curl);
    $result = json_decode($result, true);

    return $result;
}

/**
 * @return false|resource
 */
function get_curl()
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Token ' . TARALLO_TOKEN, 'Accept: application/json']);
    //curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Token ' . TARALLO_TOKEN, 'Accept: application/json', 'Cookie: XDEBUG_SESSION=PHPSTORM']);

    return $curl;
}

function print_tasktable()
{
    $_SESSION['max_row'] = get_task_number();
    $db = new MyDB();
    $per_page = 10;
    if (!isset($_SESSION['offset'])) {
        $_SESSION['offset'] = 0;
    }
    list($result, $maintainer) = get_tasks_and_maintainers($db, false, 0, 0, $per_page, $_SESSION['offset']);

    $page = 1 + floor(($_SESSION['offset']) / $per_page);
    $pages = ceil($_SESSION['max_row'] / $per_page);
    ?>
    <div id='tasktable'>
        <h5 class='text-center'>Tasklist <?= "page $page of $pages" ?></h5>
        <table class='table table-striped' style='margin: 0 auto;'>
            <thead>
            <tr>
                <th>Type</th>
                <th>Title</th>
                <th>Description</th>
                <th>Durate (Minutes)</th>
                <th>Maintainer</th>
            </tr>
            </thead>
            <tbody>
            <?php
            while ($tasklist = $result->fetchArray(SQLITE3_ASSOC)) {

                $emoji = TYPE_EMOJI[$tasklist['TaskType']];
                $taskName = TYPE_DESCRIPTION[$tasklist['TaskType']];

                echo '<tr>';
                echo '<td title=\'$taskName\'>' . $emoji . '</td>';
                echo '<td>' . $tasklist['Title'] . '</td>';
                echo '<td>';
                echo isset($tasklist['Description']) ? $tasklist['Description'] : '';
                echo '</td>';
                echo '<td>' . $tasklist['Durate'] . '</td>';
                echo '<td>';
                echo isset($maintainer[$tasklist['ID']]) ? implode(', ', $maintainer[$tasklist['ID']]) : '';
                echo '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * @param MyDB $db The patabase
 * @param $done bool True if you only want completed tasks, false if you only want tasks that are still to do (default)
 * @param string $from_d range period
 * @param string $to_d range period
 * @param $tasks_per_page int Tasks per page, shows all if less than 0
 * @param int $offset
 * @return array $result, $maintainer
 */
function get_tasks_and_maintainers(MyDB $db, bool $done, $from_d, $to_d, int $tasks_per_page = 5, int &$offset = 0): array
{
    $done = (int)$done;
    $where_clause = 'Done = ';

    if ($tasks_per_page < 0) {
        $offset = 0;
        $to_d++;
        if ($done) {
            $row_count = get_task_number(1);
            $where_clause .= $done;
            $where_clause .= ' AND Date BETWEEN \'' . $from_d . '\' AND \'' . $to_d . '\'';
            $order_clause = 'Date DESC';
        } else {
            $row_count = get_task_number();
            $where_clause .= $done;
            $order_clause = 'ID DESC';
        }

    } else {
        $row_count = $tasks_per_page;
        $total_tasks = get_task_number();
        $offset += $tasks_per_page;
        $where_clause .= $done;
        $order_clause = 'ID';
        if ($offset >= $total_tasks) {
            $offset = 0;
        }
    }

    $result = $db->query("SELECT ID, Tasktype, Title, Description, Durate, Done, Date
                                            FROM TASK 
                                            WHERE $where_clause
                                            ORDER BY $order_clause
                                            LIMIT $row_count OFFSET $offset");
    $result2 = $db->query("SELECT T_ID, Maintainer
                                            FROM T_MAINTAINER
                                            WHERE T_ID IN (SELECT ID
                                                            FROM TASK 
                                                            WHERE $where_clause
                                                            LIMIT $row_count OFFSET $offset)
                                            ORDER BY T_ID");
    $maintainer = array();
    while ($temp = $result2->fetchArray(SQLITE3_ASSOC)) {
        $maintainer[$temp['T_ID']] = array();
    }
    while ($temp = $result2->fetchArray(SQLITE3_ASSOC)) {
        //$maintainer[$temp['T_ID']]=array();
        // Why do we need two cycle?
        array_push($maintainer[$temp['T_ID']], $temp['Maintainer']);
    }
    return array($result, $maintainer);
}

function handle_post()
{
    $db = new MyDB();

    if (isset($_POST['title'])) {
        if (empty($_POST['title'])) {
            $idn = (int)$_POST['idn'];
            delete_task($db, $idn);
            return;
        }
        $title = test_input($_POST['title']);
        if (empty($_POST['idn'])) {
            $idn = null;
        } else {
            $idn = (int)$_POST['idn'];
        }
        foreach (TYPE_EMOJI as $tempType => $tempEmoji) {
            if ($tempType == $_POST['tasktype']) {
                $type = $_POST['tasktype'];
            }
        }
        if (!isset($type)) {
            $_POST['typeErr'] = 'Select a valid task type';
        }
        if (empty($_POST['date'])) {
            $date = null;
        } else {
            $date = $_POST['date'];
        }
        $description = test_input($_POST['description']);
        $durate = (int)$_POST['durate'];
        $maintainer = explode(',', $_POST['maintainer']);
        $done = (bool)$_POST['done'];
        foreach ($maintainer as &$temp_maintainer) {
            $temp_maintainer = test_input($temp_maintainer);
        }
        unset($temp_maintainer);
        $edit = $_POST['submit'];
        if ($edit === 'Save') {
            update_task($db, $title, $description, $durate, $type, $idn, $maintainer, $done, $date);
        } elseif ($edit === 'Add') {
            add_new_task($db, $title, $description, $durate, $type, $maintainer);
        } elseif ($edit === 'Done') {
            $date = date('Y-m-d H:i:s');
            update_task($db, $title, $description, $durate, $type, $idn, $maintainer, true, $date);
        } elseif ($edit === 'Undo') {
            update_task($db, $title, $description, $durate, $type, $idn, $maintainer, false, null);
        }
    }
}

function update_task(MyDB $db, $title, $description, int $durate, $type, int $idn, array $maintainer, bool $done, $date)
{
    $stmt = $db->prepare('UPDATE "Task" 
                                    SET "Title" = :title, "Description" = :descr, "Durate" = :durate,
                                         "TaskType" = :tasktype, "Done" = :done, "Date" = :compDate
                                    WHERE "ID" = :id');
    if ($stmt === false) {
        throw new RuntimeException('Cannot prepare statement');
    }

    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':descr', $description);
    $stmt->bindValue(':durate', $durate);
    $stmt->bindValue(':tasktype', $type);
    $stmt->bindValue(':done', $done);
    $stmt->bindValue(':compDate', $date);
    $stmt->bindValue(':id', $idn);
    $stmt->execute();

    $db->query("DELETE FROM T_Maintainer WHERE T_ID = $idn");

    add_maintainers($db, $maintainer, $idn);
}

function add_new_task(MyDB $db, $title, $description, int $durate, $type, array $maintainer)
{
    $stmt = $db->prepare("INSERT INTO Task (Title,Description,Durate,TaskType)
                        VALUES (:title, :descr, :durate, :type)");
    if ($stmt === false) {
        throw new RuntimeException('Cannot prepare statement');
    }

    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':descr', $description);
    $stmt->bindValue(':durate', $durate);
    $stmt->bindValue(':type', $type);

    $stmt->execute();

    $idn = get_max_id();

    add_maintainers($db, $maintainer, $idn);
}

function add_maintainers(MyDB $db, array $maintainer, int $idn)
{
    foreach ($maintainer as $temp_maintainer) {
        if (!empty($temp_maintainer)) {
            $db->query("INSERT INTO T_Maintainer (T_ID,Maintainer)
                        VALUES ('$idn', '$temp_maintainer')");
        }
    }
}

function delete_task(MyDB $db, int $idn)
{
    $db->query("DELETE FROM Task 
                WHERE ID = $idn");
}

function test_input($input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}
