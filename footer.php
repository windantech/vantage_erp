</div>

<!-- Wrapper End -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous"></script>

<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>


<script src="assets/vendor/jquery/jquery.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/vendor/jquery-easing/jquery.easing.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/scripts.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/vendor/chart.js/Chart.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/demo/chart-area-demo.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/demo/chart-pie-demo.js?v=<?php echo date('his'); ?>"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="assets/js/datatables.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/active_page.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/modal.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/print.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/lead_forms.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/edit_lead_form.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/projects.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/tasks.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/activities.js?v=<?php echo date('his'); ?>"></script>
<!--<script src="assets/js/graphs.js?v=<?php echo date('his'); ?>"></script>-->
<script src="assets/js/dash_charts.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/compose_system_email.js?v=<?php echo date('his'); ?>"></script>
<!--<script src="assets/js/new_system_email.js?v=<?php echo date('his'); ?>"></script>-->
<!--<script src="assets/js/upd_system_email.js?v=<?php echo date('his'); ?>"></script>-->
<script src="assets/js/delete_email.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/config_email.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/export.js?v=<?php echo date('his'); ?>"></script>
<!-- Select2 -->
<script src="assets/js/select2.full.min.js?v=<?php echo date('his'); ?>"></script>

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>




<script>

    var table = $('#dataTable').DataTable();

    function handleSortChange(table, sortById) {
        $(sortById).change(function() {
            var selectedOption = $(this).val().split("_");
            var column = parseInt(selectedOption[0]);
            var order = selectedOption[1];

            table.order([column, order]).draw();
        });
    }

    handleSortChange(table, '#lead_form_sortBy');
</script>

<?php
if (isset($_SESSION['msg'])) {
    if ($_SESSION['msg'] == "Success") { ?>
        <script>
            Swal.fire({
                position: "top-end",
                icon: "success",
                title: "Success",
                text: "Data submitted successfully",
                showConfirmButton: true,
            }).then((result) => {
                $.ajax({
                    url: "includes/unset_message.inc.php",
                    type: "post",
                    success: function(response) {}
                });
            });
        </script>
    <?php } elseif ($_SESSION['msg'] == "Failed") { ?>
        <script>
            Swal.fire({
                position: "top-end",
                icon: "error",
                title: "Failed",
                text: "Failed to submit!\nPlease try again later.",
                showConfirmButton: true,
            }).then((result) => {
                $.ajax({
                    url: "includes/unset_message.inc.php",
                    type: "post",
                    success: function(response) {}
                });
            });
        </script>
    <?php } elseif ($_SESSION['msg'] == "Error") { ?>
        <script>
            Swal.fire({
                position: "top-end",
                icon: "error",
                title: "Error",
                text: "Something went wrong!\nPlease try again later.",
                showConfirmButton: true,
            }).then((result) => {
                $.ajax({
                    url: "includes/unset_message.inc.php",
                    type: "post",
                    success: function(response) {}
                });
            });
        </script>
<?php }
} ?>

</body>

</html>