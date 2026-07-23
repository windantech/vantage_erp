<?php
session_start();
if (isset($_SESSION['entry_id'])) {
    $entry_id = $_SESSION['entry_id']; ?>
    <!-- More Details Modal -->
    <div class="modal fade overflow" id="moreDetailsModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="moreDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-0">
                <div class="modal-header bg_main rounded-0 p-2 d-flex align-items-center">
                    <h1 class="modal-title fs-6 text-uppercase" id="moreDetailsModalLabel">
                        What is Lorem Ipsum? <?php echo $entry_id; ?>
                    </h1>
                    <i class="bi bi-x-lg btn p-0" data-bs-dismiss="modal" aria-label="Close"></i>
                </div>
                <div class="modal-body">
                    <div class="w-100 d-flex align-items-center">
                        <div class="w-auto">
                            <div class="client_name" style="width: 8vh; height: 8vh;">
                                <span>Aisha</span>
                                <span>Juma</span>
                            </div>
                        </div>
                        <div class="w-75 d-flex flex-column">
                            <span class="ms-3 text_main d-flex align-items-center">
                                Aisha Juma <<a href="mailto:aishajuma@gmail.com" class="btn p-0 text_main">aishajuma@gmail.com</a>>
                            </span>
                            <span class="ms-3">8/20/2024 4:44 PM</span>
                        </div>
                    </div>

                    <hr>
                    <p class="text_justify">
                        Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- More Details Modal -->
<?php } else {
    echo 2;
}
