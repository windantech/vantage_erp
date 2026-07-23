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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Task List</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addNewProject">
                                <i class="bi bi-plus-lg"></i> New Project
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#</th>
                                    <th class="nowrap border-bottom">Project</th>
                                    <th class="nowrap border-bottom">Task</th>
                                    <th class="nowrap border-bottom">Project Started</th>
                                    <th class="nowrap border-bottom">Project Due Date</th>
                                    <th class="nowrap border-bottom">Project Status</th>
                                    <th class="nowrap border-bottom">Task Status</th>
                                    <th class="nowrap border-bottom">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                $where = "";
                                if ($_SESSION['login_type'] == 2) {
                                    $where = " where p.manager_id = '{$_SESSION['login_id']}' ";
                                } elseif ($_SESSION['login_type'] == 3) {
                                    $where = " where concat('[',REPLACE(p.user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
                                }

                                $stat = array("Pending", "Started", "On-Progress", "On-Hold", "Over Due", "Done");
                                $qry = $conn->query("SELECT t.*,p.name as pname,p.start_date,p.status as pstatus, p.end_date,p.id as pid FROM task_list t inner join project_list p on p.id = t.project_id $where order by p.name asc");
                                while ($row = $qry->fetch_assoc()):
                                    $trans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
                                    unset($trans["\""], $trans["<"], $trans[">"], $trans["<h2"]);
                                    $desc = strtr(html_entity_decode($row['description']), $trans);
                                    $desc = str_replace(array("<li>", "</li>"), array("", ", "), $desc);
                                    $tprog = $conn->query("SELECT * FROM task_list where project_id = {$row['pid']}")->num_rows;
                                    $cprog = $conn->query("SELECT * FROM task_list where project_id = {$row['pid']} and status = 3")->num_rows;
                                    $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
                                    $prog = $prog > 0 ?  number_format($prog, 2) : $prog;
                                    $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$row['pid']}")->num_rows;
                                    if ($row['pstatus'] == 0 && strtotime(date('Y-m-d')) >= strtotime($row['start_date'])):
                                        if ($prod  > 0  || $cprog > 0)
                                            $row['pstatus'] = 2;
                                        else
                                            $row['pstatus'] = 1;
                                    elseif ($row['pstatus'] == 0 && strtotime(date('Y-m-d')) > strtotime($row['end_date'])):
                                        $row['pstatus'] = 4;
                                    endif;
                                ?>

                                    <tr>
                                        <td class="text-center"><?php echo $i++ ?></td>
                                        <td class="nowrap">
                                            <p><b><?php echo ucwords($row['pname']) ?></b></p>
                                        </td>
                                        <td>
                                            <p class="nowrap"><b><?php echo ucwords($row['task']) ?></b></p>
                                            <p class="truncate"><?php echo strip_tags($desc) ?></p>
                                        </td>
                                        <td class="nowrap"><b><?php echo date("M d, Y", strtotime($row['start_date'])) ?></b></td>
                                        <td class="nowrap"><b><?php echo date("M d, Y", strtotime($row['end_date'])) ?></b></td>
                                        <td class="text-center">
                                            <?php
                                            if ($stat[$row['pstatus']] == 'Pending') {
                                                echo "<span class='badge badge-secondary'>{$stat[$row['pstatus']]}</span>";
                                            } elseif ($stat[$row['pstatus']] == 'Started') {
                                                echo "<span class='badge badge-primary'>{$stat[$row['pstatus']]}</span>";
                                            } elseif ($stat[$row['pstatus']] == 'On-Progress') {
                                                echo "<span class='badge badge-info'>{$stat[$row['pstatus']]}</span>";
                                            } elseif ($stat[$row['pstatus']] == 'On-Hold') {
                                                echo "<span class='badge badge-warning'>{$stat[$row['pstatus']]}</span>";
                                            } elseif ($stat[$row['pstatus']] == 'Over Due') {
                                                echo "<span class='badge badge-danger'>{$stat[$row['pstatus']]}</span>";
                                            } elseif ($stat[$row['pstatus']] == 'Done') {
                                                echo "<span class='badge badge-success'>{$stat[$row['pstatus']]}</span>";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($row['status'] == 1) {
                                                echo "<span class='badge badge-secondary'>Pending</span>";
                                            } elseif ($row['status'] == 2) {
                                                echo "<span class='badge badge-primary'>On-Progress</span>";
                                            } elseif ($row['status'] == 3) {
                                                echo "<span class='badge badge-success'>Done</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                                                Action
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="javascript:void(0)"  id="new_productivity" data-bs-toggle="modal" data-bs-target="#newProductivityModal">Add Productivity</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modals -->

                <?php
                require 'manage_projects/new_project_modal.php';
                ?>

                <?php
                include 'manage_projects/new_productivity_modal.php';
                ?>


            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>