<?php
include_once 'database/db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <link rel="icon" type="image/x-icon" href="https://system.vantageafricaleaders.com/admin/assets/img/logo/logo.png">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!-- Primary Meta Tags -->
    <title>Vantage Africa School of Leadership</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="title" content="Vantage Africa School of Leadership-Register">


    <script src="http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <!-- icons -->
    <script src="https://code.iconify.design/iconify-icon/1.0.2/iconify-icon.min.js"></script>
    <script src="https://kit.fontawesome.com/5e6ea00f2d.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>

    <!-- Sweet Alert -->
    <link type="text/css" href="https://system.vantageafricaleaders.com/admin/vendor/sweetalert2/dist/sweetalert2.min.css" rel="stylesheet">

    <!-- Notyf -->
    <link type="text/css" href="https://system.vantageafricaleaders.com/admin/vendor/notyf/notyf.min.css" rel="stylesheet">

    <!-- Volt CSS -->
    <link type="text/css" href="https://system.vantageafricaleaders.com/admin/css/volt.css" rel="stylesheet">


</head>
<style>
    .ban {
        color: #fff;
    }

    .ban:hover {
        background: #000;
        border-radius: 2px;
        padding: 1px 1px;
    }

    select {
        font-family: 'FontAwesome', 'Arial';
        font-size: 1.5em;
        font-weight: 400;
    }

    .banner {
        font-size: .8em;
        white-space: nowrap;
        font-family: "Cairo", -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif;
        line-height: 1;
        text-transform: none;
        padding: .5rem 0;
    }

    .bg_main {
        background-color: #202938 !important;
        color: #fff !important;
    }

    .spinner {
        display: block;
        width: 24px;
        height: 24px;
        position: absolute;
        background: transparent;
        box-sizing: border-box;
        border-top: 4px dotted white;
        border-left: 4px dotted white;
        border-right: 4px dotted white;
        border-bottom: 4px dotted white;
        border-radius: 100%;
        -webkit-animation: spin 1.5s linear infinite;
        -moz-animation: spin 1.5s linear infinite;
        -ms-animation: spin 1.5s linear infinite;
        -o-animation: spin 1.5s linear infinite;
        animation: spin 1.5s linear infinite;
    }

    @keyframes spin {
        from {
            -ms-transform: rotate(0deg);
            -moz-transform: rotate(0deg);
            -webkit-transform: rotate(0deg);
            -o-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        to {
            -ms-transform: rotate(360deg);
            -moz-transform: rotate(360deg);
            -webkit-transform: rotate(360deg);
            -o-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    @-webkit-keyframes spin {
        from {
            -webkit-transform: rotate(0deg);
            -o-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        to {
            -webkit-transform: rotate(360deg);
            -o-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    .loading {
        border-radius: 50px !important;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .error {
        color: red;
        position: absolute;
        right: 0;
        bottom: -25px;
        font-size: .9vw;
        font-style: italic;
    }
</style>



<style>
    /* Styles for small screens */
    @media screen and (max-width: 768px) {
        .banner {
            padding: 10px;
            /* Adjust the padding as needed */
        }

        .mobile {
            text-align: center;
        }

        .container {
            flex-direction: column;
            align-items: flex-start;
        }

        .contact-info {
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 10px;
            /* Add some spacing between the contact info items */
        }

        .social-links {
            margin-top: 10px;
            /* Add some spacing between the contact info and social links */
        }

        .ban {
            display: block;
            margin-top: 5px;
            /* Add some spacing between the social links */
        }
    }

    /* Styles for medium and larger screens */
    @media screen and (min-width: 769px) {
        .container {
            justify-content: space-between;
        }

        .contact-info {
            margin-bottom: 0;
        }

        .social-links {
            justify-content: flex-end;
        }

        .mobile {
            text-align: center;
        }

        .ban {
            margin-left: 5px;
            /* Add some spacing between the social links */
        }
    }
</style>


<body>
    <main>
        <div class="banner" style="background: #d16b30; color: #fff;">
            <div class="container d-flex justify-content-center justify-content-md-between ">
                <div class="contact-info d-flex align-items-center">
                    <i class="fas fa-phone-alt"></i> <span><a class="text-white" href="tel:+254 721 263 977"><small>
                                +254 725 303 645</small></a></span></i> &nbsp;&nbsp;
                    <i class="fas fa-envelope "></i><a a class="text-white"
                        href="mailto: info@vantageafricaleaders.com"> <small>info@vantageafricaleaders.com</small>
                    </a></i> &nbsp;&nbsp;
                    <i class="fas fa-map-marker-alt"></i><a a class="text-white mobile" href="#"> <small>Astrol Business Center
                            Thika Road Nairobi, 6th Floor, Room C603</small>
                    </a></i> &nbsp;&nbsp;
                </div>
                <div class="social-links d-none d-md-flex align-items-center text-white">
                    <a href="https://system.vantageafricaleaders.com/register.php" class="text-sm ms-1 ban"><small>Register Now</small></a>
                    <a href="https://vantageafricaleaders.com/our-programs/video/" class="ms-1 ban"><small>Videos</small></a>
                    <a href="https://vantageafricaleaders.com/program-timetable/" class="ms-1 ban"><small>Program Timetable</small></a>
                </div>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg navbar-light bg-white ">
            <div class="container">
                <a class="navbar-brand" href="#">

                    <img src="https://system.vantageafricaleaders.com/admin/assets/img/logo/logo.png" alt="" width="100" height="200">

                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">

                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="https://vantageafricaleaders.com/">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/about/">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/programs-courses/">Our Programs </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/our-blog/">Our Blog </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link border-bottom border-3 border-warning pb-0" href="ticketing.php">Tickets </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/get-in-touch/">Get In Touch </a>
                        </li>

                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <svg class="icon icon-xs text-gray-600 ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </a>
                        </li>
                    </ul>
                    <form class="d-flex">

                        <!-- <button class="btn btn-outline-success" type="submit">Search</button> -->
                        <!-- search icon -->
                    </form>

                    <div class="d-flex">

                        <a href="register.php" class="btn btn-danger ms-2">Register Now</a>
                    </div>
                </div>
        </nav>

        <!-- Section -->
        <section class=" mt-5 mt-lg-0 bg-soft d-flex align-items-center">
            <?php
            if ($_GET['id']) {
                $id = $_GET['id'];

                $query = mysqli_query($conn, "SELECT * FROM lead_forms WHERE id = '$id'") or die(mysqli_error($conn));
                $row = mysqli_fetch_array($query);
            } else {
                echo "<script>
                    location.href = 'https://vantageafricaleaders.com/'
                </script>";
            } ?>
            <div class="container card rounded-0 my-4">
                <h1 class="mt-4 fs-5">
                    <b>
                        <?php echo $row['title']; ?>
                    </b>
                </h1>
                <div class="alert alert-info pb-0 rounded-0 border-end-0 border-top-0 border-bottom-0 border-info border-4">
                    <?php echo $row['description']; ?>
                </div>
                <form action="#" method="POST" class="mt-4" id="lead_form">
                    <input type="hidden" name="ref_id" id="ref_id" value="<?php echo $id; ?>">
                    <div class="row">
                        <?php
                        $lead_forms_result = $conn->query("SELECT * FROM lead_forms WHERE id = '$id'") or die($conn->error);
                        while ($lead_forms_data = $lead_forms_result->fetch_assoc()) {
                            $array = json_decode($lead_forms_data['form_data'], true);
                            foreach ($array as $item) {
                                $lowercaseString = strtolower($item['name']);
                                $input_name = str_replace(' ', '_', $lowercaseString); ?>
                                <div class="col-xl-<?php echo (($item['type'] == "textarea") ? "12" : '6'); ?> col-md-<?php echo (($item['type'] == "textarea") ? "12" : '6'); ?>">
                                    <div class="input-group mb-3 position-relative">
                                        <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1"><?php echo $item['name']; ?></span>
                                        <?php
                                        switch ($item['type']) {
                                            case 'input': ?>
                                                <input type="<?php echo $item['field_type']; ?>" name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" class="form-control rounded-0" placeholder="Enter <?php echo $item['name']; ?>" aria-describedby="basic-addon1" required>
                                            <?php break;
                                            case 'select': ?>
                                                <select class="form-control rounded-0" name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" required>
                                                    <option value="" hidden>-- Select <?php echo $item['name']; ?> --</option>
                                                    <?php foreach ($item['options'] as $option) { ?>
                                                        <option value="<?php echo $option['value']; ?>"><?php echo $option['text']; ?></option>
                                                    <?php } ?>
                                                </select>
                                            <?php break;
                                            case 'textarea': ?>
                                                <textarea name="<?php echo $input_name; ?>" id="<?php echo $input_name; ?>" rows="7" class="form-control rounded-0" placeholder="Enter <?php echo $item['name']; ?>....." required></textarea>
                                        <?php break;
                                        }
                                        ?>
                                    </div>
                                </div>
                        <?php }
                        } ?>
                    </div>

                    <div class="form-group mb-2">
                        <div class="g-recaptcha" data-sitekey="6LeJKo4mAAAAAIgffG6LD6HZVtFHN_6UV1e4SqS9" data-callback="enableSubmitButton"></div>
                    </div>
                    <div class="w-100 d-flex justify-content-end mb-3">
                        <button type="submit" id="lead_form_btn" class="btn btn-success text-white rounded-0">Submit</button>
                    </div>
                </form>
                <script>
                    //   document.getElementById("submit-button").disabled = false;
                    function enableSubmitButton() {
                        // alert("Verified");
                        document.getElementById("checkBtn").disabled = false;
                    }
                    document.getElementById("checkBtn").disabled = true;
                </script>


                <script type="text/javascript">
                    $(document).ready(function() {
                        $('#checkBtn').click(function() {
                            checked = $("input[type=radio]:checked").length;

                            if (!checked) {
                                alert("You must check at least one radio.");
                                return false;
                            }

                        });
                    });
                </script>
            </div>
        </section>
    </main>


    <!-- footer -->

    <footer class="footer bg-gray-700 text-white">
        <div class="container">
            <div class="row align-items-center justify-content-md-between">
                <div class="col-md-12">
                    <ul class="nav nav-footer justify-content-center">
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/about/#who-we-are">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/programs-courses/">Our Programs </a>
                        </li>
                        <!--<li class="nav-item">-->
                        <!--    <a class="nav-link" href="#">Case Studies</a>-->
                        <!--</li>-->
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/our-blog/">Our Blog </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://vantageafricaleaders.com/get-in-touch/">Get In Touch </a>
                        </li>
                    </ul>
                    <p class="text-center">
                        © Vantage Africa Ltd. All rights reserved. Developed by Outright Web Solutions</p>
                </div>
            </div>
        </div>
    </footer>




    <!-- Core -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/@popperjs/core/dist/umd/popper.min.js"></script>
    <script src="https://system.vantageafricaleaders.com/admin/vendor/bootstrap/dist/js/bootstrap.min.js"></script>

    <!-- Vendor JS -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/onscreen/dist/on-screen.umd.min.js"></script>

    <!-- Slider -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/nouislider/distribute/nouislider.min.js"></script>

    <!-- Smooth scroll -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/smooth-scroll/dist/smooth-scroll.polyfills.min.js"></script>

    <!-- Charts -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/chartist/dist/chartist.min.js"></script>
    <script src="https://system.vantageafricaleaders.com/admin/vendor/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.min.js"></script>

    <!-- Datepicker -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/vanillajs-datepicker/dist/js/datepicker.min.js"></script>

    <!-- Sweet Alerts 2 -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/sweetalert2/dist/sweetalert2.all.min.js"></script>

    <!-- Moment JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.27.0/moment.min.js"></script>

    <!-- Vanilla JS Datepicker -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/vanillajs-datepicker/dist/js/datepicker.min.js"></script>

    <!-- Notyf -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/notyf/notyf.min.js"></script>

    <!-- Simplebar -->
    <script src="https://system.vantageafricaleaders.com/admin/vendor/simplebar/dist/simplebar.min.js"></script>

    <!-- Github buttons -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- Volt JS -->
    <script src="https://system.vantageafricaleaders.com/admin/assets/js/volt.js"></script>

    <script>
        $("#lead_form").submit(function(e) {
            e.preventDefault()

            var button = $("#lead_form_btn"),
                spinner = '<span class="spinner"></span>';
            if (!button.hasClass("loading")) {
                button.toggleClass("loading").html(spinner);
                button.prop("disabled", true);
            }

            var form = $(this)[0];
            var formData = new FormData(form);

            $.ajax({
                url: "includes/submit_lead.inc.php",
                type: "POST",
                processData: false,
                contentType: false,
                data: formData,
                cache: false,
                success: function(result) {
                    if (result == 0) {
                        Swal.fire({
                            position: "top-end",
                            icon: "error",
                            title: "Error",
                            text: "Something went wrong.\nPlease try again later.",
                            showConfirmButton: true,
                        }).then(() => {
                            if (button.hasClass("loading")) {
                                button
                                    .toggleClass("loading")
                                    .html("Submit");
                                button.prop("disabled", false);
                            }
                        });
                    } else if (result == 1) {
                        Swal.fire({
                            position: "top-end",
                            icon: "success",
                            title: "Success",
                            text: "Submitted Successfully.",
                            showConfirmButton: true,
                        }).then(() => {
                            window.location.href = "";
                        });
                    } else if (result == 2) {
                        Swal.fire({
                            position: "top-end",
                            icon: "error",
                            title: "Failed",
                            text: "Failed to submit.\nPlease try again later.",
                            showConfirmButton: true,
                        }).then(() => {
                            if (button.hasClass("loading")) {
                                button
                                    .toggleClass("loading")
                                    .html("Submit");
                                button.prop("disabled", false);
                            }
                        });
                    }
                },
            });
        });
    </script>
</body>

</html>