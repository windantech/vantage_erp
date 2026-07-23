<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <?php
        $project_id = isset($_GET['id']) ? $_GET['id'] : null;

        if (!$project_id) {
            echo "<script>window.location.href = 'projects';</script>";
            exit;
        }

        $stat = array("Pending", "Started", "On-Progress", "On-Hold", "Over Due", "Done");
        $qry = $conn->query("SELECT * FROM project_list where id = " . $_GET['id'])->fetch_array();
        foreach ($qry as $k => $v) {
            $$k = $v;
        }
        $tprog = $conn->query("SELECT * FROM task_list where project_id = {$id}")->num_rows;
        $cprog = $conn->query("SELECT * FROM task_list where project_id = {$id} and status = 3")->num_rows;
        $prog = $tprog > 0 ? ($cprog / $tprog) * 100 : 0;
        $prog = $prog > 0 ?  number_format($prog, 2) : $prog;
        $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$id}")->num_rows;
        if ($status == 0 && strtotime(date('Y-m-d')) >= strtotime($start_date)):
            if ($prod  > 0  || $cprog > 0)
                $status = 2;
            else
                $status = 1;
        elseif ($status == 0 && strtotime(date('Y-m-d')) > strtotime($end_date)):
            $status = 4;
        endif;
        $manager = $conn->query("SELECT *,fullname as name FROM registered_users where id = $manager_id");
        $manager = $manager->num_rows > 0 ? $manager->fetch_array() : array();
        ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50 d-flex align-items-center">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">View <?php echo ucwords($name) ?></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.href='projects'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-left-circle"></i> Go Back
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="col-lg-12">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="callout callout-info">
                                    <div class="col-md-12">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <dl>
                                                    <dt><b class="border-bottom border-primary">Project Name</b></dt>
                                                    <dd><?php echo ucwords($name) ?></dd>
                                                    <dt>
                                                        <b class="border-bottom border-primary">Description</b>
                                                    </dt>
                                                    <dd class="text_justify">
                                                        <?php echo html_entity_decode($description) ?>
                                                    </dd>
                                                </dl>
                                            </div>
                                            <div class="col-md-6">
                                                <dl>
                                                    <dt><b class="border-bottom border-primary">Start Date</b></dt>
                                                    <dd>
                                                        <?php echo date("F d, Y", strtotime($start_date)) ?>
                                                    </dd>
                                                </dl>
                                                <dl>
                                                    <dt><b class="border-bottom border-primary">End Date</b></dt>
                                                    <dd>
                                                        <?php echo date("F d, Y", strtotime($end_date)) ?>
                                                    </dd>
                                                </dl>
                                                <dl>
                                                    <dt><b class="border-bottom border-primary">Status</b></dt>
                                                    <dd>
                                                        <?php
                                                        if ($stat[$status] == 'Pending') {
                                                            echo "<span class='badge badge-secondary'>{$stat[$status]}</span>";
                                                        } elseif ($stat[$status] == 'Started') {
                                                            echo "<span class='badge badge-primary'>{$stat[$status]}</span>";
                                                        } elseif ($stat[$status] == 'On-Progress') {
                                                            echo "<span class='badge badge-info'>{$stat[$status]}</span>";
                                                        } elseif ($stat[$status] == 'On-Hold') {
                                                            echo "<span class='badge badge-warning'>{$stat[$status]}</span>";
                                                        } elseif ($stat[$status] == 'Over Due') {
                                                            echo "<span class='badge badge-danger'>{$stat[$status]}</span>";
                                                        } elseif ($stat[$status] == 'Done') {
                                                            echo "<span class='badge badge-success'>{$stat[$status]}</span>";
                                                        }
                                                        ?>
                                                    </dd>
                                                </dl>
                                                <dl>
                                                    <dt><b class="border-bottom border-primary">Project Manager</b></dt>
                                                    <dd>
                                                        <?php if (isset($manager['id'])) : ?>
                                                            <div class="d-flex align-items-center mt-1">
                                                                <img class="img-circle img-thumbnail p-0 shadow-sm border-info img-sm mr-3" src="assets/img/user.png" alt="Avatar">
                                                                
                                                                <b><?php echo ucwords($manager['name']) ?></b>
                                                            </div>
                                                        <?php else: ?>
                                                            <small><i>Manager Deleted from Database</i></small>
                                                        <?php endif; ?>
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card rounded-0 card-outline card-primary">
                                    <div class="card-header rounded-0 bg_main">
                                        <span><b>Team Member/s:</b></span>
                                        <div class="card-tools">
                                            <!-- <button class="btn btn-primary bg-gradient-primary btn-sm" type="button" id="manage_team">Manage</button> -->
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <ul class="users-list clearfix">
                                            <?php
                                            if (!empty($user_ids)):
                                                $members = $conn->query("SELECT *,fullname as name FROM registered_users where id in ($user_ids) order by fullname asc");
                                                while ($row = $members->fetch_assoc()):
                                            ?>
                                                    <li>
                                                        <img src="assets/img/user.png" alt="User Image">
                                                        <a class="users-list-name" href="javascript:void(0)"><?php echo ucwords($row['name']) ?></a>
                                                    </li>
                                            <?php
                                                endwhile;
                                            endif;
                                            ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card rounded-0 card-outline card-primary">
                                    <div class="card-header rounded-0 bg_main d-flex w-100 align-items-center">
                                        <div class="w-50">
                                            <span><b>Task List:</b></span>
                                        </div>
                                        <div class="w-50 d-flex justify-content-end">
                                            <?php if ($_SESSION['login_type'] != 3): ?>
                                                <div class="card-tools d-flex justify-content-end">
                                                    <button class="btn p-0 newTaskModal" type="button" id="new_task" data-p_name="<?php echo ucwords($name) ?>" data-bs-toggle="modal" data-bs-target="#newTaskModal"><i class="fa fa-plus"></i> New Task</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive overflow">
                                            <table class="table table-condensed m-0 table-hover">
                                                <colgroup>
                                                    <col width="5%">
                                                    <col width="20%">
                                                    <col width="25%">
                                                    <col width="20%">
                                                    <col width="15%">
                                                    <col width="10%">
                                                    <col width="5%">
                                                </colgroup>
                                                <thead>
                                                    <th class="nowrap">#</th>
                                                    <th class="nowrap">Task</th>
                                                    <th class="nowrap">Team Members</th>
                                                    <th class="nowrap">Description</th>
                                                    <th class="nowrap">End Date</th>
                                                    <th class="nowrap">Status</th>
                                                    <th class="nowrap">Action</th>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $tasks = $conn->query("SELECT t.*, GROUP_CONCAT(r.fullname SEPARATOR ', ') as fullnames 
                                                    FROM task_list t 
                                                    JOIN registered_users r ON FIND_IN_SET(r.id, t.user_ids) > 0 
                                                    WHERE t.project_id = '$id' 
                                                    GROUP BY t.id 
                                                    ORDER BY t.task ASC");
                                                    while ($row = $tasks->fetch_assoc()):
                                                        $task_id = $row['id'];
                                                        $trans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
                                                        unset($trans["\""], $trans["<"], $trans[">"], $trans["<h2"]);
                                                        $desc = strtr(html_entity_decode($row['description']), $trans);
                                                        $desc = str_replace(array("<li>", "</li>"), array("", ", "), $desc);
                                                    ?>
                                                        <tr style="font-size: 14px">
                                                            <td class="text-center"><?php echo ucwords($row['id']) ?></td>
                                                            <td class="nowrap">
                                                                <b><?php echo ucwords($row['task']) ?></b>
                                                            </td>
                                                            <td>
                                                                <ul class="list-unstyled">
                                                                    <?php
                                                                    $names = explode(',', $row['fullnames']);
                                                                    foreach ($names as $name) { ?>
                                                                        <li class="nowrap">
                                                                            <i class="bi bi-check-circle-fill me-1"></i>
                                                                            <b><?php echo ucwords(trim($name)); ?></b>
                                                                        </li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </td>
                                                            <td>
                                                                <p class="truncate">
                                                                    <?php echo strip_tags($desc) ?>
                                                                </p>
                                                            </td>
                                                            <td class="nowrap">
                                                                <b><?php echo ucwords($row['end_date']) ?></b>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $task_status = $row['status'];
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
                                                                <div class="dropdown">
                                                                    <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                                        Action
                                                                    </button>
                                                                    <ul class="dropdown-menu rounded-0 border-info p-0">
                                                                        <li class="border-bottom border-info">
                                                                            <a class="dropdown-item py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#viewTaskModal<?php echo $task_id; ?>">View</a>
                                                                        </li>
                                                                        <li class="border-bottom border-info">
                                                                            <a class="dropdown-item py-2" href="edit_task?task_id=<?php echo $task_id; ?>&p_id=<?php echo $project_id; ?>">Edit</a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item py-2" href="javascript:void(0);" id="delete_task" data-task_id="<?php echo $task_id; ?>">Delete</a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <!-- Modal for each task -->
                                                        <div class="modal fade" id="viewTaskModal<?php echo $task_id; ?>" tabindex="-1" aria-labelledby="viewTaskModalLabel<?php echo $task_id; ?>" aria-hidden="true">
                                                            <div class="modal-dialog modal-dialog-centered">
                                                                <div class="modal-content rounded-0">
                                                                    <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                                                        <h1 class="modal-title fs-5 text-uppercase" id="viewTaskModalLabel<?php echo $task_id; ?>">Task Details</h1>
                                                                        <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <dl>
                                                                            <dt><b class="border-bottom border-primary">Task Name</b></dt>
                                                                            <dd><?php echo ucwords($row['task']) ?></dd>
                                                                            <dt>
                                                                                <b class="border-bottom border-primary">Description</b>
                                                                            </dt>
                                                                            <dd class="text_justify">
                                                                                <?php echo strip_tags($desc) ?>
                                                                            </dd>
                                                                        </dl>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    endwhile;
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card rounded-0">
                                    <div class="card-header rounded-0 bg_main">
                                        <div class="w-100 d-flex align-items-center">
                                            <div class="w-50">
                                                <b>Members Progress/Activity</b>
                                            </div>
                                            <div class="w-50 d-flex justify-content-end">
                                                <div class="card-tools">
                                                    <button class="btn p-0" type="button" id="new_productivity" data-bs-toggle="modal" data-bs-target="#newProductivityModal"><i class="fa fa-plus"></i> New Productivity</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $progress = $conn->query("SELECT p.*,u.fullname as uname,t.task FROM user_productivity p inner join registered_users u on u.id = p.user_id inner join task_list t on t.id = p.task_id where p.project_id = $id order by unix_timestamp(p.date_created) desc ");
                                        $count_progress = mysqli_num_rows($progress);
                                        if ($count_progress > 0) {
                                            while ($row = $progress->fetch_assoc()):

                                                $subject_list = $conn->query("SELECT * FROM user_productivity where project_id = '$id'");
                                                while ($subject_row = $subject_list->fetch_assoc()):
                                                    $subject = $subject_row['subject'];
                                                endwhile;
                                        ?>
                                                <div class="post">
                                                    <div class="user-block">
                                                        <?php if ($_SESSION['login_id'] == $row['user_id']): ?>
                                                            <span class="btn-group dropleft float-right">
                                                                <span class="btndropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="cursor: pointer;">
                                                                    <i class="fa fa-ellipsis-v"></i>
                                                                </span>
                                                                <div class="dropdown-menu">
                                                                    <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editProductivityModal<?php echo $row['id'] ?>">Edit</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item deleteProgressModal" href="javascript:void(0)" data-p_id="<?php echo $row['id'] ?>">Delete</a>
                                                                </div>
                                                            </span>
                                                        <?php endif; ?>
                                                        <img class="img-circle img-bordered-sm" src="assets/img/user.png" alt="user image">
                                                        <span class="username">
                                                            <a href="javascript:void(0);"><?php echo ucwords($row['uname']) ?>[ <?php echo ucwords($row['task']) ?> ]</a>
                                                        </span>
                                                        <span class="description">
                                                            <span class="fa fa-calendar-day"></span>
                                                            <span><b><?php echo date('M d, Y', strtotime($row['date'])) ?></b></span>
                                                            <span class="fa fa-user-clock"></span>
                                                            <span>Start: <b><?php echo date('h:i A', strtotime($row['date'] . ' ' . $row['start_time'])) ?></b></span>
                                                            <span> | </span>
                                                            <span>End: <b><?php echo date('h:i A', strtotime($row['date'] . ' ' . $row['end_time'])) ?></b></span>
                                                        </span>
                                                    </div>
                                                    <!-- /.user-block -->
                                                    <div>
                                                        <?php echo html_entity_decode($row['comment']) ?>
                                                    </div>
                                                </div>
                                                <div class="post clearfix"></div>

                                                <!-- Edit Productivity Modal -->
                                                <div class="modal fade overflow" id="editProductivityModal<?php echo $row['id'] ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editProductivityModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                                        <div class="modal-content rounded-0">
                                                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                                                <h1 class="modal-title fs-5 text-uppercase" id="editProductivityModalLabel">
                                                                    Edit Progress
                                                                </h1>
                                                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form id="edit_productivity" method="POST">
                                                                    <input type="hidden" name="productivity_id" id="productivity_id" value="<?php echo $row['id'] ?>">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
                                                                                <select class="form-control rounded-0" name="task_id" id="upd_task_id">
                                                                                    <option value="selected" selected="true" hidden>--- Select Task ---</option>
                                                                                    <?php
                                                                                    $tasks = $conn->query("SELECT * FROM task_list where project_id = '$id' order by task asc ");
                                                                                    while ($rows = $tasks->fetch_assoc()):
                                                                                    ?>
                                                                                        <option value="<?php echo $rows['id'] ?>" <?php echo $row['task_id'] == $rows['id'] ? "selected" : '' ?>><?php echo ucwords($rows['task']) ?></option>
                                                                                    <?php endwhile; ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-6">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Subject</span>
                                                                                <input type="text" name="subject" id="upd_subject" value="<?php echo htmlspecialchars($row['subject']); ?>" class="form-control rounded-0" aria-label="Subject" aria-describedby="basic-addon1">
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-12">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Date</span>
                                                                                <input type="date" name="date" id="upd_date" value="<?php echo (($row['date'] == "") ? "" : $row['date']); ?>" class="form-control rounded-0" aria-label="Date" aria-describedby="basic-addon1">
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-6">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Time</span>
                                                                                <input type="time" name="start_time" id="upd_start_time" value="<?php echo (($row['start_time'] == "") ? "" : $row['start_time']); ?>" class="form-control rounded-0" aria-label="Start Time" aria-describedby="basic-addon1">
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-6">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Time</span>
                                                                                <input type="time" name="end_time" id="upd_end_time" value="<?php echo (($row['end_time'] == "") ? "" : $row['end_time']); ?>" class="form-control rounded-0" aria-label="End Time" aria-describedby="basic-addon1">
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-md-12">
                                                                            <div class="input-group mb-3">
                                                                                <span class="input-group-text rounded-0 bg_main" id="basic-addon1">Comment/Progress Description</span>
                                                                                <textarea name="upd_comment" id="summernote3" cols="30" rows="10" class="form-control upd_comment"><?php echo html_entity_decode($row['comment']) ?></textarea>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="w-100 d-flex border-top pt-2 mt-2">
                                                                        <div class="w-50">
                                                                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                                                <i class="bi bi-x-lg"></i> Cancel
                                                                            </button>
                                                                        </div>
                                                                        <div class="w-50 d-flex justify-content-end">
                                                                            <button type="submit" class="btn btn-success rounded-0">
                                                                                <i class="bi bi-check2"></i> Update
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Edit Productivity Modal -->
                                            <?php
                                            endwhile;
                                        } else { ?>
                                            <div class="text-center">
                                                No Members Progress/Activity Available
                                            </div>
                                        <?php }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Task Modals -->

<!-- New Task Modal -->
<div class="modal fade overflow" id="newTaskModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="newTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="newTaskModalLabel">
                    <b>New Task For <span id="p_name_head"></span></b>
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form action="" id="add_task" method="POST">
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $project_id; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
                                <input type="text" name="task_name" id="task_name" class="form-control rounded-0" aria-label="Task" aria-describedby="basic-addon1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
                                <select class="form-control rounded-0" name="task_status" id="task_status">
                                    <option value="selected" selected="true" hidden>--- Select Status ---</option>
                                    <option value="1">Pending</option>
                                    <option value="2">On-Progress</option>
                                    <option value="3">Done</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Team Members</span>
                                <select class="form-control rounded-0 select2" multiple name="user_ids[]" id="user_ids">
                                    <option value="selected" hidden></option>
                                    <?php
                                    $project = $conn->query("SELECT user_ids FROM project_list WHERE id = '$project_id'");
                                    if ($project->num_rows > 0) {
                                        $user_ids = explode(',', $project->fetch_assoc()['user_ids']);
                                        $user_ids_str = implode(',', array_map('intval', $user_ids));
                                        $users = $conn->query("SELECT id, fullname FROM registered_users WHERE id IN ($user_ids_str)");
                                        while ($row = $users->fetch_assoc()):
                                    ?>
                                            <option value="<?php echo $row['id'] ?>"><?php echo ucwords($row['fullname']) ?></option>
                                    <?php
                                        endwhile;
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Date</span>
                                <input type="date" name="end_date" id="end_date" class="form-control rounded-0" autocomplete="off" aria-label="End Date" aria-describedby="basic-addon1">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
                                <textarea name="task_desc" id="summernote" cols="30" rows="10" class="form-control task_desc summernote_width"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="w-100 d-flex border-top pt-2 mt-2">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" class="btn btn-success rounded-0">
                                <i class="bi bi-check2"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- New Task Modal -->

<!-- View Task Modal -->
<div class="modal fade overflow" id="viewTaskModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="viewTaskModalLabel">Task Details</h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <dl>
                    <dt><b class="border-bottom border-primary">Task</b></dt>
                    <dd id="task_name"></dd>
                </dl>
                <dl>
                    <dt><b class="border-bottom border-primary">Status</b></dt>
                    <dd id="task_status">

                    </dd>
                </dl>
                <dl>
                    <dt><b class="border-bottom border-primary">Description</b></dt>
                    <dd id="task_desc"></dd>
                </dl>
                <div class="w-100 d-flex justify-content-end border-top pt-2 mt-2">
                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- View Task Modal -->

<!-- Task Modals -->




<!-- Members Progress/Activity Modals -->

<!-- New Productivity Modal -->
<div class="modal fade overflow" id="newProductivityModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="newProductivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="newProductivityModalLabel">
                    New Progress
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form id="np_form" method="post">
                    <input type="hidden" name="project_id" id="project_id" value="<?php echo $project_id; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
                                <select class="form-control rounded-0" name="task_id" id="task_id">
                                    <option value="selected" selected="true" hidden>--- Select Task ---</option>
                                    <?php
                                    $tasks = $conn->query("SELECT * FROM task_list where project_id = '$id' order by task asc ");
                                    while ($row = $tasks->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $row['id'] ?>"><?php echo ucwords($row['task']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Subject</span>
                                <input type="text" name="subject" id="subject" class="form-control rounded-0" aria-label="Subject" aria-describedby="basic-addon1">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Date</span>
                                <input type="date" name="date" id="date" class="form-control rounded-0" aria-label="Date" aria-describedby="basic-addon1">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Time</span>
                                <input type="time" name="start_time" id="start_time" class="form-control rounded-0" aria-label="Start Time" aria-describedby="basic-addon1">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Time</span>
                                <input type="time" name="end_time" id="end_time" class="form-control rounded-0" aria-label="End Time" aria-describedby="basic-addon1">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" id="basic-addon1">Comment/Progress Description</span>
                                <textarea name="comment" id="summernote1" cols="30" rows="10" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="w-100 d-flex border-top pt-2 mt-2">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="submit" id="np_btn" class="btn btn-success rounded-0">
                                <i class="bi bi-check2"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- New Productivity Modal -->

<!-- Members Progress/Activity Modals -->

<?php
require_once 'footer.php';
?>