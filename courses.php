<?php
require_once 'header.php';
   include 'function.php';
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Courses</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>
                   <div class="card-body">
                    <?php 
                    $sql = "SELECT `id`, `entry_id`, `email`, `firstname`, `lastname`, `phone_number`, `program`, `country`, `datee` FROM `register` WHERE source =1  ORDER BY datee DESC";
$result = $conn->query($sql);
                    ?>
                    <div class="table-responsive overflow">
                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0" data-order='[[ 6, "desc" ]]'>
    <thead>
        <tr>
            <th class="nowrap">Email</th>
            <th class="nowrap">Firstname</th>
            <th class="nowrap">Lastname</th>
            <th class="nowrap">Phone Number</th>
            <th class="nowrap">Program</th>
            <th class="nowrap">Country</th>
            <th class="nowrap">Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result->num_rows > 0) {
            // Output data of each row
            while ($row = $result->fetch_assoc()) {
                echo "<tr onclick=\"location.href='includes/enquiry_details.inc.php?from=get_in_touch&entry_id=" . $row['entry_id'] . "'\">";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['firstname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['lastname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                echo "<td>" . htmlspecialchars(check_course($conn,$row['program'])) . "</td>";
                echo "<td>" . htmlspecialchars($row['country']) . "</td>";
                echo "<td>" . htmlspecialchars($row['datee']) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No results found</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php
$conn->close();
?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
require_once 'footer.php';
?>