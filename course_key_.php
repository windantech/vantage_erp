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
        $check = mysqli_query($conn,"SELECT `event_id`, `poster_image`, `event_title`, `event_description`, `start_on`, `end_on`, `location`, `host`, `early_start_on`, `early_end_on`, `early_amount`, `advance_start_on`, `advance_end_on`, `advance_amount`, `gate_start_on`, `gate_end_on`, `gate_amount`, `currency_code`, `status`,rate FROM `Event`  WHERE event_id>3 ORDER BY event_id DESC ") or die(mysqli_error($conn));
        if(mysqli_num_rows($check) > 0){
            while($row = mysqli_fetch_array($check)){
                $hyphenPosition = strpos($row['event_title'], '-');

if ($hyphenPosition !== false) {
    // Get the substring starting right after the hyphen
    $afterHyphen = substr($row['event_title'], $hyphenPosition + 1);
    
    // Trim any leading or trailing spaces
    $afterHyphen = trim($afterHyphen);
    
    // Get the first 3 characters
    $firstThreeLetters = substr($afterHyphen, 0, 3);
        ?>
        <tr>
                <td><span class="fw-normal"><?php echo $firstThreeLetters; ?></span></td>
            <td><span class="fw-normal">  <?php echo $row['event_title']; ?></span></td>                        
        
        </tr>
        <?php } }  } ?>
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
