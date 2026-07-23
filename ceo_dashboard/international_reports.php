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
                    <span class="text-muted">International Reports for All Countries</span>
                </h5>
            </div>

            <div class="row mt-4">
                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Enquiries for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_enquiries_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Clients for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_clients_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Fee Collected for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_fee_collected_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Fee Balances for the last 3 months
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_fee_bal_chart" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>



                <div class="col-xl-12 col-md-12 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Intake Leads/Enquiries
                            </h5>
                        </div>
                        <div class="card-body position-relative overflow">
                            <div id="enquiries_leads_enquiries_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Intake Customers
                            </h5>
                        </div>
                        <div class="card-body position-relative overflow">
                            <div id="enquiries_customers_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <?php $year = date("Y"); ?>
                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                <?= $year ?> LEADS
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_total_leads_chart" style="min-height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                <?= $year ?> CUSTOMERS
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="enquiries_total_customers_chart" style="min-height: 300px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Intake Revenue in Kshs
                            </h5>
                        </div>
                        <div class="card-body position-relative overflow">
                            <div id="enquiries_revenue_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12 mb-3">
                    <div class="card shadow border-0 rounded-0">
                        <div class="card-header">
                            <h5 class="card-title m-0 p-0" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                Intake Fee Balances in Kshs
                            </h5>
                        </div>
                        <div class="card-body position-relative overflow">
                            <div id="enquiries_intake_fee_bal_chart" style="height: 380px; width: 100%;"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>