<?php
require_once __DIR__ . "/../config/language.php";
include("../config/database.php");

$services = mysqli_query($conn,
    "SELECT service_id, service_name FROM services WHERE status='Active' AND LOWER(TRIM(service_name)) <> 'atm service' ORDER BY service_name ASC"
);

$service_fields_map = [];
$sf_result = mysqli_query($conn, "SELECT service_id, field_key FROM service_fields ORDER BY service_id, field_id");
if($sf_result){
    while($sf = mysqli_fetch_assoc($sf_result)){
        $service_fields_map[(int)$sf['service_id']][] = $sf['field_key'];
    }
}

$active_branch = null;
$branch_result = mysqli_query($conn,
    "SELECT branch_id, branch_name FROM branches WHERE status='Active' ORDER BY branch_id ASC LIMIT 1"
);
if ($branch_result && mysqli_num_rows($branch_result) > 0) {
    $active_branch = mysqli_fetch_assoc($branch_result);
}

// Use the first active priority as the system default so customers only enter
// information that is relevant to the selected service.
$default_priority_id = 0;
$priority_result = mysqli_query($conn,
    "SELECT priority_id FROM priority_categories WHERE status='Active' ORDER BY priority_level ASC, priority_id ASC LIMIT 1"
);
if ($priority_result && ($priority_row = mysqli_fetch_assoc($priority_result))) {
    $default_priority_id = (int)$priority_row['priority_id'];
}
?>

<?php
$register_ui = [
    'en' => [
        'title'=>'Customer Registration','subtitle'=>'Select your service and provide only the information required for that service.',
        'priority'=>'Customer Priority','priority_hint'=>'Select the priority that applies to you. Please choose accurately.',
        'service_question'=>'What service do you need?','select_service'=>'Select a service','service_hint'=>'The form will automatically show only the information needed for your selected service.',
        'choose_first'=>'Choose a service first','required_here'=>'Your required fields will appear here.','no_fields'=>'No customer fields configured','ask_admin'=>'Please ask the Admin to configure the required fields for this service.',
        'service_information'=>'SERVICE INFORMATION','required'=>'* Required','branch'=>'Branch','no_branch'=>'No active branch','no_branch_alert'=>'No active branch is available. Please ask the Admin to activate a branch.','submit'=>'Submit Registration','privacy'=>'Your information is used only to process the selected banking service.',
        'full_name'=>'Full Name','full_name_ph'=>'Enter your full name','full_name_title'=>'Enter at least two names separated by a space.','phone'=>'Phone Number','phone_ph'=>'09XXXXXXXX','phone_title'=>'Phone Number must be exactly 10 digits.','fan'=>'FAN','fan_ph'=>'Enter FAN','fan_title'=>'Enter a valid FAN.','account'=>'Account Number','account_ph'=>'13-digit account number','account_title'=>'Account Number must be exactly 13 digits.','amount'=>'Amount','amount_ph'=>'Enter amount'
    ],
    'om' => [
        'title'=>'Galmee Maamilaa','subtitle'=>'Tajaajila barbaaddan filadhaa; odeeffannoo tajaajilichaaf barbaachisu qofa guutaa.',
        'priority'=>'Dursa Maamilaa','priority_hint'=>'Dursa isin ilaallatu sirriitti filadhaa.',
        'service_question'=>'Tajaajila maalii barbaaddu?','select_service'=>'Tajaajila filadhu','service_hint'=>'Tajaajila filattan irratti hundaa’uun odeeffannoon barbaachisu asitti ni mul’ata.',
        'choose_first'=>'Jalqaba tajaajila filadhu','required_here'=>'Fieldwwan isin guutuu qabdan asitti ni mul’atu.','no_fields'=>'Odeeffannoon maamilaa hin qindaa’in','ask_admin'=>'Mee Adminiin fieldwwan barbaachisan tajaajila kanaaf akka qopheessu gaafadhaa.',
        'service_information'=>'ODEEFFANNOO TAJAAJILAA','required'=>'* Dirqama','branch'=>'Damee','no_branch'=>'Dameen hojii irra jiru hin jiru','no_branch_alert'=>'Dameen hojii irra jiru hin jiru. Mee Adminiin damee hojii irra akka oolchu gaafadhaa.','submit'=>'Galmee Ergi','privacy'=>'Odeeffannoon keessan tajaajila baankii filattan qofaaf ni fayyada.',
        'full_name'=>'Maqaa Guutuu','full_name_ph'=>'Maqaa guutuu keessan galchaa','full_name_title'=>'Maqaa yoo xiqqaate lama addaan baasanii galchaa.','phone'=>'Lakkoofsa Bilbilaa','phone_ph'=>'09XXXXXXXX','phone_title'=>'Lakkoofsi bilbilaa lakkoofsa 10 ta’uu qaba.','fan'=>'FAN','fan_ph'=>'FAN galchaa','fan_title'=>'FAN sirrii galchaa.','account'=>'Lakkoofsa Herregaa','account_ph'=>'Lakkoofsa herregaa 13','account_title'=>'Lakkoofsi herregaa lakkoofsa 13 ta’uu qaba.','amount'=>'Hanga Maallaqaa','amount_ph'=>'Hanga maallaqaa galchaa'
    ],
    'am' => [
        'title'=>'የደንበኛ ምዝገባ','subtitle'=>'አገልግሎትዎን ይምረጡ እና ለዚያ አገልግሎት የሚያስፈልገውን መረጃ ብቻ ያስገቡ።',
        'priority'=>'የደንበኛ ቅድሚያ','priority_hint'=>'የሚመለከትዎትን ቅድሚያ በትክክል ይምረጡ።',
        'service_question'=>'ምን አገልግሎት ይፈልጋሉ?','select_service'=>'አገልግሎት ይምረጡ','service_hint'=>'በመረጡት አገልግሎት መሰረት የሚያስፈልገው መረጃ በራስ-ሰር እዚህ ይታያል።',
        'choose_first'=>'መጀመሪያ አገልግሎት ይምረጡ','required_here'=>'የሚያስፈልጉ መስኮች እዚህ ይታያሉ።','no_fields'=>'የደንበኛ መረጃ አልተዘጋጀም','ask_admin'=>'እባክዎ Admin ለዚህ አገልግሎት አስፈላጊ መስኮችን እንዲያዘጋጅ ይጠይቁ።',
        'service_information'=>'የአገልግሎት መረጃ','required'=>'* አስፈላጊ','branch'=>'ቅርንጫፍ','no_branch'=>'ንቁ ቅርንጫፍ የለም','no_branch_alert'=>'ንቁ ቅርንጫፍ የለም። እባክዎ Admin ቅርንጫፉን እንዲያነቃ ይጠይቁ።','submit'=>'ምዝገባ አስገባ','privacy'=>'መረጃዎ የተመረጠውን የባንክ አገልግሎት ለማስኬድ ብቻ ይጠቅማል።',
        'full_name'=>'ሙሉ ስም','full_name_ph'=>'ሙሉ ስምዎን ያስገቡ','full_name_title'=>'ቢያንስ ሁለት ስሞችን በክፍተት ያስገቡ።','phone'=>'የስልክ ቁጥር','phone_ph'=>'09XXXXXXXX','phone_title'=>'የስልክ ቁጥሩ 10 አሃዝ መሆን አለበት።','fan'=>'FAN','fan_ph'=>'FAN ያስገቡ','fan_title'=>'ትክክለኛ FAN ያስገቡ።','account'=>'የሂሳብ ቁጥር','account_ph'=>'13 አሃዝ የሂሳብ ቁጥር','account_title'=>'የሂሳብ ቁጥሩ 13 አሃዝ መሆን አለበት።','amount'=>'የገንዘብ መጠን','amount_ph'=>'የገንዘብ መጠን ያስገቡ'
    ]
];
$ui = $register_ui[$current_language] ?? $register_ui['en'];
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($ui['title']); ?></title>
    <link rel="stylesheet" href="css/register.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="registration-shell">
    <div class="registration-card">
        <div class="registration-header">
            <div class="header-icon">🏦</div>
            <div>
                <h1><?php echo htmlspecialchars($ui['title']); ?></h1>
                <p><?php echo htmlspecialchars($ui['subtitle']); ?></p>
            </div>
        </div>

        <form method="POST" action="register_process.php" id="customerRegistrationForm" novalidate>
            <input type="hidden" name="branch" value="<?php echo htmlspecialchars($active_branch['branch_name'] ?? ''); ?>">
            <div class="priority-panel">
                <label for="priority_id" class="form-label"><?php echo htmlspecialchars($ui['priority']); ?> <span>*</span></label>
                <select name="priority_id" id="priority_id" class="form-select" required>
                    <?php
                    $priority_list = mysqli_query($conn, "SELECT priority_id, priority_name FROM priority_categories WHERE status='Active' ORDER BY priority_level ASC, priority_id ASC");
                    if ($priority_list && mysqli_num_rows($priority_list) > 0):
                        while ($priority = mysqli_fetch_assoc($priority_list)):
                    ?>
                        <option value="<?php echo (int)$priority['priority_id']; ?>" <?php echo ((int)$priority['priority_id'] === $default_priority_id ? 'selected' : ''); ?>><?php echo htmlspecialchars($priority['priority_name']); ?></option>
                    <?php endwhile; else: ?>
                        <option value="<?php echo $default_priority_id; ?>">Normal</option>
                    <?php endif; ?>
                </select>
                <div class="service-hint"><?php echo htmlspecialchars($ui['priority_hint']); ?></div>
            </div>

            <div class="service-panel">
                <label for="service_id" class="form-label"><?php echo htmlspecialchars($ui['service_question']); ?> <span>*</span></label>
                <div class="service-select-wrap">
                    <span class="select-icon">▣</span>
                    <select name="service_id" id="service_id" class="form-select service-select" required>
                        <option value=""><?php echo htmlspecialchars($ui['select_service']); ?></option>
                        <?php if ($services && mysqli_num_rows($services) > 0): ?>
                            <?php while ($service = mysqli_fetch_assoc($services)): ?>
                                <option value="<?php echo (int)$service['service_id']; ?>"
                                    data-service-name="<?php echo htmlspecialchars($service['service_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-service-name-localized="<?php echo htmlspecialchars(translate_service_text($service['service_name']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-fields-b64="<?php
                                        $safe_fields = $service_fields_map[(int)$service['service_id']] ?? ['full_name','phone'];
                                        $fields_json = json_encode(array_values($safe_fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                        echo htmlspecialchars(base64_encode($fields_json ?: '["full_name","phone"]'), ENT_QUOTES, 'UTF-8');
                                    ?>">
                                    <?php echo htmlspecialchars(translate_service_text($service['service_name'])); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No active service available</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="service-hint"><?php echo htmlspecialchars($ui['service_hint']); ?></div>
            </div>

            <div id="serviceForm" class="fields-panel is-empty">
                <div class="empty-state">
                    <div class="empty-icon">☝</div>
                    <h3><?php echo htmlspecialchars($ui['choose_first']); ?></h3>
                    <p><?php echo htmlspecialchars($ui['required_here']); ?></p>
                </div>
            </div>

            <div class="branch-note">
                <span class="branch-dot"></span>
                <div>
                    <strong><?php echo htmlspecialchars($ui['branch']); ?></strong>
                    <small><?php echo htmlspecialchars($active_branch['branch_name'] ?? $ui['no_branch']); ?></small>
                </div>
            </div>
            <?php if (!$active_branch): ?>
                <div class="alert alert-warning mt-3 mb-0"><?php echo htmlspecialchars($ui['no_branch_alert']); ?></div>
            <?php endif; ?>

            <button type="submit" name="register" class="submit-btn" id="submitBtn" disabled>
                <span>✓</span> <?php echo htmlspecialchars($ui['submit']); ?>
            </button>

            <div class="privacy-note"><?php echo htmlspecialchars($ui['privacy']); ?></div>
        </form>
    </div>
</div>

<script>
const serviceSelect = document.getElementById('service_id');
const serviceForm = document.getElementById('serviceForm');
const submitBtn = document.getElementById('submitBtn');

const ui = <?php echo json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const fieldCatalog = {
    full_name: { label:ui.full_name, type:'text', placeholder:ui.full_name_ph, name:'full_name', required:true, pattern:"[\\p{L}]+(?:[\\s\\'\\-]+[\\p{L}]+)+", title:ui.full_name_title },
    phone: { label:ui.phone, type:'tel', placeholder:ui.phone_ph, name:'phone', required:true, maxlength:10, minlength:10, pattern:'[0-9]{10}', title:ui.phone_title },
    fan: { label:ui.fan, type:'text', placeholder:ui.fan_ph, name:'fan', required:true, maxlength:50, pattern:'[A-Za-z0-9\\-\\/ ]+', title:ui.fan_title },
    account_number: { label:ui.account, type:'text', placeholder:ui.account_ph, name:'account_number', required:true, maxlength:13, minlength:13, pattern:'[0-9]{13}', title:ui.account_title },
    amount: { label:ui.amount, type:'number', placeholder:ui.amount_ph, name:'amount', required:true, min:1, step:'0.01' }
};

function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
}

function decodeFields(option) {
    // Keep field configuration language-neutral. The global language translator
    // works on rendered HTML, so the field list is Base64 encoded to prevent it
    // from changing the JSON before JavaScript reads it.
    try {
        const encoded = option && option.dataset ? option.dataset.fieldsB64 : '';
        if (encoded) {
            const binary = atob(encoded);
            const bytes = Uint8Array.from(binary, ch => ch.charCodeAt(0));
            const json = new TextDecoder('utf-8').decode(bytes);
            const parsed = JSON.parse(json);
            if (Array.isArray(parsed)) return parsed;
        }
    } catch (e) {
        console.warn('Could not decode service fields:', e);
    }

    // Backward-compatible fallback for older service options.
    try {
        const raw = option && option.dataset ? option.dataset.fields : '';
        const parsed = raw ? JSON.parse(raw) : [];
        if (Array.isArray(parsed)) return parsed;
    } catch (e) {}
    return [];
}

function normalizeFieldKey(rawKey) {
    const key = String(rawKey || '').trim().toLowerCase();
    const aliases = {
        'fullname':'full_name', 'full name':'full_name', 'name':'full_name',
        'phone number':'phone', 'phone_number':'phone',
        'account':'account_number', 'account number':'account_number',
        'accountnumber':'account_number', 'account_no':'account_number',
        'fan number':'fan', 'amount/birr':'amount'
    };
    return aliases[key] || key;
}

function renderFields() {
    const option = serviceSelect.options[serviceSelect.selectedIndex];
    const serviceName = option ? (option.dataset.serviceNameLocalized || option.dataset.serviceName || '') : '';
    let fields = decodeFields(option);

    // If a service has no saved field configuration, do not leave the customer
    // with a hidden/empty information area. These are the minimum registration
    // fields and match the previous registration behaviour.
    if (serviceName && !fields.length) fields = ['full_name', 'phone'];

    serviceForm.innerHTML = '';
    serviceForm.className = 'fields-panel';
    submitBtn.disabled = !serviceName;

    if (!serviceName) {
        serviceForm.className = 'fields-panel is-empty';
        serviceForm.innerHTML = '<div class="empty-state"><div class="empty-icon">☝</div><h3>' + escapeHtml(ui.choose_first) + '</h3><p>' + escapeHtml(ui.required_here) + '</p></div>';
        return;
    }

    const validFields = fields.map(normalizeFieldKey).filter(key => fieldCatalog[key]);
    if (!validFields.length) {
        // Never hide the section for Afaan Oromoo/Amharic because of a bad or
        // translated field key. Fall back to the basic customer fields.
        validFields.push('full_name', 'phone');
    }

    const title = document.createElement('div');
    title.className = 'fields-title';
    title.innerHTML = '<div><span class="section-kicker">' + escapeHtml(ui.service_information) + '</span><h2>' + escapeHtml(serviceName) + '</h2></div><span class="required-note">' + escapeHtml(ui.required) + '</span>';
    serviceForm.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'fields-grid';

    validFields.forEach(key => {
        const field = fieldCatalog[key];
        const wrapper = document.createElement('div');
        wrapper.className = 'field-group' + (validFields.length === 3 && key === 'phone' ? ' field-wide' : '');
        const id = 'field_' + key;
        let input = '<input id="' + id + '" type="' + field.type + '" name="' + field.name + '" class="form-control custom-input" placeholder="' + escapeHtml(field.placeholder) + '"';
        if (field.required) input += ' required';
        if (field.pattern) input += ' pattern="' + field.pattern + '"';
        if (field.title) input += ' title="' + escapeHtml(field.title) + '"';
        if (field.maxlength) input += ' maxlength="' + field.maxlength + '"';
        if (field.minlength) input += ' minlength="' + field.minlength + '"';
        if (field.min !== undefined) input += ' min="' + field.min + '"';
        if (field.step) input += ' step="' + field.step + '"';
        input += ' autocomplete="off">';
        wrapper.innerHTML = '<label class="form-label" for="' + id + '">' + escapeHtml(field.label) + ' <span>*</span></label>' + input;
        grid.appendChild(wrapper);
    });

    serviceForm.appendChild(grid);
    serviceForm.className = 'fields-panel';
    serviceForm.style.display = 'block';
    serviceForm.style.visibility = 'visible';
    submitBtn.disabled = false;
}

serviceSelect.addEventListener('change', renderFields);
renderFields();
</script>
</body>
</html>
