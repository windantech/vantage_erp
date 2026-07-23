<div class="input-group mb-3">
    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Task</span>
    <input type="text" name="task_name" id="task_name" class="form-control rounded-0" aria-label="Task" aria-describedby="basic-addon1">
</div>
<div class="input-group mb-3">
    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Description</span>
    <textarea name="task_desc" id="summernote" cols="30" rows="10" class="form-control summernote_width">
        <span id="task_desc"></span>
    </textarea>
</div>
<div class="input-group mb-3">
    <span class="input-group-text rounded-0 bg_main" style="width: 9rem;" id="basic-addon1">Status</span>
    <select class="form-control rounded-0" name="task_status" id="task_status">
        <option value="1">Pending</option>
        <option value="2">On-Progress</option>
        <option value="3">Done</option>
    </select>
</div>
