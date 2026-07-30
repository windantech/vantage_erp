<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require_once 'auth.php';
require_once 'includes/academic_program_functions.php';

if (!in_array(55, $role) && !in_array(777, $role)) {
    header('Location: ./');
    exit;
}

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$program = $editId > 0 ? academic_get_program($conn, $editId) : null;
if ($editId > 0 && !$program) {
    header('Location: academic_programs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_academic_program'])) {
    $data = academic_collect_post_data();
    $curriculum = academic_collect_curriculum_from_post();
    $outcomes = academic_collect_outcomes_from_post();
    $lecturersDraft = academic_collect_lecturers_from_post();
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

    // Program cover image upload → stored path used on public site as admin/uploads/...
    if (isset($_FILES['program_image']) && is_array($_FILES['program_image'])) {
        $pf = $_FILES['program_image'];
        $fileError = isset($pf['error']) ? (int) $pf['error'] : UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadError = 'Program image upload failed. Please try again.';
            } else {
                $originalName = isset($pf['name']) ? (string) $pf['name'] : '';
                $tmpName = isset($pf['tmp_name']) ? (string) $pf['tmp_name'] : '';
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    $uploadError = 'Invalid program image type. Allowed: jpg, jpeg, png, gif, webp.';
                } elseif (!is_uploaded_file($tmpName)) {
                    $uploadError = 'Invalid program image file.';
                } else {
                    if ($ensureUploadDir()) {
                        $newFileName = 'program_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        $targetPath = $uploadDir . '/' . $newFileName;
                        if (!move_uploaded_file($tmpName, $targetPath)) {
                            $uploadError = 'Could not save uploaded program image.';
                        } else {
                            $data['image_url'] = 'admin/uploads/' . $newFileName;
                        }
                    }
                }
            }
        }
    }

    // Resolve lecturer photo uploads (align by index with lecturer_* arrays)
    $lecturers = [];

    // PHP file arrays for multiple uploads: name/tmp_name/error are arrays
    $photoFiles = isset($_FILES['lecturer_photo']) && is_array($_FILES['lecturer_photo']) ? $_FILES['lecturer_photo'] : null;
    foreach ($lecturersDraft as $i => $row) {
        $name = isset($row['name']) ? trim((string) $row['name']) : '';
        $title = isset($row['title']) ? trim((string) $row['title']) : '';
        $description = isset($row['description']) ? trim((string) $row['description']) : '';
        $qualifications = isset($row['qualifications']) ? trim((string) $row['qualifications']) : '';
        $existingPhoto = isset($row['existing_photo_url']) ? trim((string) $row['existing_photo_url']) : '';

        $photoUrl = $existingPhoto;
        if ($photoFiles && isset($photoFiles['error']) && isset($photoFiles['error'][$i])) {
            $fileError = (int) $photoFiles['error'][$i];
            if ($fileError !== UPLOAD_ERR_NO_FILE) {
                if ($fileError !== UPLOAD_ERR_OK) {
                    $uploadError = 'Lecturer photo upload failed. Please try again.';
                } else {
                    $originalName = isset($photoFiles['name'][$i]) ? (string) $photoFiles['name'][$i] : '';
                    $tmpName = isset($photoFiles['tmp_name'][$i]) ? (string) $photoFiles['tmp_name'][$i] : '';
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions, true)) {
                        $uploadError = 'Invalid lecturer photo type. Allowed: jpg, jpeg, png, gif, webp.';
                    } elseif (!is_uploaded_file($tmpName)) {
                        $uploadError = 'Invalid lecturer photo file.';
                    } else {
                        if ($ensureUploadDir()) {
                            $newFileName = 'lecturer_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                            $targetPath = $uploadDir . '/' . $newFileName;
                            if (!move_uploaded_file($tmpName, $targetPath)) {
                                $uploadError = 'Could not save uploaded lecturer photo.';
                            } else {
                                $photoUrl = 'admin/uploads/' . $newFileName;
                            }
                        }
                    }
                }
            }
        }

        // Keep row even without photo; name required at save time in DB helper
        $lecturers[] = [
            'photo_url' => $photoUrl,
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'qualifications' => $qualifications,
        ];
    }

    if ($uploadError !== '') {
        $_SESSION['msg'] = $uploadError;
        $redirect = 'academic_program_form.php';
        if ($postId > 0) {
            $redirect .= '?id=' . $postId;
        }
        header('Location: ' . $redirect);
        exit;
    }

    if ($postId > 0) {
        academic_update_program($conn, $postId, $data, $curriculum, $outcomes, $lecturers);
    } else {
        academic_create_program($conn, $data, $curriculum, $outcomes, $lecturers);
    }
    $_SESSION['msg'] = 'Success';
    header('Location: academic_programs.php');
    exit;
}

require_once 'header.php';

$v = static function ($key, $default = '') use ($program) {
    if ($program === null) {
        return $default;
    }
    return isset($program[$key]) ? (string) $program[$key] : $default;
};

$programImagePreviewSrc = $v('image_url');
if ($programImagePreviewSrc !== '' && strncmp($programImagePreviewSrc, 'admin/uploads/', 14) === 0) {
    $programImagePreviewSrc = 'uploads/' . substr($programImagePreviewSrc, 14);
}

$curriculumValues = [];
$outcomeValues = [];
$lecturerValues = [];
if ($program) {
    foreach ($program['curriculum_rows'] as $row) {
        $curriculumValues[] = array(
            'module_name' => isset($row['module_name']) ? $row['module_name'] : '',
            'curriculum_tier' => isset($row['curriculum_tier']) ? $row['curriculum_tier'] : 'foundational'
        );
    }
    foreach ($program['outcome_rows'] as $row) {
        $outcomeValues[] = $row['outcome_text'];
    }
    if (isset($program['lecturer_rows']) && is_array($program['lecturer_rows'])) {
        foreach ($program['lecturer_rows'] as $row) {
            $lecturerValues[] = array(
                'photo_url' => isset($row['photo_url']) ? (string) $row['photo_url'] : '',
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'description' => isset($row['description']) ? (string) $row['description'] : '',
                'qualifications' => isset($row['qualifications']) ? (string) $row['qualifications'] : ''
            );
        }
    }
}
if (empty($curriculumValues)) {
    $curriculumValues = array(
        array(
            'module_name' => '',
            'curriculum_tier' => 'foundational'
        )
    );
}
if (empty($outcomeValues)) {
    $outcomeValues = [''];
}
if (empty($lecturerValues)) {
    $lecturerValues = array(
        array(
            'photo_url' => '',
            'name' => '',
            'title' => '',
            'description' => '',
            'qualifications' => ''
        )
    );
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>

        <div class="container-fluid mt-5 pt-5 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h4 mb-0 text-gray-800"><?php echo $program ? 'Edit program' : 'Add program'; ?></h1>
                <a href="academic_programs.php" class="btn btn-outline-secondary rounded-0">Back to list</a>
            </div>

            <form method="post" action="" id="academicProgramForm" enctype="multipart/form-data">
                <input type="hidden" name="save_academic_program" value="1">
                <?php if ($program): ?>
                    <input type="hidden" name="program_id" value="<?php echo (int) $program['id']; ?>">
                <?php endif; ?>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Basic info</div>
                    <div class="card-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control rounded-0" required value="<?php echo htmlspecialchars($v('title')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Program type</label>
                            <select name="program_type" class="form-select rounded-0">
                                <option value="certificate" <?php echo $v('program_type', 'certificate') === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                                <option value="diploma" <?php echo $v('program_type') === 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Program image</label>
                            <?php if ($programImagePreviewSrc !== ''): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($programImagePreviewSrc); ?>" alt="" class="border" style="max-width: 320px; max-height: 200px; width: 100%; height: auto; object-fit: contain;">
                                </div>
                            <?php endif; ?>
                            <label class="form-label small text-muted mb-0">Upload file (saved to admin/uploads/)</label>
                            <input type="file" name="program_image" class="form-control rounded-0" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Optional. Allowed: jpg, jpeg, png, gif, webp. Replaces the image below when provided.</div>
                            <label class="form-label small text-muted mt-2 mb-0">Or image URL</label>
                            <input type="text" name="image_url" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('image_url')); ?>" placeholder="https://… or leave empty if you uploaded a file">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration</label>
                            <input type="text" name="duration" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('duration')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mode</label>
                            <input type="text" name="mode" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('mode')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('sort_order', '0')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fee</label>
                            <input type="text" name="fee" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('fee')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select rounded-0">
                                <?php foreach (['active', 'inactive', 'draft'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $v('status', 'active') === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Registration link</label>
                            <input type="text" name="registration_link" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('registration_link', '#')); ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Entry &amp; certification</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Entry requirements</label>
                            <textarea name="entry_requirements" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('entry_requirements')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Certification (summary)</label>
                            <textarea name="certification" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('certification')); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Issuing institution</label>
                            <textarea name="issuing_institution" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('issuing_institution')); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Accreditation basis</label>
                            <textarea name="accreditation_basis" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('accreditation_basis')); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Professional alignment</label>
                            <textarea name="professional_alignment" class="form-control rounded-0" rows="2"><?php echo htmlspecialchars($v('professional_alignment')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Governing body (certificates)</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Short name</label>
                            <input type="text" name="governing_body_name" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('governing_body_name')); ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Full name</label>
                            <input type="text" name="governing_body_full_name" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('governing_body_full_name')); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Logo URL</label>
                            <input type="url" name="governing_body_logo_url" class="form-control rounded-0" value="<?php echo htmlspecialchars($v('governing_body_logo_url')); ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2">Content</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Challenge / market problem</label>
                            <textarea name="market_problem" class="form-control rounded-0" rows="6"><?php echo htmlspecialchars($v('market_problem')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Solution</label>
                            <textarea name="solution" class="form-control rounded-0" rows="6"><?php echo htmlspecialchars($v('solution')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Who this program is for (one bullet per line)</label>
                            <textarea name="who_for" class="form-control rounded-0" rows="6"><?php echo htmlspecialchars($v('who_for')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">What makes this different (one bullet per line)</label>
                            <textarea name="what_different" class="form-control rounded-0" rows="6"><?php echo htmlspecialchars($v('what_different')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Trainer profile</label>
                            <textarea name="trainer_profile" class="form-control rounded-0" rows="3"><?php echo htmlspecialchars($v('trainer_profile')); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Fees &amp; intakes text</label>
                            <textarea name="fees_intakes" class="form-control rounded-0" rows="3"><?php echo htmlspecialchars($v('fees_intakes')); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Curriculum modules</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addCurriculumRow">+ Add module</button>
                    </div>
                    <div class="card-body" id="curriculumRows">
                        <?php foreach ($curriculumValues as $i => $mod): ?>
                            <div class="input-group mb-2 curriculum-row">
                                <select name="curriculum_tier[]" class="form-select rounded-0" style="max-width: 220px;">
                                    <option value="foundational" <?php echo ((isset($mod['curriculum_tier']) ? $mod['curriculum_tier'] : 'foundational') === 'foundational') ? 'selected' : ''; ?>>Foundational level</option>
                                    <option value="intermediate" <?php echo ((isset($mod['curriculum_tier']) ? $mod['curriculum_tier'] : '') === 'intermediate') ? 'selected' : ''; ?>>Intermediate level</option>
                                    <option value="advanced" <?php echo ((isset($mod['curriculum_tier']) ? $mod['curriculum_tier'] : '') === 'advanced') ? 'selected' : ''; ?>>Advanced level</option>
                                </select>
                                <input type="text" name="curriculum[]" class="form-control rounded-0" value="<?php echo htmlspecialchars(isset($mod['module_name']) ? $mod['module_name'] : ''); ?>" placeholder="Module name">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-curriculum">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Program outcomes</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addOutcomeRow">+ Add outcome</button>
                    </div>
                    <div class="card-body" id="outcomeRows">
                        <?php foreach ($outcomeValues as $out): ?>
                            <div class="input-group mb-2 outcome-row">
                                <input type="text" name="outcomes[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($out); ?>" placeholder="Outcome">
                                <button type="button" class="btn btn-outline-danger rounded-0 remove-outcome">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card shadow mb-4 rounded-0">
                    <div class="card-header bg_main text-white rounded-0 py-2 d-flex justify-content-between align-items-center">
                        <span>Lecturers / trainers (shown on program detail page)</span>
                        <button type="button" class="btn btn-sm btn-light rounded-0" id="addLecturerRow">+ Add lecturer</button>
                    </div>
                    <div class="card-body" id="lecturerRows">
                        <?php foreach ($lecturerValues as $lx => $lec): ?>
                            <div class="border p-3 mb-3 lecturer-row">
                                <div class="row g-3 align-items-start">
                                    <div class="col-md-3">
                                        <label class="form-label">Profile photo</label>
                                        <?php if (!empty($lec['photo_url'])): ?>
                                            <div class="mb-2">
                                                <img src="<?php echo htmlspecialchars($lec['photo_url']); ?>" alt="" style="width:100%;max-width:160px;aspect-ratio:1/1;object-fit:cover;border:1px solid #ddd;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="hidden" name="lecturer_existing_photo[]" value="<?php echo htmlspecialchars($lec['photo_url']); ?>">
                                        <input type="file" name="lecturer_photo[]" class="form-control rounded-0" accept="image/*">
                                        <div class="form-text">Allowed: jpg, jpeg, png, gif, webp</div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="lecturer_name[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($lec['name']); ?>" placeholder="e.g. Dr. Jane Doe">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Title</label>
                                                <input type="text" name="lecturer_title[]" class="form-control rounded-0" value="<?php echo htmlspecialchars($lec['title']); ?>" placeholder="e.g. Senior Lecturer / Industry Expert">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description</label>
                                                <textarea name="lecturer_description[]" class="form-control rounded-0" rows="3" placeholder="Short profile summary"><?php echo htmlspecialchars($lec['description']); ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Qualifications</label>
                                                <textarea name="lecturer_qualifications[]" class="form-control rounded-0" rows="2" placeholder="One per line or comma-separated"><?php echo htmlspecialchars($lec['qualifications']); ?></textarea>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="button" class="btn btn-outline-danger rounded-0 remove-lecturer">Remove lecturer</button>
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
                    <a href="academic_programs.php" class="btn btn-secondary rounded-0">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<template id="tplCurriculumRow">
    <div class="input-group mb-2 curriculum-row">
        <select name="curriculum_tier[]" class="form-select rounded-0" style="max-width: 220px;">
            <option value="foundational" selected>Foundational level</option>
            <option value="intermediate">Intermediate level</option>
            <option value="advanced">Advanced level</option>
        </select>
        <input type="text" name="curriculum[]" class="form-control rounded-0" value="" placeholder="Module name">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-curriculum">&times;</button>
    </div>
</template>
<template id="tplOutcomeRow">
    <div class="input-group mb-2 outcome-row">
        <input type="text" name="outcomes[]" class="form-control rounded-0" value="" placeholder="Outcome">
        <button type="button" class="btn btn-outline-danger rounded-0 remove-outcome">&times;</button>
    </div>
</template>
<template id="tplLecturerRow">
    <div class="border p-3 mb-3 lecturer-row">
        <div class="row g-3 align-items-start">
            <div class="col-md-3">
                <label class="form-label">Profile photo</label>
                <input type="hidden" name="lecturer_existing_photo[]" value="">
                <input type="file" name="lecturer_photo[]" class="form-control rounded-0" accept="image/*">
                <div class="form-text">Allowed: jpg, jpeg, png, gif, webp</div>
            </div>
            <div class="col-md-9">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="lecturer_name[]" class="form-control rounded-0" value="" placeholder="e.g. Dr. Jane Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="lecturer_title[]" class="form-control rounded-0" value="" placeholder="e.g. Senior Lecturer / Industry Expert">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="lecturer_description[]" class="form-control rounded-0" rows="3" placeholder="Short profile summary"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Qualifications</label>
                        <textarea name="lecturer_qualifications[]" class="form-control rounded-0" rows="2" placeholder="One per line or comma-separated"></textarea>
                    </div>
                    <div class="col-12 text-end">
                        <button type="button" class="btn btn-outline-danger rounded-0 remove-lecturer">Remove lecturer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.getElementById('addCurriculumRow').addEventListener('click', function() {
    var t = document.getElementById('tplCurriculumRow');
    document.getElementById('curriculumRows').appendChild(t.content.cloneNode(true));
});
document.getElementById('addOutcomeRow').addEventListener('click', function() {
    var t = document.getElementById('tplOutcomeRow');
    document.getElementById('outcomeRows').appendChild(t.content.cloneNode(true));
});
document.getElementById('addLecturerRow').addEventListener('click', function() {
    var t = document.getElementById('tplLecturerRow');
    document.getElementById('lecturerRows').appendChild(t.content.cloneNode(true));
});
document.addEventListener('click', function(e) {
    // Use closest() so a click anywhere on the button (or an inner node) still matches.
    var rc = e.target.closest('.remove-curriculum');
    if (rc) {
        var row = rc.closest('.curriculum-row');
        if (row && document.querySelectorAll('.curriculum-row').length > 1) row.remove();
        return;
    }
    var ro = e.target.closest('.remove-outcome');
    if (ro) {
        var r2 = ro.closest('.outcome-row');
        if (r2 && document.querySelectorAll('.outcome-row').length > 1) r2.remove();
        return;
    }
    var rl = e.target.closest('.remove-lecturer');
    if (rl) {
        // Lecturers are optional — allow removing every row, including the last one.
        var r3 = rl.closest('.lecturer-row');
        if (r3) r3.remove();
    }
});
</script>

<?php require_once 'footer.php'; ?>
