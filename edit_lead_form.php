<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>

        <?php
        if ($_GET['id']) {
            $id = $_GET['id'];
            $_SESSION['id'] = $id;
            $query = mysqli_query($conn, "SELECT * FROM lead_forms WHERE id = '$id'") or die(mysqli_error($conn));
            $row = mysqli_fetch_array($query);

            $form__data = [];
            $data_type = [];

            $array = json_decode($row['form_data'], true);
            foreach ($array as $item) {
                $form__data[] = [
                    "name" => $item['name'],
                    "type" => $item['type'],
                    "field_type" => $item['field_type'],
                    "options" => $item['options']
                ];

                $data_type[] = [
                    "type" => $item['type']
                ];
            }
        } else {
            echo "<script>
                    location.href = 'lead_forms'
                </script>";
        } ?>

        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase title_header">
                                Edit <?php echo (($row['title'] == "") ? "NULL" : $row['title']); ?>
                            </h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.href='lead_forms'" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-left-circle"></i> Go Back
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i> Reload
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-12 col-md-12">
                            <div class="input-group mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Form Title</span>
                                <input type="text" id="upd_form_title" class="form-control rounded-0" value="<?php echo (($row['title'] == "") ? "" : $row['title']); ?>" placeholder="Enter Form Title" aria-label="Form Title" aria-describedby="basic-addon1" required>
                            </div>
                            
                             <div class="col-xl-12 col-md-12 p-1">
                                            <div class="input-group mb-3 position-relative">
                                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Redirect Link(After Completing the form )</span>
                                                <input type="text" name="upd_redirect_link" value="<?php echo (($row['redirect_link'] == "") ? "" : $row['redirect_link']); ?>" id="upd_redirect_link" class="form-control rounded-0" placeholder="https://vantageafricaleaders.com/certified-monitoring-and-evaluation-course/" aria-label="Redirect Link" aria-describedby="basic-addon1" required>
                                            </div>
                                        </div>

                            <div class="input-group px-1 mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Insctructions</span>
                                <textarea id="upd_instructions" class="form-control" placeholder="Hello" required><?php echo htmlspecialchars(($row['description'] == "") ? "" : $row['description']); ?></textarea>
                            </div>

                            <div class="input-group px-1 mb-3 position-relative">
                                <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Email Body</span>
                                <textarea id="upd_email_body_" class="form-control" placeholder="hello" required><?php echo htmlspecialchars($row['email_body']); ?></textarea>
                            </div>
                        </div>

                        <div class="col-xl-6 col-md-6">
                            <div class="card rounded-0">
                                <div class="card-header rounded-0 bg_main">
                                    Add Fields
                                </div>

                                <div class="card-body">
                                    <span class="d-none" id="form__data"><?php echo json_encode($form__data); ?></span>
                                    <div class="form-group position-relative">
                                        <label for="field_name" class="mb-0 py-1 px-2 bg_main">Field Name:</label>
                                        <input type="text" class="form-control rounded-0" name="field_name" id="field_name" placeholder="e.g. Name" required>
                                    </div>
                                    <div class="form-group position-relative">
                                        <label for="field_type" class="mb-0 py-1 px-2 bg_main">Field Type:</label>
                                        <select id="field_type" class="form-control rounded-0" required>
                                            <option value="selected" selected='true' hidden>-- Kindly select Field Type --</option>
                                            <option value="text">Text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone Number</option>
                                            <option value="select">Selection Menu</option>
                                            <option value="textarea">Textarea</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="selectOptionsGroup" style="display: none;">
                                        <label for="select_options" class="mb-0 py-1 px-2 bg_main">Options (comma-separated):</label>
                                        <input type="text" id="select_options" class="form-control rounded-0" placeholder="e.g. Option 1, Option 2">
                                    </div>
                                    <div class="form-group d-flex justify-content-end mb-0">
                                        <button type="button" id="add_field" class="btn btn-success rounded-0">
                                            Add Field <i class="bi bi-chevron-double-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6 col-md-6">
                            <div class="card rounded-0">
                                <div class="card-header rounded-0 bg_main">
                                    Add Fields
                                </div>

                                <div class="card-body">
                                    <?php
                                    $lead_forms_result = $conn->query("SELECT * FROM lead_forms WHERE id = '$id'") or die($conn->error);
                                    while ($lead_forms_data = $lead_forms_result->fetch_assoc()) {
                                        $array = json_decode($lead_forms_data['form_data'], true);
                                        foreach ($array as $index => $item) {
                                            $lowercaseString = strtolower($item['name']);
                                            $input_name = str_replace(' ', '_', $lowercaseString); ?>
                                            <div class="input-group mb-3 position-relative" data-index="<?php echo $index; ?>">
                                                <span class="input-group-text rounded-0 bg_main position-relative py-0" style="width: 9rem;" id="basic-addon1">
                                                    <input type="text" class="bg_main border-0 w-100" style="height: -webkit-fill-available;"
                                                        data-data_type="<?php echo htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-data_opt='<?php echo json_encode($item['options'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                        id="edit_form_input_<?php echo htmlspecialchars($input_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                </span>
                                                <?php
                                                switch ($item['type']) {
                                                    case 'input': ?>
                                                        <input type="<?php echo $item['field_type']; ?>" name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" class="form-control rounded-0" placeholder="Enter <?php echo $item['name']; ?>" aria-describedby="basic-addon1" required>
                                                    <?php break;
                                                    case 'select': ?>
                                                        <select class="form-control rounded-0" name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" required>
                                                            <option value="" hidden>-- Select <?php echo $item['name']; ?> --</option>
                                                            <?php foreach ($item['options'] as $option) { ?>
                                                                <option value="<?php echo $option['value']; ?>"><?php echo $option['text']; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    <?php break;
                                                    case 'textarea': ?>
                                                        <textarea name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" rows="7" class="form-control rounded-0" placeholder="Enter <?php echo $item['name']; ?>....." required></textarea>
                                                <?php break;
                                                }
                                                ?>
                                                <button type="button" class="btn btn-danger rounded-0 remove-form" data-index="<?php echo $index; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                    <?php }
                                    } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>