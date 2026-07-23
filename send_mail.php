<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Composed Mail</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <a href="bulk_mail" class="btn border-0 p-0"><i class="bi bi-plus-lg"></i> Add</a>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "asc" ]]'>
                            <thead>
                                <tr>
                                    <th> Id </th>
                                    <th> Subject </th>
                                    <th> Action </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql1 = mysqli_query($conn, "SELECT * FROM marketing_email_messages ORDER by id DESC LIMIT 1000");
                                if (mysqli_num_rows($sql1) > 0) {
                                    $counter = 1;
                                    while ($row = mysqli_fetch_assoc($sql1)) {
                                ?>
                                <tr>
                                    <td><?php echo $counter ?></td>
                                    <td><?php echo htmlspecialchars($row['subject']) ?></td>
                                    <td>
                                        <a href="view_composed_mail?id=<?php echo $row['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="bulk_mail?id=<?php echo $row['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-pencil-alt"></i> Edit
                                        </a>
                                        <a href="bulk_mail?duplicate=<?php echo $row['id'] ?>"
                                           onclick="return confirm('Create an editable copy of this email?')"
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-copy"></i> Duplicate
                                        </a>
                                        <a onclick="return confirm('Are you sure you want to delete this record?')"
                                           href="delete.php?id=<?php echo $row['id'] ?>" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php $counter++;
                                    }
                                } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th> Id </th>
                                    <th> Subject </th>
                                    <th> Action </th>
                                </tr>
                            </tfoot>
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