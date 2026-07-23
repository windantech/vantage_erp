$(document).ready(function () {
  $("#compose_email_form").submit(function (e) {
    e.preventDefault();
      $.ajax({
        type: "POST",
        url: "includes/compose_system_email.inc.php",
        data: $(this).serialize(),
        success: function (response) {
          if (response == 1) {
            Swal.fire({
              icon: "success",
              title: "Success",
              text: "Email composed successfully!",
              position: "top-end",
            }).then(() => {
              location.href = "system_emails";
            });
          } else if (response == 2) {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "Failed to compose email.",
              position: "top-end",
            });
          } else {
            Swal.fire({
              icon: "warning",
              title: "Warning",
              text: "Unexpected response.",
              position: "top-end",
            });
          }
        },
        error: function (error) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was an error saving the email.",
            position: "top-end",
          });
        },
      });
  });

  $("#update_email_form").submit(function (e) {
    e.preventDefault();
      $.ajax({
        type: "POST",
        url: "includes/update_system_email.inc.php",
        data: $(this).serialize(),
        success: function (response) {
          if (response == 1) {
            Swal.fire({
              icon: "success",
              title: "Success",
              text: "Email Updated successfully!",
              position: "top-end",
            }).then(() => {
              location.href = "system_emails";
            });
          } else if (response == 2) {
            Swal.fire({
              icon: "error",
              title: "Error",
              text: "Failed to update email.",
              position: "top-end",
            });
          } else {
            Swal.fire({
              icon: "warning",
              title: "Warning",
              text: "Unexpected response.",
              position: "top-end",
            });
          }
        },
        error: function (error) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was an error updating the email.",
            position: "top-end",
          });
        },
      });
  });


  $("#compose_email_form1").submit(function (e) {
    e.preventDefault();  // Prevents the default form submission

    // Perform AJAX POST request to submit the form data
    $.ajax({
      type: "POST",  // POST method
      url: "includes/compose_system_email1.inc.php",  // PHP script URL
      data: $(this).serialize(),  // Serialize the form data
      success: function (response) {
        // Check the response and display the appropriate alert
        if (response == 1) {
          Swal.fire({
            icon: "success",
            title: "Success",
            text: "Email composed successfully!",
            position: "top-end",
          }).then(() => {
            location.href = "system_emails1";  // Redirect to system emails page after success
          });
        } else if (response == 2) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "Failed to compose email.",
            position: "top-end",
          });
        } else {
          Swal.fire({
            icon: "warning",
            title: "Warning",
            text: "Unexpected response.",
            position: "top-end",
          });
        }
      },
      error: function (error) {
        // Handle errors from the AJAX request
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "There was an error saving the email.",
          position: "top-end",
        });
      },
    });
  });

  $("#update_email_form1").submit(function (e) {
    e.preventDefault();  // Prevent default form submission

    // Perform AJAX POST request to submit the form data for updating
    $.ajax({
        type: "POST",  // Use POST method for updating
        url: "includes/update_system_email1.inc.php",  // PHP script URL for handling the update
        data: $(this).serialize(),  // Serialize the form data for submission
        success: function (response) {
            // Check the response from the server and display the appropriate message
            if (response == "1") {
                Swal.fire({
                    icon: "success",
                    title: "Update Successful",
                    text: "Email updated successfully!",
                    position: "top-end",
                }).then(() => {
                    location.href = "system_emails1";  // Redirect to system emails page after success
                });
            } else if (response == "2") {
                Swal.fire({
                    icon: "error",
                    title: "Update Failed",
                    text: "Failed to update email. Please try again.",
                    position: "top-end",
                });
            } else {
                Swal.fire({
                    icon: "warning",
                    title: "Unexpected Response",
                    text: "An unexpected response was received.",
                    position: "top-end",
                });
            }
        },
        error: function (error) {
            // Handle errors during the AJAX request
            Swal.fire({
                icon: "error",
                title: "Server Error",
                text: "There was an error updating the email. Please check your connection and try again.",
                position: "top-end",
            });
        },
    });
});

});

