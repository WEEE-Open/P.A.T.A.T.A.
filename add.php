<!DOCTYPE html>
<?php
include 'db.php';
include 'functions.php';

$done = isset($_GET['done']) && $_GET['done'] === 'true';
?>
<html>

<head>
    <link rel="icon" type="image/svg+xml" href="patata.svg">
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" />
    <script type="text/javascript" src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</head>

<body onload="display_ct()">
    <div class="container">
        <h1 style="padding-left: 30px;">Patata</h1>

        <div class="row">
            <div class="col-md-6">
                <div id='ct' style="padding-left: 30px;"></div>
                <div id='ct2' style="padding-left: 30px;"></div>
            </div>
            <div class="col-md-6">
                <?php if ($done) : ?>
                <a class="float-right" href="?done=false">View tasks to do</a>
                <?php else : ?>
                <a class="float-right" href="?done=true">View completed tasks</a>
                <?php endif ?>
            </div>
        </div>

        <hr style="padding-left: 0 px;margin-left: 30px;">
        <?php handle_post(); ?>
        <div id='tasktable'>
            <div class="task">
                <h5 class="text-center">Tasklist</h5>
                <table class="table table-striped " style="width: 70%; margin: 0 auto;">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Durate (Minutes)</th>
                            <th>Maintainer</th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $db = new MyDB();

                        list($result, $maintainer) = get_tasks_and_maintainers($db, $done, true);

                        while ($tasklist = $result->fetchArray(SQLITE3_ASSOC)) :

                            ?><form method="post" action="add.php">
                            <tr>
                                <input type="hidden" name="idn" value="<?= $tasklist['ID'] ?>">

                                <td>
                                    <select required name="tasktype">
                                        <?php foreach (TYPE_EMOJI as $text => $emoji) { ?>
                                        <option value="<?= $text ?>" <?= $text === $tasklist['TaskType'] ? " selected" : "";
                                                                        $taskName = TYPE_DESCRIPTION[$text] ?> title="<?= $taskName ?>"><?= "$emoji $taskName" ?></option>
                                        <?php 
                                    } ?>
                                    </select>
                                </td>
                                <td><input type="text" name="title" value="<?= $tasklist['Title'] ?>"></td>
                                <td><input type="text" name="description" value="<?= isset($tasklist['Description']) ? $tasklist['Description'] : "" ?>"></td>
                                <td><input type="text" name="durate" size="3" value="<?= $tasklist['Durate'] ?>"></td>
                                <td><input type="text" name="maintainer" value="<?= isset($maintainer[$tasklist['ID']]) ? implode(', ', $maintainer[$tasklist['ID']]) : "" ?>"></td>
                                <td><input type="submit" name="submit" value="Save"></td>
                                <?php if ($done) : ?>
                                <td><input type="submit" name="submit" value="Undo"></td>
                                <?php else : ?>
                                <td><input type="submit" name="submit" value="Done"></td>
                                <?php endif ?>
                            </tr>
                        </form>
                        <?php endwhile; ?>
                        <form method="post" action="add.php">
                            <tr>
                                <td>
                                    <select required name="tasktype">
                                        <option><?php echo $_POST['typeErr'] ?></option>
                                        <?php foreach (TYPE_EMOJI as $text => $emoji) {
                                            $taskName = TYPE_DESCRIPTION[$text] ?>
                                        ?><option value="<?= $text ?>"><?= "$emoji $taskName" ?></option>
                                        <?php 
                                    } ?>
                                    </select>
                                    </span></td>
                                <td><input type="text" name="title"></td>
                                <td><input type="text" name="description"></td>
                                <td><input type="text" name="durate" size="3"></td>
                                <td><input type="text" name="maintainer"></td>
                                <td><input type="submit" name="submit" value="Add"></td>
                                <td></td>
                            </tr>
                        </form>
                    </tbody>
                </table>
            </div>
        </div>

        <script type="text/javascript">
            function display_c() { //Refresh time for the date function
                var refresh = 1000;
                mytime = setTimeout('display_ct()', refresh)
            }

            function addZero(i) {
                if (i < 10) {
                    i = "0" + i;
                }
                return i;
            }

            function display_ct() { //Date generator function
                var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                var x = new Date()
                var d = addZero(x.getDate())
                var mo = months[x.getMonth()]
                var y = addZero(x.getFullYear())
                var h = addZero(x.getHours())
                var mi = addZero(x.getMinutes())
                var s = addZero(x.getSeconds())
                var wd = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]
                var td = wd[x.getDay()]
                var x1 = td + " - " + d + " " + mo + " " + y
                var x2 = h + ":" + mi + ":" + s
                document.getElementById('ct').innerHTML = x1
                document.getElementById('ct2').innerHTML = x2

                display_c();
            }
        </script>
</body>

</html> 