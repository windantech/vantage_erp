$(document).ready(function () {
  $('[id^="edit_form_input_"]').on("keyup", function () {
    var inputValue = $(this).val();
    var inputName = this.id.replace("edit_form_input_", "");
    var targetInput = $("#" + inputName);
    targetInput.attr("placeholder", inputValue);

    // Collect form data
    var formData = [];
    $('[id^="edit_form_input_"]').each(function () {
      var name = $(this).val();
      var id = this.id.replace("edit_form_input_", "");
      var element = $("#" + id)[0];
      var type = element ? element.type : null;
      var data_type = $(this).data("data_type");
      var data_opt = $(this).data("data_opt");

      var options = [];
      if (Array.isArray(data_opt)) {
        data_opt.forEach(function (opt) {
          options.push({
            value: opt.value,
            text: opt.text,
          });
        });
      }

      formData.push({
        name: name,
        type: data_type || "default_type", // Provide a default value if data_type is undefined
        field_type:
          type === "select-one" ? "select" : type || "default_field_type", // Ensure field_type is "select" for select-one
        options: options, // Include data_opt values in options
      });
    });

    // Send AJAX request to save data
    $.ajax({
      url: "includes/save_form_data.inc.php",
      type: "POST",
      contentType: "application/json;charset=UTF-8",
      data: JSON.stringify({ form_data: formData }),
      success: function () {
        // Clear any existing messages
        $(".success-message, .failed-message").remove();

        // Append success message
        var successMessage = $("<span>")
          .addClass("success-message")
          .text("Success");
        $(this).parent().append(successMessage);

        // Remove success message after 3 seconds
        setTimeout(function () {
          successMessage.remove();
        }, 3000);
      }.bind(this),
      error: function () {
        // Clear any existing messages
        $(".success-message, .failed-message").remove();

        // Append failed message
        var failedMessage = $("<span>")
          .addClass("failed-message")
          .text("Failed");
        $(this).parent().append(failedMessage);

        // Remove failed message after 3 seconds
        setTimeout(function () {
          failedMessage.remove();
        }, 3000);
      }.bind(this),
    });
  });

  $(".remove-form").on("click", function () {
    const swalWithBootstrapButtons = Swal.mixin({
      customClass: {
        confirmButton: "btn btn-success mx-1 rounded-0",
        cancelButton: "btn btn-danger mx-1 rounded-0",
      },
      buttonsStyling: false,
    });

    swalWithBootstrapButtons
      .fire({
        position: "top-end",
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "No, cancel!",
        reverseButtons: true,
      })
      .then((result) => {
        if (result.isConfirmed) {
          var formGroup = $(this).closest(".input-group");
          var index = $(this).data("index");

          // Send AJAX request to remove the item from the database
          $.ajax({
            type: "POST",
            url: "includes/remove_form_item.inc.php",
            data: { index: index },
            success: function (response) {
              formGroup.remove();
              swalWithBootstrapButtons
                .fire({
                  position: "top-end",
                  title: "Success!",
                  text: "Record deleted Successfully.",
                  icon: "success",
                })
                .then(() => {
                  location.reload();
                });
            },
            error: function () {
              swalWithBootstrapButtons.fire({
                position: "top-end",
                title: "Failed!",
                text: "Failed to delete record",
                icon: "error",
              });
            },
          });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          swalWithBootstrapButtons.fire({
            position: "top-end",
            title: "Cancelled",
            text: "Your record is safe",
            icon: "error",
          });
        }
      });
  });

  $("#field_type").on("change", function () {
    const selectOptionsGroup = $("#selectOptionsGroup");
    if ($(this).val() === "select") {
      selectOptionsGroup.show();
    } else {
      selectOptionsGroup.hide();
    }
  });

  $("#add_field").on("click", function () {
    const field_name = $("#field_name").val();
    const field_type = $("#field_type").val();
    const select_options = $("#select_options")
      .val()
      .split(",")
      .map((option) => {
        return { value: option.trim(), text: option.trim() };
      });

    if (
      !field_name ||
      !field_type ||
      (field_type === "select" && select_options.length === 0)
    ) {
      Swal.fire({
        position: "top-end",
        icon: "info",
        title: "Empty Fields",
        text: "Please fill in all required fields.",
        showConfirmButton: true,
      });
      return;
    }

    const newField = {
      name: field_name,
      type:
        field_type === "select"
          ? "select"
          : field_type === "textarea"
          ? "textarea"
          : "input",
      field_type: field_type,
    };

    if (field_type === "select") {
      newField.options = select_options;
    }

    // Assuming saved data is stored in a variable
    var form__data = $("#form__data").text();
    let formData = JSON.parse(form__data);
    // Check if the field already exists and update it
    const existingFieldIndex = formData.findIndex(
      (field) => field.name === field_name
    );
    if (existingFieldIndex !== -1) {
      formData[existingFieldIndex] = newField;
    } else {
      formData.push(newField);
    }

    // Send data to server
    $.ajax({
      url: "includes/extra_lead_fields.inc.php",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({ form_data: formData }),
      success: function (data) {
        if (data == 0 || data == "") {
          Swal.fire({
            position: "top-end",
            icon: "error",
            title: "Error",
            text: "Something went wrong\nPlease try again later.",
            showConfirmButton: true,
          });
        } else if (data == 1) {
          Swal.fire({
            position: "top-end",
            icon: "success",
            title: "Success",
            text: "Field added successfully",
            showConfirmButton: true,
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire({
            position: "top-end",
            icon: "error",
            title: "Failed",
            text: "Failed to add field",
            showConfirmButton: true,
          });
        }
      },
      error: function () {
        alert("An error occurred while updating the field.");
      },
    });
  });

  $("#upd_form_title").on("keyup", function () {
    var input = $(this);
    var record = input.val();
    var post = "title";

    update_data(input, record, post);
    $(".title_header").text("Edit " + record);
  });
  
  
  $("#upd_redirect_link").on("keyup", function () {
    var input = $(this);
    var record = input.val();
    var post = "redirect_link";

    update_data(input, record, post);
    $(".title_header").text("Edit " + record);
  });

  $("#upd_instructions").summernote({
    callbacks: {
      onChange: function (contents, $editable) {
        var input = $("#upd_instructions");
        var record = contents;
        var post = "description";

        update_data(input, record, post);
      },
    },
  });

  $("#upd_email_body_").summernote({
    callbacks: {
      onChange: function (contents, $editable) {
        var input = $("#upd_email_body_");
        var record = contents;
        var post = "email_body";

        update_data(input, record, post);
      },
    },
  });

  function update_data(input, record, post) {
    $.ajax({
      url: "includes/update_form_lead.inc.php", // URL to your PHP script
      type: "POST",
      data: { record: record, post: post },
      success: function (response) {
        // Remove existing messages
        input.parent().find(".success-message, .failed-message").remove();
        if (response == 1) {
          // Append success message
          var successMessage = $("<span>")
            .addClass("success-message")
            .text("Success");
          input.parent().append(successMessage);
        } else if (response == 2) {
          // Append success message
          var failedMessage = $("<span>")
            .addClass("failed-message")
            .text("Failed");
          input.parent().append(failedMessage);
        }

        // Remove success message after 3 seconds
        setTimeout(function () {
          successMessage.remove();
        }, 3000);
      },
      error: function (xhr, status, error) {
        // Remove existing messages
        input.parent().find(".success-message, .failed-message").remove();

        // Append error message
        var errorMessage = $("<span>")
          .addClass("failed-message")
          .text("Error updating record");
        input.parent().append(errorMessage);

        // Remove error message after 3 seconds
        setTimeout(function () {
          errorMessage.remove();
        }, 3000);
      },
    });
  }
});
