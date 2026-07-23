<?php
require_once 'auth.php';
require_once 'includes/free_session_functions.php';

if (!in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$session = $editId > 0 ? free_session_get($conn, $editId) : null;
if ($editId > 0 && !$session) {
    header('Location: free_sessions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_free_session'])) {
    $data = free_session_collect_post_data();
    $highlights = free_session_collect_rows_from_post('highlights');
    $outcomes = free_session_collect_rows_from_post('outcomes');
    $postId = isset($_POST['session_id']) ? (int) $_POST['session_id'] : 0;
    $uploadError = '';

    if (isset($_FILES['poster_upload']) && is_array($_FILES['poster_upload']) && isset($_FILES['poster_upload']['error'])) {
        $fileError = (int) $_FILES['poster_upload']['error'];
        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadError = 'Poster upload failed. Please try again.';
            } else {
                $originalName = isset($_FILES['poster_upload']['name']) ? (string) $_FILES['poster_upload']['name'] : '';
                $tmpName = isset($_FILES['poster_upload']['tmp_name']) ? (string) $_FILES['poster_upload']['tmp_name'] : '';
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($ext, $allowedExtensions, true)) {
                    $uploadError = 'Invalid poster type. Allowed: jpg, jpeg, png, gif, webp.';
                } elseif (!is_uploaded_file($tmpName)) {
                    $uploadError = 'Invalid upload file.';
                } else {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                        $uploadError = 'Unable to create upload directory.';
                    } else {
                        $newFileName = 'free_session_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $newFileName;
                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            $uploadError = 'Could not save uploaded poster.';
                        } else {
                            $data['poster_image'] = 'admin/uploads/' . $newFileName;
                        }
                    }
                }
            }
        }
    }
    if ($uploadError === '' && isset($_FILES['trainer_upload']) && is_array($_FILES['trainer_upload']) && isset($_FILES['trainer_upload']['error'])) {
        $fileError = (int) $_FILES['trainer_upload']['error'];
        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadError = 'Trainer photo upload failed. Please try again.';
            } else {
                $originalName = isset($_FILES['trainer_upload']['name']) ? (string) $_FILES['trainer_upload']['name'] : '';
                $tmpName = isset($_FILES['trainer_upload']['tmp_name']) ? (string) $_FILES['trainer_upload']['tmp_name'] : '';
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($ext, $allowedExtensions, true)) {
                    $uploadError = 'Invalid trainer photo type. Allowed: jpg, jpeg, png, gif, webp.';
                } elseif (!is_uploaded_file($tmpName)) {
                    $uploadError = 'Invalid trainer photo file.';
                } else {
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                        $uploadError = 'Unable to create upload directory.';
                    } else {
                        $newFileName = 'trainer_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $newFileName;
                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            $uploadError = 'Could not save uploaded trainer photo.';
                        } else {
                            $data['trainer_image'] = 'admin/uploads/' . $newFileName;
                        }
                    }
                }
            }
        }
    }

    if ($uploadError !== '') {
        $_SESSION['msg'] = $uploadError;
        $redirect = 'free_session_form.php';
        if ($postId > 0) {
            $redirect .= '?id=' . $postId;
        }
        header('Location: ' . $redirect);
        exit;
    }

    if ($postId > 0) {
        free_session_update($conn, $postId, $data, $highlights, $outcomes);
    } else {
        free_session_create($conn, $data, $highlights, $outcomes);
    }

    $_SESSION['msg'] = 'Success';
    header('Location: free_sessions.php');
    exit;
}

require_once 'header.php';

$v = static function ($key, $default = '') use ($session) {
    if ($session === null) {
        return $default;
    }
    return isset($session[$key]) ? (string) $session[$key] : $default;
};

$highlightValues = [];
$outcomeValues = [];
$galleryImageValues = [];
$sectionVisibilityValues = [
    'preview' => 1,
    'overview' => 1,
    'trainer' => 0,
    'highlights' => 1,
    'outcomes' => 1,
    'testimonial' => 0,
    'schedule' => 0,
    'gallery' => 0,
    'share' => 1,
    'countdown' => 1,
];
if ($session) {
    foreach ($session['highlight_rows'] as $row) {
        $highlightValues[] = $row['highlight_text'];
    }
    foreach ($session['outcome_rows'] as $row) {
        $outcomeValues[] = $row['outcome_text'];
    }
    if (!empty($session['gallery_image_rows']) && is_array($session['gallery_image_rows'])) {
        $galleryImageValues = $session['gallery_image_rows'];
    }
    if (!empty($session['section_visibility_map']) && is_array($session['section_visibility_map'])) {
        $sectionVisibilityValues = array_merge($sectionVisibilityValues, $session['section_visibility_map']);
    }
}
if (empty($highlightValues)) {
    $highlightValues = [''];
}
if (empty($outcomeValues)) {
    $outcomeValues = [''];
}
if (empty($galleryImageValues)) {
    $galleryImageValues = [
        'admin/uploads/gallery_cb0d6dd122c11214b32cc6666fffef8b.jpeg',
        'admin/uploads/gallery_73cf3e8d6dd0cee34e21e69f8a5466c6.jpeg',
        'admin/uploads/gallery_407d087d732d56789c39310908d615a9.jpeg',
        'admin/uploads/gallery_1216040c753a36bb401caace907fc395.jpeg',
        'admin/uploads/gallery_457212bbb8d233f10daf152d0367b19e.jpeg',
        'admin/uploads/gallery_c9ea2863dfea9a519e72fb7f4728a7f7.jpeg',
    ];
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 text-gray-800"><?php echo $session ? 'Edit free session' : 'Add free session'; ?></h1>
                <a href="free_sessions.php" class="btn btn-outline-secondary rounded-0">Back to list</a>
            </div>

            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="save_free_session" value="1">
                <?php if ($session): ?>
                    <input type="hidden" name="session_id" value="<?php echo (int) $session['id']; ?>">
                <?php endif; ?>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Basic information</div>
                    <div class="card-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control rounded-0" required value="<?php echo htmlspecialchars($v('title')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Session type</label>
                            <select name="session_type" id="sessionTypeSelect" class="form-select rounded-0">
                                <option value="international" <?php echo $v('session_type', 'international') === 'international' ? 'selected' : ''; ?>>International event free session</option>
                                <option value="virtual" <?php echo $v('session_type') === 'virtual' ? 'selected' : ''; ?>>Virtual course free session</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug (optional)</label>
                            <input type="text" name="slug" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('slug')); ?>" placeholder="auto-generated-if-empty">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hero badge text</label>
                            <input type="text" name="hero_badge" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('hero_badge', 'Free Session')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Poster image URL</label>
                            <input type="text" name="poster_image" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('poster_image')); ?>">
                            <small class="text-muted d-block mt-1">You can paste a URL or upload a file below.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Upload poster image</label>
                            <input type="file" name="poster_upload" class="form-control rounded-0" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                            <small class="text-muted d-block mt-1">Uploaded files are stored in <code>admin/uploads/</code> and linked automatically.</small>
                            <?php if ($v('poster_image') !== ''): ?>
                                <small class="d-block mt-1">Current: <a href="../<?php echo htmlspecialchars($v('poster_image')); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($v('poster_image')); ?></a></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short description</label>
                            <textarea name="short_description" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('short_description')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full description</label>
                            <textarea name="full_description" class="form-control rounded-0" rows="8"><?php echo htmlspecialchars($v('full_description')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Schedule and delivery</div>
                    <div class="card-body row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start date/time</label>
                            <input type="datetime-local" name="start_on" class="form-control rounded-0" value="<?php echo !empty($v('start_on')) ? htmlspecialchars(substr($v('start_on'), 0, 16)) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End date/time</label>
                            <input type="datetime-local" name="end_on" class="form-control rounded-0" value="<?php echo !empty($v('end_on')) ? htmlspecialchars(substr($v('end_on'), 0, 16)) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mode label</label>
                            <input type="text" name="mode_label" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('mode_label')); ?>" placeholder="Online / Hybrid / In-person">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Timezone label</label>
                            <input type="text" name="timezone_label" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('timezone_label')); ?>" placeholder="EAT (Nairobi)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('location')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('sort_order', '0')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select rounded-0">
                                <?php foreach (['active', 'inactive', 'draft'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $v('status', 'active') === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0" id="internationalFieldsCard">
                    <div class="card-header bg_main text-white rounded-0 py-2">International registration settings</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Registration button label</label>
                            <input type="text" name="registration_label" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('registration_label', 'Register for free')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Linked Event ID (optional)</label>
                            <input type="number" name="event_reference_id" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('event_reference_id', '0')); ?>">
                            <small class="text-muted">Optional fallback for legacy event integrations.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Registration note</label>
                            <textarea name="registration_cta_note" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('registration_cta_note')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0" id="virtualFieldsCard">
                    <div class="card-header bg_main text-white rounded-0 py-2">Virtual free session CTA</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Virtual CTA label</label>
                            <input type="text" name="virtual_cta_label" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('virtual_cta_label', 'Watch training videos')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Virtual CTA link</label>
                            <input type="text" name="virtual_cta_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('virtual_cta_link', 'trainings/videos.php')); ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Page sections visibility</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[preview]" id="showPreview" <?php echo !empty($sectionVisibilityValues['preview']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showPreview">Show preview section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[overview]" id="showOverview" <?php echo !empty($sectionVisibilityValues['overview']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showOverview">Show overview section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[trainer]" id="showTrainer" <?php echo !empty($sectionVisibilityValues['trainer']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showTrainer">Show lead trainer section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[highlights]" id="showHighlights" <?php echo !empty($sectionVisibilityValues['highlights']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showHighlights">Show highlights section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[outcomes]" id="showOutcomes" <?php echo !empty($sectionVisibilityValues['outcomes']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showOutcomes">Show outcomes section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[testimonial]" id="showTestimonial" <?php echo !empty($sectionVisibilityValues['testimonial']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showTestimonial">Show testimonial section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[schedule]" id="showSchedule" <?php echo !empty($sectionVisibilityValues['schedule']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showSchedule">Show schedule section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[gallery]" id="showGallery" <?php echo !empty($sectionVisibilityValues['gallery']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showGallery">Show gallery section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[share]" id="showShare" <?php echo !empty($sectionVisibilityValues['share']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showShare">Show share section</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="section_visibility[countdown]" id="showCountdown" <?php echo !empty($sectionVisibilityValues['countdown']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="showCountdown">Show countdown section</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Page media and links</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Preview media type</label>
                            <select name="preview_media_type" class="form-select rounded-0">
                                <option value="poster" <?php echo $v('preview_media_type', 'poster') === 'poster' ? 'selected' : ''; ?>>Poster image</option>
                                <option value="video" <?php echo $v('preview_media_type') === 'video' ? 'selected' : ''; ?>>Video link</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Preview video link (YouTube or embed URL)</label>
                            <input type="text" name="preview_video_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('preview_video_link', 'https://www.youtube.com/watch?v=ysz5S6PUM-U')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Testimonial video link</label>
                            <input type="text" name="testimonial_video_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('testimonial_video_link', 'https://www.youtube.com/watch?v=ysz5S6PUM-U')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Schedule file URL/PDF path</label>
                            <input type="text" name="schedule_file" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('schedule_file', 'assets/training/placeholder_panels/free program flier.jpeg')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Share poster image URL</label>
                            <input type="text" name="share_image" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('share_image', 'assets/training/placeholder_panels/free program flier.jpeg')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lead trainer photo URL</label>
                            <input type="text" name="trainer_image" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('trainer_image', 'assets/logo.png')); ?>">
                            <small class="text-muted d-block mt-1">You can paste a URL or upload a photo below.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Upload lead trainer photo</label>
                            <input type="file" name="trainer_upload" class="form-control rounded-0" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                            <?php if ($v('trainer_image') !== ''): ?>
                                <small class="d-block mt-1">Current: <a href="../<?php echo htmlspecialchars($v('trainer_image')); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($v('trainer_image')); ?></a></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lead trainer description</label>
                            <textarea name="trainer_description" class="form-control rounded-0" rows="3"><?php echo htmlspecialchars($v('trainer_description', 'Our lead trainer is an experienced practitioner who combines real-world expertise with practical, action-oriented coaching.')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Zoom meeting details</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Zoom topic</label>
                            <input type="text" name="zoom_topic" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_topic', $v('title'))); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zoom date</label>
                            <input type="text" name="zoom_date" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_date', !empty($v('start_on')) ? date('jS F Y', strtotime($v('start_on'))) : '')); ?>" placeholder="e.g. 26th March 2026">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zoom time</label>
                            <input type="text" name="zoom_time" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_time', !empty($v('start_on')) ? date('g:i A T', strtotime($v('start_on'))) : '')); ?>" placeholder="e.g. 7:30 PM EAT">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zoom link</label>
                            <input type="text" name="zoom_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_link')); ?>" placeholder="https://...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Meeting ID</label>
                            <input type="text" name="zoom_meeting_id" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_meeting_id')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Passcode</label>
                            <input type="text" name="zoom_passcode" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('zoom_passcode')); ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Gallery images</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addGalleryRow">+ Add image</button>
                    </div>
                    <div class="card-body" id="galleryRows">
                        <?php foreach ($galleryImageValues as $img): ?>
                            <div class="input-group mb-2 gallery-row">
                                <input type="text" name="gallery_images[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($img); ?>" placeholder="Image URL or relative path">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-gallery">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Session highlights</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addHighlightRow">+ Add highlight</button>
                    </div>
                    <div class="card-body" id="highlightRows">
                        <?php foreach ($highlightValues as $text): ?>
                            <div class="input-group mb-2 highlight-row">
                                <input type="text" name="highlights[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($text); ?>" placeholder="Highlight item">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-highlight">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Learning outcomes</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addOutcomeRow">+ Add outcome</button>
                    </div>
                    <div class="card-body" id="outcomeRows">
                        <?php foreach ($outcomeValues as $text): ?>
                            <div class="input-group mb-2 outcome-row">
                                <input type="text" name="outcomes[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($text); ?>" placeholder="Outcome item">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-outcome">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success rounded-0 px-4">Save</button>
                    <a href="free_sessions.php" class="btn btn-secondary rounded-0">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="tplHighlightRow">
    <div class="input-group mb-2 highlight-row">
        <input type="text" name="highlights[]" class="form-control rounded-0" value="" placeholder="Highlight item">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-highlight">&times;</button>
    </div>
</template>
<template id="tplOutcomeRow">
    <div class="input-group mb-2 outcome-row">
        <input type="text" name="outcomes[]" class="form-control rounded-0" value="" placeholder="Outcome item">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-outcome">&times;</button>
    </div>
</template>
<template id="tplGalleryRow">
    <div class="input-group mb-2 gallery-row">
        <input type="text" name="gallery_images[]" class="form-control rounded-0" value="" placeholder="Image URL or relative path">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-gallery">&times;</button>
    </div>
</template>

<script>
function toggleTypeCards() {
    var type = document.getElementById('sessionTypeSelect').value;
    var internationalCard = document.getElementById('internationalFieldsCard');
    var virtualCard = document.getElementById('virtualFieldsCard');
    if (type === 'international') {
        internationalCard.style.display = '';
        virtualCard.style.display = 'none';
    } else {
        internationalCard.style.display = 'none';
        virtualCard.style.display = '';
    }
}

document.getElementById('addHighlightRow').addEventListener('click', function() {
    var tpl = document.getElementById('tplHighlightRow');
    document.getElementById('highlightRows').appendChild(tpl.content.cloneNode(true));
});
document.getElementById('addOutcomeRow').addEventListener('click', function() {
    var tpl = document.getElementById('tplOutcomeRow');
    document.getElementById('outcomeRows').appendChild(tpl.content.cloneNode(true));
});
document.getElementById('addGalleryRow').addEventListener('click', function() {
    var tpl = document.getElementById('tplGalleryRow');
    document.getElementById('galleryRows').appendChild(tpl.content.cloneNode(true));
});
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-highlight')) {
        var row = e.target.closest('.highlight-row');
        if (row && document.querySelectorAll('.highlight-row').length > 1) {
            row.remove();
        }
    }
    if (e.target.classList.contains('remove-outcome')) {
        var row2 = e.target.closest('.outcome-row');
        if (row2 && document.querySelectorAll('.outcome-row').length > 1) {
            row2.remove();
        }
    }
    if (e.target.classList.contains('remove-gallery')) {
        var row3 = e.target.closest('.gallery-row');
        if (row3 && document.querySelectorAll('.gallery-row').length > 1) {
            row3.remove();
        }
    }
});
document.getElementById('sessionTypeSelect').addEventListener('change', toggleTypeCards);
toggleTypeCards();
</script>

<?php require_once 'footer.php'; ?>
