<?php
require_once 'header.php';
if($_SESSION['login_id']==6){
    
}else{
    ?>
    <script>
        window.location.href="enquiry_dashboard.php?limit=500";
    </script>
    <?php
}
?>

<section id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        <?php
        require_once 'top_nav.php';
          include '../../function.php';
        ?>
        <div class="container-fluid mt-5 pt-5">
            <div class="mb-3">
                <h1 style="font-size: 24px; margin-bottom: 0; font-weight: 600; color: #012970; font-family: 'Nunito', sans-serif;">Dashboard</h1>
                <nav>
                    <ol class="breadcrumb bg-transparent p-0" style="font-size: 14px; font-family: 'Nunito', sans-serif; color: #899bbd; font-weight: 600;">
                        <li class="breadcrumb-item"><a href="index.html" style="color: #899bbd; transition: 0.3s; text-decoration:none;">Home</a></li>
                        <li class="breadcrumb-item active">Benson</li>
                    </ol>
                </nav>
            </div>
            <div class="row">
                <div class="col-md-6 h-500">
                 <div class="card  shadow border-0">
                <div class="card-body pt-0">
                    <h5 class="card-title" style="padding: 20px 0 15px 0; font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                        Most recent customers(Virtual)
                    </h5>
                    <table  width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                        <thead>
                            <tr>
                                <th class="nowrap border-bottom">Fullname</th>
                                <th class="nowrap border-bottom">Email</th>
                                <th class="nowrap border-bottom">Course</th>
                                
                                
                            </tr>
                        </thead>
                        
                                    <?php



$check = mysqli_query($conn,"SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee` 
        FROM `register` 
        WHERE    source=3
        ORDER BY datee DESC LIMIT 5");
 
           
            if(mysqli_num_rows($check) > 0){
                
            while($row = mysqli_fetch_array($check) ){
            ?>
            <tr>
         
                                     
                <td><span class="fw-normal"><?php echo $row['firstname']; ?></span></td>
                
                <td><span class="fw-normal">  <?php echo $row['email']; ?></span></td>  
                
               
                 <td>
                    <span class="fw-normal"><?php echo check_course($conn,$row['program']); ?></span>
                </td>
                 
                
                    
                
            </tr>
            <?php } } ?>
                    </table>
                </div>
            </div>
            </div>
            
             <div class="col-md-6 h-500">
                 <div class="card shadow border-0">
                <div class="card-body pt-0">
                    <h5 class="card-title" style="padding: 20px 0 15px 0; font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                        Most recent customers(International)
                    </h5>
                    <table  width="100%" cellspacing="0" data-order='[[ 0, "desc" ]]'>
                        <tbody>
                                                    <thead>
                            <tr>
                                 <th class="nowrap border-bottom">Status</th>
                                 <th class="nowrap border-bottom">Email</th>
                                <th class="nowrap border-bottom">Fullname</th>
                                
                                <th class="nowrap border-bottom">Course</th>
                               
                            </tr>
                        </thead>
                        
                            <!-- Item -->
            <?php

         $check = mysqli_query($conn, "
    SELECT event_id, id, fullname, email, term, phone_number, ticket_id, status, amount, ticket_number, confirmation, date_sent 
    FROM ticket_congress 
    GROUP BY email 
    ORDER BY id DESC 
    LIMIT 5
") or die(mysqli_error($conn));

            if(mysqli_num_rows($check) > 0){
                
            while($row = mysqli_fetch_array($check) ){
            ?>
            <tr>
           <td>
                    <?php 
                    if($row['status'] == 2){
                        ?>
                        <span class="fw-bold text-success">Paid</span>
                        <?php
                        
                    }else{ ?>
                    <span class="fw-bold text-warning">Not paid</span>
                    <?php } ?>
                    </td>
               
                <td><span class="fw-normal">  <?php echo $row['email']; ?></span></td>                        
                <td><span class="fw-normal"><?php echo ucwords(strtolower(explode(" ", $row['fullname'])[0])); ?></span></td>
                 <td>
                    <span class="fw-normal"><?php echo check_event($conn,$row['event_id'],"location");    ?></span>
                </td>
              
                <!--<td><span class="fw-normal">1 Jun 2020</span></td>-->
                <!--<td><span class="fw-bold">  <?php echo $row['date_sent']; ?></span></td>-->
                
                  
                
            </tr>
            <?php } } ?>
            </tbody>
                    </table>
                </div>
            </div>
            </div>
            </div>

            <div class="row mt-4">
                
                 <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0">
                        <div class="card-body py-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0 py-3" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    Leads
                                </h5>
                                <div class="position-relative">
                                    <!--<input type="month" id="lead_input" class="form-control opacity-0 position-absolute" style="width: 40px; ">-->
                                    <span class="input-group-text bg-white border-0">
                                        <i class="bi bi-calendar-event"></i>
                                    </span>
                                </div>
                            </div>
                            <div id="leadChart" style="height: 370px; width: 100%;"></div>
                        </div>
                    </div>
                </div>
                
                
                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0">
                        <div class="card-body py-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0 py-3" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    Customers
                                </h5>
                                <div class="position-relative">
                                    <!--<input type="month" id="customer_input" class="form-control opacity-0 position-absolute" style="width: 40px; ">-->
                                    <span class="input-group-text bg-white border-0">
                                        <i class="bi bi-calendar-event"></i>
                                    </span>
                                </div>
                            </div>
                            <div id="customerChart" style="height: 370px; width: 100%;"></div>
                        </div>
                    </div>
                </div>
               
                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0">
                        <div class="card-body py-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0 py-3" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    Fee collected
                                </h5>
                                <div class="position-relative">
                                    <!--<input type="month" id="rev_input" class="form-control opacity-0 position-absolute" style="width: 40px; ">-->
                                    <span class="input-group-text bg-white border-0">
                                        <i class="bi bi-calendar-event"></i>
                                    </span>
                                </div>
                            </div>
                            <div id="revenueChart" style="height: 370px; width: 100%;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-md-6 mb-3">
                    <div class="card shadow border-0">
                        <div class="card-body py-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 p-0 py-3" style="font-size: 18px; font-weight: 500; color: #012970; font-family: 'Poppins', sans-serif;">
                                    Fee balances
                                </h5>
                                <div class="position-relative">
                                    <!--<input type="month" id="bal_input" class="form-control opacity-0 position-absolute" style="width: 40px; ">-->
                                    <span class="input-group-text bg-white border-0">
                                        <i class="bi bi-calendar-event"></i>
                                    </span>
                                </div>
                            </div>

                            <div id="balChart" style="height: 370px; width: 100%;"></div>
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