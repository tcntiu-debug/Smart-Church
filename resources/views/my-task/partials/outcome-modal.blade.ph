{{-- resources/views/my-task/partials/outcome-modal.blade.php --}}

<div class="modal fade" id="outcomeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="outcomeForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6>Log Result</h6>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <p id="out-task-text" class="small text-muted mb-3"></p>
                <input type="hidden" name="action" value="log_outcome">
                <input type="hidden" name="tracking_id" id="out-track-id">
                <input type="hidden" name="week" id="out-week">
                <input type="hidden" name="task_key" id="out-task-key">
                
                <div class="form-group">
                    <label><strong>What was the outcome?</strong></label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="outcome" id="o1" value="Successful - Positive" required>
                        <label class="form-check-label" for="o1">Successful Contact - Positive</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="outcome" id="o2" value="Successful - Neutral">
                        <label class="form-check-label" for="o2">Successful Contact - Neutral</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="outcome" id="o3" value="Successful - Negative">
                        <label class="form-check-label" for="o3">Successful Contact - Negative</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="outcome" id="o4" value="Unable to Contact - No Answer">
                        <label class="form-check-label" for="o4">Unable to Contact - No Answer</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="outcome" id="o5" value="Unable to Contact - Invalid Number">
                        <label class="form-check-label" for="o5">Unable to Contact - Invalid Number</label>
                    </div>
                </div>
                
                <div class="form-group" id="out-comment-wrapper" style="display:none;">
                    <label><strong>Add brief comment</strong></label>
                    <textarea class="form-control" name="comment" id="out-comment" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="save-log-btn">Submit Log</button>
            </div>
        </form>
    </div>
</div>