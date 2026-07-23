!(function (l) {
  "use strict";
  l("#sidebarToggle, #sidebarToggleTop").on("click", function (e) {
    l("body").toggleClass("sidebar-toggled"),
      l(".sidebar").toggleClass("toggled"),
      l(".sidebar").hasClass("toggled") &&
        l(".sidebar .collapse").collapse("hide");
  }),
    l(window).resize(function () {
      l(window).width() < 768 && l(".sidebar .collapse").collapse("hide"),
        l(window).width() < 480 &&
          !l(".sidebar").hasClass("toggled") &&
          (l("body").addClass("sidebar-toggled"),
          l(".sidebar").addClass("toggled"),
          l(".sidebar .collapse").collapse("hide"));
    }),
    l("body.fixed-nav .sidebar").on(
      "mousewheel DOMMouseScroll wheel",
      function (e) {
        var o;
        768 < l(window).width() &&
          ((o = (o = e.originalEvent).wheelDelta || -o.detail),
          (this.scrollTop += 30 * (o < 0 ? 1 : -1)),
          e.preventDefault());
      }
    ),
    l(document).on("scroll", function () {
      100 < l(this).scrollTop()
        ? l(".scroll-to-top").fadeIn()
        : l(".scroll-to-top").fadeOut();
    }),
    l(document).on("click", "a.scroll-to-top", function (e) {
      var o = l(this);
      l("html, body")
        .stop()
        .animate(
          { scrollTop: l(o.attr("href")).offset().top },
          1e3,
          "easeInOutExpo"
        ),
        e.preventDefault();
    });
})(jQuery);

$(document).ready(function () {
  function initializeSummernote(selector) {
    $(selector).summernote({
      height: 200,
      toolbar: [
        ["style", ["style"]],
        [
          "font",
          [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "superscript",
            "subscript",
            "clear",
          ],
        ],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ol", "ul", "paragraph", "height"]],
        ["table", ["table"]],
         ['insert', ['link', 'picture', 'video']],
        ["view", ["undo", "redo", "fullscreen", "codeview", "help"]],
      ],
    });
  }

  initializeSummernote("#summernote");
  initializeSummernote("#summernote1");
  initializeSummernote("#summernote2");
  initializeSummernote("#summernote3");

  $(".select2").select2({
    placeholder: "Type to filter....",
    width: "100%",
  });
});

$(".reports_body .nav-link").on("click", function () {
  $(".reports_body .nav-link").removeClass("active");
  $(this).addClass("active");
});

$(document).ready(function () {
  $("#summernote5").summernote({
    height: 200,
    toolbar: [
        ["style", ["style"]],
        [
            "font",
            [
                "bold",
                "italic",
                "underline",
                "strikethrough",
                "superscript",
                "subscript",
                "clear",
            ],
        ],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ol", "ul", "paragraph", "height"]],
        ["table", ["table"]],
        ['insert', ['link', 'picture', 'video', 'shape']],  // Shape button
        ["view", ["undo", "redo", "fullscreen", "codeview", "help"]],
    ],
    buttons: {
        shape: function (context) {
            var ui = $.summernote.ui;
            var button = ui.button({
                contents: '<i class="bi bi-shape"></i> Shape',
                tooltip: 'Insert Shape',
                click: function (e) {
                    e.preventDefault();

                    // Shape selection using SweetAlert
                    Swal.fire({
                        title: 'Select a Shape',
                        input: 'select',
                        inputOptions: {
                            'rectangle': 'Rectangle',
                            'circle': 'Circle',
                            'line': 'Line'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Next',
                    }).then((shapeResult) => {
                        if (shapeResult.isConfirmed) {
                            var shapeType = shapeResult.value;

                            // Prompt for dimensions, color, and rounded corners (corner radius)
                            Swal.fire({
                                title: 'Set Dimensions, Color, and Rounded Corners',
                                html: `
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="basic-addon1">Width</span>
                                        <input type="number" id="shapeWidth" class="form-control" placeholder="Width (px)" value="100" aria-label="Width" aria-describedby="basic-addon1">
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="basic-addon2">Height</span>
                                        <input type="number" id="shapeHeight" class="form-control" placeholder="Height (px)" value="50" aria-label="Height" aria-describedby="basic-addon2">
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="basic-addon3">Color</span>
                                        <input type="color" id="shapeColor" class="form-control" value="#ff0000" aria-label="Color" aria-describedby="basic-addon3">
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="basic-addon4">Text</span>
                                        <input type="text" id="shapeText" class="form-control" placeholder="Text inside shape" aria-label="Text" aria-describedby="basic-addon4">
                                    </div>
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="basic-addon5">Corner Radius</span>
                                        <input type="number" id="cornerRadius" class="form-control" placeholder="Corner Radius (px)" value="10" aria-label="Corner Radius" aria-describedby="basic-addon5">
                                    </div>
                                `,
                                confirmButtonText: 'Insert Shape',
                                showCancelButton: true,
                                preConfirm: () => {
                                    var shapeWidth = $('#shapeWidth').val();
                                    var shapeHeight = $('#shapeHeight').val();
                                    var shapeColor = $('#shapeColor').val();
                                    var shapeText = $('#shapeText').val();
                                    var cornerRadius = $('#cornerRadius').val();

                                    // Create the shape HTML based on user input
                                    var shapeHTML = '';
                                    var borderRadiusStyle = cornerRadius > 0 ? `border-radius: ${cornerRadius}px;` : ''; // Apply corner radius if greater than 0
                                    if (shapeType === 'rectangle') {
                                        shapeHTML = `<div class="shape rectangle" style="width: ${shapeWidth}px; height: ${shapeHeight}px; background-color: ${shapeColor}; color: white; text-align: center; line-height: ${shapeHeight}px; ${borderRadiusStyle} cursor: pointer;">${shapeText}</div>`;
                                    } else if (shapeType === 'circle') {
                                        shapeHTML = `<div class="shape circle" style="width: ${shapeWidth}px; height: ${shapeWidth}px; background-color: ${shapeColor}; color: white; border-radius: 50%; text-align: center; line-height: ${shapeWidth}px; ${borderRadiusStyle} cursor: pointer;">${shapeText}</div>`;
                                    } else if (shapeType === 'line') {
                                        shapeHTML = `<div class="shape line" style="width: ${shapeWidth}px; height: 2px; background-color: ${shapeColor}; cursor: pointer;"></div>`;
                                    }

                                    // Insert the shape into the Summernote editor
                                    var shapeElement = $(shapeHTML)[0];
                                    context.invoke('editor.insertNode', shapeElement);
                                }
                            });
                        }
                    });
                }
            });
            return button.render();
        },
    },
    callbacks: {
        onChange: function (contents, $editable) {
            $("#body_email").html(contents);
        },
    },
});

});

