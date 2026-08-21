$(document).ready(function () {
    // Function to create a chart with Ajax data
    function createChart(container, title, color, dataUrl, fontColor, backgroundColor) {
        // Nothing to draw on. This file is loaded by footer.php on EVERY admin
        // page, so on all but the dashboards these containers do not exist and
        // CanvasJS threw "Chart Container with id ... was not found", followed
        // by a TypeError that stopped the rest of this file running.
        //
        // Checked before the request, not after: four AJAX calls per page whose
        // results can never be displayed is bandwidth nobody asked for.
        if (!document.getElementById(container)) { return; }

        $.ajax({
          url: dataUrl,
          dataType: "json",
          success: function (data) {
            // If there's no data, show a message inside the chart
            if (data.message) {
              new CanvasJS.Chart(container, {
                animationEnabled: true,
                theme: "light2",
                title: {
                  fontSize: 12,
                  fontWeight: "bold",
                  fontFamily: "Arial, sans-serif",
                  fontColor: fontColor,
                  backgroundColor: backgroundColor,
                  borderColor: "#000000",
                  borderThickness: 2,
                  padding: { top: 20, right: 50, bottom: 20, left: 50 },
                  horizontalAlign: "center",
                  text: `${data.message}`,  // Display the message inside the chart
                },
                axisY: {
                  interval: 10,  // Setting an arbitrary value for the interval to prevent errors
                  maximum: 100,  // Maximum value set arbitrarily
                  gridThickness: 1,
                },
                axisX: { title: "", labelAngle: -30 },
                data: []  // No data to display
              }).render();
            } else {
              const maxY = Math.max(...data.map((point) => point.y));
              const interval = Math.ceil(maxY / 10);
      
              new CanvasJS.Chart(container, {
                animationEnabled: true,
                theme: "light2",
                title: {
                  fontSize: 12,
                  fontWeight: "bold",
                  fontFamily: "Arial, sans-serif",
                  fontColor: fontColor,
                  backgroundColor: backgroundColor,
                  borderColor: "#000000",
                  borderThickness: 2,
                  padding: { top: 20, right: 50, bottom: 20, left: 50 },
                  horizontalAlign: "center",
                  text: title,
                },
                axisY: {
                  interval: interval,
                  maximum: maxY + interval,
                  gridThickness: 1,
                },
                axisX: { title: "", labelAngle: -30 },
                data: [{ type: "column", color: color, dataPoints: data }],
              }).render();
            }
          },
          error: function () {
            console.error("Error loading data.");
            new CanvasJS.Chart(container, {
              animationEnabled: true,
              theme: "light2",
              title: {
                fontSize: 12,
                fontWeight: "bold",
                fontFamily: "Arial, sans-serif",
                fontColor: fontColor,
                backgroundColor: backgroundColor,
                borderColor: "#000000",
                borderThickness: 2,
                padding: { top: 20, right: 50, bottom: 20, left: 50 },
                horizontalAlign: "center",
                text: "An error occurred while loading the data.",  // Error message inside the chart
              },
              axisY: {
                interval: 10,
                maximum: 100,
                gridThickness: 1,
              },
              axisX: { title: "", labelAngle: -30 },
              data: [],
            }).render();
          },
        });
      }
      
  
    // Generic function to handle date change and chart rendering
    function handleDateChange(inputId, chartContainer, chartTitlePrefix, dataUrlPrefix) {
      $(`#${inputId}`).on("change", function () {
        const selectedDate = $(this).val();
        if (selectedDate) {
          const date = new Date(selectedDate);
  
          if (isNaN(date.getTime())) {
            Swal.fire({
              icon: "error",
              position: "top-end",
              title: "Invalid Date",
              text: "The selected date is invalid. Please choose a valid month.",
              confirmButtonText: "Okay",
              confirmButtonColor: "#FF5733",
            });
            return;
          }
  
          const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
          ];
          const month = monthNames[date.getMonth()];
          const year = date.getFullYear();
  
          const dynamicTitle = `${month} ${year} ${chartTitlePrefix}`;
          const dataUrl = `${dataUrlPrefix}?date=${selectedDate}`;
  
          createChart(chartContainer, dynamicTitle, "#7B7B7B", dataUrl, "#FFF", "#7B7B7B");
        } else {
          Swal.fire({
            icon: "warning",
            position: "top-end",
            title: "No Month Selected",
            text: "Please select a valid month.",
            confirmButtonText: "Okay",
            confirmButtonColor: "#FF5733",
          });
        }
      });
    }
  
    // Get the current month and year in words
    const currentDate = new Date();
    const currentMonthIndex = currentDate.getMonth(); // Get current month (0-11)
    const currentYear = currentDate.getFullYear();    // Get current year (e.g. 2024)
  
    // Array of month names
    const monthNames = [
      "January", "February", "March", "April", "May", "June",
      "July", "August", "September", "October", "November", "December"
    ];
  
    // Get the current month name
    const currentMonth = monthNames[currentMonthIndex];
  
    // Create chart titles and URLs with dynamic current month and year in words
    const fixedMonthYear = `${currentYear}-${('0' + (currentMonthIndex + 1)).slice(-2)}`; // Format to yyyy-mm
  
    // Use the current month and year in words for all charts
    createChart(
      "customerChart",
      `${currentMonth} ${currentYear} Intake Customers`,
      "#FFC000",
      `loads/customerChart.php?date=${fixedMonthYear}`,
      "#000000",
      "#FFC000"
    );
  
    createChart(
      "leadChart",
      `${currentMonth} ${currentYear} Intake Leads/Enquiries`,
      "#FF7F0E",
      `loads/leadChart.php?date=${fixedMonthYear}`,
      "#000000",
      "#FF7F0E"
    );
  
    createChart(
      "revenueChart",
      `${currentMonth} ${currentYear} Intake Revenue in USD`,
      "#7030A0",
      `loads/revenueChart.php?date=${fixedMonthYear}`,
      "#FFF",
      "#7030A0"
    );
  
    createChart(
      "balChart",
      `${currentMonth} ${currentYear} Intake Fee Balances in USD`,
      "#7B7B7B",
      `loads/balChart.php?date=${fixedMonthYear}`,
      "#FFF",
      "#7B7B7B"
    );
  
    // Handle the "Customer" date change
    handleDateChange("customer_input", "customerChart", "Intake Customers", "loads/customerChart.php");
  
    // Handle the "Lead" date change
    handleDateChange("lead_input", "leadChart", "Intake Leads/Enquiries", "loads/leadChart.php");
  
    // Handle the "Revenue" date change
    handleDateChange("rev_input", "revenueChart", "Intake Revenue in USD", "loads/revenueChart.php");
  
    // Handle the "Fee Balances" date change
    handleDateChange("bal_input", "balChart", "Intake Fee Balances in USD", "loads/balChart.php");
  });
  