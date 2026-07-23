$(document).ready(function () {
  $("#new_project").submit(function (e) {
    e.preventDefault();
    if (
      isNotEmpty($("#p_name")) &&
      selNotEmpty($("#p_status")) &&
      isNotEmpty($("#start_date")) &&
      isNotEmpty($("#end_date")) &&
      selNotEmpty($("#manager_id")) &&
      selNotEmpty($("#user_ids")) &&
      isNotEmpty($(".p_description"))
    ) {
      $.ajax({
        url: "includes/new_project.inc.php",
        data: new FormData($(this)[0]),
        cache: false,
        contentType: false,
        processData: false,
        method: "POST",
        type: "POST",
        success: function (resp) {
          if (resp == 1) {
            Swal.fire({
              position: "top-end",
              icon: "success",
              title: "Success",
              text: "Data successfully saved",
              showConfirmButton: true,
            }).then((result) => {
                if (result.isConfirmed) {
                  location.href='projects';
                }
              });
          } else if (resp == 2) {
            Swal.fire({
              position: "top-end",
              icon: "error",
              title: "Error",
              text: "Data failed to save",
              showConfirmButton: true,
            });
          }
        },
        error: function (xhr, status, error) {
          Swal.fire({
            position: "top-end",
            icon: "error",
            title: "Error",
            text: "Something went wrong\nPlease try again later",
            showConfirmButton: true,
          });
        },
      });
    }
  });

  $("#update_project").submit(function (e) {
    e.preventDefault();
    if (
      isNotEmpty($("#up_name")) &&
      selNotEmpty($("#up_status")) &&
      isNotEmpty($("#ustart_date")) &&
      isNotEmpty($("#uend_date")) &&
      selNotEmpty($("#umanager_id")) &&
      selNotEmpty($("#uuser_ids")) &&
      isNotEmpty($(".up_description"))
    ) {
      $("#update_project").prop("disabled", true);
      $.ajax({
        url: "includes/update_project.inc.php",
        data: new FormData($(this)[0]),
        cache: false,
        contentType: false,
        processData: false,
        method: "POST",
        type: "POST",
        success: function (resp) {
          if (resp == 1) {
            Swal.fire({
              position: "top-end",
              icon: "success",
              title: "Success",
              text: "Data successfully updated",
              showConfirmButton: true,
            }).then((result) => {
              if (result.isConfirmed) {
                location.href='projects';
              }
            });
          } else if (resp == 2) {
            Swal.fire({
              position: "top-end",
              icon: "error",
              title: "Error",
              text: "Data failed to update",
              showConfirmButton: true,
            }).then((result) => {
              if (result.isConfirmed) {
                $("#update_project").prop("disabled", false);
              }
            });
          }
        },
        error: function (xhr, status, error) {
          Swal.fire({
            position: "top-end",
            icon: "error",
            title: "Error",
            text: "Something went wrong\nPlease try again later",
            showConfirmButton: true,
          }).then((result) => {
            if (result.isConfirmed) {
              $("#update_project").prop("disabled", false);
            }
          });
        },
      });
    }
  });

  $(".deleteProjectLink").on("click", function (e) {
    e.preventDefault();
    var projectId = $(this).data("id");

    const swalWithBootstrapButtons = Swal.mixin({
      customClass: {
        confirmButton: "btn btn-success rounded-0 mx-1",
        cancelButton: "btn btn-danger rounded-0 mx-1",
      },
      buttonsStyling: false,
    });

    swalWithBootstrapButtons
      .fire({
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
            url: "includes/delete_project.inc.php",
            type: "POST",
            data: { id: projectId },
            success: function (response) {
              if (response == 1) {
                swalWithBootstrapButtons
                  .fire("Deleted!", "Your project has been deleted.", "success")
                  .then(() => {
                    location.reload();
                  });
              } else if (response == 2) {
                swalWithBootstrapButtons.fire(
                  "Error!",
                  "Failed to delete project.",
                  "error"
                );
              }
            },
            error: function (xhr, status, error) {
              swalWithBootstrapButtons.fire(
                "Error!",
                "There was an error deleting the project.",
                "error"
              );
            },
          });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          swalWithBootstrapButtons.fire(
            "Cancelled",
            "Your project is safe :)",
            "error"
          );
        }
      });
  });
});

function isNotEmpty(caller) {
  caller.next(".error").remove();
  if (caller.val() == "") {
    caller.css("border-color", "red", "important");
    caller.after(
      "<span class='error' style='right: 0; bottom: -2.5vh;'>This field is required.</span>"
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
      "<span class='error' style='right: 2.5vh; bottom: -2.5vh;'>This field is required.</span>"
    );
    caller.focus();
    return false;
  } else {
    caller.css("border", "");
    caller.next(".error").remove();
    return true;
  }
}
