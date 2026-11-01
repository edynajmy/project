<!-- Add Schedule Modal -->

<div class="modal fade" id="addScheduleModal" tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form action="save_schedule.php" method="POST">

<div class="modal-header">

<h5 class="modal-title">

📅 Add Study Schedule

</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">

</button>

</div>

<div class="modal-body">

<div class="mb-3">

<label class="form-label">

Subject

</label>

<input
type="text"
class="form-control"
name="subject"
required>

</div>

<div class="mb-3">

<label class="form-label">

Topic

</label>

<input
type="text"
class="form-control"
name="topic"
required>

</div>

<div class="mb-3">

<label class="form-label">

Study Date

</label>

<input
type="date"
class="form-control"
name="study_date"
required>

</div>

<div class="row">

<div class="col-md-6">

<label class="form-label">

Start Time

</label>

<input
type="time"
class="form-control"
name="start_time"
required>

</div>

<div class="col-md-6">

<label class="form-label">

End Time

</label>

<input
type="time"
class="form-control"
name="end_time"
required>

</div>

</div>

</div>

<div class="modal-footer">

<button
type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Cancel

</button>

<button
type="submit"
class="btn btn-primary">

Save Schedule

</button>

</div>

</form>

</div>

</div>

</div>