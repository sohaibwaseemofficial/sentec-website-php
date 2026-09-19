<?php
include 'header.php';
include '../db_connection.php';

$ambassadors = [];
$teamParticipants = [];

try {
    $table = $conn->query("SHOW TABLES LIKE 'brand_ambassadors'");
    if ($table && $table->num_rows > 0) {
        $hasTypeColumn = false;
        $col = $conn->query("SHOW COLUMNS FROM brand_ambassadors LIKE 'ambassador_type'");
        if ($col && $col->num_rows > 0) {
            $hasTypeColumn = true;
        }
        $columns = 'id, name, email, code';
        if ($hasTypeColumn) {
            $columns .= ', ambassador_type';
        }
        $ambRes = $conn->query("SELECT {$columns} FROM brand_ambassadors ORDER BY name ASC");
        if ($ambRes) {
            while ($row = $ambRes->fetch_assoc()) {
                $email = trim($row['email'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $ambassadors[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => trim($row['name'] ?? ''),
                    'email' => $email,
                    'code' => trim($row['code'] ?? ''),
                    'type' => $hasTypeColumn ? trim($row['ambassador_type'] ?? '') : ''
                ];
            }
        }
    }
} catch (Exception $e) {
    // Swallow schema lookup errors silently for this page
}
try {
    $teamRes = $conn->query("SELECT id, team_name, module_selection, institution_type,
        participant1_name, participant1_email,
        participant2_name, participant2_email,
        participant3_name, participant3_email,
        participant4_name, participant4_email
        FROM event_registrations
        ORDER BY created_at DESC");
    if ($teamRes) {
        while ($row = $teamRes->fetch_assoc()) {
            $teamName = trim($row['team_name'] ?? '');
            $module = trim($row['module_selection'] ?? '');
            $institution = trim($row['institution_type'] ?? '');
            for ($i = 1; $i <= 4; $i++) {
                $email = trim($row["participant{$i}_email"] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $name = trim($row["participant{$i}_name"] ?? '');
                $teamParticipants[] = [
                    'registration_id' => (int)$row['id'],
                    'key' => "participant{$i}",
                    'name' => $name !== '' ? $name : "Participant {$i}",
                    'email' => $email,
                    'team_name' => $teamName,
                    'module' => $module,
                    'institution' => $institution
                ];
            }
        }
    }
} catch (Exception $e) {
    // Ignore
}
?>

<div class="page-header">
    <h2><i class="fas fa-envelope-open-text me-2"></i> Email Center</h2>
    <p class="text-muted">Send on-brand, responsive messages to ambassadors, registered teams, or custom recipients.</p>
</div>

<div class="glass-panel">
    <form id="universalEmailForm" method="post">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="mb-4">
                    <label class="form-label">Select from existing contacts</label>
                    <select class="form-select" name="predefined_recipients[]" id="predefinedRecipients" multiple
                        style="min-height:260px; background:#000; border-color:#444; color:#fff;">
                        <?php if (!empty($ambassadors)): ?>
                            <optgroup label="Ambassadors (<?php echo count($ambassadors); ?>)">
                                <?php foreach ($ambassadors as $amb): ?>
                                    <option value="ambassador:<?php echo (int)$amb['id']; ?>">
                                        <?php echo htmlspecialchars($amb['name'] !== '' ? $amb['name'] : $amb['email']); ?>
                                        (<?php echo htmlspecialchars($amb['email']); ?>)
                                        <?php if (!empty($amb['code'])): ?>
                                            &mdash; Code: <?php echo htmlspecialchars($amb['code']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($teamParticipants)): ?>
                            <optgroup label="Teams &amp; Participants (<?php echo count($teamParticipants); ?>)">
                                <?php foreach ($teamParticipants as $participant): ?>
                                    <option value="team:<?php echo (int)$participant['registration_id']; ?>:<?php echo htmlspecialchars($participant['key']); ?>">
                                        <?php echo htmlspecialchars($participant['name']); ?>
                                        &mdash; <?php echo htmlspecialchars($participant['team_name']); ?>
                                        (<?php echo htmlspecialchars($participant['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <small class="text-muted d-block mt-2" id="universalSelectedCount" style="font-size:0.75rem;">Hold Ctrl / Cmd to select multiple contacts.</small>
                </div>
                <div class="mb-4">
                    <label class="form-label">Manual recipients</label>
                    <textarea class="form-control" name="manual_recipients" id="manualRecipientsField" rows="5"
                        placeholder="Enter one email per line. You can also use Name &lt;email@example.com&gt; format."
                        style="background:#000; border-color:#444; color:#fff;"></textarea>
                    <small class="text-muted d-block mt-2" style="font-size:0.75rem;">Manual entries are appended to the selected contacts.</small>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Email Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Subject" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Header Badge</label>
                        <input type="text" name="title" class="form-control" value="SENTEC Update" placeholder="Label above heading" style="background:#000; border-color:#444; color:#fff;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Headline</label>
                        <input type="text" name="heading" class="form-control" placeholder="e.g. Congratulations!" style="background:#000; border-color:#444; color:#fff;" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Greeting Line</label>
                        <input type="text" name="greeting" class="form-control" value="Hello {{name}}," placeholder="Use placeholders like {{name}} or {{team_name}}" style="background:#000; border-color:#444; color:#fff;">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Message Body</label>
                    <textarea name="body" class="form-control" rows="7" placeholder="Share details. Support placeholders: {{name}}, {{team_name}}, {{module}}, {{code}}." style="background:#000; border-color:#444; color:#fff;" required></textarea>
                </div>
                <div class="row g-3 mt-3">
                    <div class="col-md-6">
                        <label class="form-label">CTA Button Label <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                        <input type="text" name="cta_label" class="form-control" placeholder="e.g. View Schedule" style="background:#000; border-color:#444; color:#fff;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CTA Button URL <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                        <input type="url" name="cta_url" class="form-control" placeholder="https://" style="background:#000; border-color:#444; color:#fff;">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Footer Note <span class="text-muted" style="font-size:0.75rem;">(optional)</span></label>
                    <textarea name="footer_note" class="form-control" rows="2" placeholder="Override the default SENTEC footer if needed." style="background:#000; border-color:#444; color:#fff;"></textarea>
                </div>
                <small class="text-muted d-block mt-3" style="font-size:0.75rem;">Supported placeholders: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{team_name}}</code>, <code>{{module}}</code>, <code>{{institution}}</code>, <code>{{code}}</code>, <code>{{leader_name}}</code>.</small>
                <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                    <button type="reset" class="btn btn-outline-secondary btn-sm">Clear Form</button>
                    <button type="submit" id="universalEmailSendBtn" class="btn-neon">Send Email</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const universalForm = document.getElementById('universalEmailForm');
    const sendBtn = document.getElementById('universalEmailSendBtn');
    const recipientsSelect = document.getElementById('predefinedRecipients');
    const selectedCountLabel = document.getElementById('universalSelectedCount');
    const manualField = document.getElementById('manualRecipientsField');

    function updateSelectedCount() {
        if (!recipientsSelect || !selectedCountLabel) { return; }
        const options = Array.from(recipientsSelect.options || []).filter(function(opt) { return !opt.disabled; });
        const selected = options.filter(function(opt) { return opt.selected; }).length;
        const total = options.length;
        if (total === 0) {
            selectedCountLabel.textContent = 'No saved contacts available.';
        } else if (selected === 0) {
            selectedCountLabel.textContent = 'No saved contacts selected.';
        } else {
            selectedCountLabel.textContent = selected + ' contact(s) selected out of ' + total + '.';
        }
    }

    if (recipientsSelect) {
        recipientsSelect.addEventListener('change', updateSelectedCount);
        updateSelectedCount();
    }

    if (universalForm) {
        universalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const selectedCount = recipientsSelect
                ? Array.from(recipientsSelect.options || []).filter(function(opt) { return opt.selected; }).length
                : 0;
            const manualEntries = manualField ? manualField.value.trim() : '';
            if (selectedCount === 0 && manualEntries === '') {
                alert('Please select or enter at least one recipient.');
                return;
            }
            if (!sendBtn) { return; }
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            const formData = new FormData(universalForm);
            fetch('custom_email_anyone.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                const sent = data && typeof data.sent !== 'undefined' ? data.sent : 0;
                const errors = data && Array.isArray(data.errors) && data.errors.length ? '\nErrors:\n' + data.errors.join('\n') : '';
                alert('Sent: ' + sent + ' email(s).' + errors);
                if (sent > 0) {
                    universalForm.reset();
                    updateSelectedCount();
                }
            })
            .catch(function(err) {
                alert('Custom email failed: ' + err);
            })
            .finally(function() {
                sendBtn.disabled = false;
                sendBtn.innerHTML = 'Send Email';
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>
