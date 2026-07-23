    <?php
    if(isset($_POST['user_select'])){
        ?>
        <script>

            window.location.href="create_role?action=<?php echo $_POST['user_select']; ?>";
        </script>
        <?php
    }