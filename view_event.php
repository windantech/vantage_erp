<?php
require_once 'header.php';
?>
<style>
        .box {
            display: block;
            min-width: 300px;
            height: 300px;
            margin: 10px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
        }
        
        .box_1 {
            display: block;
            min-width: 300px;
            height: 300px;
            margin: 10px;
            background-color: white;
            border-radius: 0;0
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
        }

        .upload-options {
            position: relative;
            height: 75px;
            background-color: #ff8531;
            cursor: pointer;
            overflow: hidden;
            text-align: center;
            transition: background-color ease-in-out 150ms;
        }

        .upload-options:hover {
            background-color: #b6500a;
        }

        .upload-options input {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }

        .upload-options label {
            display: flex;
            align-items: center;
            width: 100%;
            height: 100%;
            font-weight: 400;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            overflow: hidden;
        }

        .upload-options label::after {
            content: "+";
            position: absolute;
            font-size: 2.5rem;
            color: #e6e6e6;
            z-index: 0;
            display: flex;
            justify-content: center;
            align-content: center;
            flex-wrap: wrap;
            height: 100%;
            width: 100%;
        }

        .upload-options label span {
            display: inline-block;
            width: 50%;
            height: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            vertical-align: middle;
            text-align: center;
        }

        .upload-options label span:hover i.material-icons {
            color: lightgray;
        }

        .js--image-preview {
            height: 225px;
            width: 100%;
            position: relative;
            overflow: hidden;
            background-image: url("");
            background-color: white;
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .js--image-preview::after {
            content: "Poster Preview";
            position: relative;
            font-size: 35px;
            color: #e6e6e6;
            z-index: 0;
            display: flex;
            justify-content: center;
            align-content: center;
            flex-wrap: wrap;
            height: 100%;
        }

        .js--image-preview.js--no-default::after {
            display: none;
        }

        .js--image-preview:nth-child(2) {
            background-image: url("http://bastianandre.at/giphy.gif");
        }

        .drop {
            display: block;
            position: absolute;
            background: rgba(95, 158, 160, 0.2);
            border-radius: 100%;
            transform: scale(0);
        }

        .animate {
            -webkit-animation: ripple 0.4s linear;
            animation: ripple 0.4s linear;
        }

        @-webkit-keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        @keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        .bg_image {
            filter: blur(8px);
            -webkit-filter: blur(8px);
            height: 100%;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .bg_data {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
            width: 100%;
            padding: 20px;
            text-align: center;
            height: inherit;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        /* Summernote: ensure text is visible while editing */
        .note-editor.note-frame .note-editing-area .note-editable {
            color: #111 !important;
            background-color: #fff !important;
        }
    </style>
<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        
        $event_id = intval($_GET['event_id'] ?? 0);
        if ($event_id <= 0) {
            echo "<div class='alert alert-danger m-3'>Missing or invalid event_id.</div>";
            require_once 'footer.php';
            exit;
        }
function event_detail($conn,$event_id,$variable){
    $event_id = intval($event_id);
    $check = mysqli_query($conn,"SELECT `event_id`, `poster_image`, `event_title`, `event_description`, `start_on`, `end_on`, `location`, `host`, `early_start_on`, `early_end_on`, `early_amount`, `advance_start_on`, `advance_end_on`, `advance_amount`, `gate_start_on`, `gate_end_on`, `gate_amount`, `currency_code`, `status`, `rate`, `flag_flier`, `simple_writeup`, `youtube_link`, `testimonial_video_link`, `training_schedule`, `training_gallery`, `lead_form_id`, `cohort_data` FROM `Event` WHERE event_id=$event_id") or die(mysqli_error($conn));
    if(mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_array($check);
        return $row[$variable];
    }else{
        return " ";
    }
}

        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">View event || <?php echo event_detail($conn,$event_id,"event_title"); ?></h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                           <!--<a href="import_email_data" class="btn border-0 p-0"><i class="fas fa-eye"></i> View All  Dataset</a>-->
                           
                           
                             <button class="btn btn-secondary rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#editEventModal">
                            <i class="fa fa-plus"></i>
                            Edit
                        </button>
                        
          <button class="btn btn-primary rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#editEventModal_">
    <i class="fa fa-plus"></i>
    Config Flag Flier
</button> 

          <button class="btn btn-warning rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#editEventMediaModal">
    <i class="fa fa-image"></i>
    Media (Poster/Links)
</button>

<?php
// Get current event data when the page loads
$current_flier = event_detail($conn, $event_id, "flag_flier");
$current_writeup = event_detail($conn, $event_id, "simple_writeup");
$current_youtube_link = event_detail($conn, $event_id, "youtube_link");
$current_testimonial_video = event_detail($conn, $event_id, "testimonial_video_link");
$current_training_schedule = event_detail($conn, $event_id, "training_schedule");
$current_training_gallery = event_detail($conn, $event_id, "training_gallery");
$current_poster = event_detail($conn, $event_id, "poster_image");

$lead_form_id = event_detail($conn, $event_id, "lead_form_id");


// Decode gallery images (stored as JSON array)
$gallery_images = !empty($current_training_gallery) ? json_decode($current_training_gallery, true) : [];
?>

<!-- Media Modal (Poster + Flier + Links) -->
<div class="modal fade" id="editEventMediaModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editEventMediaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="editEventMediaLabel">
                    Update Event Media
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
                <form action="update_event.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">

                    <div class="row g-4">
                        <!-- Poster -->
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between">
                                    <label class="form-label fw-bold mb-0">
                                        <i class="bi bi-image me-2 text-primary"></i>Poster Image
                                    </label>
                                    <?php if (!empty($current_poster)): ?>
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo htmlspecialchars($current_poster); ?>">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($current_poster)): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo htmlspecialchars($current_poster); ?>" alt="Current poster" class="img-thumbnail w-100" style="max-height: 320px; object-fit: contain; background:#f8f9fa;">
                                        <div class="form-text">
                                            Current: <span class="fw-semibold"><?php echo htmlspecialchars(basename($current_poster)); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light mt-2 mb-2">No poster image set yet.</div>
                                <?php endif; ?>

                                <input type="file" class="form-control mt-2" name="poster_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text"><small>Upload a new poster image (optional). Leave empty to keep current.</small></div>
                            </div>
                        </div>

                        <!-- Flier + Links -->
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <label class="form-label fw-bold">
                                    <i class="bi bi-link-45deg me-2 text-success"></i>Flier & Video Links
                                </label>

                                <!-- Flier preview -->
                                <?php if (!empty($current_flier)): ?>
                                    <div class="border p-2 bg-light rounded mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-semibold">Current flier</div>
                                                <small class="text-muted"><?php echo htmlspecialchars(basename($current_flier)); ?></small>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($current_flier); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light mb-2">No flier set yet.</div>
                                <?php endif; ?>
                                <input type="file" class="form-control mb-3" name="flag_flier" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                                <div class="form-text mb-3"><small>Upload a new flier (optional). Leave empty to keep current.</small></div>

                                <!-- YouTube -->
                                <?php if (!empty($current_youtube_link)): ?>
                                    <div class="border p-2 bg-light rounded mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-semibold">Current YouTube link</div>
                                                <small class="text-muted"><?php echo htmlspecialchars($current_youtube_link); ?></small>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($current_youtube_link); ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="url" class="form-control mb-3" name="youtube_link" value="<?php echo htmlspecialchars($current_youtube_link); ?>" placeholder="https://www.youtube.com/watch?v=...">

                                <!-- Testimonial -->
                                <?php if (!empty($current_testimonial_video)): ?>
                                    <div class="border p-2 bg-light rounded mb-2">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-semibold">Current testimonial link</div>
                                                <small class="text-muted"><?php echo htmlspecialchars($current_testimonial_video); ?></small>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($current_testimonial_video); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Open
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="url" class="form-control" name="testimonial_video_link" value="<?php echo htmlspecialchars($current_testimonial_video); ?>" placeholder="https://youtu.be/... / https://vimeo.com/...">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-circle me-2"></i>Save Media
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- End Media Modal -->

<!-- Edit Modal -->
<div class="modal fade" id="editEventModal_" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editEventLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="editEventLabel">
                    Edit Event
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
                <form action="update_event.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    
                      <div class="form-group mt-2">
                   
                    
         <div class="input-group mb-3">
             
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Select Lead Form </span>
                                                <select class="form-control select2" multiple name="lead_form_id">
                                                    <option ></option>
                          <?php 
                            $check_role = mysqli_query($conn,"SELECT id,title FROM lead_forms ORDER BY id DESC") 
                            or die(mysqli_error($conn));
                            if(mysqli_num_rows($check_role))
                            {
                                while($row_user_r = mysqli_fetch_array($check_role)){ 
                               ?>
            
                        
                         <option  value="<?php echo $row_user_r['id']; ?>" ><?php echo $row_user_r['title'] ?></option>
                         
                   
                  <?php  } } ?>
                  
                     </select>
            
         
        </div>

                                            
             </div>
                    
                    <!-- Current Flier Display -->
                    <?php if (!empty($current_flier)): ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-flag-fill me-2"></i>Current Flier
                        </label>
                        <div class="border p-3 bg-light rounded">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-image fs-4 text-primary me-3"></i>
                                <div>
                                    <p class="mb-1 fw-semibold">Current file: <?php echo basename($current_flier); ?></p>
                                    <a href="<?php echo $current_flier; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>View Current Flier
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upload New Flier -->
                    <div class="mb-4">
                        <label for="flag_flier" class="form-label fw-bold">
                            <i class="bi bi-cloud-upload me-2"></i><?php echo !empty($current_flier) ? 'Update Flier (Optional)' : 'Upload Flier'; ?>
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="flag_flier" 
                               name="flag_flier" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                        <div class="form-text">
                            <small><?php echo !empty($current_flier) ? 'Leave empty to keep current flier.' : 'Upload a flier for this event.'; ?> Accepted formats: JPG, PNG, GIF, PDF, DOC, DOCX</small>
                        </div>
                    </div>

                    <!-- YouTube Link -->
                    <div class="mb-4">
                        <label for="youtube_link" class="form-label fw-bold">
                            <i class="bi bi-youtube me-2 text-danger"></i>YouTube Video Link
                        </label>
                        <?php if (!empty($current_youtube_link)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-play-circle-fill fs-4 text-danger me-3"></i>
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Video Link</p>
                                        <small class="text-muted"><?php echo htmlspecialchars($current_youtube_link); ?></small>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($current_youtube_link); ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-eye me-1"></i>View Video
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="url" 
                               class="form-control" 
                               id="youtube_link" 
                               name="youtube_link" 
                               value="<?php echo htmlspecialchars($current_youtube_link); ?>"
                               placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/...">
                        <div class="form-text">
                            <small>Enter a YouTube video URL for this event (optional)</small>
                        </div>
                    </div>

                    <!-- NEW: Testimonial Video Link -->
                    <div class="mb-4">
                        <label for="testimonial_video_link" class="form-label fw-bold">
                            <i class="bi bi-camera-video me-2 text-success"></i>Testimonial Video Link
                        </label>
                        <?php if (!empty($current_testimonial_video)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-play-circle-fill fs-4 text-success me-3"></i>
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Testimonial Video</p>
                                        <small class="text-muted"><?php echo htmlspecialchars($current_testimonial_video); ?></small>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($current_testimonial_video); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye me-1"></i>View Video
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="url" 
                               class="form-control" 
                               id="testimonial_video_link" 
                               name="testimonial_video_link" 
                               value="<?php echo htmlspecialchars($current_testimonial_video); ?>"
                               placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/...">
                        <div class="form-text">
                            <small>Enter a testimonial video URL (YouTube, Vimeo, etc.) - optional</small>
                        </div>
                    </div>

                    <!-- NEW: Training Schedule PDF -->
                    <div class="mb-4">
                        <label for="training_schedule" class="form-label fw-bold">
                            <i class="bi bi-calendar3 me-2 text-info"></i>Training Schedule (PDF)
                        </label>
                        <?php if (!empty($current_training_schedule)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-pdf-fill fs-4 text-danger me-3"></i>
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Schedule: <?php echo basename($current_training_schedule); ?></p>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($current_training_schedule); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="file" 
                               class="form-control" 
                               id="training_schedule" 
                               name="training_schedule" 
                               accept=".pdf">
                        <div class="form-text">
                            <small><?php echo !empty($current_training_schedule) ? 'Leave empty to keep current schedule.' : 'Upload training schedule PDF'; ?> (PDF only, max 10MB)</small>
                        </div>
                    </div>

                    <!-- NEW: Training Gallery Images -->
                    <div class="mb-4">
                        <label for="training_gallery" class="form-label fw-bold">
                            <i class="bi bi-images me-2 text-warning"></i>Training Gallery Images
                        </label>
                        
                        <?php if (!empty($gallery_images)): ?>
                        <div class="border p-3 bg-light rounded mb-3">
                            <p class="mb-2 fw-semibold">Current Gallery (<?php echo count($gallery_images); ?> images)</p>
                            <div class="row g-2">
                                <?php foreach ($gallery_images as $index => $image): ?>
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                             class="img-thumbnail w-100" 
                                             style="height: 120px; object-fit: cover;"
                                             alt="Gallery image">
                                        <button type="button" 
                                                class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                onclick="removeGalleryImage(<?php echo $index; ?>)"
                                                style="padding: 2px 6px;">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="existing_gallery" id="existing_gallery" value='<?php echo htmlspecialchars($current_training_gallery); ?>'>
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               class="form-control" 
                               id="training_gallery" 
                               name="training_gallery[]" 
                               accept=".jpg,.jpeg,.png,.gif"
                               multiple>
                        <div class="form-text">
                            <small>Upload multiple training gallery images (JPG, PNG, GIF - max 5MB each). Hold Ctrl/Cmd to select multiple files.</small>
                        </div>
                    </div>

                    <!-- Writeup -->
                    <div class="mb-4">
                        <label for="simple_writeup" class="form-label fw-bold">
                            <i class="bi bi-textarea-t me-2"></i>Event Writeup
                        </label>
                        <textarea class="form-control" 
                                  id="simple_writeup" 
                                  name="simple_writeup" 
                                  rows="6" 
                                  placeholder="Enter event description, details, or writeup..."><?php echo htmlspecialchars($current_writeup); ?></textarea>
                        <div class="form-text">
                            <small>Provide a detailed description of the event</small>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Function to remove gallery image
function removeGalleryImage(index) {
    if (!confirm('Remove this image from gallery?')) return;
    
    let existingGallery = document.getElementById('existing_gallery');
    let galleryArray = JSON.parse(existingGallery.value || '[]');
    
    galleryArray.splice(index, 1);
    existingGallery.value = JSON.stringify(galleryArray);
    
    // Reload the page or update UI
    alert('Image will be removed when you save changes');
}
</script>
<!-- End Edit Modal -->

<button class="btn btn-success rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#configCohortModal">
    <i class="fa fa-users"></i>
    Config Cohort
</button>

<?php
// Get current cohort data when the page loads
$current_cohort_data = event_detail($conn, $event_id, "cohort_data");
$cohort_data = !empty($current_cohort_data) ? json_decode($current_cohort_data, true) : [];

// Extract cohort details
$country_name = $cohort_data['country_name'] ?? '';
$cohort_title = $cohort_data['title'] ?? '';
$introduction_text = $cohort_data['introduction_text'] ?? '';
$intro_image = $cohort_data['intro_image'] ?? '';
$number_of_cohorts = $cohort_data['number_of_cohorts'] ?? 0;
$professionals_trained = $cohort_data['professionals_trained'] ?? 0;
$cohort_images = $cohort_data['cohort_images'] ?? [];
?>

<!-- Config Cohort Modal -->
<div class="modal fade" id="configCohortModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="configCohortLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-0">
            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                <h1 class="modal-title fs-5 text-uppercase" id="configCohortLabel">
                    Configure Cohort
                </h1>
                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
            </div>
            <div class="modal-body p-4" style="max-height: 80vh; overflow-y: auto;">
                <form action="update_cohort.php" method="POST" enctype="multipart/form-data" id="cohortForm">
                    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                    
                    <!-- Country Name -->
                    <div class="mb-4">
                        <label for="country_name" class="form-label fw-bold">
                            <i class="bi bi-flag me-2"></i>Country Name
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="country_name" 
                               name="country_name" 
                               value="<?php echo htmlspecialchars($country_name); ?>"
                               placeholder="e.g., Gambia"
                               required>
                        <div class="form-text">
                            <small>Enter the name of the country for this cohort</small>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="mb-4">
                        <label for="cohort_title" class="form-label fw-bold">
                            <i class="bi bi-card-heading me-2"></i>Cohort Title
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="cohort_title" 
                               name="cohort_title" 
                               value="<?php echo htmlspecialchars($cohort_title); ?>"
                               placeholder="e.g., Gambia Ports Authority Board Training on M&E"
                               required>
                        <div class="form-text">
                            <small>Enter a descriptive title for this cohort training</small>
                        </div>
                    </div>

                    <!-- Introduction Text -->
                    <div class="mb-4">
                        <label for="introduction_text" class="form-label fw-bold">
                            <i class="bi bi-textarea-t me-2"></i>Introduction Text
                        </label>
                        <textarea class="form-control" 
                                  id="introduction_text" 
                                  name="introduction_text" 
                                  rows="6" 
                                  placeholder="The Gambia Ports Authority sponsored 4 staff members from their M&E department to attend our open program..."
                                  required><?php echo htmlspecialchars($introduction_text); ?></textarea>
                        <div class="form-text">
                            <small>Provide detailed information about the cohort training</small>
                        </div>
                    </div>

                    <!-- Introduction Image -->
                    <div class="mb-4">
                        <label for="intro_image" class="form-label fw-bold">
                            <i class="bi bi-image me-2 text-primary"></i>Introduction Image
                        </label>
                        
                        <?php if (!empty($intro_image)): ?>
                        <div class="border p-3 bg-light rounded mb-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($intro_image); ?>" 
                                         class="img-thumbnail me-3" 
                                         style="width: 100px; height: 100px; object-fit: cover;"
                                         alt="Introduction image">
                                    <div>
                                        <p class="mb-1 fw-semibold">Current Image</p>
                                        <small class="text-muted"><?php echo basename($intro_image); ?></small>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($intro_image); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View Full
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               class="form-control" 
                               id="intro_image" 
                               name="intro_image" 
                               accept=".jpg,.jpeg,.png,.gif"
                               <?php echo empty($intro_image) ? 'required' : ''; ?>>
                        <div class="form-text">
                            <small><?php echo !empty($intro_image) ? 'Leave empty to keep current image.' : 'Upload an image for the introduction section'; ?> (JPG, PNG, GIF - max 5MB)</small>
                        </div>
                    </div>

                    <!-- Number of Cohorts -->
                    <div class="mb-4">
                        <label for="number_of_cohorts" class="form-label fw-bold">
                            <i class="bi bi-list-ol me-2 text-info"></i>Number of Cohorts
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="number_of_cohorts" 
                               name="number_of_cohorts" 
                               value="<?php echo htmlspecialchars($number_of_cohorts); ?>"
                               min="1" 
                               max="50"
                               placeholder="e.g., 3"
                               onchange="generateCohortImageFields()"
                               required>
                        <div class="form-text">
                            <small>Enter the total number of cohorts for this training</small>
                        </div>
                    </div>

                    <!-- Number of Professionals Trained -->
                    <div class="mb-4">
                        <label for="professionals_trained" class="form-label fw-bold">
                            <i class="bi bi-people me-2 text-success"></i>Number of Professionals Trained
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="professionals_trained" 
                               name="professionals_trained" 
                               value="<?php echo htmlspecialchars($professionals_trained); ?>"
                               min="1"
                               placeholder="e.g., 45"
                               required>
                        <div class="form-text">
                            <small>Enter the total number of professionals trained across all cohorts</small>
                        </div>
                    </div>

                    <!-- Cohort Images Section -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-images me-2 text-warning"></i>Cohort Images (One per Cohort)
                        </label>
                        <div id="cohortImagesContainer">
                            <?php if (!empty($cohort_images) && $number_of_cohorts > 0): ?>
                                <?php for ($i = 1; $i <= $number_of_cohorts; $i++): ?>
                                <div class="cohort-image-field mb-3 border p-3 rounded">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-circle-fill me-2" style="font-size: 8px;"></i>Cohort <?php echo $i; ?> Image
                                    </label>
                                    
                                    <?php if (!empty($cohort_images[$i-1])): ?>
                                    <div class="border p-2 bg-light rounded mb-2">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($cohort_images[$i-1]); ?>" 
                                                 class="img-thumbnail me-3" 
                                                 style="width: 80px; height: 80px; object-fit: cover;"
                                                 alt="Cohort <?php echo $i; ?> image">
                                            <div>
                                                <p class="mb-0 small fw-semibold">Current Image</p>
                                                <small class="text-muted"><?php echo basename($cohort_images[$i-1]); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="existing_cohort_image_<?php echo $i; ?>" value="<?php echo htmlspecialchars($cohort_images[$i-1]); ?>">
                                    <?php endif; ?>
                                    
                                    <input type="file" 
                                           class="form-control" 
                                           name="cohort_image_<?php echo $i; ?>" 
                                           accept=".jpg,.jpeg,.png,.gif"
                                           <?php echo empty($cohort_images[$i-1]) ? 'required' : ''; ?>>
                                    <small class="form-text text-muted">
                                        <?php echo !empty($cohort_images[$i-1]) ? 'Leave empty to keep current image.' : 'Upload image for Cohort ' . $i; ?>
                                    </small>
                                </div>
                                <?php endfor; ?>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Please enter the number of cohorts above to add images</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>Save Cohort Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function generateCohortImageFields() {
    const numberOfCohorts = parseInt(document.getElementById('number_of_cohorts').value) || 0;
    const container = document.getElementById('cohortImagesContainer');
    
    if (numberOfCohorts < 1) {
        container.innerHTML = '<p class="text-muted fst-italic">Please enter the number of cohorts above to add images</p>';
        return;
    }
    
    let html = '';
    for (let i = 1; i <= numberOfCohorts; i++) {
        html += `
            <div class="cohort-image-field mb-3 border p-3 rounded">
                <label class="form-label fw-semibold">
                    <i class="bi bi-circle-fill me-2" style="font-size: 8px;"></i>Cohort ${i} Image
                </label>
                <input type="file" 
                       class="form-control" 
                       name="cohort_image_${i}" 
                       accept=".jpg,.jpeg,.png,.gif"
                       required>
                <small class="form-text text-muted">Upload image for Cohort ${i} (JPG, PNG, GIF - max 5MB)</small>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Initialize on page load if editing
document.addEventListener('DOMContentLoaded', function() {
    const currentCohorts = parseInt(document.getElementById('number_of_cohorts').value) || 0;
    if (currentCohorts > 0) {
        // Fields already generated by PHP, no need to regenerate
    }
});
</script>

<!-- Button to trigger modal (example) -->
 <!--<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editEventModal">Edit Event</button> -->
                        
                        
                                <!-- Edit Modal -->
                <div class="modal fade" id="editEventModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                                    Edit 
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                               <form action="update_event.php" method="POST" enctype="multipart/form-data">
                                   <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                          <!-- step 1 -->
                                    <div class="step_one">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Upload Event Poster</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="box">
                                            <div class="js--image-preview"></div>
                                            <div class="upload-options">
                                                <label>
                                                    <input type="file" class="image-upload" accept="image/*" name="poster_image"  />
                                                </label>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" class="btn btn-secondary rounded-0" data-bs-dismiss="modal">
                                                        <i class="fa fa-times" aria-hidden="true"></i>
                                                        Cancel
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_two()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 1 -->

                                    <!-- step 2 -->
                                    <div class="step_two d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>


                                        <div class="text-uppercase mt-3">
                                            <b>Set Event Details</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Title</span>
                                            </div>
                                            <input type="text" name="event_title" class="form-control" placeholder="Enter title here" aria-label="title" value='<?php echo event_detail($conn,$event_id,"event_title"); ?>' aria-describedby="basic-addon1">
                                        </div>

                     
                            
                           
   
    <div class="form-group">
                                                        <label>Description</label>
                                                        <textarea rows="10" id="summernote" name="event_description" class="form-control description" placeholder="Enter event description..."><?php echo event_detail($conn,$event_id,"event_description"); ?></textarea>
                                                        
                                                    </div>
                                               

 <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_one()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_three()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 2 -->

                                    <!-- step 3 -->
                                    <div class="step_three d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Set Event Date and Location</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                            </div>
                                            <input name="start_on" type="datetime-local" value="<?php echo event_detail($conn,$event_id,"start_on") ?>"  class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                            </div>
                                            <input name="end_on"  type="datetime-local" value='<?php echo event_detail($conn,$event_id,"end_on") ?>' class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Location</span>
                                            </div>
                                            <input type="text" value='<?php echo event_detail($conn,$event_id,"location") ?>'  class="form-control" placeholder="Enter location here" aria-label="location" name="location" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Host</span>
                                            </div>
                                            <input type="text" value='<?php echo event_detail($conn,$event_id,"host") ?>'  class="form-control" placeholder="Enter host here" name="host"  aria-label="Host" aria-describedby="basic-addon1">
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_two()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_four()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 3 -->

                                    <!-- step 4 -->
                                    <div class="step_four d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Early Bird Ticket</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                            </div>
                                            <input name="early_start_on" type="datetime-local" value='<?php echo event_detail($conn,$event_id,"early_start_on") ?>'  class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                            </div>
                                            <input name="early_end_on" type="datetime-local" class="form-control" value='<?php echo event_detail($conn,$event_id,"early_end_on") ?>' aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount</span>
                                            </div>
                                            <input name="early_amount" type="number" value='<?php echo event_detail($conn,$event_id,"early_amount") ?>' class="form-control" placeholder="Enter amount here" aria-label="amount" aria-describedby="basic-addon1">
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_three()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_four1()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 4 -->

                                    <!-- step 4-1 -->
                                    <div class="step_four1 d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Advance Ticket</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                            </div>
                                            <input name="advance_start_on" type="datetime-local" class="form-control" value='<?php echo event_detail($conn,$event_id,"advance_start_on") ?>' aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                            </div>
                                            <input name="advance_end_on" type="datetime-local" value='<?php echo event_detail($conn,$event_id,"advance_end_on") ?>' class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount</span>
                                            </div>
                                            <input name="advance_amount" type="number" value='<?php echo event_detail($conn,$event_id,"advance_amount") ?>' class="form-control" placeholder="Enter amount here" aria-label="amount" aria-describedby="basic-addon1">
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_four()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_four2()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 4-1 -->

                                    <!-- step 4-2 -->
                                    <div class="step_four2 d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Gate Ticket</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                            </div>
                                            <input name="gate_start_on" type="datetime-local" value='<?php echo event_detail($conn,$event_id,"gate_start_on") ?>' class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                            </div>
                                            <input name="gate_end_on" type="datetime-local" value='<?php echo event_detail($conn,$event_id,"gate_end_on") ?>' class="form-control" aria-describedby="basic-addon1">
                                        </div>

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount</span>
                                            </div>
                                            <input name="gate_amount" type="number" value='<?php echo event_detail($conn,$event_id,"gate_amount") ?>' class="form-control" placeholder="Enter amount here" aria-label="amount" aria-describedby="basic-addon1">
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_four1()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="button" onclick="stage_four3()" class="btn btn-success rounded-0">
                                                        <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 4-2 -->

                                    <!-- step 4-3 -->
                                    <div class="step_four3 d-none">
                                        <div class="d-flex">
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-upload" aria-hidden="true"></i>
                                                Poster
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-exchange" aria-hidden="true"></i>
                                                Details
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                                Date
                                            </button>
                                            <button type="button" class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                                <i class="fa fa-ticket" aria-hidden="true"></i>
                                                Tickets
                                            </button>
                                        </div>

                                        <div class="text-uppercase mt-3">
                                            <b>Set Currency</b>
                                        </div>
                                        <hr class="mt-1 text-success border-3">

                                        <div class="input-group mb-3 mt-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Currency</span>
                                            </div>
                                            <input type="text" name="currency_code" class="form-control" value='<?php echo event_detail($conn,$event_id,"currency_code") ?>' placeholder="Enter currency here" aria-label="Currency" aria-describedby="basic-addon1">
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="row">
                                                <div class="col-md d-flex justify-content-start">
                                                    <button type="button" onclick="stage_four2()" class="btn btn-secondary rounded-0">
                                                        <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                                        Previous
                                                    </button>
                                                </div>
                                                <div class="col-md d-flex justify-content-end">
                                                    <button type="submit" class="btn btn-success rounded-0">
                                                        <i class="fa fa-check" aria-hidden="true"></i>
                                                        Finish
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- step 4-3 -->
                                </form>
                               
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Edit Modal -->
                
                            <?php /* updates handled by update_event.php */ ?>

                        <button class="btn btn-danger rounded-0 ms-1 me-1" data-bs-toggle="modal" data-bs-target="#deleteEventModal">
                            <i class="fa fa-trash"></i>
                            Delete
                        </button>
                        
                        
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                   <div class="w-100 ps-3 pe-3">
                <div class="row p-0">
                    <div class="col-md-4 m-0 p-0">
                        <div class="w-100 pt-0 pe-3 text-center text-uppercase ps-3">
                                            </div>

                        <hr class="mt-0 mb-1 text-warning border-3">

                    
                           <div class="card-body p-3">
                        <img width='300' height='300' src="https://vantageafricaleaders.com/admin/<?php echo event_detail($conn,$event_id,'poster_image'); ?>"  alt="Poster">
                    </div>
                        <div class="row p-0 mt-0 ms-2 shadow" style="max-width: 95%;">
                            <div class="col-md-4 p-1 bg-danger text-uppercase text-white text-center">
                                <b>
                                    <?php
                                
                                $dateString = event_detail($conn,$event_id,"start_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('D d M'); // Formats the date as "THUR 15 FEB"
echo strtoupper($formattedDateTime);
?>
                                </b>
                            </div>
                            <div class="col-md-8 d-flex align-items-center">
                                <h5 class="text-capitalize" style="font-size: 12px; font-weight: 800;">
                                  <?php echo event_detail($conn,$event_id,"event_title"); ?>
                    </h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 m-0 p-0">
                        <div class="w-100 pt-0 pe-3 text-center text-uppercase ps-3">
                            <b>
                                Event Details <?php echo event_detail($conn,$event_id,'event_title'); ?>
                            </b>
                        </div>

                        <hr class="mt-0 mb-1 text-warning border-3">

                        <table class="table table-striped table-borderless border-0">
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Title
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                  <?php echo event_detail($conn,$event_id,"event_title"); ?>
                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Start Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                   <?php
                                
                                $dateString = event_detail($conn,$event_id,"start_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('l, jS F Y H:i'); // Formats the date as "Thursday, 15th February 2024, 16:00"
echo $formattedDateTime;
?>                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    End Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                  <?php
                                
                                $dateString = event_detail($conn,$event_id,"end_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('l, jS F Y H:i'); // Formats the date as "Thursday, 15th February 2024, 16:00"
echo $formattedDateTime;
?>
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Location
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    <?php echo event_detail($conn,$event_id,"location") ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Host
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                     <?php echo event_detail($conn,$event_id,"host") ?>
                                </td>
                            </tr>
                        </table>
                        <div class="w-100 pt-0 pe-3 ps-3">
                            <b>
                                Description
                            </b>
                        </div>
                        <hr class="mt-0 mb-1 text-warning border-3">
                        <div style="text-align: justify;">
                           <?php echo event_detail($conn,$event_id,"event_description") ?>
                        </div>
                    </div>
                </div>

                <div class="row p-0">
                    <div class="w-100 pt-3 pe-3 text-center text-uppercase ps-3">
                        <b>
                            Ticket Details
                        </b>
                    </div>

                    <hr class="mt-0 mb-1 text-warning border-3">
                    <div class="col-md">
                        <div class="w-100 pt-1 pe-3 ps-3">
                            <b>
                                Early Bird Ticket
                            </b>
                        </div>
                        <hr class="mt-0 mb-1 text-success">

                        <table class="table table-striped table-borderless border-0">
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Start Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    End Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    <?php
                                
                                $dateString = event_detail($conn,$event_id,"early_end_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('l, jS F Y H:i'); // Formats the date as "Thursday, 15th February 2024, 16:00"
echo $formattedDateTime;
?>
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Amount
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    <?php echo event_detail($conn,$event_id,"currency_code")." ".number_format(event_detail($conn,$event_id,"early_amount") * event_detail($conn,$event_id,"rate"), 2, '.', ','); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md">
                        <div class="w-100 pt-1 pe-3 ps-3">
                            <b>
                                Advanced Ticket
                            </b>
                        </div>
                        <hr class="mt-0 mb-1 text-success">

                        <table class="table table-striped table-borderless border-0">
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Start Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                   <?php
                                
                                $dateString = event_detail($conn,$event_id,"advance_start_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('l, jS F Y H:i'); // Formats the date as "Thursday, 15th February 2024, 16:00"
echo $formattedDateTime;
?>
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    End Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Amount
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                   <?php echo event_detail($conn,$event_id,"currency_code")." ".number_format(event_detail($conn,$event_id,"advance_amount") * event_detail($conn,$event_id,"rate"), 2, '.', ','); ?>                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md">
                        <div class="w-100 pt-1 pe-3 ps-3">
                            <b>
                                Gate Ticket
                            </b>
                        </div>
                        <hr class="mt-0 mb-1 text-success">

                        <table class="table table-striped table-borderless border-0">
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Start Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    <?php
                                
                                $dateString = event_detail($conn,$event_id,"gate_start_on");
$dateTime = new DateTime($dateString);
$formattedDateTime = $dateTime->format('l, jS F Y H:i'); // Formats the date as "Thursday, 15th February 2024, 16:00"
echo $formattedDateTime;
?></td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    End Date
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                    <!--5 March, 2024-->
                                </td>
                            </tr>
                            <tr>
                                <td class="w-25" style="white-space: nowrap;">
                                    Amount
                                </td>
                                <td class="w-75" style="white-space: nowrap;">
                                   <?php echo event_detail($conn,$event_id,"currency_code")." ".number_format(event_detail($conn,$event_id,"gate_amount") * event_detail($conn,$event_id,"rate"), 2, '.', ','); ?>                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>
</section>
   <script>
        function initImageUpload(box) {
            let uploadField = box.querySelector('.image-upload');

            uploadField.addEventListener('change', getFile);

            function getFile(e) {
                let file = e.currentTarget.files[0];
                checkType(file);
            }

            function previewImage(file) {
                let thumb = box.querySelector('.js--image-preview'),
                    reader = new FileReader();

                reader.onload = function() {
                    thumb.style.backgroundImage = 'url(' + reader.result + ')';
                }
                reader.readAsDataURL(file);
                thumb.className += ' js--no-default';
            }

            function checkType(file) {
                let imageType = /image.*/;
                if (!file.type.match(imageType)) {
                    throw '1';
                } else if (!file) {
                    throw '2';
                } else {
                    previewImage(file);
                }
            }

        }

        var boxes = document.querySelectorAll('.box');

        for (let i = 0; i < boxes.length; i++) {
            let box = boxes[i];
            initDropEffect(box);
            initImageUpload(box);
        }

        function initDropEffect(box) {
            let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;

            area = box.querySelector('.js--image-preview');
            area.addEventListener('click', fireRipple);

            function fireRipple(e) {
                area = e.currentTarget
                if (!drop) {
                    drop = document.createElement('span');
                    drop.className = 'drop';
                    this.appendChild(drop);
                }
                drop.className = 'drop';

                areaWidth = getComputedStyle(this, null).getPropertyValue("width");
                areaHeight = getComputedStyle(this, null).getPropertyValue("height");
                maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));

                drop.style.width = maxDistance + 'px';
                drop.style.height = maxDistance + 'px';

                dropWidth = getComputedStyle(this, null).getPropertyValue("width");
                dropHeight = getComputedStyle(this, null).getPropertyValue("height");

                x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
                y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;

                drop.style.top = y + 'px';
                drop.style.left = x + 'px';
                drop.className += ' animate';
                e.stopPropagation();

            }
        }

        // Stages
        function stage_one() {
            $(".step_one").removeClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_two() {
            $(".step_one").addClass("d-none");
            $(".step_two").removeClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_three() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").removeClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").removeClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four1() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").removeClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four2() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").removeClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four3() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").removeClass("d-none");
        }
    </script>
<script>
    $(document).ready(function() {
        $('#summernote').summernote({
       height: 300,
       callbacks: {
           onChange:function(contents){
               $('#preview-body').html(contents);
           }
       }
  });
    });
</script>

<?php
require_once 'footer.php';
?>