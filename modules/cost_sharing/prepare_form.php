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

$departments = $pdo->query("SELECT d.id, d.name, d.college FROM departments d ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
$rates = $pdo->query("SELECT * FROM courses WHERE rate_status='Submitted'")->fetchAll(PDO::FETCH_ASSOC);
$rates_json = json_encode($rates);

$is_embed = isset($_GET['embed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prepare Cost Share Form - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-form{background:#fff;padding:40px 50px;border:1px solid #ccc;box-shadow:0 0 15px rgba(0,0,0,.1);max-width:1000px;margin:0 auto;color:#000;font-family:'Times New Roman',serif}
        .header-section{text-align:center;margin-bottom:30px;border-bottom:3px solid #000;padding-bottom:15px}
        .header-section h2,.header-section h3,.header-section h4{margin:5px 0;text-transform:uppercase}
        .form-row{display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;align-items:center;line-height:1.6}
        .form-row label{font-weight:bold;white-space:nowrap}
        .inline-input{border:none;border-bottom:1px solid #000;padding:5px;background:transparent;outline:none;min-width:80px}
        .inline-input:focus{border-bottom:2px solid #007bff}
        select.inline-input{background:#fff;cursor:pointer}
        .cost-table{width:100%;border-collapse:collapse;margin-top:10px}
        .cost-table td,.cost-table th{border:1px solid #ddd;padding:10px}
        .cost-table td input{width:100%;border:none;background:transparent;font-weight:bold}
        .editable-field{background:#fffde7!important;border-bottom:2px dashed #f59e0b!important}
        .edit-badge{display:inline-block;background:#fef3c7;color:#92400e;font-size:.6rem;padding:1px 5px;border-radius:5px;margin-left:4px;font-weight:700;vertical-align:middle}
        .top-bar{background:linear-gradient(135deg,#0a0044,#3730a3);color:#fff;padding:14px 24px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100}
        .top-bar .btn{padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:.85em;font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:.2s}
        .btn-back{background:rgba(255,255,255,.15);color:#fff}.btn-back:hover{background:rgba(255,255,255,.3)}
        .btn-print{background:#fff;color:#1e1b4b}.btn-print:hover{background:#e0e7ff}
        .btn-save{background:#22c55e;color:#fff}.btn-save:hover{background:#16a34a}
        @media print{.top-bar,.no-print{display:none!important}.paper-form{box-shadow:none;border:none}}
    </style>
</head>
<body style="<?php echo $is_embed ? 'margin:0;background:#f1f5f9' : ''; ?>">

<?php if (!$is_embed): ?>
<div class="dashboard-container">
    <?php include '../../includes/main_header.php'; ?>
    <div class="layout-body">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
<?php endif; ?>

<!-- Top Action Bar -->
<div class="top-bar no-print">
    <i class="fas fa-eye" style="font-size:1.3rem"></i>
    <div style="flex:1">
        <strong>Form Preview Mode</strong>
        <p style="margin:2px 0 0;font-size:.8em;opacity:.8">Editable preview — modify fields, then Save or Print.</p>
    </div>
    <?php if ($is_embed): ?>
    <button class="btn btn-back" onclick="window.parent.closeFormPreview()"><i class="fas fa-arrow-left"></i> Back</button>
    <?php else: ?>
    <a href="manage_tuition_rates.php" class="btn btn-back" style="text-decoration:none"><i class="fas fa-arrow-left"></i> Back</a>
    <?php endif; ?>
    <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    <button class="btn btn-save" onclick="alert('Form template saved!')"><i class="fas fa-save"></i> Save</button>
</div>

<div style="padding:20px">
    <div class="paper-form">
        <div class="header-section">
            <h3 contenteditable="true">FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA</h3>
            <h4 contenteditable="true">MINISTRY OF EDUCATION</h4>
            <h3 contenteditable="true">HIGHER EDUCATION COST SHARING REGULATION</h3>
            <h4 contenteditable="true">Council of Ministers Regulation No. 154/2008</h4>
            <h2 contenteditable="true">BENEFICIARIES AGREEMENT FORM</h2>
        </div>

        <!-- 1. Identity -->
        <div class="form-row">
            <label contenteditable="true">1. First Name:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" placeholder="[Auto-filled from DB]">
            <label contenteditable="true">Middle Name:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" placeholder="[Auto-filled]">
            <label contenteditable="true">Last Name:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" placeholder="[Auto-filled]">
        </div>
        <div class="form-row">
            <label contenteditable="true">Identity No:</label>
            <input type="text" class="inline-input editable-field" style="flex:2" placeholder="[Student ID]">
        </div>

        <!-- 2. Sex/Nationality -->
        <div class="form-row">
            <label contenteditable="true">2. Sex:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" placeholder="[Auto]">
            <label contenteditable="true" style="margin-left:15px">College/School:</label>
            <input type="text" class="inline-input editable-field" style="flex:2" placeholder="[Auto]">
            <label contenteditable="true" style="margin-left:15px">Nationality:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" value="Ethiopian">
            <label contenteditable="true" style="margin-left:15px">Status:</label>
            <input type="text" class="inline-input editable-field" style="flex:1" placeholder="[Auto]">
        </div>

        <!-- 3. Birth -->
        <div class="form-row">
            <label contenteditable="true">3. Date of Birth:</label>
            <input type="date" class="inline-input editable-field"> <span class="edit-badge">Student fills</span>
        </div>
        <div class="form-row">
            <label contenteditable="true">Place of Birth:</label>
            <span contenteditable="true">Region:</span>
            <select class="inline-input editable-field">
                <option>Amhara</option><option>Oromia</option><option>Addis Ababa</option><option>Tigray</option>
                <option>SNNPR</option><option>Sidama</option><option>Somali</option><option>Afar</option>
                <option>Gambela</option><option>Benishangul-Gumuz</option><option>Harari</option><option>Dire Dawa</option>
            </select>
            <span contenteditable="true">Zone:</span>
            <select class="inline-input editable-field">
                <option>Select Zone</option><option>East Gojjam</option><option>West Gojjam</option><option>Awi</option>
                <option>Bahir Dar</option><option>North Gondar</option><option>South Gondar</option><option>Other</option>
            </select>
        </div>
        <div class="form-row">
            <span contenteditable="true">Woreda:</span>
            <select class="inline-input editable-field">
                <option>Select Woreda</option><option>Debre Markos</option><option>Gozamin</option><option>Other</option>
            </select>
            <span contenteditable="true">Town:</span>
            <select class="inline-input editable-field">
                <option>Select Town</option><option>Debre Markos</option><option>Bahir Dar</option><option>Other</option>
            </select>
        </div>
        <div class="form-row">
            <span contenteditable="true">Kebele</span> <input type="text" class="inline-input editable-field" size="5">
            <span contenteditable="true">House No</span> <input type="text" class="inline-input editable-field" size="5">
            <span contenteditable="true">Phone</span> <input type="text" class="inline-input editable-field" size="12"> <span class="edit-badge">Always manual</span>
            <span contenteditable="true">P.O.Box</span> <input type="text" class="inline-input editable-field" size="6">
        </div>

        <!-- 4. Mother -->
        <div class="form-row">
            <label contenteditable="true">4. Mother's/Adopter's Name</label> <span class="edit-badge">Student fills</span>
        </div>
        <div class="form-row" style="padding-left:20px">
            <span contenteditable="true">First Name:</span> <input type="text" class="inline-input editable-field" style="flex:1">
            <span contenteditable="true">Father Name:</span> <input type="text" class="inline-input editable-field" style="flex:1">
            <span contenteditable="true">G.Father Name:</span> <input type="text" class="inline-input editable-field" style="flex:1">
        </div>
        <div class="form-row">
            <label contenteditable="true">Mother's Address:</label>
            <span contenteditable="true">Region</span>
            <select class="inline-input editable-field"><option>Amhara</option><option>Oromia</option><option>Other</option></select>
            <span contenteditable="true">Zone</span>
            <select class="inline-input editable-field"><option>Select Zone</option><option>East Gojjam</option><option>Other</option></select>
            <span contenteditable="true">Woreda</span>
            <select class="inline-input editable-field"><option>Select</option><option>Debre Markos</option><option>Other</option></select>
            <span contenteditable="true">Town</span>
            <select class="inline-input editable-field"><option>Select</option><option>Debre Markos</option><option>Other</option></select>
        </div>

        <!-- 5. School -->
        <div class="form-row">
            <label contenteditable="true">5. School Name (Preparatory):</label>
            <input type="text" class="inline-input editable-field" style="flex:1"> <span class="edit-badge">Student fills</span>
            <span contenteditable="true">Date Completed:</span>
            <input type="date" class="inline-input editable-field">
        </div>

        <!-- 6. University -->
        <div class="form-row">
            <label contenteditable="true">6. University/ College/ Institute:</label>
            <input type="text" class="inline-input editable-field" style="flex:1;font-weight:bold" value="Debre Markos University">
        </div>
        <div class="form-row">
            <label contenteditable="true">Department:</label>
            <select id="prevDept" class="inline-input editable-field" style="flex:1" onchange="previewCalc()">
                <?php foreach($departments as $d): ?>
                <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label contenteditable="true">Year:</label>
            <input type="number" id="prevYear" class="inline-input editable-field" style="width:55px;text-align:center" value="1" min="1" max="7" onchange="previewCalc()">
            <label contenteditable="true">Semester:</label>
            <input type="number" id="prevSem" class="inline-input editable-field" style="width:55px;text-align:center" value="1" min="1" max="2" onchange="previewCalc()">
        </div>

        <!-- 7. Withdrawal -->
        <div class="form-row">
            <label contenteditable="true">7. If Withdrawal (Indicate Date):</label>
            <input type="date" class="inline-input editable-field">
        </div>

        <!-- 8. Transfer -->
        <div class="form-row">
            <label contenteditable="true">8. If Transferred (From):</label>
            <input type="text" class="inline-input editable-field" style="flex:1">
            <span contenteditable="true">Cost Used:</span>
            <input type="text" class="inline-input editable-field" size="10">
        </div>

        <!-- 9. Services -->
        <div class="form-row">
            <label contenteditable="true">9. Services Demanded:</label> <span class="edit-badge">Required</span>
        </div>
        <div class="form-row" style="justify-content:space-around">
            <div>
                <span contenteditable="true">A. In-kind:</span>
                <label><input type="checkbox" value="Food"> <span contenteditable="true">Food</span></label>
                <label><input type="checkbox" value="Boarding"> <span contenteditable="true">Boarding</span></label>
            </div>
            <div>
                <span contenteditable="true">B. In Cash:</span>
                <label><input type="checkbox" value="Food"> <span contenteditable="true">Food</span></label>
                <label><input type="checkbox" value="Boarding"> <span contenteditable="true">Boarding</span></label>
            </div>
        </div>

        <!-- 10. Remedial -->
        <div class="form-row">
            <label contenteditable="true">10. Remedial Year (if applicable):</label>
            <input type="text" class="inline-input editable-field">
        </div>

        <!-- 11. Cost Estimate -->
        <hr style="border-top:2px solid #000;margin:25px 0">
        <h4 contenteditable="true">11. Estimate Cost (Current Academic Year)</h4>
        <table class="cost-table">
            <tr style="background:#f0f0f0">
                <th contenteditable="true">Service Type</th>
                <th contenteditable="true">Calculation</th>
                <th contenteditable="true">Amount (Birr)</th>
            </tr>
            <tr>
                <td contenteditable="true">15% Tuition Fee</td>
                <td id="prev_tuition_calc">Select department above</td>
                <td><input type="text" id="prev_tuition_fee" readonly></td>
            </tr>
            <tr>
                <td contenteditable="true">Food Expense</td>
                <td contenteditable="true"><?php echo htmlspecialchars($food_label); ?></td>
                <td><input type="text" value="<?php echo number_format($food_val, 2); ?>" readonly></td>
            </tr>
            <tr>
                <td contenteditable="true">Bed Expense</td>
                <td contenteditable="true"><?php echo htmlspecialchars($bed_label); ?></td>
                <td><input type="text" value="<?php echo number_format($bed_val, 2); ?>" readonly></td>
            </tr>
            <tr>
                <td contenteditable="true">Medication Expense</td>
                <td contenteditable="true"><?php echo htmlspecialchars($med_label); ?></td>
                <td><input type="text" value="<?php echo number_format($med_val, 2); ?>" readonly></td>
            </tr>
            <tr style="background:#f8f8f8;font-weight:bold">
                <td colspan="2" contenteditable="true">TOTAL</td>
                <td><input type="text" id="prev_total" readonly style="font-weight:bold;font-size:1.1em"></td>
            </tr>
        </table>

        <!-- 12. Agreement -->
        <hr style="border-top:2px solid #000;margin:25px 0">
        <div style="background:#fafafa;padding:18px;border-radius:8px;border:1px solid #eee">
            <h4 contenteditable="true">12. Agreement & Signature</h4>
            <p contenteditable="true" style="font-size:.95em;line-height:1.8">
                I, <strong>[Student Name]</strong>, in accordance with this contractual agreement and the higher education Proclamation No 351/1995 and the higher education cost sharing Regulation 154/2008 of the council of ministers, agree to pay the above cost <strong>after graduation</strong>.
            </p>
            <div class="form-row" style="margin-top:12px">
                <label><input type="radio" name="pm" value="Income" checked> <span contenteditable="true">A. To be paid from my income(Deduction)</span></label>
            </div>
            <div class="form-row" style="margin-top:12px">
                <label style="font-size:1.05em;font-weight:bold;color:#d9534f">
                    <input type="checkbox" checked disabled style="transform:scale(1.4);margin-right:8px">
                    <span contenteditable="true">Beneficiary's Signature (I have read and agreed to the above terms)</span>
                </label>
                <div style="margin-left:auto">
                    <label>Date:</label>
                    <input type="text" value="<?php echo date('d/m/Y'); ?>" readonly class="inline-input">
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$is_embed): ?>
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
            calcEl.textContent = 'No rate found';
            feeEl.value = '0.00';
            totalEl.value = (foodVal + bedVal + medVal).toFixed(2);
        }
    }
    window.onload = previewCalc;
</script>
<script src="../../assets/js/bilingual.js"></script>
</body>
</html>
