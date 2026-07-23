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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Project List</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <?php if ($_SESSION['login_type'] != 3): ?>
                                <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addNewProject">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            <?php endif; ?>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless table-hover border" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">#</th>
                                    <th class="nowrap border-bottom">Project</th>
                                    <th class="nowrap border-bottom">Date Started</th>
                                    <th class="nowrap border-bottom">Due Date</th>
                                    <th class="nowrap border-bottom">Status</th>
                                    <th class="nowrap border-bottom">Actions</th>
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
                                $qry = $conn->query("SELECT * FROM project_list $where order by `name` asc");
                                while ($row = $qry->fetch_assoc()):
                                    $trans = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
                                    unset($trans["\""], $trans["<"], $trans[">"], $trans["<h2"]);
                                    $desc = strtr(html_entity_decode($row['description']), $trans);
                                    $desc = str_replace(array("<li>", "</li>"), array("", ", "), $desc);

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
                                    <tr class="border-bottom">
                                        <td onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
                                            <b><?php echo $i++ ?></b>
                                        </td>
                                        <td onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
                                            <p class="mb-0">
                                                <b><?php echo ucwords($row['name']) ?></b>
                                            </p>
                                            <p class="truncate">
                                                <?php echo strip_tags($desc) ?>
                                            </p>
                                        </td>
                                        <td class="nowrap" onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
                                            <b><?php echo date("M d, Y", strtotime($row['start_date'])) ?></b>
                                        </td>
                                        <td class="nowrap" onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
                                            <b><?php echo date("M d, Y", strtotime($row['end_date'])) ?></b>
                                        </td>
                                        <td onclick="location.href='view_project?id=<?php echo $row['id'] ?>'">
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
                                            <div class="dropdown">
                                                <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu rounded-0 border-info p-0">
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="view_project?id=<?php echo $row['id'] ?>">View</a>
                                                    </li>
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="edit_project?id=<?php echo $row['id']; ?>">Edit</a>
                                                    </li>

                                                    <li>
                                                        <a class="dropdown-item py-2 deleteProjectLink" href="#" data-id="<?php echo $row['id']; ?>">Delete</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>


                                    <!-- Edit Project Modal -->
                                    <div class="modal fade" id="editProjectModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editProjectModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-centered">
                                            <div class="modal-content rounded-0">
                                                <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                                    <h5 class="modal-title fs-5 text-uppercase" id="editProjectModalLabel<?php echo $row['id']; ?>">Edit Project <?php echo $row['name']; ?></h5>
                                                    <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="" id="manage-project">
                                                        <div class="w-100 d-flex">
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Name</span>
                                                                    <input type="text" name="name" class="form-control rounded-0" value="<?php echo (($row["name"] == "") ? "" : $row["name"]); ?>" placeholder="Name" aria-label="Name" aria-describedby="basic-addon1">
                                                                </div>
                                                            </div>
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
                                                                    <select class="form-control rounded-0" name="status" id="status">
                                                                        <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : '' ?>>Pending</option>
                                                                        <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : '' ?>>On-Hold</option>
                                                                        <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : '' ?>>Done</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="w-100 d-flex">
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Date</span>
                                                                    <input type="date" name="start_date" class="form-control rounded-0" autocomplete="off" aria-label="Start Date" aria-describedby="basic-addon1">
                                                                </div>
                                                            </div>
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Date</span>
                                                                    <input type="date" name="end_date" class="form-control rounded-0" autocomplete="off" aria-label="End Date" aria-describedby="basic-addon1">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="w-100 d-flex">
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Project Manager</span>
                                                                    <select class="form-control rounded-0" name="manager_id">
                                                                        <option selected="true" hidden>--- Choose Project Manager ---</option>
                                                                        <?php
                                                                        $managers = $conn->query("SELECT *, fullname as `name` FROM registered_users WHERE `role` LIKE '%82%' ORDER BY fullname ASC");
                                                                        while ($row = $managers->fetch_assoc()):
                                                                        ?>
                                                                            <option value="<?php echo $row['id'] ?>" <?php echo isset($manager_id) && $manager_id == $row['id'] ? "selected" : '' ?>>
                                                                                <?php echo ucwords($row['name']) ?>
                                                                            </option>
                                                                        <?php endwhile; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="w-50 px-1">
                                                                <div class="input-group mb-3">
                                                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Team Members</span>
                                                                    <select class="form-control rounded-0 select2" multiple name="user_ids[]">
                                                                        <option></option>
                                                                        <?php
                                                                        $employees = $conn->query("SELECT *,fullname as `name` FROM registered_users where `role` LIKE '%83%' order by fullname asc ");
                                                                        while ($row = $employees->fetch_assoc()):
                                                                        ?>
                                                                            <option value="<?php echo $row['id'] ?>" <?php echo isset($user_ids) && in_array($row['id'], explode(',', $user_ids)) ? "selected" : '' ?>><?php echo ucwords($row['name']) ?></option>
                                                                        <?php endwhile; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="input-group px-1 mb-3">
                                                            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
                                                            <textarea name="description" id="summernote3" class="form-control"></textarea>
                                                        </div>

                                                        <div class="w-100 d-flex border-top pt-2 mt-2">
                                                            <div class="w-50">
                                                                <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                                    <i class="bi bi-x-lg"></i> Cancel
                                                                </button>
                                                            </div>
                                                            <div class="w-50 d-flex justify-content-end">
                                                                <button type="button" class="btn btn-success rounded-0">
                                                                    <i class="bi bi-check2"></i> Update
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Edit Project Modal -->

                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php
                require 'manage_projects/new_project_modal.php';
                ?>

                <!-- Delete Project Modal -->
                <div class="modal fade" id="deleteProjectModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="deleteProjectModalLabel">
                                    Delete Project
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form action="">
                                    Are you sure to delete this project?
                                    <div class="w-100 d-flex border-top pt-2 mt-2">
                                        <div class="w-50">
                                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                <i class="bi bi-x-lg"></i> Cancel
                                            </button>
                                        </div>
                                        <div class="w-50 d-flex justify-content-end">
                                            <button type="button" class="btn btn-danger rounded-0">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Delete Project Modal -->

            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>