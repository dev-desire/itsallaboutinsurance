(function () {
  'use strict';
  if (!window.IAAI) return;
  var csrf = '', contextPromise, links = {
    dashboard: 'my-dashboard', profile: 'my-profile', reports: 'my-reports',
    treatment: 'my-treatment-history', enquiries: 'my-enquiries', settings: 'account-settings'
  };
  var names = {
    dashboard: ['Overview', 'डैशबोर्ड'], profile: ['My profile', 'मेरी प्रोफ़ाइल'], reports: ['My reports', 'मेरी रिपोर्ट'],
    treatment: ['Treatment history', 'उपचार विवरण'], enquiries: ['My enquiries', 'मेरी enquiries'], settings: ['Account settings', 'अकाउंट सेटिंग्स']
  };
  var intake = new URL(location.href).searchParams;
  var secureLink = { mode: intake.get('mode'), token: intake.get('token'), key: intake.get('key'), login: intake.get('login') };
  if (secureLink.token || secureLink.key) {
    var cleaned = new URL(location.href);
    ['token', 'key', 'login'].forEach(function (x) { cleaned.searchParams.delete(x); });
    history.replaceState(null, '', cleaned.href);
  }
  var blobUrls = new Set();
  function t(en, hi) { return IAAI.locale === 'hi' ? (hi || en) : en; }
  function h(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function stateText(value) {
    var hi = { pending_scan:'सुरक्षा स्कैन बाकी', available:'उपलब्ध', failed:'जाँच पूरी नहीं हुई', rejected:'स्वीकार नहीं हुई', deleted:'हटाई गई', new:'नई', assigned:'सौंप दी गई', contact_attempted:'संपर्क का प्रयास', connected:'संपर्क हो गया', guidance_in_progress:'मार्गदर्शन जारी', closed:'बंद', health:'स्वास्थ्य बीमा', term:'टर्म बीमा', policy_review:'पॉलिसी समीक्षा', claim_help:'क्लेम सहायता', consultation:'परामर्श', test:'जाँच', medication_record:'दवा का विवरण', procedure:'प्रक्रिया', hospitalization:'अस्पताल में भर्ती', queued:'अनुरोध कतार में', ready:'तैयार', completed:'पूरा', expired:'अवधि समाप्त', export:'एक्सपोर्ट', delete:'हटाना' };
    return t(value.replace(/_/g, ' '), hi[value]);
  }
  function label(key) { return t(names[key][0], names[key][1]); }
  function link(path) { return IAAI.home + path.replace(/^\//, ''); }
  function context() {
    if (!contextPromise) contextPromise = fetch(IAAI.api + 'auth/context', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) { if (!d.csrf) throw new Error(d.message || 'Unable to initialize'); csrf = d.csrf; });
    return contextPromise;
  }
  async function api(path, method, data, extra) {
    method = method || 'GET';
    var headers = Object.assign({ 'X-WP-Nonce': IAAI.nonce || '' }, extra || {});
    if (method !== 'GET') { await context(); headers['X-IAAI-CSRF'] = csrf; }
    var options = { method: method, credentials: 'same-origin', headers: headers, cache: 'no-store' };
    if (data instanceof FormData) options.body = data;
    else if (data != null) { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(data); }
    var response = await fetch(IAAI.api + path, options);
    var d = await response.json();
    if (!response.ok) { var e = new Error(d.message || t('Unable to complete this request.', 'अनुरोध पूरा नहीं हुआ।')); e.code = d.code; throw e; }
    return d;
  }
  function input(name, title, type, required, value, newPassword) {
    return '<label class="iaai-field">' + h(title) + '<input name="' + h(name) + '" type="' + h(type || 'text') + '"' + (required ? ' required' : '') + ' value="' + h(value || '') + '"' + (type === 'password' ? (newPassword ? ' autocomplete="new-password" minlength="12"' : ' autocomplete="current-password"') : '') + '></label>';
  }
  function area(name, title, value) {
    return '<label class="iaai-field">' + h(title) + '<textarea name="' + h(name) + '" rows="3" maxlength="2000">' + h(value || '') + '</textarea></label>';
  }
  function select(name, title, options, value) {
    return '<label class="iaai-field">' + h(title) + '<select name="' + h(name) + '">' + options.map(function (o) { var v = Array.isArray(o) ? o[0] : o; var text = Array.isArray(o) ? o[1] : stateText(o); return '<option value="' + h(v) + '"' + (v === value ? ' selected' : '') + '>' + h(text) + '</option>'; }).join('') + '</select></label>';
  }
  function check(name, text) { return '<label class="iaai-check"><input type="checkbox" name="' + h(name) + '" value="1"><span>' + text + '</span></label>'; }
  function button(text, action, cls) { return '<button type="button" class="iaai-button ' + (cls || 'secondary') + '" data-action="' + h(action) + '">' + h(text) + '</button>'; }
  function submit(text) { return '<button class="iaai-button" type="submit">' + h(text) + '</button>'; }
  function status() { return '<p class="iaai-status" role="status" aria-live="polite"></p>'; }
  function empty(title, text) { return '<div class="iaai-empty"><span class="iaai-empty-icon" aria-hidden="true">◇</span><h3>' + h(title) + '</h3><p>' + h(text) + '</p></div>'; }
  function size(n) { return (n / 1000000).toFixed(1) + ' MB'; }
  function formData(f) {
    var p = {};
    new FormData(f).forEach(function (v, k) { p[k] = v; });
    f.querySelectorAll('input[type=checkbox]').forEach(function (v) { p[v.name] = v.checked; });
    return p;
  }
  var categories = [
    ['blood_lab', t('Blood / lab test', 'ब्लड / लैब टेस्ट')], ['imaging', t('Imaging', 'इमेजिंग')],
    ['prescription', t('Prescription', 'प्रिस्क्रिप्शन')], ['consultation', t('Doctor consultation', 'डॉक्टर परामर्श')],
    ['treatment', t('Treatment / procedure', 'उपचार / प्रक्रिया')], ['discharge', t('Discharge summary', 'डिस्चार्ज समरी')],
    ['insurance_claim', t('Insurance / claim document', 'बीमा / क्लेम दस्तावेज़')], ['other', t('Other', 'अन्य')]
  ];
  function App(root) {
    this.root = root; this.view = root.dataset.iaaiView;
    this.settings = JSON.parse(root.dataset.iaaiSettings || '{}');
    this.offset = 0; this.items = []; this.filter = {}; this.version = 0;
    this.root.addEventListener('submit', this.onSubmit.bind(this));
    this.root.addEventListener('click', this.onClick.bind(this));
    this.render();
  }
  App.prototype.mount = function (html, title) {
    var publicView = ['auth', 'guidance', 'staff'].includes(this.view);
    var nav = '<aside class="iaai-sidebar"><div class="iaai-sidebar-label">' + h(t('YOUR PRIVATE SPACE', 'आपका निजी स्थान')) + '</div>' +
      Object.keys(links).map(function (key) { return '<a class="' + (key === this.view ? 'active' : '') + '" href="' + h(link(links[key] + '/')) + '">' + h(label(key)) + '<span aria-hidden="true">↗</span></a>'; }, this).join('') +
      '<button type="button" data-action="logout">' + h(t('Log out', 'लॉग आउट')) + '</button></aside>';
    var heading = this.settings[IAAI.locale === 'hi' ? 'heading_hi' : 'heading'] || title;
    this.root.innerHTML = (publicView ? '' : '<div class="iaai-workspace">' + nav) +
      '<main class="iaai-main"><div class="iaai-page-heading"><span class="iaai-eyebrow">' + h(t('IT’S ALL ABOUT INSURANCE', 'IT’S ALL ABOUT INSURANCE')) + '</span><h1>' + h(heading) + '</h1>' +
      (this.settings.intro ? '<p>' + h(this.settings.intro) + '</p>' : '') + '</div>' + html + '</main>' + (publicView ? '' : '</div>');
  };
  App.prototype.render = async function () {
    try {
      if (this.view === 'auth') return this.auth(secureLink.mode || 'login');
      if (this.view === 'guidance') return this.guidance();
      if (this.view !== 'staff') {
        var session = await api('account/session'); IAAI.nonce = session.nonce;
        if (!session.verified) { this.mount(empty(t('Verify your email', 'ईमेल सत्यापित करें'), t('Please open your confirmation email before adding private records.', 'निजी रिकॉर्ड जोड़ने से पहले ईमेल का लिंक खोलें।')) + '<a class="iaai-button" href="' + h(link('account/?mode=pending')) + '">' + h(t('Verification options', 'सत्यापन विकल्प')) + '</a>', t('Your account', 'आपका अकाउंट')); return; }
        this.session = session;
      }
      await this[this.view]();
    } catch (e) {
      this.mount(empty(t('Sign in to continue', 'जारी रखने के लिए लॉग इन करें'), e.message) + '<a class="iaai-button" href="' + h(IAAI.account) + '">' + h(t('User login', 'यूज़र लॉग इन')) + '</a>', t('Your private portal', 'आपका निजी पोर्टल'));
    }
  };
  App.prototype.auth = function (mode) {
    if (['claim', 'email-change'].includes(mode) && !IAAI.loggedIn) mode = 'login';
    this.mode = mode;
    var title = { login: t('Welcome back.', 'फिर से स्वागत है।'), register: t('Create your account.', 'अपना अकाउंट बनाएं।'), forgot: t('Reset your password.', 'पासवर्ड रीसेट करें।'), pending: t('Check your inbox.', 'अपना ईमेल देखें।'), verify: t('Confirm your email.', 'ईमेल की पुष्टि करें।'), reset: t('Choose a new password.', 'नया पासवर्ड चुनें।'), claim: t('Link your enquiry.', 'अपनी enquiry जोड़ें।'), 'email-change': t('Confirm your new email.', 'नए ईमेल की पुष्टि करें।') }[mode] || t('Your account', 'आपका अकाउंट');
    var fields = '';
    if (mode === 'register' && !IAAI.publicReady) { this.mount(empty(t('Account registration is being prepared', 'अकाउंट रजिस्ट्रेशन की तैयारी जारी है'), t('Reviewed notices and email delivery must be ready before registration opens.', 'रजिस्ट्रेशन शुरू होने से पहले नोटिस की समीक्षा और ईमेल सेवा तैयार होना जरूरी है।')), title); return; }
    if (mode === 'email-change') fields += input('password', t('Current password to confirm', 'पुष्टि के लिए वर्तमान पासवर्ड'), 'password', true);
    if (mode === 'register') fields += input('name', t('Full name', 'पूरा नाम'), 'text', true);
    if (['login', 'register', 'forgot', 'pending'].includes(mode)) fields += input('email', t('Email address', 'ईमेल पता'), 'email', true);
    if (['login', 'register', 'reset'].includes(mode)) fields += input('password', t('Password', 'पासवर्ड'), 'password', true, '', this.mode !== 'login') + button(t('Show password', 'पासवर्ड दिखाएं'), 'show-password', 'text');
    if (['register', 'reset'].includes(mode)) fields += input('confirm_password', t('Confirm password', 'पासवर्ड दोबारा लिखें'), 'password', true, '', true) + '<p class="iaai-hint">' + h(t('Use at least 12 characters. Password managers are welcome.', 'कम से कम 12 अक्षर रखें। पासवर्ड मैनेजर इस्तेमाल कर सकते हैं।')) + '</p>';
    if (mode === 'register') fields += check('account_consent', h(t('I acknowledge the account terms and privacy notice.', 'मैं अकाउंट की शर्तें और गोपनीयता नोटिस स्वीकार करता/करती हूँ।')) + ' <a href="' + h(link(IAAI.locale === 'hi' ? 'hi/privacy/' : 'privacy/')) + '">' + h(t('Read notices', 'नोटिस पढ़ें')) + '</a>') + check('marketing', h(t('Send me optional education updates.', 'मुझे वैकल्पिक जानकारी के अपडेट भेजें।')));
    if (mode === 'login') fields += check('remember', h(t('Remember my basic sign-in', 'सामान्य लॉग इन याद रखें'))) + button(t('Forgot password?', 'पासवर्ड भूल गए?'), 'auth-forgot', 'text');
    if (mode === 'pending') fields += '<p>' + h(t('Reports stay unavailable until verification is complete. If no message arrives, retry delivery.', 'सत्यापन पूरा होने तक रिपोर्ट उपलब्ध नहीं होगी। ईमेल न मिले तो फिर प्रयास करें।')) + '</p>';
    if (['verify', 'claim', 'email-change'].includes(mode)) fields += '<p>' + h(t('Confirm this request only if you initiated it.', 'इस अनुरोध की पुष्टि तभी करें जब आपने इसे शुरू किया हो।')) + '</p>';
    var action = { login: 'Log in', register: 'Create account', forgot: 'Request recovery', pending: 'Resend verification', verify: 'Confirm email', reset: 'Update password', claim: 'Confirm enquiry link', 'email-change': 'Confirm email change' }[mode] || 'Confirm';
    var actionHi = { login: 'लॉग इन', register: 'अकाउंट बनाएं', forgot: 'रिकवरी अनुरोध', pending: 'सत्यापन फिर भेजें', verify: 'ईमेल की पुष्टि', reset: 'पासवर्ड अपडेट', claim: 'Enquiry जोड़ें', 'email-change': 'ईमेल बदलने की पुष्टि' }[mode];
    this.mount('<div class="iaai-auth-card"><div class="iaai-auth-tabs">' + button(t('Log in', 'लॉग इन'), 'auth-login', mode === 'login' ? '' : 'secondary') + button(t('Create account', 'अकाउंट बनाएं'), 'auth-register', mode === 'register' ? '' : 'secondary') + '</div><form data-form="auth">' + fields + submit(t(action, actionHi)) + status() + '</form><p class="iaai-hint">' + h(t('Your medical history is optional. It is never required to create an account.', 'अकाउंट बनाने के लिए मेडिकल हिस्ट्री देना आवश्यक नहीं है।')) + '</p></div>', title);
  };
  App.prototype.guidance = function () {
    var service = this.settings.service || intake.get('service') || 'health';
    var services = [['health', t('Health insurance', 'स्वास्थ्य बीमा')], ['term', t('Term insurance', 'टर्म बीमा')], ['policy_review', t('Policy review', 'पॉलिसी समीक्षा')], ['claim_help', t('Claim help', 'क्लेम सहायता')]];
    var extra = service === 'health' ? input('household_size', t('Household size (optional)', 'परिवार के सदस्य (वैकल्पिक)'), 'number') + input('age_band', t('Broad age bands (optional)', 'आयु वर्ग (वैकल्पिक)')) + input('budget_band', t('Annual budget in INR (optional)', 'सालाना बजट INR में (वैकल्पिक)')) :
      service === 'term' ? input('age_band', t('Age band (optional)', 'आयु वर्ग (वैकल्पिक)')) + input('dependants', t('Number of dependants (optional)', 'आश्रितों की संख्या (वैकल्पिक)'), 'number') :
      service === 'policy_review' ? input('product_type', t('Policy type (optional)', 'पॉलिसी प्रकार (वैकल्पिक)')) :
      select('claim_type', t('Claim issue', 'क्लेम की समस्या'), ['cashless_pending', 'cashless_declined', 'reimbursement_pending', 'reimbursement_declined', 'document_query', 'other']) + select('hospitalized', t('Currently hospitalized?', 'अभी अस्पताल में भर्ती हैं?'), [['prefer_not_to_say', t('Prefer not to say', 'बताना नहीं चाहते')], ['yes', t('Yes', 'हाँ')], ['no', t('No', 'नहीं')]]);
    this.mount('<div class="iaai-panel"><form data-form="guidance"><div class="iaai-form-grid">' + input('name', t('Full name', 'पूरा नाम'), 'text', true) + input('phone', t('Indian mobile number', 'भारतीय मोबाइल नंबर'), 'tel', true) + input('email', t('Email (optional)', 'ईमेल (वैकल्पिक)'), 'email') + select('service', t('How can we help?', 'कैसी सहायता चाहिए?'), services, service) + extra + input('city', t('City (optional)', 'शहर (वैकल्पिक)')) + input('callback', t('Preferred callback window (optional)', 'कॉल का समय (वैकल्पिक)')) + '</div>' + area('message', t('Your main question (optional)', 'मुख्य सवाल (वैकल्पिक)')) + '<p class="iaai-hint">' + h(t('Please do not enter full policy numbers, IDs or detailed medical records.', 'पूरे पॉलिसी नंबर, पहचान पत्र या विस्तृत मेडिकल रिकॉर्ड न लिखें।')) + '</p><input class="iaai-honeypot" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">' + check('contact_consent', h(t('I agree to be contacted about this enquiry.', 'मैं इस enquiry के लिए संपर्क की सहमति देता/देती हूँ।')) + ' <a href="' + h(link(IAAI.locale === 'hi' ? 'hi/privacy/' : 'privacy/')) + '">' + h(t('Privacy notice', 'गोपनीयता नोटिस')) + '</a>') + check('marketing', h(t('Send optional education updates.', 'वैकल्पिक जानकारी के अपडेट भेजें।'))) + submit(t('Send enquiry', 'Enquiry भेजें')) + status() + '</form></div>', t('Let’s find your next step.', 'आपका अगला कदम तय करें।'));
  };
  App.prototype.dashboard = async function () {
    var d = await api('dashboard');
    var metrics = [[t('Your reports', 'आपकी रिपोर्ट'), d.reports], [t('Ready to view', 'देखने के लिए तैयार'), d.available], [t('Treatment entries', 'उपचार विवरण'), d.treatments], [t('Your enquiries', 'आपकी enquiries'), d.enquiries]];
    var cards = metrics.map(function (x) { return '<div class="iaai-metric"><span>' + h(x[0]) + '</span><strong>' + x[1] + '</strong></div>'; }).join('');
    this.mount('<div class="iaai-welcome"><div><h2>' + h(t('Hello, ', 'नमस्ते, ') + d.name) + '</h2><p>' + h(t('Your records. Your choices. Your private space.', 'आपके रिकॉर्ड, आपके विकल्प, आपका निजी स्थान।')) + '</p></div><a class="iaai-button" href="' + h(link('my-reports/')) + '">' + h(t('Upload report', 'रिपोर्ट अपलोड')) + '</a></div><div class="iaai-metrics">' + cards + '</div><div class="iaai-card-grid"><section class="iaai-panel"><h2>' + h(t('Your report library', 'आपकी रिपोर्ट लाइब्रेरी')) + '</h2>' + (d.reports ? '<p>' + h(size(d.usedBytes) + ' / ' + size(d.quotaBytes)) + '</p><a href="' + h(link('my-reports/')) + '">' + h(t('Open your reports →', 'रिपोर्ट खोलें →')) + '</a>' : empty(t('Start with your first report', 'अपनी पहली रिपोर्ट जोड़ें'), t('Keep lab reports, prescriptions and important documents in one private place.', 'लैब रिपोर्ट, प्रिस्क्रिप्शन और दस्तावेज़ निजी स्थान में रखें।'))) + '</section><section class="iaai-panel iaai-tint"><h2>' + h(t('A little context helps.', 'थोड़ी जानकारी उपयोगी है।')) + '</h2><p>' + h(t('Add optional background and self-reported history whenever you are ready.', 'जब चाहें, वैकल्पिक जानकारी और स्वयं दी गई मेडिकल हिस्ट्री जोड़ें।')) + '</p><a href="' + h(link('my-profile/')) + '">' + h(t('Edit profile →', 'प्रोफ़ाइल बदलें →')) + '</a></section></div>', t('Your overview', 'आपका डैशबोर्ड'));
  };
  App.prototype.profile = async function () {
    var p = await api('profile'); this.version = p.version; var d = p.data;
    var basic = ['phone', 'city', 'year_of_birth', 'occupation', 'purpose'].map(function (key) { var labels = { phone: ['Phone (optional)', 'फोन (वैकल्पिक)'], city: ['City', 'शहर'], year_of_birth: ['Year of birth', 'जन्म वर्ष'], occupation: ['Occupation', 'व्यवसाय'], purpose: ['Reason for using the portal', 'पोर्टल इस्तेमाल करने का उद्देश्य'] }; return input(key, t(labels[key][0], labels[key][1]), 'text', false, d[key]); }).join('');
    var med = [['conditions', 'Conditions', 'स्वास्थ्य स्थितियाँ'], ['allergies', 'Allergies', 'एलर्जी'], ['medications', 'Current medication', 'वर्तमान दवाएं'], ['surgeries', 'Surgery / hospitalization', 'सर्जरी / भर्ती विवरण'], ['history', 'Relevant history', 'संबंधित हिस्ट्री']].map(function (x) { return area(x[0], t(x[1], x[2]) + ' — ' + t('user provided, optional', 'स्वयं दी गई जानकारी, वैकल्पिक'), d[x[0]]); }).join('');
    this.mount('<div class="iaai-panel"><p>' + h(t('Every field below is optional. These are your own records, without medical interpretation.', 'हर विवरण वैकल्पिक है। ये आपके स्वयं के रिकॉर्ड हैं; कोई मेडिकल व्याख्या नहीं की जाती।')) + '</p><form data-form="profile"><div class="iaai-form-grid">' + basic + '</div><details class="iaai-medical"><summary>' + h(t('Optional medical history', 'वैकल्पिक मेडिकल हिस्ट्री')) + '</summary>' + med + '</details>' + (!p.storageConsent ? check('storage_consent', h(t('I consent to storing my selected private profile information.', 'मैं अपनी चुनी हुई निजी प्रोफ़ाइल जानकारी के स्टोरेज की सहमति देता/देती हूँ।'))) : '') + submit(t('Save profile', 'प्रोफ़ाइल सहेजें')) + status() + '</form></div>', label('profile'));
  };
  App.prototype.uploadForm = function () {
    return '<form data-form="upload"><div class="iaai-upload-zone"><span aria-hidden="true">↥</span><strong>' + h(t('Choose your reports', 'अपनी रिपोर्ट चुनें')) + '</strong><p>' + h(t('PDF, JPG or PNG · 10 MB each · up to 5 files', 'PDF, JPG या PNG · प्रत्येक 10 MB · अधिकतम 5 फ़ाइलें')) + '</p><input type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png" multiple required aria-label="' + h(t('Choose report files', 'रिपोर्ट फ़ाइलें चुनें')) + '"></div><div class="iaai-form-grid">' + input('title', t('Report title (optional)', 'रिपोर्ट शीर्षक (वैकल्पिक)')) + select('category', t('Category', 'श्रेणी'), categories, 'blood_lab') + input('date', t('Report date', 'रिपोर्ट की तारीख'), 'date') + input('provider', t('Lab / provider (optional)', 'लैब / संस्था (वैकल्पिक)')) + select('replaces', t('Link as a new version (optional)', 'नए version के रूप में जोड़ें (वैकल्पिक)'), [['', t('Independent upload', 'अलग रिपोर्ट')]].concat((this.items || []).map(function(x){return [x.id,x.metadata.title];}))) + '</div>' + area('note', t('Private note (optional)', 'निजी नोट (वैकल्पिक)')) + check('storage_consent', h(t('I consent to private storage and security scanning of these files.', 'मैं इन फ़ाइलों के निजी स्टोरेज और सुरक्षा स्कैन की सहमति देता/देती हूँ।'))) + submit(t('Upload for security scan', 'सुरक्षा स्कैन के लिए अपलोड')) + status() + '</form>';
  };
  App.prototype.upload = async function () {
    var readiness = this.session.readiness; this.items = (await api('reports')).items;
    this.mount('<div class="iaai-panel">' + (readiness.ready ? this.uploadForm() : empty(t('Upload setup is pending', 'अपलोड सेटअप बाकी है'), t('Private storage, scanner and approved retention settings must be ready before files are accepted.', 'फ़ाइलें स्वीकार करने से पहले निजी स्टोरेज, स्कैनर और स्वीकृत रिटेंशन सेटिंग्स तैयार हों।'))) + '</div>', t('Upload a report', 'रिपोर्ट अपलोड करें'));
  };
  App.prototype.reports = async function () {
    var d = await api('reports/search', 'POST', this.filter); this.items = d.items; this.nextOffset = d.nextOffset;
    this.mount('<div class="iaai-panel"><details class="iaai-upload-details"><summary>' + h(t('＋ Add a report', '＋ रिपोर्ट जोड़ें')) + '</summary>' + (this.session.readiness.ready ? this.uploadForm() : '<p>' + h(t('Secure upload configuration is pending.', 'सुरक्षित अपलोड कॉन्फ़िगरेशन बाकी है।')) + '</p>') + '</details></div><form class="iaai-filter" data-form="search">' + input('search', t('Search your titles, provider or notes', 'शीर्षक, संस्था या नोट खोजें'), 'search', false, this.filter.search) + select('category', t('Category', 'श्रेणी'), [['', t('All reports', 'सभी रिपोर्ट')]].concat(categories), this.filter.category) + input('provider', t('Exact lab / provider', 'लैब / संस्था का पूरा नाम'), 'text', false, this.filter.provider) + input('from', t('From date', 'तारीख से'), 'date', false, this.filter.from) + input('to', t('To date', 'तारीख तक'), 'date', false, this.filter.to) + submit(t('Filter', 'फ़िल्टर')) + '</form><div class="iaai-report-list">' + this.reportCards(this.items) + '</div>' + (this.nextOffset != null ? button(t('Load next page', 'अगला पेज'), 'more-reports') : ''), label('reports'));
  };
  App.prototype.reportCards = function (items) {
    if (!items.length) return empty(this.settings.empty_message || t('No reports here yet.', 'अभी कोई रिपोर्ट नहीं।'), t('Add a report or adjust your filters.', 'रिपोर्ट जोड़ें या फ़िल्टर बदलें।'));
    return items.map(function (r) {
      return '<article class="iaai-report"><div class="iaai-file-icon">' + h(r.mime === 'application/pdf' ? 'PDF' : 'IMG') + '</div><div class="iaai-report-info"><h3>' + h(r.metadata.title) + '</h3><p>' + h((r.date || t('No date', 'तारीख नहीं')) + ' · ' + (r.metadata.provider || '') + ' · ' + size(r.size)) + '</p>' + (r.metadata.replaces ? '<p>' + h(t('New version linked to: ', 'नया version संबंधित है: ') + r.metadata.replaces) + '</p>' : '') + '<span class="iaai-badge ' + h(r.status) + '">' + h(stateText(r.status)) + '</span></div><div class="iaai-actions" data-report="' + h(r.id) + '">' + (r.status === 'available' ? button(t('Preview', 'प्रीव्यू'), 'preview') + button(t('Download', 'डाउनलोड'), 'download') : '') + button(t('Edit', 'बदलें'), 'edit-report', 'text') + button(t('Delete', 'हटाएं'), 'delete-report', 'text danger') + '</div></article>';
    }).join('');
  };
  App.prototype.treatment = async function () {
    this.entries = await api('treatments');
    var options = ['consultation', 'test', 'medication_record', 'procedure', 'hospitalization'];
    var reports = (await api('reports')).items;
    this.mount('<div class="iaai-panel"><h2>' + h(t('Add a timeline entry', 'उपचार विवरण जोड़ें')) + '</h2><form data-form="treatment"><div class="iaai-form-grid">' + input('title', t('Entry title', 'शीर्षक'), 'text', true) + input('date', t('Date', 'तारीख'), 'date', true) + select('type', t('Entry type', 'विवरण प्रकार'), options) + input('provider', t('Provider (optional)', 'संस्था (वैकल्पिक)')) + '</div>' + area('note', t('Your note (optional)', 'आपका नोट (वैकल्पिक)')) + '<fieldset><legend>' + h(t('Link your reports (optional)', 'अपनी रिपोर्ट जोड़ें (वैकल्पिक)')) + '</legend>' + reports.map(function (x) { return check('report:' + x.id, h(x.metadata.title)); }).join('') + '</fieldset>' + submit(t('Add entry', 'विवरण जोड़ें')) + status() + '</form></div><div class="iaai-timeline">' + (this.entries.length ? this.entries.map(function (e) { return '<article class="iaai-panel" data-entry="' + h(e.id) + '"><span class="iaai-eyebrow">' + h(e.date) + '</span><h3>' + h(e.data.title) + '</h3><p>' + h(e.data.note || '') + '</p>' + button(t('Edit', 'बदलें'), 'edit-entry', 'text') + button(t('Delete', 'हटाएं'), 'delete-entry', 'text danger') + '</article>'; }).join('') : empty(t('Your timeline starts here.', 'आपका उपचार विवरण यहाँ शुरू होगा।'), t('Keep a user-maintained history, with optional links to your own reports.', 'स्वयं दिया गया विवरण और अपनी रिपोर्ट के लिंक रखें।'))) + '</div>', label('treatment'));
  };
  App.prototype.enquiries = async function () {
    var rows = await api('enquiries/mine');
    this.mount('<div class="iaai-panel"><p>' + h(t('See your submitted enquiries and safe status updates. Internal team notes stay private.', 'अपनी enquiry और स्टेटस देखें। टीम के अंदरूनी नोट निजी रहते हैं।')) + '</p><a class="iaai-button" href="' + h(link('contact/')) + '">' + h(t('Request guidance', 'मार्गदर्शन लें')) + '</a></div>' + rows.map(function (e) { return '<article class="iaai-panel"><span class="iaai-eyebrow">' + h(e.reference) + '</span><h3>' + h(stateText(e.service)) + '</h3><p>' + h(stateText(e.status)) + '</p>' + (e.staff ? '<p>' + h(t('Assigned to: ', 'जिम्मेदार सदस्य: ') + e.staff.name) + '</p>' : '') + '</article>'; }).join('') + (rows.length ? '' : empty(t('No enquiries yet.', 'अभी कोई enquiry नहीं।'), t('Your submitted enquiries will appear here.', 'भेजी गई enquiries यहाँ दिखेंगी।'))) + '<div class="iaai-panel"><h2>' + h(t('Share selected records', 'चुने हुए रिकॉर्ड शेयर करें')) + '</h2><div data-iaai-sharing-slot></div></div><div class="iaai-panel"><h2>' + h(t('Link a guest enquiry', 'पुरानी guest enquiry जोड़ें')) + '</h2><form data-form="claim-request">' + input('reference', t('Exact enquiry reference', 'सही enquiry reference'), 'text', true) + submit(t('Send verification to saved contact', 'सेव किए गए संपर्क पर सत्यापन भेजें')) + status() + '</form></div>', label('enquiries'));
    var slot = this.root.querySelector('[data-iaai-sharing-slot]'); await this.sharing(slot);
  };
  App.prototype.sharing = async function (slot) {
    var entries = await api('enquiries/mine'); var reports = (await api('reports')).items.filter(function (r) { return r.status === 'available'; }); var grants = await api('sharing');
    var eligible = entries.filter(function (e) { return e.staff && e.status !== 'closed'; });
    var html = eligible.length ? '<form data-form="sharing">' + select('enquiry', t('Enquiry and named recipient', 'Enquiry और जिम्मेदार सदस्य'), eligible.map(function (e) { return [e.reference, e.reference + ' — ' + e.staff.name]; })) + '<fieldset><legend>' + h(t('Select only the reports you want to share', 'केवल शेयर करने वाली रिपोर्ट चुनें')) + '</legend>' + reports.map(function (r) { return check('report:' + r.id, h(r.metadata.title)); }).join('') + '</fieldset>' + check('section:background', h(t('Share a snapshot of background information', 'पृष्ठभूमि जानकारी का snapshot शेयर करें'))) + check('section:medical', h(t('Share a snapshot of medical profile', 'मेडिकल प्रोफ़ाइल का snapshot शेयर करें'))) + select('days', t('Access expires after', 'एक्सेस की अवधि'), [['1', t('1 day', '1 दिन')], ['7', t('7 days', '7 दिन')], ['30', t('30 days', '30 दिन')]], '7') + check('consent', h(t('I authorize this named recipient to read the selected records for this enquiry only.', 'मैं चुने हुए सदस्य को केवल इस enquiry के लिए चुने गए रिकॉर्ड पढ़ने की सहमति देता/देती हूँ।'))) + submit(t('Grant selected access', 'चुने गए रिकॉर्ड का एक्सेस दें')) + status() + '</form>' : '<p>' + h(t('Sharing becomes available when your enquiry is assigned to identified staff.', 'Enquiry किसी नामित सदस्य को सौंपे जाने के बाद sharing उपलब्ध होगी।')) + '</p>';
    html += '<h3>' + h(t('Access history', 'एक्सेस हिस्ट्री')) + '</h3>' + grants.map(function (g) { return '<div class="iaai-grant" data-grant="' + h(g.id) + '"><div><strong>' + h(g.recipient) + '</strong><p>' + h(g.enquiry + ' · ' + g.expires) + '</p></div>' + (g.revoked ? '<span>' + h(t('Revoked', 'एक्सेस बंद')) + '</span>' : button(t('Revoke', 'एक्सेस बंद करें'), 'revoke', 'secondary danger')) + '</div>'; }).join('');
    var support = await api('sharing/support-history');
    html += '<h3>' + h(t('Limited support access', 'सीमित सहायता एक्सेस')) + '</h3>' + support.map(function (x) { return '<div class="iaai-grant" data-support="' + h(x.id) + '"><span>' + h(x.issue_ref + ' · ' + x.expires_at) + '</span>' + (x.revoked_at ? h(t('Revoked', 'एक्सेस बंद')) : button(t('Revoke support access', 'सहायता एक्सेस बंद करें'), 'revoke-support', 'secondary danger')) + '</div>'; }).join('');
    var reads = await api('sharing/history');
    html += '<h3>' + h(t('Record access log', 'रिकॉर्ड एक्सेस का विवरण')) + '</h3>' + reads.map(function (x) { return '<p>' + h(x.action + ' · ' + x.created_at + ' · ' + t('Staff ID ', 'सदस्य ID ') + x.actor_id) + '</p>'; }).join('');
    this.eligible = eligible;
    if (slot) slot.innerHTML = html; else this.mount('<div class="iaai-panel">' + html + '</div>', t('Record sharing', 'रिकॉर्ड शेयरिंग'));
  };
  App.prototype.settings = async function () {
    var requests = await api('privacy/requests');
    this.mount('<div class="iaai-panel"><h2>' + h(t('Confirm sensitive actions', 'संवेदनशील बदलाव की पुष्टि')) + '</h2><form data-form="reauth">' + input('password', t('Current password', 'वर्तमान पासवर्ड'), 'password', true) + submit(t('Confirm identity', 'पहचान की पुष्टि')) + status() + '</form></div><div class="iaai-card-grid"><section class="iaai-panel"><h2>' + h(t('Change password', 'पासवर्ड बदलें')) + '</h2><form data-form="password">' + input('password', t('New password', 'नया पासवर्ड'), 'password', true, '', true) + input('confirm_password', t('Confirm new password', 'नया पासवर्ड दोबारा'), 'password', true, '', true) + submit(t('Update password', 'पासवर्ड अपडेट')) + status() + '</form></section><section class="iaai-panel"><h2>' + h(t('Change email', 'ईमेल बदलें')) + '</h2><form data-form="email">' + input('email', t('New email address', 'नया ईमेल पता'), 'email', true) + submit(t('Send confirmation', 'पुष्टि भेजें')) + status() + '</form></section></div><div class="iaai-panel"><h2>' + h(t('Sessions and privacy', 'सेशन और गोपनीयता')) + '</h2><div class="iaai-actions">' + button(t('Log out other sessions', 'अन्य सेशन बंद करें'), 'sessions') + button(t('Request private export', 'निजी एक्सपोर्ट मांगें'), 'export') + button(t('Withdraw storage consent', 'स्टोरेज सहमति वापस लें'), 'withdraw-storage', 'secondary danger') + '</div><div class="iaai-status" role="status"></div>' + requests.map(function (x) { return '<div class="iaai-grant" data-export="' + h(x.id) + '"><span>' + h(stateText(x.type) + ' · ' + stateText(x.state)) + '</span>' + (x.type === 'export' && x.state === 'ready' ? button(t('Download private archive', 'निजी archive डाउनलोड'), 'export-download') : '') + '</div>'; }).join('') + '</div><div class="iaai-panel iaai-danger-panel"><h2>' + h(t('Request account deletion', 'अकाउंट हटाने का अनुरोध')) + '</h2><p>' + h(t('Access stops immediately. Online files are purged by the configured worker; backup copies follow the approved retention policy. Downloaded copies cannot be recalled.', 'एक्सेस तुरंत बंद होगा। ऑनलाइन फ़ाइलें worker हटाएगा; backup स्वीकृत रिटेंशन नीति के अनुसार हटेंगे। डाउनलोड की गई कॉपी वापस नहीं ली जा सकती।')) + '</p><form data-form="delete-account">' + input('confirm', t('Type DELETE to confirm', 'पुष्टि के लिए DELETE लिखें'), 'text', true) + submit(t('Request deletion', 'हटाने का अनुरोध')) + status() + '</form></div>', label('settings'));
  };
  App.prototype.staff = async function () {
    var filter = this.leadFilter || {}, page = this.leadPage || 1;
    this.leads = await api('staff/enquiries?' + new URLSearchParams(Object.assign({}, filter, {page: page})));
    var filters = '<form data-form="lead-filters"><div class="iaai-form-grid">' + select('service', 'Service', [['','All services']].concat(services), filter.service) + select('status', 'Status', [['','All statuses']].concat(['new','assigned','contact_attempted','connected','guidance_in_progress','closed']), filter.status) + select('assignment', 'Assignment', [['','All accessible'],['unassigned','Unassigned']], filter.assignment) + select('followup', 'Follow-up', [['','All'],['overdue','Overdue']], filter.followup) + '</div>' + submit('Apply filters') + status() + '</form>';
    this.mount('<div class="iaai-panel">' + filters + '<p>Page ' + h(page) + ' · Up to 100 enquiries per page</p><div class="iaai-actions">' + (page > 1 ? button('Previous page','previous-leads') : '') + (this.leads.length === 100 ? button('Next page','next-leads') : '') + (IAAI.canExportLeads ? button('Export safe fields for this page','export-leads') : '') + '</div><div class="iaai-status" role="status"></div><div class="iaai-table-wrap"><table><thead><tr><th>Reference</th><th>Service</th><th>Status</th><th>Assignment</th><th>Contact</th><th>Follow-up (UTC)</th><th></th></tr></thead><tbody>' + this.leads.map(function (x) { return '<tr data-lead="' + h(x.reference) + '"><td>' + h(x.reference) + '</td><td>' + h(x.service) + '</td><td>' + h(x.status) + '</td><td>' + h(x.assignee || 'Unassigned') + '</td><td>' + h(x.contact) + '</td><td>' + h(x.followup || '—') + (x.followup && x.status !== 'closed' && Date.parse(x.followup.replace(' ','T') + 'Z') < Date.now() ? ' · Overdue' : '') + '</td><td>' + button('Open', 'lead') + '</td></tr>'; }).join('') + '</tbody></table></div></div>', 'Enquiry workspace');
  };
  App.prototype.onSubmit = async function (event) {
    var f = event.target.closest('form[data-form]'); if (!f) return;
    event.preventDefault(); var kind = f.dataset.form; var p = formData(f); var output = f.querySelector('.iaai-status'); var btn = f.querySelector('[type=submit]'); if (btn) btn.disabled = true;
    try {
      var d;
      if (kind === 'auth') {
        var path = { login: 'login', register: 'register', forgot: 'forgot-password', pending: 'verification/request', verify: 'verification/complete', reset: 'reset-password', claim: 'claim-complete', 'email-change': '../account/email-change/complete' }[this.mode];
        p.token = secureLink.token; p.key = secureLink.key; p.login = secureLink.login;
        d = await api(path.startsWith('../') ? path.slice(3) : 'auth/' + path, 'POST', p);
        if (d.redirect) {
          if (['claim', 'email-change'].includes(secureLink.mode) && secureLink.token) { var destination = new URL(IAAI.account); destination.searchParams.set('mode', secureLink.mode); destination.searchParams.set('token', secureLink.token); location.assign(destination.href); }
          else location.assign(d.redirect);
          return;
        }
      } else if (kind === 'guidance') {
        f.dataset.requestKey = f.dataset.requestKey || crypto.randomUUID();
        d = await api('enquiries', 'POST', p, { 'Idempotency-Key': f.dataset.requestKey });
        output.textContent = t('Your enquiry has been saved. Reference: ', 'Enquiry सहेज ली गई है। Reference: ') + d.reference;
        f.reset(); delete f.dataset.requestKey; return;
      } else if (kind === 'profile') { p.version = this.version; d = await api('profile', 'PATCH', p); this.version = d.version; }
      else if (kind === 'upload') {
        var fd = new FormData(f); var files = fd.getAll('files[]');
        if (files.length > 5 || files.some(function (x) { return x.size > 10000000; })) throw new Error(t('Maximum five files, 10 MB each.', 'अधिकतम पाँच फ़ाइलें, प्रत्येक 10 MB।'));
        f.dataset.requestKey = f.dataset.requestKey || crypto.randomUUID();
        d = await api('reports/upload', 'POST', fd, { 'Idempotency-Key': f.dataset.requestKey });
        output.textContent = t('Saved in quarantine. Reports become viewable only after security checks pass.', 'Quarantine में सहेजा गया। सुरक्षा जाँच के बाद ही रिपोर्ट खुलेगी।');
        f.reset(); delete f.dataset.requestKey; return;
      } else if (kind === 'search') { this.filter = p; await this.reports(); return; }
      else if (kind === 'treatment') {
        p.reports = Array.from(f.querySelectorAll('input[name^="report:"]:checked')).map(function (x) { return x.name.slice(7); });
        d = await api('treatments', 'POST', p); await this.treatment(); return;
      } else if (kind === 'edit-entry') {
        p.reports = this.editEntry.reports; p.version = this.editEntry.version;
        d = await api('treatments/' + this.editEntry.id, 'PATCH', p); closeDialog(); await this.treatment(); return;
      } else if (kind === 'sharing') {
        var e = this.eligible.find(function (x) { return x.reference === p.enquiry; });
        p.recipient_id = e.staff.id;
        p.reports = Array.from(f.querySelectorAll('input[name^="report:"]:checked')).map(function (x) { return x.name.slice(7); });
        p.sections = Array.from(f.querySelectorAll('input[name^="section:"]:checked')).map(function (x) { return x.name.slice(8); });
        d = await api('sharing', 'POST', p); await this.render(); return;
      } else if (kind === 'edit-report') {
        p.version = this.editReport.version; d = await api('reports/' + this.editReport.id, 'PATCH', p); closeDialog(); await this.reports(); return;
      } else if (kind === 'claim-request') { d = await api('auth/claim-request', 'POST', p); }
      else if (kind === 'reauth') { d = await api('account/reauth', 'POST', p); }
      else if (kind === 'password' || kind === 'email') { p.action = kind; d = await api('account/settings', 'POST', p); if (d.logout) { location.assign(IAAI.account); return; } }
      else if (kind === 'delete-account') { d = await api('privacy/delete-account', 'POST', p); location.assign(IAAI.account); return; }
      else if (kind === 'lead-filters') { this.leadFilter = p; this.leadPage = 1; await this.staff(); return; }
      else if (kind === 'lead-status') { p.version = this.currentLead.version; d = await api('staff/enquiries/' + this.currentLead.public_ref, 'PATCH', p); closeDialog(); await this.staff(); return; }
      else if (kind === 'lead-assign') { d = await api('staff/enquiries/' + this.currentLead.public_ref + '/assign', 'POST', p); closeDialog(); await this.staff(); return; }
      else if (kind === 'lead-note') { d = await api('staff/enquiries/' + this.currentLead.public_ref + '/notes', 'POST', p); }
      if (output) output.textContent = d.message || t('Saved successfully.', 'सहेज लिया गया।');
    } catch (e) { if (output) { output.setAttribute('role', 'alert'); output.textContent = e.message; } }
    finally { if (btn) btn.disabled = false; }
  };
  App.prototype.onClick = async function (event) {
    var b = event.target.closest('[data-action]'); if (!b) return; var action = b.dataset.action;
    if (action.startsWith('auth-')) return this.auth(action.slice(5));
    if (action === 'show-password') { this.root.querySelectorAll('input[name=password],input[name=confirm_password]').forEach(function (i) { i.type = i.type === 'password' ? 'text' : 'password'; }); return; }
    var output = this.root.querySelector('.iaai-status');
    try {
      if (action === 'revoke-support') { await api('sharing/support-access/' + event.target.closest('[data-support]').dataset.support, 'DELETE'); await this.render(); }
      if (action === 'previous-leads' || action === 'next-leads') { this.leadPage = Math.max(1, (this.leadPage || 1) + (action === 'next-leads' ? 1 : -1)); await this.staff(); }
      if (action === 'export-leads') {
        var exported = await api('staff/enquiries/export','POST',Object.assign({},this.leadFilter || {},{page:this.leadPage || 1}));
        var fields = ['reference','service','status','language','assignee','followup','created_at'];
        var csv = [fields.join(',')].concat(exported.items.map(function(row){return fields.map(function(field){return '"' + String(row[field] == null ? '' : row[field]).replace(/"/g,'""') + '"';}).join(',');})).join('\r\n');
        var exportUrl = URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));var a = document.createElement('a');a.href=exportUrl;a.download='iaai-enquiries-page-'+exported.page+'.csv';a.click();setTimeout(function(){URL.revokeObjectURL(exportUrl);},1000);
      }
      if (action === 'logout') { await api('auth/logout', 'POST', {}); clearBlobs(); location.assign(IAAI.home); return; }
      if (action === 'more-reports') { this.filter.offset = this.nextOffset; await this.reports(); return; }
      if (action === 'sessions' || action === 'withdraw-storage') { if (action === 'withdraw-storage' && !confirm(t('Stop new storage and revoke active sharing?', 'नया स्टोरेज और सक्रिय sharing बंद करें?'))) return; var d = await api('account/settings', 'POST', { action: action === 'sessions' ? 'sessions' : 'withdraw_storage' }); if (output) output.textContent = d.message || t('Updated.', 'अपडेट किया गया।'); }
      if (action === 'export') { await api('privacy/export', 'POST', {}); await this.settings(); }
      if (action === 'export-download') { await download('privacy/exports/' + b.closest('[data-export]').dataset.export + '/content', false); }
      if (action === 'revoke') { await api('sharing/' + b.closest('[data-grant]').dataset.grant, 'DELETE', {}); await this.render(); }
      var report = b.closest('[data-report]');
      if (report) {
        var id = report.dataset.report;
        if (action === 'delete-report' && confirm(t('Delete this report and revoke access?', 'यह रिपोर्ट हटाएं और एक्सेस बंद करें?'))) { await api('reports/' + id, 'DELETE', {}); await this.reports(); }
        if (action === 'download') await download('reports/' + id + '/content', false);
        if (action === 'preview') {
          var info = await api('reports/' + id); showDialog('<h2>' + h(info.metadata.title) + '</h2><div id="iaai-preview-pages"></div>', this);
          var pages = document.getElementById('iaai-preview-pages');
          for (var i = 0; i < info.previewPages; i++) { var url = await download('reports/' + id + '/content?preview=1&page=' + i, true); var image = document.createElement('img'); image.src = url; image.alt = t('Report page ', 'रिपोर्ट पेज ') + (i + 1); pages.appendChild(image); }
        }
        if (action === 'edit-report') { var r = await api('reports/' + id); this.editReport = r; showDialog('<h2>' + h(t('Edit report details', 'रिपोर्ट विवरण बदलें')) + '</h2><form data-form="edit-report">' + input('title', t('Title', 'शीर्षक'), 'text', true, r.metadata.title) + select('category', t('Category', 'श्रेणी'), categories, r.category) + input('date', t('Date', 'तारीख'), 'date', false, r.date) + input('provider', t('Provider', 'संस्था'), 'text', false, r.metadata.provider) + area('note', t('Private note', 'निजी नोट'), r.metadata.note) + submit(t('Save', 'सहेजें')) + status() + '</form>', this); }
      }
      var entry = b.closest('[data-entry]');
      if (entry) {
        if (action === 'delete-entry' && confirm(t('Delete this timeline entry?', 'यह उपचार विवरण हटाएं?'))) { await api('treatments/' + entry.dataset.entry, 'DELETE', {}); await this.treatment(); }
        if (action === 'edit-entry') { this.editEntry = this.entries.find(function (x) { return x.id === entry.dataset.entry; }); var e = this.editEntry; showDialog('<h2>' + h(t('Edit timeline entry', 'उपचार विवरण बदलें')) + '</h2><form data-form="edit-entry">' + input('title', t('Title', 'शीर्षक'), 'text', true, e.data.title) + input('date', t('Date', 'तारीख'), 'date', true, e.date) + select('type', t('Type', 'प्रकार'), ['consultation', 'test', 'medication_record', 'procedure', 'hospitalization'], e.type) + input('provider', t('Provider', 'संस्था'), 'text', false, e.data.provider) + area('note', t('Note', 'नोट'), e.data.note) + submit(t('Save', 'सहेजें')) + status() + '</form>', this); }
      }
      if (action === 'lead') {
        this.currentLead = await api('staff/enquiries/' + b.closest('[data-lead]').dataset.lead);
        var lead = this.currentLead;
        var shared = await api('staff/enquiries/' + lead.public_ref + '/shared-records');
        showDialog('<h2>' + h(lead.public_ref) + '</h2><p>' + h(lead.details.name + ' · ' + lead.details.phone) + '</p><p>' + h(lead.details.message) + '</p><form data-form="lead-status">' + select('status', 'Status', ['new', 'assigned', 'contact_attempted', 'connected', 'guidance_in_progress', 'closed'], lead.status) + select('close_reason', 'Close reason', ['guidance_completed', 'unable_to_reach', 'duplicate', 'withdrawn', 'out_of_scope']) + input('followup', 'Follow-up UTC (YYYY-MM-DD HH:MM:SS)', 'text', false, lead.followup_at) + submit('Save status') + status() + '</form><form data-form="lead-assign">' + input('staff_id', 'Assigned staff WordPress ID', 'number', true) + submit('Assign') + status() + '</form><form data-form="lead-note">' + area('note', 'Internal note') + submit('Add note') + status() + '</form><h3>Explicitly shared records</h3>' + shared.reports.map(function (x) { return '<div class="iaai-grant" data-report="' + h(x.id) + '"><strong>' + h(x.metadata.title) + '</strong>' + button('Download', 'download') + '</div>'; }).join('') + '<h3>Shared profile snapshots</h3>' + shared.sections.map(function (x) { return '<pre>' + h(JSON.stringify(x.snapshot.fields, null, 2)) + '</pre>'; }).join(''), this);
      }
    } catch (e) { if (output) output.textContent = e.message; else showDialog('<p role="alert">' + h(e.message) + '</p>', this); }
  };
  var activeDialog, returnFocus;
  function clearBlobs() { blobUrls.forEach(function (u) { URL.revokeObjectURL(u); }); blobUrls.clear(); }
  function closeDialog() { if (activeDialog) { activeDialog.close(); activeDialog.remove(); activeDialog = null; } clearBlobs(); if (returnFocus && returnFocus.isConnected) returnFocus.focus(); }
  function showDialog(html, app) {
    closeDialog(); returnFocus = document.activeElement;
    var d = document.createElement('dialog'); d.className = 'iaai-dialog'; d.setAttribute('aria-label', t('Account or record dialog', 'अकाउंट या रिकॉर्ड संवाद'));
    d.innerHTML = '<button type="button" class="iaai-dialog-close" aria-label="' + h(t('Close', 'बंद करें')) + '">×</button><div class="iaai-dialog-body">' + html + '</div>';
    document.body.appendChild(d); activeDialog = d;
    d.querySelector('.iaai-dialog-close').onclick = closeDialog;
    d.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(); });
    if (app) { d.addEventListener('submit', app.onSubmit.bind(app)); d.addEventListener('click', app.onClick.bind(app)); }
    d.showModal();
  }
  async function download(path, preview) {
    var r = await fetch(IAAI.api + path, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': IAAI.nonce } });
    if (!r.ok) { var error = await r.json().catch(function () { return {}; }); throw new Error(error.message || t('File is unavailable.', 'फ़ाइल उपलब्ध नहीं है।')); }
    var url = URL.createObjectURL(await r.blob()); blobUrls.add(url);
    if (preview) return url;
    var a = document.createElement('a'); a.href = url; a.download = ((r.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'document';
    a.click(); URL.revokeObjectURL(url); blobUrls.delete(url);
  }
  document.querySelectorAll('[data-iaai-view]').forEach(function (root) { new App(root); });
  document.addEventListener('click', function (e) {
    var auth = e.target.closest('[data-iaai-auth]');
    if (auth) { e.preventDefault(); showDialog('<div class="iaai-app" data-iaai-view="auth" data-iaai-settings="{}"></div>'); new App(activeDialog.querySelector('.iaai-app')); }
    var locale = e.target.closest('[data-iaai-locale]');
    if (locale) {
      var choice = locale.dataset.iaaiLocale;
      document.cookie = 'iaai_locale=' + choice + '; Path=/; Max-Age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
      if (IAAI.loggedIn) api('account/settings', 'POST', { action: 'locale', locale: choice }).then(function () { location.assign(locale.href); }).catch(function () { location.assign(locale.href); });
      else location.assign(locale.href);
      e.preventDefault();
    }
  });
  window.addEventListener('pagehide', clearBlobs);
})();

(function () {
  'use strict';
  function calculate(kind, v) {
    if (v.some(x => !Number.isFinite(x) || x < 0)) throw Error('Enter valid non-negative numbers.');
    if (kind === 'term') return Math.max(0, v[0] * v[1] + v[2] + v[3] - v[4]);
    if (kind === 'salary') {
      if (v[1] + v[2] + v[3] > v[0]) throw Error('Deductions exceed monthly gross salary.');
      return v[0] - v[1] - v[2] - v[3];
    }
    if (kind === 'ulip') {
      if (v[1] > 40 || v[1] < 1 || !Number.isInteger(v[1]) || v[2] > 30 || v[3] > 30) throw Error('Use 1–40 whole years and rates from 0–30%.');
      let total = 0, rate = (v[2] - v[3]) / 100;
      for (let year = 0; year < v[1]; year++) total = (total + v[0]) * (1 + rate);
      return total;
    }
    if (kind === 'tax') {
      if (v[0] > 5000000) throw Error('This simplified calculator supports taxable income up to ₹50 lakh.');
      let tax = 0, previous = 0;
      [[400000,0],[800000,.05],[1200000,.1],[1600000,.15],[2000000,.2],[2400000,.25],[5000000,.3]].forEach(([limit,rate]) => { tax += Math.max(0,Math.min(v[0],limit)-previous)*rate; previous=limit; });
      if (v[0] <= 1200000) tax = 0;
      else tax = Math.min(tax, v[0] - 1200000);
      return Math.round(tax * 1.04 / 10) * 10;
    }
    throw Error('Unknown calculator.');
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = calculate;
  if (typeof document === 'undefined') return;
  document.addEventListener('submit', function (event) {
    const form=event.target.closest('[data-iaai-calculator]'); if (!form) return;
    event.preventDefault(); const out=form.querySelector('output');
    try { const result=calculate(form.dataset.iaaiCalculator,Array.from(form.querySelectorAll('input')).map(x=>Number(x.value))); out.textContent=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:0}).format(result); }
    catch (error) { out.textContent=error.message; }
  });
})();
