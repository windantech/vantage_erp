<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>
        <div class="container-fluid mt-5 pt-5">
            <label>International Training's</label>
            <ul class="nav nav-tabs reports_body">
                <li class="nav-item">
                    <a class="nav-link active rounded-0" aria-current="page" href="#collapseOne" data-bs-toggle="collapse">Intake Leads/Enquiries</a>
                </li>
                <!--<li class="nav-item">-->
                <!--    <a class="nav-link rounded-0" href="#collapseTwo" data-bs-toggle="collapse">Report 2</a>-->
                <!--</li>-->
                <li class="nav-item">
                    <a class="nav-link rounded-0" href="#collapseThree" data-bs-toggle="collapse">Leads/Enquiries Report/Year</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" href="#collapseFour" data-bs-toggle="collapse">Fee Collections/Course</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" href="#collapseFive" data-bs-toggle="collapse">Fee Balances/Course</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" href="#collapseSix" data-bs-toggle="collapse">No. Conversion's </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-0" href="#collapseSeven" data-bs-toggle="collapse">Conversion Rate</a>
                </li>
            </ul>

            <div class="accordion mt-0 rounded-0" id="virtualReportsAccordion">
                <div class="accordion-item rounded-0">
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom">Date From:</label>
                                <input type="date" id="dateFrom" name="dateFrom" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo">Date To:</label>
                                <input type="date" id="dateTo" name="dateTo" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report1_btn">Filter</button>
                            </div>
                            <?php 
                            require "course_key_.php";
                            ?>

                            <div class="px-3 overflow">
                                <div id="chartContainer" style="height: 450px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom2">Date From:</label>
                                <input type="date" id="dateFrom2" name="dateFrom2" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo2">Date To:</label>
                                <input type="date" id="dateTo2" name="dateTo2" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report2_btn">Filter</button>
                            </div>

                            <div class="px-3 overflow">
                                <div id="chartContainer2" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="yearInput1">Year:</label>
                                <input type="number" id="yearInput1" name="yearInput1" class="form-control bg-transparent border-1" min="2000" max="<?php echo date("Y"); ?>" oninput="if(this.value.length > 4) this.value = this.value.slice(0, 4); if(this.value > this.max) this.value = this.max;">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report_year1_btn">Filter</button>
                            </div>

                            <div class="px-3 overflow">
                                <div id="chartContainerYear1" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#virtualReportsAccordion">
                    <div class="accordion-body px-0">
                               <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom">Date From:</label>
                                <input type="date" id="dateFrom4" name="dateFrom4" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo4">Date To:</label>
                                <input type="date" id="dateTo4" name="dateTo4" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report4_btn">Filter</button>
                            </div>
                            <?php 
                            require "course_key_.php";
                            ?>

                            <div class="px-3 overflow">
                                <div id="chartContainer4" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom5">Date From:</label>
                                <input type="date" id="dateFrom5" name="dateFrom5" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo5">Date To:</label>
                                <input type="date" id="dateTo5" name="dateTo5" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report5_btn">Filter</button>
                            </div>
                                 <?php 
                            require "course_key_.php";
                            ?>

                            <div class="px-3 overflow">
                                <div id="chartContainer5" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom6">Date From:</label>
                                <input type="date" id="dateFrom6" name="dateFrom6" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo6">Date To:</label>
                                <input type="date" id="dateTo6" name="dateTo6" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report6_btn">Filter</button>
                            </div>
                                                             <?php 
                            require "course_key_.php";
                            ?>

                            <div class="px-3 overflow">
                                <div id="chartContainer6" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item rounded-0">
                    <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#virtualReportsAccordion">
                        <div class="accordion-body px-0">
                            <div class="input-group mb-3 border-bottom border-3 pb-3 px-3">
                                <label class="input-group-text bg_main rounded-0" for="dateFrom7">Date From:</label>
                                <input type="date" id="dateFrom7" name="dateFrom7" class="form-control bg-transparent border-1">
                                <label class="input-group-text bg_main rounded-0 ms-2" for="dateTo7">Date To:</label>
                                <input type="date" id="dateTo7" name="dateTo7" class="form-control bg-transparent border-1">
                                <button class="btn btn-outline-success ms-2 rounded-0" type="button" id="report7_btn">Filter</button>
                            </div>

                            <div class="px-3 overflow">
                                <div id="chartContainer7" style="height: 370px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>
<script src="assets/js/graphs_.js?v=<?php echo date('his'); ?>"></script>