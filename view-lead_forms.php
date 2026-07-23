<?php
require_once 'header.php';
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Lead Forms Details</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <input type="hidden" name="" id="link" value="test">
                            <button class="btn border-0 p-0 ms-3" onclick="copyLink();">
                                <i class="bi bi-copy"></i> Copy
                            </button>

                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="card rounded-0">
                        <div class="card-body">
                            <h2 class="fs-6 text-uppercase border-bottom">
                                <b>Monitoring and Evaluation Training in Tanzania</b>
                            </h2>
                            <p class="alert alert-danger rounded-0 border-end-0 border-top-0 border-bottom-0 border-danger border-4">
                                <b>NB:</b> Lorem Ipsum is simply dummy text of the printing and typesetting industry.
                            </p>

                            <div class="row">
                                <div class="col-xl-6 col-md-6">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Name</span>
                                        <input type="text" name="form-title" class="form-control rounded-0" placeholder="Enter Name" aria-describedby="basic-addon1">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-md-6">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Email</span>
                                        <input type="email" name="form-title" class="form-control rounded-0" placeholder="Enter Email" aria-describedby="basic-addon1">
                                    </div>
                                </div>
                                <div class="col-xl-6 col-md-6">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Phone</span>
                                        <input type="tel" name="form-title" class="form-control rounded-0" placeholder="Enter Phone" aria-describedby="basic-addon1">
                                    </div>
                                </div>

                                <div class="col-xl-12 col-md-12">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Message</span>
                                        <textarea name="" id="" class="form-control rounded-0" placeholder="message"></textarea>
                                    </div>
                                </div>
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