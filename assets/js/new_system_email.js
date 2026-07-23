$(document).ready(function () {
  // Restore saved values from localStorage
  if (localStorage.getItem("email_type")) {
    $("#email_type").val(localStorage.getItem("email_type"));
    // Trigger change to show/hide course/event
    handleEmailTypeChange(localStorage.getItem("email_type"));
  }

  if (localStorage.getItem("email_subject")) {
    $("#email_subject").val(localStorage.getItem("email_subject"));
  }

  if (localStorage.getItem("course_opt")) {
    $("#course_opt").val(localStorage.getItem("course_opt"));
  }

  if (localStorage.getItem("event_opt")) {
    $("#event_opt").val(localStorage.getItem("event_opt"));
  }

  if (localStorage.getItem("email_opt")) {
    $("#email_opt").val(localStorage.getItem("email_opt"));
  }

  if (localStorage.getItem("temp_opt")) {
    $("#temp_opt").val(localStorage.getItem("temp_opt"));
    loadTemplateContent(localStorage.getItem("temp_opt"));
  }

  // Update header with course/event info
  updateComposeHeader();

  // Handle email type change
  $("#email_type").change(function () {
    var selectedType = $(this).val();
    handleEmailTypeChange(selectedType);
  });

  function handleEmailTypeChange(selectedType) {
    if (selectedType === "virtual") {
      $("#course_container").removeClass("d-none");
      $("#event_container").addClass("d-none");
      $("#course_opt").val("");
      $("#event_opt").val("");
    } else if (selectedType === "international") {
      $("#course_container").addClass("d-none");
      $("#event_container").removeClass("d-none");
      $("#course_opt").val("");
      $("#event_opt").val("");
    }
  }

  function updateComposeHeader() {
    var emailType = localStorage.getItem("email_type");
    var emailOpt = localStorage.getItem("email_opt");

    if (emailType === "virtual" && localStorage.getItem("course_opt") && emailOpt) {
      $("#compose_header").html("Compose Email (" + localStorage.getItem("course_opt") + " - Email " + emailOpt + ")");
    } else if (emailType === "international" && localStorage.getItem("event_opt") && emailOpt) {
      $("#compose_header").html("Compose Email (" + localStorage.getItem("event_opt") + " - Email " + emailOpt + ")");
    }
  }

  function loadTemplateContent(templateName) {
    // Blank template: nothing to fetch, just clear the preview area
    if (templateName === "__blank__") {
      $("#preview_temps").html("");
      return;
    }

    $.ajax({
      url: "email_templates/" + templateName + ".php",
      type: "GET",
      success: function (response) {
        $("#preview_temps").html(response);
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Failed",
          text: "Failed to load the selected template.",
          position: "top-end",
          showConfirmButton: true,
          confirmButtonText: "Close",
        });
      },
    });
  }

  $("#temp_opt").change(function () {
    var selectedTemplate = $(this).val();

    if (selectedTemplate) {
      loadTemplateContent(selectedTemplate);
    }
  });

  $("#step1_btn").click(function () {
    var emailType = $("#email_type").val();

    // Validate email type first
    if (!emailType || emailType === "") {
      $("#email_type").css("border-color", "red");
      $("#email_type").after(
        "<span class='error' style='position: absolute; right: 0; bottom: -3vh;'>Please select an email type.</span>"
      );
      $("#email_type").focus();
      return false;
    } else {
      $("#email_type").css("border-color", "");
      $("#email_type").next(".error").remove();
    }

    // Validate based on email type
    var courseEventValid = false;
    if (emailType === "virtual") {
      courseEventValid = selNotEmpty($("#course_opt"));
    } else if (emailType === "international") {
      courseEventValid = selNotEmptyEvent($("#event_opt"));
    }

    if (
      isNotEmpty($("#email_subject")) &&
      courseEventValid &&
      selNotEmpty($("#email_opt")) &&
      selNotEmpty($("#temp_opt"))
    ) {
      // Store values in localStorage
      localStorage.setItem("email_type", emailType);
      localStorage.setItem("email_subject", $("#email_subject").val());
      localStorage.setItem("email_opt", $("#email_opt").val());
      localStorage.setItem("temp_opt", $("#temp_opt").val());

      if (emailType === "virtual") {
        localStorage.setItem("course_opt", $("#course_opt").val());
        localStorage.setItem("event_id", $("#course_opt option:selected").data("id") || "");
        localStorage.setItem("event_opt", ""); // Clear event
        localStorage.setItem("event_name", "");
      } else if (emailType === "international") {
        localStorage.setItem("event_opt", $("#event_opt").val());
        localStorage.setItem("event_id", $("#event_opt option:selected").data("id") || "");
        localStorage.setItem("event_name", $("#event_opt").val());
        localStorage.setItem("course_opt", ""); // Clear course
      }

      var selectedTemplate = $("#temp_opt").val();

      // ---- Blank template: skip fetch, open empty editor, go to step 2 ----
      if (selectedTemplate === "__blank__") {
        updateComposeHeader();
        $("#email_body").summernote("code", "");

        $("#preview_temps").hide();

        $("#step1").addClass("d-none");
        $("#step1").removeClass("d-flex");

        $("#step2").addClass("d-flex");
        $("#step2").removeClass("d-none");
        return;
      }

      loadTemplateContent(selectedTemplate);

      // Wait a bit for template to load, then set content
      setTimeout(function () {
        var previewContent = $("#preview_temps").html();

        if (previewContent) {
          updateComposeHeader();
          $("#email_body").summernote("code", previewContent);

          $("#preview_temps").hide();

          $("#step1").addClass("d-none");
          $("#step1").removeClass("d-flex");

          $("#step2").addClass("d-flex");
          $("#step2").removeClass("d-none");
        } else {
          Swal.fire({
            icon: "error",
            title: "Failed",
            text: "Please select a template to preview first.",
            position: "top-end",
            showConfirmButton: true,
            confirmButtonText: "Close",
          });
        }
      }, 500);
    }
  });

  $("#prev_btn").click(function () {
    $("#step2").addClass("d-none");
    $("#step2").removeClass("d-flex");

    $("#step1").addClass("d-flex");
    $("#step1").removeClass("d-none");

    $("#preview_temps").show();
  });

  $("#step2").submit(function (event) {
    event.preventDefault();

    var button = $(this).find('button[type="submit"]'),
      spinner = '<span class="spinner"></span>';
    if (!button.hasClass("loading")) {
      button.toggleClass("loading").html(spinner);
      button.prop("disabled", true);
    }

    // Pull the current editor HTML (incl. background) into the textarea
    // so serialize() captures the edited content
    $("#email_body").val($("#email_body").summernote("code"));

    var formData = $(this).serialize();

    // Add localStorage values to form data
    if (localStorage.getItem("email_type")) {
      formData +=
        "&email_type=" + encodeURIComponent(localStorage.getItem("email_type"));
    }

    if (localStorage.getItem("email_subject")) {
      formData +=
        "&email_subject=" +
        encodeURIComponent(localStorage.getItem("email_subject"));
    }

    if (localStorage.getItem("course_opt")) {
      formData +=
        "&course_opt=" + encodeURIComponent(localStorage.getItem("course_opt"));
    }

    if (localStorage.getItem("event_opt")) {
      formData +=
        "&event_opt=" + encodeURIComponent(localStorage.getItem("event_opt"));
    }

    if (localStorage.getItem("event_id")) {
      formData +=
        "&event_id=" + encodeURIComponent(localStorage.getItem("event_id"));
    }

    if (localStorage.getItem("event_name")) {
      formData +=
        "&event_name=" + encodeURIComponent(localStorage.getItem("event_name"));
    }

    if (localStorage.getItem("email_opt")) {
      formData +=
        "&email_opt=" + encodeURIComponent(localStorage.getItem("email_opt"));
    }

    if (localStorage.getItem("temp_opt")) {
      formData +=
        "&temp_opt=" + encodeURIComponent(localStorage.getItem("temp_opt"));
    }

    $.ajax({
      url: "includes/new_system_email.inc.php",
      type: "POST",
      data: formData,
      success: function (response) {
        if (response == 1) {
          Swal.fire({
            icon: "success",
            title: "Success!",
            text: "The email has been saved successfully.",
            position: "top-end",
            showConfirmButton: true,
            confirmButtonText: "Close",
          }).then((result) => {
            if (result.isConfirmed) {
              localStorage.clear();
              window.location.href = "system_emails1";
            }
          });
        } else if (response == 2) {
          Swal.fire({
            icon: "error",
            title: "Failed",
            text: "There was an issue saving the email.",
            position: "top-end",
            showConfirmButton: true,
            confirmButtonText: "Close",
          }).then(() => {
            button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
            button.prop("disabled", false);
          });
        } else if (response == 0) {
          Swal.fire({
            icon: "error",
            title: "Error",
            text: "An error occurred while encoding the email.",
            position: "top-end",
            showConfirmButton: true,
            confirmButtonText: "Close",
          }).then(() => {
            button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
            button.prop("disabled", false);
          });
        }
      },
      error: function (xhr, status, error) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "There was an error with the submission.",
          position: "top-end",
          showConfirmButton: true,
          confirmButtonText: "Close",
        }).then(() => {
          button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Submit');
          button.prop("disabled", false);
        });
        console.log(error);
      },
    });
  });

 function initSummernote(selector) {
  var BgPickerButton = function (context) {
    var ui = $.summernote.ui;
    var button = ui.button({
      contents: '<i class="note-icon-paint"></i>',
      tooltip: "Background colour (selection)",
      click: function () {
        // build a native color input on the fly
        var input = $('<input type="color" style="position:absolute;opacity:0;">');
        $("body").append(input);
        input.on("input change", function () {
          var color = $(this).val();
          // apply background to current selection
          document.execCommand("hiliteColor", false, color) ||
            document.execCommand("backColor", false, color);
          context.invoke("editor.afterCommand");
          input.remove();
        });
        input.trigger("click");
      },
    });
    return button.render();
  };

  $(selector)
    .summernote({
      height: 400,
      buttons: { bgPicker: BgPickerButton },
      toolbar: [
        ["style", ["style"]],
        [
          "font",
          ["bold", "italic", "underline", "strikethrough", "superscript", "subscript", "clear"],
        ],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["custom", ["bgPicker"]],
        ["para", ["ol", "ul", "paragraph", "height"]],
        ["table", ["table"]],
        ["insert", ["link", "picture", "video", "shape"]],
        ["view", ["undo", "redo", "fullscreen", "codeview", "help"]],
      ],
    })
    .summernote("code", "");
}

  initSummernote("#email_body");

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

  function selNotEmpty(caller) {
    caller.next(".error").remove();
    var selectedVal = caller.find(":selected").val();
    if (selectedVal === "selected" || selectedVal === "" || !selectedVal) {
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

  // Separate validation for event select (same logic but clearer)
  function selNotEmptyEvent(caller) {
    caller.next(".error").remove();
    var selectedVal = caller.find(":selected").val();
    if (selectedVal === "selected" || selectedVal === "" || !selectedVal) {
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
});