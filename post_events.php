<?php
require_once 'header.php';
?>
<style>
        .box {
            display: block;
            min-width: 300px;
            height: 300px;
            margin: 10px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
        }

        .upload-options {
            position: relative;
            height: 75px;
            background-color: #ff8531;
            cursor: pointer;
            overflow: hidden;
            text-align: center;
            transition: background-color ease-in-out 150ms;
        }

        .upload-options:hover {
            background-color: #b6500a;
        }

        .upload-options input {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }

        .upload-options label {
            display: flex;
            align-items: center;
            width: 100%;
            height: 100%;
            font-weight: 400;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            overflow: hidden;
        }

        .upload-options label::after {
            content: "+";
            position: absolute;
            font-size: 2.5rem;
            color: #e6e6e6;
            z-index: 0;
            display: flex;
            justify-content: center;
            align-content: center;
            flex-wrap: wrap;
            height: 100%;
            width: 100%;
        }

        .upload-options label span {
            display: inline-block;
            width: 50%;
            height: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
            vertical-align: middle;
            text-align: center;
        }

        .upload-options label span:hover i.material-icons {
            color: lightgray;
        }

        .js--image-preview {
            height: 225px;
            width: 100%;
            position: relative;
            overflow: hidden;
            background-image: url("");
            background-color: white;
            background-position: center center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .js--image-preview::after {
            content: "Poster Preview";
            position: relative;
            font-size: 35px;
            color: #e6e6e6;
            z-index: 0;
            display: flex;
            justify-content: center;
            align-content: center;
            flex-wrap: wrap;
            height: 100%;
        }

        .js--image-preview.js--no-default::after {
            display: none;
        }

        .js--image-preview:nth-child(2) {
            background-image: url("http://bastianandre.at/giphy.gif");
        }

        .drop {
            display: block;
            position: absolute;
            background: rgba(95, 158, 160, 0.2);
            border-radius: 100%;
            transform: scale(0);
        }

        .animate {
            -webkit-animation: ripple 0.4s linear;
            animation: ripple 0.4s linear;
        }

        @-webkit-keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        @keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }
    </style>
<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
        include 'function.php';
        ?>

        <div class="container-fluid mt-5 pt-5">
            <!-- DataTales Example -->
            <div class="card shadow mb-4 rounded-0">
                <div class="card-header bg_main rounded-0 py-3">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-50">
                            <h6 class="m-0 font-weight-bold text-white text-uppercase"> All Training</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end"> 
                        
                         <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                          
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body overflow">
                     <div class="table-responsive">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                            
        <thead>
            <tr>
                <th class="border-gray-200">Event  Id</th>
                <th class="border-gray-200">Title</th>						
                  <th class="border-gray-200">Status</th>	
                <th class="border-gray-200">Action</th>
                
            </tr>
        </thead>
        <tbody>
            <!-- Item -->
            <?php
           
            $check = mysqli_query($conn,"SELECT `event_id`, `poster_image`, `event_title`, `event_description`, `start_on`, `end_on`, `location`, `host`, `early_start_on`, `early_end_on`, `early_amount`, `advance_start_on`, `advance_end_on`, `advance_amount`, `gate_start_on`, `gate_end_on`, `gate_amount`, `currency_code`, `status`, `rate` FROM `Event` ORDER BY event_id DESC ") or die(mysqli_error($conn));
            if(mysqli_num_rows($check) > 0){
                
            while($row = mysqli_fetch_array($check) ){
                $event_id =$row['event_id'];
            ?>
         <tr>

        
                        <td><span class="fw-normal">  <?php echo $row['event_id']; ?></span></td> 
            
                <td  ><span class="fw-normal">  <?php echo $row['event_title']; ?></span></td> 
                
                         <td>
                  <input  id='copyText' type='text' class='form-control url-input' value='https://vantageafricaleaders.com/program-details.php?id=<?php echo $row['event_id']; ?>' readonly> <button class='btn btn-success btn-copy' onclick='copyFunction()'>Copy</button>
                  
                    </td>
                        <td>
                    <?php 
                    if($row['status'] == 1){
                        ?>
                        
                        <a onclick="return confirm('Are you sure you want to proceed with this action ?')"  class="btn btn-warning" href="activate.php?id=<?php echo $row['event_id']; ?>&action=deactivate">Deactivate</a>
                        <?php
                        
                    }else{ ?>
                        <a onclick="return confirm('Are you sure you want to proceed with this action ?')"  class="btn btn-success" href="activate.php?id=<?php echo $row['event_id']; ?>&action=activate">Activate</a>
                    <?php } ?> 
                       <a class="btn btn-pending" href="view_event?event_id=<?php echo $row['event_id']; ?>">View</a>
                    </td>
                
            </tr>
            <?php } } ?>
            <!-- Item -->
             <tfoot>
              <tr>
                <th class="border-gray-200">Event  Id</th>
                <th class="border-gray-200">Title</th>						
                <th class="border-gray-200">Copy</th>	
                <th class="border-gray-200">Action</th>	
                
            </tr>
        
        </tfoot>
           </tbody>
    
                        </table>
                                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
 <!-- Add Event Modal -->
                <div class="modal fade" id="addEventModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addLoadedDataLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content rounded-0">
                            <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                                <h1 class="modal-title fs-5 text-uppercase" id="addLoadedDataLabel">
                                   Add Event
                                </h1>
                                <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                            </div>
                            <div class="modal-body">
                    <form id="addEventForm" action="#" method="POST" enctype="multipart/form-data">
                        <!-- step 1 -->
                        <div class="step_one">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Upload Event Poster</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="box">
                                <div class="js--image-preview"></div>
                                <div class="upload-options">
                                    <label>
                                        <input required name="poster_image" type="file" class="image-upload" accept="image/*" />
                                    </label>
                                </div>
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" class="btn btn-danger rounded-0" data-bs-dismiss="modal">
                                            <i class="fa fa-times" aria-hidden="true"></i>
                                            Cancel
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="button" onclick="stage_two()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 1 -->

                        <!-- step 2 -->
                        <div class="step_two d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>


                            <div class="text-uppercase mt-3">
                                <b>Set Event Details</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Title</span>
                                </div>
                                <input type="text" required name="event_title" class="form-control" placeholder="Enter title here" aria-label="title" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style=" background: #ff8531;">Description</span>
                                </div>
                           
                            </div>
                            <div class="form-group"><br>
                                 <textarea class="form-control" rows="10"  id="summernote" required name="event_description"  placeholder="Enter description here" aria-label="Description"></textarea>
                                 </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_one()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="button" onclick="stage_three()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 2 -->

                        <!-- step 3 -->
                        <div class="step_three d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-secondary text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Set Event Date and Location</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                </div>
                                <input type="datetime-local" required name="start_on" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                </div>
                                <input required type="datetime-local" class="form-control" name="end_on" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Location</span>
                                </div>
                                <input type="text" required name="location" class="form-control" placeholder="Enter location here" aria-label="location" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Host</span>
                                </div>
                                <input type="text" required name="host" class="form-control" placeholder="Enter host here" aria-label="Host" aria-describedby="basic-addon1">
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_two()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="button" onclick="stage_four()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 3 -->

                        <!-- step 4 -->
                        <div class="step_four d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Early Bird Ticket</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                </div>
                                <input type="datetime-local" required name="early_start_on" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                </div>
                                <input type="datetime-local" required name="early_end_on" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount(USD)</span>
                                </div>
                                <input type="number" class="form-control" placeholder="Enter amount here" aria-label="amount" required name="early_amount" aria-describedby="basic-addon1">
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_three()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="button" onclick="stage_four1()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 4 -->

                        <!-- step 4-1 -->
                        <div class="step_four1 d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Advance Ticket</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                </div>
                                <input name="advance_start_on" type="datetime-local" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                </div>
                                <input name="advance_end_on"  type="datetime-local" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount(USD)</span>
                                </div>
                                <input name="advance_amount" type="number" class="form-control" placeholder="Enter amount here" aria-label="amount" aria-describedby="basic-addon1">
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_four()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="button" onclick="stage_four2()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 4-1 -->

                        <!-- step 4-2 -->
                        <div class="step_four2 d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Gate Ticket</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Starts On</span>
                                </div>
                                <input name="gate_start_on" type="datetime-local" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Ends On</span>
                                </div>
                                <input name="gate_end_on" type="datetime-local" class="form-control" aria-describedby="basic-addon1">
                            </div>

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Amount(USD)</span>
                                </div>
                                <input name="gate_amount" type="number" class="form-control" placeholder="Enter amount here" aria-label="amount" aria-describedby="basic-addon1">
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_four1()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                    <button type="button" onclick="stage_four3()" class="btn btn-secondary rounded-0">
                                            <i class="fa fa-hand-o-right" aria-hidden="true"></i>
                                            Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 4-2 -->

                        <!-- step 4-3 -->
                        <div class="step_four3 d-none">
                            <div class="d-flex">
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-upload" aria-hidden="true"></i>
                                    Poster
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-exchange" aria-hidden="true"></i>
                                    Details
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-calendar-check-o" aria-hidden="true"></i>
                                    Date
                                </button>
                                <button class="btn bg-danger text-white shadow rounded-0 text-uppercase m-1">
                                    <i class="fa fa-ticket" aria-hidden="true"></i>
                                    Tickets
                                </button>
                            </div>

                            <div class="text-uppercase mt-3">
                                <b>Set Currency code</b>
                            </div>
                            <hr class="mt-1 text-success border-3">

                            <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Currency</span>
                                </div>
                                <input type="text" required name="currency_code" class="form-control" placeholder="Enter currency here" aria-label="Currency" aria-describedby="basic-addon1">
                            </div>
                            
                              <div class="input-group mb-3 mt-2">
                                <div class="input-group-prepend">
                                    <span class="input-group-text rounded-0 rounded-start" style="width: 15vh !important; background: #ff8531;" id="basic-addon1">Current Conversion rate *USD to local currency*</span>
                                </div>
                                <input type="text" required name="rate_c" class="form-control" placeholder="EnterConversion Rate Here " aria-label="rate" aria-describedby="basic-addon1">
                            </div>

                            <hr>

                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md d-flex justify-content-start">
                                        <button type="button" onclick="stage_four2()" class="btn btn-danger rounded-0">
                                            <i class="fa fa-hand-o-left" aria-hidden="true"></i>
                                            Previous
                                        </button>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <button type="submit" class="btn btn-success rounded-0">
                                            <i class="fa fa-check" aria-hidden="true"></i>
                                            Finish
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- step 4-3 -->
                    </form>
                              </div>
                        </div>
                    </div>
                </div>
  
<?php
// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Set parameters from the POST request
    $event_title = mysqli_real_escape_string($conn, $_POST['event_title']);
    $event_description = mysqli_real_escape_string($conn, $_POST['event_description']);
    $start_on = mysqli_real_escape_string($conn, $_POST['start_on']);
    $end_on = mysqli_real_escape_string($conn, $_POST['end_on']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $host = mysqli_real_escape_string($conn, $_POST['host']);
    $early_start_on = mysqli_real_escape_string($conn, $_POST['early_start_on']);
    $early_end_on = mysqli_real_escape_string($conn, $_POST['early_end_on']);
    $early_amount = mysqli_real_escape_string($conn, $_POST['early_amount']);
    $advance_start_on = mysqli_real_escape_string($conn, $_POST['advance_start_on']);
    $advance_end_on = mysqli_real_escape_string($conn, $_POST['advance_end_on']);
    $advance_amount = mysqli_real_escape_string($conn, $_POST['advance_amount']);
    $gate_start_on = mysqli_real_escape_string($conn, $_POST['gate_start_on']);
    $gate_end_on = mysqli_real_escape_string($conn, $_POST['gate_end_on']);
    $gate_amount = mysqli_real_escape_string($conn, $_POST['gate_amount']);
    $currency_code = mysqli_real_escape_string($conn, $_POST['currency_code']);
$rate_c = mysqli_real_escape_string($conn, $_POST['rate_c']);
    // Upload poster image
$targetDir = __DIR__ . "/uploads/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = time() . "_" . basename($_FILES["poster_image"]["name"]);
$targetFile = $targetDir . $filename;

if ($_FILES["poster_image"]["error"] === UPLOAD_ERR_OK) {
    if (move_uploaded_file($_FILES["poster_image"]["tmp_name"], $targetFile)) {

    
   
            
            // Construct the SQL query with uploaded file path
    $poster_image_path = mysqli_real_escape_string($conn, $targetFile);
    $sql = "INSERT INTO Event (poster_image, event_title, event_description, start_on, end_on, location, host, early_start_on, early_end_on, early_amount, advance_start_on, advance_end_on, advance_amount, gate_start_on, gate_end_on, gate_amount, currency_code,rate) VALUES ('$poster_image_path', '$event_title', '$event_description', '$start_on', '$end_on', '$location', '$host', '$early_start_on', '$early_end_on', '$early_amount', '$advance_start_on', '$advance_end_on', '$advance_amount', '$gate_start_on', '$gate_end_on', '$gate_amount', '$currency_code','$rate_c')";

    // Execute the query
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Event added!');window.location.href='post_events.php';</script>";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

    
            
                
          } 
    

    // Close

}
else {
  error_log("Poster image upload error: " . $_FILES["poster_image"]["error"]);

}
}
?>

<script>
    $(document).ready(function() {
        $('#summernote').summernote({
       height: 300,
       callbacks: {
           onChange:function(contents){
               $('#preview-body').html(contents);
           }
       }
  });
    });
</script>

  <script>
        function initImageUpload(box) {
            let uploadField = box.querySelector('.image-upload');

            uploadField.addEventListener('change', getFile);

            function getFile(e) {
                let file = e.currentTarget.files[0];
                checkType(file);
            }

            function previewImage(file) {
                let thumb = box.querySelector('.js--image-preview'),
                    reader = new FileReader();

                reader.onload = function() {
                    thumb.style.backgroundImage = 'url(' + reader.result + ')';
                }
                reader.readAsDataURL(file);
                thumb.className += ' js--no-default';
            }

            function checkType(file) {
                let imageType = /image.*/;
                if (!file.type.match(imageType)) {
                    throw '1';
                } else if (!file) {
                    throw '2';
                } else {
                    previewImage(file);
                }
            }

        }

        var boxes = document.querySelectorAll('.box');

        for (let i = 0; i < boxes.length; i++) {
            let box = boxes[i];
            initDropEffect(box);
            initImageUpload(box);
        }

        function initDropEffect(box) {
            let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;

            area = box.querySelector('.js--image-preview');
            area.addEventListener('click', fireRipple);

            function fireRipple(e) {
                area = e.currentTarget
                if (!drop) {
                    drop = document.createElement('span');
                    drop.className = 'drop';
                    this.appendChild(drop);
                }
                drop.className = 'drop';

                areaWidth = getComputedStyle(this, null).getPropertyValue("width");
                areaHeight = getComputedStyle(this, null).getPropertyValue("height");
                maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));

                drop.style.width = maxDistance + 'px';
                drop.style.height = maxDistance + 'px';

                dropWidth = getComputedStyle(this, null).getPropertyValue("width");
                dropHeight = getComputedStyle(this, null).getPropertyValue("height");

                x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
                y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;

                drop.style.top = y + 'px';
                drop.style.left = x + 'px';
                drop.className += ' animate';
                e.stopPropagation();

            }
        }

        // Stages
        function stage_one() {
            $(".step_one").removeClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_two() {
            $(".step_one").addClass("d-none");
            $(".step_two").removeClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_three() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").removeClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").removeClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four1() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").removeClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four2() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").removeClass("d-none");
            $(".step_four3").addClass("d-none");
        }

        function stage_four3() {
            $(".step_one").addClass("d-none");
            $(".step_two").addClass("d-none");
            $(".step_three").addClass("d-none");
            $(".step_four").addClass("d-none");
            $(".step_four1").addClass("d-none");
            $(".step_four2").addClass("d-none");
            $(".step_four3").removeClass("d-none");
        }

        // A required field inside a hidden wizard step makes the browser silently refuse to
        // submit (it can't focus a hidden control). Reveal the offending step so the user sees
        // exactly what's missing instead of "nothing happening".
        (function () {
            var f = document.getElementById("addEventForm");
            if (!f) { return; }
            var stepSel = ".step_one,.step_two,.step_three,.step_four,.step_four1,.step_four2,.step_four3";
            var handled = false;
            f.addEventListener("invalid", function (e) {
                if (handled) { return; }
                handled = true; setTimeout(function () { handled = false; }, 0);
                var step = e.target.closest && e.target.closest("[class*='step_']");
                if (step) {
                    f.querySelectorAll(stepSel).forEach(function (s) { s.classList.add("d-none"); });
                    step.classList.remove("d-none");
                }
            }, true);
        })();
    </script>
    <script>
function copyFunction() {
    var copyText = document.getElementById("copyText");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    navigator.clipboard.writeText(copyText.value).then(() => {
      alert("Copied");
    }).catch(err => {
        console.error("Failed to copy: ", err);
    });
}

</script>
<?php
require_once 'footer.php';
?>