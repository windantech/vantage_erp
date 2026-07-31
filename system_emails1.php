<?php
require_once 'header.php';

// ---- Read filter selections from the URL (server-side filtering) ----
$f_type   = isset($_GET['f_type'])   ? trim($_GET['f_type'])   : 'all';     // all | virtual | international | academic
$f_course = isset($_GET['f_course']) ? trim($_GET['f_course']) : '';        // course_id
$f_event  = isset($_GET['f_event'])  ? trim($_GET['f_event'])  : '';        // event_id (international)
$f_acad   = isset($_GET['f_acad'])   ? trim($_GET['f_acad'])   : '';        // event_id (academic)

// Normalise
if (!in_array($f_type, ['all', 'virtual', 'international', 'academic'], true)) {
    $f_type = 'all';
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';

        // ---- Build the distinct Course / Event lists that ACTUALLY appear in
        //      the system_emails1 table (with their IDs, for accurate filtering) ----
        $course_options = []; // course_id => course name
        $event_options  = []; // event_id  => event title (international)
        $acad_options   = []; // event_id  => programme title (academic)

        $opt_q = $conn->query("SELECT DISTINCT se.email_type, se.event_id,
                    c.course AS course_name,
                    e.event_title AS event_name
                    FROM system_emails1 se
                    LEFT JOIN course c ON se.event_id = c.course_id AND (se.email_type = 'virtual' OR se.email_type IS NULL OR se.email_type = '')
                    LEFT JOIN Event e ON se.event_id = e.event_id AND se.email_type IN ('international','academic')");
        if ($opt_q) {
            while ($o = $opt_q->fetch_assoc()) {
                $etype = (!empty($o['email_type'])) ? $o['email_type'] : 'virtual';
                $eid   = trim((string)$o['event_id']);
                if ($eid === '') continue;
                if ($etype === 'international') {
                    if (!empty($o['event_name'])) $event_options[$eid] = $o['event_name'];
                } elseif ($etype === 'academic') {
                    if (!empty($o['event_name'])) $acad_options[$eid] = $o['event_name'];
                } else {
                    if (!empty($o['course_name'])) $course_options[$eid] = $o['course_name'];
                }
            }
        }
        asort($course_options);
        asort($event_options);
        asort($acad_options);

        // ---- Full active course & event lists for the Duplicate picker ----
        $all_courses = [];
        $cq = $conn->query("SELECT course_id, course FROM course WHERE status = 1 ORDER BY course ASC");
        if ($cq) { while ($c = $cq->fetch_assoc()) $all_courses[] = ['id' => $c['course_id'], 'name' => $c['course']]; }
        $all_events = [];
        $eq = $conn->query("SELECT event_id, event_title
                            FROM Event
                            WHERE status = 1
                              AND (DATE(end_on) >= CURDATE() OR DATE(start_on) >= CURDATE()
                                   OR end_on IS NULL OR start_on IS NULL)
                            ORDER BY
                              CASE WHEN start_on IS NULL THEN 1 ELSE 0 END,
                              start_on ASC,
                              event_title ASC");
        if ($eq) { while ($e = $eq->fetch_assoc()) $all_events[] = ['id' => $e['event_id'], 'name' => $e['event_title']]; }

        // ---- Build the main query with optional WHERE filters ----
        $where = [];
        if ($f_type === 'virtual') {
            $where[] = "(se.email_type = 'virtual' OR se.email_type IS NULL OR se.email_type = '')";
            if ($f_course !== '') {
                $where[] = "se.event_id = '" . $conn->real_escape_string($f_course) . "'";
            }
        } elseif ($f_type === 'international') {
            $where[] = "se.email_type = 'international'";
            if ($f_event !== '') {
                $where[] = "se.event_id = '" . $conn->real_escape_string($f_event) . "'";
            }
        } elseif ($f_type === 'academic') {
            $where[] = "se.email_type = 'academic'";
            if ($f_acad !== '') {
                $where[] = "se.event_id = '" . $conn->real_escape_string($f_acad) . "'";
            }
        }
        $where_sql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

        $sm_result = $conn->query("SELECT se.*, ru.fullname,
            c.course AS course_name,
            e.event_title AS event_name_lookup
            FROM system_emails1 se 
            LEFT JOIN registered_users ru ON se.updated_by = ru.id 
            LEFT JOIN course c ON se.event_id = c.course_id AND (se.email_type = 'virtual' OR se.email_type IS NULL OR se.email_type = '')
            LEFT JOIN Event e ON se.event_id = e.event_id AND se.email_type IN ('international','academic')
            $where_sql
            ORDER BY se.id DESC") or die($conn->error);

        // Dedup (most recent per group)
        $seen = [];
        $rows = [];
        while ($sm_data = $sm_result->fetch_assoc()) {
            $email_type = isset($sm_data['email_type']) && !empty($sm_data['email_type'])
                ? $sm_data['email_type'] : 'virtual';

            $is_event = ($email_type === 'international' || $email_type === 'academic');
            if ($is_event) {
                $dedup_key = 'evt|' . trim((string)$sm_data['event_id']) . '|' . trim((string)$sm_data['email_opt']);
            } else {
                $dedup_key = 'virt|' . trim((string)$sm_data['course_opt']) . '|' . trim((string)$sm_data['email_opt']);
            }
            if (isset($seen[$dedup_key])) continue;
            $seen[$dedup_key] = true;

            if ($is_event) {
                $course_event_name = !empty($sm_data['event_name_lookup']) ? $sm_data['event_name_lookup']
                                   : (!empty($sm_data['event_name']) ? $sm_data['event_name'] : $sm_data['course_opt']);
            } else {
                $course_event_name = !empty($sm_data['course_name']) ? $sm_data['course_name'] : $sm_data['course_opt'];
            }
            $sm_data['_email_type'] = $email_type;
            $sm_data['_ce_name']    = trim((string)$course_event_name);
            $rows[] = $sm_data;
        }
        ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">System Emails</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end align-items-center">
                            <button class="btn border-0 p-0 ms-3" onclick="location.href='new_system_email1'">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href='system_emails1'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filter form: Type + Course/Event, applied on Search -->
                    <form method="get" action="system_emails1" class="row g-2 mb-3 align-items-end" id="filterForm">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Email Type</label>
                            <select name="f_type" id="filterType" class="form-control rounded-0">
                                <option value="all" <?php echo $f_type === 'all' ? 'selected' : ''; ?>>All Emails</option>
                                <option value="virtual" <?php echo $f_type === 'virtual' ? 'selected' : ''; ?>>Virtual Courses</option>
                                <option value="international" <?php echo $f_type === 'international' ? 'selected' : ''; ?>>International Events</option>
                                <option value="academic" <?php echo $f_type === 'academic' ? 'selected' : ''; ?>>Academic Programmes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Course</label>
                            <select name="f_course" id="filterCourse" class="form-control rounded-0" <?php echo $f_type === 'virtual' ? '' : 'disabled'; ?>>
                                <option value="">All Courses</option>
                                <?php foreach ($course_options as $cid => $cname): ?>
                                <option value="<?php echo htmlspecialchars($cid); ?>" <?php echo ($f_course !== '' && $f_course == $cid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Event</label>
                            <select name="f_event" id="filterEvent" class="form-control rounded-0" <?php echo $f_type === 'international' ? '' : 'disabled'; ?>>
                                <option value="">All Events</option>
                                <?php foreach ($event_options as $eid => $ename): ?>
                                <option value="<?php echo htmlspecialchars($eid); ?>" <?php echo ($f_event !== '' && $f_event == $eid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ename); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Programme</label>
                            <select name="f_acad" id="filterAcad" class="form-control rounded-0" <?php echo $f_type === 'academic' ? '' : 'disabled'; ?>>
                                <option value="">All Programmes</option>
                                <?php foreach ($acad_options as $aid => $aname): ?>
                                <option value="<?php echo htmlspecialchars($aid); ?>" <?php echo ($f_acad !== '' && $f_acad == $aid) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aname); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn rounded-0 text-white" style="background:#7a1c2e;">
                                <i class="bi bi-search"></i> Search
                            </button>
                            <button type="button" id="resetFilter" class="btn btn-outline-dark rounded-0">
                                <i class="bi bi-x-circle"></i> Reset
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive overflow">
                        <table class="table table-bordered table-hover border lead_table align-middle" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            <thead>
                                <tr>
                                    <th class="nowrap">Type</th>
                                    <th class="nowrap">Description</th>
                                    <th class="nowrap">Course/Event</th>
                                    <th class="nowrap">Date Created</th>
                                    <th class="nowrap">Last Updated</th>
                                    <th class="nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No emails match your filter.</td></tr>
                                <?php else: foreach ($rows as $sm_data):
                                    $email_type = $sm_data['_email_type'];
                                    $course_event_name = $sm_data['_ce_name'];

                                    $type_badge = $email_type == 'international'
                                        ? '<span class="badge bg-danger">International</span>'
                                        : ($email_type == 'academic'
                                            ? '<span class="badge" style="background:#6d1f2b;color:#fff;">Academic</span>'
                                            : '<span class="badge bg-primary">Virtual</span>');

                                    $email_no = trim((string)$sm_data['email_opt']);
                                    $email_no_badge = ($email_no !== '')
                                        ? '<span class="badge bg-secondary ms-1">Email ' . htmlspecialchars($email_no) . '</span>'
                                        : '';

                                    $desc_parts = [];
                                    if (!empty(trim((string)$sm_data['course_opt']))) {
                                        $desc_parts[] = trim((string)$sm_data['course_opt']);
                                    }
                                    if ($email_no !== '') {
                                        $desc_parts[] = 'Email ' . $email_no;
                                    }
                                    if (!empty($desc_parts)) {
                                        $description = implode(' - ', $desc_parts);
                                    } else {
                                        $description = $course_event_name !== '' ? $course_event_name : 'Untitled email';
                                    }
                                ?>
                                    <tr data-type="<?php echo $email_type; ?>">
                                        <td class="nowrap py-2">
                                            <?php echo $type_badge; ?><?php echo $email_no_badge; ?>
                                        </td>
                                        <td
                                            class="py-2"
                                            style="max-width: 260px; white-space: normal; cursor: pointer;"
                                            onclick="location.href='view_system_emails1?id=<?php echo $sm_data['id']; ?>'">
                                            <?php echo htmlspecialchars($description); ?>
                                        </td>
                                        <td class="nowrap py-2" onclick="location.href='view_system_emails1?id=<?php echo $sm_data['id']; ?>'" style="cursor: pointer;">
                                            <?php echo htmlspecialchars((string)$course_event_name); ?>
                                        </td>
                                        <td class="nowrap py-2" onclick="location.href='view_system_emails1?id=<?php echo $sm_data['id']; ?>'" style="cursor: pointer;">
                                            <?php echo $sm_data['date_created']; ?>
                                        </td>
                                        <td class="nowrap py-2" onclick="location.href='view_system_emails1?id=<?php echo $sm_data['id']; ?>'" style="cursor: pointer;">
                                            <?php echo !empty($sm_data['last_updated']) ? $sm_data['last_updated'] : 'Null'; ?>
                                            <small><?php echo !empty($sm_data['fullname']) ? "(" . $sm_data['fullname'] . ")" : ''; ?></small>
                                        </td>

                                        <td class="py-2">
                                            <div class="dropdown">
                                                <button class="btn btn-default btn-sm btn-flat border-info wave-effect text-info dropdown-toggle rounded-0" type="button" data-toggle="dropdown" aria-expanded="false">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu rounded-0 border-info p-0">
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2" href="edit_system_emails1?id=<?php echo $sm_data['id']; ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    <li class="border-bottom border-info">
                                                        <a class="dropdown-item py-2 dup_email" href="javascript:void(0);"
                                                           data-id="<?php echo $sm_data['id']; ?>"
                                                           data-type="<?php echo $email_type; ?>"
                                                           data-current="<?php echo htmlspecialchars((string)$course_event_name, ENT_QUOTES); ?>">
                                                            <i class="bi bi-files"></i>
                                                            Duplicate
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item py-2" href="view_system_emails1?id=<?php echo $sm_data['id']; ?>">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                            View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <!--<a class="dropdown-item py-2" href="javascript:void(0);" id="del_email" data-email_id="<?php echo $sm_data['id']; ?>">-->
                                                        <!--    <i class="bi bi-trash"></i>-->
                                                        <!--    Delete-->
                                                        <!--</a>-->
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Make the table lines clearly visible */
    #dataTable { border-color: #d3c7c7 !important; }
    #dataTable th,
    #dataTable td { border: 1px solid #e0d6d6 !important; vertical-align: middle; }
    #dataTable thead th {
        background-color: #7a1c2e;
        color: #ffffff;
        border-color: #5a1020 !important;
    }
    #dataTable tbody tr:hover { background-color: #fbf7ef; }
</style>

<script>
(function () {
    var typeSel   = document.getElementById('filterType');
    var courseSel = document.getElementById('filterCourse');
    var eventSel  = document.getElementById('filterEvent');
    var acadSel   = document.getElementById('filterAcad');
    var resetBtn  = document.getElementById('resetFilter');

    // Enable/disable the relevant second dropdown as the type changes
    // (search is only applied when the Search button is clicked).
    typeSel.addEventListener('change', function () {
        var t = this.value;
        courseSel.disabled = true; courseSel.value = '';
        eventSel.disabled  = true; eventSel.value  = '';
        if (acadSel) { acadSel.disabled = true; acadSel.value = ''; }
        if (t === 'virtual')                  courseSel.disabled = false;
        else if (t === 'international')        eventSel.disabled  = false;
        else if (t === 'academic' && acadSel) acadSel.disabled   = false;
    });

    // Reset clears filters and reloads the unfiltered list
    resetBtn.addEventListener('click', function () {
        window.location.href = 'system_emails1';
    });

    // ---- Duplicate: pick a new course/event, copy, then open edit ----
    var ALL_COURSES = <?php echo json_encode(array_values($all_courses)); ?>;
    var ALL_EVENTS  = <?php echo json_encode(array_values($all_events)); ?>;

    function optionsHtml(list) {
        var h = '<option value="">-- Select --</option>';
        for (var i = 0; i < list.length; i++) {
            h += '<option value="' + list[i].id + '">' + (list[i].name || ('#' + list[i].id)) + '</option>';
        }
        return h;
    }

    document.addEventListener('click', function (ev) {
        var link = ev.target.closest ? ev.target.closest('.dup_email') : null;
        if (!link) return;
        ev.preventDefault();

        var srcId   = link.getAttribute('data-id');
        var type    = link.getAttribute('data-type');
        var current = link.getAttribute('data-current') || '';
        var isIntl  = (type === 'international');
        var list    = isIntl ? ALL_EVENTS : ALL_COURSES;
        var label   = isIntl ? 'Event' : 'Course';

        if (typeof Swal === 'undefined') {
            alert('Picker component not loaded.');
            return;
        }

        Swal.fire({
            title: 'Duplicate Email',
            html:
                '<p class="small text-muted mb-2">Currently: <strong>' + (current || '—') + '</strong></p>' +
                '<label class="form-label small mb-1">Copy to which ' + label + '?</label>' +
                '<select id="dupTarget" class="form-select rounded-0">' + optionsHtml(list) + '</select>',
            showCancelButton: true,
            confirmButtonText: 'Duplicate & Edit',
            confirmButtonColor: '#7a1c2e',
            focusConfirm: false,
            preConfirm: function () {
                var sel = document.getElementById('dupTarget');
                var id  = sel.value;
                var name = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';
                if (!id) {
                    Swal.showValidationMessage('Please select a ' + label + '.');
                    return false;
                }
                return { target_id: id, target_name: name };
            }
        }).then(function (res) {
            if (!res.isConfirmed || !res.value) return;

            Swal.fire({ title: 'Duplicating…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            var fd = new FormData();
            fd.append('id', srcId);
            fd.append('target_id', res.value.target_id);
            fd.append('target_name', res.value.target_name);

            fetch('ajax/duplicate_system_email.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok && data.new_id) {
                        window.location.href = 'edit_system_emails1?id=' + data.new_id;
                    } else {
                        Swal.fire({ icon: 'error', title: 'Failed', text: (data && data.error) ? data.error : 'Could not duplicate.' });
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
                });
        });
    });
})();
</script>

<?php
require_once 'footer.php';
?>