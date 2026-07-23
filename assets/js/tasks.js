$(document).ready(function () {
  $("#add_task").submit(function (event) {
    event.preventDefault();
    if (
      isNotEmpty($("#task_name")) &&
      selStatsNotEmpty($("#task_status")) &&
      selIdNotEmpty($("#user_ids")) &&
      isNotEmpty($("#end_date")) &&
      isNotEmpty($("#summernote"))
    ) {
      var formData = new FormData($(this)[0]);

      $.ajax({
        url: "includes/add_task.inc.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response == 1) {
            Swal.fire({
              title: "Success",
              text: "Task saved successfully!",
              icon: "success",
              position: "top-end",
            }).then(() => {
              location.reload();
            });
          } else {
            Swal.fire({
              title: "Failed",
              text: "Failed to save task!",
              icon: "error",
              position: "top-end",
            });
          }
          $("#add_task")[0].reset();
        },
        error: function (xhr, status, error) {
          Swal.fire({
            title: "Error",
            text: "An error occurred while saving the task!",
            icon: "error",
            position: "top-end",
          });
        },
      });
    }
  });

  $("#edit_task").on("submit", function (e) {
    e.preventDefault();
    const p_id = $("#p_id").val();

    $.ajax({
      type: "POST",
      url: "includes/update_task.inc.php",
      data: $(this).serialize(),
      success: function (response) {
        if (response == 1) {
          Swal.fire({
            position: "top-end",
            icon: "success",
            title: "Success",
            text: "Task updated successfully!",
            showConfirmButton: true,
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = `view_project?id=${p_id}`;
            }
          });
        } else if (response == 2) {
          Swal.fire({
            position: "top-end",
            icon: "error",
            title: "Failed",
            text: "Task update failed!",
            showConfirmButton: true,
          });
        }
      },
      error: function (xhr, status, error) {
        Swal.fire({
          position: "top-end",
          icon: "error",
          title: "Error",
          text: "An error occurred: " + error,
          showConfirmButton: true,
        });
      },
    });
  });

  const swalWithBootstrapButtons = Swal.mixin({
    customClass: {
      confirmButton: "btn btn-success mx-1 rounded-0",
      cancelButton: "btn btn-danger mx-1 rounded-0",
    },
    buttonsStyling: false,
  });

  $(document).on("click", "#delete_task", function () {
    var taskId = $(this).data("task_id");

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
            url: "includes/remove_task.inc.php",
            data: { task_id: taskId },
            success: function (response) {
              if (response == 1) {
                swalWithBootstrapButtons
                  .fire({
                    position: "top-end",
                    title: "Success!",
                    text: "Task deleted successfully.",
                    icon: "success",
                  })
                  .then(() => {
                    location.reload();
                  });
              } else if (response == 2) {
                swalWithBootstrapButtons.fire({
                  position: "top-end",
                  title: "Failed!",
                  text: "Failed to delete task.",
                  icon: "error",
                });
              } else if (response == 3) {
                swalWithBootstrapButtons.fire({
                  position: "top-end",
                  title: "Invalid Record",
                  text: "The task record is invalid.",
                  icon: "warning",
                });
              }
            },
            error: function () {
              swalWithBootstrapButtons.fire({
                position: "top-end",
                title: "Failed!",
                text: "Failed to delete task.",
                icon: "error",
              });
            },
          });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          swalWithBootstrapButtons.fire({
            position: "top-end",
            title: "Cancelled",
            text: "Your task is safe.",
            icon: "error",
          });
        }
      });
  });
});

function isNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.val().trim() === "") {
    caller.css("border-color", "red");
    caller.after(
      "<span class='error' style='position: absolute; right: 0; bottom: -3vh;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border-color", "");
    caller.next(".error").remove();
    return true;
  }
}

function selStatsNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.find(":selected").val() === "selected") {
    caller.css("border-color", "red");
    caller.after(
      "<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border-color", "");
    caller.next(".error").remove();
    return true;
  }
}

function selIdNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.val() === null || caller.val().length === 0) {
    caller.css("border-color", "red");
    caller.after(
      "<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border-color", "");
    caller.next(".error").remove();
    return true;
  }
}
