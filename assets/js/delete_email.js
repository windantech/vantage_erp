$(document).on("click", "#del_email", function () {
    const emailId = $(this).data("email_id");

    Swal.fire({
        title: "Are you sure?",
        text: "Do you really want to delete this email?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "Cancel",
        position: "top-end"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "includes/delete_email.inc.php",
                type: "POST",
                data: { email_id: emailId },
                success: function (response) {
                    if (response.trim() === "1") {
                        Swal.fire({
                            title: "Deleted!",
                            text: "The email has been deleted successfully.",
                            icon: "success",
                            position: "top-end"
                        }).then(() => {
                            location.reload();
                        });
                    } else if (response.trim() === "2") {
                        Swal.fire({
                            title: "Failed!",
                            text: "There was a problem deleting the email. Please try again.",
                            icon: "error",
                            position: "top-end"
                        });
                    }
                },
                error: function () {
                    Swal.fire({
                        title: "Error!",
                        text: "An error occurred while processing your request. Please try again later.",
                        icon: "error",
                        position: "top-end"
                    });
                }
            });
        } else {
            Swal.fire({
                title: "Cancelled",
                text: "The email was not deleted.",
                icon: "info",
                position: "top-end"
            });
        }
    });
});
