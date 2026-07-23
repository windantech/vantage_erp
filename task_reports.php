<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Project Progress Reports</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <input type="hidden" id="print_date" value="<?php echo date("F d, Y") ?>">
                            <button class="btn border-0 p-0 ms-3" id="print">
                                <i class="bi bi-printer"></i> Print
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow" id="printable">
                        <table class="table table-borderless border" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#</th>
                                    <th class="nowrap border-bottom">Project</th>
                                    <th class="nowrap border-bottom">Task</th>
                                    <th class="nowrap border-bottom">Completed Task</th>
                                    <th class="nowrap border-bottom">Work Duration</th>
                                    <th class="nowrap border-bottom">Progress</th>
                                    <th class="nowrap border-bottom">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                $stat = array("Pending", "Started", "On-Progress", "On-Hold", "Over Due", "Done");
                                $where = "";
                                if ($_SESSION['login_type'] == 2) {
                                    $where = " where manager_id = '{$_SESSION['login_id']}' ";
                                } elseif ($_SESSION['login_type'] == 3) {
                                    $where = " where concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
                                }
                                $qry = $conn->query("SELECT * FROM project_list $where order by name asc");
                                while ($row = $qry->fetch_assoc()):
                                    $tprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']}")->num_rows;
                                    $cprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']} and status = 3")->num_rows;
                                    $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
                                    $prog = $prog > 0 ?  number_format($prog, 2) : $prog;
                                    $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$row['id']}")->num_rows;
                                    $dur = $conn->query("SELECT sum(time_rendered) as duration FROM user_productivity where project_id = {$row['id']}");
                                    $dur = $dur->num_rows > 0 ? $dur->fetch_assoc()['duration'] : 0;
                                    if ($row['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($row['start_date'])):
                                        if ($prod  > 0  || $cprog > 0)
                                            $row['status'] = 2;
                                        else
                                            $row['status'] = 1;
                                    elseif ($row['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($row['end_date'])):
                                        $row['status'] = 4;
                                    endif;
                                ?>
                                    <tr>
                                        <td>
                                            <?php echo $i++ ?>
                                        </td>
                                        <td>
                                            <a>
                                                <?php echo ucwords($row['name']) ?>
                                            </a>
                                            <br>
                                            <small>
                                                Due: <?php echo date("Y-m-d", strtotime($row['end_date'])) ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($tprog) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($cprog) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php echo number_format($dur) . ' Hr/s.' ?>
                                        </td>
                                        <td class="project_progress">
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-green" role="progressbar" aria-valuenow="57" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $prog ?>%">
                                                </div>
                                            </div>
                                            <small>
                                                <?php echo $prog ?>% Complete
                                            </small>
                                        </td>
                                        <td class="project-state">
                                            <?php
                                            if ($stat[$row['status']] == 'Pending') {
                                                echo "<span class='badge badge-secondary'>{$stat[$row['status']]}</span>";
                                            } elseif ($stat[$row['status']] == 'Started') {
                                                echo "<span class='badge badge-primary'>{$stat[$row['status']]}</span>";
                                            } elseif ($stat[$row['status']] == 'On-Progress') {
                                                echo "<span class='badge badge-info'>{$stat[$row['status']]}</span>";
                                            } elseif ($stat[$row['status']] == 'On-Hold') {
                                                echo "<span class='badge badge-warning'>{$stat[$row['status']]}</span>";
                                            } elseif ($stat[$row['status']] == 'Over Due') {
                                                echo "<span class='badge badge-danger'>{$stat[$row['status']]}</span>";
                                            } elseif ($stat[$row['status']] == 'Done') {
                                                echo "<span class='badge badge-success'>{$stat[$row['status']]}</span>";
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>