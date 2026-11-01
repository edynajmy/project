<!-- Add Note Modal -->

<div class="modal fade" id="addNoteModal" tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<form action="save_note.php" method="POST">

<div class="modal-header">

<h5 class="modal-title">

📝 Add New Note

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

Title

</label>

<input
type="text"
name="title"
class="form-control"
placeholder="Example: CSC264 Revision"
required>

</div>

<div class="mb-3">

<label class="form-label">

Content

</label>

<textarea
name="content"
rows="8"
class="form-control"
placeholder="Write your notes here..."
required>

</textarea>

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

Save Note

</button>

</div>

</form>

</div>

</div>

</div>