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
                <form action="" id="new_productivity">
                    <?php
                    include 'manage_progress/progress_form.php';
                    ?>
                    <div class="w-100 d-flex border-top pt-2 mt-2">
                        <div class="w-50">
                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Cancel
                            </button>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button type="button" class="btn btn-success rounded-0">
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