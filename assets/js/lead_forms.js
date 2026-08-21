// This file is loaded on every admin page but only lead_forms.php has these
// elements. The first line used to call addEventListener on null, which threw
// and stopped everything below it being defined, so on the one page that DOES
// need this file, an error here took the whole of it out.
var fieldTypeEl = document.getElementById("fieldType");
if (fieldTypeEl) fieldTypeEl.addEventListener("change", function () {
  const fieldType = this.value;
  const selectOptionsGroup = document.getElementById("selectOptionsGroup");
  if (fieldType === "select") {
    selectOptionsGroup.style.display = "block";
  } else {
    selectOptionsGroup.style.display = "none";
  }
});

var addFieldEl = document.getElementById("addField");
if (addFieldEl) addFieldEl.addEventListener("click", function () {
  const fieldName = document.getElementById("fieldName").value;
  const fieldType = document.getElementById("fieldType").value;
  const selectOptions = document
    .getElementById("selectOptions")
    .value.split(",");

  if (!fieldName) {
    Swal.fire({
      position: "top-end",
      icon: "warning",
      title: "Field name is required!",
      showConfirmButton: true,
    });
    return;
  }

  if (!fieldType) {
    Swal.fire({
      position: "top-end",
      icon: "warning",
      title: "Field type is required!",
      showConfirmButton: true,
    });
    return;
  }

  const form = document.getElementById("dynamicForm");
  const formGroup = document.createElement("div");
  formGroup.classList.add("form-group");
  formGroup.classList.add("border-bottom");
  formGroup.classList.add("pb-2");

  const label = document.createElement("label");
  label.textContent = fieldName;
  formGroup.appendChild(label);

  let input;
  if (fieldType === "textarea") {
    input = document.createElement("textarea");
  } else if (fieldType === "select") {
    input = document.createElement("select");
    selectOptions.forEach((option) => {
      const opt = document.createElement("option");
      opt.value = option.trim();
      opt.textContent = option.trim();
      input.appendChild(opt);
    });
  } else {
    input = document.createElement("input");
    input.type = fieldType;
  }
  input.name = fieldName.toLowerCase().replace(/\s+/g, "_");
  input.required = true;
  formGroup.appendChild(input);

  const removeButton = document.createElement("button");
  removeButton.textContent = "Remove";
  removeButton.classList.add("remove-btn");
  removeButton.classList.add("btn");
  removeButton.classList.add("btn-danger");
  removeButton.classList.add("rounded-0");
  removeButton.type = "button";
  removeButton.onclick = function () {
    form.removeChild(formGroup);
  };
  formGroup.appendChild(removeButton);

  form.appendChild(formGroup);
  document.getElementById("fieldName").value = "";
  document.getElementById("selectOptions").value = "";
});

var submitFormEl = document.getElementById("submitForm");
if (submitFormEl) submitFormEl.addEventListener("click", function () {
  if (isNotEmpty($("#form_title")) && isNotEmpty($("#summernote")) && isNotEmpty($("#summernote1"))) {
    const formData = [];
    const generated_fields = document.querySelectorAll(
      "#dynamicForm .form-group"
    );

    if ($("#dynamicForm .form-group").text().trim().length > 0) {
      generated_fields.forEach((group) => {
        const label = group.querySelector("label").textContent;
        const input = group.querySelector("input, select, textarea");

        let fieldOptions = [];
        if (input.tagName.toLowerCase() === "select") {
          const options = input.querySelectorAll("option");
          options.forEach((option) => {
            fieldOptions.push({
              value: option.value,
              text: option.textContent,
            });
          });
        }

        formData.push({
          name: label,
          type: input.tagName.toLowerCase(),
          field_type: input.type || "select",
          options: fieldOptions,
        });
      });

      const hiddenInput = document.createElement("input");
      hiddenInput.type = "hidden";
      hiddenInput.name = "generated_fields";
      hiddenInput.value = JSON.stringify(formData);
      document.getElementById("dynamicForm").appendChild(hiddenInput);

      document.getElementById("dynamic_submit").submit();
    } else {
      Swal.fire({
        position: "top-end",
        title: "No Forms Generated!",
        text: "Please generate lead form and retry.",
        showConfirmButton: true,
      });
    }
  }
});

function copyLink(id) {
  var link = document.getElementById("link_" + id).value;
  navigator.clipboard.writeText(link).then(
    function () {
      Swal.fire({
        position: "top-end",
        icon: "success",
        title: "Link copied to clipboard",
        showConfirmButton: true,
      });
    },
    function (err) {
      Swal.fire({
        position: "top-end",
        icon: "error",
        title: "Failed to copy link",
        showConfirmButton: true,
      });
    }
  );
}

$(document).on('click', '.del_form', function () {
  var d_id = $(this).data("id");

  const swalWithBootstrapButtons = Swal.mixin({
      customClass: {
          confirmButton: "btn btn-success mx-1 rounded-0",
          cancelButton: "btn btn-danger mx-1 rounded-0",
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
              var data = {
                  d_id: d_id,
              };

              $.ajax({
                  url: "includes/del_lead.inc.php",
                  type: "post",
                  data: data,
                  success: function (response) {
                      if (response == "0") {
                          swalWithBootstrapButtons
                              .fire({
                                  title: "Success!",
                                  text: "Record deleted Successfully.",
                                  icon: "success",
                              })
                              .then(() => {
                                  window.location.href = "";
                              });
                      } else if (response == "1") {
                          swalWithBootstrapButtons
                              .fire({
                                  title: "Failed!",
                                  text: "Failed to delete record",
                                  icon: "error",
                              })
                              .then(() => {
                                  window.location.href = "";
                              });
                      }
                  },
              });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
              swalWithBootstrapButtons.fire({
                  title: "Cancelled",
                  text: "Your record is safe",
                  icon: "error",
              });
          }
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
