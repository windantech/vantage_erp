$(document).ready(function () {
  // Handle email configuration form submission (Save)
  $("#email_config_form").submit(function (e) {
    e.preventDefault();

    if (
      isNotEmpty($("#description")) &&
      isSelectNotEmpty($("#select_emails")) &&
      isNotEmpty($("#schedule_date"))
    ) {
      var button = $(this).find('button[type="submit"]');
      var spinner =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      // Disable button and show spinner
      if (!button.hasClass("loading")) {
        button.toggleClass("loading").html(spinner);
        button.prop("disabled", true);
      }

      var formData = new FormData(this);

      // AJAX request for saving configuration
      $.ajax({
        url: "includes/config_email.inc.php",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
          handleResponse(response, button, false); // false indicates it's a save operation
        },
        error: function (xhr, status, error) {
          Swal.fire({
            title: "AJAX Error",
            text: "There was an error with the request: " + error,
            position: "top-end",
            icon: "error",
            confirmButtonText: "OK",
          }).then(() => {
            button.prop("disabled", false);
            button.toggleClass("loading").html("Save");
          });
        },
      });
    }
  });

  // Handle update email configuration form submission (Update)
  $(document).on("submit", "#upd_email_config_form", function (e) {
    e.preventDefault();

    var scheduleId = $("#schedule_id").val();

    if (
      isNotEmpty($("#upd_schedule_description")) &&
      isSelectNotEmpty($("#upd_select_emails_" + scheduleId)) &&
      isNotEmpty($("#upd_scheduled_date"))
    ) {
      var button = $(this).find('button[type="submit"]');
      var spinner =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      // Disable button and show spinner
      if (!button.hasClass("loading")) {
        button.toggleClass("loading").html(spinner);
        button.prop("disabled", true);
      }

      var formData = new FormData(this);

      // AJAX request for updating configuration
      $.ajax({
        url: "includes/upd_config_email.inc.php",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
          handleResponse(response, button, true); // true indicates it's an update operation
        },
        error: function (xhr, status, error) {
          Swal.fire({
            title: "AJAX Error",
            text: "There was an error with the request: " + error,
            position: "top-end",
            icon: "error",
            confirmButtonText: "OK",
          }).then(() => {
            button.prop("disabled", false);
            button.toggleClass("loading").html("Update");
          });
        },
      });
    }
  });

  // Handle schedule deletion
  $(document).on("click", "#del_schedule", function () {
    var schedule_id = $(this).data("email_id");
    Swal.fire({
      title: "Are you sure?",
      text: "You want to delete this schedule?",
      position: "top-end",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Yes, delete it!",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: "includes/del_config_email.inc.php",
          type: "POST",
          data: { schedule_id: schedule_id },
          success: function (response) {
            if (response == "1") {
              Swal.fire({
                title: "Deleted!",
                text: "The schedule has been deleted.",
                position: "top-end",
                icon: "success",
                confirmButtonText: "OK",
              }).then(function () {
                location.reload();
              });
            } else {
              Swal.fire({
                title: "Failed!",
                text: "Something went wrong while deleting.",
                position: "top-end",
                icon: "error",
                confirmButtonText: "Try Again",
              });
            }
          },
          error: function (xhr, status, error) {
            Swal.fire({
              title: "AJAX Error",
              text: "There was an error with the request: " + error,
              position: "top-end",
              icon: "error",
              confirmButtonText: "OK",
            });
          },
        });
      } else {
        Swal.fire({
          title: "Canceled",
          text: "You have canceled the deletion.",
          position: "top-end",
          icon: "info",
          confirmButtonText: "OK",
        });
      }
    });
  });
});

// Response handler for both save and update
function handleResponse(response, button, isUpdate) {
  if (response == "1") {
    Swal.fire({
      title: "Success!",
      text: isUpdate
        ? "Configuration updated successfully."
        : "Configuration saved successfully.",
      position: "top-end",
      icon: "success",
      confirmButtonText: "OK",
    }).then(function () {
      location.reload(); // Reload the page after success
    });
  } else if (response == "2") {
    Swal.fire({
      title: "Failed!",
      text: isUpdate
        ? "Something went wrong while updating your configuration."
        : "Something went wrong while saving your configuration.",
      position: "top-end",
      icon: "error",
      confirmButtonText: "Try Again",
    }).then(() => {
      button.prop("disabled", false);
      button.toggleClass("loading").html(isUpdate ? "Update" : "Save");
    });
  } else if (response == "0") {
    Swal.fire({
      title: "Error!",
      text: "An unexpected error occurred. Please try again later.",
      position: "top-end",
      icon: "warning",
      confirmButtonText: "OK",
    }).then(() => {
      button.prop("disabled", false);
      button.toggleClass("loading").html(isUpdate ? "Update" : "Save");
    });
  } else {
    Swal.fire({
      title: "Unknown Error",
      text: "Something went wrong. Please try again.",
      position: "top-end",
      icon: "error",
      confirmButtonText: "OK",
    }).then(() => {
      button.prop("disabled", false);
      button.toggleClass("loading").html(isUpdate ? "Update" : "Save");
    });
  }
}

// Form validation: Check if input is not empty
function isNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.val() == "") {
    caller.css("border-color", "red", "important");
    caller.after(
      "<span class='error' style='right: 3vh; bottom: -3vh;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border", "");
    caller.next(".error").remove();
    return true;
  }
}

// Form validation: Check if select is not empty
function isSelectNotEmpty(caller) {
  $(".select2-selection").next(".error").remove();
  if (caller.val() == null || caller.val().length === 0) {
    $(".select2-selection").css("border-color", "red", "important");
    $(".select2-selection").after(
      "<span class='error' style='right: 3vh; bottom: -3vh;'>This field is required.</span>"
    );
    $(".select2-selection").focus();
    return false;
  } else {
    $(".select2-selection").css("border", "");
    $(".select2-selection").next(".error").remove();
    return true;
  }
}
