$(document).ready(function () {
  function getVirtualLoader(chartType) {
    const loaderWrapper = `
      style="
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.8);
        z-index: 5;
      "
    `;

    if (chartType === "column" || chartType === "bar") {
      return `<div class="chart-loader bar-loader" ${loaderWrapper}>
                <span></span><span></span><span></span>
              </div>`;
    } else if (chartType === "line" || chartType === "spline") {
      return `<div class="chart-loader" ${loaderWrapper}>
                <div class="line-loader"></div>
              </div>`;
    } else if (chartType === "pie" || chartType === "doughnut") {
      return `<div class="chart-loader" ${loaderWrapper}>
                <div class="pie-loader"></div>
              </div>`;
    } else {
      return `<div class="chart-loader" ${loaderWrapper}>
                <div class="line-loader"></div>
              </div>`;
    }
  }

  // -------------------------------
  // For last 3 months charts
  // -------------------------------
  function createVirtualChartRecent(container, title, color, dataUrl, fontColor, backgroundColor, chartType = "column", noDataMsg = "No data available.") {
    const containerEl = $("#" + container);
    containerEl.html(getVirtualLoader(chartType));

    $.ajax({
      url: dataUrl,
      dataType: "json",
      success: function (data) {
        setTimeout(function () {
          containerEl.html("");

          // CASE 1: Server returned a message
          if (data.message) {
            containerEl.html(`
              <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <div class="alert alert-danger rounded-0" role="alert">
                  <i class="bi bi-exclamation-triangle"></i>
                  ${data.message}
                </div>
              </div>
            `);
            return;
          }

          // CASE 2: No data (all y = 0)
          const hasData = data.some((point) => point.y > 0);
          if (!hasData) {
            containerEl.html(`
              <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <div class="alert alert-danger rounded-0" role="alert">
                  <i class="bi bi-exclamation-triangle"></i>
                  ${noDataMsg}
                </div>
              </div>
            `);
            return;
          }

          // CASE 3: Normal chart
          const maxY = Math.max(...data.map((point) => point.y));
          const interval = Math.ceil(maxY / 10);

          new CanvasJS.Chart(container, {
            animationEnabled: true,
            theme: "light2",
            title: {
              fontSize: 14,
              fontWeight: "bold",
              fontFamily: "Arial, sans-serif",
              fontColor: fontColor,
              backgroundColor: backgroundColor,
              borderColor: "#000000",
              borderThickness: 2,
              padding: { top: 10, right: 20, bottom: 10, left: 20 },
              horizontalAlign: "center",
              text: title,
            },
            axisY: {
              interval: interval,
              maximum: maxY + interval,
              gridThickness: 1,
            },
            axisX: { labelAngle: -30 },
            data: [{ type: chartType, color: color, dataPoints: data }],
          }).render();
        }, 3000);
      },
      error: function () {
        containerEl.html(`
          <div class="w-100 h-100 d-flex align-items-center justify-content-center">
            <div class="alert alert-danger rounded-0" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              An error occurred while loading the data. Please try again later.
            </div>
          </div>
        `);
      },
    });
  }

  // -------------------------------
  // For year-based / intake charts
  // -------------------------------
  function createVirtualChartYearly(container, title, color, dataUrl, fontColor, backgroundColor, chartType = "column") {
    const containerEl = $("#" + container);
    containerEl.html(getVirtualLoader(chartType));

    $.ajax({
      url: dataUrl,
      dataType: "json",
      success: function (data) {
        setTimeout(function () {
          containerEl.html("");

          if (data.message) {
            containerEl.html(`
              <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <div class="alert alert-danger rounded-0" role="alert">
                  <i class="bi bi-exclamation-triangle"></i>
                  ${data.message}
                </div>
              </div>
            `);
            return;
          }

          if (!data.length) {
            containerEl.html(`
              <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <div class="alert alert-danger rounded-0" role="alert">
                  <i class="bi bi-exclamation-triangle"></i>
                  No data available for the selected filters.
                </div>
              </div>
            `);
            return;
          }

          const maxY = Math.max(...data.map((point) => point.y));
          const interval = Math.ceil(maxY / 10);

          new CanvasJS.Chart(container, {
            animationEnabled: true,
            theme: "light2",
            title: {
              fontSize: 14,
              fontWeight: "bold",
              fontFamily: "Arial, sans-serif",
              fontColor: fontColor,
              backgroundColor: backgroundColor,
              borderColor: "#000000",
              borderThickness: 2,
              padding: { top: 10, right: 20, bottom: 10, left: 20 },
              horizontalAlign: "center",
              text: title,
            },
            axisY: {
              interval: interval,
              maximum: maxY + interval,
              gridThickness: 1,
            },
            axisX: { labelAngle: -30 },
            data: [{ type: chartType, color: color, dataPoints: data }],
          }).render();
        }, 3000);
      },
      error: function () {
        containerEl.html(`
          <div class="w-100 h-100 d-flex align-items-center justify-content-center">
            <div class="alert alert-danger rounded-0" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              Failed to load yearly data. Please try again later.
            </div>
          </div>
        `);
      },
    });
  }

  // -------------------------------
  // INIT CHARTS
  // -------------------------------

  // Last 3 months charts
  createVirtualChartRecent(
    "virtual_enquiries_chart",
    "",
    "#ED7D31",
    "includes/virtual/enquiries_chart.php",
    "#000",
    "#ED7D31",
    "column",
    "No enquiries data available for the last 3 months."
  );

  createVirtualChartRecent(
    "virtual_clients_chart",
    "",
    "#5B9BD5",
    "includes/virtual/clients_chart.php",
    "#000",
    "#5B9BD5",
    "column",
    "No clients data available for the last 3 months."
  );

  createVirtualChartRecent(
    "virtual_fee_collected_chart",
    "",
    "#385723",
    "includes/virtual/fee_collected_chart.php",
    "#000",
    "#385723",
    "column",
    "No fee collection data available for the last 3 months."
  );

  createVirtualChartRecent(
    "virtual_fee_bal_chart",
    "",
    "#5B9BD5",
    "includes/virtual/fee_bal_chart.php",
    "#000",
    "#5B9BD5",
    "column",
    "No balance data available for the last 3 months."
  );

  function loadChart({ filterSelector, chartId, titleId, baseTitle, url, color, bgColor = "#FFF", lineColor = null, chartType = "column", }) {
    function render(description = "") {
      let cleanDescription = description ? description.replace(/Intake/gi, "").trim() : "";

      let title = cleanDescription
        ? `${cleanDescription} ${baseTitle}`
        : `All Intakes ${baseTitle}`;

      $(titleId).html(title);

      let fetchUrl = url;
      if (description) {
        fetchUrl += "?description=" + encodeURIComponent(description);
      }

      // pass everything to createVirtualChartYearly
      createVirtualChartYearly(
        chartId,
        baseTitle,
        color,
        fetchUrl,
        bgColor,
        lineColor || color,
        chartType
      );
    }

    render();

    $(filterSelector).on("change", function () {
      render($(this).val());
    });
  }
  loadChart({
  filterSelector: "#virtual_leads_enquiries_filter",
  chartId: "virtual_leads_enquiries_chart",
  titleId: "#virtual_leads_enquiries_chart_title",
  baseTitle: "Intake Leads/Enquiries",
  url: "includes/virtual/virtual_leads_enquiries_chart.php",
  color: "#ED7D31",
  bgColor: "#000",
  lineColor: "#ED7D31",
  chartType: "column",
});

  loadChart({
    filterSelector: "#virtual_customers_filter",
    chartId: "virtual_customers_chart",
    titleId: "#virtual_customers_chart_title",
    baseTitle: "Intake Customers",
    url: "includes/virtual/virtual_customers_chart.php",
    color: "#FFC000",
    bgColor: "#000",
    lineColor: "#FFC000",
    chartType: "column",
  });

  loadChart({
    filterSelector: "#virtual_total_leads_filter",
    chartId: "virtual_total_leads_chart",
    titleId: "#virtual_total_leads_chart_title",
    baseTitle: "LEADS",
    url: "includes/virtual/virtual_total_leads_chart.php",
    color: "#ED7D31",
    bgColor: "#000",
    lineColor: "#ED7D31",
    chartType: "spline",
  });

  loadChart({
    filterSelector: "#virtual_total_customers_filter",
    chartId: "virtual_total_customers_chart",
    titleId: "#virtual_total_customers_chart_title",
    baseTitle: "Customers",
    url: "includes/virtual/virtual_total_customers_chart.php",
    color: "#012970",
    bgColor: "#000",
    lineColor: "#ED7D31",
    chartType: "spline",
  });

  // Intake Fee Balances Chart
  loadChart({
    filterSelector: "#virtual_intake_fee_bal_filter",
    chartId: "virtual_intake_fee_bal_chart",
    titleId: "#virtual_intake_fee_bal_chart_title",
    baseTitle: "Intake Fee Balances in Kshs",
    url: "includes/virtual/virtual_intake_fee_bal_chart.php",
    color: "#7B7B7B",
    chartType: "column",
  });

  // Intake Revenue Chart
  loadChart({
    filterSelector: "#virtual_intake_revenue_filter",
    chartId: "virtual_revenue_chart",
    titleId: "#virtual_intake_revenue_chart_title",
    baseTitle: "Intake Revenue in Kshs",
    url: "includes/virtual/virtual_revenue_chart.php",
    color: "#7030A0",
    chartType: "column",
  });

});
