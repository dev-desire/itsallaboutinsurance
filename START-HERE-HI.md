# Hostinger पर वेबसाइट install करने की guide

**Domain:** https://itsallaboutinsurance.com/  
**Release:** 0.1.0

Package तैयार है। Live domain पर installation अभी नहीं हुई है: उपलब्ध cloud browser में Hostinger sign-in restricted था। यह guide WordPress site के authorized maintainer के लिए है।

## 1. पहले staging installation तैयार करें

मौजूदा वेबसाइट पर code लगाने से पहले उसका backup लें और अलग staging WordPress installation इस्तेमाल करें। Package में user database या medical files नहीं हैं। Tested versions `dependencies.lock.json` में हैं। PHP 8.3 और HTTPS चाहिए।

WordPress में Elementor Free और Two-Factor install करें। Code Snippets केवल वैकल्पिक snippet mode के लिए चाहिए। कोई अज्ञात theme/plugin overwrite न करें।

## 2. Plugin और theme upload करें

ZIP खोलें। WordPress → Plugins → Add New → Upload Plugin में **dist/iaai-portal.zip** upload करके activate करें। Appearance → Themes → Add New → Upload Theme में **dist/iaai-base.zip** upload करें और IAAI Base activate करें।

Staff/admin को WordPress के अपने Profile पेज पर authenticator/TOTP और backup codes configure करने होंगे। फिर protected sign-in session लें। Plugin privileged dashboard को MFA के बिना खोलने नहीं देता।

## 3. सभी अलग Elementor pages बनाएँ

इनमें से **एक** तरीका इस्तेमाल करें:

- **आसान तरीका:** WordPress में IAAI Portal → Setup → “Install / reconcile separate Elementor pages” दबाएँ।
- **पूरा kit:** बिल्कुल नई installation पर Elementor के native kit importer से **elementor/iaai-website-kit.zip** import करें। Matching IAAI plugin पहले active होना चाहिए। Existing managed pages वाले site पर पूरा kit दोबारा import नहीं होगा।
- **WP-CLI:** `wp iaai install-pages --release=0.1.0`

53 templates बनेंगे: 49 Pages और 4 global header/footer sections। किसी page की wording, headings, FAQs, spacing और colors “Edit with Elementor” से बदल सकते हैं। Portal forms के backend permissions plugin में रहते हैं। Global header/footer का content Elementor → Saved Templates में है और theme उसे render करती है।

## 4. वास्तविक contact और notices जोड़ें

IAAI Portal → Setup में वास्तविक phone, WhatsApp, contact email, staff notification email, privacy contact, legal identity, service role, hours, fees और approved retention policy भरें। Package में कोई अनुमानित नंबर, testimonial या authorization नहीं डाली गई है।

English और Hindi की Privacy, Terms और Disclosures draft pages को वास्तविक business विवरण के अनुसार review करके publish करें। फिर Setup में exact published notices की approval save करें। बाद में notices बदलने पर collection gate दोबारा review माँगेगा। Guides और case pages review के बिना draft रहेंगी; payment/result का दावा evidence के बिना publish न करें।

## 5. Email और private reports की hosting configuration

वास्तविक mail provider configure करें और test inbox में verification तथा password-reset messages पहुँचने की जाँच करें। उसके बाद ही Setup में mail verification confirm करें। Enquiry DB में save होने और email भेजे जाने की स्थिति अलग है; failed email से saved enquiry नहीं मिटती।

Reports के लिए **docs/HOST-CONFIGURATION.md** लागू करें। इसमें outside-webroot storage, encryption key, ClamAV, qpdf, pdftoppm और scheduler चाहिए। यदि hosting plan आवश्यक tools नहीं देता, uploads बंद रहेंगे; compatible private worker/hosting capacity चाहिए। केवल checkboxes बदलकर missing infrastructure तैयार नहीं होती।

Real records से पहले restore drill करें और approved retention/deletion procedure लिखें। Private files WordPress Media Library में upload न करें।

## 6. GitHub automatic deployment जोड़ें

Repository: https://github.com/dev-desire/itsallaboutinsurance (owner ने Public बनाई है)। `main`/`develop` branches इस्तेमाल करें। Hosting setup और initial installation के बाद repository Actions variable `DEPLOY_ENABLED=true` करें। Workflow, environment variables और SSH secrets की सूची **docs/DEPLOYMENT.md** में है। Secrets repository code या chat में न रखें।

Main production और develop staging के लिए हैं। दोनों sites के database, private storage, keys और deploy paths अलग होने चाहिए। पहले staging पर real SSH deployment, rollback, mail, cache और browser acceptance जाँचें।

## 7. Go-live से पहले बाकी जाँच

**docs/VERIFICATION.md** में 30 acceptance criteria की वास्तविक स्थिति है। 91 functional/security checks plugin mode और 91 snippet mode में पास हैं। Native kit import, snippet conflict handling, targeted page rollback और local HTTP checks भी पास हैं। ये checks live Hostinger deployment, mail delivery, actual antivirus या mobile/screen-reader review का विकल्प नहीं हैं।
