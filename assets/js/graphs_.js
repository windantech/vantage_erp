function loadChart(url, dateFromSelector, dateToSelector, chartContainer, titleBase) {
    var dateFrom = $(dateFromSelector).val();
    var dateTo = $(dateToSelector).val();

    $.ajax({
        url: url,
        type: "GET",
        data: {
            date_from: dateFrom,
            date_to: dateTo
        },
        success: function(data) {
            var dataPoints = JSON.parse(data);

            var titleText = titleBase;
            if (dateFrom && dateTo) {
                var fromDate = new Date(dateFrom);
                var toDate = new Date(dateTo);
                var options = { year: 'numeric', month: 'long' };
                var fromMonthYear = fromDate.toLocaleDateString('en-US', options);
                var toMonthYear = toDate.toLocaleDateString('en-US', options);
                titleText = (fromMonthYear === toMonthYear)
                    ? `${fromMonthYear} ${titleBase}`
                    : `${titleBase} from ${fromMonthYear} to ${toMonthYear}`;
            }

            var chartOptions = {
                animationEnabled: true,
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    text: titleText,
                    fontSize: 24,
                    fontFamily: "Arial",
                    fontColor: "#202938",
                    fontWeight: "bold",
                    borderColor: "#202938",
                    borderThickness: 2,
                    padding: 10,
                },
                axisX: {
                    labelAutoFit: true,
                    labelWrap: true,
                    labelMaxWidth: 60,
                    labelAngle: -45,
                    interval: 1
                },
                data: [{
                    type: "column",
                    yValueFormatString: "#,##0",
                    dataPoints: dataPoints,
                    dataPointWidth: 25 // Adjust for 30+ bars
                }]
            };

            if (!["chartContainer5", "chartContainer6", "chartContainer7"].includes(chartContainer)) {
                chartOptions.axisY = {
                    title: "Number of " + titleBase
                };
            }

            $("#" + chartContainer).css("width", (dataPoints.length * 40) + "px"); // Dynamic width
            var chart = new CanvasJS.Chart(chartContainer, chartOptions);
            chart.render();

            $(window).off("resize").on("resize", function() {
                chart.render();
            });
        }
    });
}

$(document).ready(function() {
    const reports = [
        {btn: '#report1_btn', url: 'loads_/graph1.php', from: '#dateFrom', to: '#dateTo', container: 'chartContainer', title: 'Intake Leads/Enquiries'},
        {btn: '#report2_btn', url: 'loads_/graph2.php', from: '#dateFrom2', to: '#dateTo2', container: 'chartContainer2', title: 'Intake Customers'},
        {btn: '#report4_btn', url: 'loads_/graph4.php', from: '#dateFrom4', to: '#dateTo4', container: 'chartContainer4', title: 'Intake Revenue(USD)'},
        {btn: '#report5_btn', url: 'loads_/graph5.php', from: '#dateFrom5', to: '#dateTo5', container: 'chartContainer5', title: 'Intake Fee Balance in USD'},
        {btn: '#report6_btn', url: 'loads_/graph6.php', from: '#dateFrom6', to: '#dateTo6', container: 'chartContainer6', title: 'Intake Revenue in USD'},
        {btn: '#report7_btn', url: 'loads_/graph7.php', from: '#dateFrom7', to: '#dateTo7', container: 'chartContainer7', title: 'Conversion Rate(%)'}
    ];

    reports.forEach(report => {
        $(report.btn).click(function() {
            loadChart(report.url, report.from, report.to, report.container, report.title);
        });
        loadChart(report.url, report.from, report.to, report.container, report.title);
    });

    const collapses = [
        {collapse: '#collapseOne', url: 'loads_/graph1.php', from: '#dateFrom', to: '#dateTo', container: 'chartContainer', title: 'Intake Leads/Enquiries'},
        {collapse: '#collapseTwo', url: 'loads_/graph2.php', from: '#dateFrom2', to: '#dateTo2', container: 'chartContainer2', title: 'Intake Customers'},
        {collapse: '#collapseFour', url: 'loads_/graph4.php', from: '#dateFrom4', to: '#dateTo4', container: 'chartContainer4', title: 'Intake Revenue (USD)'},
        {collapse: '#collapseFive', url: 'loads_/graph5.php', from: '#dateFrom5', to: '#dateTo5', container: 'chartContainer5', title: 'Intake Fee Balance in USD'},
        {collapse: '#collapseSix', url: 'loads_/graph6.php', from: '#dateFrom6', to: '#dateTo6', container: 'chartContainer6', title: 'Conversion Rate'},
        {collapse: '#collapseSeven', url: 'loads_/graph7.php', from: '#dateFrom7', to: '#dateTo7', container: 'chartContainer7', title: 'Conversion Rate(%)'}
    ];

    collapses.forEach(collapse => {
        $(collapse.collapse).on('shown.bs.collapse', function () {
            loadChart(collapse.url, collapse.from, collapse.to, collapse.container, collapse.title);
        });
    });

    // Year-based chart initialization
    const currentYear = new Date().getFullYear();

    function loadData(year, url, containerId, titleText, yAxisTitle) {
        $.getJSON(url, { year: year }, function(data) {
            $("#" + containerId).css("width", (data.length * 40) + "px");
            var chart = new CanvasJS.Chart(containerId, {
                title: {
                    text: year + " " + titleText,
                    fontSize: 24,
                    fontFamily: "Arial",
                    fontColor: "#202938",
                    fontWeight: "bold",
                    borderColor: "#202938",
                    borderThickness: 2,
                    padding: 10,
                },
                axisY: {
                    title: yAxisTitle
                },
                data: [{
                    type: "column",
                    dataPoints: data,
                    dataPointWidth: 25
                }]
            });
            chart.render();
        });
    }

    function initializeChart(url, containerId, titleText, yAxisTitle) {
        loadData(currentYear, url, containerId, titleText, yAxisTitle);
    }

    function setupButton(buttonId, inputId, url, containerId, titleText, yAxisTitle) {
        $(buttonId).click(function() {
            var year = $(inputId).val() || currentYear;
            loadData(year, url, containerId, titleText, yAxisTitle);
        });
    }

    // Initialize default yearly chart
    initializeChart("loads_/graph3.php", "chartContainerYear1", "Leads", "Number of Leads");

    setupButton("#report_year1_btn", "#yearInput1", "loads_/graph3.php", "chartContainerYear1", "Leads", "Number of Leads");

    // Collapse handling for year chart
    $('#collapseThree').on('shown.bs.collapse', function () {
        initializeChart("loads_/graph3.php", "chartContainerYear1", "Leads", "Number of Leads");
    });
});
