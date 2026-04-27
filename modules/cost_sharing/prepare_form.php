<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
require_once '../../includes/academic_translations.php';

// Fetch Expense Settings
$exp_settings = [];
$exp_rows = $pdo->query("SELECT * FROM expense_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exp_rows as $row) { $exp_settings[$row['setting_key']] = $row; }
$food_val = $exp_settings['food_expense']['setting_value'] ?? 15000.00;
$bed_val = $exp_settings['bed_expense']['setting_value'] ?? 300.00;
$med_val = $exp_settings['medication_expense']['setting_value'] ?? 25.00;
$food_label = $exp_settings['food_expense']['calculation_label'] ?? '100 * 30 * 5';
$bed_label = $exp_settings['bed_expense']['calculation_label'] ?? '60 * 5';
$med_label = $exp_settings['medication_expense']['calculation_label'] ?? 'Fixed';

// Fetch departments for dropdown
$departments = $pdo->query("SELECT d.id, d.name, d.college FROM departments d ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all rates for JS
$rates = $pdo->query("SELECT * FROM courses WHERE rate_status='Submitted'")->fetchAll(PDO::FETCH_ASSOC);
$rates_json = json_encode($rates);

$msg = $_SESSION["flash_success"] ?? ""; unset($_SESSION["flash_success"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Prepare Cost Share Form - DMU" data-am="የወጪ መጋራት ቅጽ አዘጋጅ - DMU">Prepare Cost Share Form - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-form { background:#fff; padding:50px; border:1px solid #ccc; box-shadow:0 0 15px rgba(0,0,0,0.1); max-width:1000px; margin:0 auto; color:#000; font-family:'Times New Roman',serif; }
        .header-section { text-align:center; margin-bottom:40px; border-bottom:3px solid #000; padding-bottom:20px; }
        .header-section h2,.header-section h3,.header-section h4 { margin:5px 0; text-transform:uppercase; }
        .form-row { display:flex; flex-wrap:wrap; gap:20px; margin-bottom:20px; align-items:center; line-height:1.6; }
        .form-row label { font-weight:bold; white-space:nowrap; }
        .inline-input { border:none; border-bottom:1px solid #000; padding:5px; background:transparent; outline:none; min-width:100px; }
        .inline-input:focus { border-bottom:2px solid #007bff; }
        select.inline-input { background:#fff; cursor:pointer; }
        .cost-table { width:100%; border-collapse:collapse; margin-top:10px; }
        .cost-table td,.cost-table th { border:1px solid #ddd; padding:12px; }
        .cost-table td input { width:100%; border:none; background:transparent; font-weight:bold; }
        .editable-field { background:#fffde7 !important; border-bottom:2px dashed #f59e0b !important; }
        .edit-badge { display:inline-block; background:#fef3c7; color:#92400e; font-size:0.65rem; padding:1px 6px; border-radius:6px; margin-left:6px; font-weight:700; vertical-align:middle; }
        .preview-banner { background:linear-gradient(135deg,#0a0044,#3730a3); color:#fff; padding:18px 24px; border-radius:12px; margin-bottom:24px; display:flex; align-items:center; gap:14px; }
        .preview-banner i { font-size:1.5rem; }
        @media print { .no-print { display:none !important; } .paper-form { box-shadow:none; border:none; } }
    </style>
</head>
<body>
<?php if (isset($_GET['embed'])): ?>
    <div style="padding:20px; background:#f8fafc; min-height:100vh;">
        <div class="preview-banner no-print">
            <i class="fas fa-eye"></i>
            <div>
                <strong data-en="Form Preview Mode" data-am="ቅጽ ቅድመ-እይታ">Form Preview Mode</strong>
                <p style="margin:4px 0 0; font-size:0.85em; opacity:0.85;">Yellow fields are editable by students.</p>
            </div>
            <div style="margin-left:auto;">
                <button onclick="window.print()" class="btn-primary" style="background:#fff; color:#1e1b4b; padding:8px 18px; border-radius:8px; font-size:0.85em;">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
<?php else: ?>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="preview-banner no-print">
                    <i class="fas fa-eye"></i>
                    <div>
                        <strong data-en="Form Preview Mode" data-am="ቅጽ ቅድመ-እይታ">Form Preview Mode</strong>
                        <p style="margin:4px 0 0; font-size:0.85em; opacity:0.85;">Yellow fields are editable by students.</p>
                    </div>
                    <div style="margin-left:auto; display:flex; gap:10px;">
                        <button onclick="window.print()" class="btn-primary" style="background:#fff; color:#1e1b4b; padding:8px 18px; border-radius:8px; font-size:0.85em;">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <a href="manage_tuition_rates.php" class="btn-primary" style="background:rgba(255,255,255,0.15); color:#fff; padding:8px 18px; border-radius:8px; font-size:0.85em; text-decoration:none;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
<?php endif; ?>

                <div class="paper-form">
                    <div class="header-section">
                        <h3>FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA</h3>
                        <h4>MINISTRY OF EDUCATION</h4>
                        <h3>HIGHER EDUCATION COST SHARING REGULATION</h3>
                        <h4>Council of Ministers Regulation No. 154/2008</h4>
                        <h2>BENEFICIARIES AGREEMENT FORM</h2>
                    </div>

                    <!-- 1. Identity -->
                    <div class="form-row">
                        <label data-en="1. First Name:" data-am="1. ስም:">1. First Name:</label>
                        <input type="text" class="inline-input" style="flex:1; background:#eee;" value="[Auto-filled]" readonly>
                        <label data-en="Middle Name:" data-am="የአባት ስም:">Middle Name:</label>
                        <input type="text" class="inline-input" style="flex:1; background:#eee;" value="[Auto-filled]" readonly>
                        <label data-en="Last Name:" data-am="የአያት ስም:">Last Name:</label>
                        <input type="text" class="inline-input" style="flex:1; background:#eee;" value="[Auto-filled]" readonly>
                    </div>
                    <div class="form-row">
                        <label data-en="Identity No:" data-am="የመታወቂያ ቁጥር:">Identity No:</label>
                        <input type="text" class="inline-input" style="flex:2; background:#eee;" value="[Auto-filled]" readonly>
                    </div>

                    <!-- 2. Sex/Nationality -->
                    <div class="form-row">
                        <label data-en="2. Sex:" data-am="2. ጾታ:">2. Sex:</label>
                        <span class="inline-input" style="flex:1; background:#eee;">[Auto]</span>
                        <label style="margin-left:20px;" data-en="College/School:" data-am="ኮሌጅ/ትምህርት ቤት:">College/School:</label>
                        <span class="inline-input" style="flex:2; background:#eee;">[Auto]</span>
                        <label style="margin-left:20px;" data-en="Nationality:" data-am="ዜግነት:">Nationality:</label>
                        <span class="inline-input" style="flex:1;">Ethiopian</span>
                        <label style="margin-left:20px;" data-en="Status:" data-am="ሁኔታ:">Status:</label>
                        <span class="inline-input" style="flex:1; background:#eee;">[Auto]</span>
                    </div>

                    <!-- 3. Birth -->
                    <div class="form-row">
                        <label data-en="3. Date of Birth:" data-am="3. የትውልድ ዘመን:">3. Date of Birth:</label>
                        <input type="date" class="inline-input editable-field"> <span class="edit-badge">Student fills</span>
                    </div>
                    <div class="form-row">
                        <label data-en="Place of Birth:" data-am="የትውልድ ቦታ:">Place of Birth:</label>
                        <span data-en="Region:" data-am="ክልል:">Region:</span>
                        <select class="inline-input editable-field"><option>Amhara</option><option>Oromia</option><option>Addis Ababa</option><option>Tigray</option><option>SNNPR</option><option>Sidama</option><option>Somali</option><option>Afar</option><option>Gambela</option><option>Benishangul-Gumuz</option><option>Harari</option><option>Dire Dawa</option></select>
                        <span data-en="Zone:" data-am="ዞን:">Zone:</span>
                        <select class="inline-input editable-field"><option>Select Zone</option><option>East Gojjam</option><option>West Gojjam</option><option>Other</option></select>
                    </div>
                    <div class="form-row">
                        <span data-en="Woreda:" data-am="ወረዳ:">Woreda:</span>
                        <select class="inline-input editable-field"><option>Select Woreda</option><option>Debre Markos</option><option>Other</option></select>
                        <span data-en="Town:" data-am="ከተማ:">Town:</span>
                        <select class="inline-input editable-field"><option>Select Town</option><option>Debre Markos</option><option>Other</option></select>
                    </div>
                    <div class="form-row">
                        <span data-en="Kebele" data-am="ቀበሌ">Kebele</span> <input type="text" class="inline-input editable-field" size="5">
                        <span data-en="House No" data-am="የቤት ቁጥር">House No</span> <input type="text" class="inline-input editable-field" size="5">
                        <span data-en="Phone" data-am="ስልክ ቁጥር">Phone</span> <input type="text" class="inline-input editable-field" size="12"> <span class="edit-badge">Always manual</span>
                        <span data-en="P.O.Box" data-am="ፖ.ሳ.ቁ">P.O.Box</span> <input type="text" class="inline-input editable-field" size="6">
                    </div>

                    <!-- 4. Mother -->
                    <div class="form-row"><label data-en="4. Mother's/Adopter's Name" data-am="4. የወላጅ/አሳዳጊ እናት ሙሉ ስም">4. Mother's/Adopter's Name</label> <span class="edit-badge">Student fills</span></div>
                    <div class="form-row" style="padding-left:20px;">
                        <span data-en="First Name:" data-am="ስም:">First Name:</span> <input type="text" class="inline-input editable-field" style="flex:1;">
                        <span data-en="Father Name:" data-am="የአባት ስም:">Father Name:</span> <input type="text" class="inline-input editable-field" style="flex:1;">
                        <span data-en="G.Father Name:" data-am="የአያት ስም:">G.Father Name:</span> <input type="text" class="inline-input editable-field" style="flex:1;">
                    </div>
                    <div class="form-row">
                        <label data-en="Mother's Address:" data-am="የእናት አድራሻ:">Mother's Address:</label>
                        <span>Region</span> <select class="inline-input editable-field"><option>Amhara</option><option>Oromia</option><option>Other</option></select>
                        <span>Zone</span> <select class="inline-input editable-field"><option>Select Zone</option><option>East Gojjam</option><option>Other</option></select>
                        <span>Woreda</span> <select class="inline-input editable-field"><option>Select</option><option>Debre Markos</option><option>Other</option></select>
                        <span>Town</span> <select class="inline-input editable-field"><option>Select</option><option>Debre Markos</option><option>Other</option></select>
                    </div>

                    <!-- 5. School -->
                    <div class="form-row">
                        <label data-en="5. School Name (Preparatory):" data-am="5. የመሰናዶ ትምህርት ቤት ስም:">5. School Name (Preparatory):</label>
                        <input type="text" class="inline-input editable-field" style="flex:1;"> <span class="edit-badge">Student fills</span>
                        <span data-en="Date Completed:" data-am="የተጠናቀቀበት ቀን:">Date Completed:</span>
                        <input type="date" class="inline-input editable-field">
                    </div>

                    <!-- 6. University -->
                    <div class="form-row">
                        <label data-en="6. University/ College/ Institute:" data-am="6. ዩኒቨርሲቲ/ ኮሌጅ/ ኢንስቲትዩት:">6. University/ College/ Institute:</label>
                        <span class="inline-input" style="flex:1; font-weight:bold; background:#eee;">Debre Markos University</span>
                    </div>
                    <div class="form-row">
                        <label data-en="Department:" data-am="የትምህርት ክፍል:">Department:</label>
                        <select id="prevDept" class="inline-input" style="flex:1; background:#eee;" onchange="previewCalc()">
                            <?php foreach($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label data-en="Year:" data-am="ዓመት:">Year:</label>
                        <input type="number" id="prevYear" class="inline-input" style="width:60px; text-align:center;" value="1" min="1" max="7" onchange="previewCalc()">
                        <label data-en="Semester:" data-am="ሴሚስተር:">Semester:</label>
                        <input type="number" id="prevSem" class="inline-input" style="width:60px; text-align:center;" value="1" min="1" max="2" onchange="previewCalc()">
                    </div>

                    <!-- 7. Withdrawal -->
                    <div class="form-row">
                        <label data-en="7. If Withdrawal (Indicate Date):" data-am="7. አቋርጦ ከሆነ (ቀን ይግለጹ):">7. If Withdrawal (Indicate Date):</label>
                        <input type="date" class="inline-input editable-field">
                    </div>

                    <!-- 8. Transfer -->
                    <div class="form-row">
                        <label data-en="8. If Transferred (From):" data-am="8. ዝውውር ከሆነ (ከየት):">8. If Transferred (From):</label>
                        <input type="text" class="inline-input editable-field" style="flex:1;">
                        <span data-en="Cost Used:" data-am="ጥቅም ላይ የዋለ ወጪ:">Cost Used:</span>
                        <input type="text" class="inline-input editable-field" size="10">
                    </div>

                    <!-- 9. Services -->
                    <div class="form-row"><label data-en="9. Services Demanded:" data-am="9. የሚጠየቁ አገልግሎቶች:">9. Services Demanded:</label> <span class="edit-badge">Required</span></div>
                    <div class="form-row" style="justify-content:space-around;">
                        <div>
                            <span data-en="A. In-kind:" data-am="ሀ. በዓይነት:">A. In-kind:</span>
                            <label><input type="checkbox" value="Food"> <span data-en="Food" data-am="ምግብ">Food</span></label>
                            <label><input type="checkbox" value="Boarding"> <span data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                        </div>
                        <div>
                            <span data-en="B. In Cash:" data-am="ለ. በገንዘብ:">B. In Cash:</span>
                            <label><input type="checkbox" value="Food"> <span data-en="Food" data-am="ምግብ">Food</span></label>
                            <label><input type="checkbox" value="Boarding"> <span data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                        </div>
                    </div>

                    <!-- 10. Remedial -->
                    <div class="form-row">
                        <label data-en="10. Remedial Year (if applicable):" data-am="10. የማካካሻ ዓመት (የሚመለከተው ከሆነ):">10. Remedial Year (if applicable):</label>
                        <input type="text" class="inline-input editable-field">
                    </div>

                    <!-- 11. Cost Estimate -->
                    <hr style="border-top:2px solid #000; margin:30px 0;">
                    <h4 data-en="11. Estimate Cost (Current Academic Year)" data-am="11. የተገመተ ወጪ (የአሁኑ የትምህርት ዘመን)">11. Estimate Cost (Current Academic Year)</h4>
                    <table class="cost-table">
                        <tr style="background:#f0f0f0;">
                            <th data-en="Service Type" data-am="የአገልግሎት ዓይነት">Service Type</th>
                            <th data-en="Calculation" data-am="ስሌት">Calculation</th>
                            <th data-en="Amount (Birr)" data-am="መጠን (ብር)">Amount (Birr)</th>
                        </tr>
                        <tr>
                            <td data-en="15% Tuition Fee" data-am="15% የትምህርት ክፍያ">15% Tuition Fee</td>
                            <td id="prev_tuition_calc">Select department above</td>
                            <td><input type="text" id="prev_tuition_fee" readonly></td>
                        </tr>
                        <tr>
                            <td data-en="Food Expense" data-am="የምግብ ወጪ">Food Expense</td>
                            <td><?php echo htmlspecialchars($food_label); ?></td>
                            <td><input type="text" value="<?php echo number_format($food_val, 2); ?>" readonly></td>
                        </tr>
                        <tr>
                            <td data-en="Bed Expense" data-am="የመኝታ ወጪ">Bed Expense</td>
                            <td><?php echo htmlspecialchars($bed_label); ?></td>
                            <td><input type="text" value="<?php echo number_format($bed_val, 2); ?>" readonly></td>
                        </tr>
                        <tr>
                            <td data-en="Medication Expense" data-am="የሕክምና ወጪ">Medication Expense</td>
                            <td><?php echo htmlspecialchars($med_label); ?></td>
                            <td><input type="text" value="<?php echo number_format($med_val, 2); ?>" readonly></td>
                        </tr>
                        <tr style="background:#f8f8f8; font-weight:bold;">
                            <td colspan="2" data-en="TOTAL" data-am="ጠቅላላ">TOTAL</td>
                            <td><input type="text" id="prev_total" readonly style="font-weight:bold; font-size:1.1em;"></td>
                        </tr>
                    </table>

                    <!-- 12. Agreement -->
                    <hr style="border-top:2px solid #000; margin:30px 0;">
                    <div style="background:#fafafa; padding:20px; border-radius:8px; border:1px solid #eee;">
                        <h4 data-en="12. Agreement & Signature" data-am="12. ስምምነት እና ፊርማ">12. Agreement & Signature</h4>
                        <p style="font-size:0.95em; line-height:1.8;" data-en="I, [Student Name], in accordance with this contractual agreement and the higher education Proclamation No 351/1995 and the higher education cost sharing Regulation 154/2008 of the council of ministers, agree to pay the above cost after graduation." data-am="እኔ [የተማሪ ስም]፣ ከዚህ ውል እና ከከፍተኛ ትምህርት አዋጅ ቁጥር 351/1995 እና የሚኒስትሮች ምክር ቤት ደንብ 154/2008 መሰረት ከላይ የተገለጸውን ወጪ ከተመረቅሁ በኋላ ለመክፈል እስማማለሁ።">
                            I, <strong>[Student Name]</strong>, in accordance with this contractual agreement and the higher education Proclamation No 351/1995 and the higher education cost sharing Regulation 154/2008 of the council of ministers, agree to pay the above cost <strong>after graduation</strong>.
                        </p>
                        <div class="form-row" style="margin-top:15px;">
                            <label><input type="radio" name="payment_preview" value="Income" checked> <span data-en="A. To be paid from my income(Deduction)" data-am="ሀ. ከገቢዬ ተቀናሽ ሆኖ እንዲከፈል">A. To be paid from my income(Deduction)</span></label>
                        </div>
                        <div class="form-row" style="margin-top:15px;">
                            <label style="font-size:1.1em; font-weight:bold; color:#d9534f;">
                                <input type="checkbox" checked disabled style="transform:scale(1.5); margin-right:10px;">
                                <span data-en="Beneficiary's Signature (I have read and agreed to the above terms)" data-am="የተጠቃሚው ፊርማ (ከላይ የተገለጹትን ሁኔታዎች አንብቤ ተስማምቻለሁ)">Beneficiary's Signature (I have read and agreed)</span>
                            </label>
                            <div style="margin-left:auto;">
                                <label data-en="Date:" data-am="ቀን:">Date:</label>
                                <input type="text" value="<?php echo date('d/m/Y'); ?>" readonly class="inline-input">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php if (isset($_GET['embed'])): ?>
    </div>
<?php else: ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
<?php endif; ?>

    <script>
        const rates = <?php echo $rates_json; ?>;
        const foodVal = <?php echo $food_val; ?>;
        const bedVal = <?php echo $bed_val; ?>;
        const medVal = <?php echo $med_val; ?>;

        function previewCalc() {
            const deptId = document.getElementById('prevDept').value;
            const year = document.getElementById('prevYear').value;
            const sem = document.getElementById('prevSem').value;
            const calcEl = document.getElementById('prev_tuition_calc');
            const feeEl = document.getElementById('prev_tuition_fee');
            const totalEl = document.getElementById('prev_total');

            let rateObj = rates.find(r => r.department_id == deptId && r.batch == year && r.semester == sem);
            if (rateObj) {
                const cr = parseFloat(rateObj.credit_hours || rateObj.credit_hour || 0);
                const cost = parseFloat(rateObj.cost_per_credit_hour || 0);
                const tuition = cr * cost;
                calcEl.textContent = cr + ' Cr.Hrs × ' + cost.toFixed(2) + ' Birr';
                feeEl.value = tuition.toFixed(2);
                totalEl.value = (tuition + foodVal + bedVal + medVal).toFixed(2);
            } else {
                calcEl.textContent = 'No rate found for this combination';
                feeEl.value = '0.00';
                totalEl.value = (foodVal + bedVal + medVal).toFixed(2);
            }
        }
        window.onload = previewCalc;
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>
</html>
