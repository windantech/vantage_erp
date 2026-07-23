jQuery(function ($) {
  const url = window.location.pathname;
  let page = url.substring(url.lastIndexOf("/") + 1) || "./";

  // Update document title based on page
  document.title = page
    .replace(".php", "")
    .replace(/_/g, " ")
    .replace("./", "Virtual Reports")
    .replace(/\b\w/g, (char) => char.toUpperCase());

  // Auto-highlight sidebar link based on href
  $(".nav-item a.nav-link").each(function () {
    const link = $(this).attr("href");
    if (link === page || (page === "./" && link === "index.php")) {
      $(this).closest(".nav-item").addClass("active");
    }
  });
});
