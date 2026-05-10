<?php

$isEdit = isset($isEdit) ? $isEdit : true; // default reuse in both
?>
<div class="form-group">
    <label>Activity Title <span class="req">*</span></label>
    <input type="text" name="title" id="editTitle" placeholder="Enter activity title" required>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Type <span class="req">*</span></label>
        <select name="activity_type" id="editType">
            <?php foreach (['Meeting','Sports','Cultural','Community Service','Livelihood','Health','Educational','Other'] as $t): ?>
            <option value="<?=$t?>"><?=$t?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Status</label>
        <select name="status" id="editStatus">
            <?php foreach (['Pending','Ongoing','Completed','Cancelled'] as $s): ?>
            <option value="<?=$s?>"><?=$s?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="form-group">
    <label>Venue</label>
    <input type="text" name="venue" id="editVenue" placeholder="Location/venue">
</div>
<div class="form-row">
    <div class="form-group">
        <label>Date</label>
        <input type="date" name="activity_date" id="editDate">
    </div>
    <div class="form-group">
        <label>Time</label>
        <input type="time" name="activity_time" id="editTime">
    </div>
</div>
<div class="form-group">
    <label>Description</label>
    <textarea name="description" id="editDescription" rows="3" placeholder="Describe this activity…"></textarea>
</div>