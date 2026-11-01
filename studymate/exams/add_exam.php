<!-- Add Exam Modal -->

<div class="modal fade" id="addExamModal" tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form action="save_exam.php" method="POST">

<div class="modal-header">

<h5 class="modal-title">

📅 Add New Exam

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

Exam Title

</label>

<input
type="text"
class="form-control"
name="exam_title"
required>

</div>

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

<div class="row">

<div class="col-md-6">

<label class="form-label">

Exam Date

</label>

<input
type="date"
class="form-control"
name="exam_date"
required>

</div>

<div class="col-md-6">

<label class="form-label">

Exam Time

</label>

<input
type="time"
class="form-control"
name="exam_time"
required>

</div>

</div>

<div class="mt-3">

<label class="form-label">

Venue

</label>

<input
type="text"
class="form-control"
name="venue"
required>

</div>

<div class="mt-3">

<label class="form-label">

Notes

</label>

<textarea
class="form-control"
rows="4"
name="notes"
placeholder="Example: Bring calculator and student card">

</textarea>

</div>

</div>

<div class="modal-footer">

<button
class="btn btn-secondary"
data-bs-dismiss="modal"
type="button">

Cancel

</button>

<button
class="btn btn-primary"
type="submit">

Save Exam

</button>

</div>

</form>

</div>

</div>

</div>