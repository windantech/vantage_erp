<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';

        if (isset($_GET['id']) && !empty($_GET['id'])) {
            $project_id = $_GET['id'];
            $query = "SELECT * FROM project_list WHERE id = $project_id";
            $result = mysqli_query($conn, $query);

            if ($result) {
                $project = mysqli_fetch_assoc($result);
                $umanager_id = $project['manager_id'];
                $user_ids = $project['user_ids'];
                $status = $project['status'];
            } else {
                echo "Error fetching project details.";
            }
        } else { ?>
            <script>
                window.location.href = 'projects';
            </script>
        <?php }
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Edit <?php echo $project['name']; ?></h6>
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
                    <form action="" id="update_project" method="POST">
                        <input type="hidden" name="project_id" id="project_id" value="<?php echo $project_id; ?>">
                        <div class="w-100 d-flex">
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Name</span>
                                    <input type="text" name="up_name" id="up_name" class="form-control rounded-0" value="<?php echo (($project["name"] == "") ? "" : $project["name"]); ?>" placeholder="Name" aria-label="Name" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
                                    <select class="form-control rounded-0" name="up_status" id="up_status">
                                        <option value="selected" selected="true" hidden>--- Select Status ---</option>
                                        <option value="0" <?php echo isset($status) && $status == 0 ? 'selected' : ''; ?>>Pending</option>
                                        <option value="1" <?php echo isset($status) && $status == 1 ? 'selected' : ''; ?>>Started</option>
                                        <option value="2" <?php echo isset($status) && $status == 2 ? 'selected' : ''; ?>>On-Progress</option>
                                        <option value="3" <?php echo isset($status) && $status == 3 ? 'selected' : ''; ?>>On-Hold</option>
                                        <option value="4" <?php echo isset($status) && $status == 4 ? 'selected' : ''; ?>>Over Due</option>
                                        <option value="5" <?php echo isset($status) && $status == 5 ? 'selected' : ''; ?>>Done</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="w-100 d-flex">
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Date</span>
                                    <input type="date" name="ustart_date" id="ustart_date" class="form-control rounded-0" value="<?php echo (($project["start_date"] == "") ? "" : $project["start_date"]); ?>" autocomplete="off" aria-label="Start Date" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Date</span>
                                    <input type="date" name="uend_date" id="uend_date" class="form-control rounded-0" value="<?php echo (($project["end_date"] == "") ? "" : $project["end_date"]); ?>" autocomplete="off" aria-label="End Date" aria-describedby="basic-addon1">
                                </div>
                            </div>
                        </div>

                        <div class="w-100 d-flex">
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Project Manager</span>
                                    <select class="form-control rounded-0" name="umanager_id" id="umanager_id">
                                        <option value="selected" selected="true" hidden>--- Choose Project Manager ---</option>
                                        <?php
                                        $managers = $conn->query("SELECT *, fullname as `name` FROM registered_users WHERE `role` LIKE '%82%' ORDER BY fullname ASC");
                                        while ($row = $managers->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $row['id'] ?>" <?php echo isset($umanager_id) && $umanager_id == $row['id'] ? "selected" : '' ?>>
                                                <?php echo ucwords($row['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="w-50 px-1">
                                <div class="input-group mb-3">
                                    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Team Members</span>
                                    <select class="form-control rounded-0 select2" multiple name="uuser_ids[]" id="uuser_ids">
                                        <option value="selected"></option>
                                        <?php
                                        $employees = $conn->query("SELECT *, fullname as `name` FROM registered_users WHERE `role` LIKE '%83%' ORDER BY fullname ASC");
                                        while ($row = $employees->fetch_assoc()):
                                        ?>
                                            <option value="<?php echo $row['id'] ?>" <?php echo isset($user_ids) && in_array($row['id'], explode(',', $user_ids)) ? "selected" : '' ?>>
                                                <?php echo ucwords($row['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="input-group px-1 mb-3">
                            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
                            <textarea name="up_description" id="summernote" class="form-control up_description"><?php echo (($project["description"] == "") ? "" : $project["description"]); ?></textarea>
                        </div>

                        <div class="w-100 d-flex justify-content-end border-top pt-2 mt-2">
                            <button type="submit" id="update_project" class="btn btn-success rounded-0">
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