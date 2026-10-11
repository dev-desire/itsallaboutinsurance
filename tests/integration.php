<?php
/** Synthetic WordPress/Elementor integration suite. Never run against a real account database. */
use IAAI\Portal\{Core,Schema,Auth,Records,Enquiries,Sharing,Support,Privacy,Worker,Templates,Mfa};
if (PHP_SAPI !== 'cli' || !getenv('IAAI_TEST_WP_PATH')) { exit(1); }
$_SERVER['HTTP_HOST']='localhost:8090'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if (!defined('IAAI_TEST_SITE') || !IAAI_TEST_SITE || !Core::dev()) { throw new RuntimeException('An isolated synthetic test installation is required'); }
$root=dirname(__DIR__); $passed=[]; $failed=[]; $suffix=bin2hex(random_bytes(5)); $password='Synthetic-Only-'.bin2hex(random_bytes(8));
// Independent synthetic clients avoid exhausting the previous run's legitimate rate bucket.
$_SERVER['REMOTE_ADDR']='2001:db8:'.substr($suffix,0,4).':'.substr($suffix,4,4).'::1';
function check($condition,string $name): void {global $passed,$failed; if ($condition) { $passed[]=$name; } else { $failed[]=$name; } echo ($condition?'PASS ':'FAIL ').$name."\n";}
function request(string $method,string $path,array $data=[],bool $multipart=false): WP_REST_Request {
 $r=new WP_REST_Request($method,'/iaai/v1/'.$path); $r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
 if($multipart){$r->set_body_params($data);}else{$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($data));}
 foreach($data as $k=>$v){if($method==='GET')$r->set_param($k,$v);}
 return $r;
}
function call(string $method,string $path,array $data=[]): WP_REST_Response { $r=rest_do_request(request($method,$path,$data)); if($r->get_status()>=400){ echo 'RESPONSE '.$method.' '.$path.' '.$r->get_status().' '.($r->get_data()['code']??'')."\n"; } return $r; }
function login_as(int $id,bool $mfa=false): string {
 wp_set_current_user($id); $token=WP_Session_Tokens::get_instance($id)->create(time()+3600);
 if($mfa){$manager=WP_Session_Tokens::get_instance($id);$s=$manager->get($token);$s['two-factor-provider']='Two_Factor_Totp';$s['two-factor-login']=time();$manager->update($token,$s);}
 $_COOKIE[LOGGED_IN_COOKIE]=wp_generate_auth_cookie($id,time()+3600,'logged_in',$token);
 set_transient('iaai_session_'.hash('sha256',$token),['start'=>time(),'last'=>time(),'reauth'=>time()],28800);
 return $token;
}
function new_user(string $name,string $role='iaai_portal_user',bool $verified=true): int {
 global $suffix,$password;
 $id=wp_insert_user(['user_login'=>'fixture_'.$name.'_'.$suffix,'user_email'=>$name.'_'.$suffix.'@example.invalid','user_pass'=>$password,'display_name'=>'Synthetic '.$name,'role'=>$role]);
 if(is_wp_error($id))throw new RuntimeException($id->get_error_code());
 if($verified)update_user_meta($id,'iaai_verified_at',Core::now());
 if($role!=='iaai_portal_user'){update_user_meta($id,Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY,['Two_Factor_Totp']);update_user_meta($id,Two_Factor_Totp::SECRET_META_KEY,Two_Factor_Totp::generate_key());}
 return $id;
}
function upload_file(string $file,string $key='',array $more=[]): WP_REST_Response {
 global $root;
 $r=request('POST','reports/upload',array_merge(['category'=>'blood_lab','date'=>'2026-10-01','title'=>'Synthetic private report','storage_consent'=>1],$more),true);
 $r->set_header('Idempotency-Key',$key?:wp_generate_uuid4());
 $r->set_file_params(['files'=>['name'=>[basename($file)],'tmp_name'=>[$file],'error'=>[UPLOAD_ERR_OK],'size'=>[filesize($file)]]]);
 return rest_do_request($r);
}
function grant(string $ref,int $recipient,array $reports,array $sections=[]): WP_REST_Response {return call('POST','sharing',['enquiry'=>$ref,'recipient_id'=>$recipient,'reports'=>$reports,'sections'=>$sections,'days'=>1,'consent'=>1]);}
try {
 Schema::install();
 global $wpdb;
 check(get_option('iaai_schema_version')==='2','Schema installs transactional custom tables');
 $import=Templates::import($root.'/elementor','fixture-'.$suffix);
 check(!$import['conflicts'],'Separate Elementor pages import without conflicts');
 $again=Templates::import($root.'/elementor','fixture-'.$suffix);
 check(count($again['unchanged'])===count(json_decode(file_get_contents($root.'/elementor/manifest.json'),true)['templates']) && !$again['updated'],'Repeated page import creates no duplicates');
 $guide=(int)$wpdb->get_var("SELECT post_id FROM ".Core::table('managed_templates')." WHERE deployment_key LIKE 'guide-%' AND locale='en' LIMIT 1");wp_update_post(['ID'=>$guide,'post_status'=>'publish']);check(get_post_status($guide)==='draft','Guide without source/author/reviewer approval stays draft');
 $case=(int)$wpdb->get_var("SELECT post_id FROM ".Core::table('managed_templates')." WHERE deployment_key='case-detail' AND locale='en'");delete_post_meta($case,'_iaai_review_review_date');delete_post_meta($case,'_iaai_review_payment_verified');foreach(['consent','evidence','anonymized']as$field)update_post_meta($case,'_iaai_review_'.$field,'synthetic');update_post_meta($case,'_iaai_review_outcome','pending');wp_update_post(['ID'=>$case,'post_status'=>'publish']);check(get_post_status($case)==='draft','Case without review date stays draft');update_post_meta($case,'_iaai_review_review_date','2026-10-08');update_post_meta($case,'_iaai_review_outcome','paid');wp_update_post(['ID'=>$case,'post_status'=>'publish']);check(get_post_status($case)==='draft','Paid outcome requires actual payment verification before publication');

 // Initialize the real REST server before Elementor's internal rendering simulator removes init callbacks.
 rest_get_server();
 $widgets=Elementor\Plugin::$instance->widgets_manager->get_widget_types();
 check(count(array_filter(array_keys($widgets),fn($x)=>str_starts_with($x,'iaai-')))===15 && isset($widgets['iaai-termplanner'], $widgets['iaai-calculators'], $widgets['iaai-posts']),'Fifteen registered Elementor widgets including calculators and blog posts');
 // The daily feed must paginate published content without exposing protected articles.
 $category=wp_insert_term('Synthetic journal '.$suffix,'category');$category_id=(int)$category['term_id'];$journal_ids=[];
 foreach(['publish','publish','draft','private','protected'] as $index=>$status){
  $journal_ids[]=wp_insert_post(['post_type'=>'post','post_status'=>$status==='protected'?'publish':$status,'post_password'=>$status==='protected'?'synthetic-only':'','post_title'=>'Journal '.$status.' '.$index.' '.$suffix,'post_content'=>'Synthetic article body.','post_category'=>[$category_id]]);
 }
 $feed=IAAI\Portal\Elementor::posts(['category_id'=>$category_id,'posts_per_page'=>1,'show_pagination'=>'yes']);
 check(substr_count($feed,'<article class="iaai-journal-card">')===1 && str_contains($feed,'iaai_blog_page') && !str_contains($feed,'Journal draft') && !str_contains($feed,'Journal private') && !str_contains($feed,'Journal protected'),'Blog feed paginates published articles and excludes drafts/private/password-protected posts');
 $saved_get=$_GET;$_GET['iaai_blog_page']='2';$second=IAAI\Portal\Elementor::posts(['category_id'=>$category_id,'posts_per_page'=>1,'show_pagination'=>'yes']);$_GET=$saved_get;
 check(substr_count($second,'<article class="iaai-journal-card">')===1 && $second!==$feed,'Blog pagination changes the displayed article');
 foreach($journal_ids as $id){wp_delete_post($id,true);}wp_delete_term($category_id,'category');
 $home=(int)get_option('page_on_front');
 check(strlen(Elementor\Plugin::$instance->frontend->get_builder_content_for_display($home))>20000,'Native Elementor public home renders');
 check(count(apply_filters('wp_privacy_personal_data_exporters',[]))>0 && isset(apply_filters('wp_privacy_personal_data_erasers',[])['iaai-portal']),'Native WordPress privacy hooks register outside REST');
 check(Core::locale()==='en','Clean account defaults to English');
 $_COOKIE['iaai_locale']='hi';check(Core::locale()==='hi','Explicit Hindi choice persists');unset($_COOKIE['iaai_locale']);
 wp_set_current_user(0);unset($_COOKIE[LOGGED_IN_COOKIE]);
 check(call('GET','dashboard')->get_status()===401,'Anonymous dashboard denied');
 $sid=bin2hex(random_bytes(32));$_COOKIE['iaai_pre_auth']=$sid;set_transient('iaai_ctx_'.hash('sha256',$sid),1,900);
 $csrf=request('POST','enquiries');check(is_wp_error(Core::public_permission($csrf)),'Missing public CSRF denied');
 $csrf->set_header('X-IAAI-CSRF',hash_hmac('sha256','context:'.$sid,wp_salt('nonce')));check(Core::public_permission($csrf)===true,'Bound public CSRF accepted');
 $_SERVER['HTTP_ORIGIN']='https://attacker.invalid';check(is_wp_error(Core::public_permission($csrf)),'Cross-origin public write denied');unset($_SERVER['HTTP_ORIGIN']);
 update_option('iaai_notices_approved',false);check(is_wp_error(Enquiries::create(request('POST','enquiries'))),'Unreviewed public notices block collection');
 $noticeHashes=[];
 foreach(['privacy','terms','disclosures']as$key){foreach(['en','hi']as$locale){$id=(int)$wpdb->get_var($wpdb->prepare('SELECT post_id FROM '.Core::table('managed_templates').' WHERE deployment_key=%s AND locale=%s',$key,$locale));wp_update_post(['ID'=>$id,'post_status'=>'publish']);$noticeHashes[$key.'/'.$locale]=Templates::hash($id);}}
 update_option('iaai_notice_hashes',$noticeHashes);update_option('iaai_notices_approved',true);update_option('iaai_legal_identity','Synthetic test identity');update_option('iaai_service_role','Synthetic test educator');update_option('iaai_privacy_contact','privacy@example.invalid');update_option('iaai_mail_verified',true);update_option('iaai_retention_approved',true);update_option('iaai_backup_restore_verified',true);update_option('iaai_worker_heartbeat',time());
 check(Core::notices_ready(),'Exact reviewed notice hashes unlock collection');
 $notice=(int)$wpdb->get_var("SELECT post_id FROM ".Core::table('managed_templates')." WHERE deployment_key='privacy' AND locale='en'");$description=get_post_meta($notice,'_iaai_description',true);update_post_meta($notice,'_iaai_description','Synthetic changed notice');check(!Core::notices_ready(),'Notice changes invalidate collection approval');update_post_meta($notice,'_iaai_description',$description);

 $a=new_user('a');$b=new_user('b');$unverified=new_user('unverified','iaai_portal_user',false);$manager=new_user('manager','iaai_manager');$agent=new_user('agent','iaai_service_agent');$other=new_user('other','iaai_service_agent');$editor=new_user('editor','iaai_content_editor');
 login_as($unverified);check(call('GET','reports')->get_status()===403,'Unverified account cannot access reports');
 login_as($editor);check(call('GET','staff/enquiries')->get_status()===404 && call('GET','reports')->get_status()===404,'Content editor cannot access leads or records');
 login_as($manager);check(call('GET','staff/enquiries')->get_status()===403,'Staff password session cannot bypass MFA');
 $code=Two_Factor_Totp::calc_totp(get_user_meta($manager,Two_Factor_Totp::SECRET_META_KEY,true));check(Two_Factor_Totp::get_instance()->validate_code_for_user(get_userdata($manager),$code),'Official TOTP provider validates authenticator code');
 check(!Two_Factor_Totp::get_instance()->validate_code_for_user(get_userdata($manager),$code),'TOTP code cannot be replayed');
 login_as($manager,true);check(Mfa::verified(false,$manager,wp_get_session_token()),'Adapter accepts actual provider-bound session metadata');
 $cipher=Core::seal(['note'=>'Synthetic private value']);check(!str_contains($cipher,'Synthetic') && Core::open($cipher)['note']==='Synthetic private value','Metadata encryption authenticates and round-trips');
 $raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);try{Core::open(base64_encode($raw));$tamper=false;}catch(Throwable $e){$tamper=true;}check($tamper,'Tampered metadata is rejected');
 login_as($a);check(call('PATCH','profile',['version'=>0,'storage_consent'=>1,'city'=>'Synthetic City'])->get_status()===200,'Optional profile saves without medical history');
 check(call('PATCH','profile',['version'=>0,'storage_consent'=>1])->get_status()===409,'Stale profile version rejected');
 $profileCipher=$wpdb->get_var($wpdb->prepare('SELECT payload FROM '.Core::table('profiles').' WHERE user_id=%d',$a));check(!str_contains($profileCipher,'Synthetic City'),'Database profile has no plaintext medical/contact fields');
 add_filter('iaai_test_scan','__return_zero');
 check(Records::readiness()['ready'],'Synthetic infrastructure readiness is explicit');
 $key=wp_generate_uuid4();$u=upload_file($root.'/tests/fixtures/two-pages.pdf',$key);check($u->get_status()===202,'Readable PDF is quarantined');$report=$u->get_data()['items'][0];
 $again=upload_file($root.'/tests/fixtures/two-pages.pdf',$key);check($again->get_data()['items']===$u->get_data()['items'],'Upload retry reuses exactly the same record IDs');
 check(call('GET','reports/'.$report.'/content')->get_status()===404,'Unscanned originals cannot be downloaded');
 $row=Records::row($report);check(!str_contains(file_get_contents(Records::root().'/'.$row['storage_key'].'.bin'),'%PDF'),'Stored original is encrypted outside WordPress web root');
 Worker::scan($report);$row=Records::row($report);check($row['state']==='available' && count(json_decode($row['preview_keys'],true))===2,'Real PDF parser renders both safe preview pages');
 check(call('GET','reports/'.$report.'/content',['preview'=>1,'page'=>1])->get_status()===200,'Owner can request second PDF preview page');
 $png=upload_file($root.'/tests/fixtures/sample.png')->get_data()['items'][0];Worker::scan($png);check(Records::row($png)['state']==='available','PNG structural decode creates private preview');
 check(upload_file($root.'/tests/fixtures/disguised.pdf')->get_status()===415,'MIME/extension spoof is rejected');
 foreach(['encrypted.pdf','broken.pdf']as$file){$id=upload_file($root.'/tests/fixtures/'.$file)->get_data()['items'][0];Worker::scan($id);check(Records::row($id)['state']==='rejected',$file.' fails closed');}
 $used=(int)$wpdb->get_var($wpdb->prepare('SELECT used_bytes FROM '.Core::table('storage_usage').' WHERE user_id=%d',$a));update_option('iaai_quota_bytes',$used+1);check(upload_file($root.'/tests/fixtures/sample.png')->get_status()===413,'Reserved/pending quota prevents oversubscription');update_option('iaai_quota_bytes',250000000);
 remove_filter('iaai_test_scan','__return_zero');check(!Records::readiness()['ready'],'Missing actual scanner blocks uploads');check(upload_file($root.'/tests/fixtures/sample.png')->get_status()===503,'Upload gate fails closed when scanner unavailable');add_filter('iaai_test_scan','__return_zero');
 login_as($b);update_user_meta($b,'iaai_storage_consent','storage-v1');check(call('GET','reports/'.$report)->get_status()===404 && call('GET','reports/'.$report.'/content')->get_status()===404,'Second member cannot read another owner metadata or file');
 check(call('PATCH','reports/'.$report,['version'=>1,'title'=>'Attempt'])->get_status()===404 && call('DELETE','reports/'.$report)->get_status()===404,'Second member cannot edit or delete another report');
 check(call('POST','treatments',['date'=>'2026-10-01','type'=>'test','title'=>'Synthetic','reports'=>[$report]])->get_status()===404,'Cross-owner timeline report linkage denied');
 login_as($a);check(call('POST','treatments',['date'=>'2026-10-01','type'=>'test','title'=>'Synthetic','reports'=>[$report]])->get_status()===200,'Own timeline can link own report');
 $p=['service'=>'health','name'=>'Synthetic A','phone'=>'9876543210','email'=>get_userdata($a)->user_email,'contact_consent'=>1];$r=request('POST','enquiries',$p);$r->set_header('Idempotency-Key',wp_generate_uuid4());
 $saved=Enquiries::create($r);$ref=$saved->get_data()['reference'];$same=Enquiries::create($r);check($same['reference']===$ref,'Enquiry retry returns one durable reference');
 $p['name']='Changed';$different=request('POST','enquiries',$p);$different->set_header('Idempotency-Key',$r->get_header('Idempotency-Key'));check(Enquiries::create($different)->get_error_code()==='iaai_conflict','Changed enquiry payload cannot reuse idempotency key');
 login_as($manager,true);check(call('POST','staff/enquiries/'.$ref.'/assign',['staff_id'=>$agent])->get_status()===200,'Manager assigns enquiry with event trail');
 login_as($other,true);check(call('GET','staff/enquiries/'.$ref)->get_status()===404,'Unassigned agent cannot open enquiry');
 login_as($agent,true);check(call('GET','reports/'.$report.'/content')->get_status()===404,'Assignment alone grants no medical file access');
 login_as($a);$g=grant($ref,$agent,[$report],['medical']);check($g->get_status()===200,'Owner grants only selected report and profile snapshot');$gid=$g->get_data()['id'];
 login_as($agent,true);check(call('GET','reports/'.$report.'/content')->get_status()===200 && call('GET','reports/'.$png.'/content')->get_status()===404,'Named recipient reads selected file only');
 $snapshot=call('GET','staff/enquiries/'.$ref.'/shared-records')->get_data()['sections'];
 login_as($a);call('PATCH','profile',['version'=>1,'storage_consent'=>1,'conditions'=>'Synthetic later change']);login_as($agent,true);check(call('GET','staff/enquiries/'.$ref.'/shared-records')->get_data()['sections']===$snapshot,'Shared medical profile is a fixed snapshot');
 login_as($a);call('DELETE','sharing/'.$gid);login_as($agent,true);check(call('GET','reports/'.$report.'/content')->get_status()===404,'Revocation immediately blocks future file reads');
 login_as($a);$g=grant($ref,$agent,[$report]);$wpdb->update(Core::table('grants'),['expires_at'=>'2000-01-01 00:00:00'],['public_id'=>$g->get_data()['id']]);login_as($agent,true);check(call('GET','reports/'.$report)->get_status()===404,'Expired grant is denied');
 login_as($a);grant($ref,$agent,[$report]);login_as($manager,true);call('POST','staff/enquiries/'.$ref.'/assign',['staff_id'=>$other]);login_as($agent,true);check(call('GET','reports/'.$report)->get_status()===404,'Reassignment revokes old recipient grants');
 login_as($other,true);$lead=Enquiries::find($ref);check(call('PATCH','staff/enquiries/'.$ref,['version'=>$lead['version'],'status'=>'closed'])->get_status()===400,'Closing enquiry requires explicit reason');
 check(call('POST','staff/enquiries/'.$ref.'/notes',['note'=>'Synthetic internal note'])->get_status()===200,'Assigned internal note is saved');
 check(call('PATCH','staff/enquiries/'.$ref,['version'=>$lead['version'],'status'=>'closed','close_reason'=>'guidance_completed'])->get_status()===200,'Assigned enquiry closes with reason and event');
 login_as($a);check(!str_contains(wp_json_encode(call('GET','enquiries/mine')->get_data()),'internal note'),'Member status list does not leak internal notes');
 $bound=bin2hex(random_bytes(32));$wpdb->insert(Core::table('auth_tokens'),['user_id'=>$a,'purpose'=>'email_change','token_hash'=>hash('sha256',$bound),'payload'=>Core::seal(['email'=>'new_'.$suffix.'@example.invalid']),'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)]);
 login_as($b);check(!Auth::consume($bound,'email_change',$b),'Foreign token cannot be consumed by another account');login_as($a);check((bool)Auth::consume($bound,'email_change',$a) && !Auth::consume($bound,'email_change',$a),'Bound token is consumed exactly once');
 $reset=get_password_reset_key(get_userdata($a));$oldToken=wp_get_session_token();$d=Auth::reset(request('POST','auth/reset-password',['login'=>get_userdata($a)->user_login,'key'=>$reset,'password'=>$password.'new','confirm_password'=>$password.'new']));check(!is_wp_error($d) && !WP_Session_Tokens::get_instance($a)->verify($oldToken),'Core password reset invalidates old sessions');check(is_wp_error(Auth::reset(request('POST','auth/reset-password',['login'=>get_userdata($a)->user_login,'key'=>$reset,'password'=>$password.'new','confirm_password'=>$password.'new']))),'Core reset key cannot be replayed');
 login_as($manager,true);check(call('POST','staff/enquiries/export')->get_status()===404,'Manager cannot bulk export administrator-only data');
 check(call('GET','staff/enquiries',['status'=>'new','service'=>'term'])->get_data()===[],'Queue service/status filters return only matching leads');
 $admin=new_user('support','administrator');login_as($admin,true);check(call('GET','reports/'.$report.'/content')->get_status()===404,'Administrator role alone cannot read medical bytes');
 $safeExport=call('POST','staff/enquiries/export')->get_data();check(isset($safeExport['items'])&&!str_contains(wp_json_encode($safeExport),'9876543210')&&!isset($safeExport['items'][0]['contact'])&&!isset($safeExport['items'][0]['details']),'Audited administrator export contains only safe queue fields');
 $scope=['owner_id'=>$a,'issue_ref'=>'SYNTHETIC-12345','reason_code'=>'preview_failure','reports'=>[$report]];
 update_option('iaai_support_procedure_approved',false);check(call('POST','staff/support-access',$scope)->get_status()===404,'Exceptional support access is disabled by default');
 update_option('iaai_support_procedure_approved',true);$invalid=$scope;$invalid['issue_ref']='';check(call('POST','staff/support-access',$invalid)->get_status()===400,'Support access requires an issue reference');
 $support=call('POST','staff/support-access',$scope);check($support->get_status()===200 && call('GET','reports/'.$report.'/content')->get_status()===200 && call('GET','reports/'.$png.'/content')->get_status()===404,'Reasoned support session opens only specifically selected file');
 $supportId=$support->get_data()['id'];login_as($a);$history=call('GET','sharing/support-history')->get_data();check((bool)array_filter($history,fn($row)=>$row['id']===$supportId),'Owner can inspect exceptional support history');
 call('DELETE','sharing/support-access/'.$supportId);login_as($admin,true);check(call('GET','reports/'.$report.'/content')->get_status()===404,'Owner revocation immediately blocks exceptional support');
 $expired=call('POST','staff/support-access',$scope)->get_data()['id'];$wpdb->update(Core::table('support_access'),['expires_at'=>'2000-01-01 00:00:00'],['public_id'=>$expired]);check(call('GET','reports/'.$report.'/content')->get_status()===404,'Exceptional support expires without a scheduled cleanup');update_option('iaai_support_procedure_approved',false);
 login_as($b);check(upload_file($root.'/tests/fixtures/sample.png','',['replaces'=>$report])->get_status()===404,'New-version linkage cannot target another owner report');
 login_as($a);$originalHash=Records::row($report)['checksum'];$replacement=upload_file($root.'/tests/fixtures/sample.png','',['replaces'=>$report,'provider'=>'Synthetic Lab']);check($replacement->get_status()===202&&Core::open(Records::row($replacement->get_data()['items'][0])['metadata'])['replaces']===$report&&Records::row($report)['checksum']===$originalHash,'New version is a separate encrypted upload and preserves original bytes');
 check(count(call('POST','reports/search',['provider'=>'synthetic lab'])->get_data()['items'])===1,'Provider filter reads own encrypted metadata only');
 $queuedBefore=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Core::table('enquiries'));$fault=static function($query){return str_starts_with($query,'INSERT INTO `'.Core::table('jobs').'`')?'INSERT INTO iaai_synthetic_missing_table VALUES (1)':$query;};$prior=$wpdb->suppress_errors(true);add_filter('query',$fault);$bad=request('POST','enquiries',['service'=>'health','name'=>'Synthetic','phone'=>'9876543210','contact_consent'=>1]);$bad->set_header('Idempotency-Key',wp_generate_uuid4());$failedSave=Enquiries::create($bad);remove_filter('query',$fault);$wpdb->suppress_errors($prior);check(is_wp_error($failedSave)&&(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Core::table('enquiries'))===$queuedBefore,'Queue persistence failure rolls back enquiry and returns an honest error');
 add_filter('pre_wp_mail','__return_false');Worker::run();remove_filter('pre_wp_mail','__return_false');check((bool)Enquiries::find($ref),'Outbound mail failure preserves the durable saved enquiry');
 login_as($a);$export=call('POST','privacy/export')->get_data()['id'];Privacy::build_export($export);check(call('GET','privacy/exports/'.$export.'/content')->get_status()===200,'Fresh owner can download encrypted private export');login_as($b);check(call('GET','privacy/exports/'.$export.'/content')->get_status()===404,'Another owner cannot download export');login_as($a);$wpdb->update(Core::table('privacy_requests'),['expires_at'=>'2000-01-01 00:00:00'],['public_id'=>$export]);check(call('GET','privacy/exports/'.$export.'/content')->get_status()===404,'Expired export cannot be downloaded');Privacy::purge_export($export);
 $idle=wp_get_session_token();set_transient('iaai_session_'.hash('sha256',$idle),['start'=>time(),'last'=>time()-1801,'reauth'=>time()],28800);check(call('GET','dashboard')->get_status()===401,'Idle session expires server-side');
 login_as($a);$old=Records::row($png);call('DELETE','reports/'.$png);check(!Records::row($png) && call('GET','reports/'.$png.'/content')->get_status()===404,'Deletion revokes file access before asynchronous purge');Worker::purge_report($png);check(!is_file(Records::root().'/'.$old['storage_key'].'.bin'),'Deletion worker removes encrypted original');
 $audit=wp_json_encode($wpdb->get_results('SELECT * FROM '.Core::table('audit'),ARRAY_A));check(!str_contains($audit,'Synthetic private value') && !str_contains($audit,'Synthetic internal note') && !str_contains($audit,'9876543210'),'Audit trail contains safe references, no raw contact or medical text');
 $health=call('GET','health');check($health->get_status()===200 && !str_contains(wp_json_encode($health->get_data()),Records::root()),'Safe health endpoint exposes no storage path or key');
 login_as($a);Privacy::queue_delete($a);check(call('GET','dashboard')->get_status()===403 || call('GET','dashboard')->get_status()===401,'Account deletion restricts access immediately');Privacy::erase_account($a);check(!get_userdata($a),'Account worker removes user and own online records');
 foreach([$b,$unverified,$manager,$agent,$other,$editor,$admin]as$id){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($id);}
} catch(Throwable $e){$failed[]='Uncaught '.get_class($e).': '.$e->getMessage();echo end($failed)."\n";}
$result=['runtime'=>$GLOBALS['iaai_runtime']??'inactive','passed'=>count($passed),'failed'=>$failed,'checks'=>$passed,'date'=>gmdate('c'),'versions'=>['wordpress'=>$GLOBALS['wp_version'],'php'=>PHP_VERSION,'elementor'=>ELEMENTOR_VERSION,'two_factor'=>'0.17.0','code_snippets'=>'3.10.2']];
if(getenv('IAAI_TEST_RESULT_PATH'))file_put_contents(getenv('IAAI_TEST_RESULT_PATH'),wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo count($passed).' passed, '.count($failed)." failed\n";exit($failed?1:0);
