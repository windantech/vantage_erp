<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>
        <?php
        $twhere = "";
        if ($_SESSION['login_type'] != 1)
            $twhere = "  ";
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="w-100">
                <div class="card" style="box-shadow: 0 0 1px rgba(0, 0, 0, .125), 0 1px 3px rgba(0, 0, 0, .2); margin-bottom: 1rem;">
                    <div class="card-body">
                        Welcome <?php echo $_SESSION['login_name'] ?>!
                    </div>
                </div>
            </div>
            <hr class="border_main">
            <?php

            $where = "";
            if ($_SESSION['login_type'] == 2) {
                $where = " where manager_id = '{$_SESSION['login_id']}' ";
            } elseif ($_SESSION['login_type'] == 3) {
                $where = " where concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
            }
            $where2 = "";
            if ($_SESSION['login_type'] == 2) {
                $where2 = " where p.manager_id = '{$_SESSION['login_id']}' ";
            } elseif ($_SESSION['login_type'] == 3) {
                $where2 = " where concat('[',REPLACE(p.user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
            }
            ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm rounded-2 border">
                        <div class="card-header bg_main">
                            <b>Project Progress</b>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table m-0 table-borderless table-hover">
                                    <colgroup>
                                        <col width="5%">
                                        <col width="30%">
                                        <col width="35%">
                                        <col width="15%">
                                        <col width="15%">
                                    </colgroup>
                                    <thead>
                                        <tr class="border-top-0 border-start-0 border-end-0 border-bottom">
                                            <th>#</th>
                                            <th>Project</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th></th>
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
                                            $prog = 0;
                                            $tprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']}")->num_rows;
                                            $cprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']} and status = 3")->num_rows;
                                            $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
                                            $prog = $prog > 0 ?  number_format($prog, 2) : $prog;
                                            $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$row['id']}")->num_rows;
                                            if ($row['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($row['start_date'])):
                                                if ($prod  > 0  || $cprog > 0)
                                                    $row['status'] = 2;
                                                else
                                                    $row['status'] = 1;
                                            elseif ($row['status'] == 0 && strtotime(date('Y-m-d')) > strtotime($row['end_date'])):
                                                $row['status'] = 4;
                                            endif;
                                        ?>
                                            <tr onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
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
                                                <td class="project_progress">
                                                    <div class="progress progress-sm rounded-0">
                                                        <div class="progress-bar bg-success" role="progressbar" aria-valuenow="57" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $prog ?>%">
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
                                                <td>
                                                    <a class="btn btn-primary btn-sm" href="view_project?id=<?php echo $row['id'] ?>">
                                                        <i class="fas fa-folder">
                                                        </i>
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="row">
                        <div class="col-12 col-sm-6 col-md-12 mb-3">
                            <div class="d-flex align-items-center w-100 p-3 shadow-sm border rounded-2">
                                <div class="w-75">
                                    <h3><?php echo $conn->query("SELECT * FROM project_list $where")->num_rows; ?></h3>
                                    <p>Total Projects</p>
                                </div>
                                <div class="w-auto">
                                    <i class="bi bi-stack fs-1" style="color: rgba(0, 0, 0, .15);"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-12">
                            <div class="d-flex align-items-center w-100 p-3 shadow-sm border rounded-2">
                                <div class="w-75">
                                    <h3>
                                        <?php echo $conn->query("SELECT t.*,p.name as pname,p.start_date,p.status as pstatus, p.end_date,p.id as pid FROM task_list t inner join project_list p on p.id = t.project_id $where2")->num_rows; ?>
                                    </h3>
                                    <p>Total Tasks</p>
                                </div>
                                <div class="w-auto">
                                    <i class="fa fa-tasks fs-1" style="color: rgba(0, 0, 0, .15);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>