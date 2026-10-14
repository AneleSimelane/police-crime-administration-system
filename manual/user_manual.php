<?php
// Eswatini Police CAS - User Manual
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

$currentUser = getCurrentUser();
$role = $currentUser ? $currentUser['role'] : null;
?>

<style>
    .manual-toc { position: sticky; top: 1rem; }
    .manual-toc .list-group-item { border: none; padding: 0.45rem 1rem; font-size: 0.9rem; }
    .manual-toc .list-group-item.active { background-color: var(--primary-color); border-color: var(--primary-color); }
    .manual-section { scroll-margin-top: 1.5rem; }
    .manual-section h2 { border-bottom: 3px solid var(--secondary-color); padding-bottom: 0.5rem; margin-bottom: 1.25rem; }
    .manual-section h3 { color: var(--primary-color); margin-top: 1.5rem; }
    .role-badge { font-size: 0.75rem; letter-spacing: 0.5px; }
    .workflow-step { border-left: 4px solid var(--secondary-color); padding-left: 1rem; margin-bottom: 1rem; }
    @media print {
        .manual-toc, .no-print { display: none !important; }
        .manual-section { page-break-inside: avoid; }
    }
</style>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="glass-card p-4 border text-white" style="background: linear-gradient(135deg, #0c2340 0%, #16365c 100%);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-book text-warning"></i> CAS User Manual</h1>
                    <p class="mb-0 text-white-50">Crime Administration System — operational guide for all portal users</p>
                </div>
                <div>
                    <button onclick="window.print()" class="btn btn-warning btn-sm fw-bold">
                        <i class="fa-solid fa-print me-1"></i> Print Manual
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Table of Contents -->
    <div class="col-lg-3 no-print">
        <div class="glass-card border p-3 manual-toc">
            <h6 class="fw-bold text-primary text-uppercase mb-3"><i class="fa-solid fa-list text-warning"></i> Contents</h6>
            <div class="list-group list-group-flush">
                <a href="#intro" class="list-group-item list-group-item-action">1. Introduction</a>
                <a href="#getting-started" class="list-group-item list-group-item-action">2. Getting Started</a>
                <a href="#roles" class="list-group-item list-group-item-action">3. User Roles</a>
                <a href="#workflow" class="list-group-item list-group-item-action">4. Case Workflow</a>
                <a href="#citizen" class="list-group-item list-group-item-action">5. Citizen Guide</a>
                <a href="#desk-officer" class="list-group-item list-group-item-action">6. Desk Officer Guide</a>
                <a href="#officer" class="list-group-item list-group-item-action">7. Investigator Guide</a>
                <a href="#hod" class="list-group-item list-group-item-action">8. HOD Guide</a>
                <a href="#admin" class="list-group-item list-group-item-action">9. Admin Guide</a>
                <a href="#modules" class="list-group-item list-group-item-action">10. System Modules</a>
                <a href="#emergency" class="list-group-item list-group-item-action">11. Emergency & Panic</a>
                <a href="#faq" class="list-group-item list-group-item-action">12. FAQ & Troubleshooting</a>
            </div>
            <?php if ($role): ?>
            <div class="mt-3 p-2 bg-light rounded small">
                <strong>Your role:</strong>
                <span class="badge bg-primary role-badge text-uppercase"><?php echo htmlspecialchars(str_replace('_', ' ', $role)); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Manual Content -->
    <div class="col-lg-9">
        <div class="glass-card border p-4 p-md-5 shadow-sm">

            <!-- 1. Introduction -->
            <section id="intro" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-circle-info text-warning me-2"></i>1. Introduction</h2>
                <p>The <strong>Eswatini Police Crime Administration System (CAS)</strong> is a web-based portal for reporting crimes, managing investigation dockets, and tracking cases from initial complaint through to court referral or closure.</p>
                <p>The system supports Eswatini's administrative structure, including the four regions (Hhohho, Manzini, Shiselweni, Lubombo) and traditional authority details (Chief and Indvuna) for citizen profiles.</p>
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="fw-bold"><i class="fa-solid fa-check-circle text-success"></i> Key Capabilities</h6>
                            <ul class="mb-0 small">
                                <li>Online crime reporting with GPS location capture</li>
                                <li>Emergency panic dispatch to nearest station</li>
                                <li>Digital statement approval by complainants</li>
                                <li>Evidence upload and docket management</li>
                                <li>Inter-station case transfers</li>
                                <li>PDF docket export and printing</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <h6 class="fw-bold"><i class="fa-solid fa-globe text-primary"></i> Access Requirements</h6>
                            <ul class="mb-0 small">
                                <li>Modern web browser (Chrome, Firefox, Edge, Safari)</li>
                                <li>Internet connection</li>
                                <li>For GPS features: allow location access when prompted</li>
                                <li>Citizens must register; police staff receive accounts from administration</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. Getting Started -->
            <section id="getting-started" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-rocket text-warning me-2"></i>2. Getting Started</h2>

                <h3>2.1 First-Time System Setup (Administrators)</h3>
                <ol>
                    <li>Install the system in your XAMPP <code>htdocs</code> folder and start Apache and MySQL.</li>
                    <li>Navigate to <strong>Setup DB</strong> on the landing page or visit <code>/initialize.php</code>.</li>
                    <li>Click <strong>Setup &amp; Seed Database</strong> to create tables and demo accounts.</li>
                    <li>Proceed to the portal login once setup completes successfully.</li>
                </ol>

                <h3>2.2 Citizen Registration</h3>
                <ol>
                    <li>From the landing page, click <strong>Register Citizen Profile</strong>.</li>
                    <li>Complete all required fields: name, email, password, phone, address, date of birth, and region.</li>
                    <li>Optionally provide traditional details (Chief, Indvuna), occupation, and employer information.</li>
                    <li>After registration, sign in with your email and password.</li>
                </ol>

                <h3>2.3 Logging In</h3>
                <ol>
                    <li>Click <strong>Access CAS Portal</strong> or <strong>Police Login</strong> from the landing page.</li>
                    <li>Enter your registered email address and password.</li>
                    <li>Upon successful login, you are redirected to your role-specific dashboard.</li>
                </ol>

                <h3>2.4 Navigation</h3>
                <p>After login, use the <strong>sidebar menu</strong> on the left to access modules. The top bar shows your name, role, and a dark mode toggle. Use <strong>Toggle Menu</strong> on mobile devices to show or hide the sidebar.</p>
            </section>

            <!-- 3. User Roles -->
            <section id="roles" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-users text-warning me-2"></i>3. User Roles &amp; Permissions</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Primary Functions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-info">Citizen</span></td>
                                <td>Complainant / victim reporting a crime</td>
                                <td>Report cases, approve statements, track investigations, trigger emergency alerts</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-secondary">Desk Officer</span></td>
                                <td>Station front desk personnel</td>
                                <td>Register walk-in complaints, transcribe statements, handle emergency queue</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Officer</span></td>
                                <td>Police investigator</td>
                                <td>Accept cases, add diary notes, upload evidence, log arrests and warrants</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning text-dark">HOD</span></td>
                                <td>Head of Department</td>
                                <td>Assign cases to investigators, review progress, forward to court or close cases</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-danger">Admin</span></td>
                                <td>Station Commander</td>
                                <td>Manage force directory, approve inter-station transfers, oversee station statistics</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2"><i class="fa-solid fa-lock"></i> Each role can only access modules and case data permitted for their station and assignment. Unauthorized access attempts redirect to the dashboard with an access denied message.</p>
            </section>

            <!-- 4. Case Workflow -->
            <section id="workflow" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-diagram-project text-warning me-2"></i>4. Case Lifecycle Workflow</h2>
                <p>Every case follows a defined procedural workflow. Understanding these stages helps all users know what action is required at each step.</p>

                <div class="workflow-step">
                    <strong>Step 1 — Case Reported</strong>
                    <p class="mb-0 small text-muted">Citizen submits online report or desk officer registers a walk-in. Status: <code>pending_approval</code>.</p>
                </div>
                <div class="workflow-step">
                    <strong>Step 2 — Statement Transcribed</strong>
                    <p class="mb-0 small text-muted">Desk officer records the official statement from the complainant.</p>
                </div>
                <div class="workflow-step">
                    <strong>Step 3 — Citizen Statement Approval</strong>
                    <p class="mb-0 small text-muted">Complainant reviews and approves or rejects the statement. Approval moves the case to <code>pending_assignment</code>. <em>Investigators cannot add progress notes until the statement is approved.</em></p>
                </div>
                <div class="workflow-step">
                    <strong>Step 4 — HOD Assignment</strong>
                    <p class="mb-0 small text-muted">Head of Department assigns an investigator. Status: <code>pending_investigator_acceptance</code>.</p>
                </div>
                <div class="workflow-step">
                    <strong>Step 5 — Investigator Acceptance</strong>
                    <p class="mb-0 small text-muted">Assigned officer accepts the case. Status: <code>under_investigation</code>.</p>
                </div>
                <div class="workflow-step">
                    <strong>Step 6 — Active Investigation</strong>
                    <p class="mb-0 small text-muted">Officer adds diary entries, uploads evidence, and logs warrants or arrests as applicable.</p>
                </div>
                <div class="workflow-step">
                    <strong>Step 7 — Review &amp; Closure</strong>
                    <p class="mb-0 small text-muted">HOD reviews the docket and forwards to court (<code>court_referral</code>) or closes the case (<code>closed</code>).</p>
                </div>
            </section>

            <!-- 5. Citizen Guide -->
            <section id="citizen" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-user text-warning me-2"></i>5. Citizen (Complainant) Guide</h2>

                <h3>5.1 Reporting a New Crime</h3>
                <ol>
                    <li>From the dashboard, click <strong>Report New Crime Case</strong> or go to <strong>Cases / Dockets</strong> and select the new report option.</li>
                    <li>Enter the case title, detailed description, crime type, incident date, and preferred police station.</li>
                    <li>Set priority (Low, Medium, or High) as appropriate.</li>
                    <li>Allow GPS access to capture incident coordinates automatically, or enter location manually.</li>
                    <li>Optionally attach supporting evidence files (images, video, audio, PDF — max 20 MB each).</li>
                    <li>Submit the report. Note your case reference number (e.g., <code>CAS 12/08/2026</code>).</li>
                </ol>

                <h3>5.2 Approving Your Statement</h3>
                <ol>
                    <li>After a desk officer transcribes your statement, you receive a dashboard alert.</li>
                    <li>Open <strong>Cases / Dockets</strong> and view the case details.</li>
                    <li>Scroll to the Statements section and review the transcribed text carefully.</li>
                    <li>Click <strong>Approve Statement</strong> if accurate, or <strong>Reject</strong> if corrections are needed.</li>
                    <li>Approval unlocks investigation — the case moves to pending assignment.</li>
                </ol>

                <h3>5.3 Tracking Your Cases</h3>
                <p>Use <strong>Cases / Dockets</strong> to view all your reports. Each case shows its current status, assigned investigator (once assigned), and a timeline of updates. Click <strong>View</strong> on any case for full docket details.</p>

                <h3>5.4 Profile Management</h3>
                <p>Update your personal details under <strong>My Profile</strong>. Change your password under <strong>Account Settings</strong> (minimum 8 characters required).</p>
            </section>

            <!-- 6. Desk Officer Guide -->
            <section id="desk-officer" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-headset text-warning me-2"></i>6. Desk Officer Guide</h2>

                <h3>6.1 Recording Walk-in Complaints</h3>
                <ol>
                    <li>From the dashboard, click <strong>Record Walk-in Complaint</strong>.</li>
                    <li>Enter the complainant's personal details. If they are not registered, a citizen account is created automatically.</li>
                    <li>Capture traditional details (Chief, Indvuna, Region) and incident information.</li>
                    <li>Submit to create the case docket at your station.</li>
                </ol>

                <h3>6.2 Transcribing Statements</h3>
                <ol>
                    <li>Go to <strong>Cases / Dockets</strong> and locate the case awaiting a statement.</li>
                    <li>Open the case and use the statement transcription form.</li>
                    <li>Record the complainant's account accurately and submit.</li>
                    <li>The statement is sent to the citizen for digital approval before investigation proceeds.</li>
                </ol>

                <h3>6.3 Handling Emergency Reports</h3>
                <p>High-priority panic alerts appear on your dashboard with a red emergency banner. Review these cases immediately from the emergency filter in the cases list. Panic cases are auto-routed to the nearest station based on GPS coordinates.</p>

                <h3>6.4 Printing Reports</h3>
                <p>From the station incidents queue or <strong>Reports &amp; Export</strong>, select a case and click <strong>Print</strong> to generate a printable incident report.</p>
            </section>

            <!-- 7. Investigator Guide -->
            <section id="officer" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-magnifying-glass text-warning me-2"></i>7. Investigator (Officer) Guide</h2>

                <h3>7.1 Accepting Assigned Cases</h3>
                <ol>
                    <li>Check your dashboard for newly assigned dockets.</li>
                    <li>Open the case details page and click <strong>Accept Case</strong>.</li>
                    <li>The case status changes to <code>under_investigation</code>.</li>
                </ol>

                <h3>7.2 Investigation Diary &amp; Progress Notes</h3>
                <p>On the case details page, add diary entries documenting investigation steps, witness interviews, and findings. Notes are timestamped and visible in the case timeline.</p>
                <p class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Important:</strong> Progress notes, suspect arrests, and warrant logging are locked until the citizen has approved the official statement.</p>

                <h3>7.3 Evidence Management</h3>
                <ol>
                    <li>Upload evidence from the case details page or the <strong>Evidence Files</strong> module.</li>
                    <li>Supported formats: JPG, PNG, MP4, MOV, AVI, MP3, WAV, PDF, DOC, DOCX.</li>
                    <li>Add a description for each file to maintain chain-of-custody clarity.</li>
                </ol>

                <h3>7.4 Suspects, Warrants &amp; Arrests</h3>
                <p>From the case docket, use the suspect manager to log warrant applications and arrest records. These update the case status to <code>warrant_issued</code> or <code>arrest_made</code> as applicable.</p>

                <h3>7.5 Exporting the Docket</h3>
                <p>Click <strong>Compile &amp; Export PDF Docket</strong> on the case page to open a printable layout containing the full case record. Use your browser's print function (<kbd>Ctrl</kbd> + <kbd>P</kbd>) to save as PDF or print.</p>
            </section>

            <!-- 8. HOD Guide -->
            <section id="hod" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-sitemap text-warning me-2"></i>8. Head of Department (HOD) Guide</h2>

                <h3>8.1 Assigning Cases to Investigators</h3>
                <ol>
                    <li>Review the <strong>Docket Allocation Queue</strong> on your dashboard.</li>
                    <li>Click <strong>Assign Officer</strong> on a pending case, or use the Smart Allocator in Cases.</li>
                    <li>Select an available investigator from your department and confirm.</li>
                </ol>

                <h3>8.2 Monitoring Department Cases</h3>
                <p>View all department cases from <strong>Cases / Dockets</strong>. Filter by status and priority to manage workload across your team.</p>

                <h3>8.3 Case Review &amp; Disposition</h3>
                <p>When investigation is complete, review the full docket including evidence and diary entries. Forward the case to court or close it with appropriate notes.</p>

                <h3>8.4 Force Directory</h3>
                <p>HOD users can view the <strong>Force Directory</strong> to see officers assigned to the station and department.</p>
            </section>

            <!-- 9. Admin Guide -->
            <section id="admin" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-shield-halved text-warning me-2"></i>9. Station Commander (Admin) Guide</h2>

                <h3>9.1 Force Management</h3>
                <ol>
                    <li>Navigate to <strong>Force Directory</strong> from the sidebar.</li>
                    <li>Register new desk officers, investigators, and department heads for your station.</li>
                    <li>Assign force numbers, departments, and contact details.</li>
                </ol>

                <h3>9.2 Inter-Station Case Transfers</h3>
                <ol>
                    <li>Go to <strong>Cases / Dockets</strong> and open the transfers section.</li>
                    <li>Review incoming and outgoing transfer requests for your station.</li>
                    <li>Approve or reject transfers with audit log entries maintained automatically.</li>
                </ol>

                <h3>9.3 Station Analytics</h3>
                <p>Use the <strong>Analytics</strong> module to review station-level crime statistics, case volumes, and trends.</p>

                <h3>9.4 Court Dates</h3>
                <p>Administrators can manage court date scheduling from the admin court dates module to coordinate case appearances.</p>
            </section>

            <!-- 10. System Modules -->
            <section id="modules" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-puzzle-piece text-warning me-2"></i>10. System Modules Reference</h2>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Available To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="fa-solid fa-chart-line text-primary"></i> Dashboard</td>
                                <td>Role-specific overview, statistics, and quick actions</td>
                                <td>All users</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-folder-open text-primary"></i> Cases / Dockets</td>
                                <td>Create, view, and manage case records</td>
                                <td>All users (scoped by role)</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-paperclip text-primary"></i> Evidence Files</td>
                                <td>Upload and browse case evidence</td>
                                <td>All users (scoped by role)</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-file-invoice text-primary"></i> Reports &amp; Export</td>
                                <td>Generate printable reports and PDF dockets</td>
                                <td>All users</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-chart-pie text-primary"></i> Analytics</td>
                                <td>Crime statistics and trend analysis</td>
                                <td>All users</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-users text-primary"></i> Force Directory</td>
                                <td>Police personnel directory and registration</td>
                                <td>Admin, HOD, Officer</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-bell text-primary"></i> Notifications</td>
                                <td>System alerts and case update notifications</td>
                                <td>All users</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-user-circle text-primary"></i> My Profile</td>
                                <td>View and edit personal information</td>
                                <td>All users</td>
                            </tr>
                            <tr>
                                <td><i class="fa-solid fa-gears text-primary"></i> Account Settings</td>
                                <td>Password change and theme preferences (dark mode)</td>
                                <td>All users</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 11. Emergency -->
            <section id="emergency" class="manual-section mb-5">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-phone-volume text-warning me-2"></i>11. Emergency &amp; Panic Alerts</h2>
                <div class="alert alert-danger">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> For life-threatening emergencies, always call emergency services directly in addition to using this portal.</strong>
                </div>

                <h3>11.1 Triggering a Panic Alert (Citizens)</h3>
                <ol>
                    <li>From the dashboard, click <strong>PANIC / EMERGENCY REPORT</strong>, or use the Panic button on the landing page.</li>
                    <li>Allow the browser to access your GPS location when prompted.</li>
                    <li>The system calculates proximity to police stations and routes the alert to the nearest station.</li>
                    <li>The case is created with <strong>High Priority</strong> status and flagged as a panic report.</li>
                </ol>

                <h3>11.2 If GPS Is Unavailable</h3>
                <p>If location access is denied or unavailable, you can still submit an emergency report and enter your location manually in the case form.</p>

                <h3>11.3 Desk Officer Response</h3>
                <p>Emergency cases appear with a red <strong>PANIC</strong> badge in the station queue. Desk officers should prioritize these cases and coordinate immediate dispatch response.</p>
            </section>

            <!-- 12. FAQ -->
            <section id="faq" class="manual-section mb-3">
                <h2 class="fw-bold text-primary"><i class="fa-solid fa-circle-question text-warning me-2"></i>12. FAQ &amp; Troubleshooting</h2>

                <h3>I forgot my password</h3>
                <p>Contact your station administrator (for police staff) or use <strong>Account Settings</strong> if you are already logged in. Citizens who cannot log in should contact the station desk for assistance.</p>

                <h3>I see "Access Denied"</h3>
                <p>Your role does not have permission for that module, or the case belongs to a different station. Return to the dashboard and use the modules available to your role.</p>

                <h3>Why can't the investigator add notes to my case?</h3>
                <p>The statement approval lock requires you to approve the desk officer's transcribed statement before investigation actions are enabled. Check your dashboard for pending approval alerts.</p>

                <h3>What file types can I upload as evidence?</h3>
                <p>JPG, JPEG, PNG, MP4, MOV, AVI, MP3, WAV, PDF, DOC, and DOCX files up to 20 MB each.</p>

                <h3>How do I save a docket as PDF?</h3>
                <p>Open the case, click <strong>Compile &amp; Export PDF Docket</strong>, then use your browser's Print dialog and select "Save as PDF" as the destination.</p>

                <h3>Dark mode</h3>
                <p>Click the <strong>Dark Mode</strong> button in the top navigation bar. Your preference is saved for future sessions.</p>

                <h3>Demo test accounts</h3>
                <p>For training and testing, seeded accounts are available after database setup. See the system README or ask your administrator for demo credentials.</p>
            </section>

            <hr class="my-4">
            <p class="text-muted small text-center mb-0">
                &copy; <?php echo date('Y'); ?> Royal Eswatini Police Service — Crime Administration System User Manual v1.0
            </p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tocLinks = document.querySelectorAll('.manual-toc a');
    const sections = document.querySelectorAll('.manual-section');

    function updateActiveToc() {
        let current = '';
        sections.forEach(function (section) {
            const top = section.offsetTop - 100;
            if (window.scrollY >= top) {
                current = section.getAttribute('id');
            }
        });
        tocLinks.forEach(function (link) {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', updateActiveToc);
    updateActiveToc();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
