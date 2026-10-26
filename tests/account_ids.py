"""Integration checks against a disposable schema; never create real accounts or send mail.
Run: python tests/account_ids.py (requires local XAMPP PHP/MySQL).
"""
import base64, concurrent.futures, json, os, pathlib, subprocess, uuid, tempfile, re
ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = r'C:\xampp\php\php.exe'
DB = 'frostline_id_test_' + uuid.uuid4().hex[:12]
MAIL_DIR = tempfile.TemporaryDirectory(prefix='frostline-mail-test-')
MAILBOX = pathlib.Path(MAIL_DIR.name)/'messages.txt'
CAPTURE = pathlib.Path(MAIL_DIR.name)/'capture.php'
CAPTURE.write_text("<?php file_put_contents(getenv('FROSTLINE_TEST_MAILBOX'), stream_get_contents(STDIN), FILE_APPEND);", encoding='utf-8')

def enc(value):
    return base64.b64encode(json.dumps(value).encode()).decode()

def php(code, isolated=True):
    prefix = "<?php chdir('" + ROOT.as_posix() + "/php'); require_once 'db.php';"
    if isolated:
        prefix += "$pdo->exec('USE `" + DB + "`');"
    proc = subprocess.run([PHP, '-d', 'sendmail_path='+PHP+' '+str(CAPTURE)], input=prefix+code, text=True, capture_output=True,
                          env={**os.environ, 'FROSTLINE_MAIL_FROM': 'test@example.test', 'FROSTLINE_TEST_MAILBOX': str(MAILBOX)})
    if proc.stderr: print(proc.stderr)
    if proc.returncode or 'Fatal error' in proc.stdout:
        raise AssertionError(proc.stdout + proc.stderr)
    return proc.stdout

def sql(query):
    return json.loads(php("$q=json_decode(base64_decode('"+enc(query)+"')); $s=$pdo->query($q); echo json_encode($s->columnCount() ? $s->fetchAll(PDO::FETCH_ASSOC) : []);"))

def endpoint(file, post, session, method="POST"):
    code = "$data=json_decode(base64_decode('"+enc([post,session])+"'),true);"
    code += "session_id(bin2hex(random_bytes(16))); session_start(); $_SESSION=$data[1]; session_write_close(); $_POST=$data[0]; $_GET=[]; $_SERVER['REQUEST_METHOD']='"+method+"'; $_SERVER['REMOTE_ADDR']='127.0.0.1';"
    code += "register_shutdown_function(function(){$result=json_encode($_SESSION); session_destroy(); echo '\nTEST_SESSION:'.$result;}); require '"+file+"';"
    out=php(code)
    endpoint.output = out
    assert 'TEST_SESSION:' in out, out
    return json.loads(out.split('TEST_SESSION:')[-1])

try:
    php("$pdo->exec('CREATE DATABASE `"+DB+"`'); foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {$pdo->exec('CREATE TABLE `"+DB+"`.`'.$t.'` LIKE `'.$t.'`');}", False)
    php("$pdo->exec(file_get_contents('../sql/add_account_id_sequence.sql'));")
    # Synthetic legacy Super Admin only; no copies of real customer data.
    sql("INSERT INTO users (id_number,first_name,last_name,birthdate,age,username,password,email,sex,purok,barangay,city_municipality,province,country,zip_code,role,status) VALUES ('LEGACY-SA','Test','Owner','2000-01-01',26,'TestOwner','unused','owner@example.test','private','','','','','','','SUPER_ADMIN','ACTIVE')")
    actor=int(sql("SELECT id FROM users WHERE username='TestOwner'")[0]['id'])
    session={'user_id':actor,'role':'SUPER_ADMIN','admin_management_csrf':'test','account_management_csrf':'test'}
    user=dict(username='tester.person',password='SecurePass123',confirm_password='SecurePass123',first_name='Test',middle_name='',last_name='Person',birthdate='2000-01-01',purok='Purok 1',barangay='Barangay Test',city_municipality='Manila',province='Manila',country='Philippines',zip_code='1000',sex='private',email='user@example.test',question1='Who is your best friend in Elementary?',question2='What was the name of your first school?',question3='What was your childhood nickname?',answer1='One',answer2='Two',answer3='Three',id_number='MANIPULATED',role='SUPER_ADMIN')
    endpoint('process_register.php',user,{})
    year=php("echo date('Y');")
    row=sql("SELECT * FROM users WHERE username='tester.person'")[0]
    assert row['id_number']==year+'-0001' and row['role']=='USER'
    assert len(sql('SELECT * FROM security_answers WHERE user_id='+str(row['id'])))==3
    print('PASS registration: generated ID, ignored forged ID/role, saved recovery answers')
    admin=dict(action='create',csrf_token='test',first_name='Test',middle_name='',last_name='Admin',username='TestAdmin',email='admin@example.test',role='ADMIN',id_number='FORGED',temporary_password='InitialPass123!',temporary_password_confirmation='InitialPass123!')
    endpoint('admin_management.php',admin,session)
    endpoint('admin_management.php',{**admin,'username':'TestSuper','email':'super@example.test','role':'SUPER_ADMIN'},session)
    rows=sql("SELECT id,id_number,role,first_login_required FROM users WHERE username IN ('TestAdmin','TestSuper') ORDER BY id")
    assert [r['id_number'] for r in rows]==[year+'-0002',year+'-0003'], rows
    assert all(int(r['first_login_required'])==1 for r in rows)
    aid=int(rows[0]['id'])
    print('PASS Admin and Super Admin creation: one shared sequence, setup flags retained')
    for file, key in [('admin_management.php','admin_id'),('account_management.php','account_id')]:
        endpoint(file,{**admin,'action':'update',key:aid,'status':'ACTIVE','id_number':'CHANGED','first_name':'Updated'},session)
        r=sql('SELECT id_number,first_name FROM users WHERE id='+str(aid))[0]
        assert r['id_number']==year+'-0002' and r['first_name']=='Updated',r
    print('PASS both account-edit handlers ignore forged ID while saving permitted edits')
    php("$pdo->prepare(\"INSERT INTO password_recovery (user_id,otp_hash,expires_at,last_sent_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE),NOW())\")->execute(["+str(aid)+",password_hash('123456', PASSWORD_DEFAULT)]);")
    challenge=int(sql('SELECT MAX(id) AS id FROM password_recovery')[0]['id'])
    setup_session={'first_login_user_id':aid,'first_login_challenge_id':challenge,'first_login_stage':'otp_pending','first_login_username':'TestAdmin','first_login_full_name':'Test Admin','first_login_email':'admin@example.test','first_login_role':'ADMIN','first_login_otp_csrf':'otp'}
    setup_session=endpoint('first_login_otp.php',{'action':'verify','csrf_token':'otp','otp':'123456'},setup_session)
    assert setup_session['first_login_stage']=='otp_verified',setup_session
    setup_session['first_login_setup_csrf']='setup'
    result=endpoint('process_first_login_setup.php',{**{k:v for k,v in user.items() if k.startswith(('question','answer'))},'csrf_token':'setup','new_password':'ChangedPass123!','confirm_password':'ChangedPass123!','id_number':'ATTACK','role':'SUPER_ADMIN'},setup_session)
    r=sql('SELECT id_number,role,first_login_required FROM users WHERE id='+str(aid))[0]
    assert r['id_number']==year+'-0002' and r['role']=='ADMIN' and int(r['first_login_required'])==0,(r,result)
    assert sql('SELECT used_at FROM password_recovery WHERE id='+str(challenge))[0]['used_at']
    assert len(sql('SELECT * FROM security_answers WHERE user_id='+str(aid)))==3
    assert int(sql('SELECT COUNT(*) AS n FROM users')[0]['n'])==4
    print('PASS real OTP verification and first-login setup preserve ID/role without creating another account')
    # Super Admin must complete the same flow, with real mail calls captured locally.
    sid=int(rows[1]['id'])
    mail=MAILBOX.read_text(encoding='utf-8')
    temp_password=re.findall(r'Password\s*:\s*(\S+)',mail)[-1]
    assert 'super@example.test' in mail and year+'-0003' in mail
    stale={'user_id':sid,'username':'TestSuper','role':'SUPER_ADMIN'}
    for file in ['superadmin.php','superadmin_shell.php','admin_management.php','account_management.php']:
        blocked=endpoint(file,{},stale,'GET')
        assert 'user_id' not in blocked and 'login_flash' in blocked,(file,blocked)
        assert '<!DOCTYPE' not in endpoint.output
    login=endpoint('login.php',{'username':'TestSuper','password':temp_password},session)
    assert login['first_login_stage']=='otp_pending' and 'user_id' not in login,login
    assert login['first_login_role']=='SUPER_ADMIN'
    challenge=login['first_login_challenge_id']
    otp=re.findall(r'verification code is: (\d{6})',MAILBOX.read_text(encoding='utf-8'))[-1]
    premature={**login,'first_login_stage':'otp_verified','first_login_setup_csrf':'setup'}
    endpoint('process_first_login_setup.php',{'csrf_token':'setup'},premature)
    assert int(sql('SELECT first_login_required FROM users WHERE id='+str(sid))[0]['first_login_required'])==1
    endpoint('first_login_otp.php',{'csrf_token':login['first_login_otp_csrf'],'otp':'000000'},login)
    assert int(sql('SELECT otp_verified FROM password_recovery WHERE id='+str(challenge))[0]['otp_verified'])==0
    # Exercise resend and role-aware logs too.
    sql('UPDATE password_recovery SET last_sent_at=DATE_SUB(last_sent_at, INTERVAL 2 MINUTE) WHERE id='+str(challenge))
    login=endpoint('first_login_otp.php',{'csrf_token':login['first_login_otp_csrf'],'action':'resend'},login)
    otp=re.findall(r'verification code is: (\d{6})',MAILBOX.read_text(encoding='utf-8'))[-1]
    verified=endpoint('first_login_otp.php',{'csrf_token':login['first_login_otp_csrf'],'otp':otp},login)
    assert verified['first_login_stage']=='otp_verified' and 'user_id' not in verified
    verified=endpoint('first_login_setup.php',{},verified,'GET')
    assert 'Complete Account Setup' in endpoint.output
    post={**{k:v for k,v in user.items() if k.startswith(('question','answer'))},'csrf_token':verified['first_login_setup_csrf'],'new_password':temp_password,'confirm_password':temp_password,'role':'USER','id_number':'FORGED'}
    rejected=endpoint('process_first_login_setup.php',post,verified)
    assert 'user_id' not in rejected and rejected['first_login_setup_errors']
    finished=endpoint('process_first_login_setup.php',{**post,'new_password':'SuperChanged123!','confirm_password':'SuperChanged123!'},verified)
    assert finished['role']=='SUPER_ADMIN' and int(finished['user_id'])==sid,finished
    r=sql('SELECT id_number,role,first_login_required FROM users WHERE id='+str(sid))[0]
    assert r['id_number']==year+'-0003' and r['role']=='SUPER_ADMIN' and int(r['first_login_required'])==0
    assert php("$s=$pdo->query('SELECT password FROM users WHERE id="+str(sid)+"'); echo password_verify('SuperChanged123!', $s->fetchColumn()) ? 'ok':'bad';")=='ok'
    assert len(sql('SELECT * FROM security_answers WHERE user_id='+str(sid)))==3
    assert sql('SELECT used_at FROM password_recovery WHERE id='+str(challenge))[0]['used_at']
    events=sql("SELECT role,action FROM security_logs WHERE user_id="+str(sid)+" AND action LIKE 'FIRST_LOGIN_%'")
    assert all(e['role']=='SUPER_ADMIN' for e in events),events
    assert {'FIRST_LOGIN_ATTEMPTED','FIRST_LOGIN_OTP_SENT','FIRST_LOGIN_OTP_RESENT','FIRST_LOGIN_OTP_FAILED','FIRST_LOGIN_OTP_VERIFIED','FIRST_LOGIN_SETUP_COMPLETED'} <= {e['action'] for e in events},events
    endpoint('superadmin.php',{},finished,'GET')
    assert 'Super Administrator Dashboard' in endpoint.output
    replay=endpoint('process_first_login_setup.php',{**post,'new_password':'ReplayPass123!','confirm_password':'ReplayPass123!'},verified)
    assert 'user_id' not in replay
    normal=endpoint('login.php',{'username':'TestSuper','password':'SuperChanged123!'}, {})
    assert normal['role']=='SUPER_ADMIN' and 'first_login_stage' not in normal
    # The original account remains exempt because its existing flag is zero.
    php("$pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash('CoreSecret123!',PASSWORD_DEFAULT),"+str(actor)+"]);")
    core=endpoint('login.php',{'username':'TestOwner','password':'CoreSecret123!'}, {})
    assert core['role']=='SUPER_ADMIN' and 'first_login_stage' not in core
    assert int(sql('SELECT COUNT(*) AS n FROM password_recovery WHERE user_id='+str(actor))[0]['n'])==0
    print('PASS Super Admin credentials email, login, OTP/resend, setup, role-aware logs, dashboard guards, replay rejection and core exemption (mail captured locally)')
    # Duplicate validation must not allocate; rolled-back allocations are reusable.
    endpoint('admin_management.php',admin,session)
    assert int(sql('SELECT last_number FROM account_id_sequence')[0]['last_number'])==3
    php("$pdo->beginTransaction(); generateAccountId($pdo); $pdo->rollBack();")
    assert int(sql('SELECT last_number FROM account_id_sequence')[0]['last_number'])==3
    def reserve(_):
        return php("$pdo->beginTransaction(); $id=generateAccountId($pdo); usleep(30000); $pdo->commit(); echo $id;")
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        values=list(pool.map(reserve,range(16)))
    assert len(set(values))==16 and sorted(int(v.split('-')[1]) for v in values)==list(range(4,20)),values
    php("$pdo->exec(file_get_contents('../sql/add_account_id_sequence.sql'));")
    assert int(sql('SELECT last_number FROM account_id_sequence')[0]['last_number'])==19
    assert sql("SELECT id_number FROM users WHERE username='TestOwner'")[0]['id_number']=='LEGACY-SA'
    print('PASS concurrency (16 reservations), rollback, duplicate submission, migration rerun, legacy ID preservation')
finally:
    # Only the randomly named schema created above is removed.
    assert DB.startswith('frostline_id_test_') and len(DB)==30
    php("$pdo->exec('DROP DATABASE IF EXISTS `"+DB+"`');",False)
    MAIL_DIR.cleanup()
