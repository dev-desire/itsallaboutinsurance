#!/usr/bin/env python3
"""Regenerate bilingual glass layouts using native, editable Elementor widgets.

Run this AFTER generate-templates.py if rebuilding all canonical templates.
No HTML blob replaces the visual layout; calculators/posts use focused widgets.
"""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ASSET = 'https://itsallaboutinsurance.com/wp-content/plugins/iaai-portal/assets/'
counter = 0

def element(kind, settings, children=None):
    global counter
    counter += 1
    return {'id': f'b{counter:06x}', 'elType': 'container' if kind == 'container' else 'widget',
            **({} if kind == 'container' else {'widgetType': kind}),
            'settings': settings, 'elements': children or []}

def heading(title, size=38, tag='h2'):
    return element('heading', {'title': title, 'header_size': tag, 'title_color': '#291347',
        'typography_typography': 'custom', 'typography_font_size': {'size': size, 'unit': 'px'},
        'typography_font_size_mobile': {'size': min(size, 32), 'unit': 'px'}})

def text(body):
    return element('text-editor', {'editor': body, 'text_color': '#62536f'})

def button(label, link, secondary=False):
    return element('button', {'text': label, 'link': {'url': link},
        'background_color': '#ffffff' if secondary else '#7136cb',
        'button_text_color': '#51218f' if secondary else '#ffffff',
        'border_radius': {'top': 14, 'right': 14, 'bottom': 14, 'left': 14, 'unit': 'px'},
        'text_padding': {'top': 15, 'right': 23, 'bottom': 15, 'left': 23, 'unit': 'px'}})

def photo(name, link=None):
    return element('image', {'image': {'url': ASSET + 'images/' + name + '.jpg', 'id': 0},
        'image_size': 'full', 'link_to': 'custom' if link else 'none',
        'link': {'url': link or ''}, '_css_classes': 'iaai-editor-photo',
        'width': {'size': 100, 'unit': '%'}, 'border_radius': {'top': 22, 'right': 22, 'bottom': 22, 'left': 22, 'unit': 'px'}})

def box(children, row=False, cls='iaai-glass', color='#ffffffb3'):
    if row:
        for child in children:
            child['settings'].update(width={'size': round(100 / len(children) - 2, 2), 'unit': '%'},
                width_mobile={'size': 100, 'unit': '%'}, flex_grow=1)
    return element('container', {'content_width': 'full', 'flex_direction': 'row' if row else 'column',
        'flex_direction_mobile': 'column', 'flex_wrap': 'wrap', 'flex_align_items': 'stretch',
        'gap': {'size': 24, 'unit': 'px'}, 'padding': {'top': 28, 'right': 28, 'bottom': 28, 'left': 28, 'unit': 'px'},
        'padding_mobile': {'top': 22, 'right': 18, 'bottom': 22, 'left': 18, 'unit': 'px'},
        'background_background': 'classic', 'background_color': color,
        'border_border': 'solid', 'border_color': '#ffffffd9',
        'border_width': {'top': 1, 'right': 1, 'bottom': 1, 'left': 1, 'unit': 'px'},
        'border_radius': {'top': 28, 'right': 28, 'bottom': 28, 'left': 28, 'unit': 'px'},
        'css_classes': cls}, children)

def section(children, anchor='', cls=''):
    return element('container', {'content_width': 'boxed', 'boxed_width': {'size': 1200, 'unit': 'px'},
        'flex_direction': 'column', 'gap': {'size': 25, 'unit': 'px'},
        'padding': {'top': 60, 'right': 24, 'bottom': 60, 'left': 24, 'unit': 'px'},
        'padding_mobile': {'top': 36, 'right': 16, 'bottom': 36, 'left': 16, 'unit': 'px'},
        'css_classes': 'iaai-home-section iaai-glass-section ' + cls, 'css_id': anchor}, children)

manifest_path = ROOT / 'elementor/manifest.json'
manifest = json.loads(manifest_path.read_text())

def save(key, lang, title, slug, content, parent=None):
    entry = next((x for x in manifest['templates'] if x['key'] == key and x['locale'] == lang), None)
    if entry is None:
        entry = {'key': key, 'locale': lang, 'slug': slug, 'file': f'pages/{key}-{lang}.json',
            'private': False, 'draft': False, 'global': False, 'description': title}
        if parent:
            entry['parent'] = parent
        manifest['templates'].append(entry)
    if parent:
        entry['parent'] = parent
    else:
        entry.pop('parent', None)
    path = ROOT / 'elementor' / entry['file']
    data = {'version': '0.4', 'title': title, 'type': 'section' if entry.get('global') else 'page',
        'content': content, 'page_settings': {'hide_title': 'yes'}}
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')
    entry['hash'] = hashlib.sha256(json.dumps(data, ensure_ascii=False, sort_keys=True).encode()).hexdigest()

for lang in ('en', 'hi'):
    L = lambda en, hi: hi if lang == 'hi' else en
    url = lambda slug: ('/hi/' if lang == 'hi' else '/') + slug.strip('/') + '/'
    guide = url('insurance-guides')
    blog = url('blog')
    intro = L('KNOW MORE. WORRY LESS.', 'समझ बढ़े। चिंता घटे।')
    hero = section([box([
        box([text(intro), heading(L('A little clarity.<br>A more protected tomorrow.', 'आज बीमा समझें।<br>कल बेहतर तैयारी करें।'), 62, 'h1'),
            text(L('Health, term and policy guidance for real life. Explore your options, use our planning tools and learn one clear step at a time.', 'स्वास्थ्य, टर्म और पॉलिसी का आसान मार्गदर्शन। विकल्प जानें, गणना करें और एक-एक कदम पर बीमा समझें।')),
            box([button(L('Explore insurance →', 'बीमा विकल्प जानें →'), '#services'), button(L('Plan your cover ↗', 'कवर चुनें ↗'), '#calculators', True)], True, 'iaai-action-row', '#ffffff00'),
            text(L('✓ Plain-language guides &nbsp; ✓ Family-first planning &nbsp; ✓ Human support', '✓ आसान गाइड &nbsp; ✓ परिवार की योजना &nbsp; ✓ टीम का सहयोग'))], cls='iaai-hero-copy', color='#ffffff00'),
        box([photo('family'), heading(L('Your family. Your next chapter.', 'आपका परिवार। आपकी तैयारी।'), 25),
            text(L('Start with what matters to you — not a one-size-fits-all policy.', 'अपनी जरूरत से शुरू करें — हर परिवार की जरूरत अलग है।')),
            button(L('Help me choose →', 'चुनने में सहायता लें →'), url('contact'))], cls='iaai-glass iaai-hero-visual')
        ], True, 'iaai-hero-grid', '#ffffff00')], cls='iaai-hero-section')
    services = [('Health insurance', 'स्वास्थ्य बीमा', 'care', 'health-insurance', 'Understand hospitalisation cover, exclusions and your family’s needs.', 'अस्पताल खर्च का कवर, exclusions और परिवार की जरूरत समझें।'),
        ('Term protection', 'टर्म सुरक्षा', 'family', 'term-insurance', 'Plan for the people who depend on your income.', 'आपकी आय पर निर्भर लोगों के लिए तैयारी करें।'),
        ('Policy review', 'पॉलिसी समीक्षा', 'planning', 'policy-review', 'Make sense of an existing policy before your next decision.', 'अगले फैसले से पहले मौजूदा पॉलिसी की शर्तें समझें।'),
        ('Claim guidance', 'क्लेम मार्गदर्शन', 'wellbeing', 'claim-help', 'Organise your documents and understand the next step.', 'दस्तावेज व्यवस्थित करें और अगला कदम समझें।')]
    routes = section([text(L('A STARTING POINT FOR EVERY QUESTION', 'हर सवाल के लिए एक शुरुआत')), heading(L('What would you like to protect?', 'किसकी सुरक्षा की तैयारी है?')),
        box([box([photo(p, url(slug)), heading(L(en, hi), 24, 'h3'), text(L(desc, dh)), button(L('Explore →', 'जानें →'), url(slug), True)], cls='iaai-glass iaai-color-card iaai-service-card') for en, hi, p, slug, desc, dh in services], True, 'iaai-card-row', '#ffffff00')], 'services')
    calculators = section([box([
        box([photo('planning'), text(L('YOUR FAMILY. YOUR FUTURE.', 'आपका परिवार। आपका भविष्य।')), heading(L("Your family’s financial planning starts here.", 'परिवार की आर्थिक सुरक्षा की शुरुआत यहां से।'), 44),
            text(L('✓ Explore cover for your dependants<br>✓ Choose an amount that fits your responsibilities<br>✓ Understand policy terms before you decide', '✓ आश्रितों की सुरक्षा समझें<br>✓ जिम्मेदारी के अनुसार कवर चुनें<br>✓ फैसले से पहले पॉलिसी की शर्तें जानें'))], cls='iaai-planner-copy', color='#ffffff00'),
        box([element('iaai-termplanner', {'heading': L('Term cover planner', 'टर्म कवर प्लानर'), 'continue_label': L('Continue →', 'आगे बढ़ें →')})], cls='iaai-planner-widget-wrap', color='#ffffff00')
    ], True, 'iaai-planner-layout', '#ffffff00')], 'calculators')
    benefits = section([text(L('BENEFITS OF INSURANCE', 'बीमा के लाभ')), heading(L('Protection for life’s different moments.', 'जीवन के अलग पड़ावों की सुरक्षा।'), 42),
        text(L('Explore what different types of insurance can cover, and the questions to ask before choosing.', 'अलग बीमा क्या कवर कर सकते हैं और चुनने से पहले क्या पूछना है, जानें।')),
        box([box([photo(p), text(L(en, hi)), heading(L(title, th), 25, 'h3'), text(L(desc, dh)), button(L('View more →', 'और जानें →'), guide + '#' + anchor, True)], cls='iaai-glass iaai-benefit-card') for p,en,hi,title,th,desc,dh,anchor in [
            ('care','Health insurance','स्वास्थ्य बीमा','What is health insurance?','स्वास्थ्य बीमा क्या है?','Understand eligible hospital expenses, waiting periods and exclusions.','योग्य अस्पताल खर्च, वेटिंग पीरियड और exclusions समझें।','health-benefits'),
            ('family','Travel insurance','यात्रा बीमा','What is travel insurance?','यात्रा बीमा क्या है?','Learn about trip-related risks, emergency medical cover and policy limits.','यात्रा के जोखिम, आपात चिकित्सा कवर और सीमाएं जानें।','travel-benefits'),
            ('wellbeing','Personal accident insurance','व्यक्तिगत दुर्घटना बीमा','What is personal accident insurance?','दुर्घटना बीमा क्या है?','Explore specified accident benefits and the conditions that apply.','निर्दिष्ट दुर्घटना लाभ और लागू शर्तें समझें।','accident-benefits')]], True, 'iaai-card-row', '#ffffff00')], 'insurance-benefits', 'iaai-benefits-section')
    special = section([heading(L('Clarity looks good on you.', 'समझ के साथ बेहतर फैसले।')),
        box([box([heading(num, 45, 'div'), heading(L(en, hi), 25, 'h3'), text(L(desc, dh))], cls='iaai-glass iaai-color-card', color=color) for num, en, hi, desc, dh, color in [
            ('01', 'Know the terms', 'शर्तें समझें', 'Turn policy language into a useful checklist.', 'पॉलिसी की भाषा को उपयोगी चेकलिस्ट बनाएं।', '#eadfffbd'),
            ('02', 'Compare your needs', 'जरूरत की तुलना', 'Look beyond price at benefits and restrictions.', 'कीमत के साथ लाभ और सीमाएं भी देखें।', '#d5efffbd'),
            ('03', 'Prepare the next step', 'अगला कदम तैयार', 'Keep your questions and documents organised.', 'सवाल और दस्तावेज व्यवस्थित रखें।', '#ffe7d8bd')]], True, 'iaai-card-row', '#ffffff00')])
    examples = section([heading(L('Real-life questions. Sample journeys.', 'जीवन के सवाल। उदाहरण की तैयारी।')),
        text(L('Demo scenarios for layout and education — not customer testimonials or product offers.', 'डिजाइन और जानकारी के लिए demo उदाहरण — ग्राहक अनुभव या पॉलिसी ऑफर नहीं।')),
        box([box([photo(p), text(L('DEMO SCENARIO', 'DEMO उदाहरण')), heading(L(en, hi), 25, 'h3'), text(L(desc, dh)), button(L('Read the checklist →', 'चेकलिस्ट देखें →'), guide + anchor, True)], cls='iaai-glass iaai-color-card') for p, en, hi, desc, dh, anchor in [
            ('family', 'A young family', 'एक युवा परिवार', 'Start with dependants, expenses and existing cover.', 'आश्रित, खर्च और मौजूदा कवर से शुरू करें।', '#step-1'),
            ('care', 'Cover for parents', 'माता-पिता की सुरक्षा', 'Ask about waiting periods, co-pay and hospital networks.', 'वेटिंग पीरियड, को-पे और अस्पताल नेटवर्क पूछें।', '#step-3'),
            ('planning', 'An existing policy', 'मौजूदा पॉलिसी', 'Read the schedule, exclusions and renewal details.', 'शेड्यूल, exclusions और renewal विवरण देखें।', '#step-4')]], True, 'iaai-card-row', '#ffffff00')])
    guide_cards = section([text(L('INSURANCE, ONE STEP AT A TIME', 'बीमा, एक-एक कदम पर')), heading(L('Your guide to a more informed choice.', 'बेहतर समझ की पूरी शुरुआत।')),
        box([box([photo('care'), heading(L('A simple learning path', 'आसान learning path'), 27), text(L('From identifying your needs to keeping renewal and claim records organised.', 'जरूरत पहचानने से लेकर renewal और क्लेम रिकॉर्ड व्यवस्थित रखने तक।'))], cls='iaai-glass'),
            box([heading(L('01 Understand your needs', '01 अपनी जरूरत समझें'), 24, 'h3'), text(L('02 Learn the types • 03 Compare terms • 04 Read your policy', '02 प्रकार जानें • 03 शर्तें तुलना करें • 04 पॉलिसी पढ़ें')),
                text(L('05 Prepare disclosures • 06 Review documents • 07 Keep records • 08 Understand claims', '05 सही जानकारी तैयार करें • 06 दस्तावेज जांचें • 07 रिकॉर्ड रखें • 08 क्लेम समझें')),
                button(L('Open the complete guide →', 'पूरी गाइड खोलें →'), guide)], cls='iaai-glass')], True, 'iaai-card-row', '#ffffff00')], 'guides')
    article_cards = [box([photo(p, guide + anchor), text(L('SAMPLE READING TOPIC', 'उदाहरण विषय')), heading(L(en, hi), 24, 'h3'), text(L(desc, dh)), button(L('Explore the guide →', 'गाइड में पढ़ें →'), guide + anchor, True)], cls='iaai-glass iaai-color-card') for p, en, hi, desc, dh, anchor in [
        ('care', 'Before you choose health cover', 'स्वास्थ्य बीमा लेने से पहले', 'A checklist of questions for a policy discussion.', 'पॉलिसी चर्चा से पहले सवालों की चेकलिस्ट।', '#step-3'),
        ('family', 'How much protection do you need?', 'कितनी सुरक्षा की जरूरत है?', 'Start with expenses, debts and your dependants.', 'खर्च, कर्ज और आश्रितों से शुरुआत करें।', '#step-1'),
        ('planning', 'Make your documents work for you', 'दस्तावेज रखें व्यवस्थित', 'Keep policy terms and important records together.', 'पॉलिसी शर्तें और महत्वपूर्ण रिकॉर्ड साथ रखें।', '#step-7')]]
    blog_teaser = section([text(L('FROM THE JOURNAL', 'ब्लॉग से')), heading(L('Small reads. Useful understanding.', 'छोटे लेख। उपयोगी समझ।')),
        text(L('Latest published posts appear automatically. Sample topics below show the layout while you build your journal.', 'प्रकाशित ब्लॉग अपने-आप दिखाई देंगे। नीचे उदाहरण विषय हैं; दैनिक ब्लॉग जोड़कर अपना journal बनाएं।')),
        element('iaai-posts', {'posts_per_page': 3, 'show_pagination': '', 'empty_message': L('Your first published blog will appear here.', 'आपका पहला प्रकाशित ब्लॉग यहां दिखेगा।')}),
        box(article_cards, True, 'iaai-card-row', '#ffffff00'), button(L('Visit the blog →', 'ब्लॉग खोलें →'), blog)], 'journal')
    career = section([box([box([text(L('GROW WITH PURPOSE', 'उद्देश्य के साथ आगे बढ़ें')), heading(L('Make understanding your superpower.', 'जानकारी को अपनी ताकत बनाएं।'), 42),
        text(L('Interested in helping people understand insurance? Talk to our team about current opportunities.', 'लोगों को बीमा समझाने में रुचि है? मौजूदा अवसरों के लिए टीम से बात करें।')),
        button(L('Talk to the team ↗', 'टीम से बात करें ↗'), 'https://wa.me/919179826317')], cls='iaai-glass'),
        box([photo('planning'), text(L('Opportunities depend on current requirements. No placement or earnings promises.', 'अवसर वर्तमान जरूरत पर निर्भर हैं। नियुक्ति या आय का वादा नहीं।'))], cls='iaai-glass')], True, 'iaai-card-row', '#ffffff00')], cls='iaai-career-section')
    save('home', lang, L('Home', 'मुखपृष्ठ'), 'hi' if lang == 'hi' else 'home', [hero, routes, calculators, benefits, special, examples, guide_cards, blog_teaser, career])
    save('blog', lang, L('Insurance Blog', 'बीमा ब्लॉग'), 'blog', [section([text(L('THE INSURANCE JOURNAL', 'बीमा journal')), heading(L('Read a little. Understand a lot.', 'थोड़ा पढ़ें। ज्यादा समझें।'), 54, 'h1'), text(L('Daily stories, explainers and practical checklists to help you understand insurance. Discover the latest articles from our team.', 'बीमा की समझ बढ़ाने के लिए दैनिक लेख, आसान जानकारी और उपयोगी चेकलिस्ट। टीम के नए लेख यहां पढ़ें।'))]),
        section([element('iaai-posts', {'posts_per_page': 9, 'show_pagination': 'yes', 'empty_message': L('No articles published yet. Explore our insurance guide while the journal is being prepared.', 'अभी ब्लॉग प्रकाशित नहीं हुए हैं। इस बीच बीमा गाइड पढ़ें।')}), button(L('Start with the insurance guide →', 'बीमा गाइड से शुरुआत →'), guide)])], 'home/hi' if lang == 'hi' else None)
    steps = [
        ('Start with your life', 'अपनी जरूरत से शुरू करें', 'List the people who depend on you, regular expenses, loans, savings and any existing cover. Health cover and income protection address different needs.', 'आश्रित लोगों, नियमित खर्च, कर्ज, बचत और मौजूदा कवर की सूची बनाएं। स्वास्थ्य खर्च और आय की सुरक्षा अलग जरूरतें हैं।', 'family'),
        ('Understand the different types', 'बीमा के प्रकार समझें', 'Health insurance addresses eligible medical expenses. Term insurance focuses on life protection for a chosen period. Savings and market-linked products have different benefits, costs and risks; compare the actual wording.', 'स्वास्थ्य बीमा योग्य चिकित्सा खर्च से जुड़ा है। टर्म बीमा चुनी अवधि की जीवन सुरक्षा पर केंद्रित है। बचत और market-linked योजनाओं के लाभ, शुल्क और जोखिम अलग होते हैं; वास्तविक शर्तें पढ़ें।', 'care'),
        ('Compare more than the price', 'कीमत से आगे तुलना करें', 'Ask about cover amount, waiting periods, exclusions, co-pay, deductibles, room limits and the hospital network where relevant. Ask which benefits apply to your exact plan.', 'संबंधित योजना में कवर, वेटिंग पीरियड, exclusions, को-पे, deductible, room limit और अस्पताल नेटवर्क पूछें। आपकी चुनी योजना में कौन से लाभ लागू हैं, स्पष्ट करें।', 'care'),
        ('Read the policy documents', 'पॉलिसी दस्तावेज पढ़ें', 'Read the schedule, policy wording and Customer Information Sheet where provided. Check who is covered, the benefits, exclusions, premium obligations and cancellation terms. Ask about anything you do not understand.', 'शेड्यूल, पॉलिसी wording और उपलब्ध Customer Information Sheet पढ़ें। बीमित व्यक्ति, लाभ, exclusions, premium भुगतान और cancellation की शर्तें देखें। अस्पष्ट बातों पर सवाल पूछें।', 'planning'),
        ('Prepare complete, accurate disclosures', 'सही और पूरी जानकारी तैयार करें', 'Answer application questions accurately, including relevant health, occupation and existing insurance information. Review the filled proposal before submitting and keep a copy. Do not share medical records in a public comment.', 'आवेदन के सवालों में स्वास्थ्य, पेशा और मौजूदा बीमा की संबंधित जानकारी सही दें। जमा करने से पहले भरा proposal जांचें और उसकी copy रखें। सार्वजनिक comment में मेडिकल रिकॉर्ड न डालें।', 'planning'),
        ('Review what you receive', 'मिली पॉलिसी जांचें', 'When your documents arrive, compare them with the proposal and agreed terms. Check names, benefits, nominee information and contact details. Ask the insurer about correction and applicable review or cancellation options.', 'दस्तावेज मिलने पर proposal और सहमत शर्तों से तुलना करें। नाम, लाभ, nominee और संपर्क विवरण जांचें। सुधार और लागू review या cancellation विकल्प कंपनी से पूछें।', 'family'),
        ('Keep a simple insurance folder', 'बीमा का आसान रिकॉर्ड रखें', 'Keep the policy, payment receipts, renewal reminders and insurer contacts in one secure place. Tell a trusted family member how to find them. Review cover when your family, income or responsibilities change.', 'पॉलिसी, भुगतान receipt, renewal reminder और कंपनी के संपर्क सुरक्षित रखें। विश्वसनीय परिवार सदस्य को रिकॉर्ड का पता बताएं। परिवार, आय या जिम्मेदारी बदलने पर कवर की समीक्षा करें।', 'planning'),
        ('Know the claim and support process', 'क्लेम और सहायता प्रक्रिया जानें', 'Use the insurer’s official claim channels and follow your policy’s requirements. Keep bills and relevant records; ask about cashless or reimbursement steps where applicable. If a decision is unclear, request a written explanation and the current grievance route.', 'कंपनी के official claim channels और अपनी पॉलिसी की प्रक्रिया अपनाएं। बिल और संबंधित रिकॉर्ड रखें; लागू cashless या reimbursement steps पूछें। निर्णय अस्पष्ट हो तो लिखित वजह और वर्तमान शिकायत प्रक्रिया जानें।', 'care')]
    guide_content = [section([text(L('THE COMPLETE STARTING PATH', 'समझ की पूरी शुरुआत')), heading(L('Insurance, step by step.', 'बीमा, step by step।'), 54, 'h1'), text(L('A practical orientation, not a substitute for your actual policy. Benefits and processes vary by insurer and plan. Illustrative photographs do not represent customers or insurer endorsements.', 'आसान जानकारी की शुरुआत; आपकी वास्तविक पॉलिसी का विकल्प नहीं। लाभ और प्रक्रिया कंपनी व योजना अनुसार बदलते हैं। तस्वीरें उदाहरण हैं; ग्राहक या बीमा कंपनी का endorsement नहीं।')),
        box([button(f'{i:02} · ' + L(en, hi), '#step-' + str(i), True) for i, (en, hi, *_rest) in enumerate(steps, 1)], True, 'iaai-step-index', '#ffffff00')])]
    for i, (en, hi, desc, dh, p) in enumerate(steps, 1):
        guide_content.append(section([box([box([photo(p)], cls='iaai-guide-image', color='#ffffff00'), box([text(L('STEP', 'कदम') + f' {i:02} / 08'), heading(L(en, hi), 32), text(L(desc, dh)),
            button(L('Next step →', 'अगला कदम →') if i < 8 else L('Ask the team →', 'टीम से पूछें →'), '#step-' + str(i+1) if i < 8 else url('contact'), True)], cls='iaai-glass')], True, 'iaai-guide-row', '#ffffff00')], 'step-' + str(i)))
    for anchor,en,hi,desc,dh,p in [
        ('health-benefits','Health insurance','स्वास्थ्य बीमा','Health policies can cover eligible medical expenses subject to their wording. Check waiting periods, exclusions, co-pay, room limits and the hospital network. Cashless service depends on authorisation and network arrangements.','स्वास्थ्य पॉलिसी अपनी शर्तों के अनुसार योग्य चिकित्सा खर्च कवर कर सकती है। वेटिंग पीरियड, exclusions, को-पे, room limit और अस्पताल नेटवर्क देखें। Cashless सुविधा अनुमति और नेटवर्क व्यवस्था पर निर्भर है।','care'),
        ('travel-benefits','Travel insurance','यात्रा बीमा','Travel policies may include emergency medical expenses, trip cancellation, baggage or delay benefits. Cover varies by destination, duration and plan. Check exclusions for activities, pre-existing conditions and the claim documents required.','यात्रा पॉलिसी में आपात चिकित्सा, यात्रा cancellation, baggage या delay लाभ हो सकते हैं। कवर गंतव्य, अवधि और योजना पर निर्भर है। गतिविधियों, पहले से मौजूद स्वास्थ्य स्थिति और क्लेम दस्तावेजों की शर्तें देखें।','family'),
        ('accident-benefits','Personal accident insurance','व्यक्तिगत दुर्घटना बीमा','Personal accident cover may provide specified benefits for accidental death or disability. It is different from comprehensive health and term cover. Check the definitions, benefit schedule, occupation restrictions and claim requirements.','दुर्घटना बीमा दुर्घटना से निधन या disability पर निर्दिष्ट लाभ दे सकता है। यह व्यापक स्वास्थ्य या टर्म कवर से अलग है। परिभाषाएं, लाभ तालिका, पेशे की सीमाएं और क्लेम प्रक्रिया पढ़ें।','wellbeing')]:
        guide_content.append(section([box([box([photo(p)],cls='iaai-guide-image',color='#ffffff00'),box([heading(L(en,hi),32),text(L(desc,dh)),button(L('Discuss your questions →','अपने सवाल पूछें →'),url('contact'))])],True,'iaai-guide-row','#ffffff00')],anchor))
    guide_content.append(section([heading(L('Keep learning at your pace.', 'अपने समय पर सीखते रहें।')), text(L('Explore term cover, read our journal, or discuss a specific question with the team.', 'योजना के लिए calculators उपयोग करें, ब्लॉग पढ़ें या टीम से अपना सवाल पूछें।')), box([button(L('Plan your cover →', 'कवर चुनें →'), ('/hi/' if lang == 'hi' else '/') + '#calculators'), button(L('Read the blog →', 'ब्लॉग पढ़ें →'), blog, True)], True, 'iaai-action-row', '#ffffff00')]))
    guide_content.append(section([heading(L('Reliable places to keep learning', 'विश्वसनीय जानकारी के स्रोत'), 28), text(L('Read official policyholder resources and check your insurer’s current policy documents before making a decision.', 'फैसले से पहले official policyholder जानकारी और अपनी कंपनी के वर्तमान दस्तावेज पढ़ें।')), box([button('IRDAI Policyholder Portal ↗', 'https://policyholder.gov.in/', True), button('IRDAI ↗', 'https://irdai.gov.in/', True)], True, 'iaai-action-row', '#ffffff00')]))
    save('insurance-guides', lang, L('Insurance Guides', 'बीमा गाइड'), 'insurance-guides', guide_content, 'home/hi' if lang == 'hi' else None)
    # Upgrade the existing learning hub without touching editorial draft gates.
    save('learn', lang, L('Learning hub', 'जानकारी केंद्र'), 'learn', [section([heading(L('Build your insurance understanding.', 'बीमा की समझ बढ़ाएं।'), 54, 'h1'), text(L('Start with the eight-step orientation, then explore daily articles and checklists at your own pace.', 'आठ कदम की सामान्य जानकारी से शुरू करें और अपने समय पर दैनिक लेख व चेकलिस्ट पढ़ें।')), box([photo('care'), box([button(L('Complete insurance guide →', 'पूरी बीमा गाइड →'), guide), button(L('Daily blog →', 'दैनिक ब्लॉग →'), blog, True)], cls='iaai-glass')], True, 'iaai-card-row', '#ffffff00')])], 'home/hi' if lang == 'hi' else None)
    header_path = ROOT / f'elementor/global/header-{lang}.json'
    header = json.loads(header_path.read_text())
    children = header['content'][0]['elements']
    children[0]['elements'] = [element('image', {'image': {'url': ASSET + 'logo-purple.png', 'id': 0}, 'image_size': 'full', 'width': {'size': 220, 'unit': 'px'}, 'width_mobile': {'size': 190, 'unit': 'px'}, 'link_to': 'custom', 'link': {'url': '/hi/' if lang == 'hi' else '/'}})]
    links = [('health-insurance', 'Services', 'सेवाएं'), ('insurance-guides', 'Guides', 'गाइड'), ('blog', 'Blog', 'ब्लॉग'), ('about', 'About', 'परिचय'), ('contact', 'Contact', 'संपर्क')]
    children[1] = text('<nav aria-label="Main">' + ''.join(f'<a href="{url(s)}">{L(en, hi)}</a>' for s, en, hi in links) + '</nav>')
    save('header', lang, header['title'], 'header', header['content'])
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
print('Updated glass home, learning hub, header and new blog/guide pages; native Elementor widgets retained.')
