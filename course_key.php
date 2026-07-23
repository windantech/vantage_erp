<div class="accordion" id="accordion_">

  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_" aria-expanded="false" aria-controls="collapse_">
      <B>Expand to see what each key  value mean's</B>
      </button>
    </h2>
    <div id="collapse_" class="accordion-collapse collapse" data-bs-parent="#accordion_">
      <div class="accordion-body">
       
         <div class="table-responsive">
                  <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
    <thead>
        <tr>
            <th class="border-gray-200" style="width: 20%;">Course Shortform</th>						
            <th class="border-gray-200">Course Full Name</th>
        </tr>
    </thead>
    <tbody>
        <!-- Item -->
        <?php
        $check = mysqli_query($conn,"SELECT `id`, `course_id`, `course`, `price_usd`, `close_date`, `study_type`, `status`, `resource_id`, `assigned_to`, `shortname` FROM `course` WHERE course_id > 1 ") or die(mysqli_error($conn));
        if(mysqli_num_rows($check) > 0){
            while($row = mysqli_fetch_array($check)){
        ?>
        <tr>
            <td><span class="fw-normal">  <?php echo $row['shortname']; ?></span></td>                        
            <td><span class="fw-normal"><?php echo $row['course']; ?></span></td>
        </tr>
        <?php } } ?>
    </tbody>
    <tfoot>
        <tr>
            <th class="border-gray-200" style="width: 20%;">Course Shortform</th>						
            <th class="border-gray-200">Course Full Name</th>
        </tr>
    </tfoot>
</table>


                                    </div>
                                    
      </div>
    </div>
  </div>
</div>
<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            "pageLength": 5,   // Show 5 entries per page
            "lengthMenu": [5, 10, 25, 50],  // Dropdown options for the number of entries per page
            "ordering": true   // Enable ordering on columns
        });
    });
</script>
