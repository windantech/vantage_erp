$(document).ready(function () {
  // Get saved values from PHP (passed via data attribute on template select)
  var savedTemplate = $("#upd_temp_opt").data("saved") || "";
  var savedBodyContent = $("#upd_preview_temps").html();

  /* ============================================================
     LIVE PREVIEW (step 2) — mirror Summernote content into iframe
     ============================================================ */
  function renderLivePreview(html) {
    var f = document.getElementById("upd_preview");
    if (!f) return;
    var doc = f.contentDocument || f.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;">' + (html || "") + "</body></html>");
    doc.close();
  }

  /* ============================================================
     TEMPLATE THUMBNAIL PREVIEW (step 1)
     Renders whatever HTML is currently in #upd_preview_temps into
     a scaled-down iframe so staff see the design before Next.
     ============================================================ */
  function renderTemplateThumb() {
    var wrap = document.getElementById("upd_tpl_preview_wrap");
    var f = document.getElementById("upd_tpl_preview");
    if (!wrap || !f) return;
    var html = $("#upd_preview_temps").html() || "";
    if (!html.trim()) { $(wrap).addClass("d-none"); return; }

    $("#upd_tpl_preview_name").text($("#upd_temp_opt option:selected").text() || "");
    $(wrap).removeClass("d-none");

    var doc = f.contentDocument || f.contentWindow.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;">' + html + "</body></html>");
    doc.close();

    setTimeout(function () {
      var boxW = $("#upd_tpl_preview_box").width() || 560;
      var scale = Math.min(1, boxW / 600);
      var fullH = (doc.body ? doc.body.scrollHeight : 1200);
      f.style.width = "600px";
      f.style.height = fullH + "px";
      f.style.transform = "scale(" + scale + ")";
      // Size the scroll box to the scaled email height so the whole design is
      // visible (capped, then scroll for anything taller).
      var scaledH = Math.ceil(fullH * scale);
      $("#upd_tpl_preview_box").css("height", Math.min(scaledH + 4, 700) + "px");
    }, 120);
  }

  // Restore saved values from localStorage
  if (localStorage.getItem("upd_email_type")) {
    $("#upd_email_type").val(localStorage.getItem("upd_email_type"));
    handleEmailTypeChange(localStorage.getItem("upd_email_type"));
  }

  if (localStorage.getItem("upd_email_subject")) {
    $("#upd_email_subject").val(localStorage.getItem("upd_email_subject"));
  }

  if (localStorage.getItem("upd_course_opt")) {
    $("#upd_course_opt").val(localStorage.getItem("upd_course_opt"));
  }

  if (localStorage.getItem("upd_event_opt")) {
    $("#upd_event_opt").val(localStorage.getItem("upd_event_opt"));
  }

  if (localStorage.getItem("upd_email_opt")) {
    $("#upd_email_opt").val(localStorage.getItem("upd_email_opt"));
  }

  if (localStorage.getItem("upd_temp_opt")) {
    $("#upd_temp_opt").val(localStorage.getItem("upd_temp_opt"));
    var storedTemplate = localStorage.getItem("upd_temp_opt");
    // Only load fresh template if different from saved
    if (storedTemplate !== savedTemplate) {
      loadTemplateContent(storedTemplate, renderTemplateThumb);
    }
  }

  // Show the thumbnail for whatever is selected/saved on load
  setTimeout(renderTemplateThumb, 350);

  // Handle email type change
  $("#upd_email_type").change(function () {
    var selectedType = $(this).val();
    handleEmailTypeChange(selectedType);
  });

  function handleEmailTypeChange(selectedType) {
    if (selectedType === "virtual") {
      $("#upd_course_container").removeClass("d-none");
      $("#upd_event_container").addClass("d-none");
    } else if (selectedType === "international") {
      $("#upd_course_container").addClass("d-none");
      $("#upd_event_container").removeClass("d-none");
    }
  }

  // Load a template's HTML from its PHP file into #upd_preview_temps.
  // Optional callback runs after the content is placed.
  function loadTemplateContent(templateName, cb) {
    $.ajax({
      url: "email_templates/" + templateName + ".php",
      type: "GET",
      success: function (response) {
        $("#upd_preview_temps").html(response);
        if (typeof cb === "function") cb();
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

  // Template change handler - preserve existing content if same template
  $("#upd_temp_opt").change(function () {
    var selectedTemplate = $(this).val();

    if (selectedTemplate) {
      // If same template as originally saved, restore the saved body content
      if (selectedTemplate === savedTemplate && savedBodyContent) {
        $("#upd_preview_temps").html(savedBodyContent);
        renderTemplateThumb();
      } else {
        // Different template, load fresh from file, then show thumbnail
        loadTemplateContent(selectedTemplate, renderTemplateThumb);
      }
    } else {
      $("#upd_tpl_preview_wrap").addClass("d-none");
    }
  });

  $("#upd_step1_btn").click(function () {
    var emailType = $("#upd_email_type").val();

    // Validate based on email type
    var courseEventValid = false;
    if (emailType === "virtual") {
      courseEventValid = selNotEmpty($("#upd_course_opt"));
    } else if (emailType === "international") {
      courseEventValid = selNotEmptyEvent($("#upd_event_opt"));
    }

    if (
      isNotEmpty($("#upd_email_subject")) &&
      courseEventValid &&
      selNotEmpty($("#upd_email_opt")) &&
      selNotEmpty($("#upd_temp_opt"))
    ) {
      // Store values in localStorage
      localStorage.setItem("upd_email_type", emailType);
      localStorage.setItem("upd_email_subject", $("#upd_email_subject").val());
      localStorage.setItem("upd_email_opt", $("#upd_email_opt").val());
      localStorage.setItem("upd_temp_opt", $("#upd_temp_opt").val());

      if (emailType === "virtual") {
        localStorage.setItem("upd_course_opt", $("#upd_course_opt").val());
        localStorage.setItem("upd_event_id", $("#upd_course_opt option:selected").data("id") || "");
        localStorage.setItem("upd_event_opt", "");
        localStorage.setItem("upd_event_name", "");
      } else if (emailType === "international") {
        localStorage.setItem("upd_event_opt", $("#upd_event_opt").val());
        localStorage.setItem("upd_event_id", $("#upd_event_opt option:selected").data("id") || "");
        localStorage.setItem("upd_event_name", $("#upd_event_opt").val());
        localStorage.setItem("upd_course_opt", "");
      }

      var selectedTemplate = $("#upd_temp_opt").val();

      // If different template, load it first then proceed
      if (selectedTemplate !== savedTemplate) {
        loadTemplateContent(selectedTemplate, function () {
          proceedToStep2();
        });
      } else {
        // Same template, proceed immediately with existing content
        proceedToStep2();
      }
    }
  });

  /* ============================================================
     AI FIT — fit the EXISTING EMAIL's content into the loaded
     template, then place the result in Summernote.
     Falls back to the plain template content if anything fails.
     ============================================================ */
  function fitAndLoad(templateHtml, finalize) {
    // The original saved email content is the source of the message.
    var sourceEmail = savedBodyContent || "";
    if (!sourceEmail.trim()) { finalize(templateHtml); return; }

    $("#upd_status").css("color", "#666").text("Reading current content…");
    $.ajax({
      url: "email_ai_extract.php", type: "POST", dataType: "json",
      data: { html: sourceEmail },
      success: function (ex) {
        var content = (ex && ex.ok && ex.content) ? ex.content : sourceEmail;
        var exLinks = (ex && ex.ok && ex.links && ex.links.length) ? ex.links.join(", ") : "";
        $("#upd_status").css("color", "#666").text("Fitting your content into the template…");
        $.ajax({
          url: "email_ai_fit_template.php", type: "POST", dataType: "json",
          data: { template_html: templateHtml, content: content, links: exLinks },
          success: function (res) {
            if (res && res.ok && res.html) {
              if (res.warnings && res.warnings.length) {
                // Something may have been cut — warn in amber so staff review before saving.
                $("#upd_status").css("color", "#b8860b").html(
                  '<i class="bi bi-exclamation-triangle"></i> ' + res.warnings.join(" ")
                );
              } else {
                $("#upd_status").css("color", "#1a7a3c").text("Template filled with your content. Edit on the left.");
              }
              finalize(res.html);
            } else {
              $("#upd_status").css("color", "#b00").text((res && res.error) ? res.error : "Could not fit; using template as-is.");
              finalize(templateHtml);
            }
          },
          error: function () { $("#upd_status").css("color", "#b00").text("Fit failed; using template as-is."); finalize(templateHtml); }
        });
      },
      error: function () { $("#upd_status").css("color", "#b00").text("Extract failed; using template as-is."); finalize(templateHtml); }
    });
  }

  function proceedToStep2() {
    var sourceEmail = savedBodyContent || $("#upd_preview_temps").html() || "";

    // Switch views so the editor/preview are visible.
    $("#upd_preview_temps").hide();
    $("#upd_step1").addClass("d-none").removeClass("d-flex");
    $("#upd_step2").addClass("d-flex").removeClass("d-none");

    var place = function (html) {
      $("#upd_email_body").summernote("code", html);
      renderLivePreview(html);
    };

    var emailType = localStorage.getItem("upd_email_type") || $("#upd_email_type").val() || "virtual";
    var emailNo   = localStorage.getItem("upd_email_opt")  || $("#upd_email_opt").val()  || "";

    // If AI assist is on: read the existing email into TEXT fields, then let
    // PHP merge those fields into the auto-chosen marked template. The AI never
    // produces HTML, and the template (footer included) is filled by str_replace,
    // so nothing can be dropped.
    if ($("#upd_ai_fit_toggle").is(":checked") && sourceEmail.trim()) {
      buildFromTemplate(sourceEmail, emailType, emailNo, place);
    } else if (sourceEmail.trim()) {
      // No AI: just load the existing/selected content as-is for manual editing.
      place(sourceEmail);
      $("#upd_status").css("color", "#666").text("Loaded as-is. Edit the wording on the left.");
    } else {
      Swal.fire({
        icon: "error",
        title: "Failed",
        text: "There is no content to load.",
        position: "top-end",
        showConfirmButton: true,
        confirmButtonText: "Close",
      });
    }
  }

  /* ============================================================
     AI-ASSISTED, FOOTER-SAFE BUILD
     1) email_ai_extract_fields.php : existing email -> TEXT fields (JSON)
     2) email_fill_template.php     : PHP fills the auto-chosen marked
        template's {{markers}} with those fields (str_replace). The template
        structure & footer are never sent to AI and never rewritten.
     Falls back to loading the source content as-is on any failure.
     ============================================================ */
  function buildFromTemplate(sourceEmail, emailType, emailNo, finalize) {
    $("#upd_status").css("color", "#666").text("Reading your email's content…");
    $.ajax({
      url: "email_ai_extract_fields.php", type: "POST", dataType: "json",
      data: { html: sourceEmail },
      success: function (ex) {
        if (!ex || !ex.ok || !ex.fields) {
          $("#upd_status").css("color", "#b00").text((ex && ex.error) ? ex.error : "Could not read content; loaded as-is.");
          finalize(sourceEmail);
          return;
        }
        $("#upd_status").css("color", "#666").text("Placing your content into the template…");
        $.ajax({
          url: "email_fill_template.php", type: "POST", dataType: "json",
          data: { email_type: emailType, email_no: emailNo, fields: JSON.stringify(ex.fields) },
          success: function (res) {
            if (res && res.ok && res.html) {
              $("#upd_status").css("color", "#1a7a3c").text("Built from the template — footer and design intact. Edit on the left.");
              finalize(res.html);
            } else {
              $("#upd_status").css("color", "#b00").text((res && res.error) ? res.error : "Could not build; loaded as-is.");
              finalize(sourceEmail);
            }
          },
          error: function () { $("#upd_status").css("color", "#b00").text("Build failed; loaded as-is."); finalize(sourceEmail); }
        });
      },
      error: function () { $("#upd_status").css("color", "#b00").text("Read failed; loaded as-is."); finalize(sourceEmail); }
    });
  }

  // "Reload Template" button — restore the original template HTML as-is,
  // discarding edits (handy if staff want to start the wording over).
  $("#upd_plain_btn").on("click", function () {
    var tpl = $("#upd_preview_temps").html() || "";
    $("#upd_email_body").summernote("code", tpl);
    renderLivePreview(tpl);
    $("#upd_status").css("color", "#666").text("Template reloaded.");
  });

  $("#upd_prev_btn").click(function () {
    // When going back, update the preview with current editor content
    var currentContent = $("#upd_email_body").summernote("code");
    $("#upd_preview_temps").html(currentContent);

    $("#upd_step2").addClass("d-none").removeClass("d-flex");
    $("#upd_step1").addClass("d-flex").removeClass("d-none");

    $("#upd_preview_temps").show();
    renderTemplateThumb();
  });

  $("#upd_step2").submit(function (event) {
    event.preventDefault();

    var button = $(this).find('button[type="submit"]'),
      spinner = '<span class="spinner"></span>';
    if (!button.hasClass("loading")) {
      button.toggleClass("loading").html(spinner);
      button.prop("disabled", true);
    }

    var formData = $(this).serialize();

    // Add localStorage values to form data
    if (localStorage.getItem("upd_email_type")) {
      formData += "&upd_email_type=" + encodeURIComponent(localStorage.getItem("upd_email_type"));
    }
    if (localStorage.getItem("upd_email_subject")) {
      formData += "&upd_email_subject=" + encodeURIComponent(localStorage.getItem("upd_email_subject"));
    }
    if (localStorage.getItem("upd_course_opt")) {
      formData += "&upd_course_opt=" + encodeURIComponent(localStorage.getItem("upd_course_opt"));
    }
    if (localStorage.getItem("upd_event_opt")) {
      formData += "&upd_event_opt=" + encodeURIComponent(localStorage.getItem("upd_event_opt"));
    }
    if (localStorage.getItem("upd_event_id")) {
      formData += "&upd_event_id=" + encodeURIComponent(localStorage.getItem("upd_event_id"));
    }
    if (localStorage.getItem("upd_event_name")) {
      formData += "&upd_event_name=" + encodeURIComponent(localStorage.getItem("upd_event_name"));
    }
    if (localStorage.getItem("upd_email_opt")) {
      formData += "&upd_email_opt=" + encodeURIComponent(localStorage.getItem("upd_email_opt"));
    }
    if (localStorage.getItem("upd_temp_opt")) {
      formData += "&upd_temp_opt=" + encodeURIComponent(localStorage.getItem("upd_temp_opt"));
    }

    $.ajax({
      url: "includes/upd_system_email.inc.php",
      type: "POST",
      data: formData,
      success: function (response) {
        if (response == 1) {
          Swal.fire({
            icon: "success",
            title: "Success!",
            text: "The email has been updated successfully.",
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
            text: "There was an issue updating the email.",
            position: "top-end",
            showConfirmButton: true,
            confirmButtonText: "Close",
          }).then(() => {
            button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Update');
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
            button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Update');
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
          button.removeClass("loading").html('<i class="bi bi-check2" aria-hidden="true"></i> Update');
          button.prop("disabled", false);
        });
        console.log(error);
      },
    });
  });

  function initSummernote(selector) {
    $(selector)
      .summernote({
        height: 400,
        prettifyHtml: false,
        toolbar: [
          ["style", ["style"]],
          ["font", ["bold", "italic", "underline", "strikethrough", "superscript", "subscript", "clear"]],
          ["fontname", ["fontname"]],
          ["fontsize", ["fontsize"]],
          ["color", ["color"]],
          ["para", ["ol", "ul", "paragraph", "height"]],
          ["table", ["table"]],
          ["insert", ["link", "picture", "video", "shape"]],
          ["view", ["undo", "redo", "fullscreen", "codeview", "help"]],
        ],
        callbacks: {
          onChange: function (contents) { renderLivePreview(contents); },
          onKeyup: function () { renderLivePreview($(selector).summernote("code")); },
          onPaste: function () {
            var sel = selector;
            setTimeout(function () { renderLivePreview($(sel).summernote("code")); }, 30);
          },
        },
      })
      .summernote("code", "");
  }

  initSummernote("#upd_email_body");

  function isNotEmpty(caller) {
    caller.next(".error").remove();
    if (caller.val().trim() === "") {
      caller.css("border-color", "red");
      caller.after("<span class='error' style='position: absolute; right: 0; bottom: -3vh;'>This field is required.</span>");
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
      caller.after("<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>");
      caller.focus();
      return false;
    } else {
      caller.css("border-color", "");
      caller.next(".error").remove();
      return true;
    }
  }

  function selNotEmptyEvent(caller) {
    caller.next(".error").remove();
    var selectedVal = caller.find(":selected").val();
    if (selectedVal === "selected" || selectedVal === "" || !selectedVal) {
      caller.css("border-color", "red");
      caller.after("<span class='error' style='position: absolute; right: 0 !important; bottom: -2.5vh !important;'>This field is required.</span>");
      caller.focus();
      return false;
    } else {
      caller.css("border-color", "");
      caller.next(".error").remove();
      return true;
    }
  }
});