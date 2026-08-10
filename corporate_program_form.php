<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once 'auth.php';
require_once 'includes/corporate_program_functions.php';

if (!in_array(88, $role) && !in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$program = $editId > 0 ? corporate_get_program($conn, $editId) : null;
if ($editId > 0 && !$program) {
    header('Location: corporate_programs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_corporate_program'])) {
    $data = corporate_collect_post_data();
    $curriculum = corporate_collect_curriculum_from_post();
    $lecturersDraft = corporate_collect_lecturers_from_post();
    $postId = isset($_POST['program_id']) ? (int) $_POST['program_id'] : 0;
    $uploadError = '';

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $uploadDir = __DIR__ . '/uploads';
    $ensureUploadDir = static function () use (&$uploadError, $uploadDir) {
        if ($uploadError !== '') {
            return false;
        }
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            $uploadError = 'Unable to create upload directory.';
            return false;
        }
        return true;
    };

    // Cover image upload → stored as admin/uploads/... (same convention as academic)
    if (isset($_FILES['program_image']) && is_array($_FILES['program_image'])) {
        $pf = $_FILES['program_image'];
        $fileError = isset($pf['error']) ? (int) $pf['error'] : UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadError = 'Cover image upload failed. Please try again.';
            } else {
                $ext = strtolower(pathinfo((string) $pf['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    $uploadError = 'Invalid cover image type. Allowed: jpg, jpeg, png, gif, webp.';
                } elseif (!is_uploaded_file((string) $pf['tmp_name'])) {
                    $uploadError = 'Invalid cover image file.';
                } elseif ($ensureUploadDir()) {
                    $newFileName = 'corporate_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file((string) $pf['tmp_name'], $uploadDir . '/' . $newFileName)) {
                        $uploadError = 'Could not save uploaded cover image.';
                    } else {
                        $data['image_url'] = 'admin/uploads/' . $newFileName;
                    }
                }
            }
        }
    }

    // Lecturer photo uploads (aligned by index with lecturer_* arrays)
    $lecturers = [];
    $photoFiles = (isset($_FILES['lecturer_photo']) && is_array($_FILES['lecturer_photo'])) ? $_FILES['lecturer_photo'] : null;
    foreach ($lecturersDraft as $i => $row) {
        $photoUrl = isset($row['existing_photo_url']) ? trim((string) $row['existing_photo_url']) : '';
        if ($photoFiles && isset($photoFiles['error'][$i])) {
            $fileError = (int) $photoFiles['error'][$i];
            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                if ($fileError !== UPLOAD_ERR_OK) {
                    $uploadError = 'Trainer photo upload failed. Please try again.';
                } else {
                    $ext = strtolower(pathinfo((string) $photoFiles['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions, true)) {
                        $uploadError = 'Invalid trainer photo type. Allowed: jpg, jpeg, png, gif, webp.';
                    } elseif (!is_uploaded_file((string) $photoFiles['tmp_name'][$i])) {
                        $uploadError = 'Invalid trainer photo file.';
                    } elseif ($ensureUploadDir()) {
                        $newFileName = 'trainer_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        if (!move_uploaded_file((string) $photoFiles['tmp_name'][$i], $uploadDir . '/' . $newFileName)) {
                            $uploadError = 'Could not save uploaded trainer photo.';
                        } else {
                            $photoUrl = 'admin/uploads/' . $newFileName;
                        }
                    }
                }
            }
        }
        $lecturers[] = [
            'photo_url' => $photoUrl,
            'name' => $row['name'],
            'title' => $row['title'],
            'description' => $row['description'],
            'qualifications' => $row['qualifications'],
        ];
    }

    if ($uploadError !== '') {
        $_SESSION['msg'] = $uploadError;
        header('Location: corporate_program_form.php' . ($postId > 0 ? '?id=' . $postId : ''));
        exit;
    }

    if ($postId > 0) {
        corporate_update_program($conn, $postId, $data, $curriculum, $lecturers);
    } else {
        corporate_create_program($conn, $data, $curriculum, $lecturers);
    }
    $_SESSION['msg'] = 'Success';
    header('Location: corporate_programs.php');
    exit;
}

require_once 'header.php';

$v = static function ($key, $default = '') use ($program) {
    if ($program === null) {
        return $default;
    }
    return isset($program[$key]) ? (string) $program[$key] : $default;
};

$coverPreviewSrc = $v('image_url');
if ($coverPreviewSrc !== '' && strncmp($coverPreviewSrc, 'admin/uploads/', 14) === 0) {
    $coverPreviewSrc = 'uploads/' . substr($coverPreviewSrc, 14);
}

$curriculumValues = [];
$lecturerValues = [];
if ($program) {
    foreach ($program['curriculum_rows'] as $row) {
        $curriculumValues[] = [
            'day_label' => isset($row['day_label']) ? $row['day_label'] : '',
            'module_name' => isset($row['module_name']) ? $row['module_name'] : '',
        ];
    }
    if (isset($program['lecturer_rows']) && is_array($program['lecturer_rows'])) {
        foreach ($program['lecturer_rows'] as $row) {
            $lecturerValues[] = [
                'photo_url' => isset($row['photo_url']) ? (string) $row['photo_url'] : '',
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'description' => isset($row['description']) ? (string) $row['description'] : '',
                'qualifications' => isset($row['qualifications']) ? (string) $row['qualifications'] : '',
            ];
        }
    }
}
if (empty($curriculumValues)) {
    $curriculumValues = [['day_label' => 'Day 1', 'module_name' => '']];
}
if (empty($lecturerValues)) {
    $lecturerValues = [['photo_url' => '', 'name' => '', 'title' => '', 'description' => '', 'qualifications' => '']];
}

// Helper to render a bullet-section textarea
$bulletBox = static function ($name, $label) use ($v) {
    echo '<div class="col-12"><label class="form-label">' . htmlspecialchars($label) . ' <span class="text-muted small">(one bullet per line)</span></label>'
        . '<textarea name="' . $name . '" class="form-control rounded-0" rows="5">' . htmlspecialchars($v($name)) . '</textarea></div>';
};
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 text-gray-800"><?php echo $program ? 'Edit corporate training' : 'Add corporate training'; ?></h1>
                <a href="corporate_programs.php" class="btn btn-outline-secondary rounded-0">Back to list</a>
            </div>

            <form method="post" action="" id="corporateProgramForm" enctype="multipart/form-data">
                <input type="hidden" name="save_corporate_program" value="1">
                <?php if ($program): ?>
                    <input type="hidden" name="program_id" value="<?php echo (int) $program['id']; ?>">
                <?php endif; ?>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Basic info</div>
                    <div class="card-body row g-3">
                        <div class="col-md-9">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control rounded-0" required value="<?php echo htmlspecialchars($v('title')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Accreditation tag</label>
                            <input type="text" name="accreditation" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('accreditation')); ?>" placeholder="e.g. NITA-Accredited">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tagline / hero subtitle</label>
                            <textarea name="tagline" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('tagline')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cover image</label>
                            <?php if ($coverPreviewSrc !== ''): ?>
                                <div class="mb-2"><img src="<?php echo htmlspecialchars($coverPreviewSrc); ?>" alt="" class="border" style="max-width: 320px; max-height: 200px; width: 100%; height: auto; object-fit: contain;"></div>
                            <?php endif; ?>
                            <label class="form-label small text-muted mb-0">Upload file (saved to admin/uploads/)</label>
                            <input type="file" name="program_image" class="form-control rounded-0" accept="image/jpeg,image/png,image/gif,image/webp">
                            <label class="form-label small text-muted mt-2 mb-0">Or image URL</label>
                            <input type="text" name="image_url" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('image_url')); ?>" placeholder="https://… or leave empty if you uploaded a file">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start date</label>
                            <input type="date" name="start_date" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('start_date')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End date</label>
                            <input type="date" name="end_date" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('end_date')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('duration')); ?>" placeholder="e.g. 3 Days">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mode</label>
                            <input type="text" name="mode" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('mode')); ?>" placeholder="In-person / Online / Hybrid">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('location')); ?>" placeholder="e.g. Dadaab, Garissa County">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Venue details</label>
                            <input type="text" name="venue_details" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('venue_details')); ?>" placeholder="Room / landmark / directions">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fee</label>
                            <input type="text" name="fee" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('fee')); ?>" placeholder="e.g. 49,500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fee unit</label>
                            <input type="text" name="fee_unit" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('fee_unit')); ?>" placeholder="e.g. per participant">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('currency', 'KES')); ?>" placeholder="KES">
                            <small class="text-muted">Used on the invoice the registrant receives.</small>
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
                        <div class="col-md-4">
                            <label class="form-label">Register link</label>
                            <input type="text" name="registration_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('registration_link', '#')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course outline link</label>
                            <input type="text" name="course_outline_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('course_outline_link')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Group / team rate link</label>
                            <input type="text" name="group_rate_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('group_rate_link')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact WhatsApp / phone</label>
                            <input type="text" name="contact_whatsapp" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('contact_whatsapp')); ?>" placeholder="+254…">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Overview &amp; featured solution</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Course overview</label>
                            <textarea name="overview" class="form-control rounded-0" rows="5"><?php echo htmlspecialchars($v('overview')); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Featured solution title</label>
                            <input type="text" name="featured_solution_title" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('featured_solution_title')); ?>" placeholder="e.g. Eval360 — Digital AI-Enabled MEAL System">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Featured solution intro</label>
                            <textarea name="featured_solution_text" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('featured_solution_text')); ?></textarea>
                        </div>
                        <?php $bulletBox('featured_solution_points', 'Featured solution points'); ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Sections (bulleted content)</div>
                    <div class="card-body row g-3">
                        <?php
                        $bulletBox('challenges', 'The challenge');
                        $bulletBox('why_solution', 'Why this training is the solution');
                        $bulletBox('features', 'Why this training (features)');
                        $bulletBox('gains', "What you'll gain");
                        $bulletBox('whats_included', "What's included");
                        $bulletBox('who_should_attend', 'Who should attend');
                        $bulletBox('why_vantage', 'Why train with Vantage');
                        ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Course outline (day-by-day)</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addCurriculumRow">+ Add item</button>
                    </div>
                    <div class="card-body" id="curriculumRows">
                        <?php foreach ($curriculumValues as $mod): ?>
                            <div class="input-group mb-2 curriculum-row">
                                <input type="text" name="day_label[]" class="form-control rounded-0" style="max-width: 160px;" value="<?php echo htmlspecialchars(isset($mod['day_label']) ? $mod['day_label'] : ''); ?>" placeholder="Day 1">
                                <input type="text" name="module_name[]" class="form-control rounded-0" value="<?php echo htmlspecialchars(isset($mod['module_name']) ? $mod['module_name'] : ''); ?>" placeholder="Session / topic">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-curriculum">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Trainers (shown on the detail page)</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addLecturerRow">+ Add trainer</button>
                    </div>
                    <div class="card-body" id="lecturerRows">
                        <?php foreach ($lecturerValues as $lec): ?>
                            <div class="border p-3 mb-3 lecturer-row">
                                <div class="row g-3 align-items-start">
                                    <div class="col-md-3">
                                        <label class="form-label">Photo</label>
                                        <?php if (!empty($lec['photo_url'])): ?>
                                            <?php $lecSrc = strncmp($lec['photo_url'], 'admin/uploads/', 14) === 0 ? 'uploads/' . substr($lec['photo_url'], 14) : $lec['photo_url']; ?>
                                            <div class="mb-2"><img src="<?php echo htmlspecialchars($lecSrc); ?>" alt="" style="width:100%;max-width:160px;aspect-ratio:1/1;object-fit:cover;border:1px solid #ddd;"></div>
                                        <?php endif; ?>
                                        <input type="hidden" name="lecturer_existing_photo[]" value="<?php echo htmlspecialchars($lec['photo_url']); ?>">
                                        <input type="file" name="lecturer_photo[]" class="form-control rounded-0" accept="image/*">
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="lecturer_name[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($lec['name']); ?>" placeholder="e.g. Dr. Benson Kiarie">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="lecturer_title[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($lec['title']); ?>" placeholder="e.g. Lead Trainer / CEO">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <textarea name="lecturer_description[]" class="form-control rounded-0" rows="3"><?php echo htmlspecialchars($lec['description']); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Qualifications</label>
                                                <textarea name="lecturer_qualifications[]" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($lec['qualifications']); ?></textarea>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="button" class="btn btn-outline-danger rounded-0 remove-lecturer">Remove trainer</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success rounded-0 px-4">Save</button>
                    <a href="corporate_programs.php" class="btn btn-secondary rounded-0">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="tplCurriculumRow">
    <div class="input-group mb-2 curriculum-row">
        <input type="text" name="day_label[]" class="form-control rounded-0" style="max-width: 160px;" value="" placeholder="Day 1">
        <input type="text" name="module_name[]" class="form-control rounded-0" value="" placeholder="Session / topic">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-curriculum">&times;</button>
    </div>
</template>
<template id="tplLecturerRow">
    <div class="border p-3 mb-3 lecturer-row">
        <div class="row g-3 align-items-start">
            <div class="col-md-3">
                <label class="form-label">Photo</label>
                <input type="hidden" name="lecturer_existing_photo[]" value="">
                <input type="file" name="lecturer_photo[]" class="form-control rounded-0" accept="image/*">
            </div>
            <div class="col-md-9">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="lecturer_name[]" class="form-control rounded-0" value="" placeholder="e.g. Dr. Benson Kiarie">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="lecturer_title[]" class="form-control rounded-0" value="" placeholder="e.g. Lead Trainer / CEO">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="lecturer_description[]" class="form-control rounded-0" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Qualifications</label>
                        <textarea name="lecturer_qualifications[]" class="form-control rounded-0" rows="2"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-outline-danger rounded-0 remove-lecturer">Remove trainer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.getElementById('addCurriculumRow').addEventListener('click', function() {
    document.getElementById('curriculumRows').appendChild(document.getElementById('tplCurriculumRow').content.cloneNode(true));
});
document.getElementById('addLecturerRow').addEventListener('click', function() {
    document.getElementById('lecturerRows').appendChild(document.getElementById('tplLecturerRow').content.cloneNode(true));
});
document.addEventListener('click', function(e) {
    var rc = e.target.closest('.remove-curriculum');
    if (rc) {
        var row = rc.closest('.curriculum-row');
        if (row && document.querySelectorAll('.curriculum-row').length > 1) row.remove();
        return;
    }
    var rl = e.target.closest('.remove-lecturer');
    if (rl) {
        var r3 = rl.closest('.lecturer-row');
        if (r3) r3.remove();
    }
});
</script>

<?php require_once 'footer.php'; ?>
