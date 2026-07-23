<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
      
            if(isset($_GET['id'])){
                 //get user
    $id =  $_GET['id'];
     $check_user = mysqli_query($conn,"SELECT * FROM `course` WHERE course_id=$id ") or die(mysqli_error($conn));
     if(mysqli_num_rows($check_user) > 0 ){
 $row_user = mysqli_fetch_array($check_user);   
            
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"> <b> <?php echo $row_user['course']; ?></b></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                  <button class="btn btn-info rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#configContentModal">
    <i class="fa fa-cog"></i>
    Config Content Sections
</button>

<?php
// Get current content data when the page loads
$current_content_data = $row_user["content_sections"];
$content_data = !empty($current_content_data) ? json_decode($current_content_data, true) : [];

// Extract content details
$testimonials = $content_data['testimonials'] ?? [];
$background_expertise = $content_data['background_expertise'] ?? '';
$philosophy = $content_data['philosophy'] ?? '';
$case_study = $content_data['case_study'] ?? '';
$case_study_graphic = $content_data['case_study_graphic'] ?? '';
$unique_approach_intro = $content_data['unique_approach_intro'] ?? '';
$number_of_approaches = $content_data['number_of_approaches'] ?? 0;
$approaches = $content_data['approaches'] ?? [];
$elearning_screenshot = $content_data['elearning_screenshot'] ?? '';
$sample_cert_screenshot = $content_data['sample_cert_screenshot'] ?? '';
$lead_trainer_image = $content_data['lead_trainer_image'] ?? '';
$lead_trainer_intro = $content_data['lead_trainer_intro'] ?? '';
$coordinator_whatsapp = $content_data['coordinator_whatsapp'] ?? '';
?>

<!-- Config Content Sections Modal -->
<div class="modal fade" id="configContentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="configContentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="configContentLabel">
                    Configure Content Sections
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
                <form action="update_content_sections.php" method="POST" enctype="multipart/form-data" id="contentForm">
                    <input type="hidden" name="event_id" value="<?php echo $id; ?>">
                    
                    <!-- TESTIMONIALS SECTION -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-quote me-2"></i>Testimonials (3 Required)</h5>
                        </div>
                        <div class="card-body">
                            <?php for ($t = 1; $t <= 3; $t++): 
                                $testimonial = $testimonials[$t-1] ?? [];
                            ?>
                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="fw-bold mb-3">Testimonial <?php echo $t; ?></h6>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-chat-left-text me-2"></i>Testimonial Text
                                    </label>
                                    <textarea class="form-control" 
                                              name="testimonial_<?php echo $t; ?>_word" 
                                              rows="3" 
                                              placeholder="Enter testimonial quote..."
                                              required><?php echo htmlspecialchars($testimonial['word'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-person me-2"></i>Full Name
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="testimonial_<?php echo $t; ?>_name" 
                                               value="<?php echo htmlspecialchars($testimonial['name'] ?? ''); ?>"
                                               placeholder="e.g., John Doe"
                                               required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-flag me-2"></i>Country
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               name="testimonial_<?php echo $t; ?>_country" 
                                               value="<?php echo htmlspecialchars($testimonial['country'] ?? ''); ?>"
                                               placeholder="e.g., Kenya"
                                               required>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- OUR BACKGROUND AND EXPERTISE -->
                    <div class="mb-4">
                        <label for="background_expertise" class="form-label fw-bold">
                            <i class="bi bi-info-circle me-2 text-primary"></i>Our Background and Expertise
                        </label>
                        <textarea class="form-control" 
                                  id="background_expertise" 
                                  name="background_expertise" 
                                  rows="5" 
                                  placeholder="Describe your organization's background and expertise..."
                                  required><?php echo htmlspecialchars($background_expertise); ?></textarea>
                    </div>

                    <!-- OUR PHILOSOPHY -->
                    <div class="mb-4">
                        <label for="philosophy" class="form-label fw-bold">
                            <i class="bi bi-lightbulb me-2 text-warning"></i>Our Philosophy
                        </label>
                        <textarea class="form-control" 
                                  id="philosophy" 
                                  name="philosophy" 
                                  rows="5" 
                                  placeholder="Describe your organization's philosophy and approach to training..."
                                  required><?php echo htmlspecialchars($philosophy); ?></textarea>
                    </div>

                    <!-- CASE STUDY SECTION -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>Case Study</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="case_study" class="form-label fw-bold">
                                    <i class="bi bi-journal-text me-2"></i>Case Study Content
                                </label>
                                <textarea class="form-control" 
                                          id="case_study" 
                                          name="case_study" 
                                          rows="6" 
                                          placeholder="Provide a detailed case study..."
                                          required><?php echo htmlspecialchars($case_study); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="case_study_graphic" class="form-label fw-bold">
                                    <i class="bi bi-image me-2"></i>Case Study Graphic/Image
                                </label>
                                
                                <?php if (!empty($case_study_graphic)): ?>
                                <div class="border p-3 bg-light rounded mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($case_study_graphic); ?>" 
                                                 class="img-thumbnail me-3" 
                                                 style="width: 100px; height: 100px; object-fit: cover;"
                                                 alt="Case study graphic">
                                            <div>
                                                <p class="mb-1 fw-semibold">Current Graphic</p>
                                                <small class="text-muted"><?php echo basename($case_study_graphic); ?></small>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($case_study_graphic); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-eye me-1"></i>View Full
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <input type="file" 
                                       class="form-control" 
                                       id="case_study_graphic" 
                                       name="case_study_graphic" 
                                       accept=".jpg,.jpeg,.png,.gif"
                                       <?php echo empty($case_study_graphic) ? 'required' : ''; ?>>
                                <small class="form-text text-muted">
                                    <?php echo !empty($case_study_graphic) ? 'Leave empty to keep current graphic.' : 'Upload a graphic/image for the case study'; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- OUR UNIQUE APPROACH -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-star me-2"></i>Our Unique Approach</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="unique_approach_intro" class="form-label fw-bold">
                                    <i class="bi bi-text-paragraph me-2"></i>Introduction/Overview
                                </label>
                                <textarea class="form-control" 
                                          id="unique_approach_intro" 
                                          name="unique_approach_intro" 
                                          rows="4" 
                                          placeholder="Provide an overview of your unique approach..."
                                          required><?php echo htmlspecialchars($unique_approach_intro); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="number_of_approaches" class="form-label fw-bold">
                                    <i class="bi bi-list-ol me-2"></i>How Many Approaches Do You Use?
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="number_of_approaches" 
                                       name="number_of_approaches" 
                                       value="<?php echo htmlspecialchars($number_of_approaches); ?>"
                                       min="1" 
                                       max="20"
                                       placeholder="e.g., 5"
                                       onchange="generateApproachFields()"
                                       required>
                                <small class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i><strong>Important:</strong> Enter approaches in the order you want them displayed (1st will appear first, 2nd second, etc.)
                                </small>
                            </div>

                            <div id="approachesContainer" class="mt-4">
                                <?php if (!empty($approaches) && $number_of_approaches > 0): ?>
                                    <?php for ($i = 1; $i <= $number_of_approaches; $i++): 
                                        $approach = $approaches[$i-1] ?? [];
                                    ?>
                                    <div class="approach-field mb-4 border p-3 rounded bg-light">
                                        <h6 class="fw-bold mb-3">
                                            <i class="bi bi-arrow-right-circle me-2"></i>Approach <?php echo $i; ?>
                                            <span class="badge bg-secondary ms-2">Order: <?php echo $i; ?></span>
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Title</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="approach_<?php echo $i; ?>_title" 
                                                   value="<?php echo htmlspecialchars($approach['title'] ?? ''); ?>"
                                                   placeholder="e.g., Hands-on Practical Training"
                                                   required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Description</label>
                                            <textarea class="form-control" 
                                                      name="approach_<?php echo $i; ?>_description" 
                                                      rows="3" 
                                                      placeholder="Describe this approach in detail..."
                                                      required><?php echo htmlspecialchars($approach['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                <?php else: ?>
                                    <p class="text-muted fst-italic">Enter the number of approaches above to add them in order</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- E-LEARNING SCREENSHOT -->
                    <div class="mb-4">
                        <label for="elearning_screenshot" class="form-label fw-bold">
                            <i class="bi bi-laptop me-2 text-primary"></i>E-Learning Screenshot
                        </label>
                        
                        <?php if (!empty($elearning_screenshot)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($elearning_screenshot); ?>" 
                                         class="img-thumbnail me-3" 
                                         style="width: 120px; height: 80px; object-fit: cover;"
                                         alt="E-learning screenshot">
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Screenshot</p>
                                        <small class="text-muted"><?php echo basename($elearning_screenshot); ?></small>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($elearning_screenshot); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View Full
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               class="form-control" 
                               id="elearning_screenshot" 
                               name="elearning_screenshot" 
                               accept=".jpg,.jpeg,.png"
                               <?php echo empty($elearning_screenshot) ? 'required' : ''; ?>>
                        <small class="form-text text-muted">
                            <?php echo !empty($elearning_screenshot) ? 'Leave empty to keep current screenshot.' : 'Upload a screenshot of your e-learning platform'; ?>
                        </small>
                    </div>

                    <!-- SAMPLE CERTIFICATE SCREENSHOT -->
                    <div class="mb-4">
                        <label for="sample_cert_screenshot" class="form-label fw-bold">
                            <i class="bi bi-award me-2 text-warning"></i>Sample Certificate Screenshot
                        </label>
                        
                        <?php if (!empty($sample_cert_screenshot)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($sample_cert_screenshot); ?>" 
                                         class="img-thumbnail me-3" 
                                         style="width: 120px; height: 80px; object-fit: cover;"
                                         alt="Certificate screenshot">
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Certificate</p>
                                        <small class="text-muted"><?php echo basename($sample_cert_screenshot); ?></small>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($sample_cert_screenshot); ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-eye me-1"></i>View Full
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               class="form-control" 
                               id="sample_cert_screenshot" 
                               name="sample_cert_screenshot" 
                               accept=".jpg,.jpeg,.png"
                               <?php echo empty($sample_cert_screenshot) ? 'required' : ''; ?>>
                        <small class="form-text text-muted">
                            <?php echo !empty($sample_cert_screenshot) ? 'Leave empty to keep current certificate.' : 'Upload a sample certificate image'; ?>
                        </small>
                    </div>

                    <!-- LEAD TRAINER SECTION -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>Lead Trainer Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="lead_trainer_image" class="form-label fw-bold">
                                    <i class="bi bi-camera me-2"></i>Lead Trainer Photo
                                </label>
                                
                                <?php if (!empty($lead_trainer_image)): ?>
                                <div class="border p-3 bg-light rounded mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($lead_trainer_image); ?>" 
                                                 class="img-thumbnail me-3 rounded-circle" 
                                                 style="width: 80px; height: 80px; object-fit: cover;"
                                                 alt="Lead trainer">
                                            <div>
                                                <p class="mb-1 fw-semibold">Current Photo</p>
                                                <small class="text-muted"><?php echo basename($lead_trainer_image); ?></small>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($lead_trainer_image); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-eye me-1"></i>View Full
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <input type="file" 
                                       class="form-control" 
                                       id="lead_trainer_image" 
                                       name="lead_trainer_image" 
                                       accept=".jpg,.jpeg,.png"
                                       <?php echo empty($lead_trainer_image) ? 'required' : ''; ?>>
                                <small class="form-text text-muted">
                                    <?php echo !empty($lead_trainer_image) ? 'Leave empty to keep current photo.' : 'Upload lead trainer photo'; ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="lead_trainer_intro" class="form-label fw-bold">
                                    <i class="bi bi-file-person me-2"></i>Lead Trainer Introduction/Bio
                                </label>
                                <textarea class="form-control" 
                                          id="lead_trainer_intro" 
                                          name="lead_trainer_intro" 
                                          rows="5" 
                                          placeholder="Provide a detailed introduction about the lead trainer..."
                                          required><?php echo htmlspecialchars($lead_trainer_intro); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- COORDINATOR WHATSAPP -->
                    <div class="mb-4">
                        <label for="coordinator_whatsapp" class="form-label fw-bold">
                            <i class="bi bi-whatsapp me-2 text-success"></i>Coordinator WhatsApp Number
                        </label>
                        <input type="tel" 
                               class="form-control" 
                               id="coordinator_whatsapp" 
                               name="coordinator_whatsapp" 
                               value="<?php echo htmlspecialchars($coordinator_whatsapp); ?>"
                               placeholder="e.g., +254712345678"
                               pattern="^\+?[0-9]{10,15}$"
                               required>
                        <small class="form-text text-muted">
                            Enter with country code (e.g., +254712345678)
                        </small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-check-circle me-2"></i>Save Content Sections
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function generateApproachFields() {
    const numberOfApproaches = parseInt(document.getElementById('number_of_approaches').value) || 0;
    const container = document.getElementById('approachesContainer');
    
    if (numberOfApproaches < 1) {
        container.innerHTML = '<p class="text-muted fst-italic">Enter the number of approaches above to add them in order</p>';
        return;
    }
    
    let html = '';
    for (let i = 1; i <= numberOfApproaches; i++) {
        html += `
            <div class="approach-field mb-4 border p-3 rounded bg-light">
                <h6 class="fw-bold mb-3">
                    <i class="bi bi-arrow-right-circle me-2"></i>Approach ${i}
                    <span class="badge bg-secondary ms-2">Order: ${i}</span>
                </h6>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title</label>
                    <input type="text" 
                           class="form-control" 
                           name="approach_${i}_title" 
                           placeholder="e.g., Hands-on Practical Training"
                           required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea class="form-control" 
                              name="approach_${i}_description" 
                              rows="3" 
                              placeholder="Describe this approach in detail..."
                              required></textarea>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Initialize on page load if editing
document.addEventListener('DOMContentLoaded', function() {
    const currentApproaches = parseInt(document.getElementById('number_of_approaches').value) || 0;
    if (currentApproaches > 0) {
        // Fields already generated by PHP, no need to regenerate
    }
});
</script>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                    <div class="table-responsive">
                            
                                    <div class="card card-body border-0 shadow table-wrapper table-responsive">

            <div class="card">
                <div class="card-body">
                    <h4>Select Staff's To Assign  To <b> <?php echo $row_user['course']; ?></b></h4>

                      <form action="#" method="POST" >
                          
                          
           
            <div class="">
                   <div class="form-group mt-2">
                   
                    
         <div class="input-group mb-3">
             
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Select Roles</span>
                                                <select class="form-control select2" multiple name="role[]">
                                                    <option></option>
                          <?php 
                            $check_role = mysqli_query($conn,"SELECT `id`, `email`, `password`, `fullname`, `phone_no`, `role`, `status`, `token`, `transaction_key` FROM `registered_users` ") 
                            or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_role))
                            {
                                while($row_user_r = mysqli_fetch_array($check_role)){ 
                                    if(in_array($row_user_r['id'], explode(",", $row_user['assigned_to']))) {
                                        
                                    }else{
                                ?>
            
                        
                         <option  value="<?php echo $row_user_r['id']; ?>" ><?php echo $row_user_r['fullname'] ?></option>
                         
                   
                  <?php } } } ?>
                  
                     </select>
            
         
        </div>

                                            
             </div>



                                             


                   <!-- show selected roles -->
                    <div id="roles"></div>                    
                  
            </div>

            <div class="form-group mt-4">
                <button type="submit" class="btn btn-info">Assign Role</button>
            </div>

            </div>

        </form>
        
 <?php       
        if($_POST['role']){

    //Roles to assign the user
    $roles =  $_POST['role'];
     $check_user = mysqli_query($conn,"SELECT * FROM `course` WHERE course_id=$id ") or die(mysqli_error($conn));
 $row_user = mysqli_fetch_array($check_user);
 
 //Roles already assigned to the user
 $role_assigned =  $row_user['assigned_to'];
 

foreach ($roles as $role) {
    $role_assigned = $role_assigned.",".$role;
    
}
// echo $role_assigned."<br>";
$update  = mysqli_query($conn,"UPDATE course SET assigned_to='$role_assigned' WHERE course_id=$id ")  or die(mysqli_error($conn));
?>
<script>
    alert("Assigned successfully");
    window.location.href="view_assigned_user?id=<?php echo $id ?>"
</script>
<?php

}

?>

                    

                </div>
            </div>
<?php
 $role_selected_user  = explode(",",$row_user['assigned_to'] );
?>
                
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                <h3 class="mt-4">Roles</h3>
                <thead>
                    <tr>
                        <th class="border-gray-200">#</th>
                        <th class="border-gray-200"> Staff Name</th>
                        
                       
                        <th class="border-gray-200">Action</th>
                       
                    </tr>
                </thead>
                <tbody>
<?php 
function check_role($conn,$id){
    $query = mysqli_query($conn,"SELECT `id`, `email`, `password`, `fullname`, `phone_no`, `role`, `status`, `token`, `transaction_key` FROM `registered_users`  WHERE id=$id ") or die(mysqli_error($conn));
    if(mysqli_num_rows($query) > 0){
    $row = mysqli_fetch_array($query);
    return $row['fullname'];
    }else{
        return "";
    }
}
foreach ($role_selected_user as $roles) {

?>
                    <tr>
                        <td>
                            <a href="#" class="fw-bold">
                                <?php echo $roles; ?>
                            </a>
                        </td>
                        
                        
                       
                        <td><span class="fw-bold "><b><?php echo check_role($conn,$roles); ?></b></span></td>
                        <td><a href="revoke_user?user_id=<?php echo $id; ?>&role_id=<?php echo $roles; ?>&roles=<?php echo $role_selected_user; ?>" ><button class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to proceed with this action? ');" > Revoke </button> </a></td>

                        
                    </tr>
                
<?php  } ?>
                </tbody>
            </table>
            <div
                class="card-footer px-3 border-0 d-flex flex-column flex-lg-row align-items-center justify-content-between">

                
            </div>
        </div>



<?php }  } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>