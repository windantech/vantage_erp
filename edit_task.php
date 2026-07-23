<?php
require_once 'header.php';

// Get the task ID from the URL
$task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;
$p_id = isset($_GET['p_id']) ? $_GET['p_id'] : null;

if (!$task_id && !$p_id) {
    echo "<script>window.location.href = 'projects';</script>";
    exit;
}

// Fetch task details from the database
$query = "SELECT * FROM task_list WHERE id = $task_id";
$result = mysqli_query($conn, $query);
$task = mysqli_fetch_assoc($result);

$project = $conn->query("SELECT user_ids FROM task_list WHERE id = '$task_id'");
$user_ids = [];
if ($project->num_rows > 0) {
    $user_ids = explode(',', $project->fetch_assoc()['user_ids']);
}

if (!$task) {
    echo "<script>window.location.href = 'projects';</script>";
    exit;
}
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Edit Task: <?php echo ucwords($task['task']); ?></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.href='view_project?id=<?php echo $p_id; ?>'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-left-circle"></i> Go Back
                            </button>
                            <button onclick="location.reload()" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form action="" id="edit_task">
                        <input type="hidden" name="task_id" id="task_id" value="<?php echo ucwords($task['id']); ?>">
                        <input type="hidden" name="p_id" id="p_id" value="<?php echo ucwords($p_id); ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
                                    <input type="text" name="upd_task" id="upd_task" value="<?php echo ucwords($task['task']); ?>" class="form-control rounded-0" aria-label="Task" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
                                    <select class="form-control rounded-0" name="upd_status" id="upd_status">
                                        <option value="selected" selected="true" hidden>--- Select Status ---</option>
                                        <option value="1" <?php echo ($task['status'] == 1) ? 'selected' : ''; ?>>Pending</option>
                                        <option value="2" <?php echo ($task['status'] == 2) ? 'selected' : ''; ?>>On-Progress</option>
                                        <option value="3" <?php echo ($task['status'] == 3) ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group mb-3 position-relative">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Team Members</span>
                                    <select class="form-control rounded-0 select2" multiple name="user_ids[]" id="user_ids">
                                        <option value="selected" hidden></option>
                                        <?php
                                        $project = $conn->query("SELECT user_ids FROM project_list WHERE id = '$p_id'");
                                        if ($project->num_rows > 0) {
                                            $project_user_ids = explode(',', $project->fetch_assoc()['user_ids']);
                                            $project_user_ids_str = implode(',', array_map('intval', $project_user_ids));
                                            $users = $conn->query("SELECT id, fullname FROM registered_users WHERE id IN ($project_user_ids_str)");
                                            while ($row = $users->fetch_assoc()):
                                                $selected = in_array($row['id'], $user_ids) ? 'selected' : '';
                                        ?>
                                                <option value="<?php echo $row['id'] ?>" <?php echo $selected ?>><?php echo ucwords($row['fullname']) ?></option>
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
                                    <input type="date" name="end_date" id="end_date" value="<?php echo ucwords($task['end_date']); ?>" class="form-control rounded-0" autocomplete="off" aria-label="End Date" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
                                    <textarea name="upd_description" id="summernote2" cols="30" rows="10" class="form-control task_desc summernote_width"><?php echo ucwords($task['description']); ?></textarea>
                                </div>
                            </div>
                        </div>



                        <div class="w-100 d-flex border-top pt-2 mt-2 justify-content-end">
                            <button type="submit" class="btn btn-success rounded-0">
                                <i class="bi bi-check2"></i> Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>