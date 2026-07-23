$(document).ready(function () {
  function getEnqLoader(chartType) {
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
  function createEnqChartRecent(container, title, color, dataUrl, fontColor, backgroundColor, chartType = "column", noDataMsg = "No data available.") {
    const containerEl = $("#" + container);
    containerEl.html(getEnqLoader(chartType));

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

  function createScrollableEnqChart(
    container,
    title,
    color,
    dataUrl,
    fontColor,
    backgroundColor,
    chartType = "column",
    noDataMessage = "No data available.",
    visibleBars = 8,   // how many fit before scroll
    pxPerBar = 80      // bar width
  ) {
    const containerEl = $("#" + container);
    const wrapperEl = containerEl.parent(); // must have overflow:auto
    containerEl.html(getEnqLoader(chartType));

    $.ajax({
      url: dataUrl,
      dataType: "json",
      success: function (data) {
        containerEl.empty();
        if (data.message || !data.length) {
          containerEl.html(`
          <div class="w-100 h-100 d-flex align-items-center justify-content-center">
            <div class="alert alert-danger rounded-0" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              ${data.message || noDataMessage}
            </div>
          </div>
        `);
          return;
        }

        const maxY = Math.max(...data.map(p => p.y));
        const interval = Math.ceil(maxY / 10) || 1;

        // decide chart width
        let targetPx = wrapperEl.width();
        if (data.length > visibleBars) {
          targetPx = data.length * pxPerBar;
        }

        containerEl.css("width", targetPx + "px");

        const chart = new CanvasJS.Chart(container, {
          width: targetPx,
          height: 380,
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
          axisY: { interval, maximum: maxY + interval, gridThickness: 1 },
          axisX: { labelAngle: -30, interval: 1, labelFontSize: 12 },
          data: [{ type: chartType, color, dataPoints: data }],
        });

        chart.render();
      },
      error: function () {
        containerEl.html(`
        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
          <div class="alert alert-danger rounded-0" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            Failed to load chart data. Please try again later.
          </div>
        </div>
      `);
      },
    });
  }

  function createEnqChartYearlyNoScroll(
    container,
    title,
    color,
    dataUrl,
    fontColor,
    backgroundColor,
    chartType = "column",
    noDataMessage = "No data available."
  ) {
    const containerEl = $("#" + container);
    containerEl.html(getEnqLoader(chartType));

    $.ajax({
      url: dataUrl,
      dataType: "json",
      success: function (data) {
        containerEl.empty();
        if (data.message || !data.length) {
          containerEl.html(`
          <div class="w-100 h-100 d-flex align-items-center justify-content-center">
            <div class="alert alert-danger rounded-0" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              ${data.message || noDataMessage}
            </div>
          </div>
        `);
          return;
        }

        const maxY = Math.max(...data.map(p => p.y));

        const chart = new CanvasJS.Chart(container, {
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
            includeZero: true,
            maximum: maxY + Math.ceil(maxY / 5),
            interval: Math.ceil(maxY / 5),
            gridThickness: 1,
            labelFormatter: function (e) {
              if (e.value >= 1000000) return (e.value / 1000000) + "M";
              if (e.value >= 1000) return (e.value / 1000) + "K";
              return e.value;
            }
          },
          axisX: { labelAngle: -30, interval: 1, labelFontSize: 12 },
          data: [{ type: chartType, color, dataPoints: data }],
        });

        chart.render();
      },
      error: function () {
        containerEl.html(`
        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
          <div class="alert alert-danger rounded-0" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            Failed to load chart data. Please try again later.
          </div>
        </div>
      `);
      },
    });
  }


  createEnqChartRecent(
    "enquiries_enquiries_chart",
    "",
    "#ED7D31",
    "includes/international/enquiries_chart.php",
    "#000000",
    "#ED7D31",
    "column",
    "No enquiries data available for the last 3 months."
  );

  createEnqChartRecent(
    "enquiries_clients_chart",
    "",
    "#5B9BD5",
    "includes/international/clients_chart.php",
    "#000000",
    "#5B9BD5",
    "column",
    "No clients data available for the last 3 months."
  );

  createEnqChartRecent(
    "enquiries_fee_collected_chart",
    "",
    "#385723",
    "includes/international/fee_collected_chart.php",
    "#000000",
    "#385723",
    "column",
    "No fee collection data available for the last 3 months."
  );

  createEnqChartRecent(
    "enquiries_fee_bal_chart",
    "",
    "#5B9BD5",
    "includes/international/fee_bal_chart.php",
    "#000000",
    "#5B9BD5",
    "column",
    "No balance data available for the last 3 months."
  );



  createScrollableEnqChart(
    "enquiries_leads_enquiries_chart",
    "Intake Leads/Enquiries",
    "#ED7D31",
    "includes/international/enquiries_leads_enquiries_chart.php",
    "#000000",
    "#ED7D31",
    "column",
    "No Leads/Enquiries data available for the last 3 months.",
    8,
    120
  );

  createScrollableEnqChart(
    "enquiries_customers_chart",
    "Intake Customers",
    "#FFC000",
    "includes/international/enquiries_customers_chart.php",
    "#000000",
    "#FFC000",
    "column",
    "No customers found.",
    8,
    120
  );

  const currentYear = new Date().getFullYear();
  $("#enquiries_total_leads_chart")
    .closest(".card")
    .find(".card-title")
    .text(currentYear + " LEADS");

  $("#enquiries_total_customers_chart")
    .closest(".card")
    .find(".card-title")
    .text(currentYear + " CUSTOMERS");

  createEnqChartYearlyNoScroll(
    "enquiries_total_leads_chart",
    currentYear + " LEADS",
    "#ED7D31",
    "includes/international/enquiries_total_leads_chart.php",
    "#000000",
    "#ED7D31",
    "spline",
    ""
  );

  createEnqChartYearlyNoScroll(
    "enquiries_total_customers_chart",
    currentYear + " CUSTOMERS",
    "#FFC000",
    "includes/international/enquiries_total_customers_chart.php",
    "#000000",
    "#FFC000",
    "spline",
    ""
  );

  createScrollableEnqChart(
    "enquiries_revenue_chart",
    "Intake Revenue in Kshs",
    "#7030A0",
    "includes/international/enquiries_revenue_chart.php",
    "#FFF",
    "#7030A0",
    "column",
    "No Intake Revenue found.",
    8,
    120
  );

  createScrollableEnqChart(
    "enquiries_intake_fee_bal_chart",
    "Intake Fee Balances in Kshs",
    "#7B7B7B",
    "includes/international/enquiries_intake_fee_bal_chart.php",
    "#FFF",
    "#7B7B7B",
    "column",
    "No Intake Fee Balances Found.",
    8,
    120
  );
});
