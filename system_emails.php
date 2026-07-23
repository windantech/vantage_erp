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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">System Emails</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <button class="btn border-0 p-0 ms-3" onclick="location.href='new_system_email'">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive overflow">
                        <table class="table table-borderless border lead_table" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap border-bottom">Description</th>
                                    <th class="nowrap border-bottom">Date Created</th>
                                    <th class="nowrap border-bottom">Last Updated</th>
                                    <th class="nowrap border-bottom">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sm_result = $conn->query("SELECT se.*, ru.fullname FROM system_emails se LEFT JOIN registered_users ru ON se.updated_by = ru.id") or die($conn->error);

                                while ($sm_data = $sm_result->fetch_assoc()) { ?>
                                    <tr>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='view_system_emails?id=<?php echo $sm_data['id']; ?>'">
                                            <?php echo $sm_data['title']; ?>
                                        </td>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='view_system_emails?id=<?php echo $sm_data['id']; ?>'">
                                            <?php echo $sm_data['date_created']; ?>
                                        </td>
                                        <td class="nowrap py-2 border-bottom" onclick="location.href='view_system_emails?id=<?php echo $sm_data['id']; ?>'">
                                            <?php echo !empty($sm_data['last_updated']) ? $sm_data['last_updated'] : 'Null'; ?>
                                            <small><?php echo !empty($sm_data['fullname']) ? "(" . $sm_data['fullname'] . ")" : ''; ?></small>                                            
                                        </td>

                                        <td class="border-bottom py-2">
                                            <div class="dropdown">
                                                <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu rounded-0 border-info p-0">
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="edit_system_emails?id=<?php echo $sm_data['id']; ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-2" href="view_system_emails?id=<?php echo $sm_data['id']; ?>">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                            View
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