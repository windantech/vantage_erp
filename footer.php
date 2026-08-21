</div>

<!-- Wrapper End -->
<!-- jQuery. ONE copy, first, from our own server.

     There used to be three: 3.2.1 here, 3.6.0 from code.jquery.com below, and
     the local 3.6.0 further down again. Each reload REPLACES window.jQuery and
     with it every plugin registered against the old one, so the DataTables
     loaded a few lines below was silently wiped by the next jQuery and only
     worked because a later copy re-registered it. That is the kind of thing
     that works until the day a CDN is slow, and then fails in a way nobody
     can reproduce. -->
<script src="assets/vendor/jquery/jquery.min.js?v=<?php echo date('his'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous"></script>

<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<!-- second jQuery and a third DataTables removed; see the note above -->


<!-- Bootstrap 4's bundle stays. It looks redundant next to the Bootstrap 5
     loaded above and it is NOT: assets/js/scripts.js calls
     $('.sidebar .collapse').collapse('hide'), which is the jQuery plugin API
     that Bootstrap 5 removed, and thirteen pages including top_nav.php still
     use data-toggle= rather than data-bs-toggle=. The two coexist because
     they listen for different attributes. Removing this line breaks the
     sidebar and the top navigation on every page. -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/vendor/jquery-easing/jquery.easing.min.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/js/scripts.js?v=<?php echo date('his'); ?>"></script>
<script src="assets/vendor/chart.js/Chart.min.js?v=<?php echo date('his'); ?>"></script>
<!-- chart-area-demo.js and chart-pie-demo.js were sample files from the admin
     template that were never copied into this repository. Two more 404s. -->
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
<!-- export.js: another reference to a file that does not exist here. -->
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
    // Clear the flash immediately (server-side) so it shows exactly once. The old
    // client-side AJAX unset only ran after the user dismissed the alert, so
    // navigating away left $_SESSION['msg'] set and it re-popped on every load.
    $vaslFlash = $_SESSION['msg'];
    unset($_SESSION['msg']);
    if ($vaslFlash == "Success") { ?>
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
    <?php } elseif ($vaslFlash == "Failed") { ?>
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
    <?php } elseif ($vaslFlash == "Error") { ?>
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

<!-- Sidebar collapse fallback — makes the left-nav menus expand/collapse even when the
     Bootstrap/jQuery stack is broken (site loads BS4 + BS5 + jQuery x3). Self-contained,
     no dependencies. Capture phase + stopImmediatePropagation so it fully owns these clicks. -->
<script>
(function () {
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('#accordionSidebar [data-target], #accordionSidebar [data-bs-target]');
    if (!toggle) return;
    var sel = toggle.getAttribute('data-bs-target') || toggle.getAttribute('data-target');
    if (!sel || sel.charAt(0) !== '#') return;
    var panel = document.querySelector(sel);
    if (!panel || panel.className.indexOf('collapse') === -1) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    var willOpen = panel.className.indexOf('show') === -1;
    var parentSel = toggle.getAttribute('data-bs-parent') || toggle.getAttribute('data-parent');
    if (parentSel) {
      var parent = document.querySelector(parentSel);
      if (parent) {
        Array.prototype.forEach.call(parent.querySelectorAll('.collapse.show'), function (p) {
          if (p !== panel) { p.classList.remove('show'); }
        });
      }
    }
    panel.classList.toggle('show', willOpen);
    toggle.classList.toggle('collapsed', !willOpen);
    toggle.setAttribute('aria-expanded', String(willOpen));
  }, true);
})();
</script>

</body>

</html>