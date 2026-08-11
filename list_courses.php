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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Virtual courses</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <!--<button class="btn border-0 p-0">-->
                            <!--    <i class="bi bi-plus-lg"></i> Add-->
                            <!--</button>-->
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            
                        
                <thead>
                    <tr>
                        <th class="border-gray-200">#</th>
                       
                        <th class="border-gray-200">Course name </th>
                         <th class="border-gray-200"> Action</th>
                    </tr>
                </thead>
                <tbody>

<?php 
 $check_course = mysqli_query($conn,"SELECT * FROM `course` ") or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_course)){
                                while($row = mysqli_fetch_array($check_course) ){
                                    
                           ?>
                    <tr>
                  
                        <td>
                            <span class="fw-normal"><?php echo $row['course_id']; ?></span>
                        </td>
                      
                            <td>
                            <span class="fw-normal"><?php echo $row['course']; ?></span>
                        </td>
                        
                        <td><span class="fw-bold text-danger"><a href="view_assigned_user?id=<?php echo $row['course_id']; ?>" class="btn btn-sm btn-info">View</a></span>
                        
                          <button class="btn btn-sm btn-success rounded-0" data-bs-toggle="modal" data-bs-target="#modal_test<?php echo $row['course_id']; ?>">Config</button>
                         
                         <td class="nowrap py-2 border-bottom">
                                          
                                        </td>
                        </td>

                        
                    </tr>
                    <div class="modal fade" id="modal_test<?php echo $row['course_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="modalLabel_<?php echo $row['course_id']; ?>" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                     
                                        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                            <div class="modal-content rounded-0">
                                                <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                                    <h5 class="modal-title" id="modalLabel_<?php echo $row['course']; ?>"><?php echo $row['course']; ?></h5>
                                                    <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                                                </div>
                                                <div class="modal-body">
                                                       <form method="POST" action="#" enctype="multipart/form-data">
                                                    <input type="hidden" name="course_id" value="<?php echo $row['course_id']; ?>"> 
                                                    
                                                        <div class="row">
                                                          <div class="col-md-6">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Moodle Course ID</span>
                                                                <input type="number" name="course_id_new" id="course_id_new" value="<?php echo $row['course_id_new'] ?>" class="form-control rounded-0" aria-label="Video Url" aria-describedby="basic-addon1">
                                                            </div>
                                                        </div>
                                                        
                                                          <div class="col-md-6">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">First Quiz ID</span>
                                                                <input type="number" name="module_id" id="module_id" value="<?php echo $row['module_id'] ?>" class="form-control rounded-0" aria-label="Video Url" aria-describedby="basic-addon1">
                                                            </div>
                                                        </div>
                                                        
                                                        </div>
                                                    <div class="row">
                                                      
                                                      
                                                        <div class="col-md-6">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Introductory Video Link</span>
                                                                <input type="text" name="intro_link" id="intro_link" value="<?php echo $row['intro_video'] ?>" class="form-control rounded-0" aria-label="Video Url" aria-describedby="basic-addon1">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Testmonial Video Link </span>
                                                                <input value="<?php echo $row['intro_video'] ?>" type="text" name="testmonial_link" id="testmonial_link" class="form-control rounded-0" aria-label="Testmonial" aria-describedby="basic-addon1">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-12">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Write Up </span>
                                                                <textarea name="instruction" id="instruction" cols="30" rows="10" class="form-control rounded-0 instruction"><?php echo $row['writeup'] ?></textarea>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-12">
                                                            <div class="input-group mb-1">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 11rem;">Course outline link</span>
                                                                <input type="url" name="outline_link" value="<?php echo htmlspecialchars(isset($row['outline_link']) ? (string) $row['outline_link'] : ''); ?>" class="form-control rounded-0" placeholder="Paste a link (e.g. Google Drive) — optional">
                                                            </div>
                                                            <div class="input-group mb-1">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 11rem;">Course outline PDF</span>
                                                                <input type="file" name="outline_file" accept="application/pdf" class="form-control rounded-0">
                                                            </div>
                                                            <small class="text-muted d-block mb-3">Upload a PDF <em>or</em> paste a link — the site shows this as the course-outline download (below the write-up). A file overrides the link; clear both to remove it.</small>
                                                        </div>

                                                            <div class="col-md-12">
                                                            <div class="input-group mb-3">
                                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Course Outline </span>
                                                                <textarea name="adm_letter" id="adm_letter" cols="30" rows="10" class="form-control rounded-0 adm_letter"><?php echo $row['adm_letter'] ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="w-100 d-flex border-top pt-2 mt-2">
                                                        <div class="w-50">
                                                            <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                                <i class="bi bi-x-lg"></i> Cancel
                                                            </button>
                                                        </div>
                                                        <div class="w-50 d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-success rounded-0">
                                                                <i class="bi bi-check2"></i> Submit
                                                            </button>
                                                            
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                   <?php } }  ?>


                </tbody>
            </table>
            <div
                class="card-footer px-3 border-0 d-flex flex-column flex-lg-row align-items-center justify-content-between">

                <!--<div class="fw-normal small mt-4 mt-lg-0">Showing <b>2</b> out of <b>25</b> entries</div>-->
            </div>
        </div>
                </div>
            </div>
        </div>
    </div>
</section>
<style>
    .modal-body {
  max-height: 70vh;   /* limit height */
  overflow-y: auto;   /* enable scrolling */
}
textarea {
  resize: vertical;   /* allow resizing only up/down */
}

</style>
<?php 
if(isset($_POST['course_id'])){
    $course_id  = $_POST['course_id'];
    $intro_link = $_POST['intro_link'];
      $testmonial_link = $_POST['testmonial_link'];
      $instruction = $_POST['instruction'];
       $adm_letter = $_POST['adm_letter'];
       $course_id_new = $_POST['course_id_new'];
       $module_id = $_POST['module_id'];

       // Course outline — stored on its own (course.outline_link); the site renders the download
       // below the write-up. An uploaded PDF (hosted here) wins; otherwise the pasted link.
       $outline_url = '';
       if (isset($_FILES['outline_file']) && $_FILES['outline_file']['error'] === UPLOAD_ERR_OK && (int) $_FILES['outline_file']['size'] > 0) {
           $ext = strtolower(pathinfo($_FILES['outline_file']['name'], PATHINFO_EXTENSION));
           if ($ext === 'pdf') {
               $odir = __DIR__ . '/uploads/course_outlines';
               if (!is_dir($odir)) { @mkdir($odir, 0775, true); }
               $ofile = 'outline_' . preg_replace('/[^0-9]/', '', (string) $course_id) . '_' . time() . '.pdf';
               if (move_uploaded_file($_FILES['outline_file']['tmp_name'], $odir . '/' . $ofile)) {
                   $outline_url = 'https://vantageafricaleaders.com/admin/uploads/course_outlines/' . $ofile;
               }
           }
       }
       if ($outline_url === '') {
           $outline_url = isset($_POST['outline_link']) ? trim($_POST['outline_link']) : '';
       }
       // Remove any inline "Download the Course Outline Here" link from the write-up — shown separately now.
       $instruction = preg_replace('/<p>\s*<a\b[^>]*>.*?Download the Course Outline.*?<\/a>\s*<\/p>/is', '', $instruction);
       $instruction = preg_replace('/<a\b[^>]*>.*?Download the Course Outline.*?<\/a>/is', '', $instruction);

       $instruction_sql = mysqli_real_escape_string($conn, $instruction);
       $outline_sql = mysqli_real_escape_string($conn, $outline_url);
       $update = mysqli_query($conn,"UPDATE course SET intro_video='$intro_link',testmonial_link='$testmonial_link',adm_letter='$adm_letter',writeup='$instruction_sql',course_id_new='$course_id_new',module_id='$module_id',outline_link='$outline_sql' WHERE course_id='$course_id'") or die(mysqli_error($conn));
    
   ?>
   <script>window.alert("Updated!");
   window.location.href="list_courses";
   </script>
   <?php
}
?>
<script>
    function initSummernote(selector) {
        $(selector).summernote({
            height: 200,
            toolbar: [
                ["style", ["style"]],
                ["font", ["bold", "italic", "underline", "strikethrough", "superscript", "subscript", "clear"]],
                ["fontname", ["fontname"]],
                ["fontsize", ["fontsize"]],
                ["color", ["color"]],
                ["para", ["ol", "ul", "paragraph", "height"]],
                ["table", ["table"]],
                ["insert", ["link", "picture", "video", "shape"]],
                ["view", ["undo", "redo", "fullscreen", "codeview", "help"]],
            ],
        });
    }

    document.addEventListener("shown.bs.modal", function(event) {
        const modal = event.target;
        const summernoteTextarea = modal.querySelector(".instruction");
         const summernoteTextarea_ = modal.querySelector(".adm_letter");

        // if (summernoteTextarea) {
            initSummernote(summernoteTextarea);
            initSummernote(summernoteTextarea_);
        // }
    });
</script>
<?php
require_once 'footer.php';
?>