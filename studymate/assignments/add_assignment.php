<!-- Add Assignment Modal -->

<div class="modal fade" id="addAssignmentModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form action="save_assignment.php" method="POST">

                <div class="modal-header">

                    <h5 class="modal-title">

                        Add Assignment

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

                            Assignment Title

                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Subject

                        </label>

                        <input
                            type="text"
                            name="subject"
                            class="form-control"
                            required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            Description

                        </label>

                        <textarea
                            name="description"
                            class="form-control"
                            rows="4"></textarea>

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <label class="form-label">

                                Due Date

                            </label>

                            <input
                                type="date"
                                name="due_date"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">

                                Priority

                            </label>

                            <select
                                name="priority"
                                class="form-select"
                                required>

                                <option value="High">High</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>

                            </select>

                        </div>

                    </div>

                    <div class="mt-3">

                        <label class="form-label">

                            Status

                        </label>

                        <select
                            name="status"
                            class="form-select">

                            <option value="Pending">

                                Pending

                            </option>

                            <option value="Completed">

                                Completed

                            </option>

                        </select>

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
                        type="submit"
                        class="btn btn-primary">

                        Save Assignment

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>