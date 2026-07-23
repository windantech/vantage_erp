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
                if (fromMonthYear === toMonthYear) {
                    titleText = fromMonthYear + " " + titleBase;
                } else {
                    titleText = titleBase + " from " + fromMonthYear + " to " + toMonthYear;
                }
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
                data: [{
                    type: "column",
                    yValueFormatString: "#,##0",
                    dataPoints: dataPoints
                }]
            };

            // Conditionally add axisY title
            if (chartContainer !== "chartContainer5" && chartContainer !== "chartContainer6" && chartContainer !== "chartContainer7") {
                chartOptions.axisY = {
                    // title: "Number of " + titleBase
                    title: titleBase
                };
            }

            var chart = new CanvasJS.Chart(chartContainer, chartOptions);
            chart.render();

            // Ensure the chart resizes correctly
            $(window).resize(function() {
                chart.render();
            });
        }
    });
}

$(document).ready(function() {
    const reports = [
        {btn: '#report1_btn', url: 'loads/graph1.php', from: '#dateFrom', to: '#dateTo', container: 'chartContainer', title: ' Leads/Enquiries '},
        {btn: '#report4_btn', url: 'loads/graph4.php', from: '#dateFrom4', to: '#dateTo4', container: 'chartContainer4', title: ' Revenue(USD) '},
        {btn: '#report2_btn', url: 'loads/graph2.php', from: '#dateFrom2', to: '#dateTo2', container: 'chartContainer2', title: ' Customers'},
        {btn: '#report5_btn', url: 'loads/graph5.php', from: '#dateFrom5', to: '#dateTo5', container: 'chartContainer5', title: ' Fee Balance in USD'},
        {btn: '#report6_btn', url: 'loads/graph6.php', from: '#dateFrom6', to: '#dateTo6', container: 'chartContainer6', title: ' Revenue in USD'},
        
        //  {btn: '#report4_btn', url: 'loads/graph4.php', from: '#dateFrom4', to: '#dateTo4', container: 'chartContainer4', title: ' Revenue (USD)'},
         
        {btn: '#report7_btn', url: 'loads/graph7.php', from: '#dateFrom7', to: '#dateTo7', container: 'chartContainer7', title: 'Conversion Rate(%)'}
    ];

    reports.forEach(report => {
        $(report.btn).click(function() {
            loadChart(report.url, report.from, report.to, report.container, report.title);
        });
        loadChart(report.url, report.from, report.to, report.container, report.title);
    });

    const collapses = [
        {collapse: '#collapseOne', url: 'loads/graph1.php', from: '#dateFrom', to: '#dateTo', container: 'chartContainer', title: ' Leads/Enquiries'},
         {collapse: '#collapseFour', url: 'loads/graph4.php', from: '#dateFrom4', to: '#dateTo4', container: 'chartContainer4', title: ' Revenue (USD)'},
        {collapse: '#collapseTwo', url: 'loads/graph2.php', from: '#dateFrom2', to: '#dateTo2', container: 'chartContainer2', title: ' Customers'},
        {collapse: '#collapseFive', url: 'loads/graph5.php', from: '#dateFrom5', to: '#dateTo5', container: 'chartContainer5', title: ' Fee Balance in USD'},
        {collapse: '#collapseSix', url: 'loads/graph6.php', from: '#dateFrom6', to: '#dateTo6', container: 'chartContainer6', title: 'Conversion Rate'},
        {collapse: '#collapseSeven', url: 'loads/graph7.php', from: '#dateFrom7', to: '#dateTo7', container: 'chartContainer7', title: 'Conversion Rate(%)'}
    ];

    collapses.forEach(collapse => {
        $(collapse.collapse).on('shown.bs.collapse', function () {
            loadChart(collapse.url, collapse.from, collapse.to, collapse.container, collapse.title);
        });
    });
});


$(document).ready(function() {
    var currentYear = new Date().getFullYear();

    function loadData(year, url, containerId, titleText, yAxisTitle) {
        $.getJSON(url, { year: year }, function(data) {
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
                    dataPoints: data
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

    initializeChart("loads/graph3.php", "chartContainerYear1", "Leads", "Number of Leads");
 

    setupButton("#report_year1_btn", "#yearInput1", "loads/graph3.php", "chartContainerYear1", "Leads", "Number of Leads");
    // setupButton("#report_year2_btn", "#yearInput2", "loads/graph4.php", "chartContainerYear2", "Customers", "Number of Customers");

    const collapses = [
        {collapse: '#collapseThree', url: 'loads/graph3.php', container: 'chartContainerYear1', title: 'Leads', yAxisTitle: 'Number of Leads'},
        // {collapse: '#collapseFour', url: 'loads/graph4.php', container: 'chartContainer4', title: ' Revenue(USD)', yAxisTitle: 'Amount (USD)'}
    ];

    collapses.forEach(collapse => {
        $(collapse.collapse).on('shown.bs.collapse', function () {
            initializeChart(collapse.url, collapse.container, collapse.title, collapse.yAxisTitle);
        });
    });
});

