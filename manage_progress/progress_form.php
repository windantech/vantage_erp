<div class="row">
    <div class="col-md-6">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
            <select class="form-control rounded-0" name="task_id" id="task_id">
                <option></option>
                <?php
                $tasks = $conn->query("SELECT * FROM task_list where project_id = '$id' order by task asc ");
                while ($row = $tasks->fetch_assoc()):
                ?>
                    <option value="<?php echo $row['id'] ?>" <?php echo isset($task_id) && $task_id == $row['id'] ? "selected" : '' ?>><?php echo ucwords($row['task']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="col-md-6">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Subject</span>
            <input type="text" name="subject" id="subject" class="form-control rounded-0" aria-label="Subject" aria-describedby="basic-addon1">
        </div>
    </div>

    <div class="col-md-12">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Date</span>
            <input type="date" name="date" id="date" class="form-control rounded-0" aria-label="Date" aria-describedby="basic-addon1">
        </div>
    </div>

    <div class="col-md-6">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Start Time</span>
            <input type="time" name="start_time" id="start_time" class="form-control rounded-0" aria-label="Start Time" aria-describedby="basic-addon1">
        </div>
    </div>

    <div class="col-md-6">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">End Time</span>
            <input type="time" name="end_time" id="end_time" class="form-control rounded-0" aria-label="End Time" aria-describedby="basic-addon1">
        </div>
    </div>

    <div class="col-md-12">
        <div class="input-group mb-3">
            <span class="input-group-text rounded-0 bg_main" id="basic-addon1">Comment/Progress Description</span>
            <textarea name="comment" id="summernote1" cols="30" rows="10" class="form-control">
                <span id="comment"></span>
            </textarea>
        </div>
    </div>
</div>