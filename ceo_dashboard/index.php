<?php require_once 'header.php'; ?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php require_once 'top_nav.php'; ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h5 style="font-size: 18px;margin-bottom: 0;font-weight: 600;font-family: 'Poppins', sans-serif;color: #012970;position: fixed;top: 12vh;z-index: 100;width: 100%;background: #f6f7fa;padding: .8em 0 0 0;">
                    <a href="../" class="text-decoration-none text-reset">
                        <i class="bi bi-house-door-fill"></i>
                    </a>
                    <i class="bi bi-chevron-right mx-0"></i>
                    <span class="text-muted">Virtual Reports</span>
                </h5>
            </div>

            <div class="row mt-4">
                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Enquiries for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_enquiries_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Clients for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_clients_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Fee Collected for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_fee_collected_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Fee Balances for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_fee_bal_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>



                <div class="col-xl-12 col-md-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 id="virtual_leads_enquiries_chart_title"
                                    class="card-title m-0 p-0"
                                    style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    April 2024 Intake Leads/Enquiries
                                </h5>

                                <select id="virtual_leads_enquiries_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_leads_enquiries_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    April 2024 Intake Customers
                                </h5>
                                <select id="virtual_customers_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_customers_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 id="virtual_total_leads_chart_title" class="card-title m-0 p-0"
                                    style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    2024 Leads
                                </h5>

                                <select id="virtual_total_leads_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_total_leads_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 id="virtual_total_customers_chart_title" class="card-title m-0 p-0"
                                    style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    2024 Customers
                                </h5>

                                <select id="virtual_total_customers_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_total_customers_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0" id="virtual_intake_revenue_chart_title" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    April 2024 Intake Revenue in USD
                                </h5>

                                <select id="virtual_intake_revenue_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_revenue_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-4">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0" id="virtual_intake_fee_bal_chart_title" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    April 2024 Intake Fee Balances in USD
                                </h5>

                                <select id="virtual_intake_fee_bal_filter" class="form-select form-select-sm rounded-0" style="width: auto;">
                                    <option value="">All Intakes</option>
                                    <?php
                                    $sql = "SELECT `description` FROM intake GROUP BY `description` ORDER BY `description` ASC";
                                    $result = $conn->query($sql);

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row['description']) . '">'
                                                . htmlspecialchars($row['description']) .
                                                '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-body position-relative">
                            <div id="virtual_intake_fee_bal_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>