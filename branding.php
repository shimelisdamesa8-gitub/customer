<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/site_setup.php";

if(!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1){
    header("Location: ../login.php");
    exit();
}

$msg=''; $err='';
$uploadDir = __DIR__ . '/../uploads/site';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);

function upload_site_image($field, $uploadDir, $prefix){
    if(!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    $f=$_FILES[$field];
    if($f['error']!==UPLOAD_ERR_OK || $f['size']>8*1024*1024) return null;
    $info=@getimagesize($f['tmp_name']); $mime=$info['mime']??'';
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'];
    if(!isset($allowed[$mime])) return null;
    $name=$prefix.'_'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
    if(!move_uploaded_file($f['tmp_name'],$uploadDir.'/'.$name)) return null;
    return 'uploads/site/'.$name;
}

if(isset($_POST['save_branding'])){
    $logo = upload_site_image('logo', $uploadDir, 'logo');
    $background = upload_site_image('background', $uploadDir, 'background');
    if(isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE && !$logo) $err='Logo upload failed. Use JPG, PNG, WEBP or SVG up to 8 MB.';
    if(isset($_FILES['background']) && ($_FILES['background']['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE && !$background) $err='Background upload failed. Use JPG, PNG or WEBP up to 8 MB.';
    if(!$err){
        if($logo) site_set_setting($conn,'logo_path',$logo);
        if($background) site_set_setting($conn,'home_background',$background);
        if(isset($_POST['primary_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['primary_color'])) site_set_setting($conn,'primary_color',$_POST['primary_color']);
        if(isset($_POST['secondary_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['secondary_color'])) site_set_setting($conn,'secondary_color',$_POST['secondary_color']);
        if(isset($_POST['global_background_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['global_background_color'])) site_set_setting($conn,'global_background_color',$_POST['global_background_color']);
        site_set_setting($conn, 'apply_global_background', '1');
        // Only the Header/Navbar keeps its own color. All other page/section
        // backgrounds are controlled by the single Global Page Background Color.
        if(isset($_POST['navbar_color']) && preg_match('/^#[0-9A-Fa-f]{6}$/',$_POST['navbar_color'])) {
            site_set_setting($conn,'navbar_color',$_POST['navbar_color']);
        }
        // Contact / social links are managed dynamically by Admin.
        foreach (['facebook','telegram','twitter','linkedin','whatsapp','email'] as $socialKey) {
            $value = trim($_POST['social_'.$socialKey] ?? '');
            if ($value !== '' && strlen($value) > 500) $value = substr($value, 0, 500);
            site_set_setting($conn, 'social_'.$socialKey, $value);
        }
        $msg='Branding settings saved successfully.';
    }
}

if(isset($_POST['add_slide'])){
    $title=trim($_POST['slide_title']??'');
    $slide=upload_site_image('slide_image',$uploadDir,'slide');
    if(!$slide) $err='Please choose a valid slide image (JPG, PNG or WEBP, max 8 MB).';
    else {
        $q=mysqli_prepare($conn,"SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM homepage_slides");
        mysqli_stmt_execute($q); $r=mysqli_stmt_get_result($q); $n=(int)(mysqli_fetch_assoc($r)['n']??1); mysqli_stmt_close($q);
        $q=mysqli_prepare($conn,"INSERT INTO homepage_slides(title,image_path,sort_order,status) VALUES(?,?,?,'Active')");
        mysqli_stmt_bind_param($q,'ssi',$title,$slide,$n);
        if(mysqli_stmt_execute($q)) $msg='Homepage slide added successfully.'; else {$err='Could not add slide.'; @unlink(__DIR__.'/../'.$slide);}
        mysqli_stmt_close($q);
    }
}
if(isset($_POST['delete_slide'])){
    $id=(int)($_POST['slide_id']??0);
    $q=mysqli_prepare($conn,"SELECT image_path FROM homepage_slides WHERE slide_id=?");
    mysqli_stmt_bind_param($q,'i',$id); mysqli_stmt_execute($q); $r=mysqli_stmt_get_result($q); $row=$r?mysqli_fetch_assoc($r):null; mysqli_stmt_close($q);
    $q=mysqli_prepare($conn,"DELETE FROM homepage_slides WHERE slide_id=?"); mysqli_stmt_bind_param($q,'i',$id); mysqli_stmt_execute($q); $ok=mysqli_stmt_affected_rows($q)>0; mysqli_stmt_close($q);
    if($ok && !empty($row['image_path']) && strpos($row['image_path'],'uploads/site/')===0 && is_file(__DIR__.'/../'.$row['image_path'])) @unlink(__DIR__.'/../'.$row['image_path']);
    $msg=$ok?'Slide removed successfully.':'Slide not found.';
}
if(isset($_POST['toggle_slide'])){
    $id=(int)($_POST['slide_id']??0);
    $q=mysqli_prepare($conn,"UPDATE homepage_slides SET status=IF(status='Active','Inactive','Active') WHERE slide_id=?");
    mysqli_stmt_bind_param($q,'i',$id); mysqli_stmt_execute($q); mysqli_stmt_close($q);
    $msg='Slide status updated.';
}
$logo=site_get_setting($conn,'logo_path','assets/berhan-bank-logo.svg');
if ($logo === 'assets/berhanbank-logo.svg' || $logo === '../assets/berhanbank-logo.svg') $logo='assets/berhan-bank-logo.svg';
$background=site_get_setting($conn,'home_background','');
$primaryColor=site_get_setting($conn,'primary_color','#003DA5');
$secondaryColor=site_get_setting($conn,'secondary_color','#EAAA00');
$globalBackgroundColor=site_get_setting($conn,'global_background_color','#f5f7fa');
$applyGlobalBackground=site_get_setting($conn,'apply_global_background','1')==='1';
$sectionColors=[]; foreach(['navbar_color','hero_color','services_color','education_color','working_hours_color','about_color','feedback_color','contact_color','footer_color','button_color'] as $k){$sectionColors[$k]=site_get_setting($conn,$k,'#ffffff');}
$socialFacebook=site_get_setting($conn,'social_facebook','');
$socialTelegram=site_get_setting($conn,'social_telegram','');
$socialTwitter=site_get_setting($conn,'social_twitter','');
$socialLinkedin=site_get_setting($conn,'social_linkedin','');
$socialWhatsapp=site_get_setting($conn,'social_whatsapp','');
$socialEmail=site_get_setting($conn,'social_email','');
$slides=mysqli_query($conn,"SELECT slide_id,title,image_path,sort_order,status FROM homepage_slides ORDER BY sort_order ASC, slide_id ASC");
?>
<!doctype html><html><head>
    <link rel="stylesheet" href="css/branding.css"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Welcome to Our Bank - Homepage Posts</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2>🎨 Branding & Homepage Management</h2><p class="text-muted mb-0">Manage the bank logo and administrator-added images shown in the Home page “Welcome to Our Bank” section.</p></div><a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a></div>
<?php if($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<div class="row g-4">
<div class="col-lg-6"><div class="card p-4 h-100"><h4>🏦 Logo & Background</h4><form method="POST" enctype="multipart/form-data">
<label class="fw-bold mt-2">Corporate / Website Logo</label><input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml"><small class="text-muted">PNG, JPG, WEBP or SVG · max 8 MB</small>
<div class="mt-3"><img src="../<?php echo htmlspecialchars($logo); ?>" class="logo-preview" alt="Current logo"></div>
<label class="fw-bold mt-4">Homepage Background Image</label><input type="file" name="background" class="form-control" accept="image/png,image/jpeg,image/webp"><small class="text-muted">Optional homepage background · max 8 MB</small>
<?php if($background): ?><div class="mt-3"><img src="../<?php echo htmlspecialchars($background); ?>" class="preview" alt="Current background"></div><?php endif; ?>
<div class="row g-3 mt-3">
<div class="col-md-6"><label class="fw-bold">Primary Branding Color</label><input type="color" name="primary_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($primaryColor); ?>" title="Primary color"></div>
<div class="col-md-6"><label class="fw-bold">Secondary Branding Color</label><input type="color" name="secondary_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($secondaryColor); ?>" title="Secondary color"></div>
<div class="col-md-6"><label class="fw-bold">Global Page Background Color</label><input type="color" name="global_background_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($globalBackgroundColor); ?>" title="Same background color for all pages"><small class="text-muted d-block mt-1">Choose one master background color for the whole system.</small><div class="form-check mt-2"><input class="form-check-input" type="checkbox" checked disabled id="applyGlobalBackground"><label class="form-check-label fw-semibold" for="applyGlobalBackground">Apply this color to all page/section backgrounds</label></div><input type="hidden" name="apply_global_background" value="1"></div>
</div>
<div class="alert alert-info mt-4">
<strong>🎨 Global Page Background</strong><br>
The color above is dynamically applied to all public page/section backgrounds. The Header / Navbar and Footer keep their original static colors.
</div>
<button class="btn btn-primary mt-4" name="save_branding">💾 Save Branding</button></form></div></div>

<div class="col-lg-6"><div class="card p-4 h-100"><h4>📞 Contact & Social Media</h4><p class="text-muted">Admin can add or update the official contact channels shown in the Contact Us section. Leave a field empty to hide that channel.</p>
<form method="POST"><div class="row g-3">
<div class="col-md-6"><label class="fw-bold">Facebook</label><input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars($socialFacebook); ?>" placeholder="https://facebook.com/..."></div>
<div class="col-md-6"><label class="fw-bold">Telegram</label><input type="url" name="social_telegram" class="form-control" value="<?php echo htmlspecialchars($socialTelegram); ?>" placeholder="https://t.me/..."></div>
<div class="col-md-6"><label class="fw-bold">X / Twitter</label><input type="url" name="social_twitter" class="form-control" value="<?php echo htmlspecialchars($socialTwitter); ?>" placeholder="https://x.com/..."></div>
<div class="col-md-6"><label class="fw-bold">LinkedIn</label><input type="url" name="social_linkedin" class="form-control" value="<?php echo htmlspecialchars($socialLinkedin); ?>" placeholder="https://linkedin.com/company/..."></div>
<div class="col-md-6"><label class="fw-bold">WhatsApp</label><input type="url" name="social_whatsapp" class="form-control" value="<?php echo htmlspecialchars($socialWhatsapp); ?>" placeholder="https://wa.me/..."></div>
<div class="col-md-6"><label class="fw-bold">Email</label><input type="email" name="social_email" class="form-control" value="<?php echo htmlspecialchars($socialEmail); ?>" placeholder="support@example.com"></div>
</div><button class="btn btn-primary mt-4" name="save_branding">💾 Save Contact Channels</button></form></div></div>

<div class="col-lg-6"><div class="card p-4 h-100"><h4>🖼️ Add Welcome to Our Bank Image</h4><p class="text-muted">Only images added by the administrator are used in this section. If two or more active images exist, they rotate automatically every few seconds.</p>
<form method="POST" enctype="multipart/form-data"><label class="fw-bold">Slide Title (optional)</label><input name="slide_title" class="form-control" maxlength="150" placeholder="e.g. Mobile Banking Education">
<label class="fw-bold mt-3">Slide Image</label><input type="file" name="slide_image" class="form-control" accept="image/png,image/jpeg,image/webp" required><small class="text-muted">JPG, PNG or WEBP · max 8 MB</small>
<button class="btn btn-success mt-4" name="add_slide">➕ Add Slide</button></form></div></div>
</div>

<div class="card p-4 mt-4"><h4>📋 Welcome to Our Bank Posts</h4><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Preview</th><th>Title</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($s=mysqli_fetch_assoc($slides)): ?><tr><td><img class="preview" src="../<?php echo htmlspecialchars($s['image_path']); ?>"></td><td><?php echo htmlspecialchars($s['title']?:'Untitled slide'); ?></td><td><span class="badge <?php echo $s['status']==='Active'?'bg-success':'bg-secondary'; ?>"><?php echo htmlspecialchars($s['status']); ?></span></td><td>
<form method="POST" class="d-inline"><input type="hidden" name="slide_id" value="<?php echo (int)$s['slide_id']; ?>"><button name="toggle_slide" class="btn btn-warning btn-sm"><?php echo $s['status']==='Active'?'Hide':'Show'; ?></button></form>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete this slide?');"><input type="hidden" name="slide_id" value="<?php echo (int)$s['slide_id']; ?>"><button name="delete_slide" class="btn btn-danger btn-sm">🗑️ Delete</button></form>
</td></tr><?php endwhile; ?></tbody></table></div></div>
</div></body></html>
