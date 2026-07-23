<!-- Add New Project Modal -->
<div class="modal fade" id="addNewProject" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addNewProjectLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="addNewProjectLabel">
                    New Project
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body">
                <form action="" id="new_project" method="POST">
                    <div class="w-100 d-flex">
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Project Name</span>
                                <input type="text" name="p_name" id="p_name" class="form-control rounded-0" placeholder="Enter Project Name" aria-label="Project Name" aria-describedby="basic-addon1">
                            </div>
                        </div>
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
                                <select class="form-control rounded-0" name="p_status" id="p_status">
                                    <option value="selected" selected="true" hidden>--- Select Status ---</option>
                                    <option value="0">Pending</option>
                                    <option value="1">Started</option>
                                    <option value="2">On-Progress</option>
                                    <option value="3">On-Hold</option>
                                    <option value="4">Over Due</option>
                                    <option value="5">Done</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Date</span>
                                <input type="date" name="start_date" id="start_date" class="form-control rounded-0" autocomplete="off" aria-label="Start Date" aria-describedby="basic-addon1">
                            </div>
                        </div>
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Date</span>
                                <input type="date" name="end_date" id="end_date" class="form-control rounded-0" autocomplete="off" aria-label="End Date" aria-describedby="basic-addon1">
                            </div>
                        </div>
                    </div>

                    <div class="w-100 d-flex">
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Project Manager</span>
                                <select class="form-control rounded-0" name="manager_id" id="manager_id">
                                    <option value="selected" selected="true" hidden>--- Choose Project Manager ---</option>
                                    <?php
                                    $managers = $conn->query("SELECT *,fullname as `name` FROM registered_users  order by fullname asc ");
                                    while ($row = $managers->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $row['id'] ?>" <?php echo isset($manager_id) && $manager_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="w-50 px-1">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Team Members</span>
                                <select class="form-control rounded-0 select2" multiple name="user_ids[]" id="user_ids">
                                    <option value="selected"></option>
                                    <?php
                                    $employees = $conn->query("SELECT *,fullname as `name` FROM registered_users   order by fullname asc ");
                                    while ($row = $employees->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $row['id'] ?>" <?php echo isset($user_ids) && in_array($row['id'], explode(',', $user_ids)) ? "selected" : '' ?>><?php echo ucwords($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="input-group px-1 mb-3 position-relative">
                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
                        <textarea name="description" id="summernote" class="form-control p_description"></textarea>
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
<!-- Add New Project Modal -->