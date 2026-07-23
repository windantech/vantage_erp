jQuery(function ($) {
  $(".navbar-nav li").each(function () {
    var url = window.location.pathname;
    var page = url.substring(url.lastIndexOf("/") + 1);

    document.title = page
      .toLowerCase()
      .replace(/(^|\s)[a-z]/g, function (block) {
        return block.toUpperCase();
      });

    if (page === "") {
      $(".dash").addClass("active");
    } else if (page === "table_sample") {
      $(".transact").addClass("active");
    }

    const pageMaps = {
      task: {
        map: {
          task_dashboard: 1,
          projects: 2,
          view_project: 2,
          edit_task: 2,
          task_list: 3,
        },
        menuClass: ".task_manager",
        menuId: "#task_manager",
      },
      enquiry: {
        map: {
          get_in_touch: 1,
          call_request: 2,
          courses: 3,
          loaded_data: 4,
        },
        menuClass: ".enquiries",
        menuId: "#enquiries",
        inputSelector: "#from_input",
      },
      report: {
        map: {
          task_reports: 1,
          virtual_reports: 2,
          international_reports: 3,
        },
        menuClass: ".reports",
        menuId: "#reports",
      },
      lead: {
        map: {
          lead_forms: 1,
          form_data: 2,
          edit_lead_form: 3,
        },
        menuClass: ".lead_forms",
      },
      systemEmails: {
        map: {
          system_emails: 1,
          new_system_email: 1,
          view_system_emails: 1,
          edit_system_emails: 1,
        },
        menuClass: ".system_emails",
        menuId: "#system_emails",
      },
    };

    function activateMenu(page, inputSelector, menuClass, menuId, pageMap) {
      const pageOrInput =
        page || (inputSelector ? $(inputSelector).val() : null);
      if (pageMap[pageOrInput]) {
        $(menuClass).addClass("active");
        if (menuId) {
          $(menuId).addClass("show");
          const childIndex = pageMap[pageOrInput];
          $(`${menuId} a:nth-child(${childIndex})`)
            .addClass("bi-check-circle active_bg")
            .removeClass("bi-dash-circle");
        }
      }
    }

    Object.values(pageMaps).forEach(
      ({ map, menuClass, menuId, inputSelector }) => {
        activateMenu(page, inputSelector, menuClass, menuId, map);
      }
    );
  });
});
