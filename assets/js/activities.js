$(document).ready(function () {
  $("#np_form").submit(function (e) {
    e.preventDefault();
    if (
      selNotEmpty($("#task_id")) &&
      isNotEmpty($("#subject")) &&
      isNotEmpty($("#date")) &&
      isNotEmpty($("#start_time")) &&
      isNotEmpty($("#end_time")) &&
      isNotEmpty($("#summernote1"))
    ) {
      $.ajax({
        url: "includes/new_productivity.inc.php",
        type: "POST",
        data: $(this).serialize(),
        success: function (response) {
          if (response == 1) {
            Swal.fire({
              icon: "success",
              title: "Success",
              text: "Productivity submitted successfully!",
              position: "top-end",
            }).then((result) => {
              if (result.isConfirmed) {
                location.reload();
              }
            });
          } else if (response == 2) {
            Swal.fire({
              icon: "error",
              title: "Failed",
              text: "Productivity submission failed!",
              position: "top-end",
            }).then((result) => {
              if (result.isConfirmed) {
                location.reload();
              }
            });
          }
        },
        error: function (xhr, status, error) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was an error submitting the form.",
            position: "top-end",
          });
        },
      });
    }
  });

  $("#edit_productivity").submit(function (event) {
    event.preventDefault();
    if (
      selNotEmpty($("#upd_task_id")) &&
      isNotEmpty($("#upd_subject")) &&
      isNotEmpty($("#upd_date")) &&
      isNotEmpty($("#upd_start_time")) &&
      isNotEmpty($("#upd_end_time")) &&
      isNotEmpty($("#summernote3"))
    ) {
      var formData = new FormData($(this)[0]);

      $.ajax({
        url: "includes/update_productivity.inc.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response == 1) {
            Swal.fire({
              title: "Success",
              text: "Productivity updated successfully!",
              icon: "success",
              position: "top-end",
            }).then(() => {
              location.reload();
            });
          } else if (response == 2) {
            Swal.fire({
              title: "Failed",
              text: "Failed to update productivity!",
              icon: "error",
              position: "top-end",
            });
          } else if (response == 3) {
            Swal.fire({
              title: "Invalid Request",
              text: "Invalid request method!",
              icon: "error",
              position: "top-end",
            });
          } else {
            Swal.fire({
              title: "Failed",
              text: "An unexpected error occurred!",
              icon: "error",
              position: "top-end",
            });
          }
          $("#add_task")[0].reset();
        },
        error: function (xhr, status, error) {
          Swal.fire({
            title: "Error",
            text: "An error occurred: " + error,
            icon: "error",
            position: "top-end",
          });
        },
      });
    }
  });

  const swalWithBootstrapButtons = Swal.mixin({
    customClass: {
      confirmButton: "btn btn-success mx-1 rounded-0",
      cancelButton: "btn btn-danger mx-1 rounded-0",
    },
    buttonsStyling: false,
  });

  $(document).on("click", ".deleteProgressModal", function () {
    var progressId = $(this).data("p_id");

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
          $.ajax({
            type: "POST",
            url: "includes/remove_progress.inc.php",
            data: { id: progressId },
            success: function (response) {
              if (response == 1) {
                swalWithBootstrapButtons
                  .fire({
                    position: "top-end",
                    title: "Success!",
                    text: "Progress deleted successfully.",
                    icon: "success",
                  })
                  .then(() => {
                    location.reload();
                  });
              } else if (response == 2) {
                swalWithBootstrapButtons.fire({
                  position: "top-end",
                  title: "Failed!",
                  text: "Failed to delete progress.",
                  icon: "error",
                });
              } else if (response == 3) {
                swalWithBootstrapButtons.fire({
                  position: "top-end",
                  title: "Invalid Record",
                  text: "The progress record is invalid.",
                  icon: "warning",
                });
              }
            },
            error: function () {
              swalWithBootstrapButtons.fire({
                position: "top-end",
                title: "Failed!",
                text: "Failed to delete progress.",
                icon: "error",
              });
            },
          });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          swalWithBootstrapButtons.fire({
            position: "top-end",
            title: "Cancelled",
            text: "Your progress is safe.",
            icon: "error",
          });
        }
      });
  });
});

function isNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.val() == "") {
    caller.css("border-color", "red", "important");
    caller.after(
      "<span class='error' style='right: 0; bottom: -3vh;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border", "");
    caller.next(".error").remove();
    return true;
  }
}

function selNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.find(":selected").val() == "selected") {
    caller.css("border-color", "red", "important");
    caller.after(
      "<span class='error' style='right: 2.5vh; bottom: -3.5vh;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border", "");
    caller.next(".error").remove();
    return true;
  }
}
