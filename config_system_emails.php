<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <?php
        $query_emails = "SELECT * FROM system_emails1";
        $stmt_emails = $conn->prepare($query_emails);
        $stmt_emails->execute();
        $result_emails = $stmt_emails->get_result();
        ?>


        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">System Emails Config</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addConfigModal">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>

                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="addConfigModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addConfigModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h5 class="modal-title fs-5 text-uppercase" id="addConfigModalLabel">New System Email Config</h5>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                                <form class="row d-flex" id="email_config_form">
                                    <div class="input-group mb-3 position-relative">
                                        <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Description</span>
                                        <input type="text" name="description" id="description" class="form-control rounded-0 w-100" placeholder="Enter Description" aria-label="Description" aria-describedby="basic-addon1">
                                    </div>

                                    <div class="input-group mb-3 position-relative">
                                        <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Choose Emails</span>
                                        <select name="select_emails[]" id="select_emails" class="form-control rounded-0 w-100 select2" multiple>
                                            <?php while ($email_data = $result_emails->fetch_assoc()): ?>
                                                <option value="<?php echo htmlspecialchars($email_data['id']); ?>">
                                                    <?php echo htmlspecialchars($email_data['subject']) . '(' . htmlspecialchars($email_data['course_opt']) . ')'; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>


                                    <div class="input-group mb-3 position-relative">
                                        <span class="input-group-text rounded-0 bg_main" style="min-width: 9rem;" id="basic-addon1">Schedule Date</span>
                                        <input type="datetime-local" name="schedule_date" id="schedule_date" class="form-control rounded-0 w-100" aria-label="Schedule Date" aria-describedby="basic-addon1">
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

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border lead_table" id="dataTable" width="100%" cellspacing="0" data-order='[[ 3, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">Schedule No</th>
                                    <th class="nowrap border-bottom">Description</th>
                                    <th class="nowrap border-bottom">Scheduled Date</th>
                                    <th class="nowrap border-bottom">Date Created</th>
                                    <th class="nowrap border-bottom">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $currentDateTime = date('Y-m-d H:i:s');
                                $query = "SELECT * FROM system_emails_config WHERE scheduled_date > '$currentDateTime'";
                                $smc_result = $conn->query($query) or die($conn->error);

                                while ($smc_data = $smc_result->fetch_assoc()) {
                                    $formattedDate = (new DateTime($smc_data['scheduled_date']))->format('D, d M Y H:i A');

                                    $selected_emails = json_decode($smc_data['selected_emails'], true);  // assuming it is stored as a JSON array
                                ?>
                                    <tr>
                                        <td class="nowrap py-2 border-bottom"><?php echo $smc_data['schedule_no']; ?></td>
                                        <td class="nowrap py-2 border-bottom"><?php echo $smc_data['description']; ?></td>
                                        <td class="nowrap py-2 border-bottom"><?php echo $formattedDate; ?></td>
                                        <td class="nowrap py-2 border-bottom"><?php echo $smc_data['create_date']; ?></td>
                                        <td class="border-bottom py-2">
                                            <div class="dropdown">
                                                <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu rounded-0 border-info p-0">
                                                    <li class="border-bottom border-info">
                                                        <!-- Edit Button with dynamic data-bs-target -->
                                                        <a class="dropdown-item py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editConfigModal<?php echo $smc_data['id']; ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <!-- View Button with dynamic data-bs-target -->
                                                        <a class="dropdown-item py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#viewConfigModal<?php echo $smc_data['id']; ?>">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                            View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-2" href="javascript:void(0);" id="del_schedule" data-email_id="<?php echo $smc_data['id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                            Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>

                                 

                                <?php } ?>
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

<script>
    $('#select_emails').select2({
        placeholder: "Type to filter",
        allowClear: true,
        width: '100%'
    });
</script>