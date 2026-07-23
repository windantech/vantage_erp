<?php
/**
 * Free Sessions CRM module helpers.
 */

if (!function_exists('free_session_slugify')) {
    function free_session_default_section_visibility()
    {
        return [
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
    }

    function free_session_decode_json_assoc($value, $fallback)
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    function free_session_merge_section_visibility($value)
    {
        $defaults = free_session_default_section_visibility();
        $incoming = is_array($value) ? $value : [];
        foreach ($defaults as $key => $enabled) {
            $defaults[$key] = !empty($incoming[$key]) ? 1 : 0;
        }
        return $defaults;
    }

    function free_session_slugify($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'free-session';
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');
        return $value !== '' ? $value : 'free-session';
    }

    function free_session_unique_slug($conn, $title, $existingId = 0)
    {
        $base = free_session_slugify($title);
        $slug = $base;
        $existingId = (int) $existingId;
        $suffix = 1;

        while (true) {
            $sql = "SELECT `id` FROM `free_sessions` WHERE `slug` = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return $slug;
            }
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                return $slug;
            }
            if ((int) $row['id'] === $existingId) {
                return $slug;
            }
            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }

    function free_session_get_all($conn, $type = null)
    {
        if ($type === 'virtual' || $type === 'international') {
            $sql = "SELECT * FROM `free_sessions` WHERE `session_type` = ? ORDER BY `sort_order` ASC, `id` DESC";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('s', $type);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
            $stmt->close();
            return $rows;
        }

        $res = mysqli_query($conn, "SELECT * FROM `free_sessions` ORDER BY `session_type` ASC, `sort_order` ASC, `id` DESC");
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    function free_session_get($conn, $id)
    {
        $id = (int) $id;
        $stmt = $conn->prepare("SELECT * FROM `free_sessions` WHERE `id` = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $session = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$session) {
            return null;
        }

        $session['section_visibility_map'] = free_session_merge_section_visibility(
            free_session_decode_json_assoc($session['section_visibility'], [])
        );
        $session['gallery_image_rows'] = array_values(array_filter(
            free_session_decode_json_assoc($session['gallery_images'], []),
            static function ($item) {
                return trim((string) $item) !== '';
            }
        ));

        $session['highlight_rows'] = [];
        $hres = mysqli_query($conn, "SELECT `highlight_text`, `sort_order` FROM `free_session_highlights` WHERE `free_session_id` = {$id} ORDER BY `sort_order` ASC, `id` ASC");
        if ($hres) {
            while ($row = mysqli_fetch_assoc($hres)) {
                $session['highlight_rows'][] = $row;
            }
        }

        $session['outcome_rows'] = [];
        $ores = mysqli_query($conn, "SELECT `outcome_text`, `sort_order` FROM `free_session_outcomes` WHERE `free_session_id` = {$id} ORDER BY `sort_order` ASC, `id` ASC");
        if ($ores) {
            while ($row = mysqli_fetch_assoc($ores)) {
                $session['outcome_rows'][] = $row;
            }
        }

        return $session;
    }

    function free_session_save_highlights($conn, $sessionId, $highlights)
    {
        $sessionId = (int) $sessionId;
        mysqli_query($conn, "DELETE FROM `free_session_highlights` WHERE `free_session_id` = {$sessionId}");

        $stmt = $conn->prepare("INSERT INTO `free_session_highlights` (`free_session_id`, `highlight_text`, `sort_order`) VALUES (?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        $sort = 0;
        foreach ($highlights as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $sort++;
            $stmt->bind_param('isi', $sessionId, $text, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    function free_session_save_outcomes($conn, $sessionId, $outcomes)
    {
        $sessionId = (int) $sessionId;
        mysqli_query($conn, "DELETE FROM `free_session_outcomes` WHERE `free_session_id` = {$sessionId}");

        $stmt = $conn->prepare("INSERT INTO `free_session_outcomes` (`free_session_id`, `outcome_text`, `sort_order`) VALUES (?, ?, ?)");
        if (!$stmt) {
            return false;
        }

        $sort = 0;
        foreach ($outcomes as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $sort++;
            $stmt->bind_param('isi', $sessionId, $text, $sort);
            $stmt->execute();
        }
        $stmt->close();
        return true;
    }

    function free_session_create($conn, $data, $highlights, $outcomes)
    {
        $slug = free_session_unique_slug($conn, $data['slug'] !== '' ? $data['slug'] : $data['title']);
        $sql = "INSERT INTO `free_sessions` (
            `session_type`, `title`, `slug`, `short_description`, `full_description`, `poster_image`, `hero_badge`,
            `mode_label`, `location`, `timezone_label`, `start_on`, `end_on`,
            `registration_label`, `registration_cta_note`, `virtual_cta_label`, `virtual_cta_link`,
            `event_reference_id`, `sort_order`, `status`,
            `preview_media_type`, `preview_video_link`, `testimonial_video_link`, `schedule_file`, `share_image`,
            `gallery_images`, `section_visibility`, `trainer_image`, `trainer_description`,
            `zoom_topic`, `zoom_date`, `zoom_time`, `zoom_link`, `zoom_meeting_id`, `zoom_passcode`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $eventRef = $data['event_reference_id'] > 0 ? (int) $data['event_reference_id'] : 0;
        $sortOrder = (int) $data['sort_order'];
        $galleryImagesJson = json_encode($data['gallery_images'], JSON_UNESCAPED_SLASHES);
        $sectionVisibilityJson = json_encode($data['section_visibility'], JSON_UNESCAPED_SLASHES);

        $types = str_repeat('s', 16) . 'ii' . str_repeat('s', 16);
        $stmt->bind_param(
            $types,
            $data['session_type'],
            $data['title'],
            $slug,
            $data['short_description'],
            $data['full_description'],
            $data['poster_image'],
            $data['hero_badge'],
            $data['mode_label'],
            $data['location'],
            $data['timezone_label'],
            $data['start_on'],
            $data['end_on'],
            $data['registration_label'],
            $data['registration_cta_note'],
            $data['virtual_cta_label'],
            $data['virtual_cta_link'],
            $eventRef,
            $sortOrder,
            $data['status'],
            $data['preview_media_type'],
            $data['preview_video_link'],
            $data['testimonial_video_link'],
            $data['schedule_file'],
            $data['share_image'],
            $galleryImagesJson,
            $sectionVisibilityJson,
            $data['trainer_image'],
            $data['trainer_description'],
            $data['zoom_topic'],
            $data['zoom_date'],
            $data['zoom_time'],
            $data['zoom_link'],
            $data['zoom_meeting_id'],
            $data['zoom_passcode']
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $newId = (int) $stmt->insert_id;
        $stmt->close();
        free_session_save_highlights($conn, $newId, $highlights);
        free_session_save_outcomes($conn, $newId, $outcomes);
        return $newId;
    }

    function free_session_update($conn, $id, $data, $highlights, $outcomes)
    {
        $id = (int) $id;
        $slug = free_session_unique_slug($conn, $data['slug'] !== '' ? $data['slug'] : $data['title'], $id);

        $sql = "UPDATE `free_sessions` SET
            `session_type` = ?, `title` = ?, `slug` = ?, `short_description` = ?, `full_description` = ?, `poster_image` = ?, `hero_badge` = ?,
            `mode_label` = ?, `location` = ?, `timezone_label` = ?, `start_on` = ?, `end_on` = ?,
            `registration_label` = ?, `registration_cta_note` = ?, `virtual_cta_label` = ?, `virtual_cta_link` = ?,
            `event_reference_id` = ?, `sort_order` = ?, `status` = ?,
            `preview_media_type` = ?, `preview_video_link` = ?, `testimonial_video_link` = ?, `schedule_file` = ?, `share_image` = ?,
            `gallery_images` = ?, `section_visibility` = ?, `trainer_image` = ?, `trainer_description` = ?,
            `zoom_topic` = ?, `zoom_date` = ?, `zoom_time` = ?, `zoom_link` = ?, `zoom_meeting_id` = ?, `zoom_passcode` = ?
            WHERE `id` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $eventRef = $data['event_reference_id'] > 0 ? (int) $data['event_reference_id'] : 0;
        $sortOrder = (int) $data['sort_order'];
        $galleryImagesJson = json_encode($data['gallery_images'], JSON_UNESCAPED_SLASHES);
        $sectionVisibilityJson = json_encode($data['section_visibility'], JSON_UNESCAPED_SLASHES);

        $types = str_repeat('s', 16) . 'ii' . str_repeat('s', 16) . 'i';
        $stmt->bind_param(
            $types,
            $data['session_type'],
            $data['title'],
            $slug,
            $data['short_description'],
            $data['full_description'],
            $data['poster_image'],
            $data['hero_badge'],
            $data['mode_label'],
            $data['location'],
            $data['timezone_label'],
            $data['start_on'],
            $data['end_on'],
            $data['registration_label'],
            $data['registration_cta_note'],
            $data['virtual_cta_label'],
            $data['virtual_cta_link'],
            $eventRef,
            $sortOrder,
            $data['status'],
            $data['preview_media_type'],
            $data['preview_video_link'],
            $data['testimonial_video_link'],
            $data['schedule_file'],
            $data['share_image'],
            $galleryImagesJson,
            $sectionVisibilityJson,
            $data['trainer_image'],
            $data['trainer_description'],
            $data['zoom_topic'],
            $data['zoom_date'],
            $data['zoom_time'],
            $data['zoom_link'],
            $data['zoom_meeting_id'],
            $data['zoom_passcode'],
            $id
        );

        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        free_session_save_highlights($conn, $id, $highlights);
        free_session_save_outcomes($conn, $id, $outcomes);
        return true;
    }

    function free_session_delete($conn, $id)
    {
        $id = (int) $id;
        return (bool) mysqli_query($conn, "DELETE FROM `free_sessions` WHERE `id` = {$id} LIMIT 1");
    }

    function free_session_update_status($conn, $id, $status)
    {
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            return false;
        }
        $id = (int) $id;
        $stmt = $conn->prepare("UPDATE `free_sessions` SET `status` = ? WHERE `id` = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function free_session_collect_post_data()
    {
        $f = static function ($key, $default = '') {
            return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
        };
        $normalizeDatetime = static function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }
            $value = str_replace('T', ' ', $value);
            return strlen($value) === 16 ? ($value . ':00') : $value;
        };

        $type = $f('session_type', 'international');
        if (!in_array($type, ['virtual', 'international'], true)) {
            $type = 'international';
        }
        $status = $f('status', 'active');
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            $status = 'active';
        }
        $previewMediaType = $f('preview_media_type', 'poster');
        if (!in_array($previewMediaType, ['poster', 'video'], true)) {
            $previewMediaType = 'poster';
        }

        $galleryImagesRaw = isset($_POST['gallery_images']) && is_array($_POST['gallery_images']) ? $_POST['gallery_images'] : [];
        $galleryImages = [];
        foreach ($galleryImagesRaw as $image) {
            $image = trim((string) $image);
            if ($image !== '') {
                $galleryImages[] = $image;
            }
        }
        $sectionVisibilityInput = isset($_POST['section_visibility']) && is_array($_POST['section_visibility']) ? $_POST['section_visibility'] : [];
        $sectionVisibility = free_session_merge_section_visibility($sectionVisibilityInput);

        return [
            'session_type' => $type,
            'title' => $f('title'),
            'slug' => $f('slug'),
            'short_description' => $f('short_description'),
            'full_description' => $f('full_description'),
            'poster_image' => $f('poster_image'),
            'hero_badge' => $f('hero_badge', 'Free Session'),
            'mode_label' => $f('mode_label'),
            'location' => $f('location'),
            'timezone_label' => $f('timezone_label'),
            'start_on' => $normalizeDatetime($f('start_on')),
            'end_on' => $normalizeDatetime($f('end_on')),
            'registration_label' => $f('registration_label', 'Register now'),
            'registration_cta_note' => $f('registration_cta_note'),
            'virtual_cta_label' => $f('virtual_cta_label', 'Watch training videos'),
            'virtual_cta_link' => $f('virtual_cta_link', 'trainings/videos.php'),
            'event_reference_id' => isset($_POST['event_reference_id']) ? (int) $_POST['event_reference_id'] : 0,
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
            'status' => $status,
            'preview_media_type' => $previewMediaType,
            'preview_video_link' => $f('preview_video_link'),
            'testimonial_video_link' => $f('testimonial_video_link'),
            'schedule_file' => $f('schedule_file'),
            'share_image' => $f('share_image'),
            'gallery_images' => $galleryImages,
            'section_visibility' => $sectionVisibility,
            'trainer_image' => $f('trainer_image', 'assets/logo.png'),
            'trainer_description' => $f('trainer_description', 'Our lead trainer is an experienced practitioner who combines real-world expertise with practical, action-oriented coaching.'),
            'zoom_topic' => $f('zoom_topic'),
            'zoom_date' => $f('zoom_date'),
            'zoom_time' => $f('zoom_time'),
            'zoom_link' => $f('zoom_link'),
            'zoom_meeting_id' => $f('zoom_meeting_id'),
            'zoom_passcode' => $f('zoom_passcode'),
        ];
    }

    function free_session_collect_rows_from_post($field)
    {
        if (empty($_POST[$field]) || !is_array($_POST[$field])) {
            return [];
        }
        $values = [];
        foreach ($_POST[$field] as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $values[] = $value;
            }
        }
        return $values;
    }
}
