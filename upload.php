<?php
require_once 'header.php';
require "function.php";
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
                            <h6 class="m-0 font-weight-bold text-white text-uppercase">Upload Image</h6>
                        </div>
                        <div class="w-50 d-flex justify-content-end">
                            <button class="btn border-0 p-0 ms-3" data-bs-toggle="modal" data-bs-target="#addLoadedData">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                            <button onclick="location.href=''" class="btn border-0 p-0 ms-3">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add Loaded Data Modal -->
          <!-- Upload Image Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Image</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select Image</label>
                        <input type="file" name="image" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success">Upload</button>
                </form>
            </div>
        </div>
    </div>
</div>

                
                <?php


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $targetDir = "uploads/";
    
    // Get the file extension
    $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    
    // Generate a unique name for the file
    $newFileName = uniqid('img_', true) . '.' . $fileExtension;
    $targetFilePath = $targetDir . $newFileName;

    // Move uploaded file
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
        $sql = "INSERT INTO images (image_url) VALUES ('$targetFilePath')";
        if ($conn->query($sql) === TRUE) {
            echo "<script>
                window.alert('Added!');
                window.location.href='upload';
            </script>";
        } else {
            echo "<script>
                window.alert('Failed!');
                window.location.href='upload';
            </script>";
        }
    } else {
        echo "<script>
            window.alert('Failed!');
            window.location.href='upload';
        </script>";
    }
}



?>

                <!-- Add Loaded Data Modal -->

                <div class="card-body">
                   
                   <div class="table-responsive overflow">
    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#uploadModal">Upload Image</button>
    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
        <thead>
            <tr>
                <th class="nowrap">Image</th>
                <th class="nowrap">Image URL</th>
                <th class="nowrap">Copy URL</th>
                <th class="nowrap">Uploaded At</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include 'db_connection.php';
            $sql = "SELECT * FROM images ORDER BY uploaded_at DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $imageUrl = htmlspecialchars($row['image_url']);
                    echo "<tr>";
                    echo "<td><img src='" . $imageUrl . "' width='100' height='100'></td>";
                    echo "<td><input  id='copyText' type='text' class='form-control url-input' value='https://vantageafricaleaders.com/admin/" . $imageUrl . "' readonly></td>";
                    echo "<td> <button class='btn btn-success btn-copy' onclick='copyFunction()'>Copy</button>  </td>";
                    echo "<td>" . htmlspecialchars($row['uploaded_at']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4'>No images uploaded</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
 
                   
                </div>
            </div>
        </div>
    </div>
</section>

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